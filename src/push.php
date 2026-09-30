<?php
declare(strict_types=1);

/*
 * Sending a notification to a browser that is not open.
 *
 * This is the half that makes Chrome ring on a locked phone. The browser hands
 * the page an endpoint and two keys when it subscribes; the center then posts an
 * encrypted message to that endpoint, and Chrome's own service wakes the device
 * and shows it. The page need not be open, or even to exist any more.
 *
 * Two pieces of cryptography are required, and both are done here rather than
 * pulled in, because this installation has no Composer:
 *
 *   VAPID    a short-lived JWT signed with the center's own P-256 key, which is
 *            how the push service knows the message came from this site and not
 *            from whoever else learned the endpoint. RFC 8292.
 *
 *   aes128gcm the message itself, encrypted to the browser's key so that the
 *            push service carries it without being able to read it. RFC 8291.
 *
 * Nothing here is allowed to break a save: every failure is swallowed, and a
 * subscription the push service reports as dead is quietly deleted.
 */

require_once __DIR__ . '/paths.php';

// XAMPP's PHP cannot find its own openssl.cnf unless it is told, and generating
// a key fails with an obscure "no such file" without it.
const KHOTWA_OPENSSL_CONF = 'C:/xampp/php/extras/openssl/openssl.cnf';

/**
 * The key pair this site signs with, written by tools/generate-vapid-keys.php.
 *
 * @return array{public: string, private: string, subject: string}|null
 */
function push_keys(): ?array
{
    static $keys = null;
    if ($keys !== null) {
        return $keys === [] ? null : $keys;
    }

    $file = __DIR__ . '/push-keys.php';
    if (!is_file($file)) {
        $keys = [];
        return null;
    }

    $loaded = require $file;
    if (!is_array($loaded) || ($loaded['public'] ?? '') === '' || ($loaded['private'] ?? '') === '') {
        $keys = [];
        return null;
    }

    $keys = [
        'public' => (string) $loaded['public'],
        'private' => (string) $loaded['private'],
        'subject' => (string) ($loaded['subject'] ?? 'mailto:khotwacenter.lb@gmail.com'),
    ];

    return $keys;
}

function push_is_configured(): bool
{
    return push_keys() !== null;
}

/**
 * Base64 as the web push specs use it: url-safe, unpadded.
 */
function push_b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function push_b64_decode(string $value): string
{
    $padded = strtr($value, '-_', '+/');
    $remainder = strlen($padded) % 4;
    if ($remainder > 0) {
        $padded .= str_repeat('=', 4 - $remainder);
    }

    return (string) base64_decode($padded, true);
}

/**
 * A raw 65-byte public point, wrapped as the PEM that OpenSSL will accept.
 *
 * The prefix is the fixed ASN.1 header for an uncompressed prime256v1 point;
 * only the point itself changes from one browser to the next.
 */
function push_public_pem(string $point): string
{
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $point;

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

/**
 * The 65-byte public point of a key OpenSSL is holding.
 */
function push_point_of(mixed $key): string
{
    $details = openssl_pkey_get_details($key);
    if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
        throw new RuntimeException('That key carries no EC point.');
    }

    return "\x04"
        . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
}

/**
 * An ECDSA signature as JOSE wants it: r and s, 32 bytes each, no ASN.1.
 *
 * OpenSSL hands back a DER SEQUENCE of two INTEGERs, which carry a leading zero
 * whenever the high bit is set and drop leading zeroes otherwise - so both are
 * read out and re-padded to a fixed width.
 */
function push_signature_to_raw(string $der): string
{
    $offset = 0;
    $readInteger = static function (string $der, int &$offset): string {
        if (($der[$offset] ?? '') !== "\x02") {
            throw new RuntimeException('Malformed signature.');
        }
        $length = ord($der[$offset + 1]);
        $value = substr($der, $offset + 2, $length);
        $offset += 2 + $length;

        return str_pad(ltrim($value, "\x00"), 32, "\x00", STR_PAD_LEFT);
    };

    if (($der[0] ?? '') !== "\x30") {
        throw new RuntimeException('Malformed signature.');
    }
    // Skip the SEQUENCE header, which is two bytes at this size.
    $offset = 2;

    return $readInteger($der, $offset) . $readInteger($der, $offset);
}

/**
 * The Authorization header one push service will accept.
 *
 * The token is bound to that service's origin and expires, so the same header
 * cannot be lifted and replayed against a different one.
 */
function push_vapid_header(string $endpoint): ?string
{
    $keys = push_keys();
    if ($keys === null) {
        return null;
    }

    $parts = parse_url($endpoint);
    if (!isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $header = push_b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = push_b64((string) json_encode([
        'aud' => $parts['scheme'] . '://' . $parts['host'],
        'exp' => time() + 43200,
        'sub' => $keys['subject'],
    ]));

    $privateKey = openssl_pkey_get_private($keys['private']);
    if ($privateKey === false) {
        return null;
    }

    $der = '';
    if (!openssl_sign($header . '.' . $claims, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
        return null;
    }

    try {
        $signature = push_b64(push_signature_to_raw($der));
    } catch (Throwable $exception) {
        return null;
    }

    return 'vapid t=' . $header . '.' . $claims . '.' . $signature . ', k=' . $keys['public'];
}

/**
 * The message body, encrypted to one browser.
 *
 * Layout, per RFC 8188: the salt, the record size, the length of the sender's
 * public key, that key, and then the ciphertext with its tag.
 */
function push_encrypt(string $payload, string $userPublicKey, string $authSecret): ?string
{
    try {
        $userPoint = push_b64_decode($userPublicKey);
        $auth = push_b64_decode($authSecret);
        if (strlen($userPoint) !== 65 || strlen($auth) < 16) {
            return null;
        }

        $ephemeral = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'config' => KHOTWA_OPENSSL_CONF,
        ]);
        if ($ephemeral === false) {
            return null;
        }

        $senderPoint = push_point_of($ephemeral);
        $shared = openssl_pkey_derive(push_public_pem($userPoint), $ephemeral, 32);
        if ($shared === false) {
            return null;
        }

        // RFC 8291: the browser's key and ours are both mixed into the secret,
        // so a message can only be read by the subscription it was sealed for.
        $prk = hash_hkdf(
            'sha256',
            $shared,
            32,
            "WebPush: info\x00" . $userPoint . $senderPoint,
            $auth
        );

        $salt = random_bytes(16);
        $contentKey = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

        $tag = '';
        // 0x02 ends the record; nothing is padded, because the payload is small.
        $cipher = openssl_encrypt(
            $payload . "\x02",
            'aes-128-gcm',
            $contentKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        if ($cipher === false) {
            return null;
        }

        return $salt . pack('N', 4096) . chr(65) . $senderPoint . $cipher . $tag;
    } catch (Throwable $exception) {
        return null;
    }
}

/**
 * Post one encrypted message to one endpoint.
 *
 * 'gone' is the answer that matters to the caller: the push service saying 404
 * or 410 is how a browser reports that it has unsubscribed, and the only sane
 * response is to stop trying.
 *
 * @param array<string, string> $payload
 * @return array{sent: bool, status: int, gone: bool}
 */
function push_deliver(string $endpoint, string $publicKey, string $authToken, array $payload): array
{
    $failed = ['sent' => false, 'status' => 0, 'gone' => false];

    if (!push_is_configured() || !function_exists('curl_init') || $endpoint === '') {
        return $failed;
    }

    $body = push_encrypt(
        (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        $publicKey,
        $authToken
    );
    $authorization = push_vapid_header($endpoint);
    if ($body === null || $authorization === null) {
        return $failed;
    }

    $handle = curl_init($endpoint);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        // Short: a push service that is not answering must not hold up the save
        // that raised the notification.
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: 86400',
            'Urgency: normal',
            'Content-Length: ' . strlen($body),
        ],
    ]);

    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return [
        'sent' => $status >= 200 && $status < 300,
        'status' => $status,
        'gone' => $status === 404 || $status === 410,
    ];
}
