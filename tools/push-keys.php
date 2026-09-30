<?php
declare(strict_types=1);

/*
 * Makes the center's own push key pair, once.
 *
 * Run it from the command line, paste the two lines it prints into
 * src/db-config.php, and push notifications are configured. Run it a second
 * time and every browser already subscribed goes deaf: their subscriptions were
 * signed against the old public key and the push service will refuse the new
 * one. So it prints rather than writes, and says so.
 *
 *   php tools/push-keys.php
 */

require_once __DIR__ . '/../src/push.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool runs from the command line only.\n");
}

$pair = push_generate_keypair();
if ($pair === null) {
    exit("Could not generate a key pair - openssl is not available with EC support.\n");
}

$existing = push_vapid_keys();
if ($existing['public'] !== '') {
    echo "A key pair is already configured.\n";
    echo "Replacing it silences every browser that has already subscribed.\n";
    echo "Only continue if that is what you want.\n\n";
}

echo "Add these to src/db-config.php:\n\n";
echo "define('KHOTWA_VAPID_PUBLIC_KEY', '" . push_b64url_encode($pair['public']) . "');\n";
echo "define('KHOTWA_VAPID_PRIVATE_KEY', '" . push_b64url_encode($pair['private']) . "');\n";
echo "\nThe private one is a secret. db-config.php is never committed, which is why it belongs there.\n";
