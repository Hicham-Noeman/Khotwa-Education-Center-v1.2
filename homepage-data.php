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
        "SELECT id, display_name, relationship_label, rating, review_text, created_at
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
