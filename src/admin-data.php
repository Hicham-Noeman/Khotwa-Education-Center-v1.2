<?php
declare(strict_types=1);

function admin_navigation(): array
{
    return [
        'overview' => ['label' => 'Overview', 'group' => 'Workspace'],
        'students' => ['label' => 'Students', 'group' => 'People', 'sidebar_label' => 'Users'],
        'teachers' => ['label' => 'Teachers', 'group' => 'People', 'sidebar' => false],
        'parent-links' => ['label' => 'Parents', 'group' => 'People', 'sidebar' => false],
        'nationalities' => ['label' => 'Nationalities', 'group' => 'People', 'sidebar' => false],
        'attendance' => ['label' => 'Attendance', 'group' => 'Academics'],
        'subjects' => ['label' => 'Subjects', 'group' => 'Academics', 'sidebar' => false],
        'subscriptions' => ['label' => 'Subscriptions', 'group' => 'Finance'],
        'payments' => ['label' => 'Payments', 'group' => 'Finance', 'sidebar' => false],
        'enrollments' => ['label' => 'Enrollments', 'group' => 'Finance', 'sidebar' => false],
        'warnings' => ['label' => 'Warnings', 'group' => 'Management'],
        'expiations' => ['label' => 'Expiations', 'group' => 'Expiations'],
        'expiation-categories' => ['label' => 'Categories', 'group' => 'Expiations', 'sidebar' => false],
        'age-groups' => ['label' => 'Age Groups', 'group' => 'Expiations', 'sidebar' => false],
        'website-content' => ['label' => 'Website Content', 'group' => 'Website'],
        'website-slides' => ['label' => 'Vision Slides', 'group' => 'Website', 'sidebar' => false],
        'website-statistics' => ['label' => 'Statistics', 'group' => 'Website', 'sidebar' => false],
        'website-gallery' => ['label' => 'Gallery Images', 'group' => 'Website', 'sidebar' => false],
        'website-partners' => ['label' => 'Partner Logos', 'group' => 'Website', 'sidebar' => false],
        'website-reviews' => ['label' => 'Parent Reviews', 'group' => 'Website', 'sidebar' => false],
        'website-contacts' => ['label' => 'Contact & Social', 'group' => 'Website', 'sidebar' => false],
    ];
}

function admin_manager_allowed_views(): array
{
    return [
        'students',
        'teachers',
        'nationalities',
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
        'nationalities' => 'nationalities',
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
        'parent_students' => 'Parents',
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

/**
 * Icon name for a profile section, so the tab strip can show icons instead of
 * thirteen text labels that will not fit on one line.
 */
function admin_profile_section_icon(string $sectionKey): string
{
    return match ($sectionKey) {
        'main' => 'students',
        'users' => 'users',
        'teacher_subjects' => 'subjects',
        'student_academic_records' => 'academic-record',
        'student_medical_info' => 'medical',
        'student_other_phone_numbers' => 'phone',
        'student_school_schedule' => 'schedule',
        'parent_students' => 'parent-links',
        'student_subject_enrollments' => 'enrollments',
        'student_daily_attendance' => 'attendance',
        'student_subject_attendance' => 'subjects',
        'student_warnings' => 'warnings',
        'student_subscriptions', 'student_subscription_months' => 'subscriptions',
        'student_subscription_payments' => 'payments',
        default => 'overview',
    };
}

function admin_icon(string $name): string
{
    $paths = [
        'academic-record' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'medical' => '<path d="M12 21s-7-4.5-7-10a4.5 4.5 0 0 1 7-3.7A4.5 4.5 0 0 1 19 11c0 5.5-7 10-7 10Z"/><path d="M12 9.5v4M10 11.5h4"/>',
        'phone' => '<path d="M7 3h3l1.6 4-2 1.5a11 11 0 0 0 5.9 5.9l1.5-2L21 14v3a2 2 0 0 1-2.2 2A16 16 0 0 1 5 5.2A2 2 0 0 1 7 3Z"/>',
        'schedule' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/><path d="M8 15h3M8 18h6"/>',
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
        'website-gallery' => 'gallery-images',
        'website-partners' => 'partner-logos',
    ];
}

/**
 * Views that belong together on one page, shown as a tab strip above the table
 * instead of separate sidebar entries. The first view in a group owns the
 * sidebar entry; the rest are reachable through the tabs.
 *
 * @return array<int, array<string, string>>
 */
function admin_workspace_tab_groups(): array
{
    return [
        [
            'students' => 'Students',
            'teachers' => 'Teachers',
            'parent-links' => 'Parents',
            'nationalities' => 'Nationalities',
        ],
        [
            'attendance' => 'Attendance',
            'subjects' => 'Subjects',
        ],
        [
            'subscriptions' => 'Subscriptions',
            'payments' => 'Payments',
            'enrollments' => 'Enrollments',
        ],
        [
            'expiations' => 'Expiations',
            'expiation-categories' => 'Categories',
            'age-groups' => 'Age Groups',
        ],
        [
            'website-content' => 'Website Content',
            'website-reviews' => 'Parent Reviews',
            'website-contacts' => 'Contact & Social',
        ],
    ];
}

/**
 * The tab strip this view belongs to, limited to the views the user may open.
 * Empty when the view stands on its own.
 *
 * @return array<string, string>
 */
function admin_workspace_tabs(string $view, array $user): array
{
    foreach (admin_workspace_tab_groups() as $group) {
        if (!isset($group[$view])) {
            continue;
        }

        $allowed = array_filter(
            $group,
            static fn (string $tabView): bool => admin_user_can_access_view($user, $tabView),
            ARRAY_FILTER_USE_KEY
        );

        return count($allowed) > 1 ? $allowed : [];
    }

    return [];
}

/**
 * The tab strip for a workspace. Standalone mode is for a page that is not a
 * single card underneath: the strip closes itself off instead of joining a pane.
 *
 * @param array<string, string> $tabs   view => label, from admin_workspace_tabs()
 * @param array<string, int> $counts    row count per view, where one is worth showing
 */
function admin_render_workspace_tabs(
    array $tabs,
    string $activeView,
    array $counts = [],
    bool $standalone = false
): void {
    if ($tabs === []) {
        return;
    }

    $stripClass = 'profile-tabs workspace-tabs' . ($standalone ? ' workspace-tabs-standalone' : '');
    ?>
    <nav class="<?= e($stripClass) ?>" aria-label="Workspace sections">
      <?php foreach ($tabs as $tabView => $tabLabel): ?>
        <a
          class="profile-tab<?= $tabView === $activeView ? ' is-active' : '' ?>"
          href="<?= e(khotwa_url('admin/index.php')) ?>?view=<?= e($tabView) ?>"
          <?= $tabView === $activeView ? 'aria-current="page"' : '' ?>
          title="<?= e($tabLabel) ?>"
        >
          <?= admin_icon($tabView) ?><span><?= e($tabLabel) ?></span>
          <?php if (isset($counts[$tabView])): ?>
            <i><?= e((string) $counts[$tabView]) ?></i>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * The sidebar entry that owns this view, so browsing a tab keeps its group lit.
 */
function admin_sidebar_view(string $view): string
{
    if (isset(admin_website_workspace_views()[$view])) {
        return 'website-content';
    }

    foreach (admin_workspace_tab_groups() as $group) {
        if (isset($group[$view])) {
            return (string) array_key_first($group);
        }
    }

    return $view;
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
        'show_on_website' => 'Show On Website',
        'stat_key' => 'Statistic Key',
        'stat_value' => 'Number',
        'parent_message' => 'Message To Parent',
        'link_key' => 'Link Key',
        'link_type' => 'Link Type',
        'layout_style' => 'Layout',
        'contact_url' => 'Contact Link',
        'joined_center_on' => 'At The Center Since',
        'video_url' => 'Video Link',
        'website_url' => 'Website Link',
        'parent_user_id' => 'Parent User',
        'nationality_id' => 'Nationality',
        // The teacher's own name in Arabic, shown on the Arabic side of the website.
        'first_name_ar' => 'First Name AR',
        'last_name_ar' => 'Last Name AR',
        // The three Lebanese school stages, named the way the center refers to them.
        'teaches_primary' => 'Teaches Primary (Ibtidai)',
        'teaches_intermediate' => 'Teaches Intermediate (Mutawassit)',
        'teaches_secondary' => 'Teaches Secondary (Sanawi)',
        'teaching_since' => 'Teaching Since (Start Date)',
        'joined_center_on' => 'At The Center Since',
        'certifications_en' => 'Certifications EN',
        'certifications_ar' => 'Certifications AR',
        'is_teacher_of_the_month' => 'Teacher Of The Month',
        'video_url' => 'YouTube Video Link',
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
        'nationality_id' => "SELECT id, CONCAT(name_en, ' / ', name_ar) label FROM nationalities ORDER BY sort_order, name_en",
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

    if ($column['COLUMN_DEFAULT'] === null) {
        return '';
    }

    $default = (string) $column['COLUMN_DEFAULT'];

    // MariaDB reports "no default" on a nullable column as the four letters NULL,
    // which would otherwise be pre-filled into the field and saved as that text.
    if ($default === 'NULL' && $column['IS_NULLABLE'] === 'YES') {
        return '';
    }

    // String and enum defaults arrive quoted ('guardian'), and a quoted value never
    // matches the option it belongs to, so the field fell back to the first choice.
    if (strlen($default) >= 2 && str_starts_with($default, "'") && str_ends_with($default, "'")) {
        $default = substr($default, 1, -1);
        $default = str_replace(["\'", "''"], "'", $default);
    }

    return $default;
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

/**
 * Columns the database maintains itself: never editable and never worth showing.
 */
function admin_system_columns(): array
{
    return ['id', 'created_at', 'updated_at'];
}

function admin_render_field(
    PDO $pdo,
    string $table,
    array $column,
    mixed $value = '',
    array $locked = [],
    bool $showHidden = false,
    array $hiddenOnly = []
): void
{
    $name = (string) $column['COLUMN_NAME'];
    if (!$showHidden && in_array($name, admin_hidden_derived_columns($table), true)) {
        return;
    }

    if (in_array($name, admin_system_columns(), true)) {
        return;
    }

    // Submitted but not shown: the parent record already establishes this link.
    if (in_array($name, $hiddenOnly, true)) {
        $hiddenValue = array_key_exists($name, $locked) ? $locked[$name] : $value;
        echo '<input type="hidden" name="fields[' . e($name) . ']" value="' . e((string) $hiddenValue) . '">';
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

/**
 * English name columns that are capitalised on save, per table.
 *
 * Arabic columns are left alone: the script has no letter case.
 *
 * @return array<int, string>
 */
function admin_name_columns(string $table): array
{
    return match ($table) {
        'students' => [
            'first_name_en', 'father_name_en', 'last_name_en',
            'mother_name_en', 'mother_last_name_en',
        ],
        'teachers' => ['first_name', 'last_name'],
        default => [],
    };
}

/**
 * Capitalises a person's name without rewriting what was typed.
 *
 * Only the first letter of each part is raised, and only when it is lower case, so
 * "al-masri" becomes "Al-Masri" while a deliberate "McDonald" or an all-caps
 * surname survives untouched. mb_* throughout, since a name can carry accents.
 */
function admin_capitalize_name(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    // Split on the separators that begin a new part of a name, keeping them in place.
    $parts = preg_split("/([ \-'\x{2019}])/u", $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $value;
    }

    foreach ($parts as $index => $part) {
        if ($part === '') {
            continue;
        }
        $parts[$index] = mb_strtoupper(mb_substr($part, 0, 1)) . mb_substr($part, 1);
    }

    return implode('', $parts);
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
        if (is_string($value) && in_array($name, admin_name_columns($table), true)) {
            $value = admin_capitalize_name($value);
        }
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

/**
 * Creates a parent portal account, optionally linked to a student straight away.
 *
 * Registering a family used to mean a detour through the Users table and back.
 * When a student is named, both rows are written in one transaction: a
 * half-created parent with no child attached is easy to leave behind.
 *
 * @param int $studentId student to link to, or 0 to create the account on its own
 * @param array<string, mixed> $input first_name, last_name, email, password, status
 * @return array{user_id: int, link_id: int, name: string}
 */
function admin_create_parent_account_link(PDO $pdo, int $studentId, array $input): array
{
    $firstName = admin_capitalize_name((string) ($input['first_name'] ?? ''));
    $lastName = admin_capitalize_name((string) ($input['last_name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $status = (string) ($input['status'] ?? 'active');

    if ($firstName === '') {
        throw new RuntimeException('Enter the parent first name.');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Enter a valid email address for the parent account.');
    }
    if (mb_strlen($password) < 8) {
        throw new RuntimeException('The temporary password must be at least 8 characters long.');
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $exists = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($row = $exists->fetch()) {
        throw new RuntimeException(
            $row['role'] === 'parent'
                ? 'That email already belongs to a parent account. Link that account to the student instead.'
                : 'That email is already used by another portal account.'
        );
    }

    if ($studentId > 0) {
        $student = $pdo->prepare('SELECT COUNT(*) FROM students WHERE id = ?');
        $student->execute([$studentId]);
        if ((int) $student->fetchColumn() === 0) {
            throw new RuntimeException('That student no longer exists.');
        }
    }

    // The caller may already have a transaction open (creating a student and its
    // parent together), and MySQL has no nested transactions, so only the outermost
    // owner starts and finishes one.
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        // The admin picks the first password, so the parent is made to replace it
        // the first time they sign in.
        $pdo->prepare(
            "INSERT INTO users (
                teacher_id, first_name, last_name, email, password_hash,
                role, status, must_change_password, notes
             ) VALUES (NULL, ?, ?, ?, ?, 'parent', ?, 1, ?)"
        )->execute([
            $firstName,
            $lastName === '' ? null : $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $status,
            'Parent account created from a student profile',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $linkId = 0;
        if ($studentId > 0) {
            $pdo->prepare(
                'INSERT INTO parent_students (parent_user_id, student_id, status)
                 VALUES (?, ?, ?)'
            )->execute([$userId, $studentId, $status]);
            $linkId = (int) $pdo->lastInsertId();
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'user_id' => $userId,
        'link_id' => $linkId,
        'name' => trim($firstName . ' ' . $lastName),
    ];
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

/**
 * Rows shown on one page of a table view.
 *
 * The list views used to print every row, which meant 1.6 MB of HTML for the
 * attendance table and no upper bound as the centre keeps adding records. The
 * search runs here, over the whole result set, so narrowing a table still looks
 * at every row and not only at the page that happens to be loaded.
 *
 * @param array<int, array<string, mixed>> $rows every row the view selected
 * @return array{rows: array<int, array<string, mixed>>, page: int, pages: int, total: int, matched: int, first: int, last: int, query: string, perPage: int}
 */
function admin_paginate_rows(array $rows, string $query = '', int $page = 1, int $perPage = 100): array
{
    $total = count($rows);
    $query = trim($query);

    if ($query !== '') {
        $needle = mb_strtolower($query);
        $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            foreach ($row as $value) {
                if (is_scalar($value) && mb_strpos(mb_strtolower((string) $value), $needle) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    $matched = count($rows);
    $pages = max(1, (int) ceil($matched / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    return [
        'rows' => array_slice($rows, $offset, $perPage),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'matched' => $matched,
        'first' => $matched === 0 ? 0 : $offset + 1,
        'last' => min($offset + $perPage, $matched),
        'query' => $query,
        'perPage' => $perPage,
    ];
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
    $activeView = admin_sidebar_view($activeView);
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
              // An entry that opens a tabbed workspace can be named for the whole set
              // of tables rather than for the one table its page happens to show.
              $sidebarLabel = (string) ($item['sidebar_label'] ?? $item['label']);
              ?>
              <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($href) ?>" title="<?= e($sidebarLabel) ?>">
                <?= admin_icon($icon) ?><span><?= e($sidebarLabel) ?></span>
                <?php if ($isActive): ?><i></i><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </nav>
    </aside>
    <?php
}
