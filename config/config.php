<?php
ob_start();
define('APP_NAME', 'HRMS SaaS');
define('APP_VERSION', '2.0.0');
define('BASE_URL', '/hrms');
define('TIMEZONE', 'Asia/Manila');
date_default_timezone_set(TIMEZONE);

function resolve_session_path($path) {
    if ($path === '') {
        return '';
    }

    $parts = explode(';', $path);
    return trim((string) end($parts));
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = resolve_session_path((string) session_save_path());
    $sessionPathIsWritable = $sessionPath !== '' && is_dir($sessionPath) && is_writable($sessionPath);

    if (!$sessionPathIsWritable) {
        $fallbackSessionPath = __DIR__ . '/../.cache/sessions';
        if (!is_dir($fallbackSessionPath)) {
            @mkdir($fallbackSessionPath, 0775, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            session_save_path($fallbackSessionPath);
        }
    }

    session_start();
}

// Helper to get current company
function company() {
    return $_SESSION['company'] ?? null;
}

function company_id() {
    return $_SESSION['company']['id'] ?? null;
}
