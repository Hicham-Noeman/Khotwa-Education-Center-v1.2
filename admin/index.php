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

            if (in_array($action, ['warning_issue', 'warning_dismiss', 'warning_resolve'], true)) {
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
                    $warningNumber = ($_POST['warning_number'] ?? '') === '' ? null : max(0, (int) $_POST['warning_number']);
                    $conversationMinutes = ($_POST['conversation_minutes'] ?? '') === '' ? null : max(0, (int) $_POST['conversation_minutes']);
                    $statement = $pdo->prepare(
                        "UPDATE student_warnings
                         SET warning_type = ?, warning_number = ?, conversation_minutes = ?,
                             action_taken = NULLIF(?, ''), parent_notified = 1,
                             status = 'issued', issued_by_user_id = ?, issued_at = NOW()
                         WHERE id = ? AND status = 'flagged'"
                    );
                    $statement->execute([
                        $warningType,
                        $warningNumber,
                        $conversationMinutes,
                        trim((string) ($_POST['action_taken'] ?? '')),
                        $adminUserId,
                        $warningId,
                    ]);
                } elseif ($action === 'warning_dismiss') {
                    $statement = $pdo->prepare(
                        "UPDATE student_warnings
                         SET status = 'dismissed', resolved_by_user_id = ?, resolved_at = NOW()
                         WHERE id = ? AND status = 'flagged'"
                    );
                    $statement->execute([$adminUserId, $warningId]);
                } else { // warning_resolve
                    $statement = $pdo->prepare(
                        "UPDATE student_warnings
                         SET status = 'resolved', resolved_by_user_id = ?, resolved_at = NOW()
                         WHERE id = ? AND status IN ('issued', 'assigned')"
                    );
                    $statement->execute([$adminUserId, $warningId]);
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

            if ($action === 'add') {
                $table = $viewTables[$postedView];
                $uploadResult = admin_prepare_uploads(
                    $table,
                    (array) ($_POST['fields'] ?? []),
                    (array) ($_FILES['uploads'] ?? [])
                );
                try {
                    admin_save_record($pdo, $table, $uploadResult['fields']);
                    admin_remove_uploaded_files($uploadResult['replaced']);
                } catch (Throwable $exception) {
                    admin_remove_uploaded_files($uploadResult['created']);
                    throw $exception;
                }
                header('Location: ' . admin_workspace_url($postedView, ['created' => 1]));
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
             FROM student_daily_attendance_summary
             ORDER BY attendance_date DESC, student_name_en LIMIT 8"
        )->fetchAll();
        $pageDescription = 'A live view of students, educators, attendance, enrollments, and financial activity.';
    } elseif ($view === 'students') {
        $pageDescription = 'Student profiles and their current academic placement. Double-click a student to open every linked record.';
        $columns = [
            'id' => 'ID', 'student_name' => 'Student', 'student_name_ar' => 'Arabic name',
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
             LEFT JOIN student_academic_records ON student_academic_records.student_id = students.id
              AND student_academic_records.is_current = 1
             ORDER BY students.last_name_en, students.first_name_en"
        )->fetchAll();
    } elseif ($view === 'teachers') {
        $pageDescription = 'Educator profiles and assigned subjects. Double-click a teacher to open every linked record.';
        $columns = [
            'id' => 'ID', 'teacher_name' => 'Teacher', 'email' => 'Email',
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
             FROM student_daily_attendance_summary ORDER BY attendance_date DESC, student_name_en"
        )->fetchAll();
    } elseif ($view === 'subjects') {
        $pageDescription = 'Subjects offered by the center and their teaching coverage.';
        $columns = [
            'id' => 'ID', 'name_en' => 'Subject', 'name_ar' => 'Arabic name',
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
    } elseif ($view === 'enrollments') {
        $pageDescription = 'Connections between students, teachers, subjects, and academic years.';
        $columns = [
            'id' => 'ID', 'student_name' => 'Student', 'subject_name' => 'Subject',
            'teacher_name' => 'Teacher', 'academic_year' => 'Academic year',
            'start_date' => 'Start date', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT student_subject_enrollments.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    subjects.name_en subject_name,
                    TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) teacher_name,
                    student_subject_enrollments.academic_year, student_subject_enrollments.start_date,
                    student_subject_enrollments.status
             FROM student_subject_enrollments
             INNER JOIN students ON students.id = student_subject_enrollments.student_id
             INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
             INNER JOIN teachers ON teachers.id = student_subject_enrollments.teacher_id
             ORDER BY student_subject_enrollments.academic_year DESC, student_name"
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
            'id' => 'ID', 'student_name' => 'Student', 'paid_at' => 'Paid at',
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
                    student_warnings.action_taken, student_warnings.notes,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) student_name,
                    COALESCE(TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))), 'Center team') teacher_name,
                    expiations.title_en AS expiation_title, expiation_categories.name_en AS expiation_category,
                    age_groups.name_en AS expiation_age_group,
                    student_warnings.expiation_selected_at, student_warnings.issued_at, student_warnings.resolved_at
             FROM student_warnings
             INNER JOIN students ON students.id = student_warnings.student_id
             LEFT JOIN teachers ON teachers.id = student_warnings.teacher_id
             LEFT JOIN expiations ON expiations.id = student_warnings.expiation_id
             LEFT JOIN expiation_categories ON expiation_categories.id = expiations.category_id
             LEFT JOIN age_groups ON age_groups.id = expiations.age_group_id
             ORDER BY FIELD(student_warnings.status, 'flagged', 'assigned', 'issued', 'resolved', 'dismissed'),
                      student_warnings.warning_date DESC, student_warnings.id DESC"
        )->fetchAll();
        $warningGroups = ['flagged' => [], 'issued' => [], 'assigned' => [], 'resolved' => [], 'dismissed' => []];
        foreach ($warningRows as $warningRow) {
            $warningGroups[(string) $warningRow['status']][] = $warningRow;
        }
    } elseif ($view === 'users') {
        $pageDescription = 'Portal users, roles, access status, and recent sign-ins.';
        $columns = [
            'id' => 'ID', 'user_name' => 'User', 'email' => 'Email',
            'role' => 'Role', 'status' => 'Status', 'last_login_at' => 'Last login',
        ];
        $rows = $pdo->query(
            "SELECT id, TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) user_name,
                    email, role, status, last_login_at
             FROM users ORDER BY role, user_name"
        )->fetchAll();
    } elseif ($view === 'expiations') {
        $pageDescription = 'Corrective expiations parents can assign, organised by category and age group.';
        $columns = [
            'id' => 'ID', 'title_en' => 'English title', 'title_ar' => 'Arabic title',
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
            'id' => 'ID', 'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, sort_order, status
             FROM expiation_categories ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'age-groups') {
        $pageDescription = 'Named age groups (with age ranges) used to match expiations to a student automatically.';
        $columns = [
            'id' => 'ID', 'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'min_age' => 'Min age', 'max_age' => 'Max age', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, min_age, max_age, sort_order, status
             FROM age_groups ORDER BY sort_order, min_age, id"
        )->fetchAll();
    } elseif ($view === 'website-content') {
    } elseif ($view === 'parent-links') {
      $pageDescription = 'Relationships between parent portal accounts and student records.';
      $columns = [
        'id' => 'ID', 'parent_name' => 'Parent', 'parent_email' => 'Email',
        'student_name' => 'Student', 'relationship' => 'Relationship',
        'status' => 'Status', 'updated_at' => 'Updated',
      ];
      $rows = $pdo->query(
        "SELECT parent_students.id,
            CONCAT(users.first_name, ' ', COALESCE(users.last_name, '')) AS parent_name,
            users.email AS parent_email,
            CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name,
            parent_students.relationship,
            parent_students.status,
            parent_students.updated_at
         FROM parent_students
         INNER JOIN users ON users.id = parent_students.parent_user_id
         INNER JOIN students ON students.id = parent_students.student_id
         ORDER BY parent_name, student_name"
      )->fetchAll();
        $pageDescription = 'One creative workspace for homepage writing, slides, statistics, team members, gallery images, and partner logos.';
        $admissionsBannerVisible = homepage_setting($pdo, 'admissions_banner_visible', '1') === '1';
        $foundingDate = homepage_setting($pdo, 'founding_date');
        $homepageMetrics = homepage_dynamic_metrics($pdo, ['founding_date' => $foundingDate]);
        $pendingReviewCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM homepage_reviews WHERE status = 'pending'"
        )->fetchColumn();
        $columns = [
            'id' => 'ID', 'content_type' => 'Type', 'content_key' => 'Content key',
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
            "SELECT id, name_en, name_ar, role_en, role_ar, subjects_en, image_path, sort_order, status
             FROM homepage_team_members ORDER BY sort_order, id"
        )->fetchAll();
        $websiteCollections['gallery'] = $pdo->query(
            "SELECT id, caption_en, caption_ar, layout_style, image_path, sort_order, status
             FROM homepage_gallery_images ORDER BY sort_order, id"
        )->fetchAll();
        $websiteCollections['partners'] = $pdo->query(
            "SELECT id, name_en, name_ar, logo_path, website_url, sort_order, status
             FROM homepage_partners ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-slides') {
        $pageDescription = 'Images and bilingual captions shown as the vision and mission slideshow.';
        $columns = [
            'id' => 'ID', 'title_en' => 'English title', 'title_ar' => 'Arabic title',
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
            'id' => 'ID', 'stat_key' => 'Key', 'stat_value' => 'Number',
            'suffix' => 'Suffix', 'label_en' => 'English label',
            'label_ar' => 'Arabic label', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, stat_key, stat_value, suffix, label_en, label_ar, sort_order, status
             FROM homepage_statistics ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-team') {
        $pageDescription = 'Homepage team profiles, roles, specialties, images, contact links, and display order.';
        $columns = [
            'id' => 'ID', 'name_en' => 'English name', 'name_ar' => 'Arabic name',
            'role_en' => 'Role', 'subjects_en' => 'Specialty', 'image_path' => 'Image',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, name_en, name_ar, role_en, subjects_en, image_path, sort_order, status
             FROM homepage_team_members ORDER BY sort_order, id"
        )->fetchAll();
    } elseif ($view === 'website-gallery') {
        $pageDescription = 'Uploaded gallery images, bilingual captions, and display layouts.';
        $columns = [
            'id' => 'ID', 'caption_en' => 'English caption', 'caption_ar' => 'Arabic caption',
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
            'id' => 'ID', 'name_en' => 'English name', 'name_ar' => 'Arabic name',
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
            'id' => 'ID', 'display_name' => 'Parent', 'parent_email' => 'Account',
            'rating' => 'Rating', 'review_text' => 'Review', 'created_at' => 'Submitted',
            'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT homepage_reviews.id, homepage_reviews.display_name,
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
            'id' => 'ID', 'link_key' => 'Key', 'link_type' => 'Type',
            'value_en' => 'English value', 'value_ar' => 'Arabic value',
            'url' => 'Link', 'sort_order' => 'Order', 'status' => 'Status',
        ];
        $rows = $pdo->query(
            "SELECT id, link_key, link_type, value_en, value_ar, url, sort_order, status
             FROM homepage_contact_links ORDER BY sort_order, id"
        )->fetchAll();
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
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
          <section class="overview-grid">
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
                        <tr><td><?= e($row['attendance_date']) ?></td><td><strong><?= e($row['student_name_en']) ?></strong></td><td><?= render_value('daily_status', $row['daily_status']) ?></td><td><?= e((string) $row['attended_subject_count']) ?></td><td><?= e((string) $row['missed_subject_count']) ?></td></tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <aside class="quick-panel">
              <span>Quick access</span><h2>Move through your center.</h2>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=students"><?= admin_icon('students') ?><span><strong>Students</strong><small>Profiles and grades</small></span></a>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=teachers"><?= admin_icon('teachers') ?><span><strong>Teachers</strong><small>Team and subjects</small></span></a>
              <a href="<?= e(khotwa_url('admin/index.php')) ?>?view=attendance"><?= admin_icon('attendance') ?><span><strong>Attendance</strong><small>Daily and subject records</small></span></a>
            </aside>
          </section>
        <?php else: ?>
          <?php if ($message !== ''): ?><div class="form-notice success-notice"><?= e($message) ?></div><?php endif; ?>
          <?php if ($formError !== ''): ?><div class="form-notice error-notice"><?= e($formError) ?></div><?php endif; ?>

          <?php if ($isAdding): ?>
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
                <div class="record-form-actions">
                  <button class="primary-action" type="submit">Save record</button>
                  <a class="secondary-action" href="<?= e(admin_workspace_url($view)) ?>">Cancel</a>
                </div>
              </form>
            </section>
          <?php endif; ?>

          <?php if ($view === 'warnings'): ?>
            <?php
            $warningStatusMeta = [
                'flagged' => ['label' => 'Teacher flags (admin only)', 'hint' => 'Raised by a teacher. Not visible to parents until you issue a warning.'],
                'assigned' => ['label' => 'Expiation chosen by parent', 'hint' => 'Parent selected an expiation. Confirm once the student completes it.'],
                'issued' => ['label' => 'Issued — waiting for parent', 'hint' => 'Visible to the parent. Parent can now choose an expiation.'],
                'resolved' => ['label' => 'Resolved / removed', 'hint' => 'Completed and no longer valid.'],
                'dismissed' => ['label' => 'Dismissed', 'hint' => 'Flag rejected; never shown to the parent.'],
            ];
            ?>
            <section class="warning-board">
              <?php foreach ($warningStatusMeta as $statusKey => $meta): ?>
                <?php $groupRows = $warningGroups[$statusKey] ?? []; ?>
                <article class="data-panel warning-column warning-column-<?= e($statusKey) ?>">
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
                    <?php foreach ($groupRows as $warning): ?>
                      <div class="warning-card">
                        <div class="warning-card-top">
                          <strong><?= e((string) $warning['student_name']) ?></strong>
                          <small><?= e((string) $warning['warning_date']) ?></small>
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
                          <form method="post" class="warning-action-form">
                            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                            <input type="hidden" name="view" value="warnings">
                            <input type="hidden" name="warning_id" value="<?= e((string) $warning['id']) ?>">
                            <div class="warning-type-choice">
                              <label><input type="radio" name="warning_type" value="oral" checked> Oral</label>
                              <label><input type="radio" name="warning_type" value="written"> Written</label>
                            </div>
                            <div class="warning-inline-fields">
                              <input type="number" name="warning_number" min="0" placeholder="Warning #">
                              <input type="number" name="conversation_minutes" min="0" placeholder="Talk (min)">
                            </div>
                            <input type="text" name="action_taken" placeholder="Action taken (optional)">
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
                              <button class="primary-action" type="submit" name="action" value="warning_resolve">Mark completed &amp; remove</button>
                            </div>
                          </form>
                        <?php elseif ($warning['resolved_at']): ?>
                          <small class="warning-meta"><?= e($statusKey === 'resolved' ? 'Resolved' : 'Dismissed') ?> on <?= e((string) $warning['resolved_at']) ?></small>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </section>
          <?php elseif ($view === 'website-content'): ?>
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
                    'description' => 'The educators and leaders introduced on the public homepage.',
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
                        <a class="partner-preview-card" href="<?= e(khotwa_url('admin/record.php')) ?>?view=website-team&id=<?= e((string) $item['id']) ?>">
                          <?php if ($item['image_path']): ?>
                            <img src="<?= e(khotwa_url((string) $item['image_path'])) ?>" alt="">
                          <?php else: ?>
                            <span><?= e(strtoupper(substr((string) $item['name_en'], 0, 2))) ?></span>
                          <?php endif; ?>
                          <strong><?= e((string) $item['name_en']) ?></strong>
                          <small><?= e((string) $item['role_en']) ?></small>
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
          <section class="data-panel">
            <form method="post" data-table-actions>
              <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <div class="panel-heading table-panel-heading">
                <div><span>Database table</span><h2><?= e($pageTitle) ?></h2></div>
                <label class="table-search">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                  <input type="search" placeholder="Search this table" data-table-search>
                </label>
                <div class="table-heading-actions">
                  <strong class="record-count"><?= e((string) count($rows)) ?> records</strong>
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
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add record
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
                    <?php if ($rows === []): ?>
                      <tr><td class="empty-row" colspan="<?= e((string) (count($columns) + 2)) ?>">No records found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($rows as $row): ?>
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
            </form>
          </section>
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
    <script src="https://unpkg.com/html5-qrcode" defer></script>
  <?php endif; ?>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js" defer></script>
  <script src="<?= e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/qr-tools.js')) ?>" defer></script>
  <script src="<?= e(khotwa_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
