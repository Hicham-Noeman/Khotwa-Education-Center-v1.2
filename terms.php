<?php
declare(strict_types=1);

// Was a plain .html page, which meant its stylesheet link carried no version and
// browsers kept serving a day-old copy after every change (.htaccess caches CSS
// for a day). Rendered through PHP, khotwa_asset() stamps the file's timestamp on
// the URL, so an edit is picked up on the next load.
require_once __DIR__ . '/src/paths.php';

// The wording below is administration's, from the Page Texts table. The seeded
// defaults stand in whenever the database cannot be reached, so this page still
// renders in full on a broken connection.
define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/homepage-data.php';

$termsTexts = [];
try {
    $termsTexts = load_homepage_data(getDatabaseConnection())['texts'] ?? [];
} catch (Throwable $exception) {
    // Seeded wording it is.
}

function terms_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function terms_t(string $key): string
{
    global $termsTexts;

    return terms_e(homepage_text($termsTexts, $key));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Terms and conditions design for Khotwa Education Center.">
  <meta name="theme-color" content="#223F6B">
  <title>Terms and Conditions | Khotwa Education Center</title>
  <?php // The brand face every other page loads. This page was still asking Google
        // for DM Sans / Manrope / Tajawal, which auth.css never names, so its text
        // rendered in a different typeface from the rest of the site. ?>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(khotwa_asset('css/auth.css'), ENT_QUOTES) ?>">
</head>
<body class="terms-page">
  <header class="terms-header">
    <a class="auth-brand terms-brand" href="<?= htmlspecialchars(khotwa_url('index.php'), ENT_QUOTES) ?>" aria-label="Khotwa Education Center home">
      <img src="<?= htmlspecialchars(khotwa_url('assets/images/logo-color.svg'), ENT_QUOTES) ?>" alt="Khotwa Education Center" width="148" height="71">
    </a>
    <div>
      <button class="language-switch" type="button" data-language-toggle>
        <span data-language-current>EN</span>
        <i></i>
        <span data-language-label>العربية</span>
      </button>
      <a class="back-link" href="<?= htmlspecialchars(khotwa_url('index.php'), ENT_QUOTES) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
        <span data-homepage-text="terms_back_link"><?= terms_t('terms_back_link') ?></span>
      </a>
      <a class="terms-login" href="<?= htmlspecialchars(khotwa_url('login.php'), ENT_QUOTES) ?>" data-homepage-text="terms_login_link"><?= terms_t('terms_login_link') ?></a>
    </div>
  </header>

  <main>
    <section class="terms-hero">
      <div class="terms-orbit" aria-hidden="true"></div>
      <span class="section-kicker" data-homepage-text="terms_kicker"><?= terms_t('terms_kicker') ?></span>
      <h1><span data-homepage-text="terms_title_line_1"><?= terms_t('terms_title_line_1') ?></span><br><em data-homepage-text="terms_title_line_2"><?= terms_t('terms_title_line_2') ?></em></h1>
      <p data-homepage-text="terms_intro"><?= terms_t('terms_intro') ?></p>
      <div class="terms-meta"><span data-homepage-text="terms_meta_status"><?= terms_t('terms_meta_status') ?></span><span data-homepage-text="terms_meta_updated"><?= terms_t('terms_meta_updated') ?></span></div>
    </section>

    <div class="terms-layout">
      <?php
      // The anchors are fixed; only the labels beside them are editable.
      $termsSections = [
          1 => 'acceptance',
          2 => 'accounts',
          3 => 'services',
          4 => 'conduct',
          5 => 'privacy',
          6 => 'changes',
          7 => 'contact-terms',
      ];
      ?>
      <aside class="terms-nav">
        <strong data-homepage-text="terms_nav_heading"><?= terms_t('terms_nav_heading') ?></strong>
        <?php foreach ($termsSections as $termsIndex => $termsAnchor): ?>
          <a href="#<?= terms_e($termsAnchor) ?>" data-homepage-text="terms_nav_<?= $termsIndex ?>"><?= terms_t('terms_nav_' . $termsIndex) ?></a>
        <?php endforeach; ?>
      </aside>

      <article class="terms-content">
        <?php foreach ($termsSections as $termsIndex => $termsAnchor): ?>
          <section id="<?= terms_e($termsAnchor) ?>">
            <span><?= terms_e(str_pad((string) $termsIndex, 2, '0', STR_PAD_LEFT)) ?></span>
            <div>
              <h2 data-homepage-text="terms_section_<?= $termsIndex ?>_title"><?= terms_t('terms_section_' . $termsIndex . '_title') ?></h2>
              <?php if ($termsIndex === 7): ?>
                <?php // The contact line wraps the center's real email address, which is
                      // kept in Contact & Social rather than repeated in the sentence. ?>
                <p>
                  <span data-homepage-text="terms_section_7_body_before"><?= terms_t('terms_section_7_body_before') ?></span>
                  <a href="mailto:<?= terms_t('team_join_email') ?>" data-contact-link="primary_email"><?= terms_t('team_join_email') ?></a>
                  <span data-homepage-text="terms_section_7_body_after"><?= terms_t('terms_section_7_body_after') ?></span>
                </p>
              <?php else: ?>
                <p data-homepage-text="terms_section_<?= $termsIndex ?>_body"><?= terms_t('terms_section_' . $termsIndex . '_body') ?></p>
              <?php endif; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </article>
    </div>
  </main>

  <footer class="terms-footer">
    <p data-homepage-text="terms_footer_copyright"><?= terms_t('terms_footer_copyright') ?></p>
    <a href="<?= htmlspecialchars(khotwa_url('login.php'), ENT_QUOTES) ?>"><span data-homepage-text="terms_footer_link"><?= terms_t('terms_footer_link') ?></span> <span>&rarr;</span></a>
  </footer>
  <?php // index.js does this for the homepage; this page carries the same few lines. ?>
  <script>
    window.KhotwaPageTexts = <?= json_encode(
        $termsTexts,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    document.addEventListener('khotwa:languagechange', (event) => {
      const language = event.detail?.language || 'en';
      document.querySelectorAll('[data-homepage-text]').forEach((element) => {
        const row = window.KhotwaPageTexts[element.dataset.homepageText];
        if (!row) return;
        const value = typeof row[language] === 'string' ? row[language] : row.en;
        if (typeof value === 'string') element.textContent = value;
      });
    });
  </script>
  <script src="<?= htmlspecialchars(khotwa_asset('js/language.js'), ENT_QUOTES) ?>" defer></script>
</body>
</html>
