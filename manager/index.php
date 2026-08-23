<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

$user = require_roles(['manager']);
$views = [
    'overview' => ['label' => 'Dashboard', 'description' => 'A live command view of attendance, enrollment, staffing, content, and financial activity.'],
];
$view = (string) ($_GET['view'] ?? 'overview');
if ($view !== 'overview' && admin_user_can_access_view($user, $view)) {
    header('Location: ' . khotwa_url('admin/index.php') . '?view=' . rawurlencode($view));
    exit;
}
if (!isset($views[$view])) {
    $view = 'overview';
}

$metrics = [];
$summaryMetrics = [];
$recentAttendance = [];
$attendanceChart = ['labels' => [], 'present' => [], 'late' => [], 'absent' => []];
$subjectChart = ['labels' => [], 'values' => []];
$yearChart = ['labels' => [], 'values' => []];
$teacherCoverageChart = ['labels' => [], 'subjects' => [], 'students' => []];
$gradeChart = ['labels' => [], 'values' => []];
$paymentChart = ['labels' => [], 'values' => []];
$warningChart = ['labels' => [], 'values' => []];
$subjectRows = [];
$teacherCoverageRows = [];
$paymentStatusRows = [];
$columns = [];
$rows = [];
$databaseError = '';

function manager_status_class(string $value): string
{
    return 'status-' . trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-');
}

function manager_value(string $key, mixed $value): string
{
    if ($value === null || $value === '') {
        return '<span class="empty-value">-</span>';
    }

    $text = (string) $value;
    if (in_array($key, ['status', 'daily_status'], true)) {
        return '<span class="status-pill ' . e(manager_status_class($text)) . '">'
            . e(ucwords(str_replace('_', ' ', $text)))
            . '</span>';
    }

    return e($text);
}

function render_manager_sidebar(array $user, string $activeView): void
{
    $groups = [
        'Workspace' => [
            'overview' => [
                'label' => 'Dashboard',
                'href' => khotwa_url('manager/index.php'),
                'icon' => 'overview',
            ],
        ],
    ];
    foreach (admin_navigation_for_user($user) as $key => $item) {
        if (($item['sidebar'] ?? true) === false) {
            continue;
        }
        $groups[$item['group']][$key] = [
            'label' => $item['label'],
            'href' => khotwa_url('admin/index.php') . '?view=' . $key,
            'icon' => $key,
        ];
    }
    ?>
    <aside class="admin-sidebar manager-sidebar" id="admin-sidebar" aria-label="Manager navigation">
      <div class="sidebar-top">
        <a class="admin-brand" href="<?= e(khotwa_url('manager/index.php')) ?>" aria-label="Khotwa manager dashboard">
          <span class="admin-brand-mark">K<span>.</span></span>
          <span class="admin-brand-copy"><strong>Khotwa</strong><small>Manager Portal</small></span>
        </a>
        <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
          <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 7-5 5 5 5"/></svg>
          <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m10 7 5 5-5 5"/></svg>
        </button>
      </div>
      <nav class="admin-nav">
        <?php foreach ($groups as $groupName => $items): ?>
          <section class="nav-group">
            <h2><?= e($groupName) ?></h2>
            <?php foreach ($items as $key => $item): ?>
              <?php $isActive = $activeView === $key; ?>
              <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>">
                <?= admin_icon($item['icon']) ?><span><?= e($item['label']) ?></span>
                <?php if ($isActive): ?><i></i><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </section>
          <?php endforeach; ?>
      </nav>
      <div class="sidebar-account">
        <span class="account-avatar"><?= e(strtoupper(substr((string) $user['first_name'], 0, 1))) ?></span>
        <span class="account-copy">
          <strong><?= e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?></strong>
          <small>Manager</small>
        </span>
        <a href="<?= e(khotwa_url('logout.php')) ?>" aria-label="Log out" title="Log out"><?= admin_icon('users') ?></a>
      </div>
    </aside>
    <?php
}

try {
    $pdo = khotwa_db();

    if ($view === 'overview') {
        $activeStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
        $activeTeachers = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
        $activeEnrollments = (int) $pdo->query("SELECT COUNT(*) FROM student_subject_enrollments WHERE status = 'active'")->fetchColumn();
        $openBalance = (float) $pdo->query(
            'SELECT COALESCE(SUM(GREATEST(expected_amount - paid_amount, 0)), 0) FROM student_subscription_months'
        )->fetchColumn();
        $attendanceWindowDays = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM (
                 SELECT attendance_date
                 FROM student_daily_attendance
                 GROUP BY attendance_date
                 ORDER BY attendance_date DESC
                 LIMIT 30
             ) latest_days"
        )->fetchColumn();
        $activeAttendanceMarks = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM student_daily_attendance attendance
             INNER JOIN students
               ON students.id = attendance.student_id
              AND students.status = 'active'
             INNER JOIN (
                 SELECT attendance_date
                 FROM student_daily_attendance
                 GROUP BY attendance_date
                 ORDER BY attendance_date DESC
                 LIMIT 30
             ) latest_days ON latest_days.attendance_date = attendance.attendance_date
             WHERE attendance.status IN ('present', 'late', 'left_early')"
        )->fetchColumn();
        $attendanceRate = $activeStudents > 0 && $attendanceWindowDays > 0
            ? 100 * $activeAttendanceMarks / ($activeStudents * $attendanceWindowDays)
            : 0.0;
        $paymentsThisMonth = (float) $pdo->query(
            "SELECT COALESCE(SUM(paid_amount), 0)
             FROM student_subscription_payments
             WHERE DATE_FORMAT(paid_at, '%Y-%m') = (
                 SELECT DATE_FORMAT(MAX(latest.paid_at), '%Y-%m')
                 FROM (SELECT paid_at FROM student_subscription_payments) latest
             )"
        )->fetchColumn();
        $warningsThisYear = (int) $pdo->query(
            "SELECT COUNT(*) FROM student_warnings
             WHERE warning_year = YEAR(CURDATE())
             AND status IN ('issued', 'assigned', 'resolved')"
        )->fetchColumn();
        $activeSubjects = (int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn();
        $cashMetric = admin_month_cash_metric($pdo);

        $metrics = [
            ['value' => number_format($activeStudents), 'label' => 'Active students', 'color' => 'orange'],
            ['value' => number_format($activeTeachers), 'label' => 'Active teachers', 'color' => 'green'],
            ['value' => number_format($activeEnrollments), 'label' => 'Active enrollments', 'color' => 'pink'],
            [
                'value' => number_format($cashMetric['collected'], 2),
                'label' => 'Collected · ' . $cashMetric['label'],
                'sub' => 'Net expected this month: ' . number_format($cashMetric['expected'], 2),
                'color' => 'navy',
            ],
        ];
        $summaryMetrics = [
            ['value' => number_format($attendanceRate, 1) . '%', 'label' => 'Active-student attendance rate'],
            ['value' => number_format($paymentsThisMonth, 2), 'label' => 'Latest month payments'],
            ['value' => number_format($warningsThisYear), 'label' => 'Warnings this year'],
            ['value' => number_format($activeSubjects), 'label' => 'Active subjects'],
        ];

        $attendanceRows = $pdo->query(
            "SELECT latest.attendance_date,
                    SUM(CASE WHEN students.id IS NOT NULL AND attendance.status = 'present' THEN 1 ELSE 0 END) present_count,
                    SUM(CASE WHEN students.id IS NOT NULL AND attendance.status IN ('late', 'left_early') THEN 1 ELSE 0 END) late_count,
                    SUM(CASE WHEN students.id IS NOT NULL AND attendance.status IN ('absent', 'excused') THEN 1 ELSE 0 END) absent_count
             FROM (
                 SELECT attendance_date
                 FROM student_daily_attendance
                 GROUP BY attendance_date
                 ORDER BY attendance_date DESC
                 LIMIT 14
             ) latest
             LEFT JOIN student_daily_attendance attendance
               ON attendance.attendance_date = latest.attendance_date
             LEFT JOIN students
               ON students.id = attendance.student_id
              AND students.status = 'active'
             GROUP BY latest.attendance_date
             ORDER BY latest.attendance_date"
        )->fetchAll();
        foreach ($attendanceRows as $row) {
            $presentCount = (int) $row['present_count'];
            $lateCount = (int) $row['late_count'];
            $recordedAbsentCount = (int) $row['absent_count'];
            $missingCount = max(0, $activeStudents - ($presentCount + $lateCount + $recordedAbsentCount));
            $attendanceChart['labels'][] = (new DateTimeImmutable((string) $row['attendance_date']))->format('d/m');
            $attendanceChart['present'][] = $presentCount;
            $attendanceChart['late'][] = $lateCount;
            $attendanceChart['absent'][] = $recordedAbsentCount + $missingCount;
        }

        $subjectRows = $pdo->query(
            "SELECT subjects.name_en label, COUNT(student_subject_enrollments.id) value
             FROM subjects
             LEFT JOIN student_subject_enrollments
               ON student_subject_enrollments.subject_id = subjects.id
              AND student_subject_enrollments.status = 'active'
             WHERE subjects.status = 'active'
             GROUP BY subjects.id, subjects.name_en
             ORDER BY value DESC, subjects.name_en
             LIMIT 8"
        )->fetchAll();
        $subjectChart = [
            'labels' => array_column($subjectRows, 'label'),
            'values' => array_map('intval', array_column($subjectRows, 'value')),
        ];

        $yearRows = $pdo->query(
            "SELECT CAST(student_academic_records.academic_year AS CHAR) label,
                    COUNT(DISTINCT student_academic_records.student_id) value
             FROM student_academic_records
             INNER JOIN students
               ON students.id = student_academic_records.student_id
              AND students.status = 'active'
             GROUP BY student_academic_records.academic_year
             ORDER BY student_academic_records.academic_year"
        )->fetchAll();
        if ($yearRows === [] && $activeStudents > 0) {
            $yearRows = [['label' => (string) date('Y'), 'value' => $activeStudents]];
        }
        $yearChart = [
            'labels' => array_column($yearRows, 'label'),
            'values' => array_map('intval', array_column($yearRows, 'value')),
        ];

        $teacherCoverageRows = $pdo->query(
            "SELECT teachers.id,
                    TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) label,
                    COUNT(DISTINCT teacher_subjects.subject_id) subject_count,
                    COUNT(DISTINCT CASE WHEN student_subject_enrollments.status = 'active'
                                       THEN student_subject_enrollments.student_id END) student_count
             FROM teachers
             LEFT JOIN teacher_subjects
               ON teacher_subjects.teacher_id = teachers.id
              AND teacher_subjects.status = 'active'
             LEFT JOIN student_subject_enrollments
               ON student_subject_enrollments.teacher_id = teachers.id
              AND student_subject_enrollments.status = 'active'
             WHERE teachers.status = 'active'
             GROUP BY teachers.id
             ORDER BY student_count DESC, subject_count DESC, label
             LIMIT 8"
        )->fetchAll();
        $teacherCoverageChart = [
            'labels' => array_column($teacherCoverageRows, 'label'),
            'subjects' => array_map('intval', array_column($teacherCoverageRows, 'subject_count')),
            'students' => array_map('intval', array_column($teacherCoverageRows, 'student_count')),
        ];

        $gradeBuckets = [
            '90-100' => 0,
            '80-89' => 0,
            '70-79' => 0,
            '60-69' => 0,
            'Below 60' => 0,
            'No grade' => 0,
        ];
        $gradeAverageRows = $pdo->query(
            "SELECT student_academic_records.final_average
             FROM students
             LEFT JOIN student_academic_records
               ON student_academic_records.student_id = students.id
              AND student_academic_records.is_current = 1
             WHERE students.status = 'active'"
        )->fetchAll();
        foreach ($gradeAverageRows as $row) {
            if ($row['final_average'] === null || $row['final_average'] === '') {
                $gradeBuckets['No grade']++;
                continue;
            }

            $average = (float) $row['final_average'];
            if ($average >= 90) {
                $gradeBuckets['90-100']++;
            } elseif ($average >= 80) {
                $gradeBuckets['80-89']++;
            } elseif ($average >= 70) {
                $gradeBuckets['70-79']++;
            } elseif ($average >= 60) {
                $gradeBuckets['60-69']++;
            } else {
                $gradeBuckets['Below 60']++;
            }
        }
        foreach ($gradeBuckets as $label => $value) {
            if ($value > 0) {
                $gradeChart['labels'][] = $label;
                $gradeChart['values'][] = $value;
            }
        }

        $paymentStatusRows = $pdo->query(
            "SELECT payment_status label, COUNT(*) value
             FROM student_subscription_months
             GROUP BY payment_status
             ORDER BY FIELD(payment_status, 'not_paid', 'partial_paid', 'paid', 'overpaid', 'paused', 'unsubscribed')"
        )->fetchAll();
        $paymentChart = [
            'labels' => array_map(
                static fn (string $label): string => ucwords(str_replace('_', ' ', $label)),
                array_column($paymentStatusRows, 'label')
            ),
            'values' => array_map('intval', array_column($paymentStatusRows, 'value')),
        ];

        $warningRows = $pdo->query(
            "SELECT CAST(warning_year AS CHAR) label, COUNT(*) value
             FROM student_warnings
             GROUP BY warning_year
             ORDER BY warning_year"
        )->fetchAll();
        $warningChart = [
            'labels' => array_column($warningRows, 'label'),
            'values' => array_map('intval', array_column($warningRows, 'value')),
        ];

        $recentAttendance = $pdo->query(
            "SELECT attendance_date, student_name_en, daily_status,
                    attended_subject_count, missed_subject_count
             FROM " . khotwa_daily_attendance_summary_sql() . "
             ORDER BY attendance_date DESC, student_name_en
             LIMIT 8"
        )->fetchAll();
    } elseif ($view === 'students') {
        $columns = [
            'student_name' => 'Student', 'student_name_ar' => 'Arabic name',
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
             LEFT JOIN student_academic_records
               ON student_academic_records.student_id = students.id
              AND student_academic_records.is_current = 1
             ORDER BY students.last_name_en, students.first_name_en"
        )->fetchAll();
    } else {
        $columns = [
            'teacher_name' => 'Teacher', 'email' => 'Email',
            'phone_number' => 'Phone', 'subjects' => 'Subjects',
            'student_count' => 'Students', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT teachers.id,
                    TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) teacher_name,
                    teachers.email, teachers.phone_number,
                    COALESCE(GROUP_CONCAT(DISTINCT subjects.name_en ORDER BY subjects.name_en SEPARATOR ', '), 'Not assigned') subjects,
                    COUNT(DISTINCT CASE WHEN student_subject_enrollments.status = 'active'
                                       THEN student_subject_enrollments.student_id END) student_count,
                    teachers.status
             FROM teachers
             LEFT JOIN teacher_subjects ON teacher_subjects.teacher_id = teachers.id
             LEFT JOIN subjects ON subjects.id = teacher_subjects.subject_id
             LEFT JOIN student_subject_enrollments ON student_subject_enrollments.teacher_id = teachers.id
             GROUP BY teachers.id
             ORDER BY teachers.last_name, teachers.first_name"
        )->fetchAll();
    }
} catch (Throwable $exception) {
    $databaseError = 'The manager dashboard could not read the database. Please confirm that MySQL is running.';
}

$page = $views[$view];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title><?= e($page['label']) ?> | Khotwa Manager Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/manager.css')) ?>">
</head>
<body class="admin-page manager-page manager-view-<?= e($view) ?>">
  <div class="admin-shell" data-admin-shell>
    <?php render_manager_sidebar($user, $view); ?>
    <div class="admin-stage">
      <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <main class="admin-content">
        <section class="content-heading">
          <div>
            <span class="content-kicker">Management overview</span>
            <h1><?= e($page['label']) ?></h1>
            <p><?= e($page['description']) ?></p>
          </div>
          <div class="live-indicator"><span></span>Editable management data</div>
        </section>

        <?php if ($databaseError !== ''): ?>
          <div class="database-alert"><?= e($databaseError) ?></div>
        <?php elseif ($view === 'overview'): ?>
          <section class="metrics-grid" aria-label="Primary center statistics">
            <?php foreach ($metrics as $metric): ?>
              <article class="metric-card metric-<?= e($metric['color']) ?>">
                <span class="metric-dot"></span>
                <strong><?= e($metric['value']) ?></strong>
                <p><?= e($metric['label']) ?></p>
                <?php if (!empty($metric['sub'])): ?><small class="metric-sub"><?= e($metric['sub']) ?></small><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </section>

          <section class="manager-summary-grid" aria-label="Additional center summaries">
            <?php foreach ($summaryMetrics as $summary): ?>
              <article><strong><?= e($summary['value']) ?></strong><span><?= e($summary['label']) ?></span></article>
            <?php endforeach; ?>
          </section>

          <section class="manager-action-strip" aria-label="Manager quick actions">
            <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=students">
              <?= admin_icon('students') ?>
              <span><strong>Edit students</strong><small>Profiles, photos, grades, and linked records</small></span>
            </a>
            <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=teachers">
              <?= admin_icon('teachers') ?>
              <span><strong>Edit teachers</strong><small>Open a teacher to add subjects</small></span>
            </a>
            <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=enrollments&new=1">
              <?= admin_icon('enrollments') ?>
              <span><strong>Assign student</strong><small>Connect students to teacher subjects</small></span>
            </a>
            <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=payments&new=1">
              <?= admin_icon('payments') ?>
              <span><strong>Record payment</strong><small>Update subscriptions and receipts</small></span>
            </a>
            <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=website-content">
              <?= admin_icon('website-content') ?>
              <span><strong>Website content</strong><small>Homepage, contact, and social sections</small></span>
            </a>
          </section>

          <section class="manager-chart-grid">
            <article class="data-panel manager-chart-card manager-chart-wide">
              <div class="panel-heading">
                <div><span>Last 14 days</span><h2>Attendance activity</h2></div>
                <span class="manager-chart-legend"><i class="present"></i>Present <i class="late"></i>Late <i class="absent"></i>Absent</span>
              </div>
              <div class="manager-canvas-wrap">
                <?php if ($attendanceChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No attendance data has been recorded yet.</p>
                <?php else: ?>
                  <canvas
                    data-attendance-chart
                    data-chart="<?= e(json_encode($attendanceChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Attendance activity chart"
                  ></canvas>
                <?php endif; ?>
              </div>
            </article>

            <article class="data-panel manager-chart-card">
              <div class="panel-heading">
                <div><span>Active enrollments</span><h2>Subject mix</h2></div>
              </div>
              <div class="manager-donut-layout">
                <canvas
                  data-subject-chart
                  data-chart="<?= e(json_encode($subjectChart, JSON_UNESCAPED_SLASHES)) ?>"
                  aria-label="Active enrollment subject chart"
                ></canvas>
                <div class="manager-donut-legend">
                  <?php foreach ($subjectRows as $index => $subject): ?>
                    <span><i style="--legend-index: <?= e((string) $index) ?>"></i><?= e((string) $subject['label']) ?><strong><?= e((string) $subject['value']) ?></strong></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </article>

            <article class="data-panel manager-chart-card">
              <div class="panel-heading">
                <div><span>Academic years</span><h2>Students by year</h2></div>
              </div>
              <div class="manager-canvas-wrap manager-canvas-compact">
                <?php if ($yearChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No yearly student data is available yet.</p>
                <?php else: ?>
                  <canvas
                    data-year-chart
                    data-chart="<?= e(json_encode($yearChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Students by academic year chart"
                  ></canvas>
                <?php endif; ?>
              </div>
            </article>

            <article class="data-panel manager-chart-card manager-chart-wide">
              <div class="panel-heading">
                <div><span>Teachers</span><h2>Subjects and student coverage</h2></div>
                <span class="manager-chart-legend"><i class="subjects"></i>Subjects <i class="students"></i>Students</span>
              </div>
              <div class="manager-canvas-wrap manager-canvas-compact">
                <?php if ($teacherCoverageChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No teacher coverage data is available yet.</p>
                <?php else: ?>
                  <canvas
                    data-teacher-chart
                    data-chart="<?= e(json_encode($teacherCoverageChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Teacher subject and student coverage chart"
                  ></canvas>
                <?php endif; ?>
              </div>
            </article>

            <article class="data-panel manager-chart-card">
              <div class="panel-heading">
                <div><span>Current averages</span><h2>Grades distribution</h2></div>
              </div>
              <div class="manager-canvas-wrap manager-canvas-compact">
                <?php if ($gradeChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No grade averages are available yet.</p>
                <?php else: ?>
                  <canvas
                    data-grade-chart
                    data-chart="<?= e(json_encode($gradeChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Grade average distribution chart"
                  ></canvas>
                <?php endif; ?>
              </div>
            </article>

            <article class="data-panel manager-chart-card">
              <div class="panel-heading">
                <div><span>Subscriptions</span><h2>Payment status</h2></div>
              </div>
              <div class="manager-donut-layout manager-donut-stacked">
                <?php if ($paymentChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No subscription payment data is available yet.</p>
                <?php else: ?>
                  <canvas
                    data-payment-chart
                    data-chart="<?= e(json_encode($paymentChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Subscription payment status chart"
                  ></canvas>
                  <div class="manager-donut-legend">
                    <?php foreach ($paymentStatusRows as $index => $status): ?>
                      <span><i style="--legend-index: <?= e((string) $index) ?>"></i><?= e(ucwords(str_replace('_', ' ', (string) $status['label']))) ?><strong><?= e((string) $status['value']) ?></strong></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </article>

            <article class="data-panel manager-chart-card">
              <div class="panel-heading">
                <div><span>Behavior</span><h2>Warnings by year</h2></div>
              </div>
              <div class="manager-canvas-wrap manager-canvas-compact">
                <?php if ($warningChart['labels'] === []): ?>
                  <p class="manager-chart-empty">No warnings have been recorded yet.</p>
                <?php else: ?>
                  <canvas
                    data-warning-chart
                    data-chart="<?= e(json_encode($warningChart, JSON_UNESCAPED_SLASHES)) ?>"
                    aria-label="Warnings by year chart"
                  ></canvas>
                <?php endif; ?>
              </div>
            </article>
          </section>

          <section class="overview-grid manager-overview-bottom">
            <article class="data-panel overview-attendance">
              <div class="panel-heading">
                <div><span>Latest records</span><h2>Recent attendance</h2></div>
              </div>
              <div class="table-scroll">
                <table>
                  <thead><tr><th>Date</th><th>Student</th><th>Status</th><th>Attended</th><th>Missed</th></tr></thead>
                  <tbody>
                    <?php if ($recentAttendance === []): ?>
                      <tr><td class="empty-row" colspan="5">No attendance records yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentAttendance as $attendance): ?>
                        <tr>
                          <td><?= e(fmt_date((string) $attendance['attendance_date'])) ?></td>
                          <td><strong><?= e((string) $attendance['student_name_en']) ?></strong></td>
                          <td><?= manager_value('daily_status', $attendance['daily_status']) ?></td>
                          <td><?= e((string) $attendance['attended_subject_count']) ?></td>
                          <td><?= e((string) $attendance['missed_subject_count']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <aside class="quick-panel">
              <span>Editable records</span><h2>Double-click any row in these sections to open full information.</h2>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=students"><?= admin_icon('students') ?><span><strong>Students</strong><small>Profiles, photos, grades, and status</small></span></a>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=teachers"><?= admin_icon('teachers') ?><span><strong>Teachers</strong><small>Subjects and student coverage</small></span></a>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=website-contacts"><?= admin_icon('website-contacts') ?><span><strong>Contact & social</strong><small>Phone, email, links, and maps</small></span></a>
            </aside>
          </section>
        <?php else: ?>
          <section class="data-panel">
            <div class="panel-heading table-panel-heading">
              <div><span>Directory</span><h2><?= e($page['label']) ?></h2></div>
              <label class="table-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                <input type="search" placeholder="Search <?= e(strtolower($page['label'])) ?>" data-table-search>
              </label>
              <strong class="record-count"><?= e((string) count($rows)) ?> records</strong>
            </div>
            <div class="table-scroll">
              <table data-admin-table>
                <thead>
                  <tr>
                    <?php foreach (array_values($columns) as $sortIndex => $label): ?>
                      <th aria-sort="none">
                        <button class="sort-button" type="button" data-sort-column="<?= e((string) $sortIndex) ?>">
                          <?= e($label) ?><span aria-hidden="true"></span>
                        </button>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($rows === []): ?>
                    <tr><td class="empty-row" colspan="<?= e((string) count($columns)) ?>">No records found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                      <?php
                      $detailUrl = khotwa_url('admin/person.php')
                          . ($view === 'teachers' ? '?type=teacher&id=' : '?type=student&id=')
                          . (int) $row['id'];
                      ?>
                      <tr class="is-openable" data-record-row data-detail-url="<?= e($detailUrl) ?>" title="Double-click to open the full record" tabindex="0">
                        <?php foreach ($columns as $key => $label): ?>
                          <td data-sort-value="<?= e((string) ($row[$key] ?? '')) ?>"><?= manager_value($key, $row[$key] ?? null) ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <tr class="search-empty" hidden><td colspan="<?= e((string) count($columns)) ?>">No matching records.</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>
      </main>
    </div>
    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/manager.js')) ?>" defer></script>
</body>
</html>
