<?php
declare(strict_types=1);

require_once __DIR__ . '/homepage-texts.php';

/**
 * Homepage counters that are calculated from live data instead of the stored
 * homepage_statistics numbers. The stored number stays as the fallback whenever
 * a metric cannot be derived yet (no approved reviews, no founding date).
 */
function homepage_dynamic_metrics(PDO $pdo, array $settings): array
{
    $rating = $pdo->query(
        "SELECT COUNT(*) AS review_count, AVG(rating) AS rating_average
         FROM homepage_reviews
         WHERE status = 'approved'"
    )->fetch();

    $reviewCount = (int) ($rating['review_count'] ?? 0);
    $ratingAverage = $reviewCount > 0 ? round((float) $rating['rating_average'], 1) : null;

    $yearsOfExperience = null;
    $foundingDate = trim((string) ($settings['founding_date'] ?? ''));
    if ($foundingDate !== '') {
        try {
            $founded = new DateTimeImmutable($foundingDate);
            $yearsOfExperience = max(0, (int) $founded->diff(new DateTimeImmutable('today'))->y);
        } catch (Throwable) {
            $yearsOfExperience = null;
        }
    }

    return [
        'learners_supported' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
        'expert_educators' => (int) $pdo->query(
            "SELECT COUNT(*) FROM teachers WHERE status = 'active'"
        )->fetchColumn(),
        'rating_average' => $ratingAverage,
        'rating_count' => $reviewCount,
        // The satisfaction percentage is the same aggregate rating shown in the hero card.
        'family_satisfaction' => $ratingAverage === null ? null : (int) round(($ratingAverage / 5) * 100),
        'years_of_experience' => $yearsOfExperience,
        'founding_date' => $foundingDate,
    ];
}

/**
 * Replaces the stored value of every counter that is calculated from live data.
 */
function homepage_apply_dynamic_statistics(array $statistics, array $metrics): array
{
    $dynamicValues = [
        'learners_supported' => $metrics['learners_supported'],
        'expert_educators' => $metrics['expert_educators'],
        'family_satisfaction' => $metrics['family_satisfaction'],
        'years_experience' => $metrics['years_of_experience'],
    ];

    foreach ($statistics as $index => $row) {
        $key = (string) $row['stat_key'];
        if (($dynamicValues[$key] ?? null) === null) {
            continue;
        }
        $statistics[$index]['stat_value'] = (int) $dynamicValues[$key];
        $statistics[$index]['is_dynamic'] = true;
    }

    return $statistics;
}

function homepage_dynamic_statistic_keys(): array
{
    return ['learners_supported', 'expert_educators', 'family_satisfaction', 'years_experience'];
}

/**
 * Contact link types that belong in the footer's social row.
 */
function homepage_social_link_types(): array
{
    return ['whatsapp', 'instagram', 'facebook', 'google_map', 'tiktok', 'linkedin'];
}

/**
 * White brand mark for a social link, inlined from assets/icons so the footer can
 * colour it with currentColor the way the rest of the icons on the page are coloured.
 *
 * The files there are the official brand glyphs, normalised on the way in: a 512
 * viewBox added (the downloads had none, so they could not scale), the hardcoded
 * black fill dropped, and no width or height, so CSS alone decides how big they are.
 */
function homepage_social_icon(string $linkType): string
{
    static $cache = [];

    $files = [
        'whatsapp' => 'whatsapp.svg',
        'instagram' => 'instagram.svg',
        'facebook' => 'facebook.svg',
        'google_map' => 'google-map.svg',
        'tiktok' => 'tiktok.svg',
        'linkedin' => 'linkedin.svg',
    ];

    if (!isset($files[$linkType])) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="9"/></svg>';
    }

    if (!array_key_exists($linkType, $cache)) {
        $file = dirname(__DIR__) . '/assets/icons/' . $files[$linkType];
        $markup = is_file($file) ? (string) file_get_contents($file) : '';
        $cache[$linkType] = trim($markup);
    }

    // A missing icon file leaves a plain dot rather than an empty gap in the row.
    return $cache[$linkType] !== ''
        ? $cache[$linkType]
        : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="9"/></svg>';
}

/**
 * Whole years between a start date and today, so "years of experience" never goes
 * stale. A date nobody filled in, or one in the future, counts as nothing to show.
 */
function homepage_years_since(string $date): ?int
{
    $date = trim($date);
    if ($date === '' || $date === '0000-00-00') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($date);
    } catch (Exception $exception) {
        return null;
    }

    $years = (int) $start->diff(new DateTimeImmutable('today'))->format('%r%y');

    return $years < 0 ? null : $years;
}

/**
 * The three Lebanese school stages a teacher covers, as one readable line per
 * language. A teacher usually covers more than one, so they are joined in order.
 */
function homepage_teaching_levels(array $row): array
{
    $stages = [
        'teaches_primary' => ['Primary', 'الابتدائي'],
        'teaches_intermediate' => ['Intermediate', 'المتوسط'],
        'teaches_secondary' => ['Secondary', 'الثانوي'],
    ];

    $english = [];
    $arabic = [];
    foreach ($stages as $column => [$labelEn, $labelAr]) {
        if ((int) ($row[$column] ?? 0) === 1) {
            $english[] = $labelEn;
            $arabic[] = $labelAr;
        }
    }

    return [
        'en' => implode(', ', $english),
        'ar' => implode('، ', $arabic),
    ];
}

/**
 * Only a plain http(s) address reaches the page. Anything else - a javascript: URL
 * pasted into the admin panel, a half-typed link - is dropped rather than rendered.
 */
function homepage_watch_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return ($scheme === 'http' || $scheme === 'https') ? $url : '';
}

/**
 * The YouTube video id inside any of the shapes a link is usually copied in:
 * watch?v=, youtu.be/, /embed/, /shorts/, /live/. Anything else - another host,
 * a playlist-only link - yields an empty string and simply shows no player.
 */
function homepage_youtube_id(string $url): string
{
    $url = homepage_watch_url($url);
    if ($url === '') {
        return '';
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^(www\.|m\.)/', '', $host);
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

    if ($host === 'youtu.be') {
        $id = explode('/', $path)[0] ?? '';
    } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $id = (string) ($query['v'] ?? '');
        if ($id === '' && preg_match('#^(embed|shorts|live|v)/([^/]+)#', $path, $matches) === 1) {
            $id = $matches[2];
        }
    } else {
        return '';
    }

    return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1 ? $id : '';
}

/**
 * The privacy-friendly embed address for a stored video link, ready for the iframe
 * the page opens. An unrecognised link yields an empty string, so nothing renders.
 */
function homepage_youtube_embed(string $url): string
{
    $id = homepage_youtube_id($url);

    return $id === '' ? '' : 'https://www.youtube-nocookie.com/embed/' . $id;
}

/**
 * Whether a stored link is a YouTube Short, i.e. a clip filmed vertically. The
 * embed address loses that detail, so it is read off the original link and passed
 * to the page, which then opens the player tall instead of in 16:9.
 */
function homepage_youtube_is_portrait(string $url): bool
{
    $url = homepage_watch_url($url);
    if ($url === '') {
        return false;
    }

    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

    return preg_match('#^shorts/#', $path) === 1;
}

/**
 * The homepage team section, built from the real teacher records.
 *
 * Every active teacher appears automatically, so a teacher added in the admin panel is
 * on the website without a second step. Clearing "Show On Website" on a teacher takes
 * them off it again. Their subjects come from the subjects they are assigned to.
 *
 * The columns are named the way the old homepage_team_members table named them, so the
 * homepage markup and index.js render these rows unchanged.
 */
function homepage_team_from_teachers(PDO $pdo): array
{
    return homepage_team_rows($pdo);
}

/**
 * One teacher's website card, built exactly as the homepage builds it, whether or
 * not the teacher is currently published. The admin profile shows this so the
 * office can see what a visitor sees without opening the site.
 */
function homepage_team_member(PDO $pdo, int $teacherId): ?array
{
    return homepage_team_rows($pdo, $teacherId)[0] ?? null;
}

/**
 * @param int|null $teacherId One teacher regardless of visibility, or every
 *                            published teacher when null.
 */
function homepage_team_rows(PDO $pdo, ?int $teacherId = null): array
{
    $filter = $teacherId === null
        ? "teachers.status = 'active' AND teachers.show_on_website = 1"
        : 'teachers.id = ?';

    $statement = $pdo->prepare(
        "SELECT
            teachers.id,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS name_en,
            -- The Arabic spelling when the record carries one, otherwise the Latin
            -- name, so a teacher never disappears from the Arabic page for want of it.
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(teachers.first_name_ar, ''), ' ', COALESCE(teachers.last_name_ar, ''))), ''),
                TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, '')))
            ) AS name_ar,
            'Teacher' AS role_en,
            'معلّم' AS role_ar,
            COALESCE(GROUP_CONCAT(DISTINCT subjects.name_en ORDER BY subjects.name_en SEPARATOR ', '), '') AS subjects_en,
            COALESCE(GROUP_CONCAT(DISTINCT COALESCE(subjects.name_ar, subjects.name_en) ORDER BY subjects.name_en SEPARATOR '، '), '') AS subjects_ar,
            UPPER(CONCAT(LEFT(teachers.first_name, 1), COALESCE(LEFT(teachers.last_name, 1), ''))) AS initials_en,
            COALESCE(
                NULLIF(CONCAT(LEFT(COALESCE(teachers.first_name_ar, ''), 1), LEFT(COALESCE(teachers.last_name_ar, ''), 1)), ''),
                UPPER(CONCAT(LEFT(teachers.first_name, 1), COALESCE(LEFT(teachers.last_name, 1), '')))
            ) AS initials_ar,
            teachers.photo_path AS image_path,
            teachers.teaching_since,
            teachers.teaches_primary,
            teachers.teaches_intermediate,
            teachers.teaches_secondary,
            COALESCE(teachers.certifications_en, '') AS certifications_en,
            COALESCE(NULLIF(teachers.certifications_ar, ''), teachers.certifications_en, '') AS certifications_ar,
            teachers.joined_center_on,
            COALESCE(teachers.video_url, '') AS video_url,
            NULL AS contact_url,
            teachers.id AS sort_order
         FROM teachers
         LEFT JOIN teacher_subjects
                ON teacher_subjects.teacher_id = teachers.id
               AND teacher_subjects.status = 'active'
         LEFT JOIN subjects
                ON subjects.id = teacher_subjects.subject_id
               AND subjects.status = 'active'
         WHERE " . $filter . "
         GROUP BY teachers.id
         ORDER BY teachers.first_name, teachers.last_name"
    );
    $statement->execute($teacherId === null ? [] : [$teacherId]);

    $team = $statement->fetchAll();

    // A teacher with no subjects assigned yet still belongs on the page; the card just
    // carries a neutral line instead of an empty one.
    foreach ($team as $index => $row) {
        if (trim((string) $row['subjects_en']) === '') {
            $team[$index]['subjects_en'] = 'Khotwa Education Center';
            $team[$index]['subjects_ar'] = 'مركز خطوة التعليمي';
        }

        $team[$index]['initials'] = $row['initials_en'];

        // Both spans are stored as the date they started and counted into years here,
        // so they stay right every year without anyone editing a number. The panel
        // does the wording, because "years" is not one word in Arabic.
        $team[$index]['years_experience'] = homepage_years_since((string) ($row['teaching_since'] ?? ''));
        $team[$index]['years_at_center'] = homepage_years_since((string) ($row['joined_center_on'] ?? ''));
        $levels = homepage_teaching_levels($row);
        $team[$index]['education_levels_en'] = $levels['en'];
        $team[$index]['education_levels_ar'] = $levels['ar'];
        $team[$index]['video_url'] = homepage_watch_url((string) $row['video_url']);
    }

    return $team;
}

function load_homepage_data(PDO $pdo): array
{
    $content = $pdo->query(
        "SELECT
            content_key, content_type, sort_order,
            eyebrow_en, eyebrow_ar, category_en, category_ar,
            title_en, title_ar, description_en, description_ar,
            point_1_en, point_1_ar, point_2_en, point_2_ar,
            point_3_en, point_3_ar, video_url
         FROM homepage_content
         WHERE status = 'active'
         ORDER BY FIELD(content_type, 'vision', 'mission', 'step', 'program'),
                  sort_order, id"
    )->fetchAll();

    // Only a usable YouTube address reaches the page, already in embed form.
    foreach ($content as $index => $row) {
        $content[$index]['video_embed_url'] = homepage_youtube_embed((string) ($row['video_url'] ?? ''));
        $content[$index]['video_is_portrait'] = homepage_youtube_is_portrait((string) ($row['video_url'] ?? ''));
    }

    $slides = $pdo->query(
        "SELECT id, image_path, alt_en, alt_ar, title_en, title_ar,
                description_en, description_ar, sort_order
         FROM homepage_slides
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

    $statistics = $pdo->query(
        "SELECT stat_key, stat_value, suffix, label_en, label_ar, sort_order
         FROM homepage_statistics
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

    $team = homepage_team_from_teachers($pdo);

    $gallery = $pdo->query(
        "SELECT id, image_path, alt_en, alt_ar, caption_en, caption_ar,
                layout_style, sort_order
         FROM homepage_gallery_images
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

    $partners = $pdo->query(
        "SELECT id, name_en, name_ar, logo_path, website_url, sort_order
         FROM homepage_partners
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

    $contacts = $pdo->query(
        "SELECT link_key, link_type, label_en, label_ar,
                value_en, value_ar, url, sort_order
         FROM homepage_contact_links
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

    $reviews = $pdo->query(
        "SELECT id, display_name, display_name_ar, relationship_label, rating, review_text, created_at
         FROM homepage_reviews
         WHERE status = 'approved'
         ORDER BY sort_order, id DESC
         LIMIT 12"
    )->fetchAll();

    $settings = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM homepage_settings')->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
    }

    $texts = [];
    foreach (
        $pdo->query('SELECT text_key, value_en, value_ar FROM homepage_texts')->fetchAll() as $row
    ) {
        $texts[(string) $row['text_key']] = [
            'en' => (string) $row['value_en'],
            'ar' => (string) $row['value_ar'],
        ];
    }

    $metrics = homepage_dynamic_metrics($pdo, $settings);
    $statistics = homepage_apply_dynamic_statistics($statistics, $metrics);

    return [
        'content' => $content,
        'slides' => $slides,
        'statistics' => $statistics,
        'team' => $team,
        'gallery' => $gallery,
        'partners' => $partners,
        'contacts' => $contacts,
        'reviews' => $reviews,
        'settings' => $settings,
        'texts' => $texts,
        'metrics' => $metrics,
    ];
}

/**
 * One editable sentence, in the language asked for.
 *
 * Falls back to the seeded wording whenever the row is missing or its field is
 * empty, so an unfinished translation or an unreachable database never leaves a
 * hole in the page. $texts is the map load_homepage_data() returns.
 */
function homepage_text(array $texts, string $key, string $language = 'en'): string
{
    $value = trim((string) ($texts[$key][$language] ?? ''));
    if ($value !== '') {
        return $value;
    }

    // An Arabic field left blank still has English to show rather than nothing.
    $english = trim((string) ($texts[$key]['en'] ?? ''));

    return $english !== '' ? $english : khotwa_homepage_text_default($key, $language);
}
