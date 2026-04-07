<?php
require_once __DIR__ . '/config/config.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim(BASE_URL, '/');

if ($requestPath === $basePath || $requestPath === $basePath . '/' || $requestPath === '/index.php' || $requestPath === $basePath . '/index.php') {
    $target = !empty($_SESSION['user']) ? BASE_URL . '/dashboard' : BASE_URL . '/login';
    header('Location: ' . $target);
    exit;
}

$distIndex = __DIR__ . '/dist/index.html';
if (!is_file($distIndex)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Frontend build not found. Expected dist/index.html.';
    exit;
}

$html = (string)file_get_contents($distIndex);
$assetBase = BASE_URL . '/dist';

$replacements = [
    'href="/assets/' => 'href="' . $assetBase . '/assets/',
    'src="/assets/' => 'src="' . $assetBase . '/assets/',
    'href="/vite.svg"' => 'href="' . $assetBase . '/vite.svg"',
];

$html = strtr($html, $replacements);

header('Content-Type: text/html; charset=UTF-8');
echo $html;
