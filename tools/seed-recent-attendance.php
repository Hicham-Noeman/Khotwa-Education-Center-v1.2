<?php
/*
 * Tops up the demo data with attendance for the current week.
 *
 * tools/setup.php seeds a full 2025-2026 school year, which has already ended
 * relative to today, and the teacher attendance screens are pinned to today's
 * date. This adds the last six working days so those screens have data:
 *   - every earlier day gets daily + subject attendance (history for parents)
 *   - today gets daily attendance only, so a teacher still has something to submit
 */
declare(strict_types=1);
define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/database.php';

$pdo = getDatabaseConnection();

$days = [];
$cursor = new DateTimeImmutable('today');
while (count($days) < 6) {
    if ((int) $cursor->format('N') <= 6) {   // the center runs Monday to Saturday
        $days[] = $cursor;
    }
    $cursor = $cursor->modify('-1 day');
}
$days = array_reverse($days);
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$students = $pdo->query("SELECT id FROM students WHERE status = 'active' ORDER BY id")->fetchAll();
$enrollments = $pdo->query(
    "SELECT student_id, teacher_subject_id, teacher_id, subject_id
     FROM student_subject_enrollments WHERE status = 'active'"
)->fetchAll();

$byStudent = [];
foreach ($enrollments as $row) {
    $byStudent[(int) $row['student_id']][] = $row;
}

$insertDaily = $pdo->prepare(
    "INSERT INTO student_daily_attendance (student_id, attendance_date, check_in_time, check_out_time, status, notes)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
$findDaily = $pdo->prepare(
    'SELECT id FROM student_daily_attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1'
);
$insertSubject = $pdo->prepare(
    "INSERT INTO student_subject_attendance
        (daily_attendance_id, student_id, attendance_date, teacher_subject_id, teacher_id, subject_id,
         session_number, status, homework_note, notes)
     VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NULL)
     ON DUPLICATE KEY UPDATE status = VALUES(status)"
);

$homework = [
    'Finish exercises 4 to 9 and check the answers.',
    'Read the chapter summary and write five key points.',
    'Practice the new vocabulary list for tomorrow.',
    'Revise yesterday’s worked example before the next session.',
    '',
];

$pdo->beginTransaction();
$dailyCount = 0;
$subjectCount = 0;

foreach ($days as $index => $day) {
    $date = $day->format('Y-m-d');
    foreach ($students as $position => $student) {
        $studentId = (int) $student['id'];
        $seed = ($studentId + $index) % 10;

        $status = match (true) {
            $seed === 0 => 'absent',
            $seed === 1 => 'late',
            $seed === 2 => 'left_early',
            default => 'present',
        };
        $checkIn = $status === 'absent' ? null : ($status === 'late' ? '08:24:00' : '07:55:00');
        $checkOut = $status === 'absent' ? null : ($status === 'left_early' ? '12:10:00' : '14:05:00');

        $insertDaily->execute([$studentId, $date, $checkIn, $checkOut, $status, 'Demo attendance record']);
        $dailyCount++;

        // Today keeps daily attendance only, so the teacher submission screen has work to do.
        if ($date === $today || $status === 'absent') {
            continue;
        }

        $findDaily->execute([$studentId, $date]);
        $dailyId = (int) $findDaily->fetchColumn();
        if ($dailyId < 1) {
            continue;
        }

        foreach (array_slice($byStudent[$studentId] ?? [], 0, 3) as $slot => $enrollment) {
            $attended = (($studentId + $index + $slot) % 8) !== 0;
            $insertSubject->execute([
                $dailyId,
                $studentId,
                $date,
                (int) $enrollment['teacher_subject_id'],
                (int) $enrollment['teacher_id'],
                (int) $enrollment['subject_id'],
                $attended ? 'attended' : 'missed',
                $attended ? $homework[($studentId + $slot) % count($homework)] : '',
            ]);
            $subjectCount++;
        }
    }
}

$pdo->commit();

printf("days seeded: %s .. %s\n", $days[0]->format('Y-m-d'), $days[count($days) - 1]->format('Y-m-d'));
printf("daily attendance rows written: %d\n", $dailyCount);
printf("subject attendance rows written: %d\n", $subjectCount);
