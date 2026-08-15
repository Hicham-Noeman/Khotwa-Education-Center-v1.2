<?php
declare(strict_types=1);

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/homepage-data.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    echo json_encode(
        load_homepage_data(getDatabaseConnection()),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(
        ['error' => 'Homepage content is temporarily unavailable.'],
        JSON_UNESCAPED_SLASHES
    );
}
