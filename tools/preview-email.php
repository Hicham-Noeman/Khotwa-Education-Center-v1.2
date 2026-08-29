<?php
declare(strict_types=1);

/*
 * Write the reset email to an HTML file so the design can be looked at in a browser,
 * without sending anything or touching the database.
 *
 *   php tools/preview-email.php [output.html]
 *
 * The embedded logo (cid:khotwa-logo in the real message) is rewritten to the file on
 * disk so it shows in the preview. Command line only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../src/email-template.php';
require_once __DIR__ . '/../src/password-reset.php';

$output = $argv[1] ?? (__DIR__ . '/../logs/email-preview.html');

$html = reset_code_email_html('Hello Nour Haddad,', '231 786 469');
$html = str_replace(
    'cid:khotwa-logo',
    'file:///' . str_replace('\\', '/', khotwa_mail_logo_path()),
    $html
);

file_put_contents($output, $html);

echo "Preview written to: ", realpath($output) ?: $output, "\n";
echo "Open it in a browser to see the message as it is sent.\n";
