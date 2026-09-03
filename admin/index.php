<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';
require_once __DIR__ . '/../src/homepage-data.php';

$user = require_roles(['admin', 'manager']);
$isManager = ($user['role'] ?? '') === 'manager';

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

    // The database keeps ISO strings; every date a user reads is day/month/year.
    // Matched on the value rather than the column name, because the list views
    // alias their columns freely (billing_period, last_seen, and so on).
    if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $text) === 1) {
        return e(str_contains($text, ':') ? fmt_datetime($text) : fmt_date($text));
    }

    return e($text);
}

$navigation = admin_navigation_for_user($user);
$view = (string) ($_GET['view'] ?? ($isManager ? '' : 'overview'));
if ($isManager && $view === '') {
    header('Location: ' . khotwa_url('manager/index.php'));
    exit;
}
if (!isset($navigation[$view])) {
    if ($isManager) {
        header('Location: ' . khotwa_url('manager/index.php'));
        exit;
    }
    $view = 'overview';
}

$pageTitle = $navigation[$view]['label'];
$pageDescription = '';
$columns = [];
$rows = [];
$metrics = [];
$recentAttendance = [];
$warningGroups = [];
$scheduleFilters = ['grades' => [], 'school_category' => '', 'schools' => []];
$scheduleFilterOptions = ['grades' => [], 'schools' => []];
$scheduleHasSelection = false;
$scheduleRows = [];
$websiteCollections = [
    'content' => [],
    'slides' => [],
    'statistics' => [],
    'team' => [],
    'gallery' => [],
    'partners' => [],
];
$admissionsBannerVisible = true;
$pendingReviewCount = 0;
$foundingDate = '';
$homepageMetrics = [];
$databaseError = '';
$formError = '';
$message = isset($_GET['created']) ? 'Record added successfully.' : '';
if (isset($_GET['created'], $_GET['with_parent'])) {
    $message = 'Student added, with a parent account created and linked.';
}
$pdo = null;
if (isset($_GET['deleted'])) {
    $deletedCount = max(0, (int) $_GET['deleted']);
    $message = $deletedCount === 1
        ? 'Record deleted successfully.'
        : $deletedCount . ' records deleted successfully.';
}
if (isset($_GET['updated'])) {
    $message = $view === 'website-reviews'
        ? 'Review updated successfully.'
        : 'Warning updated successfully.';
}
if (isset($_GET['banner'])) {
    $message = 'The admissions banner setting was saved.';
}
if (isset($_GET['founding'])) {
    $message = 'The founding date was saved. The years of experience counter now uses it.';
}
if (isset($_GET['created_parent'])) {
    $createdParent = trim((string) $_GET['created_parent']);
    $message = ($createdParent === ''
        ? 'The parent account was created.'
        : 'The parent account for ' . $createdParent . ' was created.')
        . ' Open a student profile to attach them to a child.';
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
            if (!isset($viewTables[$postedView]) || !admin_user_can_access_view($user, $postedView)) {
                throw new RuntimeException('Invalid data section.');
            }

            $action = isset($_POST['delete_id']) ? 'delete' : (string) ($_POST['action'] ?? '');

            if (in_array($action, ['warning_issue', 'warning_dismiss', 'warning_complete', 'warning_reopen'], true)) {
                if ($postedView !== 'warnings') {
                    throw new RuntimeException('Invalid table action.');
                }
                $warningId = (int) ($_POST['warning_id'] ?? 0);
                $adminUserId = (int) ($user['id'] ?? 0);
                if ($warningId < 1) {
                    throw new RuntimeException('Missing warning reference.');
                }

                if ($action === 'warning_issue') {
                    $warningType = (string) ($_POST['warning_type'] ?? '');
                    if (!in_array($warningType, ['oral', 'written'], true)) {
                        throw new RuntimeException('Choose an oral or written warning.');
                    }

                    // This paragraph is the only description the family receives, so it is
                    // required. The teacher's own wording stays internal.
                    $parentMessage = trim((string) ($_POST['parent_message'] ?? ''));
                    if ($parentMessage === '') {
                        throw new RuntimeException('Write the message the parent will read before issuing the warning.');
                    }
                    if (mb_strlen($parentMessage) > 4000) {
                        throw new RuntimeException('Please keep the message to the parent under 4000 characters.');
                    }

                    // Counted, never typed: how many warnings of this type the student already
                    // has in the same year that actually reached the parent.
                    $nextNumber = $pdo->prepare(
                        "SELECT COUNT(*) + 1
                         FROM student_warnings AS counted
                         INNER JOIN student_warnings AS target ON target.id = ?
                         WHERE counted.student_id = target.student_id
                           AND counted.warning_type = ?
                           AND YEAR(counted.warning_date) = YEAR(target.warning_date)
                           AND counted.status IN ('issued', 'assigned')"
                    );
                    $nextNumber->execute([$warningId, $warningType]);
                    $warningNumber = (int) $nextNumber->fetchColumn();

                    // conversation_minutes is deliberately absent here: the teacher sets it
                    // on the flag and the administration must not overwrite it.
                    $statement = $pdo->prepare(
                        "UPDATE student_warnings
                         SET warning_type = ?, warning_number = ?,
                             parent_message = ?, parent_notified = 1,
                             status = 'issued', issued_by_user_id = ?, issued_at = NOW()
                         WHERE id = ? AND status = 'flagged'"
                    );
                    $statement->execute([
                        $warningType,
                        $warningNumber,
                        $parentMessage,
                        $adminUserId,
                        $warningId,
                    ]);
                } elseif ($action === 'warning_dismiss') {
                    // The flag was not worth a warning, so it is removed outright.
                    $statement = $pdo->prepare(
                        "DELETE FROM student_warnings WHERE id = ? AND status = 'flagged'"
                    );
                    $statement->execute([$warningId]);
                } elseif ($action === 'warning_reopen') {
                    // Expiation not actually done: clear the choice and hand it back to
                    // the parent, who can then pick again.
                    $statement = $pdo->prepare(
                        "UPDATE student_warnings
                         SET status = 'issued',
                             expiation_id = NULL,
                             expiation_selected_by_user_id = NULL,
                             expiation_selected_at = NULL
                         WHERE id = ? AND status = 'assigned'"
                    );
                    $statement->execute([$warningId]);
                } else { // warning_complete
                    // The warning is finished with, so the record is removed outright.
                    $statement = $pdo->prepare(
                        "DELETE FROM student_warnings
                         WHERE id = ? AND status IN ('issued', 'assigned')"
                    );
                    $statement->execute([$warningId]);
                }

                header('Location: ' . admin_workspace_url('warnings', ['updated' => 1]));
                exit;
            }

            if ($action === 'toggle_admissions_banner') {
                if ($postedView !== 'website-content') {
                    throw new RuntimeException('Invalid table action.');
                }
                homepage_setting_save(
                    $pdo,
                    'admissions_banner_visible',
                    isset($_POST['admissions_banner_visible']) ? '1' : '0'
                );
                header('Location: ' . admin_workspace_url('website-content', ['banner' => 1]));
                exit;
            }

            if ($action === 'save_founding_date') {
                if ($postedView !== 'website-content') {
                    throw new RuntimeException('Invalid table action.');
                }
                $foundingInput = trim((string) ($_POST['founding_date'] ?? ''));
                $foundingDate = DateTimeImmutable::createFromFormat('Y-m-d', $foundingInput);
                if ($foundingInput === '' || !$foundingDate || $foundingDate->format('Y-m-d') !== $foundingInput) {
                    throw new RuntimeException('Enter the founding date as a valid calendar date.');
                }
                if ($foundingDate > new DateTimeImmutable('today')) {
                    throw new RuntimeException('The founding date cannot be in the future.');
                }

                homepage_setting_save($pdo, 'founding_date', $foundingInput);
                header('Location: ' . admin_workspace_url('website-content', ['founding' => 1]));
                exit;
            }

            if (isset($_POST['review_approve_id']) || isset($_POST['review_reject_id'])) {
                if ($postedView !== 'website-reviews') {
                    throw new RuntimeException('Invalid table action.');
                }
                $approving = isset($_POST['review_approve_id']);
                $reviewId = (int) ($_POST[$approving ? 'review_approve_id' : 'review_reject_id'] ?? 0);
                if ($reviewId < 1) {
                    throw new RuntimeException('Missing review reference.');
                }

                $statement = $pdo->prepare(
                    'UPDATE homepage_reviews
                     SET status = ?, reviewed_by_user_id = ?, reviewed_at = NOW()
                     WHERE id = ?'
                );
                $statement->execute([
                    $approving ? 'approved' : 'rejected',
                    (int) ($user['id'] ?? 0),
                    $reviewId,
                ]);

                header('Location: ' . admin_workspace_url('website-reviews', ['updated' => 1]));
                exit;
            }

            if ($action === 'create_parent_account' && $postedView === 'parent-links') {
                $created = admin_create_parent_account_link(
                    $pdo,
                    0,
                    (array) ($_POST['parent'] ?? [])
                );
                header('Location: ' . admin_workspace_url('parent-links', [
                    'created_parent' => $created['name'],
                ]));
                exit;
            }

            if ($action === 'add') {
                $table = $viewTables[$postedView];
                $uploadResult = admin_prepare_uploads(
                    $table,
                    (array) ($_POST['fields'] ?? []),
                    (array) ($_FILES['uploads'] ?? [])
                );

                // A new student can bring a parent account with it. The block is
                // optional: left empty, only the student is saved. Both rows share one
                // transaction so a rejected parent never leaves a half-entered family,
                // and the typed student values are still on screen to correct.
                $parentInput = $postedView === 'students' ? (array) ($_POST['parent'] ?? []) : [];
                $withParent = implode('', array_map(
                    static fn ($value): string => trim((string) $value),
                    $parentInput
                )) !== '';

                if ($withParent) {
                    $pdo->beginTransaction();
                }
                try {
                    $newRecordId = admin_save_record($pdo, $table, $uploadResult['fields']);
                    if ($withParent) {
                        admin_create_parent_account_link($pdo, $newRecordId, $parentInput);
                        $pdo->commit();
                    }
                    admin_remove_uploaded_files($uploadResult['replaced']);
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    admin_remove_uploaded_files($uploadResult['created']);
                    throw $exception;
                }
                header('Location: ' . admin_workspace_url($postedView, [
                    'created' => 1,
                ] + ($withParent ? ['with_parent' => 1] : [])));
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
            header('Location: ' . admin_workspace_url($postedView, ['deleted' => $deletedCount]));
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
        ];
        $cashMetric = admin_month_cash_metric($pdo);
        $metrics[] = [
            'value' => number_format($cashMetric['collected'], 2),
            'label' => 'Collected · ' . $cashMetric['label'],
            'sub' => 'Net expected this month: ' . number_format($cashMetric['expected'], 2),
            'color' => 'navy',
        ];
        $recentAttendance = $pdo->query(
            "SELECT attendance_date, student_name_en, daily_status, attended_subject_count, missed_subject_count
             FROM " . khotwa_daily_attendance_summary_sql() . "
             ORDER BY attendance_date DESC, student_name_en LIMIT 8"
        )->fetchAll();
        $pageDescription = 'A live view of students, educators, attendance, enrollments, and financial activity.';
    } elseif ($view === 'students') {
        $pageDescription = 'Student profiles and their current academic placement. Double-click a student to open every linked record.';
        $columns = [
            'student_name' => 'Student', 'student_name_ar' => 'Arabic name',
            'gender' => 'Gender', 'nationality_name' => 'Nationality',
            'date_of_birth' => 'Birth date', 'grade_name' => 'Current grade',
            'current_teaching_language' => 'Language', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT students.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    CONCAT(students.first_name_ar, ' ', students.last_name_ar) student_name_ar,
                    students.gender,
                    COALESCE(nationalities.name_en, 'Not set') nationality_name,
                    students.date_of_birth,
                    COALESCE(student_academic_records.grade_name, 'Not assigned') grade_name,
                    students.current_teaching_language, students.status
             FROM students
             LEFT JOIN nationalities ON nationalities.id = students.nationality_id
             LEFT JOIN student_academic_records ON student_academic_records.student_id = students.id
              AND student_academic_records.is_current = 1
             ORDER BY students.last_name_en, students.first_name_en"
        )->fetchAll();
    } elseif ($view === 'teachers') {
        $pageDescription = 'Educator profiles and assigned subjects. Double-click a teacher to open every linked record.';
        $columns = [
            'teacher_name' => 'Teacher', 'email' => 'Email',
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
            'teacher_notes' => 'Teacher notes', 'homework_notes' => 'Homework notes',
        ];
        $rows = $pdo->query(
          "SELECT daily_attendance_id AS id, student_id, attendance_date, student_name_en, check_in_time, check_out_time,
                    daily_status, attended_subject_count, missed_subject_count,
                    COALESCE((
                        SELECT GROUP_CONCAT(CONCAT(subjects.name_en, ': ', student_subject_attendance.notes) SEPARATOR ' | ')
                        FROM student_subject_attendance
                        INNER JOIN subjects ON subjects.id = student_subject_attendance.subject_id
                        WHERE student_subject_attendance.daily_attendance_id = student_daily_attendance_summary.daily_attendance_id
                          AND student_subject_attendance.notes IS NOT NULL
                          AND TRIM(student_subject_attendance.notes) <> ''
                    ), '') AS teacher_notes,
                    COALESCE((
                        SELECT GROUP_CONCAT(CONCAT(subjects.name_en, ': ', student_subject_attendance.homework_note) SEPARATOR ' | ')
                        FROM student_subject_attendance
                        INNER JOIN subjects ON subjects.id = student_subject_attendance.subject_id
                        WHERE student_subject_attendance.daily_attendance_id = student_daily_attendance_summary.daily_attendance_id
                          AND student_subject_attendance.homework_note IS NOT NULL
                          AND TRIM(student_subject_attendance.homework_note) <> ''
                    ), '') AS homework_notes
             FROM " . khotwa_daily_attendance_summary_sql() . " ORDER BY attendance_date DESC, student_name_en"
        )->fetchAll();
    } elseif ($view === 'subjects') {
        $pageDescription = 'Subjects offered by the center and their teaching coverage.';
        $columns = [
            'name_en' => 'Subject', 'name_ar' => 'Arabic name',
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
            'student_name' => 'Student', 'paid_at' => 'Paid at',
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
        $pageDescription = 'Teacher flags → issue an oral/written warning → parent picks an expiation → confirm completion.';
        $warningRows = $pdo->query(
            "SELECT student_warnings.id, student_warnings.warning_date, student_warnings.status,
                    student_warnings.warning_type, student_warnings.warning_number,
                    student_warnings.conversation_minutes, student_warnings.reason,
                    student_warnings.parent_message, student_warnings.notes,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    COALESCE(TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))), 'Center team') teacher_name,
                    expiations.title_en AS expiation_title, expiation_categories.name_en AS expiation_category,
                    age_groups.name_en AS expiation_age_group,
                    student_warnings.expiation_selected_at, student_warnings.issued_at, student_warnings.resolved_at,
                    history.oral_count, history.written_count
             FROM student_warnings
             INNER JOIN students ON students.id = student_warnings.student_id
             LEFT JOIN teachers ON teachers.id = student_warnings.teacher_id
             LEFT JOIN expiations ON expiations.id = student_warnings.expiation_id
             LEFT JOIN expiation_categories ON expiation_categories.id = expiations.category_id
             LEFT JOIN age_groups ON age_groups.id = expiations.age_group_id
             /* What this student already has in the same year that reached the parent. */
             LEFT JOIN (
                SELECT student_id, YEAR(warning_date) AS warning_year,
                       SUM(warning_type = 'oral') AS oral_count,
                       SUM(warning_type = 'written') AS written_count
                FROM student_warnings
                WHERE status IN ('issued', 'assigned')
                GROUP BY student_id, YEAR(warning_date)
             ) AS history
                ON history.student_id = student_warnings.student_id
               AND history.warning_year = YEAR(student_warnings.warning_date)
             WHERE student_warnings.status IN ('flagged', 'issued', 'assigned')
             ORDER BY FIELD(student_warnings.status, 'flagged', 'issued', 'assigned'),
                      student_warnings.warning_date DESC, student_warnings.id DESC"
        )->fetchAll();
        $warningGroups = ['flagged' => [], 'issued' => [], 'assigned' => []];
        foreach ($warningRows as $warningRow) {
            $warningGroups[(string) $warningRow['status']][] = $warningRow;
        }
    } elseif ($view === 'expiations') {
        $pageDescription = 'Corrective expiations parents can assign, organised by category and age group.';
        $columns = [
            'title_en' => 'English title', 'title_ar' => 'Arabic title',
            'category_name' => 'Category', 'age_group_name' => 'Age group',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT expiations.id, expiations.title_en, expiations.title_ar,
                    expiation_categories.name_en AS category_name,
                    CONCAT(age_groups.name_en, ' (', age_groups.min_age, '-', age_groups.max_age, ')') AS age_group_name,
                    expiations.sort_order, expiations.status
             FROM expiations
             INNER JOIN expiation_categories ON expiation_categories.id = expiations.category_id
             INNER JOIN age_groups ON age_groups.id = expiations.age_group_id
             ORDER BY expiation_categories.sort_order, age_groups.sort_order, expiations.sort_order, expiations.id"
        )->fetchAll();
    } elseif ($view === 'expiation-categories') {
        $pageDescription = 'Editable categories (types) of expiations students can choose from.';
        $columns = [
            'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, sort_order, status
             FROM expiation_categories ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'age-groups') {
        $pageDescription = 'Named age groups (with age ranges) used to match expiations to a student automatically.';
        $columns = [
            'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'min_age' => 'Min age', 'max_age' => 'Max age', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, min_age, max_age, sort_order, status
             FROM age_groups ORDER BY sort_order, min_age, id"
        )->fetchAll();
    } elseif ($view === 'nationalities') {
        $pageDescription = 'The nationalities offered as options on a student record.';
        $columns = [
            'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'student_count' => 'Students', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT nationalities.id, nationalities.name_en, nationalities.name_ar,
                    COUNT(students.id) student_count,
                    nationalities.sort_order, nationalities.status
             FROM nationalities
             LEFT JOIN students ON students.nationality_id = nationalities.id
             GROUP BY nationalities.id
             ORDER BY nationalities.sort_order, nationalities.name_en"
        )->fetchAll();
    } elseif ($view === 'schedule-check') {
        $pageDescription = 'Day-school hours and center enrollments side by side, so a session is never booked against a school day.';
        $scheduleFilters = admin_schedule_check_filters($_GET);
        $scheduleFilterOptions = admin_schedule_check_filter_options($pdo);
        $scheduleHasSelection = admin_schedule_check_has_selection($scheduleFilters);
        $scheduleRows = $scheduleHasSelection
            ? admin_schedule_check_rows($pdo, $scheduleFilters)
            : [];

        // The same filtered week, as a file. Sent before any markup, then the
        // request ends: nothing below this point may echo into the download.
        if ($scheduleHasSelection && ($_GET['download'] ?? '') === 'csv') {
            admin_send_schedule_check_csv($scheduleRows);
        }
    } elseif ($view === 'schools') {
        $pageDescription = 'The day schools the students attend. The category decides which timetable the center plans around.';
        $columns = [
            'name' => 'School', 'category' => 'Category',
            'phone_number' => 'Phone', 'email' => 'Email',
            'student_count' => 'Students', 'status' => 'Status',
        ];
        // Only the current academic record counts a student towards a school, so a
        // child who changed school last year is not counted against both.
        $rows = $pdo->query(
            "SELECT schools.id, schools.name, schools.category,
                    schools.phone_number, schools.email,
                    COUNT(DISTINCT CASE WHEN r.is_current = 1 THEN r.student_id END) student_count,
                    schools.status
             FROM schools
             LEFT JOIN student_academic_records r ON r.school_id = schools.id
             GROUP BY schools.id
             ORDER BY schools.name"
        )->fetchAll();
    } elseif ($view === 'parent-links') {
        $pageDescription = 'Relationships between parent portal accounts and student records.';
        $columns = [
            'parent_name' => 'Parent', 'parent_email' => 'Email',
            'warning_count' => 'Warnings',
            'status' => 'Status', 'updated_at' => 'Updated',
        ];
        // The linked student's name is off this list: what the office needs at a
        // glance is which parents have a warning to discuss. Open the row to see
        // which child the link belongs to.
        $rows = $pdo->query(
            "SELECT parent_students.id,
                CONCAT(users.first_name, ' ', COALESCE(users.last_name, '')) AS parent_name,
                users.email AS parent_email,
                (SELECT COUNT(*)
                   FROM student_warnings
                  WHERE student_warnings.student_id = parent_students.student_id
                    AND student_warnings.status NOT IN ('dismissed', 'resolved')
                ) AS warning_count,
                parent_students.status,
                parent_students.updated_at
             FROM parent_students
             INNER JOIN users ON users.id = parent_students.parent_user_id
             INNER JOIN students ON students.id = parent_students.student_id
             ORDER BY warning_count DESC, parent_name"
        )->fetchAll();
    } elseif ($view === 'website-content') {
        $pageDescription = 'One creative workspace for homepage writing, slides, statistics, team members, gallery images, and partner logos.';
        $admissionsBannerVisible = homepage_setting($pdo, 'admissions_banner_visible', '1') === '1';
        $foundingDate = homepage_setting($pdo, 'founding_date');
        $homepageMetrics = homepage_dynamic_metrics($pdo, ['founding_date' => $foundingDate]);
        $pendingReviewCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM homepage_reviews WHERE status = 'pending'"
        )->fetchColumn();
        $columns = [
            'content_type' => 'Type', 'content_key' => 'Content key',
            'title_en' => 'English title', 'title_ar' => 'Arabic title',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $websiteCollections['content'] = $pdo->query(
            "SELECT id, content_type, content_key, title_en, title_ar, sort_order, status
             FROM homepage_content
             ORDER BY FIELD(content_type, 'vision', 'mission', 'step', 'program'),
                      sort_order, id"
        )->fetchAll();
        $rows = $websiteCollections['content'];
        $websiteCollections['slides'] = $pdo->query(
            "SELECT id, title_en, title_ar, image_path, sort_order, status
             FROM homepage_slides ORDER BY sort_order, id"
        )->fetchAll();
        $websiteCollections['statistics'] = $pdo->query(
            "SELECT id, stat_key, stat_value, suffix, label_en, label_ar, sort_order, status
             FROM homepage_statistics ORDER BY sort_order, id"
        )->fetchAll();
        $websiteCollections['team'] = $pdo->query(
            "SELECT
                id,
                TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) AS name_en,
                'Teacher' AS role_en,
                photo_path AS image_path,
                show_on_website,
                status
             FROM teachers
             WHERE status = 'active'
             ORDER BY first_name, last_name"
        )->fetchAll();
        $websiteCollections['gallery'] = $pdo->query(
            "SELECT id, caption_en, caption_ar, layout_style, image_path, sort_order, status
             FROM homepage_gallery_images ORDER BY sort_order, id"
        )->fetchAll();
        $websiteCollections['partners'] = $pdo->query(
            "SELECT id, name_en, name_ar, logo_path, website_url, sort_order, status
             FROM homepage_partners ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-texts') {
        $pageDescription = 'Every fixed sentence on the public website and the terms page:'
            . ' headings, buttons, questions and answers, footer wording. Each row carries'
            . ' the English and the Arabic version of the same text.';
        $columns = [
            'text_key' => 'Text', 'section' => 'Section',
            'value_en' => 'English text', 'value_ar' => 'Arabic text',
            'sort_order' => 'Order',
        ];
        $rows = $pdo->query(
            'SELECT id, text_key, section, value_en, value_ar, sort_order
             FROM homepage_texts ORDER BY sort_order, id'
        )->fetchAll();
    } elseif ($view === 'website-slides') {
        $pageDescription = 'Images and bilingual captions shown as the vision and mission slideshow.';
        $columns = [
            'title_en' => 'English title', 'title_ar' => 'Arabic title',
            'image_path' => 'Image', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, title_en, title_ar, image_path, sort_order, status
             FROM homepage_slides ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-statistics') {
        $pageDescription = 'Homepage numbers, suffixes, and bilingual labels. Learners, educators, family satisfaction,'
            . ' and years of experience are calculated from live data, so their stored number is only a fallback.';
        $columns = [
            'stat_key' => 'Key', 'stat_value' => 'Number',
            'suffix' => 'Suffix', 'label_en' => 'English label',
            'label_ar' => 'Arabic label', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, stat_key, stat_value, suffix, label_en, label_ar, sort_order, status
             FROM homepage_statistics ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-gallery') {
        $pageDescription = 'Uploaded gallery images, bilingual captions, and display layouts.';
        $columns = [
            'caption_en' => 'English caption', 'caption_ar' => 'Arabic caption',
            'layout_style' => 'Layout', 'image_path' => 'Image',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, caption_en, caption_ar, layout_style, image_path, sort_order, status
             FROM homepage_gallery_images ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-partners') {
        $pageDescription = 'Partner names, uploaded logos, website links, and display order.';
        $columns = [
            'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'logo_path' => 'Logo', 'website_url' => 'Website',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, logo_path, website_url, sort_order, status
             FROM homepage_partners ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-reviews') {
        $pageDescription = 'Reviews submitted by parents from their portal. Approved reviews appear on the public homepage.';
        $columns = [
            'display_name' => 'Parent', 'display_name_ar' => 'Arabic name', 'parent_email' => 'Account',
            'rating' => 'Rating', 'review_text' => 'Review', 'created_at' => 'Submitted',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT homepage_reviews.id, homepage_reviews.display_name, homepage_reviews.display_name_ar,
                    COALESCE(users.email, 'Added by the administration') AS parent_email,
                    homepage_reviews.rating, homepage_reviews.review_text,
                    homepage_reviews.created_at, homepage_reviews.sort_order, homepage_reviews.status
             FROM homepage_reviews
             LEFT JOIN users ON users.id = homepage_reviews.parent_user_id
             ORDER BY FIELD(homepage_reviews.status, 'pending', 'approved', 'rejected'),
                      homepage_reviews.sort_order, homepage_reviews.id DESC"
        )->fetchAll();
    } elseif ($view === 'website-contacts') {
        $pageDescription = 'Phone, email, WhatsApp, social media, address, hours, and Google Maps links.';
        $columns = [
            'link_key' => 'Key', 'link_type' => 'Type',
            'value_en' => 'English value', 'value_ar' => 'Arabic value',
            'url' => 'Link', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, link_key, link_type, value_en, value_ar, url, sort_order, status
             FROM homepage_contact_links ORDER BY sort_order, id"
        )->fetchAll();
    }

    // Views that share a page carry a tab strip, with the row count on each tab.
    $workspaceTabs = admin_workspace_tabs($view, $user);
    $workspaceTabCounts = [];
    foreach ($workspaceTabs as $tabView => $tabLabel) {
        // The website studio fronts six collections, so a count of the one table
        // behind it would describe less than the tab opens.
        if ($tabView === 'website-content') {
            continue;
        }
        // Some tabs are a report rather than a table - there is nothing to count.
        if (!isset($viewTables[$tabView])) {
            continue;
        }
        $workspaceTabCounts[$tabView] = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . admin_quote_identifier($viewTables[$tabView])
        )->fetchColumn();
    }
} catch (Throwable $exception) {
    $databaseError = 'The management panel could not read the database. Please confirm that MySQL is running.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0B1C34">
  <title><?= e($pageTitle) ?> | Khotwa <?= $isManager ? 'Management' : 'Administration' ?></title>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= e(khotwa_asset('css/admin.css')) ?>">
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
            <span class="content-kicker"><?= $isManager ? 'Management center' : 'Control center' ?></span>
            <h1><?= e($pageTitle) ?></h1>
            <p><?= e($pageDescription) ?></p>
          </div>
          <div class="live-indicator"><span></span><?= $isManager ? 'Editable live data' : 'Live database' ?></div>
        </section>

        <?php if ($databaseError !== ''): ?>
          <div class="database-alert"><?= e($databaseError) ?></div>
        <?php elseif ($view === 'overview'): ?>
          <section class="metrics-grid" aria-label="Dashboard statistics">
            <?php foreach ($metrics as $metric): ?>
              <article class="metric-card metric-<?= e($metric['color']) ?>">
                <span class="metric-dot"></span><strong><?= e($metric['value']) ?></strong><p><?= e($metric['label']) ?></p><?php if (!empty($metric['sub'])): ?><small class="metric-sub"><?= e($metric['sub']) ?></small><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </section>
          <section class="overview-grid is-single">
            <article class="data-panel overview-attendance">
              <div class="panel-heading">
                <div><span>Latest records</span><h2>Recent attendance</h2></div>
                <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=attendance">View all</a>
              </div>
              <div class="table-scroll">
                <table>
                  <thead><tr><th>Date</th><th>Student</th><th>Status</th><th>Attended</th><th>Missed</th></tr></thead>
                  <tbody>
                    <?php if ($recentAttendance === []): ?>
                      <tr><td class="empty-row" colspan="5">No attendance records yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentAttendance as $row): ?>
                        <tr><td><?= e(fmt_date((string) $row['attendance_date'])) ?></td><td><strong><?= e($row['student_name_en']) ?></strong></td><td><?= render_value('daily_status', $row['daily_status']) ?></td><td><?= e((string) $row['attended_subject_count']) ?></td><td><?= e((string) $row['missed_subject_count']) ?></td></tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </section>
        <?php elseif ($view === 'schedule-check'): ?>
          <?php $scheduleCheckDays = admin_schedule_days(); ?>
          <?php $workspaceTabs = $workspaceTabs ?? []; ?>
          <?php if ($workspaceTabs !== []): ?>
            <?php // Same strip the table views carry, so this page sits in its group
                  // instead of looking like a page reached from nowhere. ?>
            <div class="profile-workspace">
              <?php admin_render_workspace_tabs($workspaceTabs, $view, $workspaceTabCounts ?? []); ?>
              <div class="profile-panels">
          <?php endif; ?>
          <section class="data-panel">
            <div class="panel-heading">
              <div><span>Filters</span><h2>Choose the group</h2></div>
              <p class="schedule-filter-note">Grade, school category and school all narrow the same list of active students.</p>
            </div>
            <form class="schedule-check-filters" method="get" action="<?= e(khotwa_url('admin/index.php')) ?>">
              <input type="hidden" name="view" value="schedule-check">

              <?php // Three controls of very different heights, so they hang from
                    // a shared top edge and the buttons get a row of their own. ?>
              <div class="schedule-check-fields">
                <fieldset class="admin-field filter-checklist">
                  <div class="filter-field-head">
                    <span>Grade</span>
                    <em class="filter-count" data-checklist-count hidden></em>
                  </div>
                  <?php // "All" is a real checkbox rather than an empty option: it has to
                        // clear the others, and it has to read as chosen when none are. ?>
                  <div class="filter-checklist-box" data-checklist>
                    <label class="filter-checklist-all">
                      <input type="checkbox" data-checklist-all<?= $scheduleFilters['grades'] === [] ? ' checked' : '' ?>>
                      <span>All grades</span>
                    </label>
                    <?php foreach ($scheduleFilterOptions['grades'] as $gradeName): ?>
                      <label>
                        <input type="checkbox" name="grade[]" value="<?= e((string) $gradeName) ?>"<?= in_array((string) $gradeName, $scheduleFilters['grades'], true) ? ' checked' : '' ?>>
                        <span><?= e((string) $gradeName) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <fieldset class="admin-field">
                  <div class="filter-field-head"><span>School category</span></div>
                  <div class="filter-radio-row">
                    <label>
                      <input type="radio" name="school_category" value=""<?= $scheduleFilters['school_category'] === '' ? ' checked' : '' ?>>
                      <span>Both</span>
                    </label>
                    <label>
                      <input type="radio" name="school_category" value="private"<?= $scheduleFilters['school_category'] === 'private' ? ' checked' : '' ?>>
                      <span>Private</span>
                    </label>
                    <label>
                      <input type="radio" name="school_category" value="public"<?= $scheduleFilters['school_category'] === 'public' ? ' checked' : '' ?>>
                      <span>Public</span>
                    </label>
                  </div>
                </fieldset>

                <fieldset class="admin-field filter-checklist">
                  <div class="filter-field-head">
                    <span>School</span>
                    <em class="filter-count" data-checklist-count hidden></em>
                  </div>
                  <div class="filter-checklist-box" data-checklist>
                    <label class="filter-checklist-all">
                      <input type="checkbox" data-checklist-all<?= $scheduleFilters['schools'] === [] ? ' checked' : '' ?>>
                      <span>All schools</span>
                    </label>
                    <?php foreach ($scheduleFilterOptions['schools'] as $school): ?>
                      <label>
                        <input type="checkbox" name="school[]" value="<?= e((string) $school['id']) ?>"<?= in_array((string) $school['id'], $scheduleFilters['schools'], true) ? ' checked' : '' ?>>
                        <span><?= e($school['name']) ?> <small><?= e(ucfirst((string) $school['category'])) ?></small></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>
              </div>

              <div class="schedule-check-actions">
                <button class="primary-action" type="submit">Apply filters</button>
                <a class="secondary-action" href="<?= e(khotwa_url('admin/index.php')) ?>?view=schedule-check">Reset</a>
              </div>
            </form>
          </section>

          <?php if (!$scheduleHasSelection): ?>
            <section class="data-panel">
              <div class="schedule-check-empty">
                <span class="schedule-check-empty-mark" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="4"></rect><path d="M8 3v4M16 3v4M3 11h18"></path></svg>
                </span>
                <strong>Choose who to compare.</strong>
                <span>Pick one or more grades, a school category, or one or more schools, then apply.
                The shared availability is only meaningful for a group you have chosen.</span>
              </div>
            </section>
          <?php else: ?>
          <?php if ($scheduleRows !== []): ?>
            <?php
            $availability = admin_schedule_availability($scheduleRows);
            $scheduleWindow = admin_schedule_window();
            $slotHeight = 26;
            ?>
            <section class="data-panel">
              <div class="panel-heading">
                <div>
                  <span>Everyone free</span>
                  <h2>Shared availability</h2>
                </div>
                <p class="schedule-availability-legend">
                  <span class="legend-item"><i class="is-free"></i>All <?= e((string) $availability['total']) ?> free</span>
                  <span class="legend-item"><i class="is-some"></i>Some at school</span>
                  <span class="legend-item"><i class="is-all"></i>All at school</span>
                </p>
              </div>

              <div class="schedule-grid schedule-availability" style="--schedule-slot: <?= e((string) $slotHeight) ?>px; --schedule-slots: <?= e((string) count($availability['slots'])) ?>;">
                <div class="schedule-head">
                  <span></span>
                  <?php foreach ($availability['days'] as $day): ?>
                    <span class="schedule-head-day"><b><?= e(ucfirst(substr($day, 0, 3))) ?></b></span>
                  <?php endforeach; ?>
                </div>
                <div class="schedule-body">
                  <div class="schedule-axis">
                    <?php foreach ($availability['slots'] as $index): ?>
                      <?php $minutes = $scheduleWindow['start'] + ($index * $scheduleWindow['step']); ?>
                      <?php if ($minutes % 60 === 0): ?>
                        <span class="schedule-axis-label" style="top: <?= e((string) ($index * $slotHeight)) ?>px;">
                          <?= e(substr(admin_schedule_time_string($minutes), 0, 5)) ?>
                        </span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                  <?php foreach ($availability['days'] as $day): ?>
                    <div class="schedule-day">
                      <?php foreach ($availability['slots'] as $index): ?>
                        <?php
                        $busyCount = $availability['busy'][$day][$index];
                        $slotClass = $busyCount === 0
                            ? 'is-free'
                            : ($busyCount >= $availability['total'] ? 'is-all' : 'is-some');
                        $minutes = $scheduleWindow['start'] + ($index * $scheduleWindow['step']);
                        $slotTitle = ucfirst($day) . ' ' . substr(admin_schedule_time_string($minutes), 0, 5)
                            . ' · ' . ($busyCount === 0
                                ? 'everyone free'
                                : $busyCount . ' of ' . $availability['total'] . ' at school');
                        ?>
                        <span
                          class="schedule-slot <?= e($slotClass) ?><?= $minutes % 60 === 0 ? ' is-hour' : '' ?>"
                          title="<?= e($slotTitle) ?>"
                        ></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <ul class="schedule-free-summary">
                <?php foreach ($availability['days'] as $day): ?>
                  <li>
                    <strong><?= e(ucfirst($day)) ?></strong>
                    <?php if ($availability['free'][$day] === []): ?>
                      <span class="empty-value">No window with everyone free</span>
                    <?php else: ?>
                      <span class="schedule-free-windows">
                        <?php foreach ($availability['free'][$day] as $run): ?>
                          <span class="schedule-free-window"><?= e(admin_schedule_window_label($run)) ?></span>
                        <?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endif; ?>

          <section class="data-panel">
            <div class="panel-heading">
              <div>
                <span>Matching students</span>
                <h2><?= e((string) count($scheduleRows)) ?> found</h2>
              </div>
              <?php if ($scheduleRows !== []): ?>
                <?php // The download repeats whatever is filtered on screen. ?>
                <a class="secondary-action" href="<?= e(khotwa_url('admin/index.php')) ?>?<?= e(http_build_query(
                    ['view' => 'schedule-check', 'download' => 'csv'] + admin_schedule_check_query($scheduleFilters)
                )) ?>">Download CSV</a>
              <?php endif; ?>
            </div>
            <div class="table-scroll">
              <table class="schedule-check-table">
                <thead>
                  <tr>
                    <th>Student</th><th>Age</th><th>Grade</th><th>School</th>
                    <?php foreach ($scheduleCheckDays as $day): ?>
                      <th><?= e(ucfirst(substr($day, 0, 3))) ?></th>
                    <?php endforeach; ?>
                    <th>Schedule</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($scheduleRows === []): ?>
                    <tr><td class="empty-row" colspan="<?= e((string) (count($scheduleCheckDays) + 5)) ?>">No active students match these filters.</td></tr>
                  <?php else: ?>
                    <?php foreach ($scheduleRows as $scheduleRow): ?>
                      <tr>
                        <td><strong><?= e((string) $scheduleRow['student_name']) ?></strong></td>
                        <td><?= e((string) $scheduleRow['age']) ?></td>
                        <td><?= render_value('grade_name', $scheduleRow['grade_name']) ?></td>
                        <td>
                          <?php if ($scheduleRow['school_name'] === null): ?>
                            <span class="empty-value">—</span>
                          <?php else: ?>
                            <?= e((string) $scheduleRow['school_name']) ?>
                            <small class="table-cell-detail"><?= e(ucfirst((string) $scheduleRow['school_category'])) ?></small>
                          <?php endif; ?>
                        </td>
                        <?php foreach ($scheduleCheckDays as $day): ?>
                          <td>
                            <?php $slots = $scheduleRow['schedule'][$day] ?? []; ?>
                            <?php if ($slots === []): ?>
                              <span class="empty-value">—</span>
                            <?php else: ?>
                              <?php foreach ($slots as $slot): ?>
                                <small class="schedule-check-slot"><?= e(admin_schedule_slot_label($slot)) ?></small>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </td>
                        <?php endforeach; ?>
                        <td>
                          <a class="secondary-action" href="<?= e(khotwa_url('admin/person.php')) ?>?type=student&amp;id=<?= e((string) $scheduleRow['id']) ?>#panel-student_school_schedule">
                            <?= $scheduleRow['schedule'] === [] ? 'Set' : 'Edit' ?>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
          <?php endif; // schedule-check has a selection ?>
          <?php if ($workspaceTabs !== []): ?>
              </div>
            </div>
          <?php endif; ?>
        <?php else: ?>

          <?php if ($isAdding && $view !== 'parent-links'): ?>
            <section class="data-panel record-editor">
              <div class="panel-heading">
                <div><span>New record</span><h2>Add <?= e($pageTitle) ?></h2></div>
                <a href="<?= e(admin_workspace_url($view)) ?>">Cancel</a>
              </div>
              <form method="post" enctype="multipart/form-data">
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
                <?php if ($view === 'students'): ?>
                  <?php $newParent = (array) ($_POST['parent'] ?? []); ?>
                  <fieldset class="new-student-parent">
                    <legend>Parent account <span>optional</span></legend>
                    <p class="admin-field-help">Create the parent now and link them to this student, or leave this blank and add a parent later from the student profile.</p>
                    <div class="record-form-grid">
                      <label class="admin-field">
                        <span>Parent first name</span>
                        <input type="text" name="parent[first_name]" maxlength="100" value="<?= e((string) ($newParent['first_name'] ?? '')) ?>">
                      </label>
                      <label class="admin-field">
                        <span>Parent last name</span>
                        <input type="text" name="parent[last_name]" maxlength="100" value="<?= e((string) ($newParent['last_name'] ?? '')) ?>">
                      </label>
                      <label class="admin-field">
                        <span>Parent email</span>
                        <input type="email" name="parent[email]" maxlength="150" autocomplete="off" value="<?= e((string) ($newParent['email'] ?? '')) ?>">
                      </label>
                      <label class="admin-field">
                        <span>Temporary password</span>
                        <input type="text" name="parent[password]" maxlength="72" autocomplete="off">
                      </label>
                    </div>
                  </fieldset>
                <?php endif; ?>

                <div class="record-form-actions">
                  <button class="primary-action" type="submit">Save record</button>
                  <a class="secondary-action" href="<?= e(admin_workspace_url($view)) ?>">Cancel</a>
                </div>
              </form>
            </section>
          <?php endif; ?>

          <?php if ($view === 'parent-links' && $isAdding): ?>
            <?php $submitted = (array) ($_POST['parent'] ?? []); ?>
            <section class="data-panel record-editor">
              <div class="panel-heading">
                <div>
                  <span>New parent</span>
                  <h2>Create a parent account</h2>
                </div>
                <a href="<?= e(admin_workspace_url('parent-links')) ?>">Cancel</a>
              </div>
              <form class="new-parent-form" method="post">
                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="create_parent_account">
                <input type="hidden" name="view" value="parent-links">
                <p class="admin-field-help">The parent signs in with this email and is asked to choose their own password. Open a student profile to attach them to a child.</p>
                <div class="record-form-grid">
                  <label class="admin-field">
                    <span>First name</span>
                    <input type="text" name="parent[first_name]" maxlength="100"
                           value="<?= e((string) ($submitted['first_name'] ?? '')) ?>" required>
                  </label>
                  <label class="admin-field">
                    <span>Last name</span>
                    <input type="text" name="parent[last_name]" maxlength="100"
                           value="<?= e((string) ($submitted['last_name'] ?? '')) ?>">
                  </label>
                  <label class="admin-field">
                    <span>Email</span>
                    <input type="email" name="parent[email]" maxlength="150" autocomplete="off"
                           value="<?= e((string) ($submitted['email'] ?? '')) ?>" required>
                  </label>
                  <label class="admin-field">
                    <span>Temporary password</span>
                    <input type="text" name="parent[password]" minlength="8" maxlength="72" autocomplete="off" required>
                  </label>
                </div>
                <div class="record-form-actions">
                  <button class="primary-action" type="submit">Create parent account</button>
                  <a class="secondary-action" href="<?= e(admin_workspace_url('parent-links')) ?>">Cancel</a>
                </div>
              </form>
            </section>
          <?php endif; ?>

          <?php if ($view === 'warnings'): ?>
            <?php
            $warningStatusMeta = [
                'flagged' => ['label' => 'Teacher flags (admin only)', 'hint' => 'Raised by a teacher. Not visible to parents until you issue a warning.'],
                'issued' => ['label' => 'Issued — waiting for parent', 'hint' => 'Visible to the parent. Parent can now choose an expiation.'],
                'assigned' => ['label' => 'Expiation chosen by parent', 'hint' => 'Parent selected an expiation. Remove it once the student completes it.'],
            ];
            ?>
            <?php
            // One stage at a time, in the same tabbed card as the student profile, so a
            // stage gets the full width instead of a narrow column.
            $activeStage = isset($warningStatusMeta[(string) ($_GET['stage'] ?? '')])
                ? (string) $_GET['stage']
                : 'flagged';
            ?>
            <div class="profile-workspace">
            <nav class="profile-tabs" role="tablist" aria-label="Warning stages">
              <?php foreach ($warningStatusMeta as $stageKey => $stageMeta): ?>
                <button
                  class="profile-tab<?= $stageKey === $activeStage ? ' is-active' : '' ?>"
                  type="button"
                  role="tab"
                  aria-selected="<?= $stageKey === $activeStage ? 'true' : 'false' ?>"
                  aria-controls="panel-<?= e($stageKey) ?>"
                  data-profile-tab="<?= e($stageKey) ?>"
                >
                  <span><?= e(ucfirst($stageKey)) ?></span>
                  <i><?= e((string) count($warningGroups[$stageKey] ?? [])) ?></i>
                </button>
              <?php endforeach; ?>
            </nav>
            <div class="profile-panels">
              <?php foreach ($warningStatusMeta as $statusKey => $meta): ?>
                <?php $groupRows = $warningGroups[$statusKey] ?? []; ?>
                <article
                  class="data-panel warning-column warning-column-<?= e($statusKey) ?>"
                  id="panel-<?= e($statusKey) ?>"
                  role="tabpanel"
                  data-profile-panel="<?= e($statusKey) ?>"
                  <?= $statusKey === $activeStage ? '' : 'hidden' ?>
                >
                  <div class="panel-heading">
                    <div>
                      <span><?= e($meta['label']) ?> · <?= e((string) count($groupRows)) ?></span>
                      <h2><?= e(ucfirst($statusKey)) ?></h2>
                    </div>
                  </div>
                  <p class="warning-column-hint"><?= e($meta['hint']) ?></p>
                  <?php if ($groupRows === []): ?>
                    <p class="empty-value">Nothing here.</p>
                  <?php else: ?>
                    <div class="warning-tiles">
                    <?php foreach ($groupRows as $warning): ?>
                      <div class="warning-card">
                        <div class="warning-card-top">
                          <strong><?= e((string) $warning['student_name']) ?></strong>
                          <small><?= e(fmt_date((string) $warning['warning_date'])) ?></small>
                        </div>
                        <p class="warning-reason"><?= e((string) $warning['reason']) ?></p>
                        <small class="warning-meta">Flagged by <?= e((string) $warning['teacher_name']) ?></small>
                        <?php if ($warning['warning_type']): ?>
                          <small class="warning-meta"><?= e(ucfirst((string) $warning['warning_type'])) ?> warning<?= $warning['warning_number'] ? ' #' . e((string) $warning['warning_number']) : '' ?></small>
                        <?php endif; ?>
                        <?php if ($warning['expiation_title']): ?>
                          <small class="warning-meta">Expiation: <?= e((string) $warning['expiation_title']) ?> (<?= e((string) $warning['expiation_category']) ?> · <?= e((string) $warning['expiation_age_group']) ?>)</small>
                        <?php endif; ?>

                        <?php if ($statusKey === 'flagged'): ?>
                          <?php
                          $oralSoFar = (int) ($warning['oral_count'] ?? 0);
                          $writtenSoFar = (int) ($warning['written_count'] ?? 0);
                          ?>
                          <div class="warning-history">
                            <span>Oral <b><?= e((string) $oralSoFar) ?></b></span>
                            <span>Written <b><?= e((string) $writtenSoFar) ?></b></span>
                            <small>already issued this year &middot; this one becomes oral #<?= e((string) ($oralSoFar + 1)) ?> or written #<?= e((string) ($writtenSoFar + 1)) ?></small>
                          </div>
                          <form method="post" class="warning-action-form">
                            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                            <input type="hidden" name="view" value="warnings">
                            <input type="hidden" name="warning_id" value="<?= e((string) $warning['id']) ?>">
                            <div class="warning-type-choice">
                              <label><input type="radio" name="warning_type" value="oral" checked> Oral</label>
                              <label><input type="radio" name="warning_type" value="written"> Written</label>
                            </div>
                            <?php // No warning number field: it is counted on submit. No talk
                                  // minutes either: the teacher records those on the flag. ?>
                            <textarea
                              name="parent_message"
                              rows="4"
                              maxlength="4000"
                              placeholder="Message to the parent — explain what happened and what was agreed. This is the only text the family sees."
                              required
                            ></textarea>
                            <div class="warning-card-actions">
                              <button class="primary-action" type="submit" name="action" value="warning_issue">Issue warning</button>
                              <button class="secondary-action" type="submit" name="action" value="warning_dismiss">Dismiss</button>
                            </div>
                          </form>
                        <?php elseif ($statusKey === 'issued' || $statusKey === 'assigned'): ?>
                          <form method="post" class="warning-action-form">
                            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                            <input type="hidden" name="view" value="warnings">
                            <input type="hidden" name="warning_id" value="<?= e((string) $warning['id']) ?>">
                            <div class="warning-card-actions">
                              <?php // Completing deletes the record, so it asks first. ?>
                              <button
                                class="primary-action"
                                type="submit"
                                name="action"
                                value="warning_complete"
                                data-confirm="The student completed this. Remove the warning permanently?"
                              >Mark completed &amp; remove</button>
                              <?php if ($statusKey === 'assigned'): ?>
                                <?php // Not actually done: hand it back so the parent picks again. ?>
                                <button class="secondary-action" type="submit" name="action" value="warning_reopen">Not done — back to issued</button>
                              <?php endif; ?>
                            </div>
                          </form>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
            </div>
          <?php elseif ($view === 'website-content'): ?>
            <?php admin_render_workspace_tabs($workspaceTabs ?? [], $view, $workspaceTabCounts ?? [], true); ?>
            <?php
            $workspaceSections = [
                [
                    'id' => 'page-content',
                    'view' => 'website-content',
                    'number' => '01',
                    'eyebrow' => 'Words',
                    'title' => 'Page content',
                    'description' => 'Vision, mission, learning steps, and program copy in English and Arabic.',
                    'items' => $websiteCollections['content'],
                    'tone' => 'navy',
                ],
                [
                    'id' => 'vision-slides',
                    'view' => 'website-slides',
                    'number' => '02',
                    'eyebrow' => 'Motion',
                    'title' => 'Vision slides',
                    'description' => 'The image sequence and captions displayed below the vision and mission.',
                    'items' => $websiteCollections['slides'],
                    'tone' => 'orange',
                ],
                [
                    'id' => 'statistics',
                    'view' => 'website-statistics',
                    'number' => '03',
                    'eyebrow' => 'Impact',
                    'title' => 'Statistics',
                    'description' => 'The animated numbers that communicate the center’s reach and experience.',
                    'items' => $websiteCollections['statistics'],
                    'tone' => 'green',
                ],
                [
                    'id' => 'team-members',
                    'view' => 'website-team',
                    'number' => '04',
                    'eyebrow' => 'People',
                    'title' => 'Team members',
                    'description' => 'Every active teacher appears on the homepage automatically. Open a teacher to change their photo or take them off the website.',
                    'items' => $websiteCollections['team'],
                    'tone' => 'violet',
                ],
                [
                    'id' => 'gallery-images',
                    'view' => 'website-gallery',
                    'number' => '05',
                    'eyebrow' => 'Moments',
                    'title' => 'Gallery images',
                    'description' => 'Classroom moments, activities, and visual stories arranged as a mosaic.',
                    'items' => $websiteCollections['gallery'],
                    'tone' => 'pink',
                ],
                [
                    'id' => 'partner-logos',
                    'view' => 'website-partners',
                    'number' => '06',
                    'eyebrow' => 'Network',
                    'title' => 'Partner logos',
                    'description' => 'Organizations and collaborators presented in the partners strip.',
                    'items' => $websiteCollections['partners'],
                    'tone' => 'violet',
                ],
            ];
            ?>
            <section class="website-studio" aria-label="Website content workspace">
              <div class="website-studio-hero">
                <div>
                  <span>Homepage studio</span>
                  <h2>Shape the website from one place.</h2>
                  <p>Each collection has its own visual language, while every edit stays connected to the same live homepage.</p>
                </div>
                <div class="website-studio-total">
                  <strong><?= e((string) array_sum(array_map(
                      static fn (array $section): int => count($section['items']),
                      $workspaceSections
                  ))) ?></strong>
                  <span>stored items</span>
                </div>
              </div>

              <div class="studio-controls">
                <form class="studio-switch-card" method="post">
                  <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                  <input type="hidden" name="view" value="website-content">
                  <input type="hidden" name="action" value="toggle_admissions_banner">
                  <div class="studio-switch-copy">
                    <small>Homepage banner</small>
                    <h3>“Admissions are now open”</h3>
                    <p>Show or hide the announcement badge above the homepage headline.</p>
                  </div>
                  <label class="switch-control">
                    <input
                      type="checkbox"
                      name="admissions_banner_visible"
                      value="1"
                      <?= $admissionsBannerVisible ? 'checked' : '' ?>
                    >
                    <span class="switch-track"><i></i></span>
                    <span class="switch-label"><?= $admissionsBannerVisible ? 'Open' : 'Closed' ?></span>
                  </label>
                  <button class="primary-action" type="submit">Save banner</button>
                </form>

                <form class="studio-switch-card" method="post">
                  <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                  <input type="hidden" name="view" value="website-content">
                  <input type="hidden" name="action" value="save_founding_date">
                  <div class="studio-switch-copy">
                    <small>Founding date</small>
                    <h3>
                      <?= e((string) ($homepageMetrics['years_of_experience'] ?? 0)) ?>
                      years of experience
                    </h3>
                    <p>The homepage counter is calculated from this date, so it never needs editing again.</p>
                  </div>
                  <label class="studio-date-field">
                    <span>Opened on</span>
                    <input type="date" name="founding_date" value="<?= e($foundingDate) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                  </label>
                  <button class="primary-action" type="submit">Save date</button>
                </form>

                <a class="studio-switch-card studio-review-card" href="<?= e(khotwa_url('admin/index.php')) ?>?view=website-reviews">
                  <div class="studio-switch-copy">
                    <small>Parent reviews</small>
                    <h3><?= e((string) $pendingReviewCount) ?> waiting for approval</h3>
                    <p>Approve the reviews families submit from the parent portal to publish them on the homepage.</p>
                  </div>
                  <span class="studio-review-action">Open reviews</span>
                </a>
              </div>

              <nav class="website-studio-nav" aria-label="Website content sections">
                <?php foreach ($workspaceSections as $section): ?>
                  <a class="studio-nav-<?= e($section['tone']) ?>" href="#<?= e($section['id']) ?>">
                    <span><?= e($section['number']) ?></span>
                    <strong><?= e($section['title']) ?></strong>
                    <small><?= e((string) count($section['items'])) ?> items</small>
                  </a>
                <?php endforeach; ?>
              </nav>

              <?php foreach ($workspaceSections as $section): ?>
                <section
                  class="website-collection collection-<?= e($section['tone']) ?>"
                  id="<?= e($section['id']) ?>"
                >
                  <header class="website-collection-heading">
                    <div class="collection-title">
                      <span class="collection-number"><?= e($section['number']) ?></span>
                      <div>
                        <small><?= e($section['eyebrow']) ?></small>
                        <h2><?= e($section['title']) ?></h2>
                        <p><?= e($section['description']) ?></p>
                      </div>
                    </div>
                    <div class="collection-actions">
                      <span><?= e((string) count($section['items'])) ?> records</span>
                      <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=<?= e($section['view']) ?>&new=1">
                        <?= admin_icon($section['view']) ?> Add new
                      </a>
                    </div>
                  </header>

                  <?php if ($section['items'] === []): ?>
                    <div class="website-collection-empty">No records yet. Add the first one to this collection.</div>
                  <?php elseif ($section['view'] === 'website-content'): ?>
                    <div class="content-block-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <article class="content-block-card">
                          <div class="content-block-meta">
                            <span><?= e(strtoupper((string) $item['content_type'])) ?></span>
                            <?= render_value('status', $item['status']) ?>
                          </div>
                          <strong><?= e((string) ($item['title_en'] ?: $item['content_key'])) ?></strong>
                          <p lang="ar" dir="rtl"><?= e((string) ($item['title_ar'] ?: 'No Arabic title')) ?></p>
                          <footer>
                            <small>Order <?= e((string) $item['sort_order']) ?></small>
                            <a href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-content&id=<?= e((string) $item['id']) ?>">Edit content</a>
                          </footer>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($section['view'] === 'website-slides'): ?>
                    <div class="slide-card-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <article class="slide-preview-card">
                          <img src="<?= e(khotwa_url((string) $item['image_path'])) ?>" alt="">
                          <div>
                            <span><?= render_value('status', $item['status']) ?></span>
                            <strong><?= e((string) ($item['title_en'] ?: 'Untitled slide')) ?></strong>
                            <p lang="ar" dir="rtl"><?= e((string) ($item['title_ar'] ?: '')) ?></p>
                            <a href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-slides&id=<?= e((string) $item['id']) ?>">Edit slide</a>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($section['view'] === 'website-statistics'): ?>
                    <div class="stat-card-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <?php
                        $statisticKey = (string) $item['stat_key'];
                        $isDynamicStatistic = in_array($statisticKey, homepage_dynamic_statistic_keys(), true);
                        $liveValue = match ($statisticKey) {
                            'learners_supported' => $homepageMetrics['learners_supported'] ?? null,
                            'expert_educators' => $homepageMetrics['expert_educators'] ?? null,
                            'family_satisfaction' => $homepageMetrics['family_satisfaction'] ?? null,
                            'years_experience' => $homepageMetrics['years_of_experience'] ?? null,
                            default => null,
                        };
                        ?>
                        <a class="stat-preview-card" href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-statistics&id=<?= e((string) $item['id']) ?>">
                          <small><?= e($statisticKey) ?></small>
                          <strong>
                            <?= e((string) ($liveValue ?? $item['stat_value'])) ?><sup><?= e((string) $item['suffix']) ?></sup>
                          </strong>
                          <p><?= e((string) $item['label_en']) ?></p>
                          <span lang="ar" dir="rtl"><?= e((string) $item['label_ar']) ?></span>
                          <?php if ($isDynamicStatistic): ?>
                            <b class="stat-auto-badge">
                              <?= $liveValue === null ? 'Auto · stored value in use' : 'Calculated automatically' ?>
                            </b>
                          <?php endif; ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($section['view'] === 'website-team'): ?>
                    <div class="partner-preview-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <a class="partner-preview-card" href="<?= e(khotwa_url('admin/record.php')) ?>?view=teachers&id=<?= e((string) $item['id']) ?>">
                          <?php if ($item['image_path']): ?>
                            <img src="<?= e(khotwa_url((string) $item['image_path'])) ?>" alt="">
                          <?php else: ?>
                            <span><?= e(strtoupper(substr((string) $item['name_en'], 0, 2))) ?></span>
                          <?php endif; ?>
                          <strong><?= e((string) $item['name_en']) ?></strong>
                          <small><?= (int) $item['show_on_website'] === 1 ? 'On the website' : 'Hidden from website' ?></small>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($section['view'] === 'website-gallery'): ?>
                    <div class="gallery-preview-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <a
                          class="gallery-preview-card gallery-layout-<?= e((string) $item['layout_style']) ?>"
                          href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-gallery&id=<?= e((string) $item['id']) ?>"
                        >
                          <img src="<?= e(khotwa_url((string) $item['image_path'])) ?>" alt="">
                          <span><strong><?= e((string) $item['caption_en']) ?></strong><small>Edit image</small></span>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($section['view'] === 'website-partners'): ?>
                    <div class="partner-preview-grid">
                      <?php foreach ($section['items'] as $item): ?>
                        <a class="partner-preview-card" href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-partners&id=<?= e((string) $item['id']) ?>">
                          <?php if ($item['logo_path']): ?>
                            <img src="<?= e(khotwa_url((string) $item['logo_path'])) ?>" alt="">
                          <?php else: ?>
                            <span><?= e(strtoupper(substr((string) $item['name_en'], 0, 2))) ?></span>
                          <?php endif; ?>
                          <strong><?= e((string) $item['name_en']) ?></strong>
                          <small lang="ar" dir="rtl"><?= e((string) $item['name_ar']) ?></small>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </section>
              <?php endforeach; ?>
            </section>
          <?php else: ?>
          <?php
          // One page of rows only. $rows itself stays whole above this point so the
          // metric tiles and the record count keep describing the full table.
          $pager = admin_paginate_rows(
              $rows,
              (string) ($_GET['q'] ?? ''),
              max(1, (int) ($_GET['page'] ?? 1))
          );
          $pageRows = $pager['rows'];
          $pagerLink = static function (int $target) use ($view, $pager): string {
              $query = ['view' => $view, 'page' => $target];
              if ($pager['query'] !== '') {
                  $query['q'] = $pager['query'];
              }

              return khotwa_url('admin/index.php') . '?' . http_build_query($query);
          };
          $workspaceTabs = $workspaceTabs ?? [];
          ?>
          <?php if ($workspaceTabs !== []): ?>
            <?php // Sibling tables of one subject, reached the way a profile's sections
                  // are: a strip joined to the top of the card holding the table. ?>
            <div class="profile-workspace">
              <?php admin_render_workspace_tabs($workspaceTabs, $view, $workspaceTabCounts); ?>
              <div class="profile-panels">
          <?php endif; ?>
          <section class="data-panel">
            <form method="post" data-table-actions>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <div class="panel-heading table-panel-heading">
                <div><span>Database table</span><h2><?= e($pageTitle) ?></h2></div>
                <label class="table-search">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                  <input type="search" placeholder="Search this table" value="<?= e($pager['query']) ?>"
                         data-table-search
                         data-search-url="<?= e(khotwa_url('admin/index.php')) ?>?view=<?= e($view) ?>">
                </label>
                <div class="table-heading-actions">
                  <strong class="record-count">
                    <?php if ($pager['query'] !== ''): ?>
                      <?= e((string) $pager['matched']) ?> of <?= e((string) $pager['total']) ?> records
                    <?php elseif ($pager['pages'] > 1): ?>
                      <?= e((string) $pager['first']) ?>&ndash;<?= e((string) $pager['last']) ?> of <?= e((string) $pager['total']) ?> records
                    <?php else: ?>
                      <?= e((string) $pager['total']) ?> records
                    <?php endif; ?>
                  </strong>
                  <button class="bulk-delete-button" type="submit" name="action" value="bulk_delete" disabled data-bulk-delete>
                    Delete selected
                  </button>
                  <?php if ($view === 'attendance'): ?>
                    <button class="secondary-action qr-scan-button" type="button" data-qr-scan-open>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V4h3M21 7V4h-3M3 17v3h3M21 17v3h-3"/><path d="M7 12h10"/></svg>
                      Scan
                    </button>
                  <?php endif; ?>
                  <a class="add-record-button" href="<?= e(khotwa_url('admin/index.php')) ?>?view=<?= e($view) ?>&new=1">
                    <?php if ($view === 'parent-links'): ?>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 21v-2a5 5 0 0 1 5-5h2"/><circle cx="10" cy="8" r="3"/><path d="M18 14v6M15 17h6"/></svg>
                      New parent account
                    <?php else: ?>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                      Add record
                    <?php endif; ?>
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
                    <?php if ($pageRows === []): ?>
                      <tr><td class="empty-row" colspan="<?= e((string) (count($columns) + 2)) ?>"><?= $pager['query'] === '' ? 'No records found.' : 'No records match this search.' ?></td></tr>
                    <?php else: ?>
                      <?php foreach ($pageRows as $row): ?>
                        <?php
                        if ($view === 'students') {
                            $detailUrl = khotwa_url('admin/person.php') . '?type=student&id=' . (int) $row['id'];
                        } elseif ($view === 'teachers') {
                            $detailUrl = khotwa_url('admin/person.php') . '?type=teacher&id=' . (int) $row['id'];
                        } else {
                            $detailUrl = khotwa_url('admin/record.php') . '?view=' . rawurlencode($view) . '&id=' . (int) $row['id'];
                        }
                        ?>
                        <?php
                        $attendanceAttrs = '';
                        if ($view === 'attendance') {
                          $attendanceStudentId = (int) ($row['student_id'] ?? 0);
                          $attendanceDate = (string) ($row['attendance_date'] ?? '');
                          $attendanceStatus = (string) ($row['daily_status'] ?? '');
                          $attendanceStudentName = (string) ($row['student_name_en'] ?? '');
                          $attendanceAttrs = ' data-student-id="' . e((string) $attendanceStudentId)
                            . '" data-attendance-date="' . e($attendanceDate)
                            . '" data-attendance-status="' . e($attendanceStatus)
                            . '" data-student-name="' . e($attendanceStudentName) . '"';
                        }
                        ?>
                        <tr class="is-openable" data-record-row data-detail-url="<?= e($detailUrl) ?>"<?= $attendanceAttrs ?> title="Double-click to open the full record" tabindex="0">
                          <td class="selection-column">
                            <input type="checkbox" name="record_ids[]" value="<?= e((string) $row['id']) ?>" aria-label="Select record <?= e((string) $row['id']) ?>" data-record-select>
                          </td>
                          <?php foreach ($columns as $key => $label): ?>
                            <td data-sort-value="<?= e((string) ($row[$key] ?? '')) ?>"><?= render_value($key, $row[$key] ?? null) ?></td>
                          <?php endforeach; ?>
                          <td class="actions-column">
                            <?php if ($view === 'website-reviews'): ?>
                              <?php if ((string) $row['status'] !== 'approved'): ?>
                                <button class="inline-approve-button" type="submit" name="review_approve_id" value="<?= e((string) $row['id']) ?>">
                                  Approve
                                </button>
                              <?php endif; ?>
                              <?php if ((string) $row['status'] !== 'rejected'): ?>
                                <button class="inline-reject-button" type="submit" name="review_reject_id" value="<?= e((string) $row['id']) ?>">
                                  Reject
                                </button>
                              <?php endif; ?>
                            <?php endif; ?>
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
              <?php if ($pager['pages'] > 1): ?>
                <nav class="table-pager" aria-label="Table pages">
                  <?php if ($pager['page'] > 1): ?>
                    <a class="pager-step" href="<?= e($pagerLink($pager['page'] - 1)) ?>" rel="prev">Previous</a>
                  <?php else: ?>
                    <span class="pager-step is-disabled">Previous</span>
                  <?php endif; ?>
                  <span class="pager-status">Page <?= e((string) $pager['page']) ?> of <?= e((string) $pager['pages']) ?></span>
                  <?php if ($pager['page'] < $pager['pages']): ?>
                    <a class="pager-step" href="<?= e($pagerLink($pager['page'] + 1)) ?>" rel="next">Next</a>
                  <?php else: ?>
                    <span class="pager-step is-disabled">Next</span>
                  <?php endif; ?>
                </nav>
              <?php endif; ?>
            </form>
          </section>
          <?php if ($workspaceTabs !== []): ?>
              </div>
            </div>
          <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </main>
    </div>

    <?php if ($view === 'attendance'): ?>
      <div class="qr-scan-modal" data-qr-scan-modal data-qr-scan-csrf="<?= e(admin_csrf_token()) ?>" data-qr-scan-url="<?= e(khotwa_url('admin/qr-attendance.php')) ?>" data-qr-student-url="<?= e(khotwa_url('admin/person.php')) ?>" hidden>
        <div class="qr-scan-backdrop" data-qr-scan-close></div>
        <section class="qr-scan-dialog" role="dialog" aria-modal="true" aria-label="Scan student QR code">
          <header class="qr-scan-head">
            <div>
              <strong>Scan Student QR Code</strong>
              <p>Use your camera to scan a student QR code on desktop or phone.</p>
            </div>
            <button class="secondary-action" type="button" data-qr-scan-close>Close</button>
          </header>
          <div class="qr-scan-reader" data-qr-reader></div>
          <div class="qr-scan-upload">
            <button class="secondary-action" type="button" data-qr-image-button>Scan from image</button>
            <input type="file" accept="image/*" data-qr-image-input hidden>
          </div>
          <div class="qr-scan-result" data-qr-scan-result>Waiting for scan...</div>
          <div class="qr-scan-actions">
            <a class="primary-action" href="#" data-qr-open-student hidden>Open student profile</a>
          </div>
          <div class="qr-scan-toast" data-qr-scan-toast hidden></div>
        </section>
      </div>
    <?php endif; ?>

    <button class="sidebar-scrim" type="button" aria-label="Close navigation panel" data-sidebar-scrim></button>
  </div>
  <?php if ($view === 'attendance'): ?>
    <script src="<?= e(khotwa_asset('vendor/html5-qrcode.min.js')) ?>" defer></script>
  <?php endif; ?>
  <script src="<?= e(khotwa_asset('vendor/qrcode.min.js')) ?>" defer></script>
  <?php render_toasts([
      ['type' => 'success', 'text' => $message ?? ''],
      ['type' => 'error', 'text' => $formError ?? ''],
  ]); ?>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/qr-tools.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
