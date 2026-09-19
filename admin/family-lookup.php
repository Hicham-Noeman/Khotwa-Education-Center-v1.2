<?php
declare(strict_types=1);

/**
 * The sibling picker behind "Copy from a sibling" on the new-student form.
 *
 * Two questions, one endpoint: which students are on file, and what does one
 * student's household look like. It only ever reads - the copying happens in the
 * form, so nothing is written until the admin saves the new student themselves.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/admin-data.php';

header('Content-Type: application/json; charset=utf-8');

try {
    require_roles(['admin', 'manager']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(admin_csrf_token(), $csrf)) {
        throw new RuntimeException('Invalid session token. Refresh the page and try again.');
    }

    $pdo = khotwa_db();
    $action = (string) ($_POST['action'] ?? 'list');

    if ($action === 'list') {
        /*
         * Ordered by family name so siblings sit together in the list - the
         * person scrolling it is looking for a surname, not a first name.
         */
        $rows = $pdo->query(
            "SELECT students.id,
                    CONCAT(students.first_name_en, ' ', students.last_name_en) AS name_en,
                    CONCAT(students.first_name_ar, ' ', students.last_name_ar) AS name_ar,
                    students.last_name_en AS family_en,
                    TRIM(CONCAT(students.father_name_en, ' ', students.last_name_en)) AS father_name,
                    COALESCE(current_record.grade_name, '') AS grade_name,
                    COUNT(parent_students.id) AS parent_count
             FROM students
             LEFT JOIN student_academic_records AS current_record
               ON current_record.student_id = students.id AND current_record.is_current = 1
             LEFT JOIN parent_students
               ON parent_students.student_id = students.id AND parent_students.status = 'active'
             WHERE students.status <> 'left'
             GROUP BY students.id
             ORDER BY students.last_name_en, students.first_name_en"
        )->fetchAll();

        echo json_encode([
            'success' => true,
            'students' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name_en' => (string) $row['name_en'],
                'name_ar' => (string) $row['name_ar'],
                'family' => (string) $row['family_en'],
                'father' => (string) $row['father_name'],
                'grade' => (string) $row['grade_name'],
                'has_parent' => (int) $row['parent_count'] > 0,
            ], $rows),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'fields') {
        throw new RuntimeException('Unknown action.');
    }

    $studentId = (int) ($_POST['student_id'] ?? 0);
    if ($studentId < 1) {
        throw new RuntimeException('Choose a student to copy from.');
    }

    // Only the household columns leave this endpoint; the child's own details
    // are none of the new record's business.
    $shared = admin_family_shared_columns();
    $select = implode(', ', array_map('admin_quote_identifier', $shared));
    $statement = $pdo->prepare(
        "SELECT {$select}, CONCAT(first_name_en, ' ', last_name_en) AS source_name
         FROM students WHERE id = ? LIMIT 1"
    );
    $statement->execute([$studentId]);
    $student = $statement->fetch();
    if (!$student) {
        throw new RuntimeException('That student could not be found.');
    }

    $fields = [];
    foreach ($shared as $column) {
        $fields[$column] = $student[$column] === null ? '' : (string) $student[$column];
    }

    /*
     * The family's parent account, if they have one. A sibling must join the
     * account that already exists rather than get a second one - otherwise the
     * same parent ends up with two logins and a child behind each.
     */
    $parentStatement = $pdo->prepare(
        "SELECT users.id, users.first_name, users.last_name, users.email
         FROM parent_students
         INNER JOIN users ON users.id = parent_students.parent_user_id
         WHERE parent_students.student_id = ?
           AND parent_students.status = 'active'
           AND users.role = 'parent'
         ORDER BY users.id
         LIMIT 1"
    );
    $parentStatement->execute([$studentId]);
    $parent = $parentStatement->fetch();

    echo json_encode([
        'success' => true,
        'source_name' => (string) $student['source_name'],
        'fields' => $fields,
        'parent' => $parent === false ? null : [
            'id' => (int) $parent['id'],
            'name' => trim((string) $parent['first_name'] . ' ' . (string) ($parent['last_name'] ?? '')),
            'email' => (string) $parent['email'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
