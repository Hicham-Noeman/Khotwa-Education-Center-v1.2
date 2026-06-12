<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect_to_role_home((array) current_user());
}

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pdo = khotwa_db();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $login = $pdo->prepare(
            "SELECT id, teacher_id, first_name, last_name, email, password_hash, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $login->execute([$email]);
        $user = $login->fetch();

        if (!$user && $email === 'admin@khotwa.test' && $password === 'admin123') {
            $insert = $pdo->prepare(
                "INSERT INTO users (
                    teacher_id, first_name, last_name, email, password_hash,
                    role, status, must_change_password, notes
                 ) VALUES (NULL, ?, ?, ?, ?, 'admin', 'active', 0, ?)"
            );
            $insert->execute([
                'Khotwa',
                'Administrator',
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'Demo administrator account for the v1.2 portal',
            ]);
            $login->execute([$email]);
            $user = $login->fetch();
        }

        if (
            !$user ||
            !in_array($user['role'], ['admin', 'teacher'], true) ||
            $user['status'] !== 'active' ||
            ($user['role'] === 'teacher' && $user['teacher_id'] === null) ||
            !password_verify($password, (string) $user['password_hash'])
        ) {
            $error = 'Invalid email or password, or this account is not ready for portal access.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'teacher_id' => $user['teacher_id'] === null ? null : (int) $user['teacher_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            $updateLogin = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
            $updateLogin->execute([(int) $user['id']]);

            redirect_to_role_home($_SESSION['user']);
        }
    } catch (Throwable $exception) {
        $error = 'The database is unavailable. Start MySQL in XAMPP and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Log in to the Khotwa Education Center learning portal.">
  <meta name="theme-color" content="#223F6B">
  <title>Log In | Khotwa Education Center</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="assets/images/khotwa-hero.webp" as="image" type="image/webp" fetchpriority="high">
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="prefetch" href="admin.css" as="style">
  <link rel="prefetch" href="admin.js" as="script">
  <link rel="prefetch" href="language.js" as="script">
  <link rel="stylesheet" href="auth.css?v=<?= e((string) filemtime(__DIR__ . '/auth.css')) ?>">
</head>
<body class="auth-page">
  <div class="auth-shell">
    <section class="auth-visual" aria-label="Khotwa learning community">
      <img src="assets/images/khotwa-hero.webp" alt="Students learning together at Khotwa Education Center" width="1600" height="854" fetchpriority="high">
      <div class="visual-wash"></div>
      <div class="visual-grid" aria-hidden="true"></div>

      <a class="auth-brand brand-on-image" href="index.html" aria-label="Khotwa Education Center home">
        <span class="brand-mark">K<span>.</span></span>
        <span>
          <strong>Khotwa</strong>
          <small>Education Center</small>
        </span>
      </a>

      <div class="visual-copy">
        <span class="mini-label"><i></i>Your learning space</span>
        <h1>Keep moving<br><em>forward.</em></h1>
        <p>Access your learning plan, session updates, resources, and progress in one focused place.</p>
      </div>

      <div class="visual-note note-one">
        <span class="note-icon orange">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5 9.2 17 19 7"/></svg>
        </span>
        <span><strong>Personalized plans</strong><small>Built around every learner</small></span>
      </div>
      <div class="visual-note note-two">
        <span class="note-icon green">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18V9m6 9V5m6 13v-7m4 7H2"/></svg>
        </span>
        <span><strong>Progress you can see</strong><small>Clear updates and next steps</small></span>
      </div>

      <div class="shape shape-orange" aria-hidden="true"></div>
      <div class="shape shape-pink" aria-hidden="true"></div>
    </section>

    <main class="auth-main">
      <div class="auth-topbar">
        <a class="auth-brand brand-mobile" href="index.html">
          <span class="brand-mark">K<span>.</span></span>
          <span>
            <strong>Khotwa</strong>
            <small>Education Center</small>
          </span>
        </a>
        <div class="auth-top-actions">
          <button class="language-switch" type="button" data-language-toggle>
            <span data-language-current>EN</span>
            <i></i>
            <span data-language-label>العربية</span>
          </button>
          <a class="back-link" href="index.html">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
            Back to website
          </a>
        </div>
      </div>

      <div class="auth-card">
        <div class="auth-heading">
          <span class="section-kicker">Welcome back</span>
          <h2>Log in to your account</h2>
          <p>Enter your details to continue to your Khotwa learning space.</p>
        </div>

        <?php if ($error !== ''): ?>
          <div class="auth-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="demo-credential-list">
          <button class="demo-credentials" type="button" data-fill-login data-email="admin@khotwa.test" data-password="admin123">
            <span>Administrator demo</span>
            <strong>admin@khotwa.test</strong>
            <code>admin123</code>
          </button>
          <button class="demo-credentials teacher-demo" type="button" data-fill-login data-email="maya.math@khotwa.test" data-password="teacher123">
            <span>Teacher demo</span>
            <strong>maya.math@khotwa.test</strong>
            <code>teacher123</code>
          </button>
        </div>

        <form class="auth-form" id="login-form" method="post" action="login.php">
          <label class="field">
            <span>Email address</span>
            <span class="input-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v12H3zM3.5 7l8.5 6 8.5-6"/></svg>
              <input type="email" name="email" value="<?= e($email) ?>" placeholder="name@example.com" autocomplete="username" required>
            </span>
          </label>

          <label class="field">
            <span>Password</span>
            <span class="input-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
              <button class="password-toggle" type="button" aria-label="Show password">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 16 16M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.3 3.1M8.3 7.1C4.6 8.8 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5M9.8 9.8a3.1 3.1 0 0 0 4.4 4.4"/></svg>
              </button>
            </span>
          </label>

          <div class="form-options">
            <label class="check-row">
              <input type="checkbox" name="remember">
              <span class="custom-check">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>
              </span>
              Remember me
            </label>
            <a href="forgot-password.html">Forgot password?</a>
          </div>

          <button class="primary-auth-button" type="submit">
            Log in
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </button>

          <div class="form-divider"><span>or continue with</span></div>

          <button class="google-button" type="button" data-demo-button>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.5-.2-2.2H12v4.3h5.4a4.7 4.7 0 0 1-2 3v2.8h3.4c2-1.9 2.8-4.6 2.8-7.9Z"/>
              <path fill="#34A853" d="M12 22c2.8 0 5.1-.9 6.8-2.5l-3.4-2.7c-.9.6-2.1 1-3.4 1-2.6 0-4.9-1.8-5.7-4.2H2.8v2.7A10 10 0 0 0 12 22Z"/>
              <path fill="#FBBC05" d="M6.3 13.6A6 6 0 0 1 6 12c0-.6.1-1.1.3-1.6V7.7H2.8A10 10 0 0 0 2 12c0 1.6.4 3 1 4.3l3.3-2.7Z"/>
              <path fill="#EA4335" d="M12 6.2c1.5 0 2.9.5 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2a10 10 0 0 0-9.2 5.7l3.5 2.7C7.1 8 9.4 6.2 12 6.2Z"/>
            </svg>
            Log in with Google
          </button>
        </form>

        <p class="legal-copy">By continuing, you agree to Khotwa's <a href="terms.html">Terms and Conditions</a>.</p>
      </div>

      <p class="auth-support">Need help? <a href="mailto:hello@khotwa.edu">Contact our support team</a></p>
    </main>
  </div>

  <div class="demo-toast" role="status" aria-live="polite">
    <span></span>
    <div><strong>Design preview</strong><small>No account data is being submitted.</small></div>
  </div>
  <script src="language.js?v=<?= e((string) filemtime(__DIR__ . '/language.js')) ?>" defer></script>
  <script src="auth.js?v=<?= e((string) filemtime(__DIR__ . '/auth.js')) ?>" defer></script>
</body>
</html>
