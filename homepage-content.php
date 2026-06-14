<?php
declare(strict_types=1);

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    $pdo = getDatabaseConnection();
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
        "SELECT
            teachers.id,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS name_en,
            TRIM(CONCAT(teachers.first_name, ' ', COALESCE(teachers.last_name, ''))) AS name_ar,
            'Teacher' AS role_en,
            'معلّم' AS role_ar,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT subjects.name_en
                    ORDER BY subjects.name_en SEPARATOR ' · '
                ),
                'General learning'
            ) AS subjects_en,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT COALESCE(NULLIF(subjects.name_ar, ''), subjects.name_en)
                    ORDER BY COALESCE(NULLIF(subjects.name_ar, ''), subjects.name_en) SEPARATOR ' · '
                ),
                'التعلّم العام'
            ) AS subjects_ar,
            UPPER(
                CONCAT(
                    LEFT(teachers.first_name, 1),
                    LEFT(COALESCE(teachers.last_name, ''), 1)
                )
            ) AS initials,
            NULL AS image_path,
            CONCAT('mailto:', teachers.email) AS contact_url,
            teachers.id AS sort_order
         FROM teachers
         LEFT JOIN teacher_subjects
            ON teacher_subjects.teacher_id = teachers.id
           AND teacher_subjects.status = 'active'
         LEFT JOIN subjects
            ON subjects.id = teacher_subjects.subject_id
           AND subjects.status = 'active'
         WHERE teachers.status = 'active'
         GROUP BY
            teachers.id,
            teachers.first_name,
            teachers.last_name,
            teachers.email
         ORDER BY teachers.first_name, teachers.last_name, teachers.id"
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

    echo json_encode(
        [
            'content' => $content,
            'slides' => $slides,
            'statistics' => $statistics,
            'team' => $team,
            'gallery' => $gallery,
            'partners' => $partners,
            'contacts' => $contacts,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(
        ['error' => 'Homepage content is temporarily unavailable.'],
        JSON_UNESCAPED_SLASHES
    );
}
