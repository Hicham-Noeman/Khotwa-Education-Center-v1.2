<?php
declare(strict_types=1);

/*
 * Outgoing mail for the portal.
 *
 * PHPMailer is vendored under lib/PHPMailer (there is no Composer on the host), so
 * the three classes it needs are required by hand rather than autoloaded.
 *
 * Every message goes through khotwa_send_mail(). When src/mail-config.php is absent
 * or has 'enabled' => false, the message is written to logs/mail.log instead of being
 * sent - local development then still shows the reset code without needing an SMTP
 * account, and a misconfigured server degrades to "logged" rather than to a crash.
 */

require_once __DIR__ . '/paths.php';

function khotwa_mail_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'user' => '',
        'pass' => '',
        'from_email' => 'khotwacenter.lb@gmail.com',
        'from_name' => 'Khotwa Education Center',
        'reply_to' => '',
    ];

    $configFile = __DIR__ . '/mail-config.php';
    if (is_file($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded)) {
            $defaults = array_merge($defaults, $loaded);
        }
    }

    return $config = $defaults;
}

function khotwa_mail_is_live(): bool
{
    $config = khotwa_mail_config();

    // The password counts too: a config file left with an empty one keeps logging
    // rather than failing an SMTP handshake on every request.
    return (bool) $config['enabled']
        && $config['host'] !== ''
        && $config['user'] !== ''
        && $config['pass'] !== '';
}

/**
 * Append a message to logs/mail.log. Used both as the no-SMTP fallback and to record
 * why a real send failed.
 */
function khotwa_mail_log(string $to, string $subject, string $textBody, string $note): void
{
    $directory = khotwa_path('logs');
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $entry = sprintf(
        "[%s] %s\nTo: %s\nSubject: %s\n%s\n%s\n\n",
        date('d/m/Y H:i:s'),
        $note,
        $to,
        $subject,
        str_repeat('-', 60),
        trim($textBody)
    );

    @file_put_contents($directory . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Send one message. Returns true when it left the server (or was logged in place of
 * sending). Never throws: a mail failure must not take a page down with it.
 */
function khotwa_send_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody,
    array $embeddedImages = []
): bool {
    $config = khotwa_mail_config();

    if (!khotwa_mail_is_live()) {
        khotwa_mail_log($toEmail, $subject, $textBody, 'NOT SENT - no SMTP configured, logged only');

        return true;
    }

    require_once khotwa_path('lib/PHPMailer/Exception.php');
    require_once khotwa_path('lib/PHPMailer/PHPMailer.php');
    require_once khotwa_path('lib/PHPMailer/SMTP.php');

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = (string) $config['host'];
        $mailer->Port = (int) $config['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) $config['user'];
        $mailer->Password = (string) $config['pass'];
        $mailer->SMTPSecure = ((string) $config['encryption']) === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        // A hung SMTP handshake would otherwise hold the page open for the visitor.
        $mailer->Timeout = 15;
        $mailer->CharSet = 'UTF-8';

        $mailer->setFrom((string) $config['from_email'], (string) $config['from_name']);
        if ((string) $config['reply_to'] !== '') {
            $mailer->addReplyTo((string) $config['reply_to'], (string) $config['from_name']);
        }
        $mailer->addAddress($toEmail, $toName);

        // Attached rather than linked: the logo shows even where remote images are
        // blocked, and nothing about the message depends on the site being reachable.
        foreach ($embeddedImages as $contentId => $imagePath) {
            if (is_file($imagePath)) {
                $mailer->addEmbeddedImage($imagePath, (string) $contentId);
            }
        }

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $textBody;

        $mailer->send();

        return true;
    } catch (Throwable $exception) {
        // Free hosting commonly blocks outbound SMTP ports; log the reason and the
        // body so the code is still recoverable by an administrator.
        khotwa_mail_log(
            $toEmail,
            $subject,
            $textBody,
            'SEND FAILED - ' . $exception->getMessage()
        );

        return false;
    }
}
