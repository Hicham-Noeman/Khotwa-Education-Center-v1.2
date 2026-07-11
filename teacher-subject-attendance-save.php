<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    verify_app_csrf();

    $user = require_roles(['teacher']);
    $teacherId = (int) ($user['teacher_id'] ?? 0);
    if ($teacherId < 1) {
        throw new RuntimeException('This account is not linked to a teacher profile.');
    }

    $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
    $attendanceDate = (string) ($_POST['attendance_date'] ?? '');
    $status = (string) ($_POST['status'] ?? '');
    $lessonNote = trim((string) ($_POST['note'] ?? ''));
    $homeworkNote = trim((string) ($_POST['homework_note'] ?? ''));

    if ($enrollmentId < 1) {
        throw new RuntimeException('Invalid enrollment.');
    }
    if ($attendanceDate === '') {
        throw new RuntimeException('Attendance date is required.');
    }
    if (!in_array($status, ['attended', 'missed'], true)) {
        throw new RuntimeException('Mark attendance first before saving notes.');
    }

    $pdo = khotwa_db();

    $enrollmentStatement = $pdo->prepare(
        "SELECT student_id, teacher_subject_id, teacher_id, subject_id
         FROM student_subject_enrollments
         WHERE id = ? AND teacher_id = ? AND status = 'active'
         LIMIT 1"
    );
    $enrollmentStatement->execute([$enrollmentId, $teacherId]);
    $enrollment = $enrollmentStatement->fetch();
    if (!$enrollment) {
        throw new RuntimeException('Student is not assigned to this teacher.');
    }

    $dailyAttendanceStatement = $pdo->prepare(
        "SELECT id
         FROM student_daily_attendance
         WHERE student_id = ? AND attendance_date = ?
         LIMIT 1"
    );
    $dailyAttendanceStatement->execute([(int) $enrollment['student_id'], $attendanceDate]);
    $dailyAttendanceId = (int) $dailyAttendanceStatement->fetchColumn();
    if ($dailyAttendanceId < 1) {
        throw new RuntimeException('Daily attendance is not marked by administration yet.');
    }

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

    echo json_encode([
        'success' => true,
        'saved' => [
            'enrollment_id' => $enrollmentId,
            'status' => $status,
            'homework_note' => $homeworkNote,
            'note' => $lessonNote,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
