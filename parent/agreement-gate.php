<?php
declare(strict_types=1);

/**
 * The dialog a parent meets on signing in, until they have accepted.
 *
 * It looks like a popup over the portal, and it is one - but the portal behind
 * it is a shell, not the real thing. None of the family's records are read or
 * rendered while an acceptance is owed, so the block does not depend on the
 * dialog staying on screen: there is nothing behind it to reach by closing it,
 * scrolling past it, or turning JavaScript off.
 *
 * Included by parent/index.php, which has already worked out:
 *   $publishedAgreement, $agreementClauses, $agreementPending, $user
 */

if (!isset($publishedAgreement, $agreementClauses, $agreementPending)) {
    http_response_code(500);
    exit('The agreement could not be loaded.');
}

$childCount = count($agreementPending);
$isUpdate = !empty($agreementIsUpdate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title>Parent Agreement | Khotwa Education Center</title>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/parent.css')) ?>">
</head>
<body class="admin-page parent-page agreement-page">
  <?php /*
         * The portal, as a backdrop only. Inert on purpose: it shows the parent
         * where they are without being a way in, and it carries no data.
         */ ?>
  <div class="agreement-backdrop" aria-hidden="true">
    <aside class="admin-sidebar parent-sidebar">
      <?php portal_sidebar_top(khotwa_url('parent/index.php'), 'Khotwa parent portal home', 'Parent'); ?>
    </aside>
    <div class="agreement-backdrop-stage"></div>
  </div>

  <div class="agreement-modal" role="dialog" aria-modal="true" aria-labelledby="agreement-title">
    <article class="agreement-card">
      <header class="agreement-head">
        <?php if ($isUpdate): ?>
          <?php // Flagged, because a changed agreement is easy to click past. ?>
          <span class="agreement-flag">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M12 9v4M12 17h.01"/><path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/></svg>
            <span>The agreement has been updated</span>
          </span>
        <?php else: ?>
          <span>Before you continue</span>
        <?php endif; ?>
        <h1 id="agreement-title" data-i18n-skip data-en="<?= e((string) $publishedAgreement['title_en']) ?>" data-ar="<?= e((string) $publishedAgreement['title_ar']) ?>"><?= e((string) $publishedAgreement['title_en']) ?></h1>
        <?php $intro = trim((string) ($publishedAgreement['intro_en'] ?? '')); ?>
        <?php if ($intro !== ''): ?>
          <p class="agreement-intro" data-i18n-skip data-en="<?= e($intro) ?>" data-ar="<?= e(trim((string) ($publishedAgreement['intro_ar'] ?? '')) ?: $intro) ?>"><?= e($intro) ?></p>
        <?php endif; ?>
      </header>

      <?php if ($publishedAgreement['effective_date']): ?>
        <p class="agreement-effective">
          <span>In effect from</span>
          <strong data-i18n-skip><?= e(fmt_date((string) $publishedAgreement['effective_date'])) ?></strong>
        </p>
      <?php endif; ?>

      <?php // Named so a parent knows exactly which children this covers. ?>
      <p class="agreement-children">
        <span><?= $childCount === 1 ? 'This agreement covers' : 'This agreement covers your children' ?></span>
        <?php foreach ($agreementPending as $child): ?>
          <strong data-i18n-skip data-en="<?= e((string) $child['name_en']) ?>" data-ar="<?= e((string) $child['name_ar']) ?>"><?= e((string) $child['name_en']) ?></strong>
        <?php endforeach; ?>
      </p>

      <div class="agreement-body">
        <?php foreach ($agreementClauses as $index => $clause): ?>
          <section class="agreement-clause">
            <h2>
              <i data-i18n-skip><?= e((string) ($index + 1)) ?>.</i>
              <span data-i18n-skip data-en="<?= e((string) $clause['title_en']) ?>" data-ar="<?= e((string) $clause['title_ar']) ?>"><?= e((string) $clause['title_en']) ?></span>
            </h2>
            <?php /*
                   * Both readings are in the page and the language switch picks
                   * one, so a parent reading in Arabic is not handed English.
                   */ ?>
            <p data-i18n-skip data-en="<?= e((string) $clause['body_en']) ?>" data-ar="<?= e((string) $clause['body_ar']) ?>"><?= e((string) $clause['body_en']) ?></p>
          </section>
        <?php endforeach; ?>
      </div>

      <form class="agreement-form" method="post">
        <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="accept_agreement">
        <label class="agreement-tick">
          <input type="checkbox" name="agree" value="1" required>
          <span>I have read the agreement above and I accept it<?= $childCount === 1 ? '' : ' for each of the children named' ?>.</span>
        </label>
        <div class="agreement-actions">
          <button class="primary-action" type="submit">Agree and continue</button>
          <a class="secondary-action" href="<?= e(khotwa_url('logout.php')) ?>">Log out</a>
        </div>
        <p class="agreement-note">You need to accept this before the portal opens.</p>
      </form>
    </article>
  </div>

  <div class="agreement-language">
    <?php // One control, because the only choice on this page is which language to read it in. ?>
    <button class="portal-language" type="button" title="Switch language" aria-label="Switch language" data-language-toggle>
      <span data-language-current>EN</span><i></i><span data-language-label>العربية</span>
    </button>
  </div>

  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
</body>
</html>
