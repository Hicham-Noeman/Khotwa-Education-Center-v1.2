<?php
declare(strict_types=1);

/*
 * Password reset by email.
 *
 * A request stores only the SHA-256 hash of a 9-digit code, valid for 15 minutes and
 * good for at most 6 guesses. Neither the request step nor the verify step ever says
 * whether an address belongs to an account: the page moves on identically either way,
 * so the form cannot be used to discover who is registered.
 *
 * Which request the visitor is working on is held in the session, not in the URL, so
 * a code cannot be pushed onto someone else through a link.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email-template.php';

const KHOTWA_RESET_CODE_DIGITS = 9;
const KHOTWA_RESET_MINUTES = 15;
const KHOTWA_RESET_MAX_ATTEMPTS = 6;
// Per address and per IP, within the same window as the code lifetime.
const KHOTWA_RESET_MAX_REQUESTS = 5;

function reset_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function reset_generate_code(): string
{
    $code = '';
    for ($index = 0; $index < KHOTWA_RESET_CODE_DIGITS; $index++) {
        $code .= (string) random_int(0, 9);
    }

    return $code;
}

/**
 * True when this address or this IP has already asked too often to be given another
 * code. Counted before any account lookup, so it costs an attacker the same either way.
 */
function reset_requests_exhausted(PDO $pdo, string $email, string $ip): bool
{
    $statement = $pdo->prepare(
        'SELECT
            SUM(email = ?) AS for_email,
            SUM(requested_ip = ?) AS from_ip
         FROM password_resets
         WHERE created_at > (NOW() - INTERVAL ' . KHOTWA_RESET_MINUTES . ' MINUTE)'
    );
    $statement->execute([mb_substr($email, 0, 190), $ip]);
    $row = $statement->fetch() ?: [];

    return (int) ($row['for_email'] ?? 0) >= KHOTWA_RESET_MAX_REQUESTS
        || (int) ($row['from_ip'] ?? 0) >= KHOTWA_RESET_MAX_REQUESTS;
}

/**
 * Create and send a code for $email, if it belongs to an account that can sign in.
 *
 * Returns nothing either way on purpose - the caller must show the same screen for a
 * known and an unknown address.
 */
function reset_request_code(PDO $pdo, string $email): void
{
    $email = mb_substr(trim($email), 0, 190);
    $ip = reset_client_ip();

    if ($email === '' || reset_requests_exhausted($pdo, $email, $ip)) {
        return;
    }

    $statement = $pdo->prepare(
        "SELECT id, first_name, last_name, email, role, teacher_id
         FROM users
         WHERE email = ? AND status = 'active'
         LIMIT 1"
    );
    $statement->execute([$email]);
    $user = $statement->fetch();

    if (!$user || ($user['role'] === 'teacher' && $user['teacher_id'] === null)) {
        return;
    }

    // Asking again replaces the previous code rather than leaving several live.
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([(int) $user['id']]);

    $code = reset_generate_code();
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, email, code_hash, requested_ip, expires_at)
         VALUES (?, ?, ?, ?, (NOW() + INTERVAL ' . KHOTWA_RESET_MINUTES . ' MINUTE))'
    )->execute([
        (int) $user['id'],
        $email,
        hash('sha256', $code),
        $ip,
    ]);

    $pdo->exec('DELETE FROM password_resets WHERE created_at < (NOW() - INTERVAL 7 DAY)');

    $name = trim((string) $user['first_name'] . ' ' . (string) ($user['last_name'] ?? ''));
    reset_send_code_email((string) $user['email'], $name, $code);
}

/**
 * The reset email's own content, inside the shared branded frame.
 *
 * Separate from sending so the design can be rendered and looked at without a
 * mail server: see tools/preview-email.php.
 */
function reset_code_email_html(string $greeting, string $spacedCode): string
{
    $navy = KHOTWA_MAIL_NAVY;
    $ink = KHOTWA_MAIL_INK;
    $muted = KHOTWA_MAIL_MUTED;
    $soft = KHOTWA_MAIL_SOFT;
    $line = KHOTWA_MAIL_LINE;
    $bodyFont = KHOTWA_MAIL_BODY_FONT;
    $displayFont = KHOTWA_MAIL_DISPLAY_FONT;
    $minutes = KHOTWA_RESET_MINUTES;
    $safeGreeting = khotwa_mail_e($greeting);
    $safeCode = khotwa_mail_e($spacedCode);

    $content = <<<HTML
<p style="margin:0 0 6px; font-family:{$bodyFont}; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:{$muted};">Password reset</p>
<h1 style="margin:0 0 18px; font-family:{$displayFont}; font-size:25px; line-height:1.3; font-weight:800; color:{$navy};">Here is your reset code</h1>

<p style="margin:0 0 8px; font-family:{$bodyFont}; font-size:15px; line-height:1.65; color:{$ink};">{$safeGreeting}</p>
<p style="margin:0 0 26px; font-family:{$bodyFont}; font-size:15px; line-height:1.65; color:{$ink};">Enter this code on the password reset page to choose a new password.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;">
  <tr>
    <td align="center" bgcolor="{$soft}" style="background-color:{$soft}; border:1px solid {$line}; border-radius:14px; padding:26px 16px;">
      <div class="khotwa-code" style="font-family:{$displayFont}; font-size:36px; line-height:1.1; font-weight:800; letter-spacing:9px; color:{$navy}; white-space:nowrap;">{$safeCode}</div>
    </td>
  </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;">
  <tr>
    <td style="background-color:#fdf3e0; border-radius:999px; padding:8px 16px; font-family:{$bodyFont}; font-size:13px; font-weight:700; color:#8a5a06;">
      &#9201;&nbsp; Expires in {$minutes} minutes
    </td>
  </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td style="border-top:1px solid {$line}; padding-top:22px; font-family:{$bodyFont}; font-size:13px; line-height:1.7; color:{$muted};">
      <strong style="color:{$ink};">Did not ask for this?</strong><br />
      Ignore this email and your password stays exactly as it is. Nobody can change it
      without the code above.
    </td>
  </tr>
</table>
HTML;

    return khotwa_email_shell(
        'Your Khotwa password reset code',
        'Your code is ' . $spacedCode . ' and it expires in ' . KHOTWA_RESET_MINUTES . ' minutes.',
        $content
    );
}

function reset_send_code_email(string $email, string $name, string $code): void
{
    // Grouped in threes: far easier to carry from the screen to the form.
    $spacedCode = trim(chunk_split($code, 3, ' '));
    $greeting = $name === '' ? 'Hello,' : 'Hello ' . $name . ',';

    $text = $greeting . "

"
        . "Your Khotwa Education Center password reset code is:

"
        . '    ' . $spacedCode . "

"
        . 'The code stops working in ' . KHOTWA_RESET_MINUTES . " minutes.

"
        . "If you did not ask to reset your password, ignore this email - your password
"
        . "has not changed.

"
        . 'Khotwa Education Center';

    khotwa_send_mail(
        $email,
        $name,
        'Your Khotwa password reset code',
        reset_code_email_html($greeting, $spacedCode),
        $text,
        khotwa_mail_images()
    );
}

/**
 * Check a submitted code against the newest live request for $email.
 *
 * Returns the user id on success, or 0 for wrong / expired / used up.
 */
function reset_verify_code(PDO $pdo, string $email, string $code): int
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, code_hash, attempts
         FROM password_resets
         WHERE email = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute([mb_substr($email, 0, 190)]);
    $row = $statement->fetch();

    if (!$row) {
        return 0;
    }

    if ((int) $row['attempts'] >= KHOTWA_RESET_MAX_ATTEMPTS) {
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([(int) $row['id']]);

        return 0;
    }

    $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')
        ->execute([(int) $row['id']]);

    if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
        return 0;
    }

    return (int) $row['user_id'];
}

/**
 * Set the new password and close the account back up: the code is spent, the forced
 * password change is satisfied, and every remembered device is signed out.
 */
function reset_apply_new_password(PDO $pdo, int $userId, string $password): void
{
    $pdo->prepare(
        'UPDATE users
         SET password_hash = ?, must_change_password = 0
         WHERE id = ?'
    )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$userId]);

    remember_me_forget_all($pdo, $userId);

    // A reset is how someone locked out gets back in, so the block on their address
    // has to lift with it.
    $emailStatement = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $emailStatement->execute([$userId]);
    $email = (string) $emailStatement->fetchColumn();
    if ($email !== '') {
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')
            ->execute([mb_substr($email, 0, 190)]);
    }
}
