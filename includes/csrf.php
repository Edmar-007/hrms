<?php
require_once __DIR__ . '/../config/config.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token = null): bool
    {
        $provided = $token;
        if ($provided === null) {
            $provided = $_POST['csrf_token']
                ?? $_POST['_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? null;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        return is_string($provided) && is_string($sessionToken) && hash_equals($sessionToken, $provided);
    }
}
