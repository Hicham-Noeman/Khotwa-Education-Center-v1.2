<?php
declare(strict_types=1);

/*
 * Clearing the badge.
 *
 * A post rather than a link, so a refresh cannot silently mark someone's list
 * read behind their back, and so the cleared state survives the redirect.
 */

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/notifications.php';

$user = require_login();

/*
 * Back to the page the bell was pressed on.
 *
 * A referrer is whatever the browser chose to send, so it is only trusted when
 * it points inside this installation: same host, and a path under the project's
 * own base. khotwa_base_url() is a path rather than an absolute address, which
 * is why the two are compared a piece at a time.
 */
$back = khotwa_url('login.php');
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if ($referer !== '') {
    $parts = parse_url($referer);
    $sameHost = !isset($parts['host']) || strcasecmp($parts['host'], (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0;
    $path = (string) ($parts['path'] ?? '');
    if ($sameHost && $path !== '' && str_starts_with($path, khotwa_base_url())) {
        $back = $path . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_app_csrf();
        notifications_mark_all_read(khotwa_db(), (int) $user['id']);
    } catch (Throwable $exception) {
        // The badge simply stays as it was.
    }
}

header('Location: ' . $back);
exit;
