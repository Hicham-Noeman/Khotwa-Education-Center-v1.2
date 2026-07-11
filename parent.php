<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = require_roles(['parent']);
$parentUserId = (int) ($user['id'] ?? 0);
$selectedStudentId = (int) ($_GET['student_id'] ?? 0);

$children = [];
$studentOverview = null;
$subjects = [];
$attendance = [];
$billing = [];
$error = '';

try {
    $pdo = khotwa_db();

    $childrenStatement = $pdo->prepare(
        "SELECT
            students.id,
        students.first_name_en,
        students.father_name_en,
        students.last_name_en,
        students.first_name_ar,
        students.father_name_ar,
        students.last_name_ar,
            CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name,
            CONCAT(students.first_name_ar, ' ', students.last_name_ar) AS student_name_ar,
            students.current_teaching_language,
            students.status,
            COALESCE(current_record.grade_name, 'Not assigned') AS grade_name,
            parent_students.relationship,
            parent_students.status AS link_status,
            latest_attendance.attendance_date AS latest_attendance_date,
            latest_attendance.status AS latest_attendance_status,
            COALESCE(due_summary.open_balance, 0) AS open_balance
         FROM parent_students
         INNER JOIN students
           ON students.id = parent_students.student_id
         LEFT JOIN student_academic_records AS current_record
           ON current_record.student_id = students.id
          AND current_record.is_current = 1
         LEFT JOIN student_daily_attendance AS latest_attendance
           ON latest_attendance.id = (
                SELECT attendance.id
                FROM student_daily_attendance attendance
                WHERE attendance.student_id = students.id
                ORDER BY attendance.attendance_date DESC
                LIMIT 1
            )
         LEFT JOIN (
            SELECT student_id, SUM(GREATEST(expected_amount - paid_amount, 0)) AS open_balance
            FROM student_subscription_months
            GROUP BY student_id
         ) AS due_summary
           ON due_summary.student_id = students.id
         WHERE parent_students.parent_user_id = ?
           AND parent_students.status = 'active'
         ORDER BY students.last_name_en, students.first_name_en"
    );
    $childrenStatement->execute([$parentUserId]);
    $children = $childrenStatement->fetchAll();

    if ($children === []) {
        throw new RuntimeException('No students are linked to this parent account yet. Please contact the center administration.');
    }

    $allowedStudentIds = array_map(static fn (array $row): int => (int) $row['id'], $children);
    if ($selectedStudentId < 1 || !in_array($selectedStudentId, $allowedStudentIds, true)) {
        $selectedStudentId = (int) $children[0]['id'];
    }

    foreach ($children as $child) {
        if ((int) $child['id'] === $selectedStudentId) {
            $studentOverview = $child;
            break;
        }
    }

    $subjectsStatement = $pdo->prepare(
        "SELECT
            subjects.name_en AS subject_name,
            subjects.name_ar AS subject_name_ar,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_name,
            student_subject_enrollments.academic_year,
            student_subject_enrollments.status
         FROM student_subject_enrollments
         INNER JOIN subjects
           ON subjects.id = student_subject_enrollments.subject_id
         INNER JOIN teachers
           ON teachers.id = student_subject_enrollments.teacher_id
         WHERE student_subject_enrollments.student_id = ?
           AND student_subject_enrollments.status = 'active'
         ORDER BY subjects.name_en"
    );
    $subjectsStatement->execute([$selectedStudentId]);
    $subjects = $subjectsStatement->fetchAll();

    $attendanceStatement = $pdo->prepare(
        "SELECT attendance_date, status, check_in_time, check_out_time, notes
         FROM student_daily_attendance
         WHERE student_id = ?
         ORDER BY attendance_date DESC
         LIMIT 12"
    );
    $attendanceStatement->execute([$selectedStudentId]);
    $attendance = $attendanceStatement->fetchAll();

    $billingStatement = $pdo->prepare(
        "SELECT
            billing_year,
            billing_month,
            expected_amount,
            paid_amount,
            GREATEST(expected_amount - paid_amount, 0) AS balance_amount,
            payment_status,
            last_payment_date
         FROM student_subscription_months
         WHERE student_id = ?
         ORDER BY billing_year DESC, billing_month DESC
         LIMIT 8"
    );
    $billingStatement->execute([$selectedStudentId]);
    $billing = $billingStatement->fetchAll();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

function parent_status_class(string $value): string
{
    return 'status-' . trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-');
}

function parent_relationship_label(string $value): string
{
    return match ($value) {
        'father' => 'Father',
        'mother' => 'Mother',
        'guardian' => 'Guardian',
        'relative' => 'Relative',
        default => ucfirst($value),
    };
}

function parent_icon(string $name): string
{
  $paths = [
    'children' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'overview' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h8M8 17h5"/>',
    'subjects' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
    'attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-5 5 2 2 4-4"/>',
    'billing' => '<circle cx="12" cy="12" r="9"/><path d="M16 8h-5a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4H8m4-10v12"/>',
    'website' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
  ];

  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['overview']) . '</svg>';
}

$selectedStudentSubjects = count($subjects);
$recentPresentCount = count(array_filter(
  $attendance,
  static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['present', 'late', 'left_early'], true)
));
$recentAttendanceTotal = count($attendance);
$recentAttendanceRate = $recentAttendanceTotal > 0
  ? round(($recentPresentCount / $recentAttendanceTotal) * 100, 1)
  : 0.0;
$familyOpenBalance = (float) array_sum(array_map(
  static fn (array $row): float => (float) ($row['open_balance'] ?? 0),
  $children
));
$selectedChildName = (string) ($studentOverview['student_name'] ?? '-');
$selectedChildStatus = (string) ($studentOverview['status'] ?? 'inactive');
$selectedRelationship = (string) ($studentOverview['relationship'] ?? 'guardian');
$selectedChildFullNameEn = trim((string) (
  ($studentOverview['first_name_en'] ?? '') . ' '
  . ($studentOverview['father_name_en'] ?? '') . ' '
  . ($studentOverview['last_name_en'] ?? '')
));
$selectedChildFullNameAr = trim((string) (
  ($studentOverview['first_name_ar'] ?? '') . ' '
  . ($studentOverview['father_name_ar'] ?? '') . ' '
  . ($studentOverview['last_name_ar'] ?? '')
));
$selectedChildQrPayload = [
  'Full Name in EN' => $selectedChildFullNameEn,
  'Full Name in AR' => $selectedChildFullNameAr,
  'ID' => $selectedStudentId,
];
$selectedChildQrPayloadJson = json_encode($selectedChildQrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$selectedChildQrFileBase = 'student-' . $selectedStudentId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title>Parent Portal | Khotwa Education Center</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="admin.css?v=<?= e((string) filemtime(__DIR__ . '/admin.css')) ?>">
  <link rel="stylesheet" href="parent.css?v=<?= e((string) filemtime(__DIR__ . '/parent.css')) ?>">
</head>
<body class="admin-page parent-page">
  <div class="admin-shell" data-admin-shell>
    <aside class="admin-sidebar parent-sidebar" id="admin-sidebar" aria-label="Parent navigation">
      <div class="sidebar-top">
        <a class="admin-brand" href="parent.php" aria-label="Khotwa parent portal home">
          <span class="admin-brand-mark">K<span>.</span></span>
          <span class="admin-brand-copy"><strong>Khotwa</strong><small>Parent Portal</small></span>
        </a>
        <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
          <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 7-5 5 5 5"/></svg>
          <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m10 7 5 5-5 5"/></svg>
        </button>
      </div>

      <nav class="admin-nav">
        <section class="nav-group">
          <h2>My Children</h2>
          <?php foreach ($children as $child): ?>
            <?php
            $childId = (int) $child['id'];
            $isActiveChild = $childId === $selectedStudentId;
            $label = (string) $child['student_name'] . ' (' . parent_relationship_label((string) $child['relationship']) . ')';
            ?>
            <a class="<?= $isActiveChild ? 'is-active' : '' ?>" href="parent.php?student_id=<?= e((string) $childId) ?>" title="<?= e($label) ?>">
              <?= parent_icon('children') ?><span><?= e($label) ?></span>
              <?php if ($isActiveChild): ?><i></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </section>

        <section class="nav-group">
          <h2>Shortcuts</h2>
          <a href="index.php"><?= parent_icon('website') ?><span>Website</span></a>
          <a href="logout.php"><?= parent_icon('logout') ?><span>Logout</span></a>
        </section>
      </nav>

      <div class="sidebar-footer">
        <button class="sidebar-language" type="button" data-language-toggle>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
          <span><strong data-language-current>EN</strong><small data-language-label>AR</small></span>
        </button>
        <div class="sidebar-account">
          <span class="account-avatar"><?= e(strtoupper(substr((string) $user['first_name'], 0, 1))) ?></span>
          <span class="account-copy">
            <strong><?= e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?></strong>
            <small>Parent</small>
          </span>
          <a href="logout.php" aria-label="Log out" title="Log out"><?= parent_icon('logout') ?></a>
        </div>
      </div>
    </aside>

    <div class="admin-stage">
      <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>

      <header class="admin-header">
        <div>
          <span class="header-context">Parent access</span>
        </div>
        <div class="header-actions">
          <button class="language-switch" type="button" data-language-toggle>
            <span data-language-current>EN</span>
            <i></i>
            <span data-language-label>AR</span>
          </button>
          <a class="website-link" href="index.php">
            <?= parent_icon('website') ?>
            <span>Website</span>
          </a>
          <a class="logout-link" href="logout.php">Logout</a>
        </div>
      </header>

      <main class="admin-content">
        <?php if ($error !== ''): ?>
          <div class="database-alert"><?= e($error) ?></div>
        <?php else: ?>
          <section class="content-heading">
            <div>
              <span class="content-kicker">Parent Portal</span>
              <h1>Family dashboard</h1>
              <p>Track attendance, subject enrollments, and payments for your child with the same secure Khotwa workspace experience.</p>
            </div>
            <span class="live-indicator"><span></span>Student view is live</span>
          </section>

          <section class="metrics-grid">
            <article class="metric-card metric-orange">
              <span class="metric-dot"></span>
              <strong><?= e((string) count($children)) ?></strong>
              <p>Linked children</p>
            </article>
            <article class="metric-card metric-green">
              <span class="metric-dot"></span>
              <strong><?= e(number_format($familyOpenBalance, 2)) ?></strong>
              <p>Family open balance</p>
            </article>
            <article class="metric-card metric-pink">
              <span class="metric-dot"></span>
              <strong><?= e((string) $selectedStudentSubjects) ?></strong>
              <p>Active subjects for selected child</p>
            </article>
            <article class="metric-card metric-navy">
              <span class="metric-dot"></span>
              <strong><?= e(number_format($recentAttendanceRate, 1)) ?>%</strong>
              <p>Recent attendance rate</p>
            </article>
          </section>

          <section class="overview-grid parent-overview-grid">
            <article class="data-panel">
              <div class="panel-heading">
                <div><span>Student profile</span><h2><?= e($selectedChildName) ?></h2></div>
                <a href="parent.php?student_id=<?= e((string) $selectedStudentId) ?>">Refresh</a>
              </div>
              <?php if ($studentOverview !== null): ?>
                <div class="parent-kv-grid">
                  <div><strong>Arabic name</strong><span><?= e((string) $studentOverview['student_name_ar']) ?></span></div>
                  <div><strong>Full name (EN)</strong><span><?= e($selectedChildFullNameEn) ?></span></div>
                  <div><strong>Full name (AR)</strong><span><?= e($selectedChildFullNameAr) ?></span></div>
                  <div><strong>Grade</strong><span><?= e((string) $studentOverview['grade_name']) ?></span></div>
                  <div><strong>Language</strong><span><?= e((string) $studentOverview['current_teaching_language']) ?></span></div>
                  <div><strong>Relationship</strong><span><?= e(parent_relationship_label($selectedRelationship)) ?></span></div>
                  <div><strong>Status</strong><span class="status-pill <?= e(parent_status_class($selectedChildStatus)) ?>"><?= e(ucfirst($selectedChildStatus)) ?></span></div>
                  <div><strong>Latest attendance</strong><span><?= e((string) ($studentOverview['latest_attendance_date'] ?? 'No records yet')) ?></span></div>
                </div>
              <?php endif; ?>
            </article>

            <aside class="data-panel">
              <div class="panel-heading">
                <div><span>Student QR</span><h2>Student QR Code</h2></div>
              </div>
              <div class="student-qr-panel" data-student-qr data-qr-file-base="<?= e($selectedChildQrFileBase) ?>" data-qr-payload="<?= e((string) $selectedChildQrPayloadJson) ?>">
                <div class="student-qr-head">
                  <strong>Scan to read student identity JSON</strong>
                </div>
                <div class="student-qr-box" data-qr-canvas></div>
                <div class="qr-download-actions">
                  <button class="secondary-action" type="button" data-qr-download="png">Download PNG</button>
                  <button class="secondary-action" type="button" data-qr-download="jpg">Download JPG</button>
                </div>
              </div>
            </aside>
          </section>

          <section class="parent-table-grid">
            <article class="data-panel" id="subjects-table">
              <div class="panel-heading">
                <div><span>Academic</span><h2>Active subjects</h2></div>
              </div>
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr><th>Subject</th><th>Arabic</th><th>Teacher</th><th>Year</th></tr>
                  </thead>
                  <tbody>
                    <?php if ($subjects === []): ?>
                      <tr><td colspan="4" class="empty-row">No active subject enrollments.</td></tr>
                    <?php else: ?>
                      <?php foreach ($subjects as $row): ?>
                        <tr>
                          <td><?= e((string) $row['subject_name']) ?></td>
                          <td><?= e((string) $row['subject_name_ar']) ?></td>
                          <td><?= e((string) $row['teacher_name']) ?></td>
                          <td><?= e((string) $row['academic_year']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="data-panel" id="attendance-table">
              <div class="panel-heading">
                <div><span>Attendance</span><h2>Recent attendance</h2></div>
              </div>
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr><th>Date</th><th>Status</th><th>Check in</th><th>Check out</th></tr>
                  </thead>
                  <tbody>
                    <?php if ($attendance === []): ?>
                      <tr><td colspan="4" class="empty-row">No attendance records yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($attendance as $row): ?>
                        <tr>
                          <td><?= e((string) $row['attendance_date']) ?></td>
                          <td><span class="status-pill <?= e(parent_status_class((string) $row['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></span></td>
                          <td><?= e((string) ($row['check_in_time'] ?? '-')) ?></td>
                          <td><?= e((string) ($row['check_out_time'] ?? '-')) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="data-panel" id="billing-table">
              <div class="panel-heading">
                <div><span>Finance</span><h2>Recent billing</h2></div>
              </div>
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr><th>Month</th><th>Expected</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    <?php if ($billing === []): ?>
                      <tr><td colspan="5" class="empty-row">No billing records yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($billing as $row): ?>
                        <tr>
                          <td><?= e((string) $row['billing_year']) ?>-<?= e(str_pad((string) $row['billing_month'], 2, '0', STR_PAD_LEFT)) ?></td>
                          <td><?= e(number_format((float) $row['expected_amount'], 2)) ?></td>
                          <td><?= e(number_format((float) $row['paid_amount'], 2)) ?></td>
                          <td><?= e(number_format((float) $row['balance_amount'], 2)) ?></td>
                          <td><span class="status-pill <?= e(parent_status_class((string) $row['payment_status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $row['payment_status']))) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </section>
        <?php endif; ?>
      </main>
    </div>

    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js" defer></script>
  <script src="language.js?v=<?= e((string) filemtime(__DIR__ . '/language.js')) ?>" defer></script>
  <script src="qr-tools.js?v=<?= e((string) filemtime(__DIR__ . '/qr-tools.js')) ?>" defer></script>
  <script src="admin.js?v=<?= e((string) filemtime(__DIR__ . '/admin.js')) ?>" defer></script>
</body>
</html>
