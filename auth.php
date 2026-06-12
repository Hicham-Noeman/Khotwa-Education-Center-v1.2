<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/database.php';

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

function redirect_to_role_home(array $user): never
{
    $target = match ($user['role']) {
        'teacher' => 'teacher.php',
        'manager' => 'manager.php',
        'admin' => 'admin.php',
        default => 'login.php',
    };

    header("Location: {$target}");
    exit;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: login.php');
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
      <a class="app-brand" href="dashboard.php">
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
            <a href="teacher.php">My Students</a>
          <?php endif; ?>
          <?php if ($user['role'] === 'manager'): ?>
            <a href="manager.php">Dashboard</a>
          <?php endif; ?>
          <a href="index.html">Website</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
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
        <link rel="stylesheet" href="app.css">
      </head>
      <body class="app-body">
        <main class="auth-shell">
          <section class="auth-panel">
            <span class="auth-mark">K</span>
            <h1>Database connection failed</h1>
            <p>Start MySQL in XAMPP, then open <a href="setup.php">setup.php</a> to create the database and seed demo users.</p>
          </section>
        </main>
      </body>
    </html>
    <?php
}
