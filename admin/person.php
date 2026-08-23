<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

$user = require_roles(['admin', 'manager']);
$isManager = ($user['role'] ?? '') === 'manager';

$type = ($_GET['type'] ?? '') === 'teacher' ? 'teacher' : 'student';
$personId = (int) ($_GET['id'] ?? 0);
$mainTable = $type === 'teacher' ? 'teachers' : 'students';
$relationColumn = $type === 'teacher' ? 'teacher_id' : 'student_id';
$activeView = $type === 'teacher' ? 'teachers' : 'students';
$linkedTables = admin_linked_tables_for_user($type, $user);
$addTable = (string) ($_GET['add_table'] ?? '');
$message = isset($_GET['saved']) ? 'Record saved successfully.' : '';
$error = '';
$pdo = null;
$person = [];
$personName = '';
$mainColumns = [];
$mainEditableNames = [];
$linkedCounts = [];
$studentQrPayloadJson = null;
$studentQrFileBase = null;

try {
    $pdo = khotwa_db();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            admin_verify_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $table = (string) ($_POST['table'] ?? '');
            $recordId = (int) ($_POST['record_id'] ?? 0);

            if ($action === 'update_main' && $table === $mainTable && $recordId === $personId) {
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
                'Location: ' . khotwa_url('admin/person.php') . '?type=' . rawurlencode($type)
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

    $studentQrPayloadJson = null;
    $studentQrFileBase = null;
    if ($type === 'student') {
      $studentFullNameEn = trim((string) (
        ($person['first_name_en'] ?? '') . ' '
        . ($person['father_name_en'] ?? '') . ' '
        . ($person['last_name_en'] ?? '')
      ));
      $studentFullNameAr = trim((string) (
        ($person['first_name_ar'] ?? '') . ' '
        . ($person['father_name_ar'] ?? '') . ' '
        . ($person['last_name_ar'] ?? '')
      ));
      $studentQrPayloadJson = json_encode([
        'Full Name in EN' => $studentFullNameEn,
        'Full Name in AR' => $studentFullNameAr,
        'ID' => $personId,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $studentQrFileBase = 'student-' . $personId;
    }

    $mainColumns = admin_columns($pdo, $mainTable);
    $mainEditableNames = array_fill_keys(
        array_column(admin_editable_columns($pdo, $mainTable), 'COLUMN_NAME'),
        true
    );

    $linkedCounts = array_fill_keys(array_keys($linkedTables), 0);
    $countQueries = [];
    $countValues = [];
    foreach ($linkedTables as $table => $label) {
        $countQueries[] = 'SELECT ' . $pdo->quote($table) . ' table_name, COUNT(*) record_count'
            . ' FROM ' . admin_quote_identifier($table)
            . ' WHERE ' . admin_quote_identifier($relationColumn) . ' = ?';
        $countValues[] = $personId;
    }
    if ($countQueries !== []) {
        $countStatement = $pdo->prepare(implode(' UNION ALL ', $countQueries));
        $countStatement->execute($countValues);
        foreach ($countStatement->fetchAll() as $countRow) {
            $linkedCounts[(string) $countRow['table_name']] = (int) $countRow['record_count'];
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
  <title><?= e($personName ?? 'Profile') ?> | Khotwa <?= $isManager ? 'Management' : 'Administration' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
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
              <?php // restore=1 tells the table to put back the search, sort and scroll position,
                    // and data-back-to-view swaps in the address the record was opened from. ?>
              <a class="back-to-table" href="<?= e(khotwa_url('admin/index.php')) ?>?view=<?= e($activeView) ?>&restore=1" data-back-to-view="<?= e($activeView) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7"/></svg>
                Back to <?= e(ucfirst($activeView)) ?>
              </a>
              <h1><?= e($personName) ?></h1>
              <p>Complete <?= e($type) ?> profile with every directly linked database record.</p>
            </div>
            <div class="profile-heading-actions">
              <?php if ($type === 'student' && $studentQrPayloadJson !== null): ?>
                <div
                  class="profile-qr"
                  data-student-qr
                  data-qr-file-base="<?= e((string) $studentQrFileBase) ?>"
                  data-qr-payload="<?= e((string) $studentQrPayloadJson) ?>"
                >
                  <?php // Kept in the layout but clipped out of sight: the export reads
                        // this canvas, so it must render rather than be display:none. ?>
                  <span class="profile-qr-canvas" data-qr-canvas aria-hidden="true"></span>
                  <button class="secondary-action" type="button" data-qr-download="png">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                    QR PNG
                  </button>
                  <button class="secondary-action" type="button" data-qr-download="jpg">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                    QR JPG
                  </button>
                </div>
              <?php endif; ?>
              <span class="profile-id">ID <?= e((string) $personId) ?></span>
            </div>
          </section>


          <?php
          // One button per section so the whole profile is reachable without scrolling.
          $profileTabs = ['main' => ucfirst($type) . ' information'];
          foreach ($linkedTables as $linkedTable => $linkedLabel) {
              $profileTabs[$linkedTable] = $linkedLabel;
          }
          // Opening an "add" link jumps straight to that section.
          $activeTab = isset($linkedTables[$addTable]) ? $addTable : 'main';
          ?>
          <?php // Tabs and panels share one card, so the strip belongs to the
                // rectangle holding the data rather than floating above it. ?>
          <div class="profile-workspace">
          <?php // Icons only, so all sections fit one line; the name appears on hover
                // and stays visible on the section you are in. ?>
          <nav class="profile-tabs profile-tabs-icons" role="tablist" aria-label="Profile sections">
            <?php foreach ($profileTabs as $tabKey => $tabLabel): ?>
              <button
                class="profile-tab<?= $tabKey === $activeTab ? ' is-active' : '' ?>"
                type="button"
                role="tab"
                aria-selected="<?= $tabKey === $activeTab ? 'true' : 'false' ?>"
                aria-controls="panel-<?= e($tabKey) ?>"
                data-profile-tab="<?= e($tabKey) ?>"
                title="<?= e($tabLabel) ?>"
              >
                <?= admin_icon(admin_profile_section_icon($tabKey)) ?>
                <span><?= e($tabLabel) ?></span>
                <?php if (isset($linkedCounts[$tabKey])): ?>
                  <i><?= e((string) $linkedCounts[$tabKey]) ?></i>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </nav>

          <div class="profile-panels">
          <section
            class="data-panel profile-main-card"
            id="panel-main"
            role="tabpanel"
            data-profile-panel="main"
            <?= $activeTab === 'main' ? '' : 'hidden' ?>
          >
            <div class="panel-heading">
              <div><span>Main record</span><h2><?= e(ucfirst($type)) ?> information</h2></div>
              <span class="read-only-status" data-edit-status>Read only</span>
            </div>
            <form method="post" enctype="multipart/form-data" data-edit-form>
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


          <?php foreach ($linkedTables as $table => $label): ?>
            <?php $isAddingHere = $addTable === $table; ?>
            <section
              class="data-panel linked-section"
              id="panel-<?= e($table) ?>"
              role="tabpanel"
              data-profile-panel="<?= e($table) ?>"
              data-linked-section
              data-linked-url="<?= e(khotwa_url('admin/linked-records.php')) ?>?type=<?= e($type) ?>&id=<?= e((string) $personId) ?>&table=<?= e($table) ?><?= $isAddingHere ? '&add=1' : '' ?>"
              <?= $activeTab === $table ? '' : 'hidden' ?>
            >
              <div class="panel-heading">
                <div><span><?= e(admin_table_label($table)) ?></span><h2><?= e($label) ?></h2></div>
                <strong class="record-count"><?= e((string) ($linkedCounts[$table] ?? 0)) ?> records</strong>
              </div>
              <div class="linked-section-body" data-linked-content>
                <p class="linked-empty">Loading records...</p>
              </div>
            </section>
          <?php endforeach; ?>
          </div>
          </div>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js" defer></script>
  <?php render_toasts([
      ['type' => 'success', 'text' => $message ?? ''],
      ['type' => 'error', 'text' => $error ?? ''],
  ]); ?>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/qr-tools.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
