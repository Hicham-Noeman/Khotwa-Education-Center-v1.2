<?php
declare(strict_types=1);

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

    return [
        'content' => $content,
        'slides' => $slides,
        'statistics' => $statistics,
        'team' => $team,
        'gallery' => $gallery,
        'partners' => $partners,
        'contacts' => $contacts,
    ];
}
