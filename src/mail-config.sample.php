<?php
declare(strict_types=1);

/*
 * Outgoing mail settings.
 *
 * Copy this file to src/mail-config.php and fill in the real values. That copy is
 * never committed (see .gitignore), so the mailbox password stays off GitHub and a
 * deploy can never overwrite the one already on the server.
 *
 * For a Gmail account, "pass" is NOT the account password: turn on 2-Step
 * Verification, then create an App Password at
 * https://myaccount.google.com/apppasswords and paste the 16 characters here.
 *
 * With no src/mail-config.php present, nothing is sent: every message is written to
 * logs/mail.log instead, which is what local XAMPP development wants anyway.
 */

return [
    'enabled' => true,

    'host' => 'smtp.gmail.com',
    // 587 pairs with 'tls' (STARTTLS); 465 pairs with 'ssl'.
    'port' => 587,
    'encryption' => 'tls',

    'user' => 'khotwacenter.lb@gmail.com',
    'pass' => 'your-16-character-app-password',

    // Gmail rewrites the sender to the authenticated mailbox, so keep these equal to
    // 'user' unless the address has been verified as a Gmail "send mail as" alias.
    'from_email' => 'khotwacenter.lb@gmail.com',
    'from_name' => 'Khotwa Education Center',
    'reply_to' => 'khotwacenter.lb@gmail.com',
];
