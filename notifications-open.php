<?php
declare(strict_types=1);

/*
 * Opening one notification.
 *
 * Every line in the panel points here rather than straight at its page, so that
 * reading one is the same act as going to it: the row is marked read and the
 * browser is sent on in a single step. It works the same on a phone, where the
 * panel covers the page and there is no room to mark things read by hand.
 *
 * A GET rather than a post, because this is a link people follow - the only
 * thing it changes is the read mark on a row that already belongs to them.
 */

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/notifications.php';

$user = require_login();
$pdo = khotwa_db();
$userId = (int) $user['id'];
$notificationId = (int) ($_GET['id'] ?? 0);

$link = $notificationId > 0 ? notification_link_for($pdo, $userId, $notificationId) : '';
if ($notificationId > 0) {
    notifications_mark_read($pdo, $userId, $notificationId);
}

// A notification with nowhere to go still counts as read; the person lands back
// on their own portal rather than on an error.
if ($link === '') {
    redirect_to_role_home($user);
}

header('Location: ' . khotwa_url($link));
exit;
