<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-data.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_roles(['admin', 'manager']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(admin_csrf_token(), $csrf)) {
        throw new RuntimeException('Invalid session token. Refresh the page and try again.');
    }

    $studentId = (int) ($_POST['student_id'] ?? 0);
    if ($studentId < 1) {
        throw new RuntimeException('Invalid student ID.');
    }

    $pdo = khotwa_db();
    $today = date('Y-m-d');
    $timeNow = date('H:i:s');

    $studentStatement = $pdo->prepare(
        "SELECT id,
                CONCAT(first_name_en, ' ', last_name_en) AS name_en,
                CONCAT(first_name_ar, ' ', last_name_ar) AS name_ar,
                status
         FROM students
         WHERE id = ?
         LIMIT 1"
    );
    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch();
    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    if ((string) $student['status'] !== 'active') {
        throw new RuntimeException('Student is not active.');
    }

    $attendanceStatement = $pdo->prepare(
        "SELECT id, status, check_in_time, check_out_time, notes
         FROM student_daily_attendance
         WHERE student_id = ? AND attendance_date = ?
         LIMIT 1"
    );
    $attendanceStatement->execute([$studentId, $today]);
    $attendance = $attendanceStatement->fetch();

    $action = 'existing';
    if (!$attendance) {
        $insert = $pdo->prepare(
            "INSERT INTO student_daily_attendance (
                student_id, attendance_date, check_in_time, check_out_time, status, notes
             ) VALUES (?, ?, ?, NULL, 'present', ?)"
        );
        $insert->execute([
            $studentId,
            $today,
            $timeNow,
            'Recorded via QR scan by ' . ($user['role'] ?? 'admin') . ' account',
        ]);

        $attendanceStatement->execute([$studentId, $today]);
        $attendance = $attendanceStatement->fetch();
        $action = 'created';
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'student' => [
            'id' => (int) $student['id'],
            'name_en' => (string) $student['name_en'],
            'name_ar' => (string) $student['name_ar'],
        ],
        'attendance' => [
            'date' => $today,
            'status' => (string) ($attendance['status'] ?? 'unknown'),
            'check_in_time' => (string) ($attendance['check_in_time'] ?? ''),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
