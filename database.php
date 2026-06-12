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

function getDatabaseConnection(): PDO
{
    global $dbHost, $dbName, $dbUser, $dbPass, $dbCharset;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $serverDsn = "mysql:host={$dbHost};charset={$dbCharset}";
    $serverConnection = new PDO($serverDsn, $dbUser, $dbPass, $options);
    $serverConnection->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}`
         CHARACTER SET {$dbCharset}
         COLLATE {$dbCharset}_unicode_ci"
    );

    $databaseDsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    $pdo = new PDO($databaseDsn, $dbUser, $dbPass, $options);

    createKhotwaTables($pdo);
    applyKhotwaMigrations($pdo);

    return $pdo;
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
