<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePrefix = '/hrms';
$relativePath = $requestPath;

if (str_starts_with($relativePath, $basePrefix)) {
    $relativePath = substr($relativePath, strlen($basePrefix));
    if ($relativePath === false || $relativePath === '') {
        $relativePath = '/';
    }
}

$fullPath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
if ($relativePath !== '/' && is_file($fullPath)) {
    return false;
}

require __DIR__ . '/index.php';
