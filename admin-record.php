<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-data.php';

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$view = (string) ($_GET['view'] ?? '');
$recordId = (int) ($_GET['id'] ?? 0);
$viewTables = admin_view_tables();
$navigation = admin_navigation();
$error = '';
$message = isset($_GET['saved']) ? 'Record saved successfully.' : '';

try {
    if (!isset($viewTables[$view], $navigation[$view]) || $recordId < 1) {
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

            admin_save_record($pdo, $table, (array) ($_POST['fields'] ?? []), $recordId);
            header(
                'Location: admin-record.php?view=' . rawurlencode($view)
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
  <title><?= e($pageTitle ?? 'Record') ?> #<?= e((string) $recordId) ?> | Khotwa Administration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
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
              <a class="back-to-table" href="admin.php?view=<?= e($view) ?>">Go back to <?= e($pageTitle) ?></a>
              <h1><?= e($pageTitle) ?> record</h1>
              <p>Every stored field for this record is shown below.</p>
            </div>
            <span class="profile-id">ID <?= e((string) $recordId) ?></span>
          </section>

          <?php if ($message !== ''): ?><div class="form-notice success-notice"><?= e($message) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="form-notice error-notice"><?= e($error) ?></div><?php endif; ?>

          <section class="data-panel profile-main-card">
            <div class="panel-heading">
              <div><span>Full record</span><h2><?= e(admin_table_label($table)) ?></h2></div>
              <span class="read-only-status" data-edit-status>Read only</span>
            </div>
            <form method="post" data-edit-form>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="action" value="update_record">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <input type="hidden" name="record_id" value="<?= e((string) $recordId) ?>">
              <fieldset class="record-fieldset" disabled data-edit-fields>
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
              </fieldset>
              <div class="record-form-actions">
                <button class="secondary-action edit-record-button" type="button" data-edit-toggle>Edit</button>
                <button class="primary-action" type="submit" hidden data-save-record>Save</button>
                <button class="secondary-action" type="button" hidden data-cancel-edit>Cancel</button>
              </div>
            </form>
          </section>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <script src="language.js"></script>
  <script src="admin.js"></script>
</body>
</html>
