<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';
require_once __DIR__ . '/../src/portal-ui.php';
require_once __DIR__ . '/../src/parent-agreement.php';

$user = require_roles(['parent']);
$parentUserId = (int) ($user['id'] ?? 0);

/*
 * The agreement gate.
 *
 * A family that has not accepted the published agreement for every one of their
 * children sees the agreement and nothing else. It runs before any of the page's
 * own data is read, and the accept post is handled here too, so there is no
 * route through this file that shows a child's record first and asks afterwards.
 *
 * With no published version there is nothing to accept and the portal opens as
 * it always did.
 */
$agreementPdo = khotwa_db();
$publishedAgreement = parent_agreement_published($agreementPdo);
$agreementId = (int) ($publishedAgreement['id'] ?? 0);

if ($agreementId > 0
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (string) ($_POST['action'] ?? '') === 'accept_agreement'
) {
    verify_app_csrf();
    // The box has to be ticked; the button alone is not agreement.
    if ((string) ($_POST['agree'] ?? '') === '1') {
        parent_agreement_accept(
            $agreementPdo,
            $parentUserId,
            $agreementId,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }
    header('Location: ' . khotwa_url('parent/index.php'));
    exit;
}

$agreementPending = $agreementId > 0
    ? parent_agreement_pending_students($agreementPdo, $parentUserId, $agreementId)
    : [];

if ($agreementPending !== []) {
    $agreementClauses = parent_agreement_clauses($agreementPdo, $agreementId);
    /*
     * A family that has accepted an older version is being shown a change, not a
     * first agreement. Saying which is the difference between reading it and
     * clicking past something that looks familiar.
     */
    $agreementIsUpdate = parent_agreement_is_update_for($agreementPdo, $parentUserId, $agreementId);
    require __DIR__ . '/agreement-gate.php';
    exit;
}

$selectedStudentId = (int) ($_GET['student_id'] ?? 0);
/*
 * The review belongs to the parent account, not to any one child, so it has a
 * screen of its own rather than a panel repeated under every child.
 */
$view = (string) ($_GET['view'] ?? 'child');
if (!in_array($view, ['child', 'review', 'agreement'], true)) {
    $view = 'child';
}

$children = [];
$studentOverview = null;
$subjects = [];
$attendance = [];
$subjectSessions = [];
$subjectTotals = [];
$homeworkItems = [];
$billing = [];
$parentWarnings = [];
$childAgeGroup = null;
$expiationsByCategory = [];
$parentReviews = [];
$parentReview = null;
$error = '';
$reviewError = '';
$parentMessage = isset($_GET['expiation']) ? 'Expiation saved. The administration will confirm it once completed.' : '';
if (isset($_GET['review'])) {
    $parentMessage = 'Thank you. Your review was sent to the administration.';
}

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
            current_record.teaching_language,
            students.status,
            COALESCE(current_record.grade_name, 'Not assigned') AS grade_name,
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

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (string) ($_POST['action'] ?? '') === 'select_expiation'
    ) {
        verify_app_csrf();
        $warningId = (int) ($_POST['warning_id'] ?? 0);
        $expiationId = (int) ($_POST['expiation_id'] ?? 0);

        // The warning must belong to one of this parent's children and still be awaiting an expiation.
        $warningStatement = $pdo->prepare(
            "SELECT student_warnings.student_id, student_warnings.status,
                    student_warnings.warning_type,
                    TIMESTAMPDIFF(YEAR, students.date_of_birth, CURDATE()) AS student_age
             FROM student_warnings
             INNER JOIN students ON students.id = student_warnings.student_id
             WHERE student_warnings.id = ?
             LIMIT 1"
        );
        $warningStatement->execute([$warningId]);
        $warningRow = $warningStatement->fetch();

        if (!$warningRow || !in_array((int) $warningRow['student_id'], $allowedStudentIds, true)) {
            throw new RuntimeException('That warning could not be found for your children.');
        }
        if ((string) $warningRow['warning_type'] !== 'written') {
            throw new RuntimeException('Only a written warning carries an expiation.');
        }
        if ((string) $warningRow['status'] !== 'issued') {
            throw new RuntimeException('An expiation has already been chosen for this warning.');
        }

        // The expiation must be active and appropriate for the student's age group.
        $expiationStatement = $pdo->prepare(
            "SELECT expiations.id
             FROM expiations
             INNER JOIN age_groups ON age_groups.id = expiations.age_group_id AND age_groups.status = 'active'
             INNER JOIN expiation_categories ON expiation_categories.id = expiations.category_id AND expiation_categories.status = 'active'
             WHERE expiations.id = ?
               AND expiations.status = 'active'
               AND ? BETWEEN age_groups.min_age AND age_groups.max_age
             LIMIT 1"
        );
        $expiationStatement->execute([$expiationId, (int) $warningRow['student_age']]);
        if (!$expiationStatement->fetchColumn()) {
            throw new RuntimeException('That expiation is not available for this student.');
        }

        $assignStatement = $pdo->prepare(
            "UPDATE student_warnings
             SET expiation_id = ?, expiation_selected_by_user_id = ?, expiation_selected_at = NOW(), status = 'assigned'
             WHERE id = ? AND status = 'issued'"
        );
        $assignStatement->execute([$expiationId, $parentUserId, $warningId]);

        // The administration is what happens next: they confirm it was done.
        $chosenTitle = $pdo->prepare('SELECT title_en FROM expiations WHERE id = ? LIMIT 1');
        $chosenTitle->execute([$expiationId]);
        notify_expiation_chosen(
            $pdo,
            (int) $warningRow['student_id'],
            (string) ($chosenTitle->fetchColumn() ?: 'an expiation')
        );

        header('Location: ' . khotwa_url('parent/index.php') . '?student_id=' . (int) $warningRow['student_id'] . '&expiation=1');
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (string) ($_POST['action'] ?? '') === 'submit_review'
    ) {
        try {
            verify_app_csrf();
            $rating = (int) ($_POST['rating'] ?? 0);
            $reviewText = trim((string) ($_POST['review_text'] ?? ''));
            /*
             * The name is read-only on the form, so it is taken from the account
             * rather than from the request - a posted display_name is ignored.
             */
            $existingReview = null;
            $existingStatement = $pdo->prepare(
                'SELECT display_name, display_name_ar FROM homepage_reviews WHERE parent_user_id = ? LIMIT 1'
            );
            $existingStatement->execute([$parentUserId]);
            $existingReview = $existingStatement->fetch() ?: null;
            $reviewNames = parent_review_names($user, $children, $existingReview);
            $displayName = $reviewNames['en'];
            $displayNameAr = $reviewNames['ar'];

            if ($rating < 1 || $rating > 5) {
                throw new RuntimeException('Choose a rating between 1 and 5 stars.');
            }
            if (mb_strlen($reviewText) < 20) {
                throw new RuntimeException('Please write at least 20 characters so families can learn from your experience.');
            }
            if (mb_strlen($reviewText) > 1200) {
                throw new RuntimeException('Please keep the review under 1200 characters.');
            }

            // A parent account has one review, however many children it covers. Sending
            // again replaces the words they wrote instead of adding another entry.
            // Edited text goes back to the administration for a fresh look, which is why
            // the moderation fields are cleared -- the parent is never shown any of this.
            /*
             * Both readings are stored, so the website can sign the review in
             * whichever language a visitor is reading it in.
             */
            $saveReview = $pdo->prepare(
                "INSERT INTO homepage_reviews
                    (parent_user_id, display_name, display_name_ar, relationship_label, rating, review_text, status)
                 VALUES (?, ?, ?, NULL, ?, ?, 'pending')
                 ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    display_name_ar = VALUES(display_name_ar),
                    rating = VALUES(rating),
                    review_text = VALUES(review_text),
                    status = 'pending',
                    reviewed_by_user_id = NULL,
                    reviewed_at = NULL"
            );
            $saveReview->execute([
                $parentUserId,
                mb_substr($displayName, 0, 120),
                mb_substr($displayNameAr, 0, 120),
                $rating,
                $reviewText,
            ]);

            header('Location: ' . khotwa_url('parent/index.php') . '?view=review&review=1');
            exit;
        } catch (Throwable $reviewException) {
            $reviewError = $reviewException->getMessage();
        }
    }

    $parentReviewsStatement = $pdo->prepare(
        'SELECT id, display_name, display_name_ar, rating, review_text, updated_at
         FROM homepage_reviews
         WHERE parent_user_id = ?
         ORDER BY id DESC
         LIMIT 10'
    );
    $parentReviewsStatement->execute([$parentUserId]);
    $parentReviews = $parentReviewsStatement->fetchAll();
    $parentReview = $parentReviews[0] ?? null;

    /*
     * Every subject the child is enrolled in, with how many of its sessions
     * they actually attended. A parent asks "is my child going to their
     * classes?" - a list of subject names alone cannot answer that, so the
     * count of sessions and the last one sit on the same row as the subject.
     */
    /*
     * Every subject the child is enrolled in, with how many of its sessions
     * they actually attended. A list of subject names alone cannot answer
     * "is my child going to their classes?", so the counts ride along.
     */
    $subjectsStatement = $pdo->prepare(
        "SELECT
            subjects.id AS subject_id,
            subjects.name_en AS subject_name,
            subjects.name_ar AS subject_name_ar,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_name,
            TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))) AS teacher_name_ar,
            student_subject_enrollments.academic_year,
            student_subject_enrollments.status,
            (SELECT COUNT(*)
               FROM student_subject_attendance session_row
              WHERE session_row.student_id = student_subject_enrollments.student_id
                AND session_row.subject_id = student_subject_enrollments.subject_id) AS total_sessions,
            (SELECT COUNT(*)
               FROM student_subject_attendance session_row
              WHERE session_row.student_id = student_subject_enrollments.student_id
                AND session_row.subject_id = student_subject_enrollments.subject_id
                AND session_row.status = 'attended') AS attended_sessions
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
         LIMIT 400"
    );
    $attendanceStatement->execute([$selectedStudentId]);
    $attendance = $attendanceStatement->fetchAll();

    /*
     * The sessions themselves, rather than the totals above: attendance is read
     * a week at a time, and whether a child sat in Tuesday's maths class is a
     * question about one week, not about the year.
     */
    $subjectSessionsStatement = $pdo->prepare(
        "SELECT
            student_subject_attendance.attendance_date,
            student_subject_attendance.subject_id,
            student_subject_attendance.status,
            subjects.name_en AS subject_name,
            subjects.name_ar AS subject_name_ar,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_name,
            TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))) AS teacher_name_ar
         FROM student_subject_attendance
         INNER JOIN subjects ON subjects.id = student_subject_attendance.subject_id
         INNER JOIN teachers ON teachers.id = student_subject_attendance.teacher_id
         WHERE student_subject_attendance.student_id = ?
         ORDER BY student_subject_attendance.attendance_date DESC,
                  subjects.name_en"
    );
    $subjectSessionsStatement->execute([$selectedStudentId]);
    $subjectSessions = $subjectSessionsStatement->fetchAll();

    $homeworkStatement = $pdo->prepare(
        "SELECT
            student_subject_attendance.attendance_date,
            subjects.name_en AS subject_name,
            subjects.name_ar AS subject_name_ar,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_name,
            TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))) AS teacher_name_ar,
            student_subject_attendance.homework_note
         FROM student_subject_attendance
         INNER JOIN subjects ON subjects.id = student_subject_attendance.subject_id
         INNER JOIN teachers ON teachers.id = student_subject_attendance.teacher_id
         WHERE student_subject_attendance.student_id = ?
           AND student_subject_attendance.homework_note IS NOT NULL
           AND TRIM(student_subject_attendance.homework_note) <> ''
         ORDER BY student_subject_attendance.attendance_date DESC,
                  student_subject_attendance.id DESC
         LIMIT 400"
    );
    $homeworkStatement->execute([$selectedStudentId]);
    $homeworkItems = $homeworkStatement->fetchAll();

    /*
     * Everything read a week at a time carries the Monday of its own week. The
     * page holds them all and shows one week; stepping between weeks is then
     * instant and nothing scrolls sideways.
     */
    $parentWeekStart = static function (string $date): string {
        return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
    };
    foreach ($homeworkItems as $index => $homeworkRow) {
        $homeworkItems[$index]['week_start'] = $parentWeekStart((string) $homeworkRow['attendance_date']);
    }
    foreach ($attendance as $index => $attendanceRow) {
        $attendance[$index]['week_start'] = $parentWeekStart((string) $attendanceRow['attendance_date']);
    }
    foreach ($subjectSessions as $index => $sessionRow) {
        $subjectSessions[$index]['week_start'] = $parentWeekStart((string) $sessionRow['attendance_date']);
    }

    // A session row shows how the year is going for its own subject, so the
    // totals already gathered above are kept to hand by subject.
    $subjectTotals = [];
    foreach ($subjects as $subjectRow) {
        $subjectTotals[(int) $subjectRow['subject_id']] = [
            'attended' => (int) ($subjectRow['attended_sessions'] ?? 0),
            'total' => (int) ($subjectRow['total_sessions'] ?? 0),
        ];
    }

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
         LIMIT 120"
    );
    $billingStatement->execute([$selectedStudentId]);
    $billing = $billingStatement->fetchAll();

    $warningsStatement = $pdo->prepare(
        "SELECT student_warnings.id, student_warnings.warning_date, student_warnings.warning_type,
                student_warnings.warning_number,
                student_warnings.parent_message, student_warnings.status,
                student_warnings.expiation_selected_at,
                expiations.title_en AS expiation_title, expiations.title_ar AS expiation_title_ar,
                expiation_categories.name_en AS expiation_category
         FROM student_warnings
         LEFT JOIN expiations ON expiations.id = student_warnings.expiation_id
         LEFT JOIN expiation_categories ON expiation_categories.id = expiations.category_id
         WHERE student_warnings.student_id = ?
           AND student_warnings.warning_type = 'written'
           AND student_warnings.status IN ('issued', 'assigned')
         ORDER BY FIELD(student_warnings.status, 'issued', 'assigned'),
                  student_warnings.warning_date DESC, student_warnings.id DESC"
    );
    $warningsStatement->execute([$selectedStudentId]);
    $parentWarnings = $warningsStatement->fetchAll();

    // Match the child to an age group and load expiations available for that age group.
    $ageGroupStatement = $pdo->prepare(
        "SELECT age_groups.id, age_groups.name_en, age_groups.name_ar,
                TIMESTAMPDIFF(YEAR, students.date_of_birth, CURDATE()) AS student_age
         FROM students
         INNER JOIN age_groups
            ON age_groups.status = 'active'
           AND TIMESTAMPDIFF(YEAR, students.date_of_birth, CURDATE()) BETWEEN age_groups.min_age AND age_groups.max_age
         WHERE students.id = ?
         ORDER BY age_groups.sort_order, age_groups.min_age
         LIMIT 1"
    );
    $ageGroupStatement->execute([$selectedStudentId]);
    $childAgeGroup = $ageGroupStatement->fetch() ?: null;

    if ($childAgeGroup !== null) {
        $expiationOptionsStatement = $pdo->prepare(
            "SELECT expiations.id, expiations.title_en, expiations.title_ar,
                    expiation_categories.name_en AS category_name
             FROM expiations
             INNER JOIN expiation_categories
                ON expiation_categories.id = expiations.category_id AND expiation_categories.status = 'active'
             WHERE expiations.status = 'active' AND expiations.age_group_id = ?
             ORDER BY expiation_categories.sort_order, expiation_categories.name_en,
                      expiations.sort_order, expiations.title_en"
        );
        $expiationOptionsStatement->execute([(int) $childAgeGroup['id']]);
        foreach ($expiationOptionsStatement->fetchAll() as $option) {
            $expiationsByCategory[(string) $option['category_name']][] = $option;
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

/**
 * The payment status in words a parent would use.
 *
 * The stored enum reads "paid" and "partial_paid"; spelled out here as fully
 * and partially paid. "Paid" on its own is already the heading of the amount
 * column elsewhere, so re-using it for the status would translate to the wrong
 * sense of the word.
 */
function parent_payment_label(string $status): string
{
    return [
        'not_paid' => 'Not paid',
        'partial_paid' => 'Partially paid',
        'paid' => 'Fully paid',
        'overpaid' => 'Overpaid',
        'paused' => 'Paused',
        'unsubscribed' => 'Unsubscribed',
    ][$status] ?? ucwords(str_replace('_', ' ', $status));
}

/**
 * A billing row's month as a name and a year.
 *
 * The table stores the two numbers apart, and "2026-09" reads as a code rather
 * than a month - which is all a parent is looking for in a billing list.
 */
function parent_billing_month(int $year, int $month): string
{
    $month = max(1, min(12, $month));

    return date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;
}

/**
 * A stored TIME as hours and minutes.
 *
 * The column keeps seconds, which nobody reads off an attendance list, and an
 * empty time shows a dash rather than a run of zeros.
 */
function parent_clock(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === '00:00:00') {
        return '-';
    }

    return substr($value, 0, 5);
}

/**
 * The name a review is signed with, in both languages.
 *
 * It is not the parent's to type: English is the name on their account, and
 * Arabic has no column there, so it comes from a child's record - the father's
 * given name with the family name is exactly the parent's own name in Arabic.
 * A name the administration set by hand wins over both.
 *
 * A parent with more than one child can have more than one Arabic reading on
 * file - a second family name, a different spelling - so every distinct one is
 * returned and all of them are shown. The first is the one that gets saved.
 *
 * @param array<string, mixed>      $user
 * @param array<int, array<string, mixed>> $children
 * @param array<string, mixed>|null $existingReview
 * @return array{en: string, ar: string, ar_all: array<int, string>}
 */
function parent_review_names(array $user, array $children, ?array $existingReview): array
{
    $nameEn = trim((string) ($existingReview['display_name'] ?? ''));
    if ($nameEn === '') {
        $nameEn = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    }

    $arabicNames = [];
    $setByAdministration = trim((string) ($existingReview['display_name_ar'] ?? ''));
    if ($setByAdministration !== '') {
        $arabicNames[] = $setByAdministration;
    }

    foreach ($children as $child) {
        $childReading = trim(
            (string) ($child['father_name_ar'] ?? '') . ' '
            . (string) ($child['last_name_ar'] ?? '')
        );
        if ($childReading !== '' && !in_array($childReading, $arabicNames, true)) {
            $arabicNames[] = $childReading;
        }
    }

    if ($arabicNames === []) {
        $arabicNames[] = $nameEn;
    }

    return ['en' => $nameEn, 'ar' => $arabicNames[0], 'ar_all' => $arabicNames];
}

function parent_status_class(string $value): string
{
    return 'status-' . trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-');
}

function parent_icon(string $name): string
{
  $paths = [
    'children' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'overview' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h8M8 17h5"/>',
    'subjects' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
    'attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-5 5 2 2 4-4"/>',
    'billing' => '<circle cx="12" cy="12" r="9"/><path d="M16 8h-5a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4H8m4-10v12"/>',
    'review' => '<path d="M12 3.6 14.3 9l5.7.4-4.4 3.7 1.4 5.6L12 15.7 7 18.7l1.4-5.6L4 9.4 9.7 9Z"/>',
    'agreement' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 12h6M9 16h3"/><path d="m14.5 18.5 1.5 1.5 3-3"/>',
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
/*
 * Written warnings only, so both figures speak about something a parent can act
 * on: how many stand, and how many still need an expiation chosen.
 */
$writtenWarningCount = count($parentWarnings);
$openWarningCount = count(array_filter(
  $parentWarnings,
  static fn (array $row): bool => (string) ($row['status'] ?? '') === 'issued'
));
$selectedChildName = (string) ($studentOverview['student_name'] ?? '-');
$selectedChildStatus = (string) ($studentOverview['status'] ?? 'inactive');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title>Parent Portal | Khotwa Education Center</title>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/parent.css')) ?>">
</head>
<body class="admin-page parent-page">
  <div class="admin-shell" data-admin-shell>
    <aside class="admin-sidebar parent-sidebar" id="admin-sidebar" aria-label="Parent navigation">
      <?php portal_sidebar_top(khotwa_url('parent/index.php'), 'Khotwa parent portal home', 'Parent'); ?>

      <nav class="admin-nav">
        <section class="nav-group">
          <h2>My Children</h2>
          <?php foreach ($children as $child): ?>
            <?php
            $childId = (int) $child['id'];
            $isActiveChild = $view === 'child' && $childId === $selectedStudentId;
            $label = (string) $child['student_name'];
            // The row carries both readings so the panel can switch language
            // without another request; data-i18n-skip keeps the dictionary out
            // of a proper name it would only leave alone anyway.
            $labelAr = trim((string) ($child['student_name_ar'] ?? ''));
            ?>
            <a class="<?= $isActiveChild ? 'is-active' : '' ?>" href="<?= e(khotwa_url('parent/index.php')) ?>?student_id=<?= e((string) $childId) ?>"
               title="<?= e($label) ?>" data-title-en="<?= e($label) ?>" data-title-ar="<?= e($labelAr) ?>">
              <?= parent_icon('children') ?><span data-i18n-skip data-en="<?= e($label) ?>" data-ar="<?= e($labelAr) ?>"><?= e($label) ?></span>
              <?php if ($isActiveChild): ?><i></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </section>

        <section class="nav-group">
          <h2>Shortcuts</h2>
          <a class="<?= $view === 'review' ? 'is-active' : '' ?>" href="<?= e(khotwa_url('parent/index.php')) ?>?view=review"><?= parent_icon('review') ?><span>Review the center</span><?php if ($view === 'review'): ?><i></i><?php endif; ?></a>
          <?php if ($publishedAgreement !== null): ?>
            <?php // The agreement stays readable after it has been accepted. ?>
            <a class="<?= $view === 'agreement' ? 'is-active' : '' ?>" href="<?= e(khotwa_url('parent/index.php')) ?>?view=agreement"><?= parent_icon('agreement') ?><span>Parent agreement</span><?php if ($view === 'agreement'): ?><i></i><?php endif; ?></a>
          <?php endif; ?>
          <a href="<?= e(khotwa_url('logout.php')) ?>"><?= parent_icon('logout') ?><span>Logout</span></a>
        </section>
      </nav>

      <?php portal_sidebar_bottom(); ?>
    </aside>

    <div class="admin-stage">
      <?php portal_mobile_topbar(khotwa_url('parent/index.php'), 'Khotwa parent portal home'); ?>


      <main class="admin-content">
        <?php if ($error !== ''): ?>
          <div class="database-alert"><?= e($error) ?></div>
        <?php elseif ($view === 'agreement'): ?>
          <?php
          /*
           * The agreement as it was accepted, readable at any time. A parent
           * cannot reach this screen without having accepted, because the gate
           * above runs first - so it always has an acceptance date to show.
           */
          $agreementClauses = parent_agreement_clauses($agreementPdo, $agreementId);
          $acceptedStatement = $agreementPdo->prepare(
              'SELECT MIN(accepted_at) FROM parent_agreement_acceptances
               WHERE agreement_id = ? AND parent_user_id = ?'
          );
          $acceptedStatement->execute([$agreementId, $parentUserId]);
          $acceptedOn = (string) $acceptedStatement->fetchColumn();
          ?>
          <section class="content-heading">
            <div>
              <h1 data-i18n-skip data-en="<?= e((string) $publishedAgreement['title_en']) ?>" data-ar="<?= e((string) $publishedAgreement['title_ar']) ?>"><?= e((string) $publishedAgreement['title_en']) ?></h1>
              <p class="parent-heading-facts">
                <?php if ($publishedAgreement['effective_date']): ?>
                  <span>In effect from</span>
                  <span data-i18n-skip><?= e(fmt_date((string) $publishedAgreement['effective_date'])) ?></span>
                <?php endif; ?>
                <?php if ($acceptedOn !== ''): ?>
                  <?php if ($publishedAgreement['effective_date']): ?><i aria-hidden="true">&middot;</i><?php endif; ?>
                  <span>Accepted on</span>
                  <span data-i18n-skip><?= e(fmt_date($acceptedOn)) ?></span>
                <?php endif; ?>
              </p>
            </div>
          </section>

          <section class="data-panel">
            <?php $intro = trim((string) ($publishedAgreement['intro_en'] ?? '')); ?>
            <?php if ($intro !== ''): ?>
              <p class="agreement-intro" data-i18n-skip data-en="<?= e($intro) ?>" data-ar="<?= e(trim((string) ($publishedAgreement['intro_ar'] ?? '')) ?: $intro) ?>"><?= e($intro) ?></p>
            <?php endif; ?>
            <div class="agreement-body is-open">
              <?php foreach ($agreementClauses as $index => $clause): ?>
                <section class="agreement-clause">
                  <h2>
                    <i data-i18n-skip><?= e((string) ($index + 1)) ?>.</i>
                    <span data-i18n-skip data-en="<?= e((string) $clause['title_en']) ?>" data-ar="<?= e((string) $clause['title_ar']) ?>"><?= e((string) $clause['title_en']) ?></span>
                  </h2>
                  <p data-i18n-skip data-en="<?= e((string) $clause['body_en']) ?>" data-ar="<?= e((string) $clause['body_ar']) ?>"><?= e((string) $clause['body_en']) ?></p>
                </section>
              <?php endforeach; ?>
            </div>
          </section>

        <?php elseif ($view === 'review'): ?>
          <section class="content-heading">
            <div>
              <h1>Review the center</h1>
            </div>
          </section>

          <section class="parent-reviews" id="reviews-panel">
            <article class="data-panel">
              <div class="panel-heading">
                <div>
                  <span>Your voice</span>
                  <h2>Review the center</h2>
                </div>
              </div>

              <?php // Kept on one line: the translator matches a whole text node, and an
                    // embedded newline would stop this sentence from being found. ?>
              <p class="parent-review-intro">You have one review, and sending it again replaces it. The administration reads it before anything appears on the website.</p>

              <form class="parent-review-form" method="post">
                <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="action" value="submit_review">

                <?php $reviewNames = parent_review_names($user, $children, $parentReview); ?>
                <?php /*
                       * Shown, not asked for. Both readings sit side by side rather
                       * than swapping with the page language: this is the name the
                       * website carries in each language, so a parent should be able
                       * to check both of them at once.
                       */ ?>
                <div class="parent-review-field parent-review-signature">
                  <span>Name shown on the website</span>
                  <div class="parent-review-names">
                    <div>
                      <small>English</small>
                      <strong data-i18n-skip><?= e($reviewNames['en']) ?></strong>
                    </div>
                    <?php foreach ($reviewNames['ar_all'] as $arabicName): ?>
                      <div>
                        <small lang="ar" dir="rtl" data-i18n-skip>العربية</small>
                        <strong lang="ar" dir="rtl" data-i18n-skip><?= e($arabicName) ?></strong>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <small>Set by the center. Contact the administration to change it.</small>
                </div>

                <?php $currentRating = (int) ($_POST['rating'] ?? $parentReview['rating'] ?? 5); ?>
                <fieldset class="parent-review-rating">
                  <legend>Your rating</legend>
                  <?php /*
                         * Five stars that fill up to the one chosen, rather than five
                         * separate chips each carrying its own row of stars. The
                         * inputs run 5 down to 1 and the row is reversed, so plain
                         * sibling rules can light every star below the chosen one.
                         */ ?>
                  <div class="rating-options">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                      <input
                        type="radio"
                        id="rating-<?= e((string) $star) ?>"
                        name="rating"
                        value="<?= e((string) $star) ?>"
                        <?= $star === $currentRating ? 'checked' : '' ?>
                        required
                      >
                      <label for="rating-<?= e((string) $star) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3.1 2.7 6 6.5.6-4.9 4.4 1.4 6.4-5.7-3.4-5.7 3.4 1.4-6.4L2.8 9.7l6.5-.6Z"/></svg>
                        <span class="sr-only"><?= e((string) $star) ?> <?= $star === 1 ? 'star' : 'stars' ?></span>
                      </label>
                    <?php endfor; ?>
                  </div>
                </fieldset>

                <label class="parent-review-field">
                  <span>Your review</span>
                  <textarea
                    name="review_text"
                    rows="4"
                    minlength="20"
                    maxlength="1200"
                    placeholder="Tell other families what your child experienced at Khotwa."
                    required
                  ><?= e((string) ($_POST['review_text'] ?? $parentReview['review_text'] ?? '')) ?></textarea>
                </label>

                <button class="primary-action" type="submit">
                  <?= $parentReview === null ? 'Send review' : 'Update my review' ?>
                </button>
              </form>

            </article>
          </section>
        <?php else: ?>
          <?php // The page is about one child, so the child names the page. ?>
          <section class="content-heading">
            <div>
              <h1 data-i18n-skip data-en="<?= e($selectedChildName) ?>" data-ar="<?= e((string) ($studentOverview['student_name_ar'] ?? '')) ?>"><?= e($selectedChildName) ?></h1>
              <?php if ($studentOverview !== null): ?>
                <?php // The facts that used to need a panel of their own. ?>
                <p class="parent-heading-facts">
                  <span><?= e((string) $studentOverview['grade_name']) ?></span>
                  <i aria-hidden="true">·</i>
                  <span><?= e((string) ($studentOverview['teaching_language'] ?: 'Not set')) ?></span>
                </p>
              <?php endif; ?>
            </div>
          </section>

          <?php /*
                 * What is owed leads, at the size of the question it answers.
                 * The two figures under it belong to the child named above, so
                 * they read as that child's row rather than four equal tiles.
                 */ ?>
          <section class="metrics-grid parent-metrics-grid">
            <article class="metric-card metric-green parent-balance-card">
              <span class="metric-dot"></span>
              <strong><?= e(number_format($familyOpenBalance, 2)) ?></strong>
              <p>Family open balance</p>
            </article>
            <article class="metric-card metric-pink">
              <span class="metric-dot"></span>
              <strong><?= e((string) $selectedStudentSubjects) ?></strong>
              <p>Active subjects</p>
            </article>
            <article class="metric-card metric-navy">
              <span class="metric-dot"></span>
              <strong><?= e(number_format($recentAttendanceRate, 1)) ?>%</strong>
              <p>Recent attendance rate</p>
            </article>
            <article class="metric-card metric-orange">
              <span class="metric-dot"></span>
              <strong data-i18n-skip><?= e(fmt_date((string) ($studentOverview['latest_attendance_date'] ?? ''), '—')) ?></strong>
              <p>Latest attendance</p>
            </article>
            <?php if ($writtenWarningCount > 0): ?>
              <?php /*
                     * Behaviour only appears for a child who has a written
                     * warning standing. A family with nothing to answer for is
                     * not shown a zero - there is nothing there to read.
                     */ ?>
              <a class="metric-card metric-red parent-warning-metric" href="#behaviour-panel">
                <span class="metric-dot"></span>
                <strong data-i18n-skip><?= e((string) $writtenWarningCount) ?></strong>
                <p><?= $writtenWarningCount === 1 ? 'Written warning' : 'Written warnings' ?></p>
                <?php if ($openWarningCount > 0): ?>
                  <small><span data-i18n-skip><?= e((string) $openWarningCount) ?></span> <span>awaiting an expiation</span></small>
                <?php endif; ?>
              </a>
            <?php endif; ?>
          </section>

          <section class="parent-table-grid">
            <?php
            /*
             * Three panels read the same way: one week on screen, stepped through
             * with the arrows, and a row opening the whole record rather than
             * widening the table. $parentThisMonday is the week they open on and
             * the week the "This week" button returns to.
             */
            $parentThisMonday = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
            ?>

            <article class="data-panel" id="subjects-table" data-weekpanel data-weekpanel-now="<?= e($parentThisMonday) ?>">
              <div class="panel-heading">
                <div><span>Academic</span><h2>Subject attendance</h2></div>
              </div>

              <div class="weekbar">
                <button class="weekbar-step" type="button" data-weekpanel-step="-1" aria-label="Previous week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <p class="weekbar-range" data-weekpanel-range data-i18n-skip></p>
                <?php // Beside the dates rather than below them, so nothing shifts down. ?>
                <button class="weekbar-now" type="button" data-weekpanel-now-button hidden>This week</button>
                <button class="weekbar-step" type="button" data-weekpanel-step="1" aria-label="Next week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              </div>

              <table class="weeklist">
                <thead>
                  <tr><th>Subject</th><th>Session</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($subjectSessions as $row): ?>
                    <?php
                    $sessionDate = fmt_date((string) $row['attendance_date']);
                    $sessionStatus = (string) $row['status'];
                    $totals = $subjectTotals[(int) $row['subject_id']] ?? null;
                    ?>
                    <tr class="weekrow" data-weekpanel-row data-week="<?= e((string) $row['week_start']) ?>"
                        tabindex="0" role="button"
                        <?= (string) $row['week_start'] === $parentThisMonday ? '' : 'hidden' ?>>
                      <td>
                        <?php // Swapped in place rather than stacked, so only one reading is shown. ?>
                        <strong data-i18n-skip data-en="<?= e((string) $row['subject_name']) ?>" data-ar="<?= e((string) $row['subject_name_ar']) ?>"><?= e((string) $row['subject_name']) ?></strong>
                        <small class="weekrow-date" data-i18n-skip><?= e($sessionDate) ?></small>
                      </td>
                      <td>
                        <span class="status-pill <?= e(parent_status_class($sessionStatus)) ?>"><?= e(ucwords(str_replace('_', ' ', $sessionStatus))) ?></span>

                        <?php // Read into the sheet when the row is opened. ?>
                        <div class="weeksheet-source" data-weekpanel-detail hidden>
                          <span class="weeksheet-eyebrow">Subject attendance</span>
                          <h3 data-i18n-skip data-en="<?= e((string) $row['subject_name']) ?>" data-ar="<?= e((string) $row['subject_name_ar']) ?>"><?= e((string) $row['subject_name']) ?></h3>
                          <p class="weeksheet-date" data-i18n-skip><?= e($sessionDate) ?></p>
                          <dl class="weeksheet-facts">
                            <div>
                              <dt>Status</dt>
                              <dd><span class="status-pill <?= e(parent_status_class($sessionStatus)) ?>"><?= e(ucwords(str_replace('_', ' ', $sessionStatus))) ?></span></dd>
                            </div>
                            <div>
                              <dt>Teacher</dt>
                              <dd data-i18n-skip data-en="<?= e((string) $row['teacher_name']) ?>" data-ar="<?= e((string) ($row['teacher_name_ar'] ?? '')) ?>"><?= e((string) $row['teacher_name']) ?></dd>
                            </div>
                            <?php if ($totals !== null && $totals['total'] > 0): ?>
                              <div>
                                <dt>Sessions attended</dt>
                                <dd data-i18n-skip><?= e((string) $totals['attended']) ?> / <?= e((string) $totals['total']) ?></dd>
                              </div>
                            <?php endif; ?>
                          </dl>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="weeklist-none" data-weekpanel-empty>
                    <td colspan="2" class="empty-row">No sessions this week.</td>
                  </tr>
                </tbody>
              </table>
            </article>

            <article class="data-panel" id="attendance-table" data-weekpanel data-weekpanel-now="<?= e($parentThisMonday) ?>">
              <div class="panel-heading">
                <div><span>Attendance</span><h2>Daily attendance</h2></div>
              </div>

              <div class="weekbar">
                <button class="weekbar-step" type="button" data-weekpanel-step="-1" aria-label="Previous week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <p class="weekbar-range" data-weekpanel-range data-i18n-skip></p>
                <button class="weekbar-now" type="button" data-weekpanel-now-button hidden>This week</button>
                <button class="weekbar-step" type="button" data-weekpanel-step="1" aria-label="Next week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              </div>

              <?php // The day and how it went; the clock times are read in the sheet. ?>
              <table class="weeklist">
                <thead>
                  <tr><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($attendance as $row): ?>
                    <?php
                    $dayDate = fmt_date((string) $row['attendance_date']);
                    $dayStatus = (string) $row['status'];
                    $checkIn = parent_clock((string) ($row['check_in_time'] ?? ''));
                    $checkOut = parent_clock((string) ($row['check_out_time'] ?? ''));
                    ?>
                    <tr class="weekrow" data-weekpanel-row data-week="<?= e((string) $row['week_start']) ?>"
                        tabindex="0" role="button"
                        <?= (string) $row['week_start'] === $parentThisMonday ? '' : 'hidden' ?>>
                      <td data-i18n-skip><?= e($dayDate) ?></td>
                      <td>
                        <span class="status-pill <?= e(parent_status_class($dayStatus)) ?>"><?= e(ucwords(str_replace('_', ' ', $dayStatus))) ?></span>

                        <div class="weeksheet-source" data-weekpanel-detail hidden>
                          <span class="weeksheet-eyebrow">Daily attendance</span>
                          <h3 data-i18n-skip><?= e($dayDate) ?></h3>
                          <dl class="weeksheet-facts">
                            <div>
                              <dt>Status</dt>
                              <dd><span class="status-pill <?= e(parent_status_class($dayStatus)) ?>"><?= e(ucwords(str_replace('_', ' ', $dayStatus))) ?></span></dd>
                            </div>
                            <div>
                              <dt>Check in</dt>
                              <dd data-i18n-skip><?= e($checkIn) ?></dd>
                            </div>
                            <div>
                              <dt>Check out</dt>
                              <dd data-i18n-skip><?= e($checkOut) ?></dd>
                            </div>
                            <?php if (trim((string) ($row['notes'] ?? '')) !== ''): ?>
                              <div>
                                <dt>Notes</dt>
                                <dd data-i18n-skip><?= e((string) $row['notes']) ?></dd>
                              </div>
                            <?php endif; ?>
                          </dl>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="weeklist-none" data-weekpanel-empty>
                    <td colspan="2" class="empty-row">No attendance recorded this week.</td>
                  </tr>
                </tbody>
              </table>
            </article>

            <article class="data-panel" id="homework-table" data-weekpanel data-weekpanel-now="<?= e($parentThisMonday) ?>">
              <div class="panel-heading">
                <div><span>Homework</span><h2>Homework</h2></div>
              </div>

              <div class="weekbar">
                <button class="weekbar-step" type="button" data-weekpanel-step="-1" aria-label="Previous week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <p class="weekbar-range" data-weekpanel-range data-i18n-skip></p>
                <button class="weekbar-now" type="button" data-weekpanel-now-button hidden>This week</button>
                <button class="weekbar-step" type="button" data-weekpanel-step="1" aria-label="Next week">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              </div>

              <table class="weeklist">
                <thead>
                  <tr><th>Subject</th><th>Homework</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($homeworkItems as $row): ?>
                    <?php $rowDate = fmt_date((string) $row['attendance_date']); ?>
                    <tr class="weekrow" data-weekpanel-row data-week="<?= e((string) $row['week_start']) ?>"
                        tabindex="0" role="button"
                        <?= (string) $row['week_start'] === $parentThisMonday ? '' : 'hidden' ?>>
                      <td>
                        <?php // Swapped in place rather than stacked, so only one is read. ?>
                        <strong data-i18n-skip data-en="<?= e((string) $row['subject_name']) ?>" data-ar="<?= e((string) $row['subject_name_ar']) ?>"><?= e((string) $row['subject_name']) ?></strong>
                        <small class="weekrow-date" data-i18n-skip><?= e($rowDate) ?></small>
                      </td>
                      <td class="parent-homework-cell" data-i18n-skip><?= e((string) $row['homework_note']) ?>

                        <div class="weeksheet-source" data-weekpanel-detail hidden>
                          <span class="weeksheet-eyebrow">Homework</span>
                          <h3 data-i18n-skip data-en="<?= e((string) $row['subject_name']) ?>" data-ar="<?= e((string) $row['subject_name_ar']) ?>"><?= e((string) $row['subject_name']) ?></h3>
                          <p class="weeksheet-date" data-i18n-skip><?= e($rowDate) ?></p>
                          <p class="weeksheet-note" data-i18n-skip><?= e((string) $row['homework_note']) ?></p>
                          <dl class="weeksheet-facts">
                            <div>
                              <dt>Teacher</dt>
                              <dd data-i18n-skip data-en="<?= e((string) $row['teacher_name']) ?>" data-ar="<?= e((string) ($row['teacher_name_ar'] ?? '')) ?>"><?= e((string) $row['teacher_name']) ?></dd>
                            </div>
                          </dl>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="weeklist-none" data-weekpanel-empty>
                    <td colspan="2" class="empty-row">No homework set this week.</td>
                  </tr>
                </tbody>
              </table>
            </article>

            <?php // Billing is owed by the month, so that one is stepped by the year. ?>
            <?php // Billing is owed by the month, so this one is stepped by the year. ?>
            <article class="data-panel" id="billing-table" data-weekpanel data-weekpanel-unit="year"
                     data-weekpanel-now="<?= e(date('Y')) ?>">
              <div class="panel-heading">
                <div><span>Finance</span><h2>Billing</h2></div>
              </div>

              <div class="weekbar">
                <button class="weekbar-step" type="button" data-weekpanel-step="-1" aria-label="Previous year">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <p class="weekbar-range" data-weekpanel-range data-i18n-skip></p>
                <button class="weekbar-now" type="button" data-weekpanel-now-button hidden>This year</button>
                <button class="weekbar-step" type="button" data-weekpanel-step="1" aria-label="Next year">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              </div>

              <?php // The month and what is still owed on it; the rest is in the sheet. ?>
              <table class="weeklist">
                <thead>
                  <tr><th>Month</th><th>Balance</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($billing as $row): ?>
                    <?php
                    $balance = (float) $row['balance_amount'];
                    $billingYear = (string) (int) $row['billing_year'];
                    $monthLabel = parent_billing_month((int) $row['billing_year'], (int) $row['billing_month']);
                    $paymentStatus = (string) $row['payment_status'];
                    ?>
                    <tr class="weekrow" data-weekpanel-row data-week="<?= e($billingYear) ?>"
                        tabindex="0" role="button"
                        <?= $billingYear === date('Y') ? '' : 'hidden' ?>>
                      <td>
                        <?php // "September 2026" rather than "2026-09": a month, not a code. ?>
                        <strong data-i18n-skip><?= e($monthLabel) ?></strong>
                        <?php if ($row['last_payment_date']): ?>
                          <small class="parent-billing-paid-on">
                            <span>Paid on</span> <span data-i18n-skip><?= e(fmt_date((string) $row['last_payment_date'])) ?></span>
                          </small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php // What is still owed is the one figure worth reading twice. ?>
                        <span class="parent-billing-balance <?= $balance > 0 ? 'is-owing' : 'is-clear' ?>" data-i18n-skip><?= e(number_format($balance, 2)) ?></span>
                        <span class="status-pill <?= e(parent_status_class($paymentStatus)) ?>"><?= e(parent_payment_label($paymentStatus)) ?></span>

                        <div class="weeksheet-source" data-weekpanel-detail hidden>
                          <span class="weeksheet-eyebrow">Billing</span>
                          <h3 data-i18n-skip><?= e($monthLabel) ?></h3>
                          <dl class="weeksheet-facts">
                            <div>
                              <dt>Status</dt>
                              <dd><span class="status-pill <?= e(parent_status_class($paymentStatus)) ?>"><?= e(parent_payment_label($paymentStatus)) ?></span></dd>
                            </div>
                            <div>
                              <dt>Expected</dt>
                              <dd data-i18n-skip><?= e(number_format((float) $row['expected_amount'], 2)) ?></dd>
                            </div>
                            <div>
                              <dt>Paid</dt>
                              <dd data-i18n-skip><?= e(number_format((float) $row['paid_amount'], 2)) ?></dd>
                            </div>
                            <div>
                              <dt>Balance</dt>
                              <dd class="parent-billing-balance <?= $balance > 0 ? 'is-owing' : 'is-clear' ?>" data-i18n-skip><?= e(number_format($balance, 2)) ?></dd>
                            </div>
                            <?php if ($row['last_payment_date']): ?>
                              <div>
                                <dt>Paid on</dt>
                                <dd data-i18n-skip><?= e(fmt_date((string) $row['last_payment_date'])) ?></dd>
                              </div>
                            <?php endif; ?>
                          </dl>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="weeklist-none" data-weekpanel-empty>
                    <td colspan="2" class="empty-row">No billing recorded this year.</td>
                  </tr>
                </tbody>
              </table>
            </article>
          </section>

          <?php /*
                 * Nothing here until there is something to answer for: no panel,
                 * no "great work" banner. Only a written warning carries an
                 * expiation, so an oral one never reaches this screen.
                 */ ?>
          <?php if ($parentWarnings !== []): ?>
            <section class="parent-behaviour" id="behaviour-panel">
              <article class="data-panel">
                <div class="panel-heading">
                  <div>
                    <span>Behaviour</span>
                    <h2><?= $writtenWarningCount === 1 ? 'Written warning' : 'Written warnings' ?></h2>
                  </div>
                  <?php if ($childAgeGroup !== null): ?>
                    <?php // The age group decides which expiations are offered, so it is
                          // named here rather than left for a parent to work out. ?>
                    <strong class="record-count">Age group: <span data-i18n-skip data-en="<?= e((string) $childAgeGroup['name_en']) ?>" data-ar="<?= e((string) ($childAgeGroup['name_ar'] ?? '')) ?>"><?= e((string) $childAgeGroup['name_en']) ?></span></strong>
                  <?php endif; ?>
                </div>

                <?php if ($openWarningCount > 0): ?>
                  <p class="parent-behaviour-lead">
                    <?php // One warning, one expiation: each card below is chosen on its own. ?>
                    <span data-i18n-skip><?= e((string) $openWarningCount) ?></span>
                    <span><?= $openWarningCount === 1
                        ? 'of these warnings still needs an expiation. Choose one below.'
                        : 'of these warnings still need an expiation. Choose one for each.' ?></span>
                  </p>
                <?php endif; ?>

                <?php // The same table the panels above are read as: the row says
                      // which warning and where it stands, and opening it carries the
                      // message and the expiation. ?>
                <table class="weeklist">
                  <thead>
                    <tr><th>Warning</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($parentWarnings as $index => $warning): ?>
                      <?php
                      $isOpen = (string) $warning['status'] === 'issued';
                      $warningLabel = (string) ($warning['warning_number'] ?: ($index + 1));
                      $warningDate = fmt_date((string) $warning['warning_date']);
                      ?>
                      <tr class="weekrow<?= $isOpen ? ' weekrow-open' : '' ?>" data-weekpanel-row tabindex="0" role="button">
                        <td>
                          <strong class="parent-warning-label">
                            <span>Warning</span>
                            <span data-i18n-skip><?= e($warningLabel) ?></span>
                          </strong>
                          <small class="weekrow-date" data-i18n-skip><?= e($warningDate) ?></small>
                        </td>
                        <td>
                          <span class="status-pill <?= $isOpen ? 'status-issued' : 'status-assigned' ?>">
                            <?= $isOpen ? 'Expiation needed' : 'Expiation chosen' ?>
                          </span>

                          <div class="weeksheet-source" data-weekpanel-detail hidden>
                            <span class="weeksheet-eyebrow">Written warning</span>
                            <h3>
                              <span>Warning</span>
                              <span data-i18n-skip><?= e($warningLabel) ?></span>
                            </h3>
                            <p class="weeksheet-date" data-i18n-skip><?= e($warningDate) ?></p>

                            <?php // Only the administration's message is shown; the teacher's
                                  // own wording of the incident stays internal to the centre. ?>
                            <p class="weeksheet-note"><?= nl2br(e((string) $warning['parent_message'])) ?></p>

                            <?php if ($isOpen): ?>
                              <?php if ($expiationsByCategory === []): ?>
                                <p class="parent-warning-note">No expiations are available for this age group yet. Please contact the administration.</p>
                              <?php else: ?>
                                <form method="post" class="parent-expiation-form">
                                  <input type="hidden" name="csrf" value="<?= e(app_csrf_token()) ?>">
                                  <input type="hidden" name="action" value="select_expiation">
                                  <input type="hidden" name="warning_id" value="<?= e((string) $warning['id']) ?>">
                                  <label>
                                    <span>Choose an expiation for this warning</span>
                                    <select name="expiation_id" required>
                                      <option value="">Select an expiation…</option>
                                      <?php foreach ($expiationsByCategory as $categoryName => $options): ?>
                                        <optgroup label="<?= e((string) $categoryName) ?>">
                                          <?php foreach ($options as $option): ?>
                                            <option value="<?= e((string) $option['id']) ?>" data-i18n-skip data-en="<?= e((string) $option['title_en']) ?>" data-ar="<?= e((string) ($option['title_ar'] ?? '')) ?>"><?= e((string) $option['title_en']) ?></option>
                                          <?php endforeach; ?>
                                        </optgroup>
                                      <?php endforeach; ?>
                                    </select>
                                  </label>
                                  <button class="primary-action" type="submit">Save expiation</button>
                                </form>
                              <?php endif; ?>
                            <?php elseif ($warning['expiation_title']): ?>
                              <dl class="weeksheet-facts">
                                <div>
                                  <dt>Chosen expiation</dt>
                                  <dd data-i18n-skip data-en="<?= e((string) $warning['expiation_title']) ?>" data-ar="<?= e((string) ($warning['expiation_title_ar'] ?? '')) ?>"><?= e((string) $warning['expiation_title']) ?></dd>
                                </div>
                                <div>
                                  <dt>Category</dt>
                                  <dd data-i18n-skip><?= e((string) $warning['expiation_category']) ?></dd>
                                </div>
                              </dl>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </article>
            </section>
          <?php endif; ?>

        <?php endif; ?>
      </main>
    </div>

    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>

  <?php render_toasts([
      ['type' => 'success', 'text' => $parentMessage ?? ''],
      ['type' => 'error', 'text' => $reviewError ?? ''],
  ]); ?>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/notifications.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/parent.js')) ?>" defer></script>
</body>
</html>
