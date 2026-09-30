<?php
declare(strict_types=1);

/*
 * The one-off that gives this installation its push identity.
 *
 * Web push asks a site to sign every message with a key pair of its own: the
 * public half is handed to each browser when it subscribes, and the private half
 * signs the messages that follow. Browsers remember the public half, so changing
 * it later invalidates every subscription already granted - which is why this
 * refuses to overwrite a pair that already exists unless asked to.
 *
 *   php tools/generate-vapid-keys.php          write the pair, once
 *   php tools/generate-vapid-keys.php --force  replace it, dropping every
 *                                              subscription that used the old one
 *
 * The pair is written to src/push-keys.php, which is git-ignored: it belongs to
 * this machine, not to the project.
 */

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/push.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line.\n");
}

$target = __DIR__ . '/../src/push-keys.php';
$force = in_array('--force', $argv ?? [], true);

if (is_file($target) && !$force) {
    $existing = require $target;
    echo "A key pair already exists.\n";
    echo "  public: " . (string) ($existing['public'] ?? '') . "\n\n";
    echo "Every browser that subscribed knows this key. Pass --force only if you\n";
    echo "accept that replacing it silently stops notifications reaching them.\n";
    exit(0);
}

$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
    'config' => KHOTWA_OPENSSL_CONF,
]);

if ($key === false) {
    echo "Could not generate a key.\n";
    while ($error = openssl_error_string()) {
        echo "  {$error}\n";
    }
    exit(1);
}

$privatePem = '';
if (!openssl_pkey_export($key, $privatePem, null, ['config' => KHOTWA_OPENSSL_CONF])) {
    echo "Could not export the private key.\n";
    exit(1);
}

$publicKey = push_b64(push_point_of($key));

$contents = "<?php\ndeclare(strict_types=1);\n\n"
    . "/*\n"
    . " * This machine's web push identity. Written by tools/generate-vapid-keys.php\n"
    . " * on " . date('d/m/Y') . ", and never committed: the private half signs every\n"
    . " * notification this site sends, and the public half is what the browsers that\n"
    . " * already subscribed are expecting to see.\n"
    . " */\n\n"
    . "return [\n"
    . "    'public' => " . var_export($publicKey, true) . ",\n"
    . "    'private' => " . var_export($privatePem, true) . ",\n"
    . "    'subject' => " . var_export('mailto:khotwacenter.lb@gmail.com', true) . ",\n"
    . "];\n";

if (file_put_contents($target, $contents) === false) {
    echo "Could not write {$target}.\n";
    exit(1);
}

echo "Key pair written to src/push-keys.php\n";
echo "  public key: {$publicKey}\n\n";
echo "Chrome will only use it on https, or on http://localhost.\n";
