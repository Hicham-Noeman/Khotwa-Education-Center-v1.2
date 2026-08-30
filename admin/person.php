<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';
require_once __DIR__ . '/../src/homepage-data.php';

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
            } elseif ($action === 'save_schedule' && $type === 'student' && $table === 'student_school_schedule'
                && isset($linkedTables[$table])) {
                // The planner posts the whole week at once instead of one row per session.
                admin_save_student_schedule($pdo, $personId, (string) ($_POST['schedule'] ?? '[]'));
            } elseif ($action === 'add_linked' && isset($linkedTables[$table])) {
                $fields = (array) ($_POST['fields'] ?? []);
                $fields[$relationColumn] = $personId;
                admin_save_record($pdo, $table, $fields);
            } else {
                throw new RuntimeException('Invalid profile action.');
            }

            // Coming back to the tab the record was saved from, instead of the main card.
            $returnPanel = (string) ($_POST['return_panel'] ?? '');
            $savedHash = $returnPanel !== ''
                ? '#panel-' . rawurlencode($returnPanel)
                : ($action === 'update_main' ? '' : '#panel-' . rawurlencode($table));
            header(
                'Location: ' . khotwa_url('admin/person.php') . '?type=' . rawurlencode($type)
                . '&id=' . $personId . '&saved=1' . $savedHash
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

    // What a visitor sees when they open this teacher on the homepage. Kept beside
    // the raw record so the office can check the public card without leaving here.
    $websiteProfile = null;
    if ($type === 'teacher') {
        $websiteProfile = homepage_team_member($pdo, $personId);
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
  <?= khotwa_head_fonts() ?>
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
                  <?php // One button; the file type is picked from the menu it opens. ?>
                  <div class="qr-download" data-qr-menu>
                    <button
                      class="secondary-action qr-download-toggle"
                      type="button"
                      data-qr-menu-toggle
                      aria-haspopup="true"
                      aria-expanded="false"
                    >
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                      Download QR code
                      <svg class="qr-download-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="qr-download-menu" data-qr-menu-list hidden>
                      <button type="button" data-qr-download="png">PNG image</button>
                      <button type="button" data-qr-download="jpg">JPG image</button>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              <span class="profile-id">ID <?= e((string) $personId) ?></span>
            </div>
          </section>


          <?php
          // One button per section so the whole profile is reachable without scrolling.
          $profileTabs = ['main' => ucfirst($type) . ' information'];
          if ($websiteProfile !== null) {
              $profileTabs['website'] = 'Website profile';
          }
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
                    // Portal sign-in is checked against the users table, so the copy of
                    // the password sitting on the teacher row controls nothing. It is
                    // shown as a pointer to the account that does instead of a box that
                    // has to be filled in again on every edit.
                    if ($columnName === 'password_hash') {
                        continue;
                    }
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
                  <?php if ($type === 'teacher'): ?>
                    <div class="admin-field admin-field-pointer">
                      <span>Password</span>
                      <p>
                        <?= isset($linkedTables['users'])
                            ? "Sign-in is handled by this teacher's portal account. Open the Portal account section to set or reset the password."
                            : "Sign-in is handled by this teacher's portal account, which an administrator manages." ?>
                      </p>
                    </div>
                  <?php endif; ?>
                </div>
              </fieldset>
              <div class="record-form-actions">
                <button class="secondary-action edit-record-button" type="button" data-edit-toggle>Edit</button>
                <button class="primary-action" type="submit" hidden data-save-record>Save</button>
                <button class="secondary-action" type="button" hidden data-cancel-edit>Cancel</button>
              </div>
            </form>
          </section>


          <?php if ($websiteProfile !== null): ?>
            <?php
            $isPublished = (string) ($person['status'] ?? '') === 'active'
                && (int) ($person['show_on_website'] ?? 0) === 1;
            $hiddenReason = (string) ($person['status'] ?? '') !== 'active'
                ? 'Not on the website because this teacher is not active.'
                : 'Not on the website because "Show On Website" is off.';
            $yearsLabel = static fn (?int $years): string => $years === null
                ? 'no date set yet'
                : ($years === 0 ? 'less than a year' : $years . ($years === 1 ? ' year' : ' years'));

            // The card reads these fields, so each one is edited here once and the
            // help line says what it turns into on the public page.
            $websiteFields = [
                'show_on_website' => 'Takes this teacher on or off the Our team section.',
                'photo_path' => 'Shown on the card; initials are used when there is none.',
                'teaches_primary' => '',
                'teaches_intermediate' => '',
                'teaches_secondary' => 'The three switches together make the Teaches line on the card.',
                'teaching_since' => 'The card counts this into "' . $yearsLabel($websiteProfile['years_experience']) . ' of experience".',
                'joined_center_on' => 'The card counts this into "' . $yearsLabel($websiteProfile['years_at_center']) . ' at the center".',
                'certifications_en' => '',
                'certifications_ar' => 'Falls back to the English line when left empty.',
                'video_url' => 'A YouTube link adds the video button; anything else is ignored.',
            ];
            $columnsByName = [];
            foreach ($mainColumns as $mainColumn) {
                $columnsByName[(string) $mainColumn['COLUMN_NAME']] = $mainColumn;
            }
            ?>
            <section
              class="data-panel website-profile"
              id="panel-website"
              role="tabpanel"
              data-profile-panel="website"
              <?= $activeTab === 'website' ? '' : 'hidden' ?>
            >
              <div class="panel-heading">
                <div><span>Public page</span><h2>Website profile</h2></div>
                <div class="website-profile-chips">
                  <span class="website-state<?= $isPublished ? ' is-live' : '' ?>">
                    <?= $isPublished ? 'Live on the website' : 'Not on the website' ?>
                  </span>
                  <span class="read-only-status" data-edit-status>Read only</span>
                </div>
              </div>

              <p class="website-profile-lead">
                <?= $isPublished
                    ? 'These are the details behind the card visitors open from the Our team section of the homepage.'
                    : e($hiddenReason) ?>
              </p>

              <?php // Subjects belong to another table, so they are named here rather
                    // than edited: this is the one value on the card with no field. ?>
              <p class="website-profile-subjects">
                <span>Subjects on the card</span>
                <strong><?= e((string) $websiteProfile['subjects_en']) ?></strong>
                <?php if (trim((string) $websiteProfile['subjects_ar']) !== ''): ?>
                  <b dir="rtl"><?= e((string) $websiteProfile['subjects_ar']) ?></b>
                <?php endif; ?>
                <em>Edited in the Assigned subjects section.</em>
              </p>

              <form class="website-profile-form" method="post" enctype="multipart/form-data" data-edit-form>
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_main">
                <input type="hidden" name="table" value="<?= e($mainTable) ?>">
                <input type="hidden" name="record_id" value="<?= e((string) $personId) ?>">
                <?php // Saving from here lands back on this tab rather than the main card. ?>
                <input type="hidden" name="return_panel" value="website">

                <fieldset class="record-fieldset" disabled data-edit-fields>
                  <div class="record-form-grid">
                    <?php foreach ($websiteFields as $websiteField => $fieldHelp): ?>
                      <?php if (!isset($columnsByName[$websiteField])) continue; ?>
                      <?php admin_render_field(
                          $pdo,
                          $mainTable,
                          $columnsByName[$websiteField],
                          $person[$websiteField] ?? '',
                          isset($mainEditableNames[$websiteField]) ? [] : [$websiteField => $person[$websiteField] ?? ''],
                          help: $fieldHelp
                      ); ?>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <div class="record-form-actions">
                  <button class="secondary-action edit-record-button" type="button" data-edit-toggle>Edit</button>
                  <button class="primary-action" type="submit" hidden data-save-record>Save website details</button>
                  <button class="secondary-action" type="button" hidden data-cancel-edit>Cancel</button>
                </div>
              </form>

              <p class="website-profile-note">
                The name and the account status stay on the <?= e(ucfirst($type)) ?> information tab.
              </p>
            </section>
          <?php endif; ?>

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
  <script src="<?= e(khotwa_asset('vendor/qrcode.min.js')) ?>" defer></script>
  <?php render_toasts([
      ['type' => 'success', 'text' => $message ?? ''],
      ['type' => 'error', 'text' => $error ?? ''],
  ]); ?>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/qr-tools.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/schedule-planner.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
