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

// Every date shown to a user reads day/month/year; the database keeps ISO strings.
function fmt_date(?string $value, string $fallback = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $time = strtotime($value);

    return $time === false ? $value : date('d/m/Y', $time);
}

function fmt_datetime(?string $value, string $fallback = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $time = strtotime($value);

    return $time === false ? $value : date('d/m/Y H:i', $time);
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

/*
 * "Remember me".
 *
 * The session cookie dies with the browser on purpose. Staying signed in on a phone,
 * tablet or laptop is a separate, longer-lived cookie holding "selector:secret":
 *
 *   selector - a public random id, used to find the row
 *   secret   - never stored; only its SHA-256 hash is, so a database leak alone
 *              cannot sign anyone in
 *
 * The secret is replaced on every use. If a copied cookie is used, the real device's
 * next visit presents a token that no longer matches, and every remembered device for
 * that account is dropped.
 *
 * One row per device, so signing out on the laptop leaves the phone signed in.
 */

const KHOTWA_REMEMBER_COOKIE = 'khotwa_remember';
const KHOTWA_REMEMBER_DAYS = 30;

function remember_cookie_write(string $value, int $expiresAt): void
{
    setcookie(KHOTWA_REMEMBER_COOKIE, $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443',
    ]);
}

function remember_device_label(): string
{
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    return $agent === '' ? 'Unknown device' : mb_substr($agent, 0, 190);
}

/**
 * Store a fresh token for this device and hand the browser its cookie.
 */
function remember_me_issue(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(16));
    $secret = bin2hex(random_bytes(32));
    $expiresAt = time() + (KHOTWA_REMEMBER_DAYS * 86400);

    $pdo->prepare(
        'INSERT INTO user_remember_tokens
            (user_id, selector, token_hash, device_label, ip_address, expires_at, last_used_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        $userId,
        $selector,
        hash('sha256', $secret),
        remember_device_label(),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        date('Y-m-d H:i:s', $expiresAt),
    ]);

    // Expired rows are of no use to anyone; clear them out on the way past.
    $pdo->exec('DELETE FROM user_remember_tokens WHERE expires_at < NOW()');

    remember_cookie_write($selector . ':' . $secret, $expiresAt);
}

/**
 * Drop the token for the device making this request, and its cookie.
 */
function remember_me_forget(PDO $pdo): void
{
    $cookie = (string) ($_COOKIE[KHOTWA_REMEMBER_COOKIE] ?? '');
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $pdo->prepare('DELETE FROM user_remember_tokens WHERE selector = ?')->execute([$selector]);
    }

    unset($_COOKIE[KHOTWA_REMEMBER_COOKIE]);
    remember_cookie_write('', time() - 42000);
}

/**
 * Sign every remembered device out. Used after a password change or reset, so a
 * stolen cookie stops working the moment the owner recovers the account.
 */
function remember_me_forget_all(PDO $pdo, int $userId): void
{
    $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = ?')->execute([$userId]);
}

/**
 * Rebuild the session from the remember cookie, if there is a valid one.
 *
 * Runs on every page that includes this file, so it stays quiet: no cookie, no
 * database work, and a database that is down never turns into a fatal error here.
 */
function remember_me_attempt_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $cookie = (string) ($_COOKIE[KHOTWA_REMEMBER_COOKIE] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $secret] = explode(':', $cookie, 2);
    if (strlen($selector) !== 32 || strlen($secret) !== 64) {
        remember_cookie_write('', time() - 42000);

        return;
    }

    try {
        $pdo = khotwa_db();

        $statement = $pdo->prepare(
            'SELECT t.id, t.user_id, t.token_hash,
                    u.teacher_id, u.first_name, u.last_name, u.email, u.role, u.status
             FROM user_remember_tokens AS t
             INNER JOIN users AS u ON u.id = t.user_id
             WHERE t.selector = ? AND t.expires_at > NOW()
             LIMIT 1'
        );
        $statement->execute([$selector]);
        $row = $statement->fetch();

        if (!$row) {
            remember_cookie_write('', time() - 42000);

            return;
        }

        if (!hash_equals((string) $row['token_hash'], hash('sha256', $secret))) {
            // The selector is real but the secret is not: the cookie has been copied.
            // Everything remembered for this account goes, on both devices.
            remember_me_forget_all($pdo, (int) $row['user_id']);
            remember_cookie_write('', time() - 42000);

            return;
        }

        // The same checks login.php makes, so a disabled account cannot walk back in
        // through a cookie issued while it was still active.
        if (
            !in_array($row['role'], ['admin', 'manager', 'teacher', 'parent'], true)
            || $row['status'] !== 'active'
            || ($row['role'] === 'teacher' && $row['teacher_id'] === null)
        ) {
            remember_me_forget_all($pdo, (int) $row['user_id']);
            remember_cookie_write('', time() - 42000);

            return;
        }

        // Single use: this device gets a new secret, and the old one stops working.
        $newSecret = bin2hex(random_bytes(32));
        $expiresAt = time() + (KHOTWA_REMEMBER_DAYS * 86400);
        $pdo->prepare(
            'UPDATE user_remember_tokens
             SET token_hash = ?, expires_at = ?, last_used_at = NOW(), ip_address = ?
             WHERE id = ?'
        )->execute([
            hash('sha256', $newSecret),
            date('Y-m-d H:i:s', $expiresAt),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (int) $row['id'],
        ]);
        remember_cookie_write($selector . ':' . $newSecret, $expiresAt);

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $row['user_id'],
            'teacher_id' => $row['teacher_id'] === null ? null : (int) $row['teacher_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'role' => $row['role'],
        ];

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
            ->execute([(int) $row['user_id']]);
    } catch (Throwable $exception) {
        // A database outage should show the page's own error, not one from here.
        return;
    }
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

// Restores a session from the "Remember me" cookie before any page checks the login.
remember_me_attempt_login();
