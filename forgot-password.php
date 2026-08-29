<?php
declare(strict_types=1);

require_once __DIR__ . '/src/password-reset.php';

if (is_logged_in()) {
    redirect_to_role_home((array) current_user());
}

// Which of the four panels is on screen. The step is driven by the server, so the
// code and password screens cannot be reached by editing the page in the browser.
$step = 1;
$error = '';
$notice = '';
$flow = $_SESSION['password_reset'] ?? null;

if (is_array($flow)) {
    $step = (int) ($flow['step'] ?? 1);
}

$email = is_array($flow) ? (string) ($flow['email'] ?? '') : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pdo = khotwa_db();
        verify_app_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'restart') {
            unset($_SESSION['password_reset']);
            $flow = null;
            $step = 1;
            $email = '';
        } elseif ($action === 'request' || $action === 'resend') {
            $submitted = $action === 'resend'
                ? $email
                : mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190);

            if ($submitted === '' || !filter_var($submitted, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }

            reset_request_code($pdo, $submitted);

            // Identical wording whether or not the address is registered, so the form
            // cannot be used to find out who has an account here.
            $_SESSION['password_reset'] = ['step' => 2, 'email' => $submitted];
            $email = $submitted;
            $step = 2;
            $notice = 'If that email belongs to an account, a ' . KHOTWA_RESET_CODE_DIGITS
                . '-digit code is on its way. It expires in ' . KHOTWA_RESET_MINUTES . ' minutes.';
        } elseif ($action === 'verify') {
            if ($email === '' || $step < 2) {
                throw new RuntimeException('Start again by entering your email address.');
            }

            $code = (string) preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
            if (strlen($code) !== KHOTWA_RESET_CODE_DIGITS) {
                $step = 2;
                throw new RuntimeException('Enter all ' . KHOTWA_RESET_CODE_DIGITS . ' digits of the code.');
            }

            $userId = reset_verify_code($pdo, $email, $code);
            if ($userId === 0) {
                $step = 2;
                throw new RuntimeException('That code is wrong or has expired. Request a new one if needed.');
            }

            $_SESSION['password_reset'] = ['step' => 3, 'email' => $email, 'user_id' => $userId];
            $step = 3;
        } elseif ($action === 'reset') {
            $userId = is_array($flow) ? (int) ($flow['user_id'] ?? 0) : 0;
            if ($step < 3 || $userId === 0) {
                throw new RuntimeException('Start again by entering your email address.');
            }

            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (strlen($password) < 8) {
                $step = 3;
                throw new RuntimeException('Use at least 8 characters for the new password.');
            }
            if ($password !== $confirm) {
                $step = 3;
                throw new RuntimeException('The two passwords need to match.');
            }

            reset_apply_new_password($pdo, $userId, $password);

            // The reset is spent; nothing about it should survive into the next visit.
            unset($_SESSION['password_reset']);
            session_regenerate_id(true);
            $step = 4;
        }
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'The service is unavailable right now. Please try again in a moment.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset your Khotwa Education Center password by email.">
  <meta name="theme-color" content="#223F6B">
  <meta name="robots" content="noindex">
  <title>Reset Password | Khotwa Education Center</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700&family=Tajawal:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700&family=Tajawal:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/auth.css')) ?>">
</head>
<body class="auth-page recovery-page">
  <div class="recovery-backdrop" aria-hidden="true">
    <span class="orb orb-one"></span>
    <span class="orb orb-two"></span>
    <span class="orb orb-three"></span>
  </div>

  <header class="simple-auth-header">
    <a class="auth-brand" href="<?= e(khotwa_url('index.php')) ?>" aria-label="Khotwa Education Center home">
      <span class="brand-mark">K<span>.</span></span>
      <span>
        <strong>Khotwa</strong>
        <small>Education Center</small>
      </span>
    </a>
    <div class="simple-header-actions">
      <button class="language-switch language-switch-dark" type="button" data-language-toggle>
        <span data-language-current>EN</span>
        <i></i>
        <span data-language-label>العربية</span>
      </button>
      <a class="back-link" href="<?= e(khotwa_url('login.php')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
        Back to login
      </a>
    </div>
  </header>

  <main class="recovery-main">
    <section class="recovery-card">
      <div class="recovery-progress" aria-label="Password reset progress">
        <div class="progress-step<?= $step === 1 ? ' is-current' : ' is-complete' ?>" data-progress="1"><span>1</span><small>Email</small></div>
        <i></i>
        <div class="progress-step<?= $step === 2 ? ' is-current' : ($step > 2 ? ' is-complete' : '') ?>" data-progress="2"><span>2</span><small>Code</small></div>
        <i></i>
        <div class="progress-step<?= $step === 3 ? ' is-current' : ($step > 3 ? ' is-complete' : '') ?>" data-progress="3"><span>3</span><small>Reset</small></div>
      </div>

      <div class="recovery-panels">
        <section class="recovery-panel<?= $step === 1 ? ' is-active' : '' ?>" data-step="1">
          <div class="recovery-icon icon-orange">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v12H3zM3.5 7l8.5 6 8.5-6"/></svg>
          </div>
          <span class="section-kicker">Step one</span>
          <h1>Forgot your password?</h1>
          <p>Enter the email connected to your account and we will send you a <?= KHOTWA_RESET_CODE_DIGITS ?>-digit code.</p>

          <form class="auth-form compact-form" method="post" action="<?= e(khotwa_url('forgot-password.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
            <input type="hidden" name="action" value="request">
            <label class="field">
              <span>Email address</span>
              <span class="input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v12H3zM3.5 7l8.5 6 8.5-6"/></svg>
                <input id="recovery-email" name="email" type="email" value="<?= $step === 1 ? e($email) : '' ?>" placeholder="name@example.com" autocomplete="email" required>
              </span>
            </label>
            <p class="field-message" data-error="email"><?= $step === 1 ? e($error) : '' ?></p>
            <button class="primary-auth-button" type="submit">
              Send code
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
          </form>
          <a class="text-back-link" href="<?= e(khotwa_url('login.php')) ?>">Remembered it? Return to login</a>
        </section>

        <section class="recovery-panel<?= $step === 2 ? ' is-active' : '' ?>" data-step="2">
          <div class="recovery-icon icon-green">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5zM9 16h6"/></svg>
          </div>
          <span class="section-kicker">Step two</span>
          <h1>Enter your <?= KHOTWA_RESET_CODE_DIGITS ?>-digit code</h1>
          <p>Check the inbox of <strong id="recovery-email-label"><?= e($email !== '' ? $email : 'your email') ?></strong>. The code expires in <?= KHOTWA_RESET_MINUTES ?> minutes.</p>

          <form class="auth-form compact-form" id="recovery-code-form" method="post" action="<?= e(khotwa_url('forgot-password.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="code" id="recovery-code-value">
            <div class="code-grid" aria-label="Verification code">
              <?php // autocomplete on the first box lets a browser or password manager
                    // offer the code straight from the email. ?>
              <?php for ($digit = 1; $digit <= KHOTWA_RESET_CODE_DIGITS; $digit++): ?>
                <input type="text" inputmode="numeric" maxlength="1"<?= $digit === 1 ? ' autocomplete="one-time-code"' : ' autocomplete="off"' ?> aria-label="Digit <?= $digit ?>">
              <?php endfor; ?>
            </div>
            <?php if ($step === 2 && $error !== ''): ?>
              <p class="field-message" data-error="code"><?= e($error) ?></p>
            <?php else: ?>
              <p class="field-message field-notice" data-error="code"><?= $step === 2 ? e($notice) : '' ?></p>
            <?php endif; ?>
            <button class="primary-auth-button" type="submit">
              Verify code
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
          </form>
          <div class="recovery-links">
            <form method="post" action="<?= e(khotwa_url('forgot-password.php')) ?>">
              <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
              <input type="hidden" name="action" value="restart">
              <button type="submit">Change email</button>
            </form>
            <form method="post" action="<?= e(khotwa_url('forgot-password.php')) ?>">
              <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
              <input type="hidden" name="action" value="resend">
              <button type="submit">Resend code</button>
            </form>
          </div>
        </section>

        <section class="recovery-panel<?= $step === 3 ? ' is-active' : '' ?>" data-step="3">
          <div class="recovery-icon icon-pink">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11h14v10H5zM8 11V8a4 4 0 0 1 7.5-2M16 3l2 2-2 2"/></svg>
          </div>
          <span class="section-kicker">Final step</span>
          <h1>Create a new password</h1>
          <p>Choose a strong password you have not used for this account before.</p>

          <form class="auth-form compact-form" id="reset-password-form" method="post" action="<?= e(khotwa_url('forgot-password.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
            <input type="hidden" name="action" value="reset">
            <label class="field">
              <span>New password</span>
              <span class="input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input id="new-password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                <button class="password-toggle" type="button" aria-label="Show password">
                  <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                  <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 16 16M8 7c-3.5 1.8-5.5 5-5.5 5s3.5 6 9.5 6c1.2 0 2.2-.2 3.2-.5M12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.2 3"/></svg>
                </button>
              </span>
            </label>
            <div class="password-meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <label class="field">
              <span>Confirm new password</span>
              <span class="input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input id="confirm-password" name="confirm_password" type="password" placeholder="Enter it again" autocomplete="new-password" minlength="8" required>
              </span>
            </label>
            <p class="field-message" data-error="password"><?= $step === 3 ? e($error) : '' ?></p>
            <button class="primary-auth-button" type="submit">
              Reset password
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
          </form>
        </section>

        <section class="recovery-panel recovery-success<?= $step === 4 ? ' is-active' : '' ?>" data-step="4">
          <div class="success-ring">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5 9.2 17 19 7"/></svg>
          </div>
          <span class="section-kicker">All done</span>
          <h1>Your password has been changed</h1>
          <p>You can now log in with your new password. Every device that was kept signed in has been signed out.</p>
          <a class="primary-auth-button" href="<?= e(khotwa_url('login.php')) ?>">
            Return to login
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </section>
      </div>
    </section>

    <div class="recovery-footer">
      <a href="<?= e(khotwa_url('index.php')) ?>">Back to website</a>
      <span></span>
      <a href="<?= e(khotwa_url('terms.html')) ?>">Terms and Conditions</a>
    </div>
  </main>

  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/auth.js')) ?>" defer></script>
</body>
</html>
