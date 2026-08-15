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
