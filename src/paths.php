<?php
declare(strict_types=1);

/*
 * Project paths and URLs.
 *
 * Pages live at different folder depths (index.php at the root, admin/index.php
 * one level down), while image paths are stored in the database relative to the
 * project root. Building every internal link through khotwa_url() keeps those
 * links correct no matter which page renders them.
 */

define('KHOTWA_ROOT_PATH', dirname(__DIR__));

// PHP adds this after mod_headers has run, so it has to be dropped from PHP itself.
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

/**
 * Absolute filesystem path inside the project.
 */
function khotwa_path(string $relativePath = ''): string
{
    $relativePath = ltrim($relativePath, '/');

    return $relativePath === ''
        ? KHOTWA_ROOT_PATH
        : KHOTWA_ROOT_PATH . '/' . $relativePath;
}

/**
 * URL prefix of the project root, e.g. "/Khotwa%20Education%20Center%20v1.2/".
 *
 * Derived by removing the running script's path-inside-the-project from its URL,
 * so it works in any folder Apache serves the project from. Falls back to a
 * relative prefix on the command line, where there is no request URL.
 */
function khotwa_base_url(): string
{
    static $baseUrl = null;
    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $root = str_replace('\\', '/', KHOTWA_ROOT_PATH);
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptUrl = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    // Windows paths differ only in drive-letter case, so compare case-insensitively.
    if ($scriptFile !== '' && $scriptUrl !== '' && stripos($scriptFile, $root) === 0) {
        $insideProject = substr($scriptFile, strlen($root));
        if ($insideProject !== '' && str_ends_with($scriptUrl, $insideProject)) {
            $prefix = substr($scriptUrl, 0, strlen($scriptUrl) - strlen($insideProject));
            $segments = array_filter(explode('/', $prefix), static fn (string $part): bool => $part !== '');

            return $baseUrl = '/' . implode('/', array_map('rawurlencode', $segments)) . (count($segments) > 0 ? '/' : '');
        }
    }

    return $baseUrl = './';
}

/**
 * Internal link built from a project-root-relative path, e.g. khotwa_url('admin/index.php').
 */
function khotwa_url(string $relativePath = ''): string
{
    return khotwa_base_url() . ltrim($relativePath, '/');
}

/**
 * Asset link with a cache-busting stamp, e.g. khotwa_asset('css/admin.css').
 */
function khotwa_asset(string $assetPath): string
{
    $assetPath = 'assets/' . ltrim($assetPath, '/');
    $fullPath = khotwa_path($assetPath);
    $version = is_file($fullPath) ? (string) filemtime($fullPath) : '';

    return khotwa_url($assetPath) . ($version === '' ? '' : '?v=' . $version);
}

/**
 * Brand font tags for a page <head>.
 *
 * Emits the @font-face stylesheet plus a preload for the Regular file, which is the
 * weight every page needs first. The preload is skipped while the file is missing,
 * so a copy that has not had the licensed RB files dropped in yet renders on the
 * fallback stack instead of asking the browser for a font that 404s.
 */
function khotwa_head_fonts(): string
{
    $tags = '<link rel="stylesheet" href="'
        . htmlspecialchars(khotwa_asset('css/fonts.css'), ENT_QUOTES, 'UTF-8') . '">';

    $regular = 'fonts/rb-regular.woff2';
    if (is_file(khotwa_path('assets/' . $regular))) {
        $tags = '<link rel="preload" href="'
            . htmlspecialchars(khotwa_asset($regular), ENT_QUOTES, 'UTF-8')
            . '" as="font" type="font/woff2" crossorigin>' . "\n  " . $tags;
    }

    // PHP swallows the newline that follows a closing tag, so the markup carries
    // its own and the next stylesheet link still lands on a fresh line.
    return $tags . "
";
}
