<?php
declare(strict_types=1);

/*
 * Send one test email, to check the SMTP settings without going through a whole
 * password reset.
 *
 *   php tools/test-mail.php you@example.com
 *
 * Prints what the mailer decided to do and why. Command line only - tools/ is never
 * uploaded by the deploy workflow, but this refuses to run over the web regardless.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../src/mailer.php';

$recipient = $argv[1] ?? '';
if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php tools/test-mail.php you@example.com\n");
}

$config = khotwa_mail_config();
$configFile = __DIR__ . '/../src/mail-config.php';

echo "Config file : ", is_file($configFile) ? 'src/mail-config.php found' : 'MISSING (src/mail-config.php)', "\n";
echo "Enabled     : ", $config['enabled'] ? 'yes' : 'no', "\n";
echo "Server      : ", $config['host'] === '' ? '(none)' : $config['host'] . ':' . $config['port']
    . ' (' . $config['encryption'] . ')', "\n";
echo "Username    : ", $config['user'] === '' ? '(none)' : $config['user'], "\n";
echo "Password    : ", $config['pass'] === '' ? '(empty)' : '(set, ' . strlen((string) $config['pass']) . ' chars)', "\n";
echo "\n";

if (!khotwa_mail_is_live()) {
    echo "Result      : NOT SENDING - the message will only be written to logs/mail.log.\n";
    echo "              Fill in 'pass' in src/mail-config.php to send for real.\n\n";
}

$stamp = date('d/m/Y H:i:s');
$text = "This is a test message from the Khotwa Education Center portal.\n\n"
    . "Sent: {$stamp}\n\n"
    . "If it arrived, password reset codes will arrive too.";
$html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1c2b45;line-height:1.6">'
    . '<p>This is a test message from the Khotwa Education Center portal.</p>'
    . '<p style="color:#5b6b86">Sent: ' . htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>If it arrived, password reset codes will arrive too.</p></div>';

$sent = khotwa_send_mail($recipient, '', 'Khotwa portal test message', $html, $text);

if (khotwa_mail_is_live()) {
    echo $sent
        ? "Result      : SENT to {$recipient}. Check the inbox, and the spam folder.\n"
        : "Result      : FAILED. The reason is at the end of logs/mail.log.\n";
} else {
    echo "Logged      : see logs/mail.log\n";
}
