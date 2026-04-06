<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/* ── MIME type map for static assets ── */
$mimeMap = [
    'css'   => 'text/css; charset=UTF-8',
    'js'    => 'application/javascript; charset=UTF-8',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
    'map'   => 'application/json',
    'json'  => 'application/json',
    'pdf'   => 'application/pdf',
    'txt'   => 'text/plain; charset=UTF-8',
];

/**
 * Serve a static file with correct MIME type and caching headers.
 */
function serveStatic(string $fullPath): true {
    global $mimeMap;
    $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');   // 1-day browser cache
    header('X-Content-Type-Options: nosniff');         // tell browser to trust this MIME
    readfile($fullPath);
    return true;
}

/* ── 1. Bare static files at the server root (e.g. /favicon.ico) ── */
if ($uri !== '/') {
    $rootPath = __DIR__ . '/public' . $uri;
    if (file_exists($rootPath) && !is_dir($rootPath)) {
        return serveStatic($rootPath);
    }

    $directPath = __DIR__ . $uri;
    if (file_exists($directPath) && !is_dir($directPath)) {
        $ext = strtolower(pathinfo($directPath, PATHINFO_EXTENSION));
        if (isset($mimeMap[$ext])) {
            return serveStatic($directPath);
        }
        // Non-asset file — let PHP built-in server handle it
        return false;
    }
}

/* ── 2. /hrms/ prefix routing ── */
if ($uri === '/hrms' || $uri === '/hrms/') {
    require __DIR__ . '/index.php';
    return true;
}

if (strpos($uri, '/hrms/') === 0) {
    $path     = substr($uri, 6);                      // strip leading '/hrms/'
    $fullPath = __DIR__ . '/' . $path;

    if (file_exists($fullPath) && !is_dir($fullPath)) {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // Static asset — serve with correct MIME type
        if (isset($mimeMap[$ext])) {
            return serveStatic($fullPath);
        }

        // PHP file — execute normally
        require $fullPath;
        return true;
    }
}

return false;
