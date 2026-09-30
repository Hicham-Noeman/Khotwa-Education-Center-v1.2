<?php
declare(strict_types=1);

/*
 * What the bell asks for while a page is open.
 *
 * The badge is drawn with the page, so it is right when the page loads and
 * wrong a minute later. This is polled on a slow beat to keep it honest - and
 * on the day the site answers on https, the same rows arrive as a Chrome
 * notification instead, with no change here.
 */

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in.']);
    exit;
}

$pdo = khotwa_db();
$userId = (int) $user['id'];
$items = [];

foreach (notifications_recent($pdo, $userId, 20) as $row) {
    $items[] = [
        'id' => (int) $row['id'],
        'event' => (string) $row['event_type'],
        'title_en' => (string) $row['title_en'],
        'title_ar' => (string) $row['title_ar'],
        'body_en' => (string) $row['body_en'],
        'body_ar' => (string) $row['body_ar'],
        // The same door the rendered panel uses: read it and go to it at once.
        'open_url' => khotwa_url('notifications-open.php') . '?id=' . (int) $row['id'],
        'unread' => $row['read_at'] === null,
        'created_at' => fmt_datetime((string) $row['created_at']),
    ];
}

echo json_encode([
    'unread' => notifications_unread_count($pdo, $userId),
    'items' => $items,
]);
