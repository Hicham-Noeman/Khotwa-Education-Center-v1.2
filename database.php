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
 * require_once __DIR__ . '/database.php';
 */

$dbHost = 'localhost';
$dbName = 'khotwa_education_center';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

// Increment this only when a release needs createKhotwaTables/applyKhotwaMigrations again.
const KHOTWA_SCHEMA_VERSION = 3;

function getDatabaseConnection(): PDO
{
    global $dbHost, $dbName, $dbUser, $dbPass, $dbCharset;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ];

    $databaseDsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    try {
        $pdo = new PDO($databaseDsn, $dbUser, $dbPass, $options);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1049) {
            throw $exception;
        }

        $serverDsn = "mysql:host={$dbHost};charset={$dbCharset}";
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
    applyKhotwaMigrations($pdo);
    seedHomepageContentDefaults($pdo);
    seedHomepageCollectionsDefaults($pdo);
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

function createKhotwaTables(PDO $pdo): void
{
    $queries = [
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
            gender ENUM('male', 'female') NOT NULL,
            nationality VARCHAR(100) NOT NULL,
            blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
            date_of_birth DATE NOT NULL,
            address TEXT NULL,
            family_status VARCHAR(100) NOT NULL,
            number_of_people_in_household TINYINT UNSIGNED NOT NULL,
            current_teaching_language ENUM('Arabic', 'English') NOT NULL,
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
            INDEX idx_students_status (status)
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
            phone_number VARCHAR(30) NULL,
            email VARCHAR(150) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
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
            role ENUM('admin', 'manager', 'teacher') NOT NULL,
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

        "CREATE TABLE IF NOT EXISTS student_warnings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            teacher_id BIGINT UNSIGNED NULL,

            warning_date DATE NOT NULL,
            warning_year SMALLINT UNSIGNED GENERATED ALWAYS AS (YEAR(warning_date)) STORED,
            warning_type ENUM('oral', 'written') NOT NULL,
            warning_number TINYINT UNSIGNED NULL,
            conversation_minutes SMALLINT UNSIGNED NULL,
            reason VARCHAR(255) NOT NULL,
            action_taken VARCHAR(255) NULL,
            parent_notified TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            INDEX idx_warnings_student_year_type (student_id, warning_year, warning_type),
            INDEX idx_warnings_date_type (warning_date, warning_type),
            INDEX idx_warnings_teacher (teacher_id),
            CONSTRAINT fk_warnings_student
                FOREIGN KEY (student_id) REFERENCES students(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_warnings_teacher
                FOREIGN KEY (teacher_id) REFERENCES teachers(id)
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

        "CREATE OR REPLACE VIEW student_warning_yearly_summary AS
            SELECT
                students.id AS student_id,
                CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name_en,
                student_warnings.warning_year,
                SUM(CASE WHEN student_warnings.warning_type = 'oral' THEN 1 ELSE 0 END) AS oral_warning_count,
                SUM(CASE WHEN student_warnings.warning_type = 'written' THEN 1 ELSE 0 END) AS written_warning_count,
                COUNT(student_warnings.id) AS total_warning_count,
                COALESCE(SUM(student_warnings.conversation_minutes), 0) AS total_conversation_minutes,
                MAX(student_warnings.warning_date) AS latest_warning_date
            FROM students
            INNER JOIN student_warnings ON student_warnings.student_id = students.id
            GROUP BY
                students.id,
                students.first_name_en,
                students.last_name_en,
                student_warnings.warning_year",

        "CREATE OR REPLACE VIEW student_current_subjects AS
            SELECT
                student_subject_enrollments.id AS enrollment_id,
                students.id AS student_id,
                CONCAT(students.first_name_en, ' ', students.last_name_en) AS student_name_en,
                subjects.id AS subject_id,
                student_subject_enrollments.teacher_subject_id,
                subjects.name_en AS subject_name_en,
                subjects.name_ar AS subject_name_ar,
                teachers.id AS teacher_id,
                TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS teacher_name,
                student_subject_enrollments.academic_year,
                student_subject_enrollments.start_date,
                student_subject_enrollments.end_date,
                student_subject_enrollments.status
            FROM student_subject_enrollments
            INNER JOIN students ON students.id = student_subject_enrollments.student_id
            INNER JOIN subjects ON subjects.id = student_subject_enrollments.subject_id
            INNER JOIN teachers ON teachers.id = student_subject_enrollments.teacher_id
            WHERE student_subject_enrollments.status = 'active'",

        "CREATE OR REPLACE VIEW student_daily_attendance_summary AS
            SELECT
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
                student_daily_attendance.status",

        "CREATE OR REPLACE VIEW student_subscription_month_balances AS
            SELECT
                student_subscription_months.id AS subscription_month_id,
                student_subscription_months.student_id,
                student_subscription_months.billing_year,
                student_subscription_months.billing_month,
                student_subscription_months.expected_amount,
                student_subscription_months.paid_amount AS stored_paid_amount,
                COALESCE(SUM(student_subscription_payments.paid_amount), 0.00) AS payment_history_paid_amount,
                COUNT(student_subscription_payments.id) AS payment_history_count,
                CASE
                    WHEN COUNT(student_subscription_payments.id) > 0
                        THEN COALESCE(SUM(student_subscription_payments.paid_amount), 0.00)
                    ELSE student_subscription_months.paid_amount
                END AS effective_paid_amount,
                student_subscription_months.expected_amount - CASE
                    WHEN COUNT(student_subscription_payments.id) > 0
                        THEN COALESCE(SUM(student_subscription_payments.paid_amount), 0.00)
                    ELSE student_subscription_months.paid_amount
                END AS balance_amount,
                student_subscription_months.payment_status,
                student_subscription_months.billing_type
            FROM student_subscription_months
            LEFT JOIN student_subscription_payments
                ON student_subscription_payments.subscription_month_id = student_subscription_months.id
            GROUP BY
                student_subscription_months.id,
                student_subscription_months.student_id,
                student_subscription_months.billing_year,
                student_subscription_months.billing_month,
                student_subscription_months.expected_amount,
                student_subscription_months.paid_amount,
                student_subscription_months.payment_status,
                student_subscription_months.billing_type",
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }
}

function seedHomepageContentDefaults(PDO $pdo): void
{
    $rows = [
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
            'step_diagnose', 'step', 1, 'Step 01', 'الخطوة 01', null, null,
            'Diagnose', 'نشخّص',
            'We identify strengths, gaps, learning habits, and goals through focused assessment.',
            'نحدّد نقاط القوة والفجوات وعادات التعلّم والأهداف من خلال تقييم مركّز.',
            null, null, null, null, null, null,
        ],
        [
            'step_build', 'step', 2, 'Step 02', 'الخطوة 02', null, null,
            'Build', 'نبني',
            'We create strong foundations with clear explanations and personalized strategies.',
            'نبني أسساً قوية بشرح واضح واستراتيجيات مخصصة.',
            null, null, null, null, null, null,
        ],
        [
            'step_practice', 'step', 3, 'Step 03', 'الخطوة 03', null, null,
            'Practice', 'نتدرّب',
            'Students apply skills actively with coached repetition, challenge, and feedback.',
            'يطبّق الطلاب مهاراتهم بفاعلية من خلال التكرار الموجّه والتحدّي والتغذية الراجعة.',
            null, null, null, null, null, null,
        ],
        [
            'step_progress', 'step', 4, 'Step 04', 'الخطوة 04', null, null,
            'Progress', 'نتقدّم',
            'We measure growth, celebrate milestones, and adjust the path for what comes next.',
            'نقيس التطوّر، ونحتفل بالمحطات، ونعدّل المسار للخطوة التالية.',
            null, null, null, null, null, null,
        ],
        [
            'program_teaching', 'program', 1,
            'Core program', 'البرنامج الأساسي', 'Teaching', 'التعليم',
            'Academic support from KG to Grade 12',
            'دعم أكاديمي من الروضة حتى الصف الثاني عشر',
            'Personalized and small-group learning across core school subjects.',
            'تعليم مخصص وضمن مجموعات صغيرة في المواد المدرسية الأساسية.',
            'KG & primary foundations', 'أساسيات الروضة والمرحلة الابتدائية',
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

    $statement = $pdo->prepare(
        "INSERT IGNORE INTO homepage_content (
            content_key, content_type, sort_order,
            eyebrow_en, eyebrow_ar, category_en, category_ar,
            title_en, title_ar, description_en, description_ar,
            point_1_en, point_1_ar, point_2_en, point_2_ar, point_3_en, point_3_ar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($rows as $row) {
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
        ['expert_educators', 18, '+', 'expert educators', 'معلّماً خبيراً', 2],
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

    $teamMembers = [
        [
            'Rana Mansour', 'رنا منصور', 'Academic Director', 'المديرة الأكاديمية',
            'Learning strategy', 'استراتيجيات التعلّم', 'RM', null, '#contact', 1,
        ],
        [
            'Omar Saad', 'عمر سعد', 'Math & Science Lead', 'مسؤول الرياضيات والعلوم',
            'Mathematics & Science', 'الرياضيات والعلوم', 'OS', null, '#contact', 2,
        ],
        [
            'Layla Nasser', 'ليلى ناصر', 'Languages Coordinator', 'منسقة اللغات',
            'Arabic & English Languages', 'اللغتان العربية والإنجليزية', 'LN', null, '#contact', 3,
        ],
    ];
    if ((int) $pdo->query('SELECT COUNT(*) FROM homepage_team_members')->fetchColumn() === 0) {
        $teamStatement = $pdo->prepare(
            "INSERT INTO homepage_team_members (
                name_en, name_ar, role_en, role_ar, subjects_en, subjects_ar,
                initials, image_path, contact_url, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($teamMembers as $row) {
            $teamStatement->execute($row);
        }
    }

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

    $partners = [
        ['EduCore', 'إديوكور', null, null, 1],
        ['BrightLab', 'برايت لاب', null, null, 2],
        ['Northstar', 'نورث ستار', null, null, 3],
        ['Skillwise', 'سكيل وايز', null, null, 4],
        ['LearnHub', 'ليرن هب', null, null, 5],
    ];
    if ((int) $pdo->query('SELECT COUNT(*) FROM homepage_partners')->fetchColumn() === 0) {
        $partnerStatement = $pdo->prepare(
            "INSERT INTO homepage_partners (
                name_en, name_ar, logo_path, website_url, sort_order
            ) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($partners as $row) {
            $partnerStatement->execute($row);
        }
    }

    $contacts = [
        [
            'primary_email', 'email', 'Email', 'البريد الإلكتروني',
            'hello@khotwa.edu', 'hello@khotwa.edu', 'mailto:hello@khotwa.edu', 1,
        ],
        [
            'primary_phone', 'phone', 'Phone', 'الهاتف',
            '+961 1 000 000', '+961 1 000 000', 'tel:+9611000000', 2,
        ],
        [
            'whatsapp', 'whatsapp', 'WhatsApp', 'واتساب',
            'WhatsApp', 'واتساب', 'https://wa.me/9611000000', 3,
        ],
        [
            'instagram', 'instagram', 'Instagram', 'إنستغرام',
            'Instagram', 'إنستغرام', '#', 4,
        ],
        [
            'facebook', 'facebook', 'Facebook', 'فيسبوك',
            'Facebook', 'فيسبوك', '#', 5,
        ],
        [
            'google_map', 'google_map', 'Google Maps', 'خرائط Google',
            'Beirut, Lebanon', 'بيروت، لبنان',
            'https://maps.google.com/?q=Beirut%2C+Lebanon', 6,
        ],
        [
            'address', 'address', 'Address', 'العنوان',
            'Beirut, Lebanon', 'بيروت، لبنان', null, 7,
        ],
        [
            'opening_hours', 'hours', 'Opening hours', 'ساعات العمل',
            'Mon–Sat, 9:00–19:00', 'الاثنين–السبت، 9:00–19:00', null, 8,
        ],
        [
            'tiktok', 'tiktok', 'TikTok', 'تيك توك',
            'TikTok', 'تيك توك', '#', 9,
        ],
        [
            'linkedin', 'linkedin', 'LinkedIn', 'لينكدإن',
            'LinkedIn', 'لينكدإن', '#', 10,
        ],
    ];
    $contactStatement = $pdo->prepare(
        "INSERT IGNORE INTO homepage_contact_links (
            link_key, link_type, label_en, label_ar,
            value_en, value_ar, url, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
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

function applyKhotwaMigrations(PDO $pdo): void
{
    $teachingLanguageType = columnType($pdo, 'students', 'current_teaching_language');

    if ($teachingLanguageType !== "enum('Arabic','English')") {
        if (str_contains(strtolower((string) $teachingLanguageType), 'french')) {
            $pdo->exec(
                "UPDATE students
                 SET current_teaching_language = 'English'
                 WHERE current_teaching_language = 'French'"
            );
        }

        $pdo->exec(
            "ALTER TABLE students
             MODIFY current_teaching_language ENUM('Arabic', 'English') NOT NULL"
        );
    }

    addColumnIfMissing(
        $pdo,
        'students',
        'parents_assigned_to_whatsapp_group',
        'parents_assigned_to_whatsapp_group TINYINT(1) NOT NULL DEFAULT 0 AFTER home_phone_number'
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
    addColumnIfMissing($pdo, 'teachers', 'password_hash', 'password_hash VARCHAR(255) NULL AFTER email');
    addIndexIfMissing($pdo, 'teachers', 'uq_teachers_email', 'UNIQUE KEY uq_teachers_email (email)');

    addColumnIfMissing($pdo, 'users', 'teacher_id', 'teacher_id BIGINT UNSIGNED NULL AFTER id');
    addColumnIfMissing($pdo, 'users', 'first_name', 'first_name VARCHAR(100) NOT NULL AFTER teacher_id');
    addColumnIfMissing($pdo, 'users', 'last_name', 'last_name VARCHAR(100) NULL AFTER first_name');
    addColumnIfMissing($pdo, 'users', 'email', 'email VARCHAR(150) NOT NULL AFTER last_name');
    addColumnIfMissing($pdo, 'users', 'password_hash', 'password_hash VARCHAR(255) NOT NULL AFTER email');
    addColumnIfMissing($pdo, 'users', 'role', "role ENUM('admin', 'manager', 'teacher') NOT NULL AFTER password_hash");
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
