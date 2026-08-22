<?php
declare(strict_types=1);

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
 * White brand mark for a social link, drawn in the same stroke style as the rest
 * of the site so the footer row stays visually consistent.
 */
function homepage_social_icon(string $linkType): string
{
    $icons = [
        'whatsapp' =>
            '<path d="M3.5 20.5l1.2-4.1a8 8 0 1 1 3 3l-4.2 1.1Z"/>'
            . '<path d="M9.2 8.6h.9l1 2.2-.9 1a6.3 6.3 0 0 0 2.9 2.6l1-.9 2.2 1v.9c0 .8-.7 1.3-1.6 1.3-3 0-6.8-3.8-6.8-6.8 0-.9.5-1.6 1.3-1.6Z"/>',
        'instagram' =>
            '<rect x="3" y="3" width="18" height="18" rx="5"/>'
            . '<circle cx="12" cy="12" r="4"/>'
            . '<path d="M17.5 6.5h.01"/>',
        'facebook' =>
            '<path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V2.8h-2.4A4.1 4.1 0 0 0 10.5 7v1.5H8V12h2.5v9.2H14V12h2.6l.5-3.5H14Z"/>',
        'google_map' =>
            '<path d="M12 21.5s6.5-6.1 6.5-11a6.5 6.5 0 1 0-13 0c0 4.9 6.5 11 6.5 11Z"/>'
            . '<circle cx="12" cy="10.2" r="2.6"/>',
        'tiktok' =>
            '<path d="M14.4 2.8v11.6a3.6 3.6 0 1 1-3.6-3.6c.3 0 .6 0 .9.1"/>'
            . '<path d="M14.4 2.8a5 5 0 0 0 5 5"/>',
        'linkedin' =>
            '<path d="M15.5 9.2a5 5 0 0 1 5 5v6.6h-3.4v-6.6a1.6 1.6 0 0 0-3.2 0v6.6h-3.4V9.6h3.4v1a5 5 0 0 1 1.6-1.4Z"/>'
            . '<rect x="3.2" y="9.6" width="3.4" height="11.2" rx="0.4"/>'
            . '<circle cx="4.9" cy="5.2" r="1.9"/>',
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . ($icons[$linkType] ?? '<circle cx="12" cy="12" r="9"/>')
        . '</svg>';
}

function load_homepage_data(PDO $pdo): array
{
    $content = $pdo->query(
        "SELECT
            content_key, content_type, sort_order,
            eyebrow_en, eyebrow_ar, category_en, category_ar,
            title_en, title_ar, description_en, description_ar,
            point_1_en, point_1_ar, point_2_en, point_2_ar,
            point_3_en, point_3_ar
         FROM homepage_content
         WHERE status = 'active'
         ORDER BY FIELD(content_type, 'vision', 'mission', 'step', 'program'),
                  sort_order, id"
    )->fetchAll();

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

    $team = $pdo->query(
        "SELECT id, name_en, name_ar, role_en, role_ar, subjects_en, subjects_ar,
                initials, image_path, contact_url, sort_order
         FROM homepage_team_members
         WHERE status = 'active'
         ORDER BY sort_order, id"
    )->fetchAll();

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
        'metrics' => $metrics,
    ];
}
