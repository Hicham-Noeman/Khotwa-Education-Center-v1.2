<?php
/*
 * Fills the warnings board with test data: ten warnings in each of the three stages.
 *
 * Every row carries the audit fields the real workflow would have written, so the
 * board, the parent portal and the yearly summary all behave as they would in use:
 *
 *   flagged    teacher raised it, nothing else set, invisible to parents
 *   issued     admin picked oral/written, parent notified, awaiting an expiation
 *   assigned   parent chose an age-appropriate expiation
 *
 * Completing or rejecting a warning deletes it, so those are not states to seed.
 *
 * Re-running replaces only the rows this script created; anything else is left alone.
 *
 * Usage: php tools/seed-warnings.php
 */
declare(strict_types=1);
define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/database.php';

const DEMO_MARKER = '[seed-warnings]';
const PER_STATUS = 10;

$pdo = getDatabaseConnection();

$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
$teacherIds = array_map('intval', array_column(
    $pdo->query("SELECT id FROM teachers WHERE status = 'active' ORDER BY id")->fetchAll(),
    'id'
));

// Students a parent account can actually see, so the parent side is testable too.
$linked = $pdo->query(
    "SELECT ps.student_id, ps.parent_user_id,
            TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
     FROM parent_students ps
     INNER JOIN students s ON s.id = ps.student_id
     WHERE ps.status = 'active'
     ORDER BY ps.student_id"
)->fetchAll();

$others = $pdo->query(
    "SELECT s.id AS student_id, NULL AS parent_user_id,
            TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
     FROM students s
     WHERE s.status = 'active'
       AND s.id NOT IN (SELECT student_id FROM parent_students WHERE status = 'active')
     ORDER BY s.id"
)->fetchAll();

if ($adminId < 1 || $teacherIds === [] || $linked === [] || $others === []) {
    exit("Cannot seed: the database needs an admin, active teachers, students and parent links.\n");
}

// Expiations grouped by the age range they belong to, so a choice is always valid.
$expiationsByAge = [];
foreach ($pdo->query(
    "SELECT e.id, g.min_age, g.max_age
     FROM expiations e
     INNER JOIN age_groups g ON g.id = e.age_group_id AND g.status = 'active'
     WHERE e.status = 'active'
     ORDER BY e.id"
) as $row) {
    $expiationsByAge[] = $row;
}

function expiationForAge(array $expiations, int $age, int $pick): ?int
{
    $fit = array_values(array_filter(
        $expiations,
        static fn (array $e): bool => $age >= (int) $e['min_age'] && $age <= (int) $e['max_age']
    ));

    return $fit === [] ? null : (int) $fit[$pick % count($fit)]['id'];
}

$reasons = [
    'Arriving late to sessions repeatedly without excuse.',
    'Leaving class session early without teacher permission.',
    'Failure to submit homework assignments three times in a row.',
    'Repeatedly disruptive behaviour and talking during explanation.',
    'Damaged learning materials and refused to cooperate in class.',
    'Severe lack of academic effort and refusing to write notes in class.',
    'Using a mobile phone during the session after being asked to stop.',
    'Disrespectful language towards a classmate during group work.',
    'Skipped a scheduled subject session without informing the centre.',
    'Refused to take part in the session activity for the whole period.',
];

// These are written to the parent, so they explain the incident and the outcome.
$messages = [
    "Your son arrived late to three sessions this week without an excuse. We spoke with him about how much of the lesson he misses, and agreed he will be at the centre ten minutes early from now on. Please help us keep to that at home.",
    "Your daughter left the session before it ended without telling the teacher. We explained why that is not allowed and she understood. She has agreed to ask permission first from now on.",
    "Homework was not submitted for three sessions in a row. We went through the missing work with your son and agreed a daily check with his subject teacher. We would appreciate a look at his notebook in the evenings.",
    "Your son was talking during the explanation and the teacher had to stop the lesson more than once. We spoke with him privately and he apologised. He has been moved to the front row for two weeks.",
    "Learning materials were damaged during the session and your daughter did not want to cooperate when asked about it. We have spoken with her calmly and she understands the materials are shared by everyone.",
];

$notesPool = [
    'Third occurrence this month.',
    'Teacher asked for administration follow-up.',
    'Student was cooperative once spoken to.',
    'Parent already aware of the situation.',
    'Behaviour improved after the conversation.',
];

$today = new DateTimeImmutable('today');

// --- clear only what a previous run of this script inserted
$removed = $pdo->prepare('DELETE FROM student_warnings WHERE notes LIKE ?');
$removed->execute(['%' . DEMO_MARKER . '%']);
$cleared = $removed->rowCount();

$insert = $pdo->prepare(
    'INSERT INTO student_warnings (
        student_id, teacher_id, warning_date, warning_year, warning_type, warning_number,
        conversation_minutes, reason, parent_message, parent_notified, notes, status,
        issued_by_user_id, issued_at, expiation_id, expiation_selected_by_user_id,
        expiation_selected_at, resolved_by_user_id, resolved_at
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

/** Rows for each status draw from the pool that makes them testable. */
$plan = [
    // status     pool      an expiation was chosen first?
    'flagged'  => ['pool' => $others, 'viaExpiation' => false],
    'issued'   => ['pool' => $linked, 'viaExpiation' => false],
    'assigned' => ['pool' => $linked, 'viaExpiation' => true],
];

$counts = [];
$tally = [];
$dayOffset = 0;

foreach ($plan as $status => $config) {
    $pool = $config['pool'];
    for ($index = 0; $index < PER_STATUS; $index++) {
        $person = $pool[$index % count($pool)];
        $studentId = (int) $person['student_id'];
        $age = (int) $person['age'];
        $dayOffset += 2;

        $warningDate = $today->modify('-' . (($dayOffset % 110) + 3) . ' days');
        $issuedAt = $warningDate->modify('+1 day')->setTime(9 + ($index % 6), 15);
        $chosenAt = $issuedAt->modify('+2 days');

        $type = $index % 2 === 0 ? 'oral' : 'written';
        $minutes = [15, 30, 45][$index % 3];
        $reason = $reasons[($index + $dayOffset) % count($reasons)];
        $notes = $notesPool[$index % count($notesPool)] . ' ' . DEMO_MARKER;
        $teacherId = $teacherIds[$index % count($teacherIds)];

        // A resolved warning may have gone through an expiation or straight from issued.
        $viaExpiation = $config['viaExpiation'];
        if ($viaExpiation === null) {
            $viaExpiation = $index % 2 === 0;
        }

        $parentId = $person['parent_user_id'] === null ? null : (int) $person['parent_user_id'];
        $expiationId = null;
        $expiationBy = null;
        $expiationOn = null;
        if ($viaExpiation && in_array($status, ['assigned', 'resolved'], true)) {
            $expiationId = expiationForAge($expiationsByAge, $age, $index);
            if ($expiationId !== null) {
                $expiationBy = $parentId;
                $expiationOn = $chosenAt->format('Y-m-d H:i:s');
            }
        }

        $isFlagOnly = $status === 'flagged';

        // Numbered the way the app numbers: per student, per type, counting only the
        // states that reach the parent. Flags and dismissals never take a number.
        $number = null;
        if (!$isFlagOnly) {
            $tally[$studentId][$type] = ($tally[$studentId][$type] ?? 0) + 1;
            $number = $tally[$studentId][$type];
        }

        $insert->execute([
            $studentId,
            $teacherId,
            $warningDate->format('Y-m-d'),
            (int) $warningDate->format('Y'),
            $isFlagOnly ? null : $type,                          // type only exists once issued
            $isFlagOnly ? null : $number,
            $isFlagOnly ? null : $minutes,
            $reason,
            $isFlagOnly ? null : $messages[$index % count($messages)],
            $isFlagOnly ? 0 : 1,                                 // issuing notifies the parent
            $notes,
            $status,
            $isFlagOnly ? null : $adminId,                       // who issued it
            $isFlagOnly ? null : $issuedAt->format('Y-m-d H:i:s'),
            $expiationId,
            $expiationBy,
            $expiationOn,
            null,
            null,
        ]);
        $counts[$status] = ($counts[$status] ?? 0) + 1;
    }
}

printf("removed %d row(s) from a previous run\n", $cleared);
foreach ($counts as $status => $count) {
    printf("  %-10s %d inserted\n", $status, $count);
}

echo "\nboard totals now:\n";
foreach ($pdo->query('SELECT status, COUNT(*) c FROM student_warnings GROUP BY status ORDER BY status') as $row) {
    printf("  %-10s %s\n", $row['status'], $row['c']);
}
