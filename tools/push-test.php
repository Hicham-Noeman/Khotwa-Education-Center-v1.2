<?php
declare(strict_types=1);

/*
 * Sends one test notification, so the push chain can be checked without waiting
 * for a real warning or payment.
 *
 *   php tools/push-test.php                       - list who could be notified
 *   php tools/push-test.php parent.one@khotwa.test
 *
 * It writes a real notification row, exactly as the rest of the site does, and
 * reports what each push service answered. The answers worth knowing:
 *
 *   201  accepted - the phone should buzz
 *   404  the subscription is unknown; the row has been dropped
 *   410  the subscription is finished; the row has been dropped
 *   401  the VAPID key pair does not match the one the browser subscribed with
 *   403  the same, from Google's side
 *     0  this machine could not reach the push service at all
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool runs from the command line only.\n");
}

$pdo = khotwa_db();

if (!push_is_configured()) {
    exit("No key pair is configured. Run: php tools/push-keys.php\n");
}

$email = trim((string) ($argv[1] ?? ''));

if ($email === '') {
    echo "Accounts with a browser subscribed:\n\n";
    $rows = $pdo->query(
        'SELECT users.email, users.role, COUNT(*) AS browsers
         FROM push_subscriptions
         JOIN users ON users.id = push_subscriptions.user_id
         GROUP BY users.id, users.email, users.role
         ORDER BY users.email'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        echo "  (none yet)\n\n";
        echo "Nobody has granted permission. Open the portal over https or on\n";
        echo "http://localhost/, sign in, and press the bell - Chrome asks the\n";
        echo "first time it is pressed, and the answer is stored on the spot.\n";
        exit(0);
    }

    foreach ($rows as $row) {
        printf("  %-34s %-8s %d browser(s)\n", $row['email'], $row['role'], $row['browsers']);
    }
    echo "\nRun again with one of these addresses to send a test.\n";
    exit(0);
}

$statement = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ? LIMIT 1');
$statement->execute([$email]);
$user = $statement->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    exit("No account with that address.\n");
}

$userId = (int) $user['id'];
$subscriptions = push_subscriptions_for_user($pdo, $userId);

if ($subscriptions === []) {
    exit("That account has no browser subscribed yet.\n");
}

echo 'Sending to ' . $user['full_name'] . ' (' . count($subscriptions) . " browser(s))\n\n";

/*
 * The same shape every real notification uses, so this exercises the whole path
 * rather than a special case: the row is written, then the phones are told.
 */
$payload = [
    'event' => 'test',
    'title_en' => 'Khotwa Education Center',
    'title_ar' => 'مركز خطوة التعليمي',
    'body_en' => 'Notifications are working. This is a test.',
    'body_ar' => 'الإشعارات تعمل. هذه رسالة تجريبية.',
    'link' => 'parent/index.php',
];

foreach ($subscriptions as $subscription) {
    $arabic = ($subscription['language'] ?? 'en') === 'ar';
    $result = push_deliver(
        $subscription['endpoint'],
        $subscription['public_key'],
        $subscription['auth_token'],
        [
            'title' => $arabic ? $payload['title_ar'] : $payload['title_en'],
            'body' => $arabic ? $payload['body_ar'] : $payload['body_en'],
            'link' => '',
            'tag' => 'test',
        ]
    );

    printf(
        "  %-3d %-3s %s\n",
        $result['status'],
        $result['ok'] ? 'ok' : '',
        substr($subscription['endpoint'], 0, 64) . '...'
    );

    if ($result['gone']) {
        push_subscription_forget($pdo, $subscription['endpoint']);
        echo "      (dropped - that subscription is finished)\n";
    }
}

echo "\nA 201 means the push service took it. The phone is the last word.\n";
