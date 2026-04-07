<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePrefix = '/hrms';
$relativePath = $requestPath;

// If the path starts with /hrms, remove it for internal routing
if (str_starts_with($relativePath, $basePrefix)) {
    $relativePath = substr($relativePath, strlen($basePrefix));
}

// Ensure relative path is not empty
if ($relativePath === false || $relativePath === '' || $relativePath === '/') {
    $relativePath = '/index.php';
}

$fullPath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

// Serve static files if they exist (and are not PHP files)
if (is_file($fullPath) && !str_ends_with($fullPath, '.php')) {
    return false;
}

// Otherwise, fall through to index.php
require __DIR__ . '/index.php';
