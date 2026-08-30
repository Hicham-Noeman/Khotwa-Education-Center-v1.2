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

    // A parent link only says which parent belongs to this student, so the section
    // shows the account and nothing else -- on the add form and on saved rows alike.
    $skipColumns = $table === 'parent_students' ? ['status'] : [];
    $keepColumn = static fn (array $column): bool => !in_array(
        (string) $column['COLUMN_NAME'],
        $skipColumns,
        true
    );

    $addColumns = array_values(array_filter($editableColumns, $keepColumn));
    $rowColumns = array_values(array_filter($columns, $keepColumn));

    $statement = $pdo->prepare(
        'SELECT * FROM ' . admin_quote_identifier($table)
        . ' WHERE ' . admin_quote_identifier($relationColumn) . ' = ? ORDER BY id DESC'
    );
    $statement->execute([$personId]);
    $rows = $statement->fetchAll();

    // The school schedule is entered on a weekly planner rather than as a stack of
    // rows, so the section loads it as a grid of sessions instead.
    $isPlanner = $type === 'student' && $table === 'student_school_schedule';
    $plannerState = null;
    $plannerName = '';
    $window = admin_schedule_window();
    if ($isPlanner) {
        $plannerState = admin_student_schedule_state($pdo, $personId);
        $nameStatement = $pdo->prepare(
            "SELECT TRIM(CONCAT(first_name_en, ' ', COALESCE(last_name_en, ''))) AS name FROM students WHERE id = ?"
        );
        $nameStatement->execute([$personId]);
        $plannerName = (string) ($nameStatement->fetchColumn() ?: 'Student');
    }

    // Each saved row is collapsed, so a parent link names the account on its header
    // instead of the useless "Record 1" -- the name and the email are the whole point.
    $rowTitles = [];
    if ($table === 'parent_students' && $rows !== []) {
        $parentIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['parent_user_id'],
            $rows
        )));
        $accounts = $pdo->prepare(
            "SELECT id, TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) AS name, email
             FROM users
             WHERE id IN (" . implode(', ', array_fill(0, count($parentIds), '?')) . ')'
        );
        $accounts->execute($parentIds);
        foreach ($accounts as $account) {
            $rowTitles[(int) $account['id']] = [
                'title' => (string) $account['name'],
                'meta' => (string) $account['email'],
            ];
        }
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('These records could not be loaded. Please try again.');
}
?>
<?php if ($isPlanner): ?>
  <form
    class="schedule-planner"
    method="post"
    data-edit-form
    data-schedule-planner
    data-schedule-window="<?= e(json_encode($window)) ?>"
    data-schedule-blocks="<?= e(json_encode($plannerState['blocks'], JSON_UNESCAPED_UNICODE)) ?>"
    data-schedule-name="<?= e($plannerName) ?>"
    data-schedule-file="student-<?= e((string) $personId) ?>-schedule"
  >
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_schedule">
    <input type="hidden" name="table" value="<?= e($table) ?>">
    <input type="hidden" name="schedule" value="<?= e(json_encode($plannerState['blocks'], JSON_UNESCAPED_UNICODE)) ?>" data-schedule-payload>

    <div class="schedule-toolbar-row">
      <p class="schedule-hint" data-schedule-hint>Press Edit to change this schedule.</p>
      <button class="secondary-action schedule-download" type="button" data-schedule-download>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
        Download as image
      </button>
    </div>

    <fieldset class="record-fieldset" disabled data-edit-fields>
      <div class="schedule-grid" data-schedule-grid></div>
    </fieldset>

    <?php if ($plannerState['unplanned'] > 0): ?>
      <p class="schedule-kept-note"><?= $plannerState['unplanned'] === 1
          ? '1 saved entry without a usable time is kept as it is and is not shown on the planner.'
          : e((string) $plannerState['unplanned']) . ' saved entries without a usable time are kept as they are and are not shown on the planner.' ?></p>
    <?php endif; ?>

    <div class="record-form-actions">
      <button class="secondary-action edit-record-button" type="button" data-edit-toggle>Edit</button>
      <button class="primary-action" type="submit" hidden data-save-record>Save schedule</button>
      <button class="secondary-action" type="button" hidden data-cancel-edit>Cancel</button>
    </div>
  </form>
<?php else: ?>
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
      <?php foreach ($addColumns as $column): ?>
        <?php admin_render_field(
            $pdo,
            $table,
            $column,
            admin_default_field_value($column),
            [$relationColumn => $personId],
            false,
            [$relationColumn]
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
      <?php
      $rowTitle = $rowTitles[(int) ($row['parent_user_id'] ?? 0)] ?? null;
      ?>
      <summary>
        <?= e($rowTitle === null ? 'Record ' . ($index + 1) : $rowTitle['title']) ?>
        <span><?= e($rowTitle === null
            ? ($index + 1) . ' of ' . count($rows)
            : $rowTitle['meta']) ?></span>
      </summary>
      <form class="linked-record-form" method="post" data-edit-form>
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="update_linked">
        <input type="hidden" name="table" value="<?= e($table) ?>">
        <input type="hidden" name="record_id" value="<?= e((string) $row['id']) ?>">
        <fieldset class="record-fieldset" disabled data-edit-fields>
          <div class="record-form-grid compact-grid">
            <?php foreach ($rowColumns as $column): ?>
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
                  true,
                  [$relationColumn]
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
<?php endif; ?>
