<?php
declare(strict_types=1);

/**
 * The parent agreement: drafting, publication and acceptance.
 *
 * The rules it enforces, in one place so every screen agrees on them:
 *
 *  - The administration writes a version and publishes it. Drafts can be
 *    rewritten or deleted freely; publishing is the moment it becomes real.
 *  - Publishing a version archives the one before it.
 *  - A family agrees once per child, against a version. Publishing a new version
 *    therefore asks every family again, without anything having to be reset.
 *  - A parent who owes an acceptance can read the agreement and nothing else.
 */

require_once __DIR__ . '/auth.php';

/**
 * The version parents are currently held to, or null before the first publish.
 *
 * @return array<string, mixed>|null
 */
function parent_agreement_published(PDO $pdo): ?array
{
    $statement = $pdo->query(
        "SELECT * FROM parent_agreements
         WHERE status = 'published'
         ORDER BY version DESC, id DESC
         LIMIT 1"
    );

    return $statement->fetch() ?: null;
}

/**
 * The clauses of one version, in the order they are meant to be read.
 *
 * @return array<int, array<string, mixed>>
 */
function parent_agreement_clauses(PDO $pdo, int $agreementId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM parent_agreement_clauses
         WHERE agreement_id = ?
         ORDER BY sort_order, id'
    );
    $statement->execute([$agreementId]);

    return $statement->fetchAll();
}

/**
 * The children of one parent that have not yet been agreed for.
 *
 * Returns an empty list when there is nothing to sign - no published version, or
 * every child already covered - which is what lets the portal open normally.
 *
 * @return array<int, array<string, mixed>>
 */
function parent_agreement_pending_students(PDO $pdo, int $parentUserId, int $agreementId): array
{
    if ($agreementId < 1) {
        return [];
    }

    $statement = $pdo->prepare(
        "SELECT students.id,
                CONCAT(students.first_name_en, ' ', students.last_name_en) AS name_en,
                CONCAT(students.first_name_ar, ' ', students.last_name_ar) AS name_ar
         FROM parent_students
         INNER JOIN students ON students.id = parent_students.student_id
         LEFT JOIN parent_agreement_acceptances AS accepted
                ON accepted.student_id = students.id
               AND accepted.parent_user_id = parent_students.parent_user_id
               AND accepted.agreement_id = ?
         WHERE parent_students.parent_user_id = ?
           AND parent_students.status = 'active'
           AND accepted.id IS NULL
         ORDER BY students.first_name_en"
    );
    $statement->execute([$agreementId, $parentUserId]);

    return $statement->fetchAll();
}

/**
 * Records one family's acceptance, for every child that still owed one.
 *
 * Written one child at a time rather than as a single insert, so a child added
 * between the page loading and the button being pressed is simply not covered -
 * and gets asked for on the next visit - instead of the whole acceptance failing.
 */
function parent_agreement_accept(PDO $pdo, int $parentUserId, int $agreementId, ?string $ip = null): int
{
    $pending = parent_agreement_pending_students($pdo, $parentUserId, $agreementId);
    if ($pending === []) {
        return 0;
    }

    $insert = $pdo->prepare(
        'INSERT INTO parent_agreement_acceptances
            (agreement_id, parent_user_id, student_id, accepted_at, accepted_ip)
         VALUES (?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE accepted_at = accepted_at'
    );

    $accepted = 0;
    foreach ($pending as $student) {
        $insert->execute([
            $agreementId,
            $parentUserId,
            (int) $student['id'],
            $ip === null ? null : mb_substr($ip, 0, 45),
        ]);
        $accepted++;
    }

    return $accepted;
}

/**
 * The version a new draft should start from.
 *
 * Writing the next agreement means changing the last one, not starting from a
 * blank page - so a new draft opens as a copy of what is live, or of the most
 * recently worked-on version when nothing is published yet.
 *
 * @return array<string, mixed>|null
 */
function parent_agreement_latest_source(PDO $pdo): ?array
{
    $published = parent_agreement_published($pdo);
    if ($published !== null) {
        return $published;
    }

    $statement = $pdo->query(
        'SELECT * FROM parent_agreements ORDER BY updated_at DESC, id DESC LIMIT 1'
    );

    return $statement->fetch() ?: null;
}

/**
 * Copies one version's clauses onto another, keeping their order.
 *
 * The ids are new, so editing or deleting a clause on the new draft cannot reach
 * back into the version it came from - which still has to read exactly as the
 * families who accepted it saw it.
 */
function parent_agreement_copy_clauses(PDO $pdo, int $fromAgreementId, int $toAgreementId): int
{
    $clauses = parent_agreement_clauses($pdo, $fromAgreementId);
    if ($clauses === []) {
        return 0;
    }

    $insert = $pdo->prepare(
        'INSERT INTO parent_agreement_clauses
            (agreement_id, title_en, title_ar, body_en, body_ar, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $order = 0;
    foreach ($clauses as $clause) {
        $order++;
        $insert->execute([
            $toAgreementId,
            (string) $clause['title_en'],
            (string) $clause['title_ar'],
            (string) $clause['body_en'],
            (string) $clause['body_ar'],
            $order,
        ]);
    }

    return count($clauses);
}

/**
 * Publishes a draft to the families.
 *
 * Everything hangs off this moment: the previous version is archived, a version
 * number is stamped, and because acceptance is recorded against a version, every
 * family is asked again from here on - including families who accepted the one
 * before. Done in a transaction, since two published versions at once would mean
 * two different answers to "what does this family have to accept".
 *
 * An empty agreement is refused: it would reach families as a blank dialog.
 */
function parent_agreement_publish(PDO $pdo, int $agreementId): void
{
    $statement = $pdo->prepare('SELECT status FROM parent_agreements WHERE id = ? LIMIT 1');
    $statement->execute([$agreementId]);
    $status = (string) $statement->fetchColumn();

    if ($status === '') {
        throw new RuntimeException('That agreement could not be found.');
    }
    if ($status !== 'draft') {
        throw new RuntimeException('Only a draft can be published.');
    }
    if (parent_agreement_clauses($pdo, $agreementId) === []) {
        throw new RuntimeException('Add at least one clause before publishing this agreement.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE parent_agreements SET status = 'archived' WHERE status = 'published'")->execute();

        $nextVersion = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) + 1 FROM parent_agreements')->fetchColumn();
        $pdo->prepare(
            "UPDATE parent_agreements
             SET status = 'published', version = ?, published_at = NOW()
             WHERE id = ?"
        )->execute([$nextVersion, $agreementId]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Deletes a draft, or an old version nobody is held to any more.
 *
 * The published version is the exception and always stays: it is what families
 * are being asked to accept right now, and deleting it would leave the portal
 * gating against nothing.
 *
 * Deleting an archived version also deletes the record of who accepted it - the
 * acceptances cascade - so the count is worth showing before the click, not
 * after. parent_agreement_acceptance_count() is there for exactly that.
 */
function parent_agreement_delete(PDO $pdo, int $agreementId): void
{
    $statement = $pdo->prepare('SELECT status FROM parent_agreements WHERE id = ? LIMIT 1');
    $statement->execute([$agreementId]);
    $status = (string) $statement->fetchColumn();

    if ($status === '') {
        throw new RuntimeException('That agreement could not be found.');
    }
    if ($status === 'published') {
        throw new RuntimeException(
            'The published version cannot be deleted. Publish another version first, then delete this one.'
        );
    }

    // The clauses and the acceptances go with it through the foreign keys.
    $pdo->prepare('DELETE FROM parent_agreements WHERE id = ?')->execute([$agreementId]);
}

/** How many acceptances are recorded against one version. */
function parent_agreement_acceptance_count(PDO $pdo, int $agreementId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM parent_agreement_acceptances WHERE agreement_id = ?'
    );
    $statement->execute([$agreementId]);

    return (int) $statement->fetchColumn();
}

/**
 * Whether this family has accepted an earlier version before.
 *
 * What separates "here is the agreement" from "the agreement has changed" - the
 * second wants saying plainly, so a parent knows to read it again rather than
 * clicking past something they think they have already seen.
 */
function parent_agreement_is_update_for(PDO $pdo, int $parentUserId, int $agreementId): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM parent_agreement_acceptances
         WHERE parent_user_id = ? AND agreement_id <> ?'
    );
    $statement->execute([$parentUserId, $agreementId]);

    return (int) $statement->fetchColumn() > 0;
}

/** How many child records still owe an acceptance of the published version. */
function parent_agreement_outstanding_count(PDO $pdo, int $agreementId): int
{
    if ($agreementId < 1) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM parent_students
         LEFT JOIN parent_agreement_acceptances AS accepted
                ON accepted.student_id = parent_students.student_id
               AND accepted.parent_user_id = parent_students.parent_user_id
               AND accepted.agreement_id = ?
         WHERE parent_students.status = 'active' AND accepted.id IS NULL"
    );
    $statement->execute([$agreementId]);

    return (int) $statement->fetchColumn();
}

/** The label a status carries on screen. */
function parent_agreement_status_label(string $status): string
{
    return [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ][$status] ?? ucfirst($status);
}
