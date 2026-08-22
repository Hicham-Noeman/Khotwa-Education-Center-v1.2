<?php
declare(strict_types=1);

function admin_navigation(): array
{
    return [
        'overview' => ['label' => 'Overview', 'group' => 'Workspace'],
        'students' => ['label' => 'Students', 'group' => 'People'],
        'teachers' => ['label' => 'Teachers', 'group' => 'People'],
        'attendance' => ['label' => 'Attendance', 'group' => 'Academics'],
        'subjects' => ['label' => 'Subjects', 'group' => 'Academics'],
        'enrollments' => ['label' => 'Enrollments', 'group' => 'Academics'],
        'subscriptions' => ['label' => 'Subscriptions', 'group' => 'Finance'],
        'payments' => ['label' => 'Payments', 'group' => 'Finance'],
        'warnings' => ['label' => 'Warnings', 'group' => 'Management'],
        'users' => ['label' => 'Users', 'group' => 'Management'],
        'parent-links' => ['label' => 'Parent Links', 'group' => 'Management'],
        'expiations' => ['label' => 'Expiations', 'group' => 'Expiations'],
        'expiation-categories' => ['label' => 'Categories', 'group' => 'Expiations'],
        'age-groups' => ['label' => 'Age Groups', 'group' => 'Expiations'],
        'website-content' => ['label' => 'Website Content', 'group' => 'Website'],
        'website-slides' => ['label' => 'Vision Slides', 'group' => 'Website', 'sidebar' => false],
        'website-statistics' => ['label' => 'Statistics', 'group' => 'Website', 'sidebar' => false],
        'website-team' => ['label' => 'Team Members', 'group' => 'Website', 'sidebar' => false],
        'website-gallery' => ['label' => 'Gallery Images', 'group' => 'Website', 'sidebar' => false],
        'website-partners' => ['label' => 'Partner Logos', 'group' => 'Website', 'sidebar' => false],
        'website-reviews' => ['label' => 'Parent Reviews', 'group' => 'Website'],
        'website-contacts' => ['label' => 'Contact & Social', 'group' => 'Website'],
    ];
}

function admin_manager_allowed_views(): array
{
    return [
        'students',
        'teachers',
        'subjects',
        'enrollments',
        'subscriptions',
        'payments',
        'warnings',
        'expiations',
        'expiation-categories',
        'age-groups',
        'website-content',
        'website-slides',
        'website-statistics',
        'website-team',
        'website-gallery',
        'website-partners',
        'website-reviews',
        'website-contacts',
        'parent-links',
    ];
}

function admin_allowed_views_for_role(string $role): array
{
    if ($role === 'admin') {
        return array_keys(admin_navigation());
    }

    if ($role === 'manager') {
        return admin_manager_allowed_views();
    }

    return [];
}

function admin_user_can_access_view(array $user, string $view): bool
{
    return in_array($view, admin_allowed_views_for_role((string) ($user['role'] ?? '')), true);
}

function admin_navigation_for_user(array $user): array
{
    $allowedViews = admin_allowed_views_for_role((string) ($user['role'] ?? ''));

    return array_filter(
        admin_navigation(),
        static fn (string $view): bool => in_array($view, $allowedViews, true),
        ARRAY_FILTER_USE_KEY
    );
}

function admin_view_tables(): array
{
    return [
        'students' => 'students',
        'teachers' => 'teachers',
        'attendance' => 'student_daily_attendance',
        'subjects' => 'subjects',
        'enrollments' => 'student_subject_enrollments',
        'subscriptions' => 'student_subscription_months',
        'payments' => 'student_subscription_payments',
        'warnings' => 'student_warnings',
        'users' => 'users',
        'parent-links' => 'parent_students',
        'expiations' => 'expiations',
        'expiation-categories' => 'expiation_categories',
        'age-groups' => 'age_groups',
        'website-content' => 'homepage_content',
        'website-slides' => 'homepage_slides',
        'website-statistics' => 'homepage_statistics',
        'website-team' => 'homepage_team_members',
        'website-gallery' => 'homepage_gallery_images',
        'website-partners' => 'homepage_partners',
        'website-reviews' => 'homepage_reviews',
        'website-contacts' => 'homepage_contact_links',
    ];
}

/**
 * Cash figures for the overview balance card.
 * "This month" = the current calendar month if it already has billing rows,
 * otherwise the most recent month that does (the center's active billing cycle).
 * Returns collected (money received in that month), expected (billed for that month), and a label.
 */
function admin_month_cash_metric(PDO $pdo): array
{
    $period = $pdo->query(
        "SELECT billing_year AS y, billing_month AS m
         FROM student_subscription_months
         ORDER BY (billing_year = YEAR(CURDATE()) AND billing_month = MONTH(CURDATE())) DESC,
                  billing_year DESC, billing_month DESC
         LIMIT 1"
    )->fetch();

    if (!$period) {
        return ['collected' => 0.0, 'expected' => 0.0, 'label' => date('F Y')];
    }

    $year = (int) $period['y'];
    $month = (int) $period['m'];

    $expectedStatement = $pdo->prepare(
        "SELECT COALESCE(SUM(expected_amount), 0)
         FROM student_subscription_months
         WHERE billing_year = ? AND billing_month = ?"
    );
    $expectedStatement->execute([$year, $month]);

    $collectedStatement = $pdo->prepare(
        "SELECT COALESCE(SUM(paid_amount), 0)
         FROM student_subscription_payments
         WHERE YEAR(paid_at) = ? AND MONTH(paid_at) = ?"
    );
    $collectedStatement->execute([$year, $month]);

    return [
        'collected' => (float) $collectedStatement->fetchColumn(),
        'expected' => (float) $expectedStatement->fetchColumn(),
        'label' => date('F Y', (int) mktime(0, 0, 0, $month, 1, $year)),
    ];
}

function admin_linked_tables(string $type): array
{
    if ($type === 'teacher') {
        return [
            'users' => 'Portal account',
            'teacher_subjects' => 'Assigned subjects',
            'student_subject_enrollments' => 'Student enrollments',
            'student_subject_attendance' => 'Subject attendance',
            'student_warnings' => 'Warnings issued',
        ];
    }

    return [
        'student_academic_records' => 'Academic records',
        'student_medical_info' => 'Medical information',
        'student_other_phone_numbers' => 'Other phone numbers',
        'student_school_schedule' => 'School schedule',
        'parent_students' => 'Parent links',
        'student_subject_enrollments' => 'Subject enrollments',
        'student_daily_attendance' => 'Daily attendance',
        'student_subject_attendance' => 'Subject attendance',
        'student_warnings' => 'Warnings',
        'student_subscriptions' => 'Subscriptions',
        'student_subscription_months' => 'Subscription months',
        'student_subscription_payments' => 'Payments',
    ];
}

function admin_linked_tables_for_user(string $type, array $user): array
{
    $tables = admin_linked_tables($type);

    if (($user['role'] ?? '') === 'manager') {
        unset($tables['users']);
    }

    return $tables;
}

function admin_icon(string $name): string
{
    $paths = [
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'overview' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'students' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'teachers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-5 5 2 2 4-4"/>',
        'subjects' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7Z"/>',
        'enrollments' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'subscriptions' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'payments' => '<circle cx="12" cy="12" r="9"/><path d="M16 8h-5a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4H8m4-10v12"/>',
        'warnings' => '<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/><path d="M12 9v4M12 17h.01"/>',
        'users' => '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/><path d="m9 12 2 2 4-4"/>',
        'parent-links' => '<path d="M7 21v-2a5 5 0 0 1 5-5h5"/><circle cx="9" cy="8" r="3"/><path d="m16 14 2 2 4-4"/>',
        'expiations' => '<path d="M12 3v18"/><path d="M5 8h14"/><path d="M6 8 4 20h6L8 8M18 8l-2 12h-6"/>',
        'expiation-categories' => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
        'age-groups' => '<circle cx="12" cy="7" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/><path d="M12 11v4"/>',
        'website-content' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h3M8 17h8"/>',
        'website-slides' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/>',
        'website-statistics' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/>',
        'website-team' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20a6 6 0 0 1 12 0M14 20a5 5 0 0 1 8 0"/>',
        'website-gallery' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
        'website-partners' => '<path d="M8 12h8M12 8v8"/><circle cx="12" cy="12" r="9"/>',
        'website-reviews' => '<path d="M12 3.6 14.3 9l5.7.4-4.4 3.7 1.4 5.6L12 15.7 7 18.7l1.4-5.6L4 9.4 9.7 9Z"/>',
        'website-contacts' => '<path d="M4 4h16v16H4zM4 7l8 6 8-6"/><path d="M8 17h8"/>',
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['overview']) . '</svg>';
}

function admin_table_label(string $table): string
{
    return ucwords(str_replace('_', ' ', $table));
}

function admin_website_workspace_views(): array
{
    return [
        'website-content' => 'page-content',
        'website-slides' => 'vision-slides',
        'website-statistics' => 'statistics',
        'website-team' => 'team-members',
        'website-gallery' => 'gallery-images',
        'website-partners' => 'partner-logos',
    ];
}

function admin_workspace_url(string $view, array $query = []): string
{
    $workspaceViews = admin_website_workspace_views();
    if (!isset($workspaceViews[$view])) {
        $parameters = ['view' => $view, ...$query];
        return khotwa_url('admin/index.php') . '?' . http_build_query($parameters);
    }

    $parameters = ['view' => 'website-content', ...$query];
    return khotwa_url('admin/index.php') . '?' . http_build_query($parameters) . '#' . $workspaceViews[$view];
}

function admin_column_label(string $column): string
{
    $labels = [
        'photo_path' => 'Photo',
        'image_path' => 'Image',
        'logo_path' => 'Logo',
        'stat_key' => 'Statistic Key',
        'stat_value' => 'Number',
        'link_key' => 'Link Key',
        'link_type' => 'Link Type',
        'layout_style' => 'Layout',
        'contact_url' => 'Contact Link',
        'website_url' => 'Website Link',
        'parent_user_id' => 'Parent User',
    ];
    if (isset($labels[$column])) {
        return $labels[$column];
    }

    $special = ['id' => 'ID', 'en' => 'EN', 'ar' => 'AR', 'url' => 'URL'];
    $words = explode('_', $column);

    return implode(' ', array_map(
        static fn (string $word): string => $special[$word] ?? ucfirst($word),
        $words
    ));
}

function admin_quote_identifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }

    return "`{$identifier}`";
}

function admin_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $statement = $pdo->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $statement->execute([$table]);

    $cache[$table] = $statement->fetchAll();
    return $cache[$table];
}

function admin_editable_columns(PDO $pdo, string $table): array
{
    return array_values(array_filter(
        admin_columns($pdo, $table),
        static function (array $column): bool {
            $extra = strtolower((string) $column['EXTRA']);
            return !str_contains($extra, 'auto_increment')
                && !str_contains($extra, 'generated')
                && !in_array($column['COLUMN_NAME'], ['created_at', 'updated_at', 'last_login_at'], true);
        }
    ));
}

function admin_enum_options(string $columnType): array
{
    if (!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches)) {
        return [];
    }

    return array_map(static fn (string $value): string => stripcslashes($value), $matches[1]);
}

function admin_relation_options(PDO $pdo, string $column): array
{
    static $cache = [];
    if (isset($cache[$column])) {
        return $cache[$column];
    }

    $queries = [
        'student_id' => "SELECT id, CONCAT(first_name_en, ' ', last_name_en) label FROM students ORDER BY last_name_en, first_name_en",
        'teacher_id' => "SELECT id, TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) label FROM teachers ORDER BY last_name, first_name",
        'subject_id' => "SELECT id, name_en label FROM subjects ORDER BY name_en",
        'teacher_subject_id' => "SELECT teacher_subjects.id, CONCAT(TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))), ' / ', subjects.name_en) label FROM teacher_subjects INNER JOIN teachers ON teachers.id = teacher_subjects.teacher_id INNER JOIN subjects ON subjects.id = teacher_subjects.subject_id ORDER BY label",
        'daily_attendance_id' => "SELECT student_daily_attendance.id, CONCAT(student_daily_attendance.attendance_date, ' / ', students.first_name_en, ' ', students.last_name_en) label FROM student_daily_attendance INNER JOIN students ON students.id = student_daily_attendance.student_id ORDER BY student_daily_attendance.attendance_date DESC",
        'subscription_id' => "SELECT student_subscriptions.id, CONCAT(students.first_name_en, ' ', students.last_name_en, ' / ', student_subscriptions.start_date) label FROM student_subscriptions INNER JOIN students ON students.id = student_subscriptions.student_id ORDER BY students.last_name_en, student_subscriptions.start_date DESC",
        'subscription_month_id' => "SELECT student_subscription_months.id, CONCAT(students.first_name_en, ' ', students.last_name_en, ' / ', student_subscription_months.billing_year, '-', LPAD(student_subscription_months.billing_month, 2, '0')) label FROM student_subscription_months INNER JOIN students ON students.id = student_subscription_months.student_id ORDER BY student_subscription_months.billing_year DESC, student_subscription_months.billing_month DESC",
        'parent_user_id' => "SELECT id, CONCAT(first_name, ' ', COALESCE(last_name, ''), ' / ', email) label FROM users WHERE role = 'parent' ORDER BY first_name, last_name, email",
        'category_id' => "SELECT id, CONCAT(name_en, ' / ', name_ar) label FROM expiation_categories ORDER BY sort_order, name_en",
        'age_group_id' => "SELECT id, CONCAT(name_en, ' (', min_age, '-', max_age, ')') label FROM age_groups ORDER BY sort_order, min_age",
    ];

    if (!isset($queries[$column])) {
        return [];
    }

    $cache[$column] = $pdo->query($queries[$column])->fetchAll();
    return $cache[$column];
}

function admin_hidden_derived_columns(string $table): array
{
    return match ($table) {
        'student_subject_enrollments' => ['teacher_id', 'subject_id'],
        'student_subject_attendance' => ['student_id', 'attendance_date', 'teacher_id', 'subject_id'],
        'student_subscription_months', 'student_subscription_payments' => ['student_id'],
        default => [],
    };
}

function admin_default_field_value(array $column): string
{
    if ($column['COLUMN_NAME'] === 'password_hash') {
        return '';
    }

    if ($column['COLUMN_DEFAULT'] !== null) {
        return (string) $column['COLUMN_DEFAULT'];
    }

    return '';
}

function admin_upload_columns(string $table): array
{
    return match ($table) {
        'students', 'teachers' => ['photo_path'],
        'homepage_slides', 'homepage_team_members', 'homepage_gallery_images' => ['image_path'],
        'homepage_partners' => ['logo_path'],
        default => [],
    };
}

function admin_prepare_uploads(string $table, array $fields, array $uploads): array
{
    $created = [];
    $replaced = [];

    foreach (admin_upload_columns($table) as $column) {
        $error = (int) ($uploads['error'][$column] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The image upload did not complete. Please try again.');
        }

        $size = (int) ($uploads['size'][$column] ?? 0);
        $temporaryPath = (string) ($uploads['tmp_name'][$column] ?? '');
        if ($size < 1 || $size > 8 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('Images must be valid uploaded files no larger than 8 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Use a JPEG, PNG, GIF, or WebP image.');
        }

        $directory = khotwa_path('assets/uploads/' . $table);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The upload folder could not be created.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $absolutePath = $directory . '/' . $fileName;
        if (!move_uploaded_file($temporaryPath, $absolutePath)) {
            throw new RuntimeException('The image could not be saved.');
        }

        $oldPath = trim((string) ($fields[$column] ?? ''));
        if ($oldPath !== '' && str_starts_with($oldPath, 'assets/uploads/')) {
            $replaced[] = $oldPath;
        }

        $relativePath = 'assets/uploads/' . $table . '/' . $fileName;
        $fields[$column] = $relativePath;
        $created[] = $relativePath;
    }

    return ['fields' => $fields, 'created' => $created, 'replaced' => $replaced];
}

function admin_remove_uploaded_files(array $paths): void
{
    $uploadRoot = realpath(khotwa_path('assets/uploads'));
    if ($uploadRoot === false) {
        return;
    }

    foreach ($paths as $path) {
        if (!is_string($path) || !str_starts_with($path, 'assets/uploads/')) {
            continue;
        }

        $absolutePath = realpath(khotwa_path($path));
        if (
            $absolutePath !== false
            && str_starts_with($absolutePath, $uploadRoot . DIRECTORY_SEPARATOR)
            && is_file($absolutePath)
        ) {
            unlink($absolutePath);
        }
    }
}

function admin_render_field(
    PDO $pdo,
    string $table,
    array $column,
    mixed $value = '',
    array $locked = [],
    bool $showHidden = false
): void
{
    $name = (string) $column['COLUMN_NAME'];
    if (!$showHidden && in_array($name, admin_hidden_derived_columns($table), true)) {
        return;
    }

    $label = $name === 'password_hash' ? 'Password' : admin_column_label($name);
    $type = (string) $column['DATA_TYPE'];
    $required = $column['IS_NULLABLE'] === 'NO' && $column['COLUMN_DEFAULT'] === null ? ' required' : '';
    $isLocked = array_key_exists($name, $locked);
    $value = $isLocked ? $locked[$name] : $value;
    if ($name === 'password_hash') {
        $value = '';
    }
    $options = admin_relation_options($pdo, $name);

    $languageClass = str_ends_with($name, '_ar')
        ? ' admin-field-ar'
        : (str_ends_with($name, '_en') ? ' admin-field-en' : '');
    $languageAttributes = str_ends_with($name, '_ar')
        ? ' lang="ar" dir="rtl"'
        : (str_ends_with($name, '_en') ? ' lang="en" dir="ltr"' : '');
    $translationSkipAttribute = $languageClass !== '' ? ' data-i18n-skip' : '';

    echo '<label class="admin-field' . $languageClass . '" data-field-name="' . e($name) . '"><span>' . e($label) . '</span>';

    if (in_array($name, admin_upload_columns($table), true)) {
        if ((string) $value !== '') {
            echo '<span class="admin-image-preview"><img src="' . e(khotwa_url((string) $value)) . '" alt=""></span>';
        }
        echo '<input type="hidden" name="fields[' . e($name) . ']" value="' . e((string) $value) . '">';
        if (!$isLocked) {
            $fileRequired = $required !== '' && (string) $value === '' ? ' required' : '';
            echo '<input class="admin-file-input" type="file" name="uploads[' . e($name) . ']" accept="image/jpeg,image/png,image/gif,image/webp"' . $fileRequired . '>';
            echo '<small class="admin-field-help">JPEG, PNG, GIF, or WebP. Maximum 8 MB.</small>';
        }
    } elseif ($isLocked) {
        $lockedLabel = (string) $value;
        foreach ($options as $option) {
            if ((string) $option['id'] === (string) $value) {
                $lockedLabel = (string) $option['label'];
                break;
            }
        }
        echo '<input type="hidden" name="fields[' . e($name) . ']" value="' . e((string) $value) . '">';
        echo '<input type="text" value="' . e($lockedLabel) . '" disabled' . $languageAttributes . $translationSkipAttribute . '>';
    } elseif ($options !== []) {
        echo '<select name="fields[' . e($name) . ']"' . $required . '>';
        if ($column['IS_NULLABLE'] === 'YES' || $value === '') {
            echo '<option value="">Select an option</option>';
        }
        foreach ($options as $option) {
            $selected = (string) $option['id'] === (string) $value ? ' selected' : '';
            echo '<option value="' . e((string) $option['id']) . '"' . $selected . '>' . e((string) $option['label']) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'tinyint' && str_contains((string) $column['COLUMN_TYPE'], '(1)')) {
        echo '<select name="fields[' . e($name) . ']">';
        echo '<option value="0"' . ((string) $value === '0' ? ' selected' : '') . '>No</option>';
        echo '<option value="1"' . ((string) $value === '1' ? ' selected' : '') . '>Yes</option>';
        echo '</select>';
    } elseif (($enum = admin_enum_options((string) $column['COLUMN_TYPE'])) !== []) {
        echo '<select name="fields[' . e($name) . ']"' . $required . '>';
        foreach ($enum as $option) {
            $selected = (string) $option === (string) $value ? ' selected' : '';
            echo '<option value="' . e($option) . '"' . $selected . '>' . e(ucwords(str_replace('_', ' ', $option))) . '</option>';
        }
        echo '</select>';
    } elseif (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
        echo '<textarea name="fields[' . e($name) . ']"' . $required . $languageAttributes . $translationSkipAttribute . '>' . e((string) $value) . '</textarea>';
    } else {
        $inputType = match ($type) {
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'time' => 'time',
            'int', 'bigint', 'smallint', 'tinyint', 'decimal' => 'number',
            default => $name === 'email' ? 'email' : ($name === 'password_hash' ? 'password' : 'text'),
        };
        $step = $type === 'decimal' ? ' step="0.01"' : '';
        echo '<input type="' . e($inputType) . '" name="fields[' . e($name) . ']" value="' . e((string) $value) . '"' . $step . $required . $languageAttributes . $translationSkipAttribute . '>';
    }

    echo '</label>';
}

function admin_derive_fields(PDO $pdo, string $table, array $fields): array
{
    if (in_array($table, ['student_subject_enrollments', 'student_subject_attendance'], true)) {
        $statement = $pdo->prepare('SELECT teacher_id, subject_id FROM teacher_subjects WHERE id = ?');
        $statement->execute([(int) ($fields['teacher_subject_id'] ?? 0)]);
        $relation = $statement->fetch();
        if (!$relation) {
            throw new RuntimeException('Select a valid teacher and subject assignment.');
        }
        if (
            isset($fields['teacher_id'])
            && (int) $fields['teacher_id'] > 0
            && (int) $fields['teacher_id'] !== (int) $relation['teacher_id']
        ) {
            throw new RuntimeException('Select a teacher and subject assignment for this teacher.');
        }
        $fields['teacher_id'] = $relation['teacher_id'];
        $fields['subject_id'] = $relation['subject_id'];
    }

    if ($table === 'student_subject_attendance') {
        $statement = $pdo->prepare('SELECT student_id, attendance_date FROM student_daily_attendance WHERE id = ?');
        $statement->execute([(int) ($fields['daily_attendance_id'] ?? 0)]);
        $attendance = $statement->fetch();
        if (!$attendance) {
            throw new RuntimeException('Select a valid daily attendance record.');
        }
        $fields['student_id'] = $attendance['student_id'];
        $fields['attendance_date'] = $attendance['attendance_date'];
    } elseif ($table === 'student_subscription_months') {
        $statement = $pdo->prepare('SELECT student_id FROM student_subscriptions WHERE id = ?');
        $statement->execute([(int) ($fields['subscription_id'] ?? 0)]);
        $fields['student_id'] = $statement->fetchColumn();
    } elseif ($table === 'student_subscription_payments') {
        $statement = $pdo->prepare('SELECT student_id FROM student_subscription_months WHERE id = ?');
        $statement->execute([(int) ($fields['subscription_month_id'] ?? 0)]);
        $fields['student_id'] = $statement->fetchColumn();
    }

    return $fields;
}

function admin_sync_subscription_month_payment(PDO $pdo, int $subscriptionMonthId): void
{
    if ($subscriptionMonthId < 1) {
        return;
    }

    $statement = $pdo->prepare(
        "SELECT expected_amount, billing_type
         FROM student_subscription_months
         WHERE id = ?"
    );
    $statement->execute([$subscriptionMonthId]);
    $month = $statement->fetch();
    if (!$month) {
        return;
    }

    $paymentStatement = $pdo->prepare(
        "SELECT COALESCE(SUM(paid_amount), 0) paid_amount, MAX(paid_at) last_payment_date
         FROM student_subscription_payments
         WHERE subscription_month_id = ?"
    );
    $paymentStatement->execute([$subscriptionMonthId]);
    $payment = $paymentStatement->fetch() ?: ['paid_amount' => 0, 'last_payment_date' => null];

    $expectedAmount = (float) $month['expected_amount'];
    $paidAmount = (float) $payment['paid_amount'];
    $billingType = (string) $month['billing_type'];
    if ($paidAmount <= 0 && in_array($billingType, ['paused', 'unsubscribed'], true)) {
        $paymentStatus = $billingType;
    } elseif ($paidAmount <= 0) {
        $paymentStatus = 'not_paid';
    } elseif ($paidAmount < $expectedAmount) {
        $paymentStatus = 'partial_paid';
    } elseif ($paidAmount > $expectedAmount) {
        $paymentStatus = 'overpaid';
    } else {
        $paymentStatus = 'paid';
    }

    $update = $pdo->prepare(
        "UPDATE student_subscription_months
         SET paid_amount = ?, payment_status = ?, last_payment_date = ?
         WHERE id = ?"
    );
    $update->execute([
        $paidAmount,
        $paymentStatus,
        $payment['last_payment_date'],
        $subscriptionMonthId,
    ]);
}

function admin_save_record(PDO $pdo, string $table, array $fields, ?int $id = null): int
{
    $previousPaymentMonthId = null;
    if ($table === 'student_subscription_payments' && $id !== null) {
        $statement = $pdo->prepare('SELECT subscription_month_id FROM student_subscription_payments WHERE id = ?');
        $statement->execute([$id]);
        $previousPaymentMonthId = (int) $statement->fetchColumn();
    }

    $fields = admin_derive_fields($pdo, $table, $fields);
    $columns = admin_editable_columns($pdo, $table);
    $names = [];
    $values = [];

    foreach ($columns as $column) {
        $name = (string) $column['COLUMN_NAME'];
        if (!array_key_exists($name, $fields)) {
            continue;
        }

        $value = is_string($fields[$name]) ? trim($fields[$name]) : $fields[$name];
        if ($name === 'password_hash') {
            if ($id !== null && $value === '') {
                continue;
            }
            if ($value !== '' && !str_starts_with((string) $value, '$2y$') && !str_starts_with((string) $value, '$argon')) {
                $value = password_hash((string) $value, PASSWORD_DEFAULT);
            }
        }
        if ($value === '' && $column['IS_NULLABLE'] === 'YES') {
            $value = null;
        } elseif ($id === null && $value === '' && $column['COLUMN_DEFAULT'] !== null) {
            continue;
        }

        $names[] = $name;
        $values[] = $value;
    }

    if ($id === null) {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            admin_quote_identifier($table),
            implode(', ', array_map('admin_quote_identifier', $names)),
            implode(', ', array_fill(0, count($names), '?'))
        );
        $pdo->prepare($sql)->execute($values);
        $newId = (int) $pdo->lastInsertId();
        if ($table === 'student_subscription_payments') {
            admin_sync_subscription_month_payment($pdo, (int) ($fields['subscription_month_id'] ?? 0));
        }
        return $newId;
    }

    $assignments = implode(', ', array_map(
        static fn (string $name): string => admin_quote_identifier($name) . ' = ?',
        $names
    ));
    $pdo->prepare(
        'UPDATE ' . admin_quote_identifier($table) . ' SET ' . $assignments . ' WHERE id = ?'
    )->execute([...$values, $id]);

    if ($table === 'student_subscription_payments') {
        $statement = $pdo->prepare('SELECT subscription_month_id FROM student_subscription_payments WHERE id = ?');
        $statement->execute([$id]);
        $currentPaymentMonthId = (int) $statement->fetchColumn();
        foreach (array_unique([$previousPaymentMonthId, $currentPaymentMonthId]) as $subscriptionMonthId) {
            admin_sync_subscription_month_payment($pdo, (int) $subscriptionMonthId);
        }
    }

    return $id;
}

function admin_delete_records(PDO $pdo, string $table, array $recordIds, int $currentUserId): int
{
    if (!in_array($table, array_values(admin_view_tables()), true)) {
        throw new RuntimeException('Invalid database table.');
    }

    $recordIds = array_values(array_unique(array_filter(
        array_map('intval', $recordIds),
        static fn (int $id): bool => $id > 0
    )));

    if ($recordIds === []) {
        throw new RuntimeException('Select at least one record to delete.');
    }

    if ($table === 'users' && in_array($currentUserId, $recordIds, true)) {
        throw new RuntimeException('You cannot delete the administrator account currently in use.');
    }

    $placeholders = implode(', ', array_fill(0, count($recordIds), '?'));
    $uploadedPaths = [];
    $uploadColumns = admin_upload_columns($table);
    $paymentMonthIds = [];
    if ($table === 'student_subscription_payments') {
        $monthStatement = $pdo->prepare(
            'SELECT DISTINCT subscription_month_id FROM student_subscription_payments WHERE id IN (' . $placeholders . ')'
        );
        $monthStatement->execute($recordIds);
        $paymentMonthIds = array_map('intval', array_column($monthStatement->fetchAll(), 'subscription_month_id'));
    }
    if ($uploadColumns !== []) {
        $pathStatement = $pdo->prepare(
            'SELECT ' . implode(', ', array_map('admin_quote_identifier', $uploadColumns))
            . ' FROM ' . admin_quote_identifier($table)
            . ' WHERE id IN (' . $placeholders . ')'
        );
        $pathStatement->execute($recordIds);
        foreach ($pathStatement->fetchAll() as $pathRow) {
            foreach ($uploadColumns as $column) {
                if (!empty($pathRow[$column])) {
                    $uploadedPaths[] = (string) $pathRow[$column];
                }
            }
        }
    }

    $statement = $pdo->prepare(
        'DELETE FROM ' . admin_quote_identifier($table) . ' WHERE id IN (' . $placeholders . ')'
    );

    try {
        $pdo->beginTransaction();
        $statement->execute($recordIds);
        $deletedCount = $statement->rowCount();
        $pdo->commit();
        admin_remove_uploaded_files($uploadedPaths);
        foreach ($paymentMonthIds as $subscriptionMonthId) {
            admin_sync_subscription_month_payment($pdo, $subscriptionMonthId);
        }

        return $deletedCount;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ((string) $exception->getCode() === '23000') {
            throw new RuntimeException(
                'This record cannot be deleted because other database records still depend on it.'
            );
        }

        throw $exception;
    }
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['admin_csrf'];
}

function admin_verify_csrf(): void
{
    if (!hash_equals(admin_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('The form session expired. Please try again.');
    }
}

/**
 * Applies the saved collapsed state while the page is still parsing.
 *
 * The panel is collapsed by a class on .admin-shell, and .admin-sidebar animates its
 * width. Waiting for the deferred admin.js meant every page load painted the panel
 * open and then animated it shut. This runs synchronously as the first child of the
 * shell, so the sidebar's very first layout is already correct.
 */
function admin_sidebar_boot_script(): void
{
    ?>
    <script>
      try {
        if (localStorage.getItem('khotwa-v12-admin-sidebar-collapsed') === 'true') {
          document.currentScript.parentElement.classList.add('is-sidebar-collapsed');
        }
      } catch (error) {
        /* private mode or storage disabled: fall back to the expanded panel */
      }
    </script>
    <?php
}

function admin_render_sidebar(array $user, string $activeView): void
{
    $isManager = ($user['role'] ?? '') === 'manager';
    $groups = [];
    if ($isManager) {
        $groups['Workspace']['manager-dashboard'] = [
            'label' => 'Dashboard',
            'group' => 'Workspace',
            'href' => khotwa_url('manager/index.php'),
            'icon' => 'overview',
        ];
    }
    foreach (admin_navigation_for_user($user) as $key => $item) {
        if (($item['sidebar'] ?? true) === false) {
            continue;
        }
        $groups[$item['group']][$key] = $item;
    }
    if (isset(admin_website_workspace_views()[$activeView])) {
        $activeView = 'website-content';
    }
    $brandHref = khotwa_url($isManager ? 'manager/index.php' : 'admin/index.php');
    $brandLabel = $isManager ? 'Khotwa management home' : 'Khotwa administration home';
    $brandSubline = $isManager ? 'Management' : 'Administration';
    $sidebarLabel = $isManager ? 'Manager navigation' : 'Administrator navigation';
    ?>
    <?php admin_sidebar_boot_script(); ?>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="<?= e($sidebarLabel) ?>">
      <div class="sidebar-top">
        <a class="admin-brand" href="<?= e($brandHref) ?>" aria-label="<?= e($brandLabel) ?>">
          <?php // The logo carries the name, so the wordmark replaces the "Khotwa" text. ?>
          <span class="admin-brand-mark admin-brand-mark-logo">
            <img
              class="admin-brand-logo"
              src="<?= e(khotwa_asset('images/logo-white.svg')) ?>"
              alt="Khotwa"
              width="148"
              height="71"
            >
          </span>
          <span class="admin-brand-copy"><small><?= e($brandSubline) ?></small></span>
        </a>
        <div class="sidebar-top-actions">
          <?php // Language and logout live beside the brand; the footer keeps only the account. ?>
          <button
            class="sidebar-top-action sidebar-language-compact"
            type="button"
            title="Switch language"
            aria-label="Switch language"
            data-language-toggle
          ><strong data-language-current>EN</strong></button>
          <a
            class="sidebar-top-action"
            href="<?= e(khotwa_url('logout.php')) ?>"
            title="Log out"
            aria-label="Log out"
          ><?= admin_icon('logout') ?></a>
        </div>
        <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
          <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
          <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
      <nav class="admin-nav">
        <?php foreach ($groups as $groupName => $items): ?>
          <section class="nav-group">
            <h2><?= e($groupName) ?></h2>
            <?php foreach ($items as $key => $item): ?>
              <?php
              $isActive = $key === 'manager-dashboard' ? $activeView === 'overview' : $activeView === $key;
              $href = $item['href'] ?? (khotwa_url('admin/index.php') . '?view=' . $key);
              $icon = $item['icon'] ?? $key;
              ?>
              <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($href) ?>" title="<?= e($item['label']) ?>">
                <?= admin_icon($icon) ?><span><?= e($item['label']) ?></span>
                <?php if ($isActive): ?><i></i><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </nav>
    </aside>
    <?php
}
