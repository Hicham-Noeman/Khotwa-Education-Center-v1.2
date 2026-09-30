<?php
declare(strict_types=1);

/*
 * A browser offering to be notified with the site closed.
 *
 * Chrome only hands a subscription over on https, so on the LAN address nothing
 * ever reaches here - the page does not even ask. The endpoint is stored from
 * the first day it does, so bringing push online is a matter of writing the
 * sender, not of chasing every browser for permission again.
 */

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/notifications.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid subscription.']);
    exit;
}

if (!hash_equals(app_csrf_token(), (string) ($payload['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session token.']);
    exit;
}

$saved = push_subscription_save(
    khotwa_db(),
    (int) $user['id'],
    is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [],
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    // Which language the notifications themselves should be written in.
    (string) ($payload['language'] ?? 'en')
);

echo json_encode(['ok' => $saved]);
