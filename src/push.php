<?php
declare(strict_types=1);

/*
 * Web Push: reaching a phone with the site closed.
 *
 * A notification row is what the bell reads; this file is what makes a phone
 * buzz for it. Chrome never accepts a push straight from us - it is handed to
 * the push service whose address the browser chose (Google's, for Chrome), and
 * that service wakes the phone. Two pieces of cryptography make that safe, and
 * both are done here by hand rather than by a library, because this machine has
 * no composer and openssl already carries everything the two need:
 *
 *   - VAPID (RFC 8292) signs a short-lived token with the center's own key, so
 *     the push service knows the push came from this site and not from anyone
 *     who happened to copy an endpoint.
 *   - aes128gcm (RFC 8291) encrypts the message to the browser's own key, so
 *     the push service carries the words without being able to read them.
 *
 * Nothing here is allowed to break a save: every failure is swallowed and the
 * notification still sits in the bell, which is the part that always works.
 */

require_once __DIR__ . '/paths.php';

/** The prime256v1 curve, the only one Web Push uses. */
const PUSH_CURVE = 'prime256v1';

/*
 * The DER preamble of a P-256 public key. A browser hands its key over as 65
 * raw bytes; openssl will only take a structured one, and for this one curve
 * the structure ahead of those bytes is always these same 26.
 */
const PUSH_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

/** Base64 as the web uses it in URLs: no padding, and two characters swapped. */
function push_b64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function push_b64url_decode(string $value): string
{
    $padded = strtr($value, '-_', '+/');
    $padded .= str_repeat('=', (4 - (strlen($padded) % 4)) % 4);

    return (string) base64_decode($padded, true);
}

/**
 * The center's own key pair, as the two settings hold it.
 *
 * @return array{public: string, private: string}
 */
function push_vapid_keys(): array
{
    return [
        'public' => defined('KHOTWA_VAPID_PUBLIC_KEY') ? (string) KHOTWA_VAPID_PUBLIC_KEY : '',
        'private' => defined('KHOTWA_VAPID_PRIVATE_KEY') ? (string) KHOTWA_VAPID_PRIVATE_KEY : '',
    ];
}

/**
 * Who the push service should complain to, if a push goes wrong.
 *
 * The spec wants a way to reach the sender. The center's own address is the
 * honest answer, and it is already a setting.
 */
function push_vapid_subject(): string
{
    if (defined('KHOTWA_VAPID_SUBJECT') && (string) KHOTWA_VAPID_SUBJECT !== '') {
        return (string) KHOTWA_VAPID_SUBJECT;
    }

    return defined('KHOTWA_CENTER_EMAIL')
        ? 'mailto:' . KHOTWA_CENTER_EMAIL
        : 'mailto:info@khotwaeducation.com';
}

/**
 * Where openssl's own config file is, if it has to be pointed at one.
 *
 * Generating a key is the one call here that reads it, and on Windows openssl
 * ships without knowing where it is: XAMPP carries two copies and sets the
 * environment variable for neither, so the call fails with "no such file"
 * rather than anything about keys. Found once and remembered, since it cannot
 * change while the process is up. KHOTWA_OPENSSL_CONF overrides it on a server
 * that keeps it somewhere else.
 */
function push_openssl_config(): ?string
{
    static $found = false;
    static $path = null;

    if ($found) {
        return $path;
    }
    $found = true;

    $candidates = [];
    if (defined('KHOTWA_OPENSSL_CONF')) {
        $candidates[] = (string) KHOTWA_OPENSSL_CONF;
    }
    $fromEnvironment = getenv('OPENSSL_CONF');
    if (is_string($fromEnvironment) && $fromEnvironment !== '') {
        $candidates[] = $fromEnvironment;
    }
    $candidates[] = 'C:/xampp/apache/conf/openssl.cnf';
    $candidates[] = 'C:/xampp/php/extras/ssl/openssl.cnf';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }

    return $path;
}

/** Whether a push can be sent at all: both halves of the key, and curl. */
function push_is_configured(): bool
{
    $keys = push_vapid_keys();

    return $keys['public'] !== ''
        && $keys['private'] !== ''
        && function_exists('curl_init')
        && function_exists('openssl_pkey_derive');
}

/**
 * A fresh P-256 pair, as the raw bytes Web Push passes around.
 *
 * Used once to make the center's own key, and again for every single push: each
 * message is encrypted under a throwaway pair, which is what keeps one captured
 * message from opening the others.
 *
 * @return array{public: string, private: string, resource: OpenSSLAsymmetricKey}|null
 */
function push_generate_keypair(): ?array
{
    $arguments = [
        'curve_name' => PUSH_CURVE,
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];
    $config = push_openssl_config();
    if ($config !== null) {
        $arguments['config'] = $config;
    }

    $key = openssl_pkey_new($arguments);
    if ($key === false) {
        return null;
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || !isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
        return null;
    }

    return [
        // 0x04 says "both halves follow, uncompressed" - the only form used here.
        'public' => "\x04"
            . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT),
        'private' => str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT),
        'resource' => $key,
    ];
}

/**
 * The browser's 65 raw bytes, wrapped in the DER an openssl call will accept.
 */
function push_public_key_resource(string $rawPoint)
{
    if (strlen($rawPoint) !== 65 || $rawPoint[0] !== "\x04") {
        return false;
    }

    $der = hex2bin(PUSH_SPKI_PREFIX) . $rawPoint;

    return openssl_pkey_get_public(
        "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n"
    );
}

/**
 * A stored private key, rebuilt into one openssl will sign with.
 *
 * The settings hold the 32 secret bytes and the 65 public ones, because those
 * are the forms the browser and the push service speak. openssl wants the SEC1
 * structure around them, which is assembled here: the lengths are fixed, since
 * P-256 is the only curve in play.
 */
function push_private_key_resource(string $rawPrivate, string $rawPublic)
{
    if (strlen($rawPrivate) !== 32 || strlen($rawPublic) !== 65) {
        return false;
    }

    $der = "\x30\x77"                                       // SEQUENCE, 119 bytes
        . "\x02\x01\x01"                                    // version 1
        . "\x04\x20" . $rawPrivate                          // the secret scalar
        . "\xa0\x0a\x06\x08" . hex2bin('2a8648ce3d030107')  // [0] curve: prime256v1
        . "\xa1\x44\x03\x42\x00" . $rawPublic;              // [1] the matching public point

    return openssl_pkey_get_private(
        "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n"
    );
}

/**
 * An ECDSA signature, from the DER openssl returns to the 64 flat bytes a JWT wants.
 *
 * openssl wraps the two halves as DER integers, which are signed - so a half
 * whose top bit is set gains a leading zero, and a short one loses leading
 * zeros. Both are undone here and each half is laid out at its full 32 bytes.
 */
function push_der_signature_to_raw(string $der): string
{
    $offset = 0;
    if (($der[$offset] ?? '') !== "\x30") {
        return '';
    }
    // A P-256 signature is short enough that its length is always one byte.
    $offset += 2;

    $readInteger = static function (string $der, int &$offset): string {
        if (($der[$offset] ?? '') !== "\x02") {
            return '';
        }
        $offset++;
        $length = ord($der[$offset] ?? "\0");
        $offset++;
        $value = substr($der, $offset, $length);
        $offset += $length;

        return str_pad(ltrim($value, "\0"), 32, "\0", STR_PAD_LEFT);
    };

    $r = $readInteger($der, $offset);
    $s = $readInteger($der, $offset);

    return strlen($r) === 32 && strlen($s) === 32 ? $r . $s : '';
}

/**
 * The VAPID header pair for one push service.
 *
 * The token is good for twelve hours and names the service it was made for, so
 * a token taken from one push cannot be replayed at another.
 *
 * @return array<int, string>|null  The Authorization line, then the encoding.
 */
function push_vapid_headers(string $endpoint): ?array
{
    $keys = push_vapid_keys();
    $parts = parse_url($endpoint);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $audience = $parts['scheme'] . '://' . $parts['host'];

    $header = push_b64url_encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = push_b64url_encode((string) json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => push_vapid_subject(),
    ]));

    $privateKey = push_private_key_resource(
        push_b64url_decode($keys['private']),
        push_b64url_decode($keys['public'])
    );
    if ($privateKey === false) {
        return null;
    }

    $der = '';
    if (!openssl_sign($header . '.' . $claims, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
        return null;
    }

    $raw = push_der_signature_to_raw($der);
    if ($raw === '') {
        return null;
    }

    return [
        'Authorization: vapid t=' . $header . '.' . $claims . '.' . push_b64url_encode($raw)
            . ', k=' . $keys['public'],
        'Content-Encoding: aes128gcm',
    ];
}

/**
 * One message, sealed to one browser.
 *
 * The recipe is RFC 8291's. A throwaway key pair is agreed with the browser's
 * key; that shared secret, stirred together with the browser's auth secret and
 * both public keys, yields the key and nonce the message is encrypted under.
 * The result carries its own salt and public key at the front, which is how the
 * browser can work out the same key without anything else being sent.
 */
function push_encrypt(string $payload, string $clientPublicKey, string $authSecret): ?string
{
    $clientPublic = push_b64url_decode($clientPublicKey);
    $auth = push_b64url_decode($authSecret);
    if (strlen($clientPublic) !== 65 || strlen($auth) !== 16) {
        return null;
    }

    $local = push_generate_keypair();
    if ($local === null) {
        return null;
    }

    $clientResource = push_public_key_resource($clientPublic);
    if ($clientResource === false) {
        return null;
    }

    $sharedSecret = openssl_pkey_derive($clientResource, $local['resource'], 32);
    if ($sharedSecret === false) {
        return null;
    }

    /*
     * The two public keys go into the mix in a fixed order - the browser's
     * first, ours second - because the browser will stir them the same way and
     * the two sides must land on the same key.
     */
    $keyInfo = "WebPush: info\0" . $clientPublic . $local['public'];
    $pseudoRandomKey = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $auth);

    $salt = random_bytes(16);
    $contentKey = hash_hkdf('sha256', $pseudoRandomKey, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $pseudoRandomKey, 12, "Content-Encoding: nonce\0", $salt);

    // 0x02 closes the one and only record; there is no padding after it.
    $tag = '';
    $ciphertext = openssl_encrypt(
        $payload . "\x02",
        'aes-128-gcm',
        $contentKey,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );
    if ($ciphertext === false) {
        return null;
    }

    /*
     * The header the browser reads before it can decrypt anything: the salt,
     * the record size, and our throwaway public key with its length ahead of it.
     */
    return $salt
        . pack('N', 4096)
        . chr(strlen($local['public']))
        . $local['public']
        . $ciphertext . $tag;
}

/**
 * Hand one sealed message to one push service.
 *
 * The answer matters in one case beyond success: a browser that has been wiped
 * or has revoked permission answers 404 or 410, and that subscription is dead
 * for good. Saying so lets the caller drop the row rather than retry it forever.
 *
 * @return array{ok: bool, gone: bool, status: int}
 */
function push_deliver(string $endpoint, string $publicKey, string $authToken, array $payload): array
{
    $failed = ['ok' => false, 'gone' => false, 'status' => 0];

    if (!push_is_configured()) {
        return $failed;
    }

    $body = push_encrypt((string) json_encode($payload), $publicKey, $authToken);
    if ($body === null) {
        return $failed;
    }

    $headers = push_vapid_headers($endpoint);
    if ($headers === null) {
        return $failed;
    }

    $handle = curl_init($endpoint);
    if ($handle === false) {
        return $failed;
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => array_merge($headers, [
            'Content-Type: application/octet-stream',
            // How long the service should hold it for a phone that is off.
            'TTL: 86400',
            'Urgency: normal',
        ]),
    ]);

    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    return [
        'ok' => $status >= 200 && $status < 300,
        'gone' => $status === 404 || $status === 410,
        'status' => $status,
    ];
}
