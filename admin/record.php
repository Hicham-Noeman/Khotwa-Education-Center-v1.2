<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

$user = require_roles(['admin', 'manager']);
$isManager = ($user['role'] ?? '') === 'manager';

$view = (string) ($_GET['view'] ?? '');
$recordId = (int) ($_GET['id'] ?? 0);
$viewTables = admin_view_tables();
$navigation = admin_navigation();
$error = '';
$message = isset($_GET['saved']) ? 'Record saved successfully.' : '';

try {
    if (
        !isset($viewTables[$view], $navigation[$view])
        || !admin_user_can_access_view($user, $view)
        || $recordId < 1
    ) {
        http_response_code(404);
        throw new RuntimeException('The requested record was not found.');
    }

    $pdo = khotwa_db();
    $table = $viewTables[$view];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            admin_verify_csrf();
            if (
                (string) ($_POST['action'] ?? '') !== 'update_record'
                || (string) ($_POST['view'] ?? '') !== $view
                || (int) ($_POST['record_id'] ?? 0) !== $recordId
            ) {
                throw new RuntimeException('Invalid record action.');
            }

            $uploadResult = admin_prepare_uploads(
                $table,
                (array) ($_POST['fields'] ?? []),
                (array) ($_FILES['uploads'] ?? [])
            );
            try {
                admin_save_record($pdo, $table, $uploadResult['fields'], $recordId);
                admin_remove_uploaded_files($uploadResult['replaced']);
            } catch (Throwable $exception) {
                admin_remove_uploaded_files($uploadResult['created']);
                throw $exception;
            }
            header(
                'Location: ' . khotwa_url('admin/record.php') . '?view=' . rawurlencode($view)
                . '&id=' . $recordId . '&saved=1'
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $statement = $pdo->prepare(
        'SELECT * FROM ' . admin_quote_identifier($table) . ' WHERE id = ?'
    );
    $statement->execute([$recordId]);
    $record = $statement->fetch();
    if (!$record) {
        http_response_code(404);
        throw new RuntimeException('The requested record was not found.');
    }

    if ($error !== '') {
        $record = array_replace($record, (array) ($_POST['fields'] ?? []));
    }

    $allColumns = admin_columns($pdo, $table);
    $editableNames = array_fill_keys(array_column(admin_editable_columns($pdo, $table), 'COLUMN_NAME'), true);
    foreach (admin_hidden_derived_columns($table) as $derivedColumn) {
        unset($editableNames[$derivedColumn]);
    }
    $pageTitle = (string) $navigation[$view]['label'];
    $columnsByName = array_column($allColumns, null, 'COLUMN_NAME');
} catch (Throwable $exception) {
    $databaseError = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title><?= e($pageTitle ?? 'Record') ?> #<?= e((string) $recordId) ?> | Khotwa <?= $isManager ? 'Management' : 'Administration' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
</head>
<body class="admin-page">
  <div class="admin-shell" data-admin-shell>
    <?php admin_render_sidebar($user, $view); ?>
    <div class="admin-stage">
      <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <main class="admin-content profile-content">
        <?php if (isset($databaseError)): ?>
          <div class="database-alert"><?= e($databaseError) ?></div>
        <?php else: ?>
          <section class="content-heading profile-heading">
            <div>
              <?php // data-back-to-view lets the table's stored address (filters, tab, search,
                    // sort, scroll) replace this plain fallback link once the page loads. ?>
              <a class="back-to-table" href="<?= e(admin_workspace_url($view, ['restore' => 1])) ?>" data-back-to-view="<?= e($view) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7"/></svg>
                Back to <?= e($pageTitle) ?>
              </a>
              <h1><?= e($pageTitle) ?> record</h1>
              <p>Every stored field for this record is shown below.</p>
            </div>
            <span class="profile-id">ID <?= e((string) $recordId) ?></span>
          </section>


          <section class="data-panel profile-main-card<?= $view === 'website-content' ? ' website-content-record' : '' ?>">
            <form method="post" enctype="multipart/form-data" data-edit-form>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="action" value="update_record">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <input type="hidden" name="record_id" value="<?= e((string) $recordId) ?>">
              <div class="panel-heading record-panel-heading">
                <div><span>Full record</span><h2><?= e(admin_table_label($table)) ?></h2></div>
                <div class="record-header-tools">
                  <span class="read-only-status" data-edit-status>Read only</span>
                  <button class="secondary-action edit-record-button" type="button" data-edit-toggle>Edit record</button>
                  <button class="primary-action" type="submit" hidden data-save-record>Save changes</button>
                  <button class="secondary-action" type="button" hidden data-cancel-edit>Cancel</button>
                </div>
              </div>
              <fieldset class="record-fieldset" disabled data-edit-fields>
                <?php if ($view === 'website-content'): ?>
                  <?php
                  $renderWebsiteField = static function (string $columnName, string $wrapperClass = '') use (
                      $columnsByName,
                      $editableNames,
                      $record,
                      $pdo,
                      $table
                  ): void {
                      if (!isset($columnsByName[$columnName])) {
                          return;
                      }

                      $locked = isset($editableNames[$columnName])
                          ? []
                          : [$columnName => $record[$columnName] ?? ''];
                      echo '<div class="website-field ' . e($wrapperClass) . '">';
                      admin_render_field(
                          $pdo,
                          $table,
                          $columnsByName[$columnName],
                          $record[$columnName] ?? '',
                          $locked,
                          true
                      );
                      echo '</div>';
                  };
                  $isProgramContent = ($record['content_type'] ?? '') === 'program';
                  ?>

                  <div class="website-content-editor">
                    <section class="content-editor-section content-settings-section">
                      <header class="content-section-heading">
                        <span class="content-section-number">01</span>
                        <div>
                          <h3>Content settings</h3>
                          <p>Control where this block appears and whether it is visible on the homepage.</p>
                        </div>
                      </header>
                      <div class="website-settings-grid">
                        <?php $renderWebsiteField('content_key'); ?>
                        <?php $renderWebsiteField('content_type'); ?>
                        <?php $renderWebsiteField('sort_order'); ?>
                        <?php $renderWebsiteField('status'); ?>
                      </div>
                    </section>

                    <div class="content-language-grid">
                      <section class="content-editor-section language-content-card language-content-en">
                        <header class="language-card-heading">
                          <span>EN</span>
                          <div>
                            <h3>English content</h3>
                            <p>Text shown when the website language is English.</p>
                          </div>
                        </header>
                        <div class="language-fields">
                          <?php $renderWebsiteField('eyebrow_en'); ?>
                          <?php if ($isProgramContent): ?><?php $renderWebsiteField('category_en'); ?><?php endif; ?>
                          <?php $renderWebsiteField('title_en'); ?>
                          <?php $renderWebsiteField('description_en', 'website-field-description'); ?>
                        </div>
                      </section>

                      <section class="content-editor-section language-content-card language-content-ar">
                        <header class="language-card-heading">
                          <span>ع</span>
                          <div>
                            <h3>Arabic content</h3>
                            <p>Text shown when the website language is Arabic.</p>
                          </div>
                        </header>
                        <div class="language-fields">
                          <?php $renderWebsiteField('eyebrow_ar'); ?>
                          <?php if ($isProgramContent): ?><?php $renderWebsiteField('category_ar'); ?><?php endif; ?>
                          <?php $renderWebsiteField('title_ar'); ?>
                          <?php $renderWebsiteField('description_ar', 'website-field-description'); ?>
                        </div>
                      </section>
                    </div>

                    <?php if ($isProgramContent): ?>
                      <section class="content-editor-section program-points-section">
                        <header class="content-section-heading">
                          <span class="content-section-number">02</span>
                          <div>
                            <h3>Program points</h3>
                            <p>The three highlights displayed below this program in both languages.</p>
                          </div>
                        </header>
                        <div class="program-points-grid">
                          <?php for ($point = 1; $point <= 3; $point++): ?>
                            <article class="program-point-row">
                              <span class="program-point-number"><?= e((string) $point) ?></span>
                              <?php $renderWebsiteField('point_' . $point . '_en'); ?>
                              <?php $renderWebsiteField('point_' . $point . '_ar'); ?>
                            </article>
                          <?php endfor; ?>
                        </div>
                      </section>
                    <?php endif; ?>

                    <section class="content-editor-section record-information-section">
                      <header class="content-section-heading">
                        <span class="content-section-number"><?= $isProgramContent ? '03' : '02' ?></span>
                        <div>
                          <h3>Record information</h3>
                          <p>System-managed identifiers and timestamps for this content block.</p>
                        </div>
                      </header>
                      <div class="record-information-grid">
                        <?php $renderWebsiteField('id'); ?>
                        <?php $renderWebsiteField('created_at'); ?>
                        <?php $renderWebsiteField('updated_at'); ?>
                      </div>
                    </section>
                  </div>
                <?php else: ?>
                  <div class="record-form-grid">
                    <?php foreach ($allColumns as $column): ?>
                      <?php
                      $columnName = (string) $column['COLUMN_NAME'];
                      $locked = isset($editableNames[$columnName])
                          ? []
                          : [$columnName => $record[$columnName] ?? ''];
                      admin_render_field(
                          $pdo,
                          $table,
                          $column,
                          $record[$columnName] ?? '',
                          $locked,
                          true
                      );
                      ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </fieldset>
            </form>
          </section>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <?php render_toasts([
      ['type' => 'success', 'text' => $message ?? ''],
      ['type' => 'error', 'text' => $error ?? ''],
  ]); ?>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
