<?php
declare(strict_types=1);

/**
 * A draft agreement, shown exactly as a parent would meet it.
 *
 * The point of a draft is to be read before it goes out, and reading it in the
 * editor is not the same as reading it on the page families see. This renders
 * the real gate page against any version - draft included - with the accept
 * form replaced by a note, so nothing can be agreed to from here.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/parent-agreement.php';

require_roles(['admin', 'manager']);

$pdo = khotwa_db();
$agreementId = (int) ($_GET['id'] ?? 0);

$statement = $pdo->prepare('SELECT * FROM parent_agreements WHERE id = ? LIMIT 1');
$statement->execute([$agreementId]);
$agreement = $statement->fetch();

if (!$agreement) {
    http_response_code(404);
    exit('That agreement could not be found.');
}

$clauses = parent_agreement_clauses($pdo, $agreementId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title>Preview | Parent Agreement</title>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/parent.css')) ?>">
</head>
<body class="admin-page parent-page agreement-page">
  <div class="agreement-preview-banner">
    <strong>Preview &mdash; <?= e(parent_agreement_status_label((string) $agreement['status'])) ?></strong>
    <span>This is what a parent sees. Nothing here can be accepted.</span>
  </div>

  <main class="agreement-shell">
    <article class="agreement-card">
      <header class="agreement-head">
        <span>Before you continue</span>
        <h1 data-i18n-skip data-en="<?= e((string) $agreement['title_en']) ?>" data-ar="<?= e((string) $agreement['title_ar']) ?>"><?= e((string) $agreement['title_en']) ?></h1>
        <?php $intro = trim((string) ($agreement['intro_en'] ?? '')); ?>
        <?php if ($intro !== ''): ?>
          <p class="agreement-intro" data-i18n-skip data-en="<?= e($intro) ?>" data-ar="<?= e(trim((string) ($agreement['intro_ar'] ?? '')) ?: $intro) ?>"><?= e($intro) ?></p>
        <?php endif; ?>
      </header>

      <p class="agreement-children">
        <span>This agreement covers your children</span>
        <strong data-i18n-skip>Example Student</strong>
      </p>

      <div class="agreement-body">
        <?php if ($clauses === []): ?>
          <p class="linked-empty">This agreement has no clauses yet, so a parent would see an empty page.</p>
        <?php endif; ?>
        <?php foreach ($clauses as $index => $clause): ?>
          <section class="agreement-clause">
            <h2>
              <i data-i18n-skip><?= e((string) ($index + 1)) ?>.</i>
              <span data-i18n-skip data-en="<?= e((string) $clause['title_en']) ?>" data-ar="<?= e((string) $clause['title_ar']) ?>"><?= e((string) $clause['title_en']) ?></span>
            </h2>
            <p data-i18n-skip data-en="<?= e((string) $clause['body_en']) ?>" data-ar="<?= e((string) $clause['body_ar']) ?>"><?= e((string) $clause['body_en']) ?></p>
          </section>
        <?php endforeach; ?>
      </div>

      <?php // The accept form, disabled: a preview must not be able to agree. ?>
      <div class="agreement-form">
        <label class="agreement-tick">
          <input type="checkbox" disabled>
          <span>I have read the agreement above and I accept it.</span>
        </label>
        <div class="agreement-actions">
          <button class="primary-action" type="button" disabled>Agree and continue</button>
        </div>
      </div>
    </article>

    <p class="agreement-foot">
      <span>Khotwa Education Center</span>
    </p>
  </main>

  <div class="agreement-language">
    <button class="portal-language" type="button" title="Switch language" aria-label="Switch language" data-language-toggle>
      <span data-language-current>EN</span><i></i><span data-language-label>العربية</span>
    </button>
  </div>

  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
</body>
</html>
