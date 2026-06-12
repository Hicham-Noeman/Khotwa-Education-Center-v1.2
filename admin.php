<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-data.php';

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

function status_class(string $value): string
{
    $safe = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value));
    return 'status-' . trim((string) $safe, '-');
}

function render_value(string $key, mixed $value): string
{
    if ($value === null || $value === '') {
        return '<span class="empty-value">—</span>';
    }

    $text = (string) $value;
    if (in_array($key, ['status', 'daily_status', 'payment_status', 'role', 'warning_type'], true)) {
        return '<span class="status-pill ' . e(status_class($text)) . '">' . e(ucwords(str_replace('_', ' ', $text))) . '</span>';
    }
    if (str_contains($key, 'amount')) {
        return e(number_format((float) $text, 2));
    }

    return e($text);
}

$navigation = admin_navigation();
$view = (string) ($_GET['view'] ?? 'overview');
if (!isset($navigation[$view])) {
    $view = 'overview';
}

$pageTitle = $navigation[$view]['label'];
$pageDescription = '';
$columns = [];
$rows = [];
$metrics = [];
$recentAttendance = [];
$databaseError = '';
$formError = '';
$message = isset($_GET['created']) ? 'Record added successfully.' : '';
if (isset($_GET['deleted'])) {
    $deletedCount = max(0, (int) $_GET['deleted']);
    $message = $deletedCount === 1
        ? 'Record deleted successfully.'
        : $deletedCount . ' records deleted successfully.';
}
$isAdding = isset($_GET['new']) && $view !== 'overview';
$formColumns = [];

try {
    $pdo = khotwa_db();
    $viewTables = admin_view_tables();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            admin_verify_csrf();
            $postedView = (string) ($_POST['view'] ?? '');
            if (!isset($viewTables[$postedView])) {
                throw new RuntimeException('Invalid data section.');
            }

            $action = isset($_POST['delete_id']) ? 'delete' : (string) ($_POST['action'] ?? '');
            if ($action === 'add') {
                admin_save_record($pdo, $viewTables[$postedView], (array) ($_POST['fields'] ?? []));
                header('Location: admin.php?view=' . rawurlencode($postedView) . '&created=1');
                exit;
            }

            if ($action === 'delete') {
                $recordIds = [(int) ($_POST['delete_id'] ?? 0)];
            } elseif ($action === 'bulk_delete') {
                $recordIds = (array) ($_POST['record_ids'] ?? []);
            } else {
                throw new RuntimeException('Invalid table action.');
            }

            $deletedCount = admin_delete_records(
                $pdo,
                $viewTables[$postedView],
                $recordIds,
                (int) ($user['id'] ?? 0)
            );
            header(
                'Location: admin.php?view=' . rawurlencode($postedView)
                . '&deleted=' . $deletedCount
            );
            exit;
        } catch (Throwable $exception) {
            $formError = $exception->getMessage();
            $isAdding = (string) ($_POST['action'] ?? '') === 'add';
        }
    }

    if ($isAdding && isset($viewTables[$view])) {
        $formColumns = admin_editable_columns($pdo, $viewTables[$view]);
    }

    if ($view === 'overview') {
        $metrics = [
            ['value' => (string) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(), 'label' => 'Active students', 'color' => 'orange'],
            ['value' => (string) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn(), 'label' => 'Active teachers', 'color' => 'green'],
            ['value' => (string) $pdo->query("SELECT COUNT(*) FROM student_subject_enrollments WHERE status = 'active'")->fetchColumn(), 'label' => 'Active enrollments', 'color' => 'pink'],
            ['value' => number_format((float) $pdo->query("SELECT COALESCE(SUM(GREATEST(expected_amount - paid_amount, 0)), 0) FROM student_subscription_months")->fetchColumn(), 2), 'label' => 'Open balance', 'color' => 'navy'],
        ];
        $recentAttendance = $pdo->query(
            "SELECT attendance_date, student_name_en, daily_status, attended_subject_count, missed_subject_count
             FROM student_daily_attendance_summary
             ORDER BY attendance_date DESC, student_name_en LIMIT 8"
        )->fetchAll();
        $pageDescription = 'A live view of students, educators, attendance, enrollments, and financial activity.';
    } elseif ($view === 'students') {
        $pageDescription = 'Student profiles and their current academic placement. Double-click a student to open every linked record.';
        $columns = [
            'id' => 'ID', 'student_name' => 'Student', 'student_name_ar' => 'Arabic name',
            'gender' => 'Gender', 'date_of_birth' => 'Birth date', 'grade_name' => 'Current grade',
            'current_teaching_language' => 'Language', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT students.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    CONCAT(students.first_name_ar, ' ', students.last_name_ar) student_name_ar,
                    students.gender, students.date_of_birth,
                    COALESCE(student_academic_records.grade_name, 'Not assigned') grade_name,
                    students.current_teaching_language, students.status
             FROM students
             LEFT JOIN student_academic_records ON student_academic_records.student_id = students.id
              AND student_academic_records.is_current = 1
             ORDER BY students.last_name_en, students.first_name_en"
        )->fetchAll();
    } elseif ($view === 'teachers') {
        $pageDescription = 'Educator profiles and assigned subjects. Double-click a teacher to open every linked record.';
        $columns = [
            'id' => 'ID', 'teacher_name' => 'Teacher', 'email' => 'Email',
            'phone_number' => 'Phone', 'subjects' => 'Subjects', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT teachers.id,
                    TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) teacher_name,
                    teachers.email, teachers.phone_number,
                    COALESCE(GROUP_CONCAT(subjects.name_en ORDER BY subjects.name_en SEPARATOR ', '), 'Not assigned') subjects,
                    teachers.status
             FROM teachers
             LEFT JOIN teacher_subjects ON teacher_subjects.teacher_id = teachers.id
             LEFT JOIN subjects ON subjects.id = teacher_subjects.subject_id
             GROUP BY teachers.id ORDER BY teachers.last_name, teachers.first_name"
        )->fetchAll();
    } elseif ($view === 'attendance') {
        $pageDescription = 'Daily attendance totals, check-in times, and subject session results.';
        $columns = [
            'attendance_date' => 'Date', 'student_name_en' => 'Student', 'check_in_time' => 'Check in',
            'check_out_time' => 'Check out', 'daily_status' => 'Daily status',
            'attended_subject_count' => 'Attended', 'missed_subject_count' => 'Missed',
        ];
        $rows = $pdo->query(
            "SELECT daily_attendance_id AS id, attendance_date, student_name_en, check_in_time, check_out_time,
                    daily_status, attended_subject_count, missed_subject_count
             FROM student_daily_attendance_summary ORDER BY attendance_date DESC, student_name_en"
        )->fetchAll();
    } elseif ($view === 'subjects') {
        $pageDescription = 'Subjects offered by the center and their teaching coverage.';
        $columns = [
            'id' => 'ID', 'name_en' => 'Subject', 'name_ar' => 'Arabic name',
            'teacher_count' => 'Teachers', 'enrollment_count' => 'Enrollments', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT subjects.id, subjects.name_en, subjects.name_ar, subjects.status,
                    COUNT(DISTINCT teacher_subjects.teacher_id) teacher_count,
                    COUNT(DISTINCT student_subject_enrollments.id) enrollment_count
             FROM subjects
             LEFT JOIN teacher_subjects ON teacher_subjects.subject_id = subjects.id
             LEFT JOIN student_subject_enrollments ON student_subject_enrollments.subject_id = subjects.id
             GROUP BY subjects.id ORDER BY subjects.name_en"
        )->fetchAll();
    } elseif ($view === 'enrollments') {
        $pageDescription = 'Connections between students, teachers, subjects, and academic years.';
        $columns = [
            'id' => 'ID', 'student_name' => 'Student', 'subject_name' => 'Subject',
            'teacher_name' => 'Teacher', 'academic_year' => 'Academic year',
            'start_date' => 'Start date', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT student_subject_enrollments.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    subjects.name_en subject_name,
                    TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) teacher_name,
                    student_subject_enrollments.academic_year, student_subject_enrollments.start_date,
                    student_subject_enrollments.status
             FROM student_subject_enrollments
             INNER JOIN students ON students.id = student_subject_enrollments.student_id
             INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
             INNER JOIN teachers ON teachers.id = student_subject_enrollments.teacher_id
             ORDER BY student_subject_enrollments.academic_year DESC, student_name"
        )->fetchAll();
    } elseif ($view === 'subscriptions') {
        $pageDescription = 'Monthly billing status and outstanding amounts for each student.';
        $columns = [
            'student_name' => 'Student', 'billing_period' => 'Billing period',
            'expected_amount' => 'Expected', 'paid_amount' => 'Paid',
            'balance_amount' => 'Balance', 'payment_status' => 'Payment status',
        ];
        $rows = $pdo->query(
            "SELECT student_subscription_months.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    CONCAT(student_subscription_months.billing_year, '-', LPAD(student_subscription_months.billing_month, 2, '0')) billing_period,
                    student_subscription_months.expected_amount, student_subscription_months.paid_amount,
                    GREATEST(student_subscription_months.expected_amount - student_subscription_months.paid_amount, 0) balance_amount,
                    student_subscription_months.payment_status
             FROM student_subscription_months
             INNER JOIN students ON students.id = student_subscription_months.student_id
             ORDER BY student_subscription_months.billing_year DESC,
                      student_subscription_months.billing_month DESC, student_name"
        )->fetchAll();
    } elseif ($view === 'payments') {
        $pageDescription = 'Recorded subscription payments and receipt references.';
        $columns = [
            'id' => 'ID', 'student_name' => 'Student', 'paid_at' => 'Paid at',
            'paid_amount' => 'Amount', 'receipt_number' => 'Receipt', 'notes' => 'Notes',
        ];
        $rows = $pdo->query(
            "SELECT student_subscription_payments.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    student_subscription_payments.paid_at, student_subscription_payments.paid_amount,
                    student_subscription_payments.receipt_number, student_subscription_payments.notes
             FROM student_subscription_payments
             INNER JOIN students ON students.id = student_subscription_payments.student_id
             ORDER BY student_subscription_payments.paid_at DESC"
        )->fetchAll();
    } elseif ($view === 'warnings') {
        $pageDescription = 'Behavior and learning warnings recorded by the center team.';
        $columns = [
            'warning_date' => 'Date', 'student_name' => 'Student', 'teacher_name' => 'Teacher',
            'warning_type' => 'Type', 'reason' => 'Reason', 'parent_notified_label' => 'Parent notified',
        ];
        $rows = $pdo->query(
            "SELECT student_warnings.id, student_warnings.warning_date,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    COALESCE(TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))), 'Center team') teacher_name,
                    student_warnings.warning_type, student_warnings.reason,
                    IF(student_warnings.parent_notified = 1, 'Yes', 'No') parent_notified_label
             FROM student_warnings
             INNER JOIN students ON students.id = student_warnings.student_id
             LEFT JOIN teachers ON teachers.id = student_warnings.teacher_id
             ORDER BY student_warnings.warning_date DESC"
        )->fetchAll();
    } elseif ($view === 'users') {
        $pageDescription = 'Portal users, roles, access status, and recent sign-ins.';
        $columns = [
            'id' => 'ID', 'user_name' => 'User', 'email' => 'Email',
            'role' => 'Role', 'status' => 'Status', 'last_login_at' => 'Last login',
        ];
        $rows = $pdo->query(
            "SELECT id, TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) user_name,
                    email, role, status, last_login_at
             FROM users ORDER BY role, user_name"
        )->fetchAll();
    }
} catch (Throwable $exception) {
    $databaseError = 'The administrator panel could not read the database. Please confirm that MySQL is running.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title><?= e($pageTitle) ?> | Khotwa Administration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
</head>
<body class="admin-page">
  <div class="admin-shell" data-admin-shell>
    <?php admin_render_sidebar($user, $view); ?>
    <div class="admin-stage">
      <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <main class="admin-content">
        <section class="content-heading">
          <div>
            <span class="content-kicker">Control center</span>
            <h1><?= e($pageTitle) ?></h1>
            <p><?= e($pageDescription) ?></p>
          </div>
          <div class="live-indicator"><span></span>Live database</div>
        </section>

        <?php if ($databaseError !== ''): ?>
          <div class="database-alert"><?= e($databaseError) ?></div>
        <?php elseif ($view === 'overview'): ?>
          <section class="metrics-grid" aria-label="Dashboard statistics">
            <?php foreach ($metrics as $metric): ?>
              <article class="metric-card metric-<?= e($metric['color']) ?>">
                <span class="metric-dot"></span><strong><?= e($metric['value']) ?></strong><p><?= e($metric['label']) ?></p>
              </article>
            <?php endforeach; ?>
          </section>
          <section class="overview-grid">
            <article class="data-panel overview-attendance">
              <div class="panel-heading">
                <div><span>Latest records</span><h2>Recent attendance</h2></div>
                <a href="admin.php?view=attendance">View all</a>
              </div>
              <div class="table-scroll">
                <table>
                  <thead><tr><th>Date</th><th>Student</th><th>Status</th><th>Attended</th><th>Missed</th></tr></thead>
                  <tbody>
                    <?php if ($recentAttendance === []): ?>
                      <tr><td class="empty-row" colspan="5">No attendance records yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentAttendance as $row): ?>
                        <tr><td><?= e($row['attendance_date']) ?></td><td><strong><?= e($row['student_name_en']) ?></strong></td><td><?= render_value('daily_status', $row['daily_status']) ?></td><td><?= e((string) $row['attended_subject_count']) ?></td><td><?= e((string) $row['missed_subject_count']) ?></td></tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <aside class="quick-panel">
              <span>Quick access</span><h2>Move through your center.</h2>
              <a href="admin.php?view=students"><?= admin_icon('students') ?><span><strong>Students</strong><small>Profiles and grades</small></span></a>
              <a href="admin.php?view=teachers"><?= admin_icon('teachers') ?><span><strong>Teachers</strong><small>Team and subjects</small></span></a>
              <a href="admin.php?view=attendance"><?= admin_icon('attendance') ?><span><strong>Attendance</strong><small>Daily and subject records</small></span></a>
            </aside>
          </section>
        <?php else: ?>
          <?php if ($message !== ''): ?><div class="form-notice success-notice"><?= e($message) ?></div><?php endif; ?>
          <?php if ($formError !== ''): ?><div class="form-notice error-notice"><?= e($formError) ?></div><?php endif; ?>

          <?php if ($isAdding): ?>
            <section class="data-panel record-editor">
              <div class="panel-heading">
                <div><span>New record</span><h2>Add <?= e($pageTitle) ?></h2></div>
                <a href="admin.php?view=<?= e($view) ?>">Cancel</a>
              </div>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="view" value="<?= e($view) ?>">
                <div class="record-form-grid">
                  <?php foreach ($formColumns as $column): ?>
                    <?php
                    $fieldName = (string) $column['COLUMN_NAME'];
                    $fieldValue = $_POST['fields'][$fieldName] ?? admin_default_field_value($column);
                    admin_render_field($pdo, admin_view_tables()[$view], $column, $fieldValue);
                    ?>
                  <?php endforeach; ?>
                </div>
                <div class="record-form-actions">
                  <button class="primary-action" type="submit">Save record</button>
                  <a class="secondary-action" href="admin.php?view=<?= e($view) ?>">Cancel</a>
                </div>
              </form>
            </section>
          <?php endif; ?>

          <section class="data-panel">
            <form method="post" data-table-actions>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <div class="panel-heading table-panel-heading">
                <div><span>Database table</span><h2><?= e($pageTitle) ?></h2></div>
                <label class="table-search">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                  <input type="search" placeholder="Search this table" data-table-search>
                </label>
                <div class="table-heading-actions">
                  <strong class="record-count"><?= e((string) count($rows)) ?> records</strong>
                  <button class="bulk-delete-button" type="submit" name="action" value="bulk_delete" disabled data-bulk-delete>
                    Delete selected
                  </button>
                  <a class="add-record-button" href="admin.php?view=<?= e($view) ?>&new=1">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add record
                  </a>
                </div>
              </div>
              <div class="table-scroll">
                <table data-admin-table>
                  <thead>
                    <tr>
                      <th class="selection-column">
                        <input type="checkbox" aria-label="Select all visible records" data-select-all>
                      </th>
                      <?php foreach (array_values($columns) as $sortIndex => $label): ?>
                        <th aria-sort="none">
                          <button class="sort-button" type="button" data-sort-column="<?= e((string) $sortIndex) ?>">
                            <?= e($label) ?><span aria-hidden="true"></span>
                          </button>
                        </th>
                      <?php endforeach; ?>
                      <th class="actions-column">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($rows === []): ?>
                      <tr><td class="empty-row" colspan="<?= e((string) (count($columns) + 2)) ?>">No records found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($rows as $row): ?>
                        <?php
                        if ($view === 'students') {
                            $detailUrl = 'admin-person.php?type=student&id=' . (int) $row['id'];
                        } elseif ($view === 'teachers') {
                            $detailUrl = 'admin-person.php?type=teacher&id=' . (int) $row['id'];
                        } else {
                            $detailUrl = 'admin-record.php?view=' . rawurlencode($view) . '&id=' . (int) $row['id'];
                        }
                        ?>
                        <tr class="is-openable" data-record-row data-detail-url="<?= e($detailUrl) ?>" title="Double-click to open the full record" tabindex="0">
                          <td class="selection-column">
                            <input type="checkbox" name="record_ids[]" value="<?= e((string) $row['id']) ?>" aria-label="Select record <?= e((string) $row['id']) ?>" data-record-select>
                          </td>
                          <?php foreach ($columns as $key => $label): ?>
                            <td data-sort-value="<?= e((string) ($row[$key] ?? '')) ?>"><?= render_value($key, $row[$key] ?? null) ?></td>
                          <?php endforeach; ?>
                          <td class="actions-column">
                            <button class="inline-delete-button" type="submit" name="delete_id" value="<?= e((string) $row['id']) ?>" data-delete-record>
                              Delete
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="search-empty" hidden><td colspan="<?= e((string) (count($columns) + 2)) ?>">No matching records.</td></tr>
                  </tbody>
                </table>
              </div>
            </form>
          </section>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <script src="language.js?v=<?= e((string) filemtime(__DIR__ . '/language.js')) ?>" defer></script>
  <script src="admin.js?v=<?= e((string) filemtime(__DIR__ . '/admin.js')) ?>" defer></script>
</body>
</html>
