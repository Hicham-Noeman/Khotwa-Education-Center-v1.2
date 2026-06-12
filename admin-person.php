<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-data.php';

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$type = ($_GET['type'] ?? '') === 'teacher' ? 'teacher' : 'student';
$personId = (int) ($_GET['id'] ?? 0);
$mainTable = $type === 'teacher' ? 'teachers' : 'students';
$relationColumn = $type === 'teacher' ? 'teacher_id' : 'student_id';
$activeView = $type === 'teacher' ? 'teachers' : 'students';
$linkedTables = admin_linked_tables($type);
$addTable = (string) ($_GET['add_table'] ?? '');
$message = isset($_GET['saved']) ? 'Record saved successfully.' : '';
$error = '';

try {
    $pdo = khotwa_db();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            admin_verify_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $table = (string) ($_POST['table'] ?? '');
            $recordId = (int) ($_POST['record_id'] ?? 0);

            if ($action === 'update_main' && $table === $mainTable && $recordId === $personId) {
                admin_save_record($pdo, $table, (array) ($_POST['fields'] ?? []), $recordId);
            } elseif ($action === 'update_linked' && isset($linkedTables[$table])) {
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM ' . admin_quote_identifier($table)
                    . ' WHERE id = ? AND ' . admin_quote_identifier($relationColumn) . ' = ?'
                );
                $check->execute([$recordId, $personId]);
                if (!(bool) $check->fetchColumn()) {
                    throw new RuntimeException('The selected linked record does not belong to this profile.');
                }
                admin_save_record($pdo, $table, (array) ($_POST['fields'] ?? []), $recordId);
            } elseif ($action === 'add_linked' && isset($linkedTables[$table])) {
                $fields = (array) ($_POST['fields'] ?? []);
                $fields[$relationColumn] = $personId;
                admin_save_record($pdo, $table, $fields);
            } else {
                throw new RuntimeException('Invalid profile action.');
            }

            header(
                'Location: admin-person.php?type=' . rawurlencode($type)
                . '&id=' . $personId . '&saved=1'
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $personStatement = $pdo->prepare(
        'SELECT * FROM ' . admin_quote_identifier($mainTable) . ' WHERE id = ?'
    );
    $personStatement->execute([$personId]);
    $person = $personStatement->fetch();
    if (!$person) {
        http_response_code(404);
        throw new RuntimeException('The requested profile was not found.');
    }

    $personName = $type === 'teacher'
        ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
        : trim((string) $person['first_name_en'] . ' ' . (string) $person['last_name_en']);

    $mainColumns = admin_columns($pdo, $mainTable);
    $mainEditableNames = array_fill_keys(
        array_column(admin_editable_columns($pdo, $mainTable), 'COLUMN_NAME'),
        true
    );
    $linkedData = [];
    foreach ($linkedTables as $table => $label) {
        $editableColumns = admin_editable_columns($pdo, $table);
        $statement = $pdo->prepare(
            'SELECT * FROM ' . admin_quote_identifier($table)
            . ' WHERE ' . admin_quote_identifier($relationColumn) . ' = ? ORDER BY id DESC'
        );
        $statement->execute([$personId]);
        $linkedData[$table] = [
            'label' => $label,
            'columns' => admin_columns($pdo, $table),
            'editable_columns' => $editableColumns,
            'editable_names' => array_fill_keys(array_column($editableColumns, 'COLUMN_NAME'), true),
            'rows' => $statement->fetchAll(),
        ];
        foreach (admin_hidden_derived_columns($table) as $derivedColumn) {
            unset($linkedData[$table]['editable_names'][$derivedColumn]);
        }
    }
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
  <title><?= e($personName ?? 'Profile') ?> | Khotwa Administration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page">
  <div class="admin-shell" data-admin-shell>
    <?php admin_render_sidebar($user, $activeView); ?>
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
              <a class="back-to-table" href="admin.php?view=<?= e($activeView) ?>">Go back to <?= e(ucfirst($activeView)) ?></a>
              <h1><?= e($personName) ?></h1>
              <p>Complete <?= e($type) ?> profile with every directly linked database record.</p>
            </div>
            <span class="profile-id">ID <?= e((string) $personId) ?></span>
          </section>

          <?php if ($message !== ''): ?><div class="form-notice success-notice"><?= e($message) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="form-notice error-notice"><?= e($error) ?></div><?php endif; ?>

          <section class="data-panel profile-main-card">
            <div class="panel-heading">
              <div><span>Main record</span><h2><?= e(ucfirst($type)) ?> information</h2></div>
              <span class="read-only-status" data-edit-status>Read only</span>
            </div>
            <form method="post" data-edit-form>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="action" value="update_main">
              <input type="hidden" name="table" value="<?= e($mainTable) ?>">
              <input type="hidden" name="record_id" value="<?= e((string) $personId) ?>">
              <fieldset class="record-fieldset" disabled data-edit-fields>
                <div class="record-form-grid">
                  <?php foreach ($mainColumns as $column): ?>
                    <?php
                    $columnName = (string) $column['COLUMN_NAME'];
                    $locked = isset($mainEditableNames[$columnName])
                        ? []
                        : [$columnName => $person[$columnName] ?? ''];
                    admin_render_field(
                        $pdo,
                        $mainTable,
                        $column,
                        $person[$columnName] ?? '',
                        $locked
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

          <section class="linked-records-heading">
            <div><span>Linked database</span><h2>Related records</h2></div>
            <p><?= e((string) count($linkedTables)) ?> connected tables</p>
          </section>

          <div class="linked-records">
            <?php foreach ($linkedData as $table => $data): ?>
              <?php $isAddingHere = $addTable === $table; ?>
              <details class="linked-section"<?= $isAddingHere ? ' open' : '' ?>>
                <summary>
                  <span><strong><?= e($data['label']) ?></strong><small><?= e(admin_table_label($table)) ?></small></span>
                  <i><?= e((string) count($data['rows'])) ?></i>
                </summary>
                <div class="linked-section-body">
                  <div class="linked-section-actions">
                    <a class="add-record-button" href="admin-person.php?type=<?= e($type) ?>&id=<?= e((string) $personId) ?>&add_table=<?= e($table) ?>">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                      Add linked record
                    </a>
                  </div>

                  <?php if ($isAddingHere): ?>
                    <form class="linked-record-form is-new" method="post">
                      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                      <input type="hidden" name="action" value="add_linked">
                      <input type="hidden" name="table" value="<?= e($table) ?>">
                      <div class="record-form-grid compact-grid">
                        <?php foreach ($data['editable_columns'] as $column): ?>
                          <?php admin_render_field(
                              $pdo,
                              $table,
                              $column,
                              admin_default_field_value($column),
                              [$relationColumn => $personId]
                          ); ?>
                        <?php endforeach; ?>
                      </div>
                      <div class="record-form-actions">
                        <button class="primary-action" type="submit">Save linked record</button>
                        <a class="secondary-action" href="admin-person.php?type=<?= e($type) ?>&id=<?= e((string) $personId) ?>">Cancel</a>
                      </div>
                    </form>
                  <?php endif; ?>

                  <?php if ($data['rows'] === []): ?>
                    <p class="linked-empty">No linked records in this table.</p>
                  <?php else: ?>
                    <?php foreach ($data['rows'] as $index => $row): ?>
                      <details class="linked-row">
                        <summary>Record #<?= e((string) $row['id']) ?><span><?= e((string) ($index + 1)) ?> of <?= e((string) count($data['rows'])) ?></span></summary>
                        <form class="linked-record-form" method="post" data-edit-form>
                          <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                          <input type="hidden" name="action" value="update_linked">
                          <input type="hidden" name="table" value="<?= e($table) ?>">
                          <input type="hidden" name="record_id" value="<?= e((string) $row['id']) ?>">
                          <fieldset class="record-fieldset" disabled data-edit-fields>
                            <div class="record-form-grid compact-grid">
                              <?php foreach ($data['columns'] as $column): ?>
                                <?php
                                $columnName = (string) $column['COLUMN_NAME'];
                                $locked = [];
                                if (!isset($data['editable_names'][$columnName])) {
                                    $locked[$columnName] = $row[$columnName] ?? '';
                                }
                                if ($columnName === $relationColumn) {
                                    $locked[$columnName] = $personId;
                                }
                                admin_render_field(
                                    $pdo,
                                    $table,
                                    $column,
                                    $row[$columnName] ?? '',
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
                      </details>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <script src="language.js"></script>
  <script src="admin.js"></script>
</body>
</html>
