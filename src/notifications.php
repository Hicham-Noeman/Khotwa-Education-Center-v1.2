<?php
declare(strict_types=1);

/*
 * The notification center.
 *
 * Something happens to a child - they arrive, they leave, a warning is issued,
 * a month is paid - and the people who need to know are told. A notification is
 * one row per person told, written in both languages at the moment it is raised,
 * because the child's name and the month are known then and reading it back
 * later should not depend on deriving them again.
 *
 * Raising one must never be the reason a save fails: every entry point here
 * swallows its own errors. A family would rather have the payment recorded and
 * no notification than neither.
 */

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/push.php';

/**
 * Write one notification per recipient.
 *
 * @param array<int, int> $userIds
 * @param array{event: string, title_en: string, title_ar: string, body_en: string, body_ar: string, link?: string, student_id?: int} $payload
 */
function notify_users(PDO $pdo, array $userIds, array $payload): int
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if ($userIds === []) {
        return 0;
    }

    try {
        $statement = $pdo->prepare(
            'INSERT INTO notifications
                (user_id, event_type, title_en, title_ar, body_en, body_ar, link, student_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $written = 0;
        foreach ($userIds as $userId) {
            $statement->execute([
                $userId,
                mb_substr((string) $payload['event'], 0, 40),
                mb_substr((string) $payload['title_en'], 0, 160),
                mb_substr((string) $payload['title_ar'], 0, 160),
                mb_substr((string) $payload['body_en'], 0, 400),
                mb_substr((string) $payload['body_ar'], 0, 400),
                isset($payload['link']) ? mb_substr((string) $payload['link'], 0, 255) : null,
                isset($payload['student_id']) ? (int) $payload['student_id'] : null,
            ]);
            $written++;
        }

        /*
         * The rows are safe; now the phones. Kept after the insert and inside
         * nothing that can undo it, because a push that fails must never cost
         * the notification - the bell is the part that always works, and a
         * phone that was off simply reads it there instead.
         */
        push_broadcast($pdo, $userIds, $payload);

        return $written;
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * The parent accounts linked to one child.
 *
 * @return array<int, int>
 */
function notification_parent_user_ids(PDO $pdo, int $studentId): array
{
    try {
        $statement = $pdo->prepare(
            "SELECT parent_students.parent_user_id
             FROM parent_students
             INNER JOIN users ON users.id = parent_students.parent_user_id
             WHERE parent_students.student_id = ? AND users.status = 'active'"
        );
        $statement->execute([$studentId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * Everyone who runs the center.
 *
 * @return array<int, int>
 */
function notification_staff_user_ids(PDO $pdo): array
{
    try {
        return array_map('intval', $pdo->query(
            "SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'"
        )->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * A child's name in both languages, for the sentence that names them.
 *
 * @return array{en: string, ar: string}
 */
function notification_student_name(PDO $pdo, int $studentId): array
{
    try {
        $statement = $pdo->prepare(
            "SELECT CONCAT(first_name_en, ' ', last_name_en) AS name_en,
                    TRIM(CONCAT(COALESCE(first_name_ar, ''), ' ', COALESCE(last_name_ar, ''))) AS name_ar
             FROM students WHERE id = ? LIMIT 1"
        );
        $statement->execute([$studentId]);
        $row = $statement->fetch();
        if (!$row) {
            return ['en' => 'The student', 'ar' => 'الطالب'];
        }

        $nameEn = trim((string) $row['name_en']);
        $nameAr = trim((string) $row['name_ar']);

        return [
            'en' => $nameEn !== '' ? $nameEn : 'The student',
            // A child with no Arabic name on file is named the way they are written.
            'ar' => $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : 'الطالب'),
        ];
    } catch (Throwable $exception) {
        return ['en' => 'The student', 'ar' => 'الطالب'];
    }
}

/*
 * The events themselves. Each one decides who hears about it: a family hears
 * about its own child, the administration hears what it has to act on.
 */

function notify_student_arrived(PDO $pdo, int $studentId, string $time): void
{
    $name = notification_student_name($pdo, $studentId);
    notify_users($pdo, notification_parent_user_ids($pdo, $studentId), [
        'event' => 'attendance_in',
        'student_id' => $studentId,
        'title_en' => 'Arrived at the center',
        'title_ar' => 'وصل إلى المركز',
        'body_en' => $name['en'] . ' was checked in at ' . $time . '.',
        'body_ar' => 'تم تسجيل دخول ' . $name['ar'] . ' الساعة ' . $time . '.',
        'link' => 'parent/index.php?student_id=' . $studentId . '#attendance-table',
    ]);
}

function notify_student_left(PDO $pdo, int $studentId, string $time): void
{
    $name = notification_student_name($pdo, $studentId);
    notify_users($pdo, notification_parent_user_ids($pdo, $studentId), [
        'event' => 'attendance_out',
        'student_id' => $studentId,
        'title_en' => 'Left the center',
        'title_ar' => 'غادر المركز',
        'body_en' => $name['en'] . ' was checked out at ' . $time . '.',
        'body_ar' => 'تم تسجيل خروج ' . $name['ar'] . ' الساعة ' . $time . '.',
        'link' => 'parent/index.php?student_id=' . $studentId . '#attendance-table',
    ]);
}

/**
 * An oral or written warning reaching the family.
 */
function notify_warning_issued(PDO $pdo, int $studentId, string $warningType): void
{
    $name = notification_student_name($pdo, $studentId);
    $isWritten = $warningType === 'written';

    notify_users($pdo, notification_parent_user_ids($pdo, $studentId), [
        'event' => 'warning_issued',
        'student_id' => $studentId,
        'title_en' => $isWritten ? 'Written warning issued' : 'Oral warning issued',
        'title_ar' => $isWritten ? 'صدر إنذار خطي' : 'صدر إنذار شفهي',
        // Only a written warning asks the family to do something next.
        'body_en' => $isWritten
            ? 'A written warning was issued for ' . $name['en'] . '. Please choose an expiation.'
            : 'An oral warning was issued for ' . $name['en'] . '.',
        'body_ar' => $isWritten
            ? 'صدر إنذار خطي بحق ' . $name['ar'] . '. الرجاء اختيار كفّارة.'
            : 'صدر إنذار شفهي بحق ' . $name['ar'] . '.',
        'link' => 'parent/index.php?student_id=' . $studentId . '#behaviour-panel',
    ]);
}

/**
 * A teacher raising a flag, which only the administration acts on.
 */
function notify_warning_flagged(PDO $pdo, int $studentId, string $teacherName): void
{
    $name = notification_student_name($pdo, $studentId);
    notify_users($pdo, notification_staff_user_ids($pdo), [
        'event' => 'warning_flagged',
        'student_id' => $studentId,
        'title_en' => 'Behaviour flag raised',
        'title_ar' => 'تم رفع ملاحظة سلوك',
        'body_en' => $teacherName . ' raised a flag for ' . $name['en'] . '.',
        'body_ar' => 'رفع ' . $teacherName . ' ملاحظة بحق ' . $name['ar'] . '.',
        'link' => 'admin/index.php?view=warnings',
    ]);
}

function notify_expiation_chosen(PDO $pdo, int $studentId, string $expiationTitle): void
{
    $name = notification_student_name($pdo, $studentId);
    notify_users($pdo, notification_staff_user_ids($pdo), [
        'event' => 'expiation_chosen',
        'student_id' => $studentId,
        'title_en' => 'Expiation chosen',
        'title_ar' => 'تم اختيار كفّارة',
        'body_en' => 'The family of ' . $name['en'] . ' chose: ' . $expiationTitle,
        'body_ar' => 'اختارت عائلة ' . $name['ar'] . ': ' . $expiationTitle,
        'link' => 'admin/index.php?view=warnings&stage=assigned',
    ]);
}

/**
 * A payment recorded against one billing month.
 *
 * The family is told what was received and what, if anything, is still owed;
 * the administration is told the same thing, so the desk and the portal agree.
 */
function notify_payment_recorded(PDO $pdo, int $paymentId): void
{
    try {
        $statement = $pdo->prepare(
            'SELECT student_subscription_payments.student_id,
                    student_subscription_payments.paid_amount,
                    student_subscription_months.billing_year,
                    student_subscription_months.billing_month,
                    GREATEST(student_subscription_months.expected_amount
                             - student_subscription_months.paid_amount, 0) AS balance_amount
             FROM student_subscription_payments
             LEFT JOIN student_subscription_months
                    ON student_subscription_months.id = student_subscription_payments.subscription_month_id
             WHERE student_subscription_payments.id = ?
             LIMIT 1'
        );
        $statement->execute([$paymentId]);
        $row = $statement->fetch();
        if (!$row) {
            return;
        }

        $studentId = (int) $row['student_id'];
        $name = notification_student_name($pdo, $studentId);
        $amount = number_format((float) $row['paid_amount'], 2);
        $balance = (float) ($row['balance_amount'] ?? 0);

        // A payment that was not tied to a month still says what it was for.
        $month = 'the subscription';
        $monthAr = 'الاشتراك';
        if ($row['billing_year'] !== null) {
            $monthTime = mktime(0, 0, 0, (int) $row['billing_month'], 1, (int) $row['billing_year']);
            $month = date('F Y', $monthTime !== false ? $monthTime : time());
            $monthAr = $month;
        }

        $settled = $balance <= 0;

        notify_users($pdo, notification_parent_user_ids($pdo, $studentId), [
            'event' => 'payment_recorded',
            'student_id' => $studentId,
            'title_en' => 'Payment received',
            'title_ar' => 'تم استلام دفعة',
            'body_en' => $amount . ' was received for ' . $month . '.'
                . ($settled ? ' That month is now settled.' : ' ' . number_format($balance, 2) . ' is still owed.'),
            'body_ar' => 'تم استلام ' . $amount . ' عن ' . $monthAr . '.'
                . ($settled ? ' تمت تسوية هذا الشهر.' : ' ما زال مستحقاً ' . number_format($balance, 2) . '.'),
            'link' => 'parent/index.php?student_id=' . $studentId . '#billing-table',
        ]);

        notify_users($pdo, notification_staff_user_ids($pdo), [
            'event' => 'payment_recorded',
            'student_id' => $studentId,
            'title_en' => 'Payment recorded',
            'title_ar' => 'تم تسجيل دفعة',
            'body_en' => $amount . ' received from ' . $name['en'] . ' for ' . $month . '.',
            'body_ar' => 'تم استلام ' . $amount . ' من ' . $name['ar'] . ' عن ' . $monthAr . '.',
            'link' => 'admin/index.php?view=payments',
        ]);
    } catch (Throwable $exception) {
        // Never the reason a payment fails to save.
    }
}

/*
 * The reading side: what the bell shows, and what clearing it does.
 */

function notifications_unread_count(PDO $pdo, int $userId): int
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL'
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function notifications_recent(PDO $pdo, int $userId, int $limit = 20): array
{
    try {
        // The limit is clamped rather than bound: LIMIT takes no placeholder.
        $statement = $pdo->prepare(
            'SELECT id, event_type, title_en, title_ar, body_en, body_ar, link, read_at, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * One notification, read because it was opened.
 *
 * The user id is part of the match rather than checked beforehand: a row that
 * belongs to somebody else simply does not update.
 */
function notifications_mark_read(PDO $pdo, int $userId, int $notificationId): void
{
    try {
        $statement = $pdo->prepare(
            'UPDATE notifications SET read_at = NOW()
             WHERE id = ? AND user_id = ? AND read_at IS NULL'
        );
        $statement->execute([$notificationId, $userId]);
    } catch (Throwable $exception) {
        // The badge simply stays as it was.
    }
}

/**
 * Where one notification leads, if it belongs to this person.
 */
function notification_link_for(PDO $pdo, int $userId, int $notificationId): string
{
    try {
        $statement = $pdo->prepare(
            'SELECT link FROM notifications WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $statement->execute([$notificationId, $userId]);
        $link = (string) ($statement->fetchColumn() ?: '');
    } catch (Throwable $exception) {
        return '';
    }

    /*
     * These are written by the center's own code, never by a request, but the
     * value still leaves through a Location header - so anything that could
     * point off this site is refused rather than trusted.
     */
    if ($link === '' || str_contains($link, ':') || str_starts_with($link, '//') || str_starts_with($link, '/')) {
        return '';
    }

    return $link;
}

function notifications_mark_all_read(PDO $pdo, int $userId): void
{
    try {
        $statement = $pdo->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL'
        );
        $statement->execute([$userId]);
    } catch (Throwable $exception) {
        // Nothing to do: the badge simply stays as it was.
    }
}

/**
 * The public half of the key pair Chrome is asked to subscribe against.
 *
 * Empty until a pair is generated, and empty is what keeps the page from asking
 * for permission at all - there is nothing to subscribe to yet. Set it by
 * defining KHOTWA_VAPID_PUBLIC_KEY in src/db-config.php, the file that already
 * holds this machine's own settings and is never committed.
 */
function push_vapid_public_key(): string
{
    return defined('KHOTWA_VAPID_PUBLIC_KEY') ? (string) KHOTWA_VAPID_PUBLIC_KEY : '';
}

/**
 * A browser offering to be notified while the site is closed.
 *
 * Nothing sends to these yet - Chrome only hands them over on https - but they
 * are kept from the first day, so the sending side is all that is left to write.
 * The endpoint is the identity: a browser re-subscribing replaces its own row.
 */
function push_subscription_save(
    PDO $pdo,
    int $userId,
    array $subscription,
    string $userAgent = '',
    string $language = 'en'
): bool {
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $publicKey = trim((string) ($subscription['keys']['p256dh'] ?? ''));
    $authToken = trim((string) ($subscription['keys']['auth'] ?? ''));

    if ($endpoint === '' || $publicKey === '' || $authToken === '') {
        return false;
    }
    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
        return false;
    }

    $language = $language === 'ar' ? 'ar' : 'en';

    try {
        $statement = $pdo->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, public_key, auth_token, user_agent, language)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                public_key = VALUES(public_key),
                auth_token = VALUES(auth_token),
                user_agent = VALUES(user_agent),
                language = VALUES(language),
                last_seen_at = NOW()'
        );
        $statement->execute([
            $userId,
            mb_substr($endpoint, 0, 500),
            mb_substr($publicKey, 0, 255),
            mb_substr($authToken, 0, 255),
            mb_substr($userAgent, 0, 255) ?: null,
            $language,
        ]);

        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * Every browser one person has agreed to be notified on.
 *
 * @return array<int, array{endpoint: string, public_key: string, auth_token: string, language: string}>
 */
function push_subscriptions_for_user(PDO $pdo, int $userId): array
{
    try {
        $statement = $pdo->prepare(
            'SELECT endpoint, public_key, auth_token, language
             FROM push_subscriptions
             WHERE user_id = ?'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * Forget a browser that will never answer again.
 *
 * Only ever called on a 404 or 410 from the push service, which is that
 * service saying the subscription is finished - permission withdrawn, the
 * browser data cleared, the app removed. Keeping the row would mean sending to
 * it forever.
 */
function push_subscription_forget(PDO $pdo, string $endpoint): void
{
    try {
        $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$endpoint]);
    } catch (Throwable $exception) {
        // It will simply be tried again and fail again, which costs nothing.
    }
}

/**
 * The whole address of a page, which is what a notification tap needs.
 *
 * Links are stored as site paths, because that is all a page ever needs. A
 * service worker opening one has no page to resolve it against, so the scheme
 * and host are put back on here.
 */
function push_absolute_url(string $path): string
{
    if ($path === '' || str_contains($path, ':')) {
        return '';
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    $secure = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

    return ($secure ? 'https://' : 'http://') . $host . khotwa_url($path);
}

/**
 * Send one notification to every phone its recipients have signed up.
 *
 * Written in the language each browser was being read in when it subscribed,
 * which is why the subscription carries one: the notification itself holds
 * both, and there is no page open to choose between them at the moment it
 * arrives.
 *
 * @param array<int, int> $userIds
 * @param array{event: string, title_en: string, title_ar: string, body_en: string, body_ar: string, link?: string} $payload
 */
function push_broadcast(PDO $pdo, array $userIds, array $payload): void
{
    if (!push_is_configured()) {
        return;
    }

    $link = push_absolute_url((string) ($payload['link'] ?? ''));
    $icon = push_absolute_url('assets/images/logo-color.svg');

    foreach ($userIds as $userId) {
        foreach (push_subscriptions_for_user($pdo, (int) $userId) as $subscription) {
            $arabic = ($subscription['language'] ?? 'en') === 'ar';

            try {
                $result = push_deliver(
                    $subscription['endpoint'],
                    $subscription['public_key'],
                    $subscription['auth_token'],
                    [
                        'title' => $arabic ? $payload['title_ar'] : $payload['title_en'],
                        'body' => $arabic ? $payload['body_ar'] : $payload['body_en'],
                        'link' => $link,
                        'icon' => $icon,
                        'badge' => $icon,
                        /*
                         * One tag per event per child, so a second warning
                         * replaces the first on the lock screen rather than
                         * stacking - the phone shows what is true now.
                         */
                        'tag' => (string) $payload['event'] . ':' . (int) ($payload['student_id'] ?? 0),
                    ]
                );

                if ($result['gone']) {
                    push_subscription_forget($pdo, $subscription['endpoint']);
                }
            } catch (Throwable $exception) {
                // The next browser still gets its turn.
            }
        }
    }
}
