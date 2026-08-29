<?php
/*
 * Marks today's daily attendance so a teacher has a roster to submit against.
 *
 * The teacher attendance screen inner-joins student_daily_attendance on today's
 * date: administration checks students in, and only then can a teacher record
 * subject attendance for them. After a fresh setup - or on any day nobody has
 * been checked in yet - that screen reads "No active students are assigned to
 * this teacher" even though the enrollments are all in place.
 *
 * This only touches student_daily_attendance for today. It never writes subject
 * attendance, so a teacher's roster stays unmarked and ready to be submitted.
 *
 *   php tools/seed-today-attendance.php            all active students
 *   php tools/seed-today-attendance.php 1           only teacher 1's students
 */
declare(strict_types=1);
define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/database.php';

$pdo = getDatabaseConnection();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$teacherId = isset($argv[1]) ? (int) $argv[1] : 0;

if ($teacherId > 0) {
    $students = $pdo->prepare(
        "SELECT DISTINCT students.id
         FROM students
         INNER JOIN student_subject_enrollments
                 ON student_subject_enrollments.student_id = students.id
                AND student_subject_enrollments.status = 'active'
         WHERE students.status = 'active'
           AND student_subject_enrollments.teacher_id = ?
         ORDER BY students.id"
    );
    $students->execute([$teacherId]);
    $students = $students->fetchAll(PDO::FETCH_COLUMN);
} else {
    $students = $pdo->query(
        "SELECT id FROM students WHERE status = 'active' ORDER BY id"
    )->fetchAll(PDO::FETCH_COLUMN);
}

if ($students === []) {
    echo "No active students matched. Nothing to do.\n";
    exit(1);
}

$insert = $pdo->prepare(
    "INSERT INTO student_daily_attendance
        (student_id, attendance_date, check_in_time, check_out_time, status, notes)
     VALUES (?, ?, ?, NULL, ?, NULL)
     ON DUPLICATE KEY UPDATE status = VALUES(status), check_in_time = VALUES(check_in_time)"
);

// A realistic spread rather than a wall of "present": every eighth student is late
// and every seventeenth is excused, so the screen shows more than one state.
$counts = ['present' => 0, 'late' => 0, 'excused' => 0];

$pdo->beginTransaction();
foreach (array_values($students) as $index => $studentId) {
    if ($index % 17 === 16) {
        $status = 'excused';
        $checkIn = null;
    } elseif ($index % 8 === 7) {
        $status = 'late';
        $checkIn = '08:' . str_pad((string) (20 + ($index % 25)), 2, '0', STR_PAD_LEFT) . ':00';
    } else {
        $status = 'present';
        $checkIn = '07:' . str_pad((string) (45 + ($index % 10)), 2, '0', STR_PAD_LEFT) . ':00';
    }

    $insert->execute([(int) $studentId, $today, $checkIn, $status]);
    $counts[$status]++;
}
$pdo->commit();

printf("Daily attendance written for %s\n", (new DateTimeImmutable($today))->format('d/m/Y'));
foreach ($counts as $status => $count) {
    printf("  %-8s %d\n", $status, $count);
}
printf("  %-8s %d student(s)\n", 'total', count($students));
echo "\nSubject attendance was not touched - the teacher roster is unmarked and ready to submit.\n";
