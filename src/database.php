<?php
declare(strict_types=1);

/*
 * Khotwa Education Center database bootstrap.
 *
 * Default XAMPP MySQL settings are used here:
 * host: localhost
 * user: root
 * pass: empty
 *
 * Include this file in PHP pages to get a ready PDO connection:
 * require_once __DIR__ . '/../src/database.php';
 */

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/homepage-texts.php';

$dbHost = '127.0.0.1';
// XAMPP's MariaDB runs on 3307 here because a separate Windows "MySQL80" service
// holds the default 3306. Set this back to 3306 once MariaDB owns that port again.
$dbPort = 3307;
$dbName = 'khotwa_education_center';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

// A deployed copy keeps its own credentials in src/db-config.php, which is never
// committed, so a push can never overwrite them and they never reach GitHub. On this
// machine that file does not exist and the local XAMPP values above are used as-is.
$khotwaLocalConfig = __DIR__ . '/db-config.php';
if (is_file($khotwaLocalConfig)) {
    $khotwaConfig = require $khotwaLocalConfig;
    if (is_array($khotwaConfig)) {
        $dbHost = (string) ($khotwaConfig['host'] ?? $dbHost);
        $dbPort = (int) ($khotwaConfig['port'] ?? $dbPort);
        $dbName = (string) ($khotwaConfig['name'] ?? $dbName);
        $dbUser = (string) ($khotwaConfig['user'] ?? $dbUser);
        $dbPass = (string) ($khotwaConfig['pass'] ?? $dbPass);
    }
}

// The center's real contact details. Kept here so the seed for a fresh database and
// the migration that replaces the old placeholders always agree. Editing them in the
// admin panel afterwards is what changes the live site; these are only the starting
// values.
const KHOTWA_CENTER_PHONE_DISPLAY = '+961 79 42 79 40';
const KHOTWA_CENTER_PHONE_E164 = '+96179427940';
const KHOTWA_CENTER_WHATSAPP_URL = 'https://wa.me/96179427940';
const KHOTWA_CENTER_INSTAGRAM_URL = 'https://www.instagram.com/khotwa_center.lb/';
const KHOTWA_CENTER_FACEBOOK_URL = 'https://www.facebook.com/profile.php?id=61551043138729';
const KHOTWA_CENTER_ADDRESS_EN = 'Tripoli, Lebanon';
const KHOTWA_CENTER_ADDRESS_AR = 'طرابلس، لبنان';
const KHOTWA_CENTER_MAP_URL = 'https://maps.google.com/?q=Tripoli%2C+Lebanon';
const KHOTWA_CENTER_HOURS_EN = 'Mon-Thu & Sat, 3:00-8:00 PM';
const KHOTWA_CENTER_HOURS_AR = 'الاثنين–الخميس والسبت، 3:00–8:00 مساءً';

// Increment this only when a release needs createKhotwaTables/applyKhotwaMigrations again.
const KHOTWA_SCHEMA_VERSION = 24;

function getDatabaseConnection(): PDO
{
    global $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbCharset;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ];

    $databaseDsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
    try {
        $pdo = new PDO($databaseDsn, $dbUser, $dbPass, $options);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1049) {
            throw $exception;
        }

        $serverDsn = "mysql:host={$dbHost};port={$dbPort};charset={$dbCharset}";
        $serverConnection = new PDO($serverDsn, $dbUser, $dbPass, $options);
        $serverConnection->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}`
             CHARACTER SET {$dbCharset}
             COLLATE {$dbCharset}_unicode_ci"
        );
        $pdo = new PDO($databaseDsn, $dbUser, $dbPass, $options);
    }

    ensureKhotwaSchema($pdo);

    return $pdo;
}

function ensureKhotwaSchema(PDO $pdo): void
{
    try {
        $schemaVersion = (int) $pdo->query(
            'SELECT schema_version FROM khotwa_schema_meta WHERE id = 1'
        )->fetchColumn();
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '42S02') {
            throw $exception;
        }
        $schemaVersion = 0;
    }

    if ($schemaVersion >= KHOTWA_SCHEMA_VERSION) {
        return;
    }

    createKhotwaTables($pdo);
    // The nationality list has to exist before the migration can map the old
    // free-text values onto it.
    seedNationalityDefaults($pdo);
    applyKhotwaMigrations($pdo);
    seedHomepageContentDefaults($pdo);
    seedHomepageCollectionsDefaults($pdo);
    seedHomepageSettingsDefaults($pdo);
    seedHomepageTextDefaults($pdo);
    seedManagerAccountDefault($pdo);
    seedExpiationDefaults($pdo);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS khotwa_schema_meta (
            id TINYINT UNSIGNED NOT NULL,
            schema_version INT UNSIGNED NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $statement = $pdo->prepare(
        'INSERT INTO khotwa_schema_meta (id, schema_version) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version)'
    );
    $statement->execute([KHOTWA_SCHEMA_VERSION]);
}

function seedManagerAccountDefault(PDO $pdo): void
{
    $statement = $pdo->prepare(
        "INSERT IGNORE INTO users (
            teacher_id, first_name, last_name, email, password_hash,
            role, status, must_change_password, notes
         ) VALUES (NULL, ?, ?, ?, ?, 'manager', 'active', 0, ?)"
    );
    $statement->execute([
        'Khotwa',
        'Manager',
        'manager@khotwa.test',
        password_hash('manager123', PASSWORD_DEFAULT),
        'Management dashboard and operations account',
    ]);
}

/**
 * Adds any seed sentence the table does not carry yet, and never touches one that
 * is already there: a wording the administration has edited stays edited, while a
 * key added in a later version still arrives on the next load.
 */
function seedHomepageTextDefaults(PDO $pdo): void
{
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO homepage_texts (text_key, section, sort_order, value_en, value_ar)
         VALUES (?, ?, ?, ?, ?)'
    );

    foreach (khotwa_homepage_text_defaults() as $row) {
        // The array is ordered key, section, sort, en, ar - the statement wants the
        // same order, so it can be passed straight through.
        $statement->execute([$row[0], $row[1], $row[2], $row[3], $row[4]]);
    }
}

function seedHomepageSettingsDefaults(PDO $pdo): void
{
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO homepage_settings (setting_key, setting_value) VALUES (?, ?)'
    );

    $defaults = [
        'admissions_banner_visible' => '1',
        // Drives the "years of experience" counter; editable from the admin website workspace.
        'founding_date' => '2014-09-01',
    ];

    foreach ($defaults as $key => $value) {
        $statement->execute([$key, $value]);
    }
}

/**
 * Login throttling. Only failures are recorded, and a successful login clears them,
 * so a legitimate user who mistypes once is never held back for long.
 */
function login_attempts_recent(PDO $pdo, string $ip, string $email): array
{
    $statement = $pdo->prepare(
        'SELECT
            SUM(ip_address = ?) AS from_ip,
            SUM(email = ?) AS for_email
         FROM login_attempts
         WHERE attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $statement->execute([$ip, $email]);
    $row = $statement->fetch() ?: [];

    return [
        'ip' => (int) ($row['from_ip'] ?? 0),
        'email' => (int) ($row['for_email'] ?? 0),
    ];
}

function login_attempt_record(PDO $pdo, string $ip, string $email): void
{
    $pdo->prepare('INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())')
        ->execute([$ip, mb_substr($email, 0, 190)]);
    // Keep the table small; yesterday's failures are of no further use.
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function login_attempts_clear(PDO $pdo, string $ip, string $email): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? OR email = ?')
        ->execute([$ip, mb_substr($email, 0, 190)]);
}

function homepage_setting(PDO $pdo, string $key, string $default = ''): string
{
    $statement = $pdo->prepare('SELECT setting_value FROM homepage_settings WHERE setting_key = ? LIMIT 1');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();

    return $value === false ? $default : (string) $value;
}

/**
 * One contact link's URL, straight from the admin-managed list.
 *
 * Pages outside the homepage need the odd link - the WhatsApp number on the login
 * page, for one - without loading the whole homepage payload. The fallback covers
 * a row that was deleted or switched off, so a link never renders empty.
 */
function homepage_contact_link_url(PDO $pdo, string $key, string $fallback = ''): string
{
    $statement = $pdo->prepare(
        "SELECT url FROM homepage_contact_links
         WHERE link_key = ? AND status = 'active'
         LIMIT 1"
    );
    $statement->execute([$key]);
    $url = trim((string) $statement->fetchColumn());

    return $url === '' ? $fallback : $url;
}

function homepage_setting_save(PDO $pdo, string $key, string $value): void
{
    $statement = $pdo->prepare(
        'INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute([$key, $value]);
}

function seedNationalityDefaults(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM nationalities')->fetchColumn() > 0) {
        return;
    }

    $nationalities = [
        ['Lebanese', 'لبناني', 1],
        ['Syrian', 'سوري', 2],
        ['Palestinian', 'فلسطيني', 3],
    ];
    $insertNationality = $pdo->prepare(
        'INSERT INTO nationalities (name_en, name_ar, sort_order) VALUES (?, ?, ?)'
    );
    foreach ($nationalities as $nationality) {
        $insertNationality->execute($nationality);
    }
}

function seedExpiationDefaults(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM age_groups')->fetchColumn() === 0) {
        $ageGroups = [
            ['Kids', 'أطفال', 6, 9, 1],
            ['Youth', 'ناشئة', 10, 13, 2],
            ['Teens', 'يافعون', 14, 17, 3],
            ['Adults', 'كبار', 18, 99, 4],
        ];
        $insertAgeGroup = $pdo->prepare(
            'INSERT INTO age_groups (name_en, name_ar, min_age, max_age, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($ageGroups as $group) {
            $insertAgeGroup->execute($group);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM expiation_categories')->fetchColumn() === 0) {
        $categories = [
            ['Quran memorization', 'حفظ القرآن', 'Memorising verses or short surahs.', 'حفظ آيات أو سور قصيرة.', 1],
            ['Written reflection', 'تأمّل كتابي', 'Writing a short reflection or apology letter.', 'كتابة تأمّل قصير أو رسالة اعتذار.', 2],
            ['Community service', 'خدمة المجتمع', 'Helping with a task at the center or at home.', 'المساعدة في مهمة داخل المركز أو المنزل.', 3],
            ['Extra worship', 'عبادات إضافية', 'Extra prayers, dhikr, or charity.', 'صلوات أو أذكار أو صدقة إضافية.', 4],
        ];
        $insertCategory = $pdo->prepare(
            'INSERT INTO expiation_categories (name_en, name_ar, description_en, description_ar, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($categories as $category) {
            $insertCategory->execute($category);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM expiations')->fetchColumn() === 0) {
        $categories = $pdo->query(
            'SELECT id, name_en, name_ar FROM expiation_categories ORDER BY sort_order, id'
        )->fetchAll();
        $ageGroups = $pdo->query(
            'SELECT id, name_en, name_ar FROM age_groups ORDER BY sort_order, id'
        )->fetchAll();

        $insertExpiation = $pdo->prepare(
            'INSERT INTO expiations (category_id, age_group_id, title_en, title_ar, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $sortOrder = 0;
        foreach ($categories as $category) {
            foreach ($ageGroups as $ageGroup) {
                $sortOrder++;
                $insertExpiation->execute([
                    (int) $category['id'],
                    (int) $ageGroup['id'],
                    $category['name_en'] . ' — ' . $ageGroup['name_en'] . ' level',
                    $category['name_ar'] . ' — لمستوى ' . $ageGroup['name_ar'],
                    $sortOrder,
                ]);
            }
        }
    }
}

/**
 * The per-day attendance rollup, as a SELECT rather than a database view.
 *
 * It reads as a table named student_daily_attendance_summary wherever it is used, so
 * the queries around it need no other change - and it requires no CREATE VIEW
 * privilege, which shared hosting does not grant.
 */
function khotwa_daily_attendance_summary_sql(): string
{
    return "(SELECT
                student_daily_attendance.id AS daily_attendance_id,
                students.id AS student_id,
                CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name_en,
                student_daily_attendance.attendance_date,
                student_daily_attendance.check_in_time,
                student_daily_attendance.check_out_time,
                student_daily_attendance.status AS daily_status,
                COUNT(student_subject_attendance.id) AS subject_session_count,
                SUM(CASE WHEN student_subject_attendance.status = 'attended' THEN 1 ELSE 0 END) AS attended_subject_count,
                SUM(CASE WHEN student_subject_attendance.status = 'missed' THEN 1 ELSE 0 END) AS missed_subject_count
            FROM student_daily_attendance
            INNER JOIN students ON students.id = student_daily_attendance.student_id
            LEFT JOIN student_subject_attendance
                ON student_subject_attendance.daily_attendance_id = student_daily_attendance.id
            GROUP BY
                student_daily_attendance.id,
                students.id,
                students.first_name_en,
                students.last_name_en,
                student_daily_attendance.attendance_date,
                student_daily_attendance.check_in_time,
                student_daily_attendance.check_out_time,
                student_daily_attendance.status) AS student_daily_attendance_summary";
}

function createKhotwaTables(PDO $pdo): void
{
    $queries = [
        // The center serves three nationalities, kept as an editable list so the
        // student form offers them as options instead of free text.
        "CREATE TABLE IF NOT EXISTS nationalities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_nationalities_name_en (name_en),
            INDEX idx_nationalities_status_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS students (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name_en VARCHAR(100) NOT NULL,
            father_name_en VARCHAR(100) NOT NULL,
            last_name_en VARCHAR(100) NOT NULL,
            mother_name_en VARCHAR(100) NOT NULL,
            mother_last_name_en VARCHAR(100) NOT NULL,
            first_name_ar VARCHAR(100) NOT NULL,
            father_name_ar VARCHAR(100) NOT NULL,
            last_name_ar VARCHAR(100) NOT NULL,
            mother_name_ar VARCHAR(100) NOT NULL,
            mother_last_name_ar VARCHAR(100) NOT NULL,
            photo_path VARCHAR(255) NULL,
            gender ENUM('male', 'female') NOT NULL,
            nationality_id BIGINT UNSIGNED NULL,
            blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
            date_of_birth DATE NOT NULL,
            address TEXT NULL,
            family_status ENUM('Married', 'Divorced', 'Widowed', 'Separated', 'Single') NOT NULL,
            number_of_people_in_household TINYINT UNSIGNED NOT NULL,
            current_teaching_language ENUM('French', 'English') NOT NULL,
            father_phone_number VARCHAR(30) NULL,
            mother_phone_number VARCHAR(30) NULL,
            home_phone_number VARCHAR(30) NULL,
            parents_assigned_to_whatsapp_group TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive', 'waiting', 'left', 'graduated') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_students_en_name (first_name_en, last_name_en),
            INDEX idx_students_ar_name (first_name_ar, last_name_ar),
            INDEX idx_students_whatsapp_group (parents_assigned_to_whatsapp_group),
            INDEX idx_students_status (status),
            INDEX idx_students_nationality (nationality_id),
            CONSTRAINT fk_students_nationality
                FOREIGN KEY (nationality_id) REFERENCES nationalities(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_other_phone_numbers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            relationship VARCHAR(100) NULL,
            person_full_name VARCHAR(150) NULL,
            phone_number VARCHAR(30) NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_other_phone_numbers_student_id (student_id),
            INDEX idx_other_phone_numbers_relationship (student_id, relationship),
            CONSTRAINT fk_other_phone_numbers_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_medical_info (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            has_health_condition TINYINT(1) NOT NULL DEFAULT 0,
            health_condition_details TEXT NULL,
            has_special_educational_needs TINYINT(1) NOT NULL DEFAULT 0,
            special_educational_needs_details TEXT NULL,
            takes_regular_medicine TINYINT(1) NOT NULL DEFAULT 0,
            medicine_details TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_medical_student (student_id),
            CONSTRAINT fk_medical_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_academic_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            academic_year SMALLINT UNSIGNED NOT NULL,
            school_name VARCHAR(150) NULL,
            grade_name VARCHAR(100) NULL,
            final_total DECIMAL(8,2) NULL,
            final_average DECIMAL(5,2) NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_academic_year (student_id, academic_year),
            INDEX idx_academic_student_current (student_id, is_current),
            CONSTRAINT fk_academic_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_school_schedule (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            day_of_week ENUM(
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday'
            ) NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_schedule_student_day (student_id, day_of_week),
            CONSTRAINT fk_schedule_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS teachers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NULL,
            first_name_ar VARCHAR(100) NULL,
            last_name_ar VARCHAR(100) NULL,
            phone_number VARCHAR(30) NULL,
            photo_path VARCHAR(255) NULL,
            email VARCHAR(150) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            show_on_website TINYINT(1) NOT NULL DEFAULT 1,

            -- The Lebanese school stages the teacher covers. Most teachers cover
            -- more than one, so these are three flags rather than a single choice.
            teaches_primary TINYINT(1) NOT NULL DEFAULT 0,
            teaches_intermediate TINYINT(1) NOT NULL DEFAULT 0,
            teaches_secondary TINYINT(1) NOT NULL DEFAULT 0,

            -- Experience is stored as the date teaching began rather than a count of
            -- years, so the figure on the profile keeps itself up to date.
            teaching_since DATE NULL,
            joined_center_on DATE NULL,

            certifications_en VARCHAR(255) NULL,
            certifications_ar VARCHAR(255) NULL,
            is_teacher_of_the_month TINYINT(1) NOT NULL DEFAULT 0,
            video_url VARCHAR(255) NULL,

            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_teachers_email (email),
            INDEX idx_teachers_name (first_name, last_name),
            INDEX idx_teachers_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id BIGINT UNSIGNED NULL,

            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(150) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'manager', 'teacher', 'parent') NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            last_login_at DATETIME NULL,
            notes VARCHAR(255) NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_users_email (email),
            UNIQUE KEY uq_users_teacher_id (teacher_id),
            INDEX idx_users_role_status (role, status),
            CONSTRAINT fk_users_teacher
                FOREIGN KEY (teacher_id) REFERENCES teachers(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS parent_students (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_user_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_parent_student_pair (parent_user_id, student_id),
            INDEX idx_parent_students_parent_status (parent_user_id, status),
            INDEX idx_parent_students_student_status (student_id, status),
            CONSTRAINT fk_parent_students_parent
                FOREIGN KEY (parent_user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_parent_students_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS subjects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(100) NOT NULL,
            name_ar VARCHAR(100) NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_subjects_name_en (name_en),
            INDEX idx_subjects_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS teacher_subjects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_teacher_subject (teacher_id, subject_id),
            UNIQUE KEY uq_teacher_subject_id_pair (id, teacher_id, subject_id),
            INDEX idx_teacher_subjects_subject (subject_id),
            CONSTRAINT fk_teacher_subjects_teacher
                FOREIGN KEY (teacher_id) REFERENCES teachers(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_teacher_subjects_subject
                FOREIGN KEY (subject_id) REFERENCES subjects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_subject_enrollments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,

            teacher_subject_id BIGINT UNSIGNED NOT NULL,
            teacher_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,

            academic_year SMALLINT UNSIGNED NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status ENUM('active', 'paused', 'stopped', 'completed') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_student_subject_teacher_year (student_id, teacher_subject_id, academic_year),
            UNIQUE KEY uq_student_enrollment_id_student (id, student_id),
            INDEX idx_student_subject_enrollments_student_status (student_id, status),
            INDEX idx_student_subject_enrollments_teacher (teacher_id),
            INDEX idx_student_subject_enrollments_subject (subject_id),
            INDEX idx_student_subject_enrollments_year (academic_year),
            CONSTRAINT fk_student_subject_enrollments_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_student_subject_enrollments_teacher_subject
                FOREIGN KEY (teacher_subject_id, teacher_id, subject_id)
                REFERENCES teacher_subjects(id, teacher_id, subject_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_daily_attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            attendance_date DATE NOT NULL,
            check_in_time TIME NULL,
            check_out_time TIME NULL,
            status ENUM('present', 'absent', 'late', 'excused', 'left_early') NOT NULL DEFAULT 'absent',
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_attendance_date (student_id, attendance_date),
            UNIQUE KEY uq_daily_attendance_id_student_date (id, student_id, attendance_date),
            INDEX idx_daily_attendance_date_status (attendance_date, status),
            CONSTRAINT fk_daily_attendance_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_subject_attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            daily_attendance_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            attendance_date DATE NOT NULL,
            teacher_subject_id BIGINT UNSIGNED NOT NULL,
            teacher_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            session_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('attended', 'missed') NOT NULL DEFAULT 'missed',
            homework_note VARCHAR(255) NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_subject_attendance_session (daily_attendance_id, teacher_subject_id, session_number),
            INDEX idx_subject_attendance_student_date (student_id, attendance_date),
            INDEX idx_subject_attendance_teacher_date (teacher_id, attendance_date),
            INDEX idx_subject_attendance_subject_date (subject_id, attendance_date),
            INDEX idx_subject_attendance_status (status),
            CONSTRAINT fk_subject_attendance_daily
                FOREIGN KEY (daily_attendance_id, student_id, attendance_date)
                REFERENCES student_daily_attendance(id, student_id, attendance_date)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_subject_attendance_teacher_subject
                FOREIGN KEY (teacher_subject_id, teacher_id, subject_id)
                REFERENCES teacher_subjects(id, teacher_id, subject_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS age_groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            min_age TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_age TINYINT UNSIGNED NOT NULL DEFAULT 99,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_age_groups_status_range (status, min_age, max_age)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS expiation_categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expiation_categories_status (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS expiations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id BIGINT UNSIGNED NOT NULL,
            age_group_id BIGINT UNSIGNED NOT NULL,
            title_en VARCHAR(200) NOT NULL,
            title_ar VARCHAR(200) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expiations_category (category_id),
            INDEX idx_expiations_age_group (age_group_id),
            INDEX idx_expiations_lookup (status, age_group_id, category_id),
            CONSTRAINT fk_expiations_category
                FOREIGN KEY (category_id) REFERENCES expiation_categories(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_expiations_age_group
                FOREIGN KEY (age_group_id) REFERENCES age_groups(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_warnings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            teacher_id BIGINT UNSIGNED NULL,

            warning_date DATE NOT NULL,
            warning_year SMALLINT UNSIGNED GENERATED ALWAYS AS (YEAR(warning_date)) STORED,
            warning_type ENUM('oral', 'written') NULL,
            warning_number TINYINT UNSIGNED NULL,
            conversation_minutes SMALLINT UNSIGNED NULL,
            reason VARCHAR(255) NOT NULL,
            parent_message TEXT NULL,
            parent_notified TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT NULL,

            status ENUM('flagged', 'issued', 'assigned', 'resolved', 'dismissed') NOT NULL DEFAULT 'flagged',
            issued_by_user_id BIGINT UNSIGNED NULL,
            issued_at DATETIME NULL,
            expiation_id BIGINT UNSIGNED NULL,
            expiation_selected_by_user_id BIGINT UNSIGNED NULL,
            expiation_selected_at DATETIME NULL,
            resolved_by_user_id BIGINT UNSIGNED NULL,
            resolved_at DATETIME NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            INDEX idx_warnings_student_year_type (student_id, warning_year, warning_type),
            INDEX idx_warnings_date_type (warning_date, warning_type),
            INDEX idx_warnings_teacher (teacher_id),
            INDEX idx_warnings_status (status),
            INDEX idx_warnings_student_status (student_id, status),
            INDEX idx_warnings_expiation (expiation_id),
            CONSTRAINT fk_warnings_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_teacher
                FOREIGN KEY (teacher_id) REFERENCES teachers(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_issued_by
                FOREIGN KEY (issued_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_resolved_by
                FOREIGN KEY (resolved_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_expiation_selected_by
                FOREIGN KEY (expiation_selected_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_expiation
                FOREIGN KEY (expiation_id) REFERENCES expiations(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_subscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            start_year SMALLINT UNSIGNED GENERATED ALWAYS AS (YEAR(start_date)) STORED,
            start_month TINYINT UNSIGNED GENERATED ALWAYS AS (MONTH(start_date)) STORED,
            default_monthly_amount DECIMAL(10,2) NULL,
            status ENUM('active', 'paused', 'unsubscribed', 'ended') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_subscriptions_id_student (id, student_id),
            INDEX idx_subscriptions_student_status (student_id, status),
            INDEX idx_subscriptions_start (start_year, start_month),
            CONSTRAINT fk_subscriptions_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_subscription_months (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            billing_year SMALLINT UNSIGNED NOT NULL,
            billing_month TINYINT UNSIGNED NOT NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            billing_type ENUM('full_month', 'half_month', 'custom', 'paused', 'unsubscribed') NOT NULL DEFAULT 'full_month',
            expected_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status ENUM('not_paid', 'partial_paid', 'paid', 'overpaid', 'paused', 'unsubscribed') NOT NULL DEFAULT 'not_paid',
            last_payment_date DATE NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_billing_month (student_id, billing_year, billing_month),
            UNIQUE KEY uq_subscription_month_id_student (id, student_id),
            INDEX idx_subscription_months_subscription (subscription_id),
            INDEX idx_subscription_months_student_status (student_id, payment_status),
            INDEX idx_subscription_months_period_status (billing_year, billing_month, payment_status),
            INDEX idx_subscription_months_status (payment_status),
            CONSTRAINT fk_subscription_months_subscription_student
                FOREIGN KEY (subscription_id, student_id) REFERENCES student_subscriptions(id, student_id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_subscription_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_month_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            paid_amount DECIMAL(10,2) NOT NULL,
            paid_at DATE NOT NULL,
            receipt_number VARCHAR(80) NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_payments_receipt_number (receipt_number),
            INDEX idx_payments_student_date (student_id, paid_at),
            INDEX idx_payments_month (subscription_month_id),
            CONSTRAINT fk_payments_subscription_month_student
                FOREIGN KEY (subscription_month_id, student_id) REFERENCES student_subscription_months(id, student_id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_content (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_key VARCHAR(80) NOT NULL,
            content_type ENUM('vision', 'mission', 'step', 'program') NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            eyebrow_en VARCHAR(150) NULL,
            eyebrow_ar VARCHAR(150) NULL,
            category_en VARCHAR(150) NULL,
            category_ar VARCHAR(150) NULL,
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            description_en TEXT NOT NULL,
            description_ar TEXT NOT NULL,
            point_1_en VARCHAR(255) NULL,
            point_1_ar VARCHAR(255) NULL,
            point_2_en VARCHAR(255) NULL,
            point_2_ar VARCHAR(255) NULL,
            point_3_en VARCHAR(255) NULL,
            point_3_ar VARCHAR(255) NULL,
            video_url VARCHAR(255) NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_content_key (content_key),
            INDEX idx_homepage_content_type_order (content_type, sort_order),
            INDEX idx_homepage_content_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_slides (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_path VARCHAR(255) NOT NULL,
            alt_en VARCHAR(255) NOT NULL,
            alt_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(180) NULL,
            title_ar VARCHAR(180) NULL,
            description_en VARCHAR(255) NULL,
            description_ar VARCHAR(255) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_homepage_slides_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_statistics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_key VARCHAR(80) NOT NULL,
            stat_value INT UNSIGNED NOT NULL DEFAULT 0,
            suffix VARCHAR(12) NULL,
            label_en VARCHAR(150) NOT NULL,
            label_ar VARCHAR(150) NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_statistics_key (stat_key),
            INDEX idx_homepage_statistics_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_team_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(180) NOT NULL,
            name_ar VARCHAR(180) NOT NULL,
            role_en VARCHAR(180) NOT NULL,
            role_ar VARCHAR(180) NOT NULL,
            subjects_en VARCHAR(255) NOT NULL,
            subjects_ar VARCHAR(255) NOT NULL,
            initials VARCHAR(12) NULL,
            image_path VARCHAR(255) NULL,
            contact_url VARCHAR(500) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_homepage_team_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_gallery_images (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_path VARCHAR(255) NOT NULL,
            alt_en VARCHAR(255) NOT NULL,
            alt_ar VARCHAR(255) NOT NULL,
            caption_en VARCHAR(180) NOT NULL,
            caption_ar VARCHAR(180) NOT NULL,
            layout_style ENUM('wide', 'tall', 'standard', 'crop_one', 'crop_two') NOT NULL DEFAULT 'standard',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_homepage_gallery_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_partners (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(180) NOT NULL,
            name_ar VARCHAR(180) NOT NULL,
            logo_path VARCHAR(255) NULL,
            website_url VARCHAR(500) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_homepage_partners_order (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_contact_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            link_key VARCHAR(80) NOT NULL,
            link_type ENUM(
                'phone', 'email', 'whatsapp', 'instagram', 'facebook',
                'google_map', 'address', 'hours', 'tiktok', 'linkedin'
            ) NOT NULL,
            label_en VARCHAR(150) NOT NULL,
            label_ar VARCHAR(150) NOT NULL,
            value_en VARCHAR(255) NOT NULL,
            value_ar VARCHAR(255) NOT NULL,
            url VARCHAR(500) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_contact_key (link_key),
            INDEX idx_homepage_contact_type_order (status, link_type, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Failed logins, so repeated guessing can be slowed down.
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(190) NOT NULL,
            attempted_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_login_attempts_ip (ip_address, attempted_at),
            INDEX idx_login_attempts_email (email, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Password reset codes. Only the hash of the code is stored, so a leaked copy
        // of this table cannot be used to reset anyone's password.
        "CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            requested_ip VARCHAR(45) NOT NULL DEFAULT '',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_password_resets_email (email, created_at),
            INDEX idx_password_resets_ip (requested_ip, created_at),
            INDEX idx_password_resets_expiry (expires_at),
            CONSTRAINT fk_password_resets_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // One row per device kept signed in by "Remember me". The cookie carries a
        // public selector plus a secret; only the hash of the secret is stored here.
        "CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            selector CHAR(32) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            device_label VARCHAR(190) NOT NULL DEFAULT '',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_remember_selector (selector),
            INDEX idx_remember_user (user_id),
            INDEX idx_remember_expiry (expires_at),
            CONSTRAINT fk_remember_tokens_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Small key/value store for homepage switches such as the admissions banner.
        // Every fixed sentence on the public pages, so the wording is administration's
        // to change rather than the code's. Seeded from khotwa_homepage_text_defaults().
        "CREATE TABLE IF NOT EXISTS homepage_texts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            text_key VARCHAR(80) NOT NULL,
            section VARCHAR(40) NOT NULL,
            value_en TEXT NOT NULL,
            value_ar TEXT NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_text_key (text_key),
            INDEX idx_homepage_text_section (section, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(80) NOT NULL,
            setting_value VARCHAR(255) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_settings_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS homepage_reviews (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_user_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(120) NOT NULL,
            display_name_ar VARCHAR(120) NULL,
            relationship_label VARCHAR(120) NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            review_text TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            reviewed_by_user_id BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_homepage_reviews_status_order (status, sort_order, id),
            INDEX idx_homepage_reviews_parent (parent_user_id),
            CONSTRAINT fk_homepage_reviews_parent
                FOREIGN KEY (parent_user_id) REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_homepage_reviews_reviewer
                FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Reporting views used to live here. Three of them were never queried by any
        // page, and shared hosting commonly withholds CREATE VIEW, which stopped the
        // whole schema from being built. The one that was used is now a derived table
        // in the queries that need it: see khotwa_daily_attendance_summary_sql().
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }
}

/**
 * The canonical homepage copy.
 *
 * This is the single source of truth for every homepage_content row: a fresh
 * database is seeded from it, and applyHomepageCopyMigration() repairs an older
 * database from the very same values. Editing the text here is enough to make a
 * rebuilt database come back with exactly this content.
 *
 * Column order:
 * content_key, content_type, sort_order, eyebrow_en, eyebrow_ar, category_en,
 * category_ar, title_en, title_ar, description_en, description_ar,
 * point_1_en, point_1_ar, point_2_en, point_2_ar, point_3_en, point_3_ar
 */
function khotwa_homepage_content_defaults(): array
{
    return [
        [
            'vision', 'vision', 1, 'Our vision', 'رؤيتنا', null, null,
            'Confident learners. Limitless futures.',
            'متعلّمون واثقون. وآفاق بلا حدود.',
            'To shape a generation of curious, capable students who understand how they learn and trust how far they can go.',
            'أن نصنع جيلاً من الطلاب الفضوليين والقادرين، يفهمون كيف يتعلّمون ويثقون بقدرتهم على التقدّم.',
            null, null, null, null, null, null,
        ],
        [
            'mission', 'mission', 1, 'Our mission', 'رسالتنا', null, null,
            'Make every learning step count.',
            'نجعل لكل خطوة تعليمية قيمة.',
            'We combine careful assessment, personalized instruction, purposeful practice, and consistent feedback to turn effort into progress.',
            'نجمع بين التقييم الدقيق والتعليم المخصص والتدريب الهادف والتغذية الراجعة المستمرة لتحويل الجهد إلى تقدّم.',
            null, null, null, null, null, null,
        ],
        [
            'step_discover', 'step', 1, 'Step 01', 'الخطوة 01', null, null,
            'Discover', 'اكتشاف',
            'Student strengths, gaps, learning habits, and goals through focused assessment.',
            'نقاط القوة والاحتياجات التعليمية لدى الطالب، وفهم نمط تعلمه وأهدافه عبر تقييمٍ مُوجَّه.',
            null, null, null, null, null, null,
        ],
        [
            'step_guide', 'step', 2, 'Step 02', 'الخطوة 02', null, null,
            'Guide', 'إرشاد',
            'Students with targeted support and personalized direction for daily homework.',
            'الطالب عبر توجيهٍ مباشر ومُخصص، لمساعدته في متابعة فروضه المدرسية اليومية وتنظيم دراسته.',
            null, null, null, null, null, null,
        ],
        [
            'step_build', 'step', 3, 'Step 03', 'الخطوة 03', null, null,
            'Build', 'تعزيز',
            'Strong academic foundations through clear explanations and effective routines.',
            'المهارات والمفاهيم الأكاديمية الأساسية بأساليب شرحٍ واضحة وتطبيقاتٍ عمليّةٍ مُكثّفة.',
            null, null, null, null, null, null,
        ],
        [
            'step_achieve', 'step', 4, 'Step 04', 'الخطوة 04', null, null,
            'Achieve', 'إنجاز',
            'Continuous progress, celebrate key milestones, and reach academic success.',
            'الأهداف الأكاديمية ومتابعة التقدم المستمر، لضمان الوصول إلى أفضل المستويات الدراسية.',
            null, null, null, null, null, null,
        ],
        [
            'program_teaching', 'program', 1,
            'Core program', 'البرنامج الأساسي', 'Teaching', 'التعليم',
            'Academic support from Grade 1 till 12',
            'دعم أكاديمي من الصف الأول حتى الثاني عشر',
            'Personalized and small-group learning across core school subjects.',
            'دعم فردي وضمن مجموعات صغيرة في المواد المدرسية الأساسية.',
            'Primary foundations', 'أساسيات المرحلة الابتدائية',
            'Middle school support', 'دعم المرحلة المتوسطة',
            'Grades 10, 11 & 12 preparation', 'تحضير الصفوف العاشر والحادي عشر والثاني عشر',
        ],
        [
            'program_training', 'program', 2,
            'Skills program', 'برنامج المهارات', 'Training', 'التدريب',
            'Practical skills for learners and educators',
            'مهارات عملية للمتعلمين والمعلّمين',
            'Focused workshops that turn knowledge into confident action.',
            'ورش مركّزة تحوّل المعرفة إلى تطبيق واثق.',
            'Study and learning skills', 'مهارات الدراسة والتعلّم',
            'Teacher development', 'تطوير المعلّمين',
            'Digital and communication skills', 'المهارات الرقمية ومهارات التواصل',
        ],
        [
            'program_activities', 'program', 3,
            'Enrichment program', 'برنامج الإثراء', 'Activities', 'الأنشطة',
            'Creative, social, and hands-on experiences',
            'تجارب إبداعية واجتماعية وتطبيقية',
            'Active sessions that spark curiosity and build future-ready abilities.',
            'جلسات تفاعلية تثير الفضول وتبني قدرات جاهزة للمستقبل.',
            'STEM and maker activities', 'أنشطة العلوم والتكنولوجيا والابتكار',
            'Arts, reading, and expression', 'الفنون والقراءة والتعبير',
            'Seasonal clubs and events', 'نوادٍ وفعاليات موسمية',
        ],
    ];
}

/**
 * Turns one positional row of khotwa_homepage_content_defaults() into a named map.
 */
function khotwa_homepage_content_row(string $contentKey): ?array
{
    $names = [
        'content_key', 'content_type', 'sort_order',
        'eyebrow_en', 'eyebrow_ar', 'category_en', 'category_ar',
        'title_en', 'title_ar', 'description_en', 'description_ar',
        'point_1_en', 'point_1_ar', 'point_2_en', 'point_2_ar', 'point_3_en', 'point_3_ar',
    ];

    foreach (khotwa_homepage_content_defaults() as $row) {
        if ($row[0] === $contentKey) {
            return array_combine($names, $row);
        }
    }

    return null;
}

function seedHomepageContentDefaults(PDO $pdo): void
{
    $statement = $pdo->prepare(
        "INSERT IGNORE INTO homepage_content (
            content_key, content_type, sort_order,
            eyebrow_en, eyebrow_ar, category_en, category_ar,
            title_en, title_ar, description_en, description_ar,
            point_1_en, point_1_ar, point_2_en, point_2_ar, point_3_en, point_3_ar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach (khotwa_homepage_content_defaults() as $row) {
        $statement->execute($row);
    }
}

function seedHomepageCollectionsDefaults(PDO $pdo): void
{
    $slides = [
        [
            'assets/images/khotwa-classroom-gallery.webp',
            'Teacher guiding students through a collaborative classroom activity',
            'معلّم يوجّه الطلاب خلال نشاط صفي تعاوني',
            'Human guidance', 'توجيه إنساني',
            'at the center of every lesson', 'في قلب كل حصة',
            1,
        ],
        [
            'assets/images/khotwa-stem-gallery.webp',
            'Students building a project during a STEM activity',
            'طلاب يبنون مشروعاً خلال نشاط علمي',
            'Active discovery', 'اكتشاف تفاعلي',
            'through practical learning experiences', 'من خلال تجارب تعليمية عملية',
            2,
        ],
        [
            'assets/images/khotwa-hero.webp',
            'Teacher supporting students around a learning table',
            'معلّم يدعم الطلاب حول طاولة التعلّم',
            'Personal support', 'دعم شخصي',
            'for every learner and every goal', 'لكل متعلّم ولكل هدف',
            3,
        ],
    ];
    if ((int) $pdo->query('SELECT COUNT(*) FROM homepage_slides')->fetchColumn() === 0) {
        $slideStatement = $pdo->prepare(
            "INSERT INTO homepage_slides (
                image_path, alt_en, alt_ar, title_en, title_ar,
                description_en, description_ar, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($slides as $row) {
            $slideStatement->execute($row);
        }
    }

    $statistics = [
        ['learners_supported', 450, '+', 'learners supported', 'متعلّم تلقى الدعم', 1],
        ['expert_educators', 18, '+', 'expert educators', 'معلّماً متخصصاً', 2],
        ['family_satisfaction', 92, '%', 'family satisfaction', 'رضا العائلات', 3],
        ['years_experience', 12, '+', 'years of experience', 'عاماً من الخبرة', 4],
    ];
    $statStatement = $pdo->prepare(
        "INSERT IGNORE INTO homepage_statistics (
            stat_key, stat_value, suffix, label_en, label_ar, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($statistics as $row) {
        $statStatement->execute($row);
    }

    // No team members are seeded. The homepage team section is built from the real
    // teachers in the teachers table (see homepage_team_from_teachers), so there is
    // nothing to invent here. The old homepage_team_members rows are cleared by
    // khotwa_migrate_retire_demo_team().

    $galleryImages = [
        [
            'assets/images/khotwa-classroom-gallery.webp',
            'Students learning together with their teacher',
            'طلاب يتعلّمون مع معلّمهم',
            'Collaborative learning', 'تعلّم تعاوني', 'wide', 1,
        ],
        [
            'assets/images/khotwa-stem-gallery.webp',
            'Students building a project during a STEM activity',
            'طلاب يبنون مشروعاً خلال نشاط علمي',
            'Hands-on discovery', 'اكتشاف بالتجربة', 'tall', 2,
        ],
        [
            'assets/images/khotwa-hero.webp',
            'Teacher supporting students around a learning table',
            'معلّم يدعم الطلاب حول طاولة التعلّم',
            'Guided support', 'دعم موجّه', 'crop_one', 3,
        ],
        [
            'assets/images/khotwa-stem-gallery.webp',
            'Young students focused on a classroom project',
            'طلاب صغار يركّزون على مشروع صفي',
            'Curiosity at work', 'فضول يتحوّل إلى عمل', 'crop_two', 4,
        ],
    ];
    if ((int) $pdo->query('SELECT COUNT(*) FROM homepage_gallery_images')->fetchColumn() === 0) {
        $galleryStatement = $pdo->prepare(
            "INSERT INTO homepage_gallery_images (
                image_path, alt_en, alt_ar, caption_en, caption_ar, layout_style, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($galleryImages as $row) {
            $galleryStatement->execute($row);
        }
    }

    // No partners are seeded. The five that used to be here (EduCore, BrightLab and
    // the rest) were invented for the design, and a real center should not ship a
    // homepage claiming partnerships it does not have. Real ones are added, with
    // their logos, from the admin panel: Website workspace > Partner Logos.

    $contacts = [
        [
            'primary_email', 'email', 'Email', 'البريد الإلكتروني',
            'khotwacenter.lb@gmail.com', 'khotwacenter.lb@gmail.com',
            'mailto:khotwacenter.lb@gmail.com', 1, 'active',
        ],
        [
            'primary_phone', 'phone', 'Phone', 'الهاتف',
            KHOTWA_CENTER_PHONE_DISPLAY, KHOTWA_CENTER_PHONE_DISPLAY,
            'tel:' . KHOTWA_CENTER_PHONE_E164, 2, 'active',
        ],
        [
            'whatsapp', 'whatsapp', 'WhatsApp', 'واتساب',
            'WhatsApp', 'واتساب', KHOTWA_CENTER_WHATSAPP_URL, 3, 'active',
        ],
        [
            'instagram', 'instagram', 'Instagram', 'إنستغرام',
            'Instagram', 'إنستغرام', KHOTWA_CENTER_INSTAGRAM_URL, 4, 'active',
        ],
        [
            'facebook', 'facebook', 'Facebook', 'فيسبوك',
            'Facebook', 'فيسبوك', KHOTWA_CENTER_FACEBOOK_URL, 5, 'active',
        ],
        [
            'google_map', 'google_map', 'Google Maps', 'خرائط Google',
            KHOTWA_CENTER_ADDRESS_EN, KHOTWA_CENTER_ADDRESS_AR,
            KHOTWA_CENTER_MAP_URL, 6, 'active',
        ],
        [
            'address', 'address', 'Address', 'العنوان',
            KHOTWA_CENTER_ADDRESS_EN, KHOTWA_CENTER_ADDRESS_AR, null, 7, 'active',
        ],
        [
            'opening_hours', 'hours', 'Opening hours', 'ساعات العمل',
            KHOTWA_CENTER_HOURS_EN, KHOTWA_CENTER_HOURS_AR, null, 8, 'active',
        ],
        // The center has no TikTok or LinkedIn yet. The rows exist so a link can be
        // filled in later from the admin panel, but they stay inactive and the
        // homepage leaves them out entirely.
        [
            'tiktok', 'tiktok', 'TikTok', 'تيك توك',
            'TikTok', 'تيك توك', null, 9, 'inactive',
        ],
        [
            'linkedin', 'linkedin', 'LinkedIn', 'لينكدإن',
            'LinkedIn', 'لينكدإن', null, 10, 'inactive',
        ],
    ];
    $contactStatement = $pdo->prepare(
        "INSERT IGNORE INTO homepage_contact_links (
            link_key, link_type, label_en, label_ar,
            value_en, value_ar, url, sort_order, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($contacts as $row) {
        $contactStatement->execute($row);
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );
    $statement->execute([$tableName, $columnName]);

    return (int) $statement->fetchColumn() > 0;
}

function columnType(PDO $pdo, string $tableName, string $columnName): ?string
{
    $statement = $pdo->prepare(
        "SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );
    $statement->execute([$tableName, $columnName]);
    $columnType = $statement->fetchColumn();

    return $columnType === false ? null : (string) $columnType;
}

function indexExists(PDO $pdo, string $tableName, string $indexName): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND INDEX_NAME = ?"
    );
    $statement->execute([$tableName, $indexName]);

    return (int) $statement->fetchColumn() > 0;
}

function addColumnIfMissing(PDO $pdo, string $tableName, string $columnName, string $definition): void
{
    if (!columnExists($pdo, $tableName, $columnName)) {
        $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$definition}");
    }
}

function constraintExists(PDO $pdo, string $tableName, string $constraintName): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND CONSTRAINT_NAME = ?"
    );
    $statement->execute([$tableName, $constraintName]);

    return (int) $statement->fetchColumn() > 0;
}

function addForeignKeyIfMissing(PDO $pdo, string $tableName, string $constraintName, string $definition): void
{
    if (!constraintExists($pdo, $tableName, $constraintName)) {
        $pdo->exec("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$constraintName}` {$definition}");
    }
}

function dropColumnIfExists(PDO $pdo, string $tableName, string $columnName): void
{
    if (columnExists($pdo, $tableName, $columnName)) {
        $pdo->exec("ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`");
    }
}

function dropIndexIfExists(PDO $pdo, string $tableName, string $indexName): void
{
    if (indexExists($pdo, $tableName, $indexName)) {
        $pdo->exec("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }
}

function addIndexIfMissing(PDO $pdo, string $tableName, string $indexName, string $definition): void
{
    if (!indexExists($pdo, $tableName, $indexName)) {
        $pdo->exec("ALTER TABLE `{$tableName}` ADD {$definition}");
    }
}

/**
 * Replace the placeholder contact details from the original demo data with the
 * center's real ones.
 *
 * Every row is matched on the placeholder it is being replaced, so a detail already
 * corrected in the admin panel is left exactly as the administrator set it. That
 * matters because migrations run again on every schema bump.
 */
/**
 * Remove the five invented partner names from the original demo data.
 *
 * Matched by name and only when the row still has no logo and no website link, so a
 * genuine partner that happens to share a name is never deleted.
 */
function khotwa_migrate_remove_demo_partners(PDO $pdo): void
{
    $statement = $pdo->prepare(
        "DELETE FROM homepage_partners
         WHERE name_en = ?
           AND (logo_path IS NULL OR logo_path = '')
           AND (website_url IS NULL OR website_url = '')"
    );
    foreach (['EduCore', 'BrightLab', 'Northstar', 'Skillwise', 'LearnHub'] as $demoName) {
        $statement->execute([$demoName]);
    }
}

/**
 * Retire the three invented team members from the original demo data.
 *
 * The homepage team section is drawn from the teachers table now. Only the exact demo
 * rows are removed, so anything an administrator added by hand survives - though it no
 * longer appears on the homepage.
 */
function khotwa_migrate_retire_demo_team(PDO $pdo): void
{
    $statement = $pdo->prepare('DELETE FROM homepage_team_members WHERE name_en = ? AND image_path IS NULL');
    foreach (['Rana Mansour', 'Omar Saad', 'Layla Nasser'] as $demoName) {
        $statement->execute([$demoName]);
    }
}

function khotwa_migrate_contact_placeholders(PDO $pdo): void
{
    $replacements = [
        // link_key, the placeholder to look for, and the values that replace it.
        ['primary_phone', 'tel:+9611000000', KHOTWA_CENTER_PHONE_DISPLAY, KHOTWA_CENTER_PHONE_DISPLAY, 'tel:' . KHOTWA_CENTER_PHONE_E164],
        ['whatsapp', 'https://wa.me/9611000000', 'WhatsApp', 'واتساب', KHOTWA_CENTER_WHATSAPP_URL],
        ['instagram', '#', 'Instagram', 'إنستغرام', KHOTWA_CENTER_INSTAGRAM_URL],
        ['facebook', '#', 'Facebook', 'فيسبوك', KHOTWA_CENTER_FACEBOOK_URL],
        ['google_map', 'https://maps.google.com/?q=Beirut%2C+Lebanon', KHOTWA_CENTER_ADDRESS_EN, KHOTWA_CENTER_ADDRESS_AR, KHOTWA_CENTER_MAP_URL],
    ];

    $update = $pdo->prepare(
        'UPDATE homepage_contact_links
         SET value_en = ?, value_ar = ?, url = ?
         WHERE link_key = ? AND url = ?'
    );
    foreach ($replacements as [$key, $placeholder, $valueEn, $valueAr, $url]) {
        $update->execute([$valueEn, $valueAr, $url, $key, $placeholder]);
    }

    // The phone is grouped in pairs now. Only the previous spellings of the same
    // number are rewritten, so a number edited in the admin panel is left alone.
    $phoneUpdate = $pdo->prepare(
        "UPDATE homepage_contact_links
         SET value_en = ?, value_ar = ?
         WHERE link_key = 'primary_phone' AND value_en = ?"
    );
    foreach (['+961 79 427 940', '+961 1 000 000'] as $previousDisplay) {
        $phoneUpdate->execute([KHOTWA_CENTER_PHONE_DISPLAY, KHOTWA_CENTER_PHONE_DISPLAY, $previousDisplay]);
    }

    // The address and opening hours carry no URL, so they are matched on their text.
    $pdo->prepare(
        "UPDATE homepage_contact_links
         SET value_en = ?, value_ar = ?
         WHERE link_key = 'address' AND value_en = 'Beirut, Lebanon'"
    )->execute([KHOTWA_CENTER_ADDRESS_EN, KHOTWA_CENTER_ADDRESS_AR]);

    $pdo->prepare(
        "UPDATE homepage_contact_links
         SET value_en = ?, value_ar = ?
         WHERE link_key = 'opening_hours' AND value_en = 'Mon–Sat, 9:00–19:00'"
    )->execute([KHOTWA_CENTER_HOURS_EN, KHOTWA_CENTER_HOURS_AR]);

    // No TikTok or LinkedIn account exists yet. Only rows still holding the '#'
    // placeholder are switched off, so a real link added later survives.
    $pdo->exec(
        "UPDATE homepage_contact_links
         SET url = NULL, status = 'inactive'
         WHERE link_key IN ('tiktok', 'linkedin') AND (url = '#' OR url IS NULL OR url = '')"
    );
}

function applyKhotwaMigrations(PDO $pdo): void
{
    khotwa_migrate_contact_placeholders($pdo);
    khotwa_migrate_remove_demo_partners($pdo);
    khotwa_migrate_retire_demo_team($pdo);

    // The teaching language is the foreign-language stream the student follows, so the
    // options are French and English. Rows recorded as 'Arabic' predate that and are
    // moved to French, which keeps the two original groups distinct.
    $teachingLanguageType = columnType($pdo, 'students', 'current_teaching_language');

    if ($teachingLanguageType !== "enum('French','English')") {
        if (str_contains(strtolower((string) $teachingLanguageType), 'arabic')) {
            $pdo->exec(
                "ALTER TABLE students
                 MODIFY current_teaching_language
                 ENUM('Arabic', 'English', 'French') NOT NULL"
            );
            $pdo->exec(
                "UPDATE students
                 SET current_teaching_language = 'French'
                 WHERE current_teaching_language = 'Arabic'"
            );
        }

        $pdo->exec(
            "ALTER TABLE students
             MODIFY current_teaching_language ENUM('French', 'English') NOT NULL"
        );
    }

    // Nationality used to be free text on the student row. It is now a foreign key
    // into the nationalities list so the form offers it as a fixed set of options.
    // Only the three nationalities the center serves are kept; anything else (and
    // anything blank) is left empty for an admin to pick from the list.
    if (columnExists($pdo, 'students', 'nationality')) {
        addColumnIfMissing(
            $pdo,
            'students',
            'nationality_id',
            'nationality_id BIGINT UNSIGNED NULL AFTER gender'
        );

        $nationalityIds = $pdo->query(
            'SELECT LOWER(name_en) AS name_en, id FROM nationalities'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        // Spellings seen in the old text column, per nationality.
        $spellings = [
            'lebanese' => ['lebanese', 'lebanon', 'لبناني', 'لبنانية', 'لبنان'],
            'syrian' => ['syrian', 'syria', 'سوري', 'سورية', 'سوريا'],
            'palestinian' => ['palestinian', 'palestine', 'فلسطيني', 'فلسطينية', 'فلسطين'],
        ];
        $assign = $pdo->prepare(
            'UPDATE students SET nationality_id = ?
             WHERE nationality_id IS NULL AND LOWER(TRIM(nationality)) = ?'
        );
        foreach ($spellings as $key => $variants) {
            if (!isset($nationalityIds[$key])) {
                continue;
            }
            foreach ($variants as $variant) {
                $assign->execute([(int) $nationalityIds[$key], $variant]);
            }
        }

        addIndexIfMissing(
            $pdo,
            'students',
            'idx_students_nationality',
            'INDEX idx_students_nationality (nationality_id)'
        );
        addForeignKeyIfMissing(
            $pdo,
            'students',
            'fk_students_nationality',
            'FOREIGN KEY (nationality_id) REFERENCES nationalities(id)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $pdo->exec('ALTER TABLE students DROP COLUMN nationality');
    }

    // Family status used to be free text; the known values map straight onto the list.
    $familyStatusType = (string) columnType($pdo, 'students', 'family_status');
    if (!str_starts_with(strtolower($familyStatusType), 'enum')) {
        $pdo->exec("UPDATE students SET family_status = 'Married' WHERE family_status = '' OR family_status IS NULL");
        $pdo->exec(
            "ALTER TABLE students
             MODIFY family_status
             ENUM('Married', 'Divorced', 'Widowed', 'Separated', 'Single') NOT NULL"
        );
    }

    // One review per parent account, editable in place. Any extras a parent already
    // has are detached rather than deleted: an approved one stays on the homepage and
    // stays visible to the admin, it just is no longer the parent's editable review.
    if (!indexExists($pdo, 'homepage_reviews', 'uq_homepage_reviews_parent')) {
        $pdo->exec(
            'UPDATE homepage_reviews AS extra
             JOIN (
                SELECT parent_user_id, MAX(id) AS keep_id
                FROM homepage_reviews
                WHERE parent_user_id IS NOT NULL
                GROUP BY parent_user_id
                HAVING COUNT(*) > 1
             ) AS newest ON newest.parent_user_id = extra.parent_user_id
             SET extra.parent_user_id = NULL
             WHERE extra.id <> newest.keep_id'
        );
        $pdo->exec(
            'ALTER TABLE homepage_reviews
             ADD UNIQUE KEY uq_homepage_reviews_parent (parent_user_id)'
        );
    }

    // A parent link only records which parent belongs to which student. The
    // relationship kind and the free-text note were never used, so they are gone.
    foreach (['relationship', 'notes'] as $unusedParentLinkColumn) {
        if (columnExists($pdo, 'parent_students', $unusedParentLinkColumn)) {
            $pdo->exec(
                'ALTER TABLE parent_students DROP COLUMN '
                . '`' . $unusedParentLinkColumn . '`'
            );
        }
    }

    addColumnIfMissing(
        $pdo,
        'students',
        'parents_assigned_to_whatsapp_group',
        'parents_assigned_to_whatsapp_group TINYINT(1) NOT NULL DEFAULT 0 AFTER home_phone_number'
    );
    addColumnIfMissing(
        $pdo,
        'students',
        'photo_path',
        'photo_path VARCHAR(255) NULL AFTER mother_last_name_ar'
    );
    addIndexIfMissing(
        $pdo,
        'students',
        'idx_students_whatsapp_group',
        'INDEX idx_students_whatsapp_group (parents_assigned_to_whatsapp_group)'
    );

    addColumnIfMissing(
        $pdo,
        'student_academic_records',
        'final_total',
        'final_total DECIMAL(8,2) NULL AFTER grade_name'
    );
    addColumnIfMissing(
        $pdo,
        'student_academic_records',
        'final_average',
        'final_average DECIMAL(5,2) NULL AFTER final_total'
    );

    addColumnIfMissing($pdo, 'teachers', 'email', 'email VARCHAR(150) NULL AFTER phone_number');
    addColumnIfMissing($pdo, 'teachers', 'photo_path', 'photo_path VARCHAR(255) NULL AFTER phone_number');
    addColumnIfMissing($pdo, 'teachers', 'password_hash', 'password_hash VARCHAR(255) NULL AFTER email');
    // Every teacher is on the public homepage unless this is switched off for them.
    addColumnIfMissing(
        $pdo,
        'teachers',
        'show_on_website',
        'show_on_website TINYINT(1) NOT NULL DEFAULT 1 AFTER status'
    );
    // The teacher's own name in Arabic. Students already carry both spellings; the
    // website showed teachers in Latin script even on the Arabic side without these.
    addColumnIfMissing(
        $pdo,
        'teachers',
        'first_name_ar',
        'first_name_ar VARCHAR(100) NULL AFTER last_name'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'last_name_ar',
        'last_name_ar VARCHAR(100) NULL AFTER first_name_ar'
    );

    // What the public teacher profile shows beyond the name and the subjects.
    // The three Lebanese school stages the teacher covers: ibtidai (primary),
    // mutawassit (intermediate) and sanawi (secondary). Flags rather than one
    // choice, because a teacher usually covers more than one stage.
    addColumnIfMissing(
        $pdo,
        'teachers',
        'teaches_primary',
        'teaches_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER show_on_website'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'teaches_intermediate',
        'teaches_intermediate TINYINT(1) NOT NULL DEFAULT 0 AFTER teaches_primary'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'teaches_secondary',
        'teaches_secondary TINYINT(1) NOT NULL DEFAULT 0 AFTER teaches_intermediate'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'teaching_since',
        'teaching_since DATE NULL AFTER teaches_secondary'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'joined_center_on',
        'joined_center_on DATE NULL AFTER teaching_since'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'certifications_en',
        'certifications_en VARCHAR(255) NULL AFTER joined_center_on'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'certifications_ar',
        'certifications_ar VARCHAR(255) NULL AFTER certifications_en'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'is_teacher_of_the_month',
        'is_teacher_of_the_month TINYINT(1) NOT NULL DEFAULT 0 AFTER certifications_ar'
    );
    addColumnIfMissing(
        $pdo,
        'teachers',
        'video_url',
        'video_url VARCHAR(255) NULL AFTER is_teacher_of_the_month'
    );

    // Experience used to be a typed-in number of years, which goes stale the moment
    // it is saved. Any figure already recorded is converted to the matching start
    // date before the old column is dropped.
    if (columnExists($pdo, 'teachers', 'years_experience')) {
        $pdo->exec(
            'UPDATE teachers
             SET teaching_since = DATE_SUB(CURDATE(), INTERVAL years_experience YEAR)
             WHERE teaching_since IS NULL AND years_experience IS NOT NULL'
        );
        dropColumnIfExists($pdo, 'teachers', 'years_experience');
    }

    // The stages used to be free text in two languages. Anything recognisable in
    // what was typed is mapped onto the three flags, then the columns are dropped.
    if (columnExists($pdo, 'teachers', 'education_levels_en')) {
        $stageMatches = [
            'teaches_primary' => ['primary', 'ibtida', 'elementary', 'ابتدائ'],
            'teaches_intermediate' => ['intermediate', 'mutawas', 'middle', 'متوسط', 'اعدادي', 'إعدادي'],
            'teaches_secondary' => ['secondary', 'sanaw', 'high school', 'ثانو'],
        ];
        $rows = $pdo->query(
            'SELECT id, education_levels_en, education_levels_ar FROM teachers'
        )->fetchAll();
        $flag = $pdo->prepare(
            'UPDATE teachers
             SET teaches_primary = ?, teaches_intermediate = ?, teaches_secondary = ?
             WHERE id = ?'
        );
        foreach ($rows as $row) {
            $written = mb_strtolower(
                trim((string) $row['education_levels_en'] . ' ' . (string) $row['education_levels_ar'])
            );
            if ($written === '') {
                continue;
            }
            $values = [];
            foreach ($stageMatches as $needles) {
                $hit = false;
                foreach ($needles as $needle) {
                    if (str_contains($written, $needle)) {
                        $hit = true;
                        break;
                    }
                }
                $values[] = $hit ? 1 : 0;
            }
            $values[] = (int) $row['id'];
            $flag->execute($values);
        }

        dropColumnIfExists($pdo, 'teachers', 'education_levels_en');
        dropColumnIfExists($pdo, 'teachers', 'education_levels_ar');
    }

    addIndexIfMissing($pdo, 'teachers', 'uq_teachers_email', 'UNIQUE KEY uq_teachers_email (email)');

    addColumnIfMissing($pdo, 'users', 'teacher_id', 'teacher_id BIGINT UNSIGNED NULL AFTER id');
    addColumnIfMissing($pdo, 'users', 'first_name', 'first_name VARCHAR(100) NOT NULL AFTER teacher_id');
    addColumnIfMissing($pdo, 'users', 'last_name', 'last_name VARCHAR(100) NULL AFTER first_name');
    addColumnIfMissing($pdo, 'users', 'email', 'email VARCHAR(150) NOT NULL AFTER last_name');
    addColumnIfMissing($pdo, 'users', 'password_hash', 'password_hash VARCHAR(255) NOT NULL AFTER email');
    addColumnIfMissing($pdo, 'users', 'role', "role ENUM('admin', 'manager', 'teacher', 'parent') NOT NULL AFTER password_hash");
    addColumnIfMissing($pdo, 'users', 'status', "status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role");
    addColumnIfMissing(
        $pdo,
        'users',
        'must_change_password',
        'must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
    );
    addColumnIfMissing($pdo, 'users', 'last_login_at', 'last_login_at DATETIME NULL AFTER must_change_password');
    addColumnIfMissing($pdo, 'users', 'notes', 'notes VARCHAR(255) NULL AFTER last_login_at');
    addIndexIfMissing($pdo, 'users', 'uq_users_email', 'UNIQUE KEY uq_users_email (email)');
    addIndexIfMissing($pdo, 'users', 'uq_users_teacher_id', 'UNIQUE KEY uq_users_teacher_id (teacher_id)');
    addIndexIfMissing($pdo, 'users', 'idx_users_role_status', 'INDEX idx_users_role_status (role, status)');

    $roleType = strtolower((string) columnType($pdo, 'users', 'role'));
    if ($roleType !== '' && !str_contains($roleType, "'parent'")) {
        $pdo->exec(
            "ALTER TABLE users
             MODIFY role ENUM('admin', 'manager', 'teacher', 'parent') NOT NULL"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS parent_students (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_user_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_parent_student_pair (parent_user_id, student_id),
            INDEX idx_parent_students_parent_status (parent_user_id, status),
            INDEX idx_parent_students_student_status (student_id, status),
            CONSTRAINT fk_parent_students_parent
                FOREIGN KEY (parent_user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_parent_students_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (columnExists($pdo, 'student_subject_enrollments', 'academic_year')) {
        $pdo->exec("UPDATE student_subject_enrollments SET academic_year = YEAR(CURDATE()) WHERE academic_year IS NULL");
        $pdo->exec("ALTER TABLE student_subject_enrollments MODIFY academic_year SMALLINT UNSIGNED NOT NULL");
    }

    addColumnIfMissing(
        $pdo,
        'student_subject_attendance',
        'session_number',
        'session_number TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER subject_id'
    );
    dropIndexIfExists($pdo, 'student_subject_attendance', 'uq_subject_attendance_session');
    addIndexIfMissing(
        $pdo,
        'student_subject_attendance',
        'uq_subject_attendance_session',
        'UNIQUE KEY uq_subject_attendance_session (daily_attendance_id, teacher_subject_id, session_number)'
    );

    addIndexIfMissing(
        $pdo,
        'student_subscription_months',
        'idx_subscription_months_student_status',
        'INDEX idx_subscription_months_student_status (student_id, payment_status)'
    );
    addIndexIfMissing(
        $pdo,
        'student_subscription_months',
        'idx_subscription_months_period_status',
        'INDEX idx_subscription_months_period_status (billing_year, billing_month, payment_status)'
    );
    addIndexIfMissing(
        $pdo,
        'student_subscription_payments',
        'uq_payments_receipt_number',
        'UNIQUE KEY uq_payments_receipt_number (receipt_number)'
    );

    // Warning workflow (teacher flag -> admin issue -> parent expiation -> resolved).
    if (columnExists($pdo, 'student_warnings', 'warning_type')) {
        $pdo->exec("ALTER TABLE student_warnings MODIFY warning_type ENUM('oral', 'written') NULL");
    }
    addColumnIfMissing(
        $pdo,
        'student_warnings',
        'status',
        "status ENUM('flagged', 'issued', 'assigned', 'resolved', 'dismissed') NOT NULL DEFAULT 'flagged' AFTER notes"
    );
    addColumnIfMissing($pdo, 'student_warnings', 'issued_by_user_id', 'issued_by_user_id BIGINT UNSIGNED NULL AFTER status');
    addColumnIfMissing($pdo, 'student_warnings', 'issued_at', 'issued_at DATETIME NULL AFTER issued_by_user_id');
    addColumnIfMissing($pdo, 'student_warnings', 'expiation_id', 'expiation_id BIGINT UNSIGNED NULL AFTER issued_at');
    addColumnIfMissing(
        $pdo,
        'student_warnings',
        'expiation_selected_by_user_id',
        'expiation_selected_by_user_id BIGINT UNSIGNED NULL AFTER expiation_id'
    );
    addColumnIfMissing(
        $pdo,
        'student_warnings',
        'expiation_selected_at',
        'expiation_selected_at DATETIME NULL AFTER expiation_selected_by_user_id'
    );
    addColumnIfMissing($pdo, 'student_warnings', 'resolved_by_user_id', 'resolved_by_user_id BIGINT UNSIGNED NULL AFTER expiation_selected_at');
    addColumnIfMissing($pdo, 'student_warnings', 'resolved_at', 'resolved_at DATETIME NULL AFTER resolved_by_user_id');

    addIndexIfMissing($pdo, 'student_warnings', 'idx_warnings_status', 'INDEX idx_warnings_status (status)');
    addIndexIfMissing($pdo, 'student_warnings', 'idx_warnings_student_status', 'INDEX idx_warnings_student_status (student_id, status)');
    addIndexIfMissing($pdo, 'student_warnings', 'idx_warnings_expiation', 'INDEX idx_warnings_expiation (expiation_id)');

    addForeignKeyIfMissing(
        $pdo,
        'student_warnings',
        'fk_warnings_issued_by',
        'FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );
    addForeignKeyIfMissing(
        $pdo,
        'student_warnings',
        'fk_warnings_resolved_by',
        'FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );
    addForeignKeyIfMissing(
        $pdo,
        'student_warnings',
        'fk_warnings_expiation_selected_by',
        'FOREIGN KEY (expiation_selected_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );
    addForeignKeyIfMissing(
        $pdo,
        'student_warnings',
        'fk_warnings_expiation',
        'FOREIGN KEY (expiation_id) REFERENCES expiations(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );

    // Existing warnings already carry a type, so treat them as issued (visible to parents).
    $pdo->exec("UPDATE student_warnings SET status = 'issued' WHERE warning_type IS NOT NULL AND status = 'flagged'");


    // 'action_taken' became the message the parent reads, so it is renamed and widened
    // from VARCHAR(255) to TEXT. Existing text carries over untouched.
    if (columnExists($pdo, 'student_warnings', 'action_taken')
        && !columnExists($pdo, 'student_warnings', 'parent_message')
    ) {
        $pdo->exec('ALTER TABLE student_warnings CHANGE action_taken parent_message TEXT NULL');
    }
    addColumnIfMissing($pdo, 'student_warnings', 'parent_message', 'parent_message TEXT NULL AFTER reason');

    // Reviews can carry the reviewer's name in Arabic as well as Latin script.
    addColumnIfMissing(
        $pdo,
        'homepage_reviews',
        'display_name_ar',
        'display_name_ar VARCHAR(120) NULL AFTER display_name'
    );

    // An approach step can point at a short YouTube clip that explains it, shown
    // as a small play button on the step and opened in a player on the page.
    addColumnIfMissing(
        $pdo,
        'homepage_content',
        'video_url',
        'video_url VARCHAR(255) NULL AFTER point_3_ar'
    );

    applyHomepageCopyMigration($pdo);
}

// Raise this when the canonical homepage copy changes and every existing
// database has to be brought back in line with khotwa_homepage_content_defaults().
const KHOTWA_HOMEPAGE_COPY_REVISION = 3;

/**
 * Brings an existing database back to the canonical homepage copy.
 *
 * A fresh database is already correct because seedHomepageContentDefaults() reads
 * the same source, so this only has work to do when upgrading an older install.
 * It runs once per revision, which is what keeps later admin edits from being
 * overwritten on every schema bump.
 */
function applyHomepageCopyMigration(PDO $pdo): void
{
    $storedRevision = (int) homepage_setting($pdo, 'homepage_copy_revision', '0');
    if ($storedRevision >= KHOTWA_HOMEPAGE_COPY_REVISION) {
        return;
    }

    // The four approach steps were renamed. Old keys are moved in an order that
    // never collides with a key still in use.
    $hasLegacySteps = (int) $pdo->query(
        "SELECT COUNT(*) FROM homepage_content WHERE content_key = 'step_diagnose'"
    )->fetchColumn() > 0;

    if ($hasLegacySteps) {
        $renames = [
            'step_build' => 'step_guide',
            'step_practice' => 'step_build',
            'step_diagnose' => 'step_discover',
            'step_progress' => 'step_achieve',
        ];
        $rename = $pdo->prepare('UPDATE homepage_content SET content_key = ? WHERE content_key = ?');
        foreach ($renames as $oldKey => $newKey) {
            $rename->execute([$newKey, $oldKey]);
        }
    }

    // Rewrite every row whose copy this release owns, straight from the defaults.
    $rewrittenKeys = [
        'step_discover', 'step_guide', 'step_build', 'step_achieve', 'program_teaching',
    ];
    $update = $pdo->prepare(
        "UPDATE homepage_content
         SET sort_order = ?, eyebrow_en = ?, eyebrow_ar = ?, category_en = ?, category_ar = ?,
             title_en = ?, title_ar = ?, description_en = ?, description_ar = ?,
             point_1_en = ?, point_1_ar = ?, point_2_en = ?, point_2_ar = ?,
             point_3_en = ?, point_3_ar = ?
         WHERE content_key = ?"
    );

    foreach ($rewrittenKeys as $contentKey) {
        $row = khotwa_homepage_content_row($contentKey);
        if ($row === null) {
            continue;
        }
        $update->execute([
            $row['sort_order'], $row['eyebrow_en'], $row['eyebrow_ar'],
            $row['category_en'], $row['category_ar'],
            $row['title_en'], $row['title_ar'], $row['description_en'], $row['description_ar'],
            $row['point_1_en'], $row['point_1_ar'], $row['point_2_en'], $row['point_2_ar'],
            $row['point_3_en'], $row['point_3_ar'],
            $contentKey,
        ]);
    }

    // Wording changes that can appear in any row, including admin-authored ones.
    $pdo->exec(
        "UPDATE homepage_content
         SET title_en = REPLACE(title_en, 'KG to Grade 12', 'Grade 1 till 12'),
             description_en = REPLACE(description_en, 'KG to Grade 12', 'Grade 1 till 12'),
             description_ar = REPLACE(description_ar, 'تعليم مخصص', 'دعم فردي')"
    );
    $pdo->exec(
        "UPDATE homepage_statistics
         SET label_ar = 'معلّماً متخصصاً'
         WHERE stat_key = 'expert_educators' AND label_ar = 'معلّماً خبيراً'"
    );

    // The center's public email address.
    $pdo->exec(
        "UPDATE homepage_contact_links
         SET value_en = 'khotwacenter.lb@gmail.com',
             value_ar = 'khotwacenter.lb@gmail.com',
             url = 'mailto:khotwacenter.lb@gmail.com'
         WHERE link_key = 'primary_email'"
    );

    homepage_setting_save($pdo, 'homepage_copy_revision', (string) KHOTWA_HOMEPAGE_COPY_REVISION);
}

$isDirectRun = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;

if (!defined('KHOTWA_SKIP_AUTO_BOOTSTRAP')) {
    try {
        $pdo = getDatabaseConnection();

        if ($isDirectRun) {
            echo 'Khotwa database and tables are ready.';
        }
    } catch (PDOException $exception) {
        if (!$isDirectRun) {
            throw $exception;
        }

        http_response_code(500);
        echo 'Database connection failed. Please start MySQL in XAMPP and check the database settings in database.php.';
    }
}
