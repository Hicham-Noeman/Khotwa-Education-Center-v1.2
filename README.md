# Khotwa Education Center

Bilingual (English / Arabic) website and school-management portal, running on XAMPP
with PHP and MySQL.

## Folder structure

```
.
├── index.php               Public homepage
├── login.php  logout.php   Session entry and exit
├── terms.html              Terms and conditions
├── forgot-password.php     Password reset by emailed code
├── .htaccess               Directory index, compression, cache headers
│
├── admin/                  Administrator workspace
│   ├── index.php               Main panel (all data views)
│   ├── record.php              Single-record editor
│   ├── person.php              Student / teacher profile with linked records
│   ├── linked-records.php      Linked-record fragment loaded over fetch()
│   └── qr-attendance.php       QR check-in endpoint
├── manager/index.php       Manager dashboard
├── teacher/                Teacher portal
│   ├── index.php
│   └── subject-attendance-save.php   Attendance autosave endpoint
├── parent/index.php        Parent portal
├── api/homepage-content.php  Homepage content as JSON
│
├── src/                    Shared PHP, never served over the web
│   ├── paths.php               Project paths and URL helpers
│   ├── database.php            Connection, schema, migrations, seeds
│   ├── auth.php                Sessions, roles, CSRF, "Remember me" tokens
│   ├── password-reset.php      Reset codes: issue, verify, apply
│   ├── mailer.php              Outgoing mail (SMTP via lib/PHPMailer)
│   ├── email-template.php      Branded frame shared by outgoing email
│   ├── mail-config.sample.php  Template for the SMTP credentials
│   ├── admin-data.php          Admin tables, forms, uploads, sidebar
│   ├── homepage-data.php       Homepage queries and live counters
│   └── .htaccess               Deny all
│
├── lib/PHPMailer/          Vendored mailer (no Composer on the host)
│
├── assets/
│   ├── css/                index, admin, auth, manager, parent, teacher
│   ├── js/                 index, admin, auth, language, manager, qr-tools, teacher
│   ├── images/             Logos and photography
│   └── uploads/            Admin-uploaded images (PHP execution denied)
│
├── tools/
│   ├── setup.php               Database setup and demo seeding
│   ├── seed-recent-attendance.php  Refreshes this week's demo attendance
│   ├── seed-warnings.php           Ten demo warnings in each workflow state
│   ├── test-mail.php               Sends one test message, prints the SMTP result
│   └── preview-email.php           Writes the reset email to HTML to look at
└── logs/                   Server logs (deny all)
```

## Linking between folders

Pages sit at different depths, and image paths are stored in the database relative
to the project root, so **every internal link goes through a helper** from
`src/paths.php` instead of a hand-written relative path:

```php
khotwa_url()                          // project root, e.g. /Khotwa Education Center v1.2/
khotwa_url('admin/index.php')         // page link
khotwa_url($row['image_path'])        // database-stored image path
khotwa_asset('css/admin.css')         // asset link, cache-busted with filemtime()
khotwa_path('assets/uploads')         // absolute filesystem path
```

`khotwa_base_url()` derives the prefix from the running script, so the project works
in any folder Apache serves it from without configuration.

Shared code is included by filesystem path:

```php
require_once __DIR__ . '/../src/auth.php';   // from a page inside a folder
require_once __DIR__ . '/src/auth.php';      // from a page at the root
```

`src/auth.php` loads `src/database.php`, which loads `src/paths.php`, so any page
that includes either one already has the helpers available.

## Running locally

1. Start Apache and MySQL in the XAMPP control panel.
2. Open `http://localhost/Khotwa Education Center v1.2/`.
3. The database and its tables are created on first request. To load demo data,
   open `tools/setup.php`.

MySQL connection settings live at the top of `src/database.php`. The port is set to
**3307** because a separately installed Windows "MySQL80" service holds the default
3306; change `$dbPort` back to 3306 once MariaDB owns that port again.

## Sending email

Password reset codes are the only mail the site sends. Delivery is configured by
copying `src/mail-config.sample.php` to `src/mail-config.php` and filling in the SMTP
details; that copy is gitignored, so the mailbox password never reaches GitHub and a
deploy cannot overwrite the one already on the server.

Messages use the site's own colors and logo, built as email HTML (tables and inline
styles - Gmail and Outlook drop stylesheets, flexbox and SVG). The logo is attached to
each message as `assets/images/logo-email.png` and referenced as `cid:khotwa-logo`, so
it appears even where remote images are blocked; it is a raster copy of
`logo-white.svg`, since email clients will not render SVG.

Two command-line helpers:

```
php tools/preview-email.php      # writes the reset email to logs/email-preview.html
php tools/test-mail.php you@example.com    # sends one test message
```

For the center's Gmail account, `pass` is an **App Password**
(<https://myaccount.google.com/apppasswords>), not the account password, and 2-Step
Verification has to be on before that page will offer one.

With no `src/mail-config.php` present - which is the normal state on this machine -
nothing is sent: every message is appended to `logs/mail.log` instead, so the reset
code can still be read while developing. `logs/` is denied to the web by its own
`.htaccess`.

## Demo accounts

`tools/setup.php` rebuilds the demo data and creates these logins:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@khotwa.test` | `admin123` |
| Manager | `manager@khotwa.test` | `manager123` |
| Teacher (12 accounts) | e.g. `maya.math@khotwa.test` | `teacher123` |
| Parent | `parent.one@khotwa.test`, `parent.two@khotwa.test` | `parent123` |

The seeded school year is 2025-2026. The teacher attendance screens always show
*today*, so run `php tools/seed-recent-attendance.php` to fill the current week
whenever the demo dates go stale.
