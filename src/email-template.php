<?php
declare(strict_types=1);

/*
 * The look of the emails the portal sends.
 *
 * Email is not the web: Gmail, Outlook and Apple Mail between them drop flexbox,
 * grid, external stylesheets, custom properties and SVG. So this is built the way
 * email has to be built - nested tables, widths in attributes as well as CSS, and
 * every style written inline. The palette and type are the site's own, from
 * assets/css/index.css.
 *
 * The logo travels with the message as an embedded attachment (cid:khotwa-logo)
 * rather than a link, so it appears even when a client blocks remote images.
 */

require_once __DIR__ . '/paths.php';

// The site palette, kept here as plain hex because email has no custom properties.
const KHOTWA_MAIL_NAVY = '#223f6b';
const KHOTWA_MAIL_NAVY_DEEP = '#0b1c34';
const KHOTWA_MAIL_ORANGE = '#f49f0f';
const KHOTWA_MAIL_GREEN = '#4fbb37';
const KHOTWA_MAIL_PINK = '#e51c6f';
const KHOTWA_MAIL_INK = '#142238';
const KHOTWA_MAIL_MUTED = '#667085';
const KHOTWA_MAIL_SOFT = '#f4f7fb';
const KHOTWA_MAIL_LINE = '#dfe5ee';

// Web fonts load in only a few clients, so the fallbacks carry the design. The
// brand family is named first so any client that does have RB installed uses it.
const KHOTWA_MAIL_DISPLAY_FONT = "'RB','Segoe UI',Tahoma,'Noto Sans Arabic',sans-serif";
const KHOTWA_MAIL_BODY_FONT = "'RB','Segoe UI',Tahoma,'Noto Sans Arabic',sans-serif";

function khotwa_mail_logo_path(): string
{
    return khotwa_path('assets/images/logo-email.png');
}

/**
 * The images every message carries, ready for khotwa_send_mail()'s last argument.
 */
function khotwa_mail_images(): array
{
    return ['khotwa-logo' => khotwa_mail_logo_path()];
}

function khotwa_mail_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Wrap message content in the branded frame: navy masthead with the logo, the
 * three accent colors as a hairline under it, a white card, and the footer.
 *
 * $preheader is the grey line of text a mail app shows next to the subject in the
 * inbox list. Left unset, clients pull the first words of the body instead.
 */
function khotwa_email_shell(string $title, string $preheader, string $contentHtml): string
{
    $navy = KHOTWA_MAIL_NAVY;
    $navyDeep = KHOTWA_MAIL_NAVY_DEEP;
    $orange = KHOTWA_MAIL_ORANGE;
    $green = KHOTWA_MAIL_GREEN;
    $pink = KHOTWA_MAIL_PINK;
    $muted = KHOTWA_MAIL_MUTED;
    $soft = KHOTWA_MAIL_SOFT;
    $line = KHOTWA_MAIL_LINE;
    $body = KHOTWA_MAIL_BODY_FONT;
    $display = KHOTWA_MAIL_DISPLAY_FONT;

    $safeTitle = khotwa_mail_e($title);
    $safePreheader = khotwa_mail_e($preheader);
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="color-scheme" content="light" />
<meta name="supported-color-schemes" content="light" />
<title>{$safeTitle}</title>
<style type="text/css">
  /* Only the handful of clients that support a style block will use these; the
     inline styles below carry the design everywhere else. */
  body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
  table { border-collapse: collapse !important; }
  img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  a { color: {$navy}; }
  @media only screen and (max-width: 620px) {
    .khotwa-card { width: 100% !important; }
    .khotwa-pad { padding-left: 24px !important; padding-right: 24px !important; }
    .khotwa-code { font-size: 30px !important; letter-spacing: 6px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{$soft}; font-family:{$body}; -webkit-font-smoothing:antialiased;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:{$soft};">{$safePreheader}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$soft};">
  <tr>
    <td align="center" style="padding:32px 12px;">

      <table role="presentation" class="khotwa-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:18px; overflow:hidden; border:1px solid {$line};">

        <!-- Masthead -->
        <tr>
          <td align="center" bgcolor="{$navy}" style="background-color:{$navy}; background-image:linear-gradient(135deg,{$navy} 0%,{$navyDeep} 100%); padding:34px 24px 30px;">
            <img src="cid:khotwa-logo" width="200" height="67" alt="Khotwa Education Center" style="display:block; width:200px; height:67px; border:0;" />
          </td>
        </tr>

        <!-- The three accent colors from the site, as a hairline -->
        <tr>
          <td style="font-size:0; line-height:0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="33.33%" bgcolor="{$orange}" style="background-color:{$orange}; height:4px; font-size:0; line-height:0;">&nbsp;</td>
                <td width="33.33%" bgcolor="{$green}" style="background-color:{$green}; height:4px; font-size:0; line-height:0;">&nbsp;</td>
                <td width="33.34%" bgcolor="{$pink}" style="background-color:{$pink}; height:4px; font-size:0; line-height:0;">&nbsp;</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Message -->
        <tr>
          <td class="khotwa-pad" style="padding:38px 44px 40px;">
            {$contentHtml}
          </td>
        </tr>

      </table>

      <!-- Footer -->
      <table role="presentation" class="khotwa-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">
        <tr>
          <td class="khotwa-pad" align="center" style="padding:24px 44px 8px; font-family:{$body}; font-size:12px; line-height:1.7; color:{$muted};">
            <strong style="font-family:{$display}; font-size:13px; color:{$navy};">Khotwa Education Center</strong><br />
            <a href="mailto:khotwacenter.lb@gmail.com" style="color:{$muted}; text-decoration:underline;">khotwacenter.lb@gmail.com</a>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:6px 44px 24px; font-family:{$body}; font-size:11px; line-height:1.7; color:{$muted};">
            This message was sent automatically &mdash; please do not reply to it.<br />
            &copy; {$year} Khotwa Education Center
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>

</body>
</html>
HTML;
}
