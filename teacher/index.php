<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

$user = require_roles(['teacher']);
$teacherId = (int) ($user['teacher_id'] ?? 0);
$views = [
  'attendance' => ['label' => 'Subject Attendance', 'description' => 'Record subject attendance, lesson notes, and homework after administration marks daily attendance.'],
  'submission' => ['label' => "Today's Submission", 'description' => 'Review and save today\'s subject attendance entries for your assigned students.'],
    'students' => ['label' => 'Students', 'description' => 'Students actively assigned to your subjects.'],
    'warnings' => ['label' => 'Behaviour', 'description' => 'Raise a behaviour flag to the administration. Only the administration can see your flags.'],
    'profile' => ['label' => 'My Profile', 'description' => 'Your teacher information, account details, and assigned subjects.'],
];
/**
 * Whole years between a start date and today. Experience and time at the center
 * are stored as dates, so the number shown never needs updating by hand.
 */
function teacher_years_since(?string $startDate): ?int
{
    $startDate = trim((string) $startDate);
    if ($startDate === '') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($startDate);
    } catch (Throwable) {
        return null;
    }

    return max(0, (int) $start->diff(new DateTimeImmutable('today'))->y);
}

$view = (string) ($_GET['view'] ?? 'attendance');
if (!isset($views[$view])) {
    $view = 'attendance';
}

$today = date('Y-m-d');
$attendanceDate = $today;
$message = isset($_GET['saved'])
  ? max(0, (int) $_GET['saved']) . ' subject attendance record(s) saved.'
    : '';
if (isset($_GET['flagged'])) {
    $message = 'Behaviour flag sent to the administration.';
}
$error = '';
$studentRows = [];
$attendanceRows = [];
$teacherProfile = [];
$assignedSubjects = [];
$flagStudents = [];
$myFlags = [];

function teacher_icon(string $name): string
{
    $paths = [
        'students' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-5 5 2 2 4-4"/>',
        'submission' => '<path d="M9 11l2 2 4-4"/><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 17h8"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/><path d="M18 4h3v3"/>',
        'warnings' => '<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/><path d="M12 9v4M12 17h.01"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['students']) . '</svg>';
}

function render_teacher_sidebar(array $user, string $activeView): void
{
    ?>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Teacher navigation">
      <div class="sidebar-top">
        <a class="admin-brand" href="<?= e(khotwa_url('teacher/index.php')) ?>" aria-label="Khotwa teacher portal home">
          <span class="admin-brand-mark">K<span>.</span></span>
          <span class="admin-brand-copy"><strong>Khotwa</strong><small>Teacher Portal</small></span>
        </a>
        <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
          <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
          <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
      <nav class="admin-nav">
        <section class="nav-group">
          <h2>My Workspace</h2>
          <?php foreach (['attendance' => 'Attendance', 'submission' => "Today's Submission", 'students' => 'Students', 'warnings' => 'Behaviour', 'profile' => 'My Profile'] as $key => $label): ?>
            <?php $href = khotwa_url('teacher/index.php') . '?view=' . rawurlencode($key); ?>
            <a class="<?= $activeView === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>" title="<?= e($label) ?>">
              <?= teacher_icon($key) ?><span><?= e($label) ?></span>
              <?php if ($activeView === $key): ?><i></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
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
            <small>Teacher</small>
          </span>
          <a href="<?= e(khotwa_url('logout.php')) ?>" aria-label="Log out" title="Log out"><?= teacher_icon('logout') ?></a>
        </div>
      </div>
    </aside>
    <?php
}

try {
    if ($teacherId < 1) {
        throw new RuntimeException('This account is not linked to a teacher profile.');
    }

    $pdo = khotwa_db();
    $profileStatement = $pdo->prepare(
        "SELECT teachers.id, teachers.first_name, teachers.last_name, teachers.phone_number,
                teachers.email AS teacher_email, teachers.status, teachers.notes,
                teachers.created_at, users.email AS account_email, users.last_login_at,
                teachers.teaches_primary, teachers.teaches_intermediate, teachers.teaches_secondary,
                teachers.teaching_since, teachers.joined_center_on,
                teachers.certifications_en, teachers.certifications_ar,
                teachers.is_teacher_of_the_month, teachers.video_url
         FROM teachers
         INNER JOIN users ON users.teacher_id = teachers.id
         WHERE teachers.id = ? AND users.id = ? AND teachers.status = 'active'
         LIMIT 1"
    );
    $profileStatement->execute([$teacherId, (int) $user['id']]);
    $teacherProfile = $profileStatement->fetch();
    if (!$teacherProfile) {
        throw new RuntimeException('The linked teacher profile is unavailable.');
    }

    if (in_array($view, ['attendance', 'submission'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            verify_app_csrf();
            $submittedRows = (array) ($_POST['attendance'] ?? []);
            $allowedStatuses = ['attended', 'missed'];
            $savedCount = 0;

            $enrollmentStatement = $pdo->prepare(
                "SELECT student_id, teacher_subject_id, teacher_id, subject_id
                 FROM student_subject_enrollments
                 WHERE id = ? AND teacher_id = ? AND status = 'active'
                 LIMIT 1"
            );
            $dailyAttendanceStatement = $pdo->prepare(
              "SELECT id
               FROM student_daily_attendance
               WHERE student_id = ? AND attendance_date = ?
               LIMIT 1"
            );
            $subjectStatement = $pdo->prepare(
                "INSERT INTO student_subject_attendance (
                    daily_attendance_id, student_id, attendance_date, teacher_subject_id,
                    teacher_id, subject_id, status, homework_note, notes
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    homework_note = VALUES(homework_note),
                    notes = VALUES(notes)"
            );

            $pdo->beginTransaction();
            foreach ($submittedRows as $enrollmentId => $payload) {
                if (!is_array($payload)) {
                    continue;
                }

                $status = (string) ($payload['status'] ?? '');
                $lessonNote = trim((string) ($payload['note'] ?? ''));
                $homeworkNote = trim((string) ($payload['homework_note'] ?? ''));
                if ($status === '') {
                    if ($lessonNote !== '' || $homeworkNote !== '') {
                        throw new RuntimeException('Choose Came or Did not come before saving notes.');
                    }
                    continue;
                }
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new RuntimeException('An invalid attendance status was submitted.');
                }

                $enrollmentStatement->execute([(int) $enrollmentId, $teacherId]);
                $enrollment = $enrollmentStatement->fetch();
                if (!$enrollment) {
                    throw new RuntimeException('One submitted student is not assigned to this teacher.');
                }

                $dailyAttendanceStatement->execute([(int) $enrollment['student_id'], $attendanceDate]);
                $dailyAttendanceId = (int) $dailyAttendanceStatement->fetchColumn();
                if ($dailyAttendanceId < 1) {
                  throw new RuntimeException(
                    'Daily attendance is not marked yet for one or more students. Ask administration to record main attendance first.'
                  );
                }

                $subjectStatement->execute([
                    $dailyAttendanceId,
                    (int) $enrollment['student_id'],
                    $attendanceDate,
                    (int) $enrollment['teacher_subject_id'],
                    (int) $enrollment['teacher_id'],
                    (int) $enrollment['subject_id'],
                    $status,
                    $homeworkNote === '' ? null : substr($homeworkNote, 0, 255),
                    $lessonNote === '' ? null : substr($lessonNote, 0, 255),
                ]);
                $savedCount++;
            }
            $pdo->commit();

            header('Location: ' . khotwa_url('teacher/index.php') . '?view=' . rawurlencode($view) . '&saved=' . $savedCount);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }

    if ($view === 'warnings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            verify_app_csrf();
            $flagStudentId = (int) ($_POST['student_id'] ?? 0);
            $flagReason = trim((string) ($_POST['reason'] ?? ''));
            $flagNotes = trim((string) ($_POST['notes'] ?? ''));
            // The teacher records how long they talked it through; the admin never edits it.
            $flagMinutes = trim((string) ($_POST['conversation_minutes'] ?? ''));
            $flagMinutes = $flagMinutes === '' ? null : min(600, max(0, (int) $flagMinutes));

            if ($flagReason === '') {
                throw new RuntimeException('Describe the behaviour you want to flag.');
            }

            // The student must be actively assigned to one of this teacher's subjects.
            $assignedStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM student_subject_enrollments
                 WHERE student_id = ? AND teacher_id = ? AND status = 'active'"
            );
            $assignedStatement->execute([$flagStudentId, $teacherId]);
            if ((int) $assignedStatement->fetchColumn() < 1) {
                throw new RuntimeException('You can only flag a student assigned to your subjects.');
            }

            $insertFlag = $pdo->prepare(
                "INSERT INTO student_warnings (
                    student_id, teacher_id, warning_date, reason, notes, conversation_minutes, status
                 ) VALUES (?, ?, CURDATE(), ?, NULLIF(?, ''), ?, 'flagged')"
            );
            $insertFlag->execute([
                $flagStudentId,
                $teacherId,
                substr($flagReason, 0, 255),
                substr($flagNotes, 0, 60000),
                $flagMinutes,
            ]);

            header('Location: ' . khotwa_url('teacher/index.php') . '?view=warnings&flagged=1');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    if ($view === 'students') {
        $statement = $pdo->prepare(
            "SELECT student_subject_enrollments.id AS enrollment_id,
                    students.id AS student_id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name,
                    CONCAT(students.first_name_ar, ' ', students.last_name_ar) AS student_name_ar,
                    COALESCE(student_academic_records.grade_name, 'Not set') AS grade_name,
                    subjects.name_en AS subject_name,
                    subjects.name_ar AS subject_name_ar,
                    student_subject_enrollments.academic_year,
                    student_subject_enrollments.start_date,
                    latest_attendance.attendance_date AS latest_attendance_date,
                    latest_attendance.status AS latest_attendance_status
             FROM student_subject_enrollments
             INNER JOIN students ON students.id = student_subject_enrollments.student_id
             INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
             LEFT JOIN student_academic_records
                ON student_academic_records.student_id = students.id
                AND student_academic_records.is_current = 1
             LEFT JOIN student_subject_attendance latest_attendance
                ON latest_attendance.id = (
                    SELECT attendance.id
                    FROM student_subject_attendance attendance
                    WHERE attendance.student_id = students.id
                      AND attendance.teacher_subject_id = student_subject_enrollments.teacher_subject_id
                    ORDER BY attendance.attendance_date DESC, attendance.updated_at DESC
                    LIMIT 1
                )
             WHERE student_subject_enrollments.teacher_id = ?
               AND student_subject_enrollments.status = 'active'
               AND students.status = 'active'
             ORDER BY students.last_name_en, students.first_name_en, subjects.name_en"
        );
        $statement->execute([$teacherId]);
        $studentRows = $statement->fetchAll();
    } elseif ($view === 'warnings') {
        $flagStudentsStatement = $pdo->prepare(
            "SELECT DISTINCT students.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name
             FROM student_subject_enrollments
             INNER JOIN students ON students.id = student_subject_enrollments.student_id
             WHERE student_subject_enrollments.teacher_id = ?
               AND student_subject_enrollments.status = 'active'
               AND students.status = 'active'
             ORDER BY students.last_name_en, students.first_name_en"
        );
        $flagStudentsStatement->execute([$teacherId]);
        $flagStudents = $flagStudentsStatement->fetchAll();

        $myFlagsStatement = $pdo->prepare(
            "SELECT student_warnings.id, student_warnings.warning_date, student_warnings.reason,
                    student_warnings.status, student_warnings.warning_type,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name
             FROM student_warnings
             INNER JOIN students ON students.id = student_warnings.student_id
             WHERE student_warnings.teacher_id = ?
             ORDER BY student_warnings.warning_date DESC, student_warnings.id DESC
             LIMIT 50"
        );
        $myFlagsStatement->execute([$teacherId]);
        $myFlags = $myFlagsStatement->fetchAll();
    } elseif (in_array($view, ['attendance', 'submission'], true)) {
        $statement = $pdo->prepare(
            "SELECT student_subject_enrollments.id AS enrollment_id,
                    students.id AS student_id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name,
                    CONCAT(students.first_name_ar, ' ', students.last_name_ar) AS student_name_ar,
                    COALESCE(student_academic_records.grade_name, 'Not set') AS grade_name,
                    subjects.name_en AS subject_name,
                    student_subject_attendance.status AS attendance_status,
                    student_subject_attendance.homework_note,
                    student_subject_attendance.notes AS attendance_note,
                    student_subject_attendance.updated_at AS attendance_updated_at
             FROM student_subject_enrollments
             INNER JOIN students ON students.id = student_subject_enrollments.student_id
             INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
             LEFT JOIN student_academic_records
                ON student_academic_records.student_id = students.id
                AND student_academic_records.is_current = 1
             INNER JOIN student_daily_attendance
                ON student_daily_attendance.student_id = students.id
                AND student_daily_attendance.attendance_date = ?
             LEFT JOIN student_subject_attendance
                ON student_subject_attendance.daily_attendance_id = student_daily_attendance.id
                AND student_subject_attendance.teacher_subject_id = student_subject_enrollments.teacher_subject_id
                AND student_subject_attendance.session_number = 1
             WHERE student_subject_enrollments.teacher_id = ?
               AND student_subject_enrollments.status = 'active'
               AND students.status = 'active'
             ORDER BY grade_name, students.last_name_en, students.first_name_en, subjects.name_en"
        );
        $statement->execute([$attendanceDate, $teacherId]);
        $attendanceRows = $statement->fetchAll();
    } else {
        $subjectStatement = $pdo->prepare(
            "SELECT subjects.name_en, subjects.name_ar, teacher_subjects.status, teacher_subjects.notes
             FROM teacher_subjects
             INNER JOIN subjects ON subjects.id = teacher_subjects.subject_id
             WHERE teacher_subjects.teacher_id = ?
             ORDER BY subjects.name_en"
        );
        $subjectStatement->execute([$teacherId]);
        $assignedSubjects = $subjectStatement->fetchAll();
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$teacherName = trim(
    (string) ($teacherProfile['first_name'] ?? $user['first_name'])
    . ' '
    . (string) ($teacherProfile['last_name'] ?? $user['last_name'])
);
$uniqueStudents = count(array_unique(array_column($attendanceRows, 'student_id')));
$attendedCount = count(array_filter(
    $attendanceRows,
    static fn (array $row): bool => $row['attendance_status'] === 'attended'
));
$missedCount = count(array_filter(
    $attendanceRows,
    static fn (array $row): bool => $row['attendance_status'] === 'missed'
));
$unmarkedCount = count($attendanceRows) - $attendedCount - $missedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title><?= e($views[$view]['label']) ?> | Khotwa Teacher Portal</title>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/teacher.css')) ?>">
</head>
<body
  class="admin-page teacher-admin-page teacher-view-<?= e($view) ?>"
  data-teacher-id="<?= e((string) $teacherId) ?>"
  data-attendance-date="<?= e($attendanceDate) ?>"
  data-clear-attendance-draft="<?= isset($_GET['saved']) ? 'true' : 'false' ?>"
>
  <div class="admin-shell" data-admin-shell>
    <?php render_teacher_sidebar($user, $view); ?>
    <div class="admin-stage">
      <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <main class="admin-content">
        <section class="content-heading">
          <div>
            <span class="content-kicker">Teacher workspace</span>
            <h1><?= e($views[$view]['label']) ?></h1>
            <p><?= e($views[$view]['description']) ?></p>
          </div>
          <div class="live-indicator"><span></span><?= e($teacherName) ?></div>
        </section>


        <?php if ($view === 'students'): ?>
          <section class="metrics-grid teacher-summary-grid" aria-label="Student summary">
            <article class="metric-card metric-orange"><span class="metric-dot"></span><strong><?= e((string) count(array_unique(array_column($studentRows, 'student_id')))) ?></strong><p>Assigned students</p></article>
            <article class="metric-card metric-green"><span class="metric-dot"></span><strong><?= e((string) count($studentRows)) ?></strong><p>Active assignments</p></article>
            <article class="metric-card metric-pink"><span class="metric-dot"></span><strong><?= e((string) count(array_unique(array_column($studentRows, 'subject_name')))) ?></strong><p>Subjects taught</p></article>
          </section>

          <section class="data-panel">
            <div class="panel-heading table-panel-heading">
              <div><span>My roster</span><h2>Assigned Students</h2></div>
              <label class="table-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                <input type="search" placeholder="Search students" data-table-search>
              </label>
              <div class="table-heading-actions">
                <strong class="record-count"><?= e((string) count($studentRows)) ?> assignments</strong>
                <a class="add-record-button" href="<?= e(khotwa_url('teacher/index.php')) ?>?view=attendance">
                  <?= teacher_icon('attendance') ?> Record subject attendance
                </a>
              </div>
            </div>
            <div class="table-scroll">
              <table data-admin-table>
                <thead>
                  <tr>
                    <?php foreach (['Student', 'Arabic name', 'Grade', 'Subject', 'Academic year', 'Latest attendance'] as $index => $label): ?>
                      <th aria-sort="none"><button class="sort-button" type="button" data-sort-column="<?= $index ?>"><?= e($label) ?><span aria-hidden="true"></span></button></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($studentRows === []): ?>
                    <tr><td class="empty-row" colspan="6">No active students are assigned to this teacher.</td></tr>
                  <?php else: ?>
                    <?php foreach ($studentRows as $row): ?>
                      <?php
                      $attendanceLabel = $row['latest_attendance_status'] === 'attended'
                          ? 'Came'
                          : ($row['latest_attendance_status'] === 'missed' ? 'Did not come' : 'Not recorded');
                      ?>
                      <tr data-record-row>
                        <td data-sort-value="<?= e((string) $row['student_name']) ?>"><strong><?= e((string) $row['student_name']) ?></strong></td>
                        <td data-sort-value="<?= e((string) $row['student_name_ar']) ?>" class="teacher-arabic-name"><?= e((string) $row['student_name_ar']) ?></td>
                        <td data-sort-value="<?= e((string) $row['grade_name']) ?>"><?= e((string) $row['grade_name']) ?></td>
                        <td data-sort-value="<?= e((string) $row['subject_name']) ?>"><?= e((string) $row['subject_name']) ?></td>
                        <td data-sort-value="<?= e((string) $row['academic_year']) ?>"><?= e((string) $row['academic_year']) ?></td>
                        <td data-sort-value="<?= e((string) ($row['latest_attendance_date'] ?? '')) ?>">
                          <span class="status-pill status-<?= e((string) ($row['latest_attendance_status'] ?? 'unmarked')) ?>"><?= e($attendanceLabel) ?></span>
                          <?php if ($row['latest_attendance_date']): ?><small class="table-cell-detail"><?= e(fmt_date((string) $row['latest_attendance_date'])) ?></small><?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <tr class="search-empty" hidden><td colspan="6">No matching students.</td></tr>
                </tbody>
              </table>
            </div>
          </section>

        <?php elseif ($view === 'attendance'): ?>
          <?php if ($attendanceRows === []): ?>
            <section class="data-panel">
              <p class="linked-empty">No active students are assigned to this teacher.</p>
            </section>
          <?php else: ?>
            <section class="attendance-session-bar">
              <div class="attendance-today">
                <span>Today's subject attendance</span>
                <time datetime="<?= e($attendanceDate) ?>"><?= e(date('l, d/m/Y', strtotime($attendanceDate))) ?></time>
              </div>
            </section>
            <p class="table-cell-detail">Daily attendance is managed by administration. Teachers submit subject attendance, lesson notes, and homework only.</p>


            <form method="post" class="attendance-workspace" data-attendance-form>
              <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
              <input type="hidden" name="attendance_date" value="<?= e($attendanceDate) ?>">

              <div class="attendance-mode-tabs" role="tablist" aria-label="Attendance workflow">
                <button class="attendance-mode-tab is-active" type="button" role="tab" aria-selected="true" data-attendance-mode="mark">Quick mark</button>
                <button class="attendance-mode-tab" type="button" role="tab" aria-selected="false" data-attendance-mode="notes">Daily notes</button>
              </div>

              <section class="attendance-panel is-active" data-attendance-panel="mark" role="tabpanel">
                <div class="attendance-mark-toolbar">
                  <label class="table-search attendance-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                    <input type="search" placeholder="Find a student" data-roster-search>
                  </label>
                  <div class="attendance-bulk-actions">
                    <button class="secondary-action" type="button" data-mark-all="attended">All came</button>
                    <button class="secondary-action" type="button" data-mark-all="missed">All absent</button>
                  </div>
                </div>
                <div class="attendance-filters">
                  <span class="letter-filter-label">Filter by first letter</span>
                  <div class="letter-filter-container" data-letter-filter-container>
                    <!-- Letter buttons will be dynamically generated by JS -->
                  </div>
                </div>

                <div class="attendance-swipe-zone" data-swipe-zone>
                  <div class="swipe-stack" data-swipe-stack aria-live="polite"></div>
                  <p class="swipe-skip-hint">Swipe up for next, down for previous</p>
                  <div class="swipe-zone-empty" data-swipe-empty hidden>
                    <strong data-swipe-empty-title>All students marked</strong>
                    <p data-swipe-empty-message>Add any attendance notes, then save.</p>
                    <button class="secondary-action" type="button" data-go-notes>Add notes</button>
                  </div>
                </div>

                <div class="swipe-actions" data-swipe-actions>
                  <button class="swipe-btn swipe-btn-missed" type="button" data-swipe-action="missed" aria-label="Mark absent">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    <span>Absent</span>
                  </button>
                  <button class="swipe-btn swipe-btn-came" type="button" data-swipe-action="attended" aria-label="Mark came">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
                    <span>Came</span>
                  </button>
                </div>

                <div class="attendance-progress" aria-label="Attendance progress">
                  <div class="attendance-progress-track"><span data-progress-fill style="width: <?= count($attendanceRows) > 0 ? round((($attendedCount + $missedCount) / count($attendanceRows)) * 100) : 0 ?>%"></span></div>
                  <div class="attendance-progress-stats">
                    <span><strong data-attended-count><?= e((string) $attendedCount) ?></strong> came</span>
                    <span><strong data-missed-count><?= e((string) $missedCount) ?></strong> absent</span>
                    <span><strong data-unmarked-count><?= e((string) $unmarkedCount) ?></strong> left</span>
                  </div>
                </div>


                <div class="attendance-quick-list" data-quick-list>
                  <?php foreach ($attendanceRows as $row): ?>
                    <?php
                    $rowStatus = (string) ($row['attendance_status'] ?? '');
                    $enrollmentId = (string) $row['enrollment_id'];
                    $searchText = strtolower(
                        (string) $row['student_name'] . ' '
                        . (string) $row['student_name_ar'] . ' '
                        . (string) $row['grade_name'] . ' '
                        . (string) $row['subject_name']
                    );
                    ?>
                    <article
                      class="attendance-quick-row"
                      data-roster-row
                      data-enrollment-id="<?= e($enrollmentId) ?>"
                      data-status="<?= e($rowStatus) ?>"
                      data-search-text="<?= e($searchText) ?>"
                      tabindex="0"
                    >
                      <span class="quick-row-avatar"><?= e(strtoupper(substr((string) $row['student_name'], 0, 1))) ?></span>
                      <div class="quick-row-copy">
                        <strong><?= e((string) $row['student_name']) ?></strong>
                        <small class="quick-row-arabic"><?= e((string) $row['student_name_ar']) ?></small>
                        <span class="quick-row-meta"><?= e((string) $row['grade_name']) ?> &middot; <?= e((string) $row['subject_name']) ?></span>
                      </div>
                      <div class="quick-row-actions">
                        <button class="quick-status-btn quick-status-came" type="button" data-set-status="attended" aria-pressed="<?= $rowStatus === 'attended' ? 'true' : 'false' ?>">Came</button>
                        <button class="quick-status-btn quick-status-missed" type="button" data-set-status="missed" aria-pressed="<?= $rowStatus === 'missed' ? 'true' : 'false' ?>">Absent</button>
                      </div>
                      <input type="radio" name="attendance[<?= e($enrollmentId) ?>][status]" value="attended" <?= $rowStatus === 'attended' ? 'checked' : '' ?> hidden>
                      <input type="radio" name="attendance[<?= e($enrollmentId) ?>][status]" value="missed" <?= $rowStatus === 'missed' ? 'checked' : '' ?> hidden>
                      <textarea name="attendance[<?= e($enrollmentId) ?>][note]" maxlength="255" hidden data-note-input><?= e((string) ($row['attendance_note'] ?? '')) ?></textarea>
                      <textarea name="attendance[<?= e($enrollmentId) ?>][homework_note]" maxlength="255" hidden data-homework-input><?= e((string) ($row['homework_note'] ?? '')) ?></textarea>
                    </article>
                  <?php endforeach; ?>
                </div>
                <p class="teacher-search-empty" hidden data-search-empty>No assigned students match this search.</p>
                <p class="attendance-desktop-hint">Tip: focus a row and press <kbd>C</kbd> for came or <kbd>A</kbd> for absent.</p>
              </section>

              <section class="attendance-panel" data-attendance-panel="notes" role="tabpanel" hidden>
                <div class="attendance-notes-heading">
                  <button class="secondary-action" type="button" data-go-mark>Back to attendance</button>
                  <p class="attendance-notes-intro">Add attendance and homework notes for students you already marked. Attendance status is shown read-only here.</p>
                </div>
                <div class="attendance-notes-list" data-notes-list>
                  <?php foreach ($attendanceRows as $row): ?>
                    <?php
                    $rowStatus = (string) ($row['attendance_status'] ?? '');
                    $enrollmentId = (string) $row['enrollment_id'];
                    ?>
                    <article class="attendance-notes-row" data-notes-row data-enrollment-id="<?= e($enrollmentId) ?>" data-status="<?= e($rowStatus) ?>" <?= $rowStatus === '' ? 'hidden' : '' ?>>
                      <div class="notes-row-head">
                        <div>
                          <strong><?= e((string) $row['student_name']) ?></strong>
                          <small><?= e((string) $row['subject_name']) ?> &middot; <?= e((string) $row['grade_name']) ?></small>
                        </div>
                        <span class="attendance-state" data-notes-status-label><?= $rowStatus === 'attended' ? 'Came' : ($rowStatus === 'missed' ? 'Did not come' : 'Not marked') ?></span>
                      </div>
                      <div class="notes-row-fields">
                        <label><span>Attendance note</span><textarea maxlength="255" data-note-field="<?= e($enrollmentId) ?>" placeholder="Participation, progress, or follow-up"><?= e((string) ($row['attendance_note'] ?? '')) ?></textarea></label>
                        <label><span>Homework note</span><textarea maxlength="255" data-homework-field="<?= e($enrollmentId) ?>" placeholder="Homework assigned or completion"><?= e((string) ($row['homework_note'] ?? '')) ?></textarea></label>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
                <p class="attendance-notes-empty" data-notes-empty <?= ($attendedCount + $missedCount) > 0 ? 'hidden' : '' ?>>Mark attendance in Quick mark first, then come back here to add notes.</p>
              </section>

              <div class="submission-final-bar">
                <span>Save now to publish teacher notes/homework to parent and admin dashboards.</span>
                <button class="primary-action" type="submit" data-final-save>Save subject attendance</button>
              </div>

            </form>
          <?php endif; ?>

        <?php elseif ($view === 'submission'): ?>
          <section class="attendance-session-bar">
            <div class="attendance-today">
              <span>Review date</span>
              <time datetime="<?= e($attendanceDate) ?>"><?= e(date('l, d/m/Y', strtotime($attendanceDate))) ?></time>
            </div>
          </section>
          <p class="table-cell-detail">Main daily attendance is not changed in this screen. This submission saves subject attendance entries only.</p>

          <section class="metrics-grid submission-summary" aria-label="Submission summary">
            <article class="metric-card metric-green"><span class="metric-dot"></span><strong data-review-came-count><?= e((string) $attendedCount) ?></strong><p>Came</p></article>
            <article class="metric-card metric-pink"><span class="metric-dot"></span><strong data-review-absent-count><?= e((string) $missedCount) ?></strong><p>Absent</p></article>
            <article class="metric-card metric-orange"><span class="metric-dot"></span><strong data-review-unmarked-count><?= e((string) $unmarkedCount) ?></strong><p>Not marked</p></article>
          </section>

          <form method="post" class="submission-workspace" data-submission-form>
            <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
            <input type="hidden" name="attendance_date" value="<?= e($attendanceDate) ?>">

            <section class="data-panel">
              <div class="panel-heading submission-heading">
                <div><span>Final validation</span><h2>Today's Subject Attendance Submission</h2></div>
                <a class="secondary-action" href="<?= e(khotwa_url('teacher/index.php')) ?>?view=attendance">Back to attendance</a>
              </div>

              <?php if ($attendanceRows === []): ?>
                <p class="linked-empty">No active students are assigned to this teacher.</p>
              <?php else: ?>
                <div class="submission-list" data-submission-list>
                  <?php foreach ($attendanceRows as $row): ?>
                    <?php
                    $rowStatus = (string) ($row['attendance_status'] ?? '');
                    $enrollmentId = (string) $row['enrollment_id'];
                    ?>
                    <article class="submission-row" data-review-row data-enrollment-id="<?= e($enrollmentId) ?>" data-status="<?= e($rowStatus) ?>">
                      <div class="submission-row-head">
                        <span class="quick-row-avatar"><?= e(strtoupper(substr((string) $row['student_name'], 0, 1))) ?></span>
                        <div>
                          <strong><?= e((string) $row['student_name']) ?></strong>
                          <small><?= e((string) $row['grade_name']) ?> &middot; <?= e((string) $row['subject_name']) ?></small>
                        </div>
                        <span class="attendance-state" data-review-status-label><?= $rowStatus === 'attended' ? 'Came' : ($rowStatus === 'missed' ? 'Absent' : 'Not marked') ?></span>
                      </div>
                      <div class="submission-row-body">
                        <div class="submission-status-actions">
                          <button class="quick-status-btn quick-status-came" type="button" data-review-status="attended" aria-pressed="<?= $rowStatus === 'attended' ? 'true' : 'false' ?>">Came</button>
                          <button class="quick-status-btn quick-status-missed" type="button" data-review-status="missed" aria-pressed="<?= $rowStatus === 'missed' ? 'true' : 'false' ?>">Absent</button>
                        </div>
                        <div class="notes-row-fields">
                          <label><span>Attendance note</span><textarea name="attendance[<?= e($enrollmentId) ?>][note]" maxlength="255" data-review-note placeholder="Participation, progress, or follow-up"><?= e((string) ($row['attendance_note'] ?? '')) ?></textarea></label>
                          <label><span>Homework note</span><textarea name="attendance[<?= e($enrollmentId) ?>][homework_note]" maxlength="255" data-review-homework placeholder="Homework assigned or completion"><?= e((string) ($row['homework_note'] ?? '')) ?></textarea></label>
                        </div>
                      </div>
                      <input type="radio" name="attendance[<?= e($enrollmentId) ?>][status]" value="attended" <?= $rowStatus === 'attended' ? 'checked' : '' ?> hidden>
                      <input type="radio" name="attendance[<?= e($enrollmentId) ?>][status]" value="missed" <?= $rowStatus === 'missed' ? 'checked' : '' ?> hidden>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <?php if ($attendanceRows !== []): ?>
              <div class="submission-final-bar">
                <span>Draft changes are saved automatically. Review them before committing to the database.</span>
                <button class="primary-action" type="submit" data-final-save>Final save all</button>
              </div>
            <?php endif; ?>
          </form>

        <?php elseif ($view === 'warnings'): ?>
          <section class="behaviour-grid">
            <article class="data-panel">
              <div class="panel-heading"><div><span>New flag</span><h2>Raise a behaviour flag</h2></div></div>
              <?php if ($flagStudents === []): ?>
                <p class="linked-empty">No active students are assigned to your subjects yet.</p>
              <?php else: ?>
                <form method="post" class="behaviour-flag-form">
                  <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
                  <label>
                    <span>Student</span>
                    <select name="student_id" required>
                      <option value="">Select a student…</option>
                      <?php foreach ($flagStudents as $flagStudent): ?>
                        <option value="<?= e((string) $flagStudent['id']) ?>"><?= e((string) $flagStudent['student_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    <span>What happened?</span>
                    <input type="text" name="reason" maxlength="255" placeholder="Short reason for the flag" required>
                  </label>
                  <label>
                    <span>Time spent talking with the student, in minutes (optional)</span>
                    <input type="number" name="conversation_minutes" min="0" max="600" placeholder="e.g. 15">
                  </label>
                  <label>
                    <span>Notes for administration (optional)</span>
                    <textarea name="notes" rows="3" placeholder="Any extra context only the administration will see"></textarea>
                  </label>
                  <div class="record-form-actions">
                    <button class="primary-action" type="submit">Send flag to administration</button>
                  </div>
                </form>
              <?php endif; ?>
            </article>

            <article class="data-panel">
              <div class="panel-heading"><div><span>My flags</span><h2>Flags I raised</h2></div><strong class="record-count"><?= e((string) count($myFlags)) ?> total</strong></div>
              <?php if ($myFlags === []): ?>
                <p class="linked-empty">You have not raised any behaviour flags yet.</p>
              <?php else: ?>
                <div class="behaviour-flag-list">
                  <?php
                  // A completed or rejected flag is deleted, so only the three live
                  // stages can ever show here.
                  $flagStatusLabels = [
                      'flagged' => 'Pending review',
                      'issued' => 'Warning issued',
                      'assigned' => 'Expiation chosen',
                  ];
                  ?>
                  <?php foreach ($myFlags as $flag): ?>
                    <div class="behaviour-flag-item">
                      <div>
                        <strong><?= e((string) $flag['student_name']) ?></strong>
                        <small><?= e(fmt_date((string) $flag['warning_date'])) ?></small>
                      </div>
                      <p><?= e((string) $flag['reason']) ?></p>
                      <span class="status-pill status-<?= e((string) $flag['status']) ?>"><?= e($flagStatusLabels[(string) $flag['status']] ?? ucfirst((string) $flag['status'])) ?><?= $flag['warning_type'] ? ' · ' . e(ucfirst((string) $flag['warning_type'])) : '' ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
          </section>

        <?php elseif ($teacherProfile !== []): ?>
          <section class="profile-overview-grid">
            <article class="data-panel teacher-profile-card">
              <div class="panel-heading"><div><span>Teacher profile</span><h2><?= e($teacherName) ?></h2></div><span class="profile-id">ID <?= e((string) $teacherId) ?></span></div>
              <div class="teacher-profile-fields">
                <div><span>First name</span><strong><?= e((string) $teacherProfile['first_name']) ?></strong></div>
                <div><span>Last name</span><strong><?= e((string) $teacherProfile['last_name']) ?></strong></div>
                <div><span>Phone number</span><strong><?= e((string) ($teacherProfile['phone_number'] ?: 'Not provided')) ?></strong></div>
                <div><span>Teacher email</span><strong><?= e((string) $teacherProfile['teacher_email']) ?></strong></div>
                <div><span>Portal account</span><strong><?= e((string) $teacherProfile['account_email']) ?></strong></div>
                <div><span>Status</span><strong><span class="status-pill status-active">Active</span></strong></div>
                <div><span>Last login</span><strong><?= e(fmt_datetime((string) $teacherProfile['last_login_at'], 'First login')) ?></strong></div>
                <div><span>Member since</span><strong><?= e(fmt_datetime((string) $teacherProfile['created_at'])) ?></strong></div>
                <?php
                $teachingSince = (string) ($teacherProfile['teaching_since'] ?? '');
                $teachingYears = teacher_years_since($teachingSince);
                $atCenterSince = (string) ($teacherProfile['joined_center_on'] ?? '');
                $atCenterYears = teacher_years_since($atCenterSince);
                $stages = array_keys(array_filter([
                    'Primary (Ibtidai)' => (int) ($teacherProfile['teaches_primary'] ?? 0) === 1,
                    'Intermediate (Mutawassit)' => (int) ($teacherProfile['teaches_intermediate'] ?? 0) === 1,
                    'Secondary (Sanawi)' => (int) ($teacherProfile['teaches_secondary'] ?? 0) === 1,
                ]));
                ?>
                <div>
                  <span>Educational levels</span>
                  <strong>
                    <?php if ($stages === []): ?>
                      Not set
                    <?php else: ?>
                      <span class="teacher-stage-list">
                        <?php foreach ($stages as $stage): ?><i><?= e($stage) ?></i><?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                  </strong>
                </div>
                <div>
                  <span>Years of experience</span>
                  <strong>
                    <?php if ($teachingYears === null): ?>
                      Not set
                    <?php else: ?>
                      <span data-i18n-skip><?= e((string) $teachingYears) ?></span>
                      <span><?= $teachingYears === 1 ? 'year' : 'years' ?></span>
                      <small class="teacher-profile-since">
                        <span>since</span> <span data-i18n-skip><?= e(fmt_date($teachingSince)) ?></span>
                      </small>
                    <?php endif; ?>
                  </strong>
                </div>
                <div>
                  <span>At the center since</span>
                  <strong>
                    <?php if ($atCenterSince === ''): ?>
                      Not set
                    <?php else: ?>
                      <span data-i18n-skip><?= e(fmt_date($atCenterSince)) ?></span>
                      <?php if ($atCenterYears !== null): ?>
                        <small class="teacher-profile-since">
                          <span data-i18n-skip><?= e((string) $atCenterYears) ?></span>
                          <span><?= $atCenterYears === 1 ? 'year' : 'years' ?></span>
                        </small>
                      <?php endif; ?>
                    <?php endif; ?>
                  </strong>
                </div>
                <div>
                  <span>Teacher of the month</span>
                  <strong>
                    <?php if ((int) ($teacherProfile['is_teacher_of_the_month'] ?? 0) === 1): ?>
                      <span class="status-pill status-active">Yes</span>
                    <?php else: ?>
                      No
                    <?php endif; ?>
                  </strong>
                </div>
                <?php $videoUrl = trim((string) ($teacherProfile['video_url'] ?? '')); ?>
                <div>
                  <span>Video</span>
                  <strong>
                    <?php if ($videoUrl === ''): ?>
                      Not set
                    <?php else: ?>
                      <a href="<?= e($videoUrl) ?>" target="_blank" rel="noopener noreferrer">Watch on YouTube</a>
                    <?php endif; ?>
                  </strong>
                </div>
              </div>
              <?php
              $certifications = trim((string) ($teacherProfile['certifications_en'] ?? ''));
              $certificationsAr = trim((string) ($teacherProfile['certifications_ar'] ?? ''));
              ?>
              <?php if ($certifications !== '' || $certificationsAr !== ''): ?>
                <div class="teacher-profile-note">
                  <span>Certifications</span>
                  <?php if ($certifications !== ''): ?><p><?= e($certifications) ?></p><?php endif; ?>
                  <?php if ($certificationsAr !== ''): ?><p lang="ar" dir="rtl" data-i18n-skip><?= e($certificationsAr) ?></p><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($teacherProfile['notes']): ?><div class="teacher-profile-note"><span>Profile note</span><p><?= e((string) $teacherProfile['notes']) ?></p></div><?php endif; ?>
            </article>

            <article class="data-panel">
              <div class="panel-heading"><div><span>Teaching assignment</span><h2>My Subjects</h2></div><strong class="record-count"><?= e((string) count($assignedSubjects)) ?> subjects</strong></div>
              <?php if ($assignedSubjects === []): ?>
                <p class="linked-empty">No subjects are assigned to this teacher.</p>
              <?php else: ?>
                <div class="teacher-subject-list">
                  <?php foreach ($assignedSubjects as $subject): ?>
                    <div><span><?= teacher_icon('attendance') ?></span><div><strong><?= e((string) $subject['name_en']) ?></strong><small><?= e((string) $subject['name_ar']) ?></small></div><i class="status-pill status-<?= e((string) $subject['status']) ?>"><?= e(ucfirst((string) $subject['status'])) ?></i></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
          </section>
        <?php else: ?>
          <section class="data-panel">
            <p class="linked-empty">Your teacher profile could not be loaded. Please contact an administrator.</p>
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
  <script src="<?= e(khotwa_asset('js/teacher.js')) ?>" defer></script>
</body>
</html>
