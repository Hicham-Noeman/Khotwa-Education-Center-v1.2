<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

$user = current_user();
if (!$user || !in_array(($user['role'] ?? ''), ['admin', 'manager'], true)) {
    http_response_code(401);
    exit('Your session has expired. Refresh the page and log in again.');
}

$type = ($_GET['type'] ?? '') === 'teacher' ? 'teacher' : 'student';
$personId = (int) ($_GET['id'] ?? 0);
$table = (string) ($_GET['table'] ?? '');
$isAdding = isset($_GET['add']);
$linkedTables = admin_linked_tables_for_user($type, $user);
$relationColumn = $type === 'teacher' ? 'teacher_id' : 'student_id';

if ($personId < 1 || !isset($linkedTables[$table])) {
    http_response_code(404);
    exit('The requested linked records were not found.');
}

$csrfToken = admin_csrf_token();
session_write_close();

try {
    $pdo = khotwa_db();
    $editableColumns = admin_editable_columns($pdo, $table);
    $columns = admin_columns($pdo, $table);
    $editableNames = array_fill_keys(array_column($editableColumns, 'COLUMN_NAME'), true);
    foreach (admin_hidden_derived_columns($table) as $derivedColumn) {
        unset($editableNames[$derivedColumn]);
    }

    $statement = $pdo->prepare(
        'SELECT * FROM ' . admin_quote_identifier($table)
        . ' WHERE ' . admin_quote_identifier($relationColumn) . ' = ? ORDER BY id DESC'
    );
    $statement->execute([$personId]);
    $rows = $statement->fetchAll();
} catch (Throwable $exception) {
    http_response_code(500);
    exit('These records could not be loaded. Please try again.');
}
?>
<div class="linked-section-actions">
  <a class="add-record-button" href="<?= e(khotwa_url('admin/person.php')) ?>?type=<?= e($type) ?>&id=<?= e((string) $personId) ?>&add_table=<?= e($table) ?>#linked-<?= e($table) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Add linked record
  </a>
</div>

<?php if ($isAdding): ?>
  <form class="linked-record-form is-new" method="post">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="add_linked">
    <input type="hidden" name="table" value="<?= e($table) ?>">
    <div class="record-form-grid compact-grid">
      <?php foreach ($editableColumns as $column): ?>
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
      <a class="secondary-action" href="<?= e(khotwa_url('admin/person.php')) ?>?type=<?= e($type) ?>&id=<?= e((string) $personId) ?>#linked-<?= e($table) ?>">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<?php if ($rows === []): ?>
  <p class="linked-empty">No linked records in this table.</p>
<?php else: ?>
  <?php foreach ($rows as $index => $row): ?>
    <details class="linked-row">
      <summary>Record #<?= e((string) $row['id']) ?><span><?= e((string) ($index + 1)) ?> of <?= e((string) count($rows)) ?></span></summary>
      <form class="linked-record-form" method="post" data-edit-form>
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="update_linked">
        <input type="hidden" name="table" value="<?= e($table) ?>">
        <input type="hidden" name="record_id" value="<?= e((string) $row['id']) ?>">
        <fieldset class="record-fieldset" disabled data-edit-fields>
          <div class="record-form-grid compact-grid">
            <?php foreach ($columns as $column): ?>
              <?php
              $columnName = (string) $column['COLUMN_NAME'];
              $locked = [];
              if (!isset($editableNames[$columnName])) {
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
