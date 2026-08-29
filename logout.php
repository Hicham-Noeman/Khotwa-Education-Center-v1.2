<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';

// Signing out is per device: this browser's "Remember me" token goes with the
// session, while the same account stays signed in on any other device.
try {
    remember_me_forget(khotwa_db());
} catch (Throwable $exception) {
    // A database that is down must not block the sign-out itself.
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: ' . khotwa_url('login.php'));
exit;
