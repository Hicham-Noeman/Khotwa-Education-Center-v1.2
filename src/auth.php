<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Harden the session cookie before it is created:
    //   httponly  - JavaScript cannot read it, so an XSS bug cannot steal the login
    //   samesite  - the cookie is not sent on cross-site POSTs, blocking CSRF replay
    //   secure    - only sent over HTTPS, switched on automatically when available
    //   strict_mode - the server refuses session ids it never issued (fixation)
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443',
    ]);
    session_start();
}

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ui.php';

function khotwa_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = getDatabaseConnection();
    }

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['app_csrf'])) {
        $_SESSION['app_csrf'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['app_csrf'];
}

function verify_app_csrf(): void
{
    if (!hash_equals(app_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('The form session expired. Refresh the page and try again.');
    }
}

function redirect_to_role_home(array $user): never
{
    $target = match ($user['role']) {
        'teacher' => 'teacher/index.php',
        'manager' => 'manager/index.php',
        'parent' => 'parent/index.php',
        'admin' => 'admin/index.php',
        default => 'login.php',
    };

    header('Location: ' . khotwa_url($target));
    exit;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: ' . khotwa_url('login.php'));
        exit;
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        redirect_to_role_home($user);
    }

    return $user;
}

function render_app_header(string $title, array $user): void
{
    $roleLabel = ucfirst($user['role']);
    ?>
    <header class="app-header">
      <a class="app-brand" href="<?= e(khotwa_url()) ?>">
        <span>K</span>
        <strong>Khotwa</strong>
      </a>
      <div class="app-title">
        <h1><?= e($title) ?></h1>
        <p><?= e($roleLabel) ?> access</p>
      </div>
      <nav class="app-nav" aria-label="Application navigation">
        <?php if ($user['role'] !== 'admin'): ?>
          <?php if ($user['role'] === 'teacher'): ?>
            <a href="<?= e(khotwa_url('teacher/index.php')) ?>">My Students</a>
          <?php endif; ?>
          <?php if ($user['role'] === 'manager'): ?>
            <a href="<?= e(khotwa_url('manager/index.php')) ?>">Dashboard</a>
          <?php endif; ?>
          <?php if ($user['role'] === 'parent'): ?>
            <a href="<?= e(khotwa_url('parent/index.php')) ?>">My Children</a>
          <?php endif; ?>
          <a href="<?= e(khotwa_url()) ?>">Website</a>
        <?php endif; ?>
        <a href="<?= e(khotwa_url('logout.php')) ?>">Logout</a>
      </nav>
    </header>
    <?php
}

function render_db_error(Throwable $exception): void
{
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Khotwa Database Error</title>
        <link rel="stylesheet" href="<?= e(khotwa_asset('css/auth.css')) ?>">
      </head>
      <body class="app-body">
        <main class="auth-shell">
          <section class="auth-panel">
            <span class="auth-mark">K</span>
            <h1>Database connection failed</h1>
            <p>Start MySQL in XAMPP, then open <a href="<?= e(khotwa_url('tools/setup.php')) ?>">tools/setup.php</a> to create the database and seed demo users.</p>
          </section>
        </main>
      </body>
    </html>
    <?php
}
