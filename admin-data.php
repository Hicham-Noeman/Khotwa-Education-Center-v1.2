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
    ];
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
        'student_subject_enrollments' => 'Subject enrollments',
        'student_daily_attendance' => 'Daily attendance',
        'student_subject_attendance' => 'Subject attendance',
        'student_warnings' => 'Warnings',
        'student_subscriptions' => 'Subscriptions',
        'student_subscription_months' => 'Subscription months',
        'student_subscription_payments' => 'Payments',
    ];
}

function admin_icon(string $name): string
{
    $paths = [
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
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['overview']) . '</svg>';
}

function admin_table_label(string $table): string
{
    return ucwords(str_replace('_', ' ', $table));
}

function admin_column_label(string $column): string
{
    $special = ['id' => 'ID', 'en' => 'EN', 'ar' => 'AR'];
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
    $statement = $pdo->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $statement->execute([$table]);

    return $statement->fetchAll();
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
    $queries = [
        'student_id' => "SELECT id, CONCAT(first_name_en, ' ', last_name_en) label FROM students ORDER BY last_name_en, first_name_en",
        'teacher_id' => "SELECT id, TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) label FROM teachers ORDER BY last_name, first_name",
        'subject_id' => "SELECT id, name_en label FROM subjects ORDER BY name_en",
        'teacher_subject_id' => "SELECT teacher_subjects.id, CONCAT(TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))), ' / ', subjects.name_en) label FROM teacher_subjects INNER JOIN teachers ON teachers.id = teacher_subjects.teacher_id INNER JOIN subjects ON subjects.id = teacher_subjects.subject_id ORDER BY label",
        'daily_attendance_id' => "SELECT student_daily_attendance.id, CONCAT(student_daily_attendance.attendance_date, ' / ', students.first_name_en, ' ', students.last_name_en) label FROM student_daily_attendance INNER JOIN students ON students.id = student_daily_attendance.student_id ORDER BY student_daily_attendance.attendance_date DESC",
        'subscription_id' => "SELECT student_subscriptions.id, CONCAT(students.first_name_en, ' ', students.last_name_en, ' / ', student_subscriptions.start_date) label FROM student_subscriptions INNER JOIN students ON students.id = student_subscriptions.student_id ORDER BY students.last_name_en, student_subscriptions.start_date DESC",
        'subscription_month_id' => "SELECT student_subscription_months.id, CONCAT(students.first_name_en, ' ', students.last_name_en, ' / ', student_subscription_months.billing_year, '-', LPAD(student_subscription_months.billing_month, 2, '0')) label FROM student_subscription_months INNER JOIN students ON students.id = student_subscription_months.student_id ORDER BY student_subscription_months.billing_year DESC, student_subscription_months.billing_month DESC",
    ];

    if (!isset($queries[$column])) {
        return [];
    }

    return $pdo->query($queries[$column])->fetchAll();
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

function admin_render_field(PDO $pdo, string $table, array $column, mixed $value = '', array $locked = []): void
{
    $name = (string) $column['COLUMN_NAME'];
    if (in_array($name, admin_hidden_derived_columns($table), true)) {
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

    echo '<label class="admin-field"><span>' . e($label) . '</span>';

    if ($isLocked) {
        $lockedLabel = (string) $value;
        foreach ($options as $option) {
            if ((string) $option['id'] === (string) $value) {
                $lockedLabel = (string) $option['label'];
                break;
            }
        }
        echo '<input type="hidden" name="fields[' . e($name) . ']" value="' . e((string) $value) . '">';
        echo '<input type="text" value="' . e($lockedLabel) . '" disabled>';
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
        echo '<textarea name="fields[' . e($name) . ']"' . $required . '>' . e((string) $value) . '</textarea>';
    } else {
        $inputType = match ($type) {
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'time' => 'time',
            'int', 'bigint', 'smallint', 'tinyint', 'decimal' => 'number',
            default => $name === 'email' ? 'email' : ($name === 'password_hash' ? 'password' : 'text'),
        };
        $step = $type === 'decimal' ? ' step="0.01"' : '';
        echo '<input type="' . e($inputType) . '" name="fields[' . e($name) . ']" value="' . e((string) $value) . '"' . $step . $required . '>';
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

function admin_save_record(PDO $pdo, string $table, array $fields, ?int $id = null): int
{
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
        return (int) $pdo->lastInsertId();
    }

    $assignments = implode(', ', array_map(
        static fn (string $name): string => admin_quote_identifier($name) . ' = ?',
        $names
    ));
    $pdo->prepare(
        'UPDATE ' . admin_quote_identifier($table) . ' SET ' . $assignments . ' WHERE id = ?'
    )->execute([...$values, $id]);

    return $id;
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

function admin_render_sidebar(array $user, string $activeView): void
{
    $groups = [];
    foreach (admin_navigation() as $key => $item) {
        $groups[$item['group']][$key] = $item;
    }
    ?>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Administrator navigation">
      <div class="sidebar-top">
        <a class="admin-brand" href="admin.php" aria-label="Khotwa administration home">
          <span class="admin-brand-mark">K<span>.</span></span>
          <span class="admin-brand-copy"><strong>Khotwa</strong><small>Administration</small></span>
        </a>
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
              <a class="<?= $activeView === $key ? 'is-active' : '' ?>" href="admin.php?view=<?= e($key) ?>" title="<?= e($item['label']) ?>">
                <?= admin_icon($key) ?><span><?= e($item['label']) ?></span>
                <?php if ($activeView === $key): ?><i></i><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <button class="sidebar-language" type="button" data-language-toggle>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
          <span><strong data-language-current>EN</strong><small data-language-label>العربية</small></span>
        </button>
        <div class="sidebar-account">
          <span class="account-avatar"><?= e(strtoupper(substr((string) $user['first_name'], 0, 1))) ?></span>
          <span class="account-copy"><strong><?= e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?></strong><small>Administrator</small></span>
          <a href="logout.php" aria-label="Log out" title="Log out"><?= admin_icon('users') ?></a>
        </div>
      </div>
    </aside>
    <?php
}
