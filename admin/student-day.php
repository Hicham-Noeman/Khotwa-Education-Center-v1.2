<?php
declare(strict_types=1);

/**
 * The read-only dossier behind the overview scanner.
 *
 * It answers one question - "what does this student's day look like right now?"
 * - and it answers it without touching a single row. Marking attendance stays on
 * the attendance page, where it is a deliberate act rather than a side effect of
 * pointing a camera at a card.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

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

    $studentStatement = $pdo->prepare(
        "SELECT id,
                CONCAT(first_name_en, ' ', last_name_en) AS name_en,
                CONCAT(first_name_ar, ' ', last_name_ar) AS name_ar,
                status,
                photo_path,
                father_phone_number,
                mother_phone_number
         FROM students
         WHERE id = ?
         LIMIT 1"
    );
    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch();
    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    // Today's arrival at the centre. Null when nobody has marked the student yet:
    // the dossier reports what is recorded, it does not assume an absence.
    $dailyStatement = $pdo->prepare(
        "SELECT status, check_in_time, check_out_time, notes
         FROM student_daily_attendance
         WHERE student_id = ? AND attendance_date = ?
         LIMIT 1"
    );
    $dailyStatement->execute([$studentId, $today]);
    $daily = $dailyStatement->fetch() ?: null;

    /*
     * Every active enrolment, with today's session hung off it by a LEFT JOIN.
     * The join has to start from the enrolment rather than from the attendance
     * row, or a subject the student never showed up for would vanish from the
     * list - which is exactly the case this page exists to surface.
     */
    $enrolmentStatement = $pdo->prepare(
        "SELECT student_subject_enrollments.id AS enrollment_id,
                student_subject_enrollments.status AS enrollment_status,
                subjects.name_en AS subject_en,
                subjects.name_ar AS subject_ar,
                TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_en,
                TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))) AS teacher_ar,
                student_subject_attendance.status AS session_status,
                student_subject_attendance.session_number,
                student_subject_attendance.homework_note,
                student_subject_attendance.notes AS session_notes
         FROM student_subject_enrollments
         INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
         INNER JOIN teachers ON teachers.id = student_subject_enrollments.teacher_id
         LEFT JOIN student_subject_attendance
                ON student_subject_attendance.student_id = student_subject_enrollments.student_id
               AND student_subject_attendance.teacher_subject_id = student_subject_enrollments.teacher_subject_id
               AND student_subject_attendance.attendance_date = ?
         WHERE student_subject_enrollments.student_id = ?
           AND student_subject_enrollments.status = 'active'
         ORDER BY subjects.name_en, student_subject_attendance.session_number"
    );
    $enrolmentStatement->execute([$today, $studentId]);
    $enrolments = $enrolmentStatement->fetchAll();

    // Warnings still hanging over the student. Resolved and dismissed ones are
    // history, not something the person holding the phone needs to act on.
    $warningStatement = $pdo->prepare(
        "SELECT student_warnings.warning_date,
                student_warnings.warning_type,
                student_warnings.warning_number,
                student_warnings.reason,
                student_warnings.status,
                student_warnings.notes,
                student_warnings.parent_notified,
                TRIM(CONCAT(COALESCE(teachers.first_name, ''), ' ', COALESCE(teachers.last_name, ''))) AS teacher_en,
                TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))) AS teacher_ar
         FROM student_warnings
         LEFT JOIN teachers ON teachers.id = student_warnings.teacher_id
         WHERE student_warnings.student_id = ?
           AND student_warnings.status NOT IN ('resolved', 'dismissed')
         ORDER BY student_warnings.warning_date DESC, student_warnings.id DESC
         LIMIT 10"
    );
    $warningStatement->execute([$studentId]);
    $warnings = $warningStatement->fetchAll();

    $sessions = [];
    $homework = [];
    $notes = [];
    $attendedCount = 0;
    $missedCount = 0;
    $pendingCount = 0;

    foreach ($enrolments as $row) {
        $sessionStatus = $row['session_status'] === null ? 'not_recorded' : (string) $row['session_status'];
        if ($sessionStatus === 'attended') {
            $attendedCount++;
        } elseif ($sessionStatus === 'missed') {
            $missedCount++;
        } else {
            $pendingCount++;
        }

        $subject = (string) $row['subject_en'];
        $subjectAr = (string) ($row['subject_ar'] ?? '');
        $teacher = trim((string) $row['teacher_en']);
        $teacherAr = trim((string) ($row['teacher_ar'] ?? ''));
        // Carried on every note row too, so the card can switch language
        // without going back to the server for the same names.
        $names = [
            'subject_en' => $subject,
            'subject_ar' => $subjectAr,
            'teacher_en' => $teacher,
            'teacher_ar' => $teacherAr,
        ];

        $sessions[] = $names + [
            'status' => $sessionStatus,
            'session_number' => $row['session_number'] === null ? null : (int) $row['session_number'],
        ];

        $homeworkNote = trim((string) ($row['homework_note'] ?? ''));
        if ($homeworkNote !== '') {
            $homework[] = $names + ['note' => $homeworkNote];
        }

        $sessionNote = trim((string) ($row['session_notes'] ?? ''));
        if ($sessionNote !== '') {
            $notes[] = $names + ['note' => $sessionNote];
        }
    }

    $warningList = [];
    foreach ($warnings as $row) {
        $warningList[] = [
            'date' => (string) $row['warning_date'],
            'type' => (string) ($row['warning_type'] ?? ''),
            'number' => $row['warning_number'] === null ? null : (int) $row['warning_number'],
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'notes' => trim((string) ($row['notes'] ?? '')),
            'teacher_en' => trim((string) ($row['teacher_en'] ?? '')),
            'teacher_ar' => trim((string) ($row['teacher_ar'] ?? '')),
            'parent_notified' => (int) $row['parent_notified'] === 1,
        ];
    }

    $photoPath = trim((string) ($student['photo_path'] ?? ''));

    echo json_encode([
        'success' => true,
        'date' => $today,
        'student' => [
            'id' => (int) $student['id'],
            'name_en' => (string) $student['name_en'],
            'name_ar' => (string) $student['name_ar'],
            'status' => (string) $student['status'],
            'photo_url' => $photoPath === '' ? null : khotwa_url(ltrim($photoPath, '/')),
            'father_phone' => (string) ($student['father_phone_number'] ?? ''),
            'mother_phone' => (string) ($student['mother_phone_number'] ?? ''),
        ],
        'daily' => $daily === null ? null : [
            'status' => (string) $daily['status'],
            'check_in_time' => (string) ($daily['check_in_time'] ?? ''),
            'check_out_time' => (string) ($daily['check_out_time'] ?? ''),
            'notes' => trim((string) ($daily['notes'] ?? '')),
        ],
        'totals' => [
            'enrollments' => count($sessions),
            'attended' => $attendedCount,
            'missed' => $missedCount,
            'not_recorded' => $pendingCount,
        ],
        'sessions' => $sessions,
        'homework' => $homework,
        'notes' => $notes,
        'warnings' => $warningList,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
