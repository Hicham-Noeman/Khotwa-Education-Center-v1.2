<?php
declare(strict_types=1);

/*
 * Khotwa Education Center - Database Setup & Seeding Script
 * 
 * This file can be run in two ways:
 * 1. From the Web: Open http://localhost/Khotwa%20Education%20Center%20v1.2/tools/setup.php
 * 2. From CLI: Run `php tools/setup.php` from the project root
 */

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/database.php';

$isCli = php_sapi_name() === 'cli';
$message = '';
$error = '';
$results = [];

function seedDatabase(PDO $pdo): array
{
    try {
        // 1. Disable Foreign Keys and Truncate
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $tables = [
            'student_subscription_payments',
            'student_subscription_months',
            'student_subscriptions',
            'student_warnings',
            'student_subject_attendance',
            'student_daily_attendance',
            'student_subject_enrollments',
          'parent_students',
            'teacher_subjects',
            'subjects',
            'users',
            'teachers',
            'student_school_schedule',
            'student_academic_records',
            'student_medical_info',
            'student_other_phone_numbers',
            'students'
        ];
        
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }
        
        $pdo->beginTransaction();
        
        $counts = [];

        // 2. Seed Admin User
        $insertAdmin = $pdo->prepare(
            "INSERT INTO users (
                teacher_id, first_name, last_name, email, password_hash,
                role, status, must_change_password, notes
             ) VALUES (NULL, ?, ?, ?, ?, 'admin', 'active', 0, ?)"
        );
        $insertAdmin->execute([
            'Khotwa',
            'Administrator',
            'admin@khotwa.test',
            password_hash('admin123', PASSWORD_DEFAULT),
            'Demo administrator account for the portal'
        ]);
        $insertManager = $pdo->prepare(
            "INSERT INTO users (
                teacher_id, first_name, last_name, email, password_hash,
                role, status, must_change_password, notes
             ) VALUES (NULL, ?, ?, ?, ?, 'manager', 'active', 0, ?)"
        );
        $insertManager->execute([
            'Khotwa',
            'Manager',
            'manager@khotwa.test',
            password_hash('manager123', PASSWORD_DEFAULT),
            'Read-only management dashboard account'
        ]);
        $counts['users'] = 2;

        // 3. Seed Subjects
        $subjectsSeed = [
            ['Mathematics', 'الرياضيات'],
            ['Arabic Language', 'اللغة العربية'],
            ['English Language', 'اللغة الإنجليزية'],
            ['General Science', 'العلوم العامة'],
            ['Physics', 'الفيزياء'],
            ['Chemistry', 'الكيمياء'],
            ['Biology', 'الأحياء'],
            ['History & Civics', 'التاريخ والتربية الوطنية'],
            ['Geography', 'الجغرافيا'],
            ['Islamic Studies', 'التربية الإسلامية'],
            ['Computer Science', 'الحاسوب وتكنولوجيا المعلومات'],
            ['Physical Education', 'التربية البدنية']
        ];

        $subjectMap = []; // Map english name to database ID
        $insertSubject = $pdo->prepare("INSERT INTO subjects (name_en, name_ar, status, notes) VALUES (?, ?, 'active', ?)");
        
        foreach ($subjectsSeed as $sub) {
            $insertSubject->execute([$sub[0], $sub[1], "Core curriculum subject"]);
            $subjectMap[$sub[0]] = (int)$pdo->lastInsertId();
        }
        $counts['subjects'] = count($subjectMap);

        // 4. Seed Teachers and User Accounts
        $teachersSeed = [
            ['name' => 'Maya', 'last' => 'Math', 'email' => 'maya.math@khotwa.test', 'subject' => 'Mathematics'],
            ['name' => 'Ahmad', 'last' => 'Arabic', 'email' => 'ahmad.arabic@khotwa.test', 'subject' => 'Arabic Language'],
            ['name' => 'Emily', 'last' => 'English', 'email' => 'emily.english@khotwa.test', 'subject' => 'English Language'],
            ['name' => 'Sarah', 'last' => 'Science', 'email' => 'sarah.science@khotwa.test', 'subject' => 'General Science'],
            ['name' => 'Fadi', 'last' => 'Physics', 'email' => 'fadi.physics@khotwa.test', 'subject' => 'Physics'],
            ['name' => 'Carla', 'last' => 'Chemistry', 'email' => 'carla.chemistry@khotwa.test', 'subject' => 'Chemistry'],
            ['name' => 'Bassel', 'last' => 'Biology', 'email' => 'bassel.biology@khotwa.test', 'subject' => 'Biology'],
            ['name' => 'Tariq', 'last' => 'History', 'email' => 'tariq.history@khotwa.test', 'subject' => 'History & Civics'],
            ['name' => 'Ghaida', 'last' => 'Geography', 'email' => 'ghaida.geography@khotwa.test', 'subject' => 'Geography'],
            ['name' => 'Imran', 'last' => 'Islamic', 'email' => 'imran.islamic@khotwa.test', 'subject' => 'Islamic Studies'],
            ['name' => 'Khaled', 'last' => 'Computer', 'email' => 'khaled.computer@khotwa.test', 'subject' => 'Computer Science'],
            ['name' => 'Tareq', 'last' => 'PE', 'email' => 'tareq.pe@khotwa.test', 'subject' => 'Physical Education'],
        ];

        $teacherMap = []; // Map subject to teacher_id
        $teacherObjMap = []; // Map subject to teacher_subject_id
        $insertTeacher = $pdo->prepare("INSERT INTO teachers (first_name, last_name, phone_number, email, password_hash, status, notes) VALUES (?, ?, ?, ?, ?, 'active', ?)");
        $insertTeacherUser = $pdo->prepare("INSERT INTO users (teacher_id, first_name, last_name, email, password_hash, role, status, must_change_password) VALUES (?, ?, ?, ?, ?, 'teacher', 'active', 0)");
        $insertTeacherSubject = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, status) VALUES (?, ?, 'active')");

        $hashedTeacherPassword = password_hash('teacher123', PASSWORD_DEFAULT);

        foreach ($teachersSeed as $t) {
            $phone = '+961 7' . (rand(0, 1) ? '0' : '1') . ' ' . rand(100, 999) . ' ' . rand(100, 999);
            $insertTeacher->execute([$t['name'], $t['last'], $phone, $t['email'], $hashedTeacherPassword, "Specialist " . $t['subject'] . " instructor"]);
            $teacherId = (int)$pdo->lastInsertId();
            $teacherMap[$t['subject']] = $teacherId;

            // Link User Account
            $insertTeacherUser->execute([$teacherId, $t['name'], $t['last'], $t['email'], $hashedTeacherPassword]);
            $counts['users']++;

            // Link Subject
            $subId = $subjectMap[$t['subject']];
            $insertTeacherSubject->execute([$teacherId, $subId]);
            $teacherObjMap[$t['subject']] = (int)$pdo->lastInsertId();
        }
        $counts['teachers'] = count($teachersSeed);
        $counts['teacher_subjects'] = count($teachersSeed);

        // 5. Seed Students
        $boysNames = [
            ['en' => 'Omar', 'ar' => 'عمر'],
            ['en' => 'Youssef', 'ar' => 'يوسف'],
            ['en' => 'Rami', 'ar' => 'رامي'],
            ['en' => 'Kareem', 'ar' => 'كريم'],
            ['en' => 'Ziad', 'ar' => 'زياد'],
            ['en' => 'Hani', 'ar' => 'هاني'],
            ['en' => 'Fadi', 'ar' => 'فادي'],
            ['en' => 'Sami', 'ar' => 'سامي'],
            ['en' => 'Ahmad', 'ar' => 'أحمد'],
            ['en' => 'Ali', 'ar' => 'علي'],
            ['en' => 'Khaled', 'ar' => 'خالد'],
            ['en' => 'Mustafa', 'ar' => 'مصطفى'],
            ['en' => 'Mahmoud', 'ar' => 'محمود'],
            ['en' => 'Layth', 'ar' => 'ليث'],
            ['en' => 'Yasser', 'ar' => 'ياسر'],
            ['en' => 'Hamza', 'ar' => 'حمزة'],
            ['en' => 'Jad', 'ar' => 'جاد'],
            ['en' => 'Nadim', 'ar' => 'نديم']
        ];

        $girlsNames = [
            ['en' => 'Layla', 'ar' => 'ليلى'],
            ['en' => 'Nour', 'ar' => 'نور'],
            ['en' => 'Yasmin', 'ar' => 'ياسمين'],
            ['en' => 'Fatimah', 'ar' => 'فاطمة'],
            ['en' => 'Salma', 'ar' => 'سلمى'],
            ['en' => 'Mariam', 'ar' => 'مريم'],
            ['en' => 'Mona', 'ar' => 'منى'],
            ['en' => 'Reem', 'ar' => 'ريم'],
            ['en' => 'Sarah', 'ar' => 'سارة'],
            ['en' => 'Tala', 'ar' => 'تالا'],
            ['en' => 'Farida', 'ar' => 'فريدة'],
            ['en' => 'Zeina', 'ar' => 'زينة'],
            ['en' => 'Maya', 'ar' => 'مايا'],
            ['en' => 'Judy', 'ar' => 'جودي'],
            ['en' => 'Dana', 'ar' => 'دانا'],
            ['en' => 'Jude', 'ar' => 'جود'],
            ['en' => 'Nadine', 'ar' => 'نادين'],
            ['en' => 'Hana', 'ar' => 'هناء']
        ];

        $familyNames = [
            ['en' => 'Mansour', 'ar' => 'منصور'],
            ['en' => 'Haddad', 'ar' => 'حداد'],
            ['en' => 'Kanaan', 'ar' => 'كنعان'],
            ['en' => 'Al-Masri', 'ar' => 'المصري'],
            ['en' => 'Khalil', 'ar' => 'خليل'],
            ['en' => 'Ghandour', 'ar' => 'غندور'],
            ['en' => 'Zein', 'ar' => 'زين'],
            ['en' => 'Harb', 'ar' => 'حرب'],
            ['en' => 'Said', 'ar' => 'سعيد'],
            ['en' => 'Salhab', 'ar' => 'سلهب'],
            ['en' => 'Rizk', 'ar' => 'رزق'],
            ['en' => 'Daoud', 'ar' => 'داوود'],
            ['en' => 'Najjar', 'ar' => 'نجار'],
            ['en' => 'Halabi', 'ar' => 'حلبي'],
            ['en' => 'Slim', 'ar' => 'سليم'],
            ['en' => 'Awad', 'ar' => 'عوض'],
            ['en' => 'Jaber', 'ar' => 'جابر'],
            ['en' => 'Badawi', 'ar' => 'بدوي']
        ];

        $motherNames = [
            ['en' => 'Samira', 'ar' => 'سميرة'],
            ['en' => 'Hana', 'ar' => 'هناء'],
            ['en' => 'Rania', 'ar' => 'رانيا'],
            ['en' => 'Mona', 'ar' => 'منى'],
            ['en' => 'Leila', 'ar' => 'ليلى'],
            ['en' => 'Fatimah', 'ar' => 'فاطمة'],
            ['en' => 'Salma', 'ar' => 'سلمى'],
            ['en' => 'Maha', 'ar' => 'مها'],
            ['en' => 'Siham', 'ar' => 'سهام'],
            ['en' => 'May', 'ar' => 'مي']
        ];

        $motherLastNames = [
            ['en' => 'Khoury', 'ar' => 'خوري'],
            ['en' => 'Fakhoury', 'ar' => 'فاخوري'],
            ['en' => 'Chahine', 'ar' => 'شاهين'],
            ['en' => 'Gemayel', 'ar' => 'جميل'],
            ['en' => 'Sayegh', 'ar' => 'صايغ'],
            ['en' => 'Matar', 'ar' => 'مطر'],
            ['en' => 'Saab', 'ar' => 'صعب'],
            ['en' => 'Tahan', 'ar' => 'طحان']
        ];

        // Nationality is a lookup row now, so the demo data cycles through the ids
        // the nationalities table actually holds.
        $nationalityIds = $pdo->query(
            'SELECT id FROM nationalities ORDER BY sort_order, id'
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($nationalityIds === []) {
            throw new RuntimeException('No nationalities are configured; run the app once to seed them.');
        }
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        
        $insertStudent = $pdo->prepare(
            "INSERT INTO students (
                first_name_en, father_name_en, last_name_en, mother_name_en, mother_last_name_en,
                first_name_ar, father_name_ar, last_name_ar, mother_name_ar, mother_last_name_ar,
                gender, nationality_id, blood_type, date_of_birth, address, family_status,
                number_of_people_in_household, current_teaching_language,
                father_phone_number, mother_phone_number, home_phone_number,
                parents_assigned_to_whatsapp_group, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );

        $insertMedical = $pdo->prepare(
            "INSERT INTO student_medical_info (
                student_id, has_health_condition, health_condition_details,
                has_special_educational_needs, special_educational_needs_details,
                takes_regular_medicine, medicine_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $insertPhone = $pdo->prepare(
            "INSERT INTO student_other_phone_numbers (student_id, relationship, person_full_name, phone_number, notes)
             VALUES (?, ?, ?, ?, ?)"
        );

        $insertSchedule = $pdo->prepare(
            "INSERT INTO student_school_schedule (student_id, day_of_week, start_time, end_time, notes)
             VALUES (?, ?, ?, ?, ?)"
        );

        $insertAcademic = $pdo->prepare(
            "INSERT INTO student_academic_records (student_id, academic_year, school_name, grade_name, final_total, final_average, is_current, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $insertEnrollment = $pdo->prepare(
            "INSERT INTO student_subject_enrollments (
                student_id, teacher_subject_id, teacher_id, subject_id,
                academic_year, start_date, end_date, status
            ) VALUES (?, ?, ?, ?, 2025, '2025-09-01', '2026-06-30', 'active')"
        );

        $insertSubscription = $pdo->prepare(
            "INSERT INTO student_subscriptions (student_id, start_date, default_monthly_amount, status)
             VALUES (?, '2025-09-01', ?, 'active')"
        );

        $insertSubMonth = $pdo->prepare(
            "INSERT INTO student_subscription_months (
                subscription_id, student_id, billing_year, billing_month, period_start, period_end,
                billing_type, expected_amount, paid_amount, payment_status, last_payment_date
            ) VALUES (?, ?, ?, ?, ?, ?, 'full_month', ?, 0.00, 'not_paid', NULL)"
        );

        $insertPayment = $pdo->prepare(
            "INSERT INTO student_subscription_payments (subscription_month_id, student_id, paid_amount, paid_at, receipt_number, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $updateSubMonth = $pdo->prepare(
            "UPDATE student_subscription_months
             SET paid_amount = ?, payment_status = ?, last_payment_date = ?
             WHERE id = ?"
        );

        $students = []; // Keep track of student IDs and their grades for attendance
        $studentEnrollments = []; // Store active enrollments for attendance seeding

        $counts['students'] = 0;
        $counts['student_medical_info'] = 0;
        $counts['student_other_phone_numbers'] = 0;
        $counts['student_school_schedule'] = 0;
        $counts['student_academic_records'] = 0;
        $counts['student_subject_enrollments'] = 0;
        $counts['student_subscriptions'] = 0;
        $counts['student_subscription_months'] = 0;
        $counts['student_subscription_payments'] = 0;
        $counts['parent_students'] = 0;

        $receiptCounter = 1001;

        // Generate for Grade 1 till 12
        for ($grade = 1; $grade <= 12; $grade++) {
            $gradeName = "Grade " . $grade;
            
            // 6 students per grade: 3 boys, 3 girls
            for ($sIndex = 1; $sIndex <= 6; $sIndex++) {
                $isBoy = $sIndex <= 3;
                $fn = $isBoy ? $boysNames[($grade * 5 + $sIndex) % count($boysNames)] : $girlsNames[($grade * 5 + $sIndex) % count($girlsNames)];
                $father = $boysNames[($grade * 3 + $sIndex * 2) % count($boysNames)];
                $family = $familyNames[($grade * 2 + $sIndex * 7) % count($familyNames)];
                $mother = $motherNames[($grade * 7 + $sIndex * 4) % count($motherNames)];
                $motherLast = $motherLastNames[($grade * 4 + $sIndex * 3) % count($motherLastNames)];

                $gender = $isBoy ? 'male' : 'female';
                $nat = (int) $nationalityIds[($grade + $sIndex) % count($nationalityIds)];
                $blood = $bloodTypes[($grade * 3 + $sIndex) % count($bloodTypes)];
                
                // Birth year logic: Grade 1 is ~6 (born 2019), Grade 12 is ~17 (born 2008)
                $birthYear = 2025 - (5 + $grade);
                $birthMonth = str_pad((string)rand(1, 12), 2, '0', STR_PAD_LEFT);
                $birthDay = str_pad((string)rand(1, 28), 2, '0', STR_PAD_LEFT);
                $dob = "{$birthYear}-{$birthMonth}-{$birthDay}";

                $address = "Street " . rand(10, 99) . ", Block " . rand(1, 5) . ", " . ['Hamra', 'Achrafieh', 'Badaro', 'Sin El Fil', 'Sidon', 'Tripoli'][rand(0, 5)];
                $familyStatus = ['Married', 'Married', 'Married', 'Divorced', 'Widowed'][rand(0, 4)];
                $household = rand(3, 7);
                $lang = ($grade + $sIndex) % 2 === 0 ? 'French' : 'English';
                
                $phoneF = '+961 70 ' . rand(100, 999) . ' ' . rand(100, 999);
                $phoneM = '+961 71 ' . rand(100, 999) . ' ' . rand(100, 999);
                $phoneH = '+961 1 ' . rand(100, 999) . ' ' . rand(100, 999);
                $whatsapp = rand(0, 1);

                // Insert student
                $insertStudent->execute([
                    $fn['en'], $father['en'], $family['en'], $mother['en'], $motherLast['en'],
                    $fn['ar'], $father['ar'], $family['ar'], $mother['ar'], $motherLast['ar'],
                    $gender, $nat, $blood, $dob, $address, $familyStatus,
                    $household, $lang, $phoneF, $phoneM, $phoneH, $whatsapp
                ]);
                $studentId = (int)$pdo->lastInsertId();
                $students[] = ['id' => $studentId, 'grade' => $grade];
                $counts['students']++;

                // Medical Info
                $hasCond = rand(1, 10) === 1 ? 1 : 0;
                $condDetails = $hasCond ? ['Mild Asthma - carries inhaler', 'Lactose intolerant', 'Penicillin Allergy'][rand(0, 2)] : null;
                $hasNeeds = rand(1, 15) === 1 ? 1 : 0;
                $needsDetails = $hasNeeds ? ['Requires font enlargement', 'Needs front row seating for attention focus', 'Dyslexia assistance'][rand(0, 2)] : null;
                $hasMeds = rand(1, 20) === 1 ? 1 : 0;
                $medDetails = $hasMeds ? 'Takes allergy pills during spring' : null;

                $insertMedical->execute([$studentId, $hasCond, $condDetails, $hasNeeds, $needsDetails, $hasMeds, $medDetails]);
                $counts['student_medical_info']++;

                // Other phone numbers (relative/driver)
                if (rand(0, 1)) {
                    $relType = ['Uncle', 'Grandfather', 'Driver', 'Aunt'][rand(0, 3)];
                    $relFn = $boysNames[rand(0, count($boysNames)-1)]['en'] . ' ' . $familyNames[rand(0, count($familyNames)-1)]['en'];
                    $relPh = '+961 76 ' . rand(100, 999) . ' ' . rand(100, 999);
                    $insertPhone->execute([$studentId, $relType, $relFn, $relPh, "Emergency backup number"]);
                    $counts['student_other_phone_numbers']++;
                }

                // Schedule: Sunday, Tuesday, Thursday
                $insertSchedule->execute([$studentId, 'sunday', '08:00:00', '10:00:00', 'Morning session block']);
                $insertSchedule->execute([$studentId, 'tuesday', '10:15:00', '12:15:00', 'Midday session block']);
                $insertSchedule->execute([$studentId, 'thursday', '12:30:00', '14:30:00', 'Afternoon session block']);
                $counts['student_school_schedule'] += 3;

                // Academic records (Current year + past years)
                // Current Year 2025
                $insertAcademic->execute([$studentId, 2025, 'Khotwa Education Center', $gradeName, null, null, 1, 'Current academic year enrolment']);
                $counts['student_academic_records']++;

                // Past Year 2024
                if ($grade > 1) {
                    $pastGrade = "Grade " . ($grade - 1);
                    $avg = rand(76, 98) + (rand(0, 99) / 100);
                    $tot = $avg * 5;
                    $insertAcademic->execute([$studentId, 2024, 'Public Arabic School', $pastGrade, $tot, $avg, 0, 'Completed previous grade successfully']);
                    $counts['student_academic_records']++;
                }
                
                // Historical Year 2023
                if ($grade > 2) {
                    $histGrade = "Grade " . ($grade - 2);
                    $avg = rand(72, 97) + (rand(0, 99) / 100);
                    $tot = $avg * 5;
                    $insertAcademic->execute([$studentId, 2023, 'Public Arabic School', $histGrade, $tot, $avg, 0, 'Promoted with good standing']);
                    $counts['student_academic_records']++;
                }

                // Enrollments (Determine subjects based on grade)
                $curriculum = ['Mathematics', 'Arabic Language', 'English Language', 'Islamic Studies', 'Physical Education'];
                if ($grade <= 9) {
                    $curriculum[] = 'General Science';
                    if ($grade >= 4) {
                        $curriculum[] = 'History & Civics';
                        $curriculum[] = 'Geography';
                    }
                } else {
                    $curriculum[] = 'Physics';
                    $curriculum[] = 'Chemistry';
                    $curriculum[] = 'Biology';
                    $curriculum[] = 'Computer Science';
                }

                foreach ($curriculum as $subjectName) {
                    $tsId = $teacherObjMap[$subjectName];
                    $tId = $teacherMap[$subjectName];
                    $sId = $subjectMap[$subjectName];

                    $insertEnrollment->execute([$studentId, $tsId, $tId, $sId]);
                    $enrollId = (int)$pdo->lastInsertId();
                    
                    $studentEnrollments[] = [
                        'enrollment_id' => $enrollId,
                        'student_id' => $studentId,
                        'teacher_subject_id' => $tsId,
                        'teacher_id' => $tId,
                        'subject_id' => $sId
                    ];
                    $counts['student_subject_enrollments']++;
                }

                // Subscriptions and payments
                $defaultAmount = ($grade <= 6) ? 100.00 : (($grade <= 9) ? 120.00 : 150.00);
                $insertSubscription->execute([$studentId, $defaultAmount]);
                $subscriptionId = (int)$pdo->lastInsertId();
                $counts['student_subscriptions']++;

                // Months: Sept 2025 (9) to June 2026 (6)
                $monthsConfig = [
                    ['year' => 2025, 'month' => 9,  'status_chance' => 'paid'],
                    ['year' => 2025, 'month' => 10, 'status_chance' => 'paid'],
                    ['year' => 2025, 'month' => 11, 'status_chance' => 'paid'],
                    ['year' => 2025, 'month' => 12, 'status_chance' => 'paid'],
                    ['year' => 2026, 'month' => 1,  'status_chance' => 'paid'],
                    ['year' => 2026, 'month' => 2,  'status_chance' => 'paid_mostly'],
                    ['year' => 2026, 'month' => 3,  'status_chance' => 'paid_mostly'],
                    ['year' => 2026, 'month' => 4,  'status_chance' => 'partial_mostly'],
                    ['year' => 2026, 'month' => 5,  'status_chance' => 'unpaid_mostly'],
                    ['year' => 2026, 'month' => 6,  'status_chance' => 'unpaid']
                ];

                foreach ($monthsConfig as $m) {
                    $year = $m['year'];
                    $month = $m['month'];
                    $lastDay = date('t', strtotime("{$year}-{$month}-01"));
                    $periodStart = "{$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . "-01";
                    $periodEnd = "{$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . "-{$lastDay}";

                    $insertSubMonth->execute([$subscriptionId, $studentId, $year, $month, $periodStart, $periodEnd, $defaultAmount]);
                    $subMonthId = (int)$pdo->lastInsertId();
                    $counts['student_subscription_months']++;

                    // Determine payment
                    $mode = $m['status_chance'];
                    $paidAmount = 0.00;
                    $payStatus = 'not_paid';
                    $payDate = null;

                    if ($mode === 'paid') {
                        $paidAmount = $defaultAmount;
                        $payStatus = 'paid';
                    } elseif ($mode === 'paid_mostly') {
                        $roll = rand(1, 100);
                        if ($roll <= 92) {
                            $paidAmount = $defaultAmount;
                            $payStatus = 'paid';
                        } elseif ($roll <= 98) {
                            $paidAmount = $defaultAmount / 2.0;
                            $payStatus = 'partial_paid';
                        } else {
                            $paidAmount = 0.00;
                            $payStatus = 'not_paid';
                        }
                    } elseif ($mode === 'partial_mostly') {
                        $roll = rand(1, 100);
                        if ($roll <= 60) {
                            $paidAmount = $defaultAmount;
                            $payStatus = 'paid';
                        } elseif ($roll <= 90) {
                            $paidAmount = $defaultAmount / 2.0;
                            $payStatus = 'partial_paid';
                        } else {
                            $paidAmount = 0.00;
                            $payStatus = 'not_paid';
                        }
                    } elseif ($mode === 'unpaid_mostly') {
                        $roll = rand(1, 100);
                        if ($roll <= 20) {
                            $paidAmount = $defaultAmount;
                            $payStatus = 'paid';
                        } elseif ($roll <= 50) {
                            $paidAmount = $defaultAmount / 2.0;
                            $payStatus = 'partial_paid';
                        } else {
                            $paidAmount = 0.00;
                            $payStatus = 'not_paid';
                        }
                    } else { // unpaid
                        $roll = rand(1, 100);
                        if ($roll <= 5) {
                            $paidAmount = $defaultAmount;
                            $payStatus = 'paid';
                        } elseif ($roll <= 15) {
                            $paidAmount = $defaultAmount / 2.0;
                            $payStatus = 'partial_paid';
                        }
                    }

                    if ($paidAmount > 0) {
                        $payDay = str_pad((string)rand(1, 10), 2, '0', STR_PAD_LEFT);
                        $payDate = "{$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . "-{$payDay}";
                        $receipt = "REC-{$year}" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . "-" . $receiptCounter++;
                        
                        $insertPayment->execute([
                            $subMonthId,
                            $studentId,
                            $paidAmount,
                            $payDate,
                            $receipt,
                            "Monthly tuition fees payment"
                        ]);
                        $counts['student_subscription_payments']++;

                        // Update billing month record
                        $updateSubMonth->execute([$paidAmount, $payStatus, $payDate, $subMonthId]);
                    }
                }
            }
        }

            // Parent portal demo accounts and parent/student assignments.
            $insertParentUser = $pdo->prepare(
              "INSERT INTO users (
                teacher_id, first_name, last_name, email, password_hash,
                role, status, must_change_password, notes
               ) VALUES (NULL, ?, ?, ?, ?, 'parent', 'active', 0, ?)"
            );
            $insertParentStudent = $pdo->prepare(
              "INSERT INTO parent_students (parent_user_id, student_id, status)
               VALUES (?, ?, 'active')"
            );
            $hashedParentPassword = password_hash('parent123', PASSWORD_DEFAULT);

            $insertParentUser->execute([
              'Rami',
              'Mansour',
              'parent.one@khotwa.test',
              $hashedParentPassword,
              'Demo parent account linked to two students',
            ]);
            $parentOneId = (int) $pdo->lastInsertId();
            $counts['users']++;

            $insertParentUser->execute([
              'Nour',
              'Haddad',
              'parent.two@khotwa.test',
              $hashedParentPassword,
              'Demo parent account linked to three students',
            ]);
            $parentTwoId = (int) $pdo->lastInsertId();
            $counts['users']++;

            if (isset($students[0]['id'], $students[1]['id'], $students[2]['id'], $students[3]['id'], $students[4]['id'])) {
              $insertParentStudent->execute([$parentOneId, (int) $students[0]['id']]);
              $counts['parent_students']++;
              $insertParentStudent->execute([$parentOneId, (int) $students[1]['id']]);
              $counts['parent_students']++;

              $insertParentStudent->execute([$parentTwoId, (int) $students[2]['id']]);
              $counts['parent_students']++;
              $insertParentStudent->execute([$parentTwoId, (int) $students[3]['id']]);
              $counts['parent_students']++;
              $insertParentStudent->execute([$parentTwoId, (int) $students[4]['id']]);
              $counts['parent_students']++;
            }

        // 6. Seed Attendance (Last 5 class days: Sun June 7 to Thu June 11, 2026)
        $schoolDays = [
            '2026-06-07', // Sunday
            '2026-06-08', // Monday
            '2026-06-09', // Tuesday
            '2026-06-10', // Wednesday
            '2026-06-11'  // Thursday
        ];

        $insertDailyAttendance = $pdo->prepare(
            "INSERT INTO student_daily_attendance (student_id, attendance_date, check_in_time, check_out_time, status, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $insertSubjectAttendance = $pdo->prepare(
            "INSERT INTO student_subject_attendance (
                daily_attendance_id, student_id, attendance_date,
                teacher_subject_id, teacher_id, subject_id,
                session_number, status, homework_note, notes
             ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)"
        );

        $counts['student_daily_attendance'] = 0;
        $counts['student_subject_attendance'] = 0;

        foreach ($schoolDays as $date) {
            foreach ($students as $st) {
                $studentId = $st['id'];
                
                // Roll status: 90% present, 5% late, 3% absent, 2% excused
                $roll = rand(1, 100);
                if ($roll <= 90) {
                    $status = 'present';
                    $checkIn = '07:' . str_pad((string)rand(45, 59), 2, '0', STR_PAD_LEFT) . ':00';
                    $checkOut = '14:' . str_pad((string)rand(0, 10), 2, '0', STR_PAD_LEFT) . ':00';
                    $notes = 'On time';
                } elseif ($roll <= 95) {
                    $status = 'late';
                    $checkIn = '08:' . str_pad((string)rand(1, 25), 2, '0', STR_PAD_LEFT) . ':00';
                    $checkOut = '14:00:00';
                    $notes = 'Arrived late, traffic delay';
                } elseif ($roll <= 98) {
                    $status = 'absent';
                    $checkIn = null;
                    $checkOut = null;
                    $notes = 'No notification from parents';
                } else {
                    $status = 'excused';
                    $checkIn = null;
                    $checkOut = null;
                    $notes = 'Parent called - sick leave';
                }

                $insertDailyAttendance->execute([$studentId, $date, $checkIn, $checkOut, $status, $notes]);
                $dailyId = (int)$pdo->lastInsertId();
                $counts['student_daily_attendance']++;

                // Get student enrollments
                $myEnrollments = array_filter($studentEnrollments, fn($e) => $e['student_id'] === $studentId);

                // Assign subject attendance for their enrolled subjects
                foreach ($myEnrollments as $e) {
                    $subStatus = 'missed';
                    $homework = null;
                    $subNotes = null;

                    if ($status === 'present' || $status === 'late') {
                        // 96% chance they attended the subject session if they are present in school
                        if (rand(1, 100) <= 96) {
                            $subStatus = 'attended';
                            
                            // 20% chance of a homework note
                            if (rand(1, 5) === 1) {
                                $homework = ['Completed fully', 'Well prepared', 'Incomplete exercises', 'Forgot notebook'][rand(0, 3)];
                            }
                            // 15% chance of teacher note
                            if (rand(1, 7) === 1) {
                                $subNotes = ['Active class participation', 'Excellent focus today', 'Easily distracted', 'Struggled with the exercises'][rand(0, 3)];
                            }
                        }
                    }

                    $insertSubjectAttendance->execute([
                        $dailyId,
                        $studentId,
                        $date,
                        $e['teacher_subject_id'],
                        $e['teacher_id'],
                        $e['subject_id'],
                        $subStatus,
                        $homework,
                        $subNotes
                    ]);
                    $counts['student_subject_attendance']++;
                }
            }
        }

        // 7. Seed Warnings (Oral/Written)
        $warningsSeed = [
            ['reason' => 'Repeatedly disruptive behavior and talking during explanation.', 'type' => 'oral', 'action' => 'Verbal warning and seated away from friends.'],
            ['reason' => 'Failure to submit homework assignments 3 times in a row.', 'type' => 'oral', 'action' => 'Contacted parent via Whatsapp and assigned makeup homework.'],
            ['reason' => 'Arriving late to sessions repeatedly without excuse.', 'type' => 'oral', 'action' => 'Spoke to student and student promised punctuality.'],
            ['reason' => 'Damaged learning materials and refused to cooperate in class.', 'type' => 'written', 'action' => 'Official warning letter sent to parent, scheduled a meeting.'],
            ['reason' => 'Leaving class session early without teacher permission.', 'type' => 'written', 'action' => 'Meeting with coordinator and parent notified.'],
            ['reason' => 'Severe lack of academic effort and refusing to write notes in class.', 'type' => 'oral', 'action' => 'Academic warning and requested homework check next session.'],
        ];

        $insertWarning = $pdo->prepare(
            "INSERT INTO student_warnings (
                student_id, teacher_id, warning_date, warning_type, warning_number,
                conversation_minutes, reason, parent_message, parent_notified, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $counts['student_warnings'] = 0;
        // Select 6 random students
        $warningStudents = array_slice($students, 0, count($warningsSeed));
        shuffle($warningStudents);

        foreach ($warningsSeed as $idx => $w) {
            $studentId = $warningStudents[$idx]['id'];
            
            // Get student enrollments to associate with one of their teachers
            $myEnrollments = array_values(array_filter($studentEnrollments, fn($e) => $e['student_id'] === $studentId));
            $tId = (!empty($myEnrollments)) ? $myEnrollments[0]['teacher_id'] : null;

            $dateOffset = rand(10, 60);
            $warnDate = date('Y-m-d', strtotime("2026-06-12 -{$dateOffset} days"));
            $num = rand(1, 2);
            $minutes = [15, 20, 30, 45][rand(0, 3)];
            $notified = rand(0, 1);

            $insertWarning->execute([
                $studentId, $tId, $warnDate, $w['type'], $num,
                $minutes, $w['reason'], $w['action'], $notified,
                "Follow up session scheduled for next week to monitor progress."
            ]);
            $counts['student_warnings']++;
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $pdo->commit();
        return $counts;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        throw $e;
    }
}

// Perform action if POST or CLI
$seeded = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || $isCli) {
    try {
        $pdo = getDatabaseConnection();
        $results = seedDatabase($pdo);
        $seeded = true;
        $message = "Database has been populated successfully!";
        if ($isCli) {
            echo "SUCCESS: Seeding completed successfully!\n";
            echo "Results summary:\n";
            foreach ($results as $tbl => $cnt) {
                echo " - {$tbl}: {$cnt} records created\n";
            }
            exit(0);
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        if ($isCli) {
            echo "ERROR: Seeding failed!\n" . $error . "\n";
            exit(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Initialization & Seeding | Khotwa</title>
  <?= khotwa_head_fonts() ?>
  <style>
    :root {
      --navy: #223f6b;
      --navy-dark: #142a49;
      --navy-deep: #0b1c34;
      --orange: #f49f0f;
      --green: #4fbb37;
      --pink: #e51c6f;
      --paper: #ffffff;
      --soft: #f5f7fb;
      --line: #dfe5ee;
      --font-en: 'RB', 'Segoe UI', Tahoma, 'Noto Sans Arabic', sans-serif;
      --font-ar: 'RB', 'Segoe UI', Tahoma, 'Noto Sans Arabic', sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-en);
      background: var(--navy-deep);
      color: #eaeef6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      position: relative;
      overflow-x: hidden;
    }

    /* Background blobs */
    body::before,
    body::after {
      content: "";
      position: absolute;
      width: 40vw;
      height: 40vw;
      border-radius: 50%;
      filter: blur(150px);
      z-index: -1;
      opacity: 0.15;
      pointer-events: none;
    }

    body::before {
      background: var(--orange);
      top: -10vw;
      left: -10vw;
    }

    body::after {
      background: var(--pink);
      bottom: -10vw;
      right: -10vw;
    }

    .container {
      width: 100%;
      max-width: 650px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 24px;
      padding: 40px;
      backdrop-filter: blur(20px);
      box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
      animation: loadIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }

    @keyframes loadIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 30px;
    }

    .brand-logo {
      width: 50px;
      height: 50px;
      background: var(--navy);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 16px;
      display: grid;
      place-items: center;
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--paper);
    }

    .brand-logo span {
      color: var(--orange);
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      line-height: 1.1;
    }

    .brand-text strong {
      font-size: 1.15rem;
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    .brand-text small {
      font-size: 0.68rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      opacity: 0.6;
      margin-top: 4px;
    }

    h1 {
      font-size: 1.8rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      margin-bottom: 12px;
      color: var(--paper);
      display: flex;
      flex-direction: column;
    }

    h1 span.ar-title {
      font-family: var(--font-ar);
      font-size: 1.6rem;
      color: var(--orange);
      margin-top: 4px;
    }

    p.subtitle {
      color: #92a1b9;
      font-size: 0.95rem;
      line-height: 1.6;
      margin-bottom: 30px;
    }

    .alert {
      padding: 18px 20px;
      border-radius: 16px;
      margin-bottom: 25px;
      font-size: 0.88rem;
      line-height: 1.5;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .alert-success {
      border: 1px solid rgba(79, 187, 55, 0.25);
      background: rgba(79, 187, 55, 0.08);
      color: #79e961;
    }

    .alert-error {
      border: 1px solid rgba(229, 28, 111, 0.25);
      background: rgba(229, 28, 111, 0.08);
      color: #ff5f9e;
    }

    .alert-warning {
      border: 1px solid rgba(244, 159, 15, 0.25);
      background: rgba(244, 159, 15, 0.08);
      color: #ffb834;
    }

    .seeding-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      font-size: 0.88rem;
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      overflow: hidden;
    }

    .seeding-table th, 
    .seeding-table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .seeding-table th {
      background: rgba(255, 255, 255, 0.05);
      color: var(--paper);
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
    }

    .seeding-table tr:last-child td {
      border-bottom: 0;
    }

    .seeding-table tr:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 6px;
      font-weight: 800;
      font-size: 0.75rem;
      background: rgba(34, 63, 107, 0.4);
      color: #9abce8;
      border: 1px solid rgba(34, 63, 107, 0.6);
    }

    .btn {
      display: inline-flex;
      width: 100%;
      height: 55px;
      align-items: center;
      justify-content: center;
      border-radius: 14px;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.25s ease;
      text-decoration: none;
      border: 0;
    }

    .btn-primary {
      background: var(--navy);
      color: var(--paper);
      box-shadow: 0 10px 25px rgba(34, 63, 107, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-primary:hover {
      background: var(--navy-dark);
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(34, 63, 107, 0.4);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.05);
      color: #eaeef6;
      border: 1px solid rgba(255, 255, 255, 0.08);
      margin-top: 12px;
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }

    .info-list {
      list-style: none;
      display: grid;
      gap: 12px;
      margin-bottom: 30px;
      font-size: 0.88rem;
      line-height: 1.5;
    }

    .info-list li {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      color: #b0c0d7;
    }

    .info-list li::before {
      content: "✓";
      color: var(--orange);
      font-weight: 800;
    }

    .loader {
      display: none;
      flex-direction: column;
      align-items: center;
      gap: 15px;
      margin: 20px 0;
    }

    .spinner {
      width: 40px;
      height: 40px;
      border: 3px solid rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      border-top-color: var(--orange);
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .success-footer {
      display: grid;
      gap: 15px;
      margin-top: 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      padding-top: 25px;
    }

    .credentials-card {
      background: rgba(244, 159, 15, 0.05);
      border: 1px solid rgba(244, 159, 15, 0.15);
      border-radius: 14px;
      padding: 18px;
      font-size: 0.84rem;
      margin-top: 20px;
    }

    .credentials-card h3 {
      font-size: 0.9rem;
      color: var(--orange);
      margin-bottom: 10px;
      font-weight: 700;
    }

    .credentials-card p {
      margin-bottom: 6px;
      color: #c9d5e8;
    }

    .credentials-card code {
      font-family: var(--font-en);
      background: rgba(0, 0, 0, 0.2);
      padding: 2px 6px;
      border-radius: 4px;
      color: var(--paper);
    }
  </style>
  <script>
    function startSeeding() {
      document.getElementById('setup-form').style.display = 'none';
      document.getElementById('setup-loader').style.display = 'flex';
    }
  </script>
</head>
<body>
  <div class="container">
    <header class="brand">
      <div class="brand-logo">K<span>.</span></div>
      <div class="brand-text">
        <strong>Khotwa</strong>
        <small>Education Center</small>
      </div>
    </header>

    <?php if ($seeded): ?>
      <h1>
        Database Ready!
        <span class="ar-title">قاعدة البيانات جاهزة!</span>
      </h1>
      <p class="subtitle">The database tables have been successfully initialized and populated with high-density mock data.</p>
      
      <div class="alert alert-success">
        <strong>Status: Success</strong>
        All seed data has been loaded and the database constraints are properly set up.
      </div>

      <table class="seeding-table">
        <thead>
          <tr>
            <th>Data Table</th>
            <th>Records Seeded</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $table => $count): ?>
            <tr>
              <td><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $table))) ?></strong></td>
              <td><span class="badge"><?= $count ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="credentials-card">
        <h3>Portal Demo Login Credentials</h3>
        <p><strong>Admin Panel:</strong> <code>admin@khotwa.test</code> / password: <code>admin123</code></p>
        <p><strong>Manager Panel:</strong> <code>manager@khotwa.test</code> / password: <code>manager123</code></p>
        <p><strong>Teacher Panel:</strong> <code>maya.math@khotwa.test</code> / password: <code>teacher123</code></p>
      </div>

      <div class="success-footer">
        <a href="<?= e(khotwa_url('login.php')) ?>" class="btn btn-primary">Proceed to Portal Login</a>
        <a href="<?= e(khotwa_url('index.php')) ?>" class="btn btn-secondary">Go to Homepage</a>
      </div>

    <?php else: ?>
      <h1>
        Initialize Portal Data
        <span class="ar-title">تهيئة بيانات البوابة التعليمية</span>
      </h1>
      <p class="subtitle">Configure Khotwa Education Center database tables and seed high-density realistic mock data for Grades 1 through 12 Arabic schools.</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error">
          <strong>Database Error</strong>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="alert alert-warning">
        <strong>Warning: Data Reset</strong>
        Running this process will TRUNCATE and completely wipe out all existing data in center databases to load a clean seeded dataset.
      </div>

      <ul class="info-list">
        <li><strong>Subjects:</strong> 12 core curriculum Arabic-school subjects</li>
        <li><strong>Staff:</strong> 12 teacher profiles with associated portal log-ins</li>
        <li><strong>Students:</strong> 72 profiles (6 per grade) with complete contact, medical, and history logs</li>
        <li><strong>Linkage:</strong> Many-to-many subject enrollments linking teachers and students</li>
        <li><strong>Finance:</strong> Complete tuition subscriptions and payments for the 2025-2026 academic year</li>
        <li><strong>Attendance:</strong> 5 full days of daily and class attendance history</li>
        <li><strong>Warnings:</strong> Set of oral and written behavior records</li>
      </ul>

      <form id="setup-form" method="post" onsubmit="startSeeding()">
        <button type="submit" class="btn btn-primary">Initialize & Seed Database</button>
        <a href="<?= e(khotwa_url('login.php')) ?>" class="btn btn-secondary">Cancel and Return</a>
      </form>

      <div id="setup-loader" class="loader">
        <div class="spinner"></div>
        <p>Generating database records, creating relationships, and hashing passwords... Please wait.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
