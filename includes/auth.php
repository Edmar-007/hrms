<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!function_exists('is_json_request')) {
    function is_json_request(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_contains($uri, '/api/');
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!empty($_SESSION['user'])) {
            return;
        }

        if (is_json_request()) {
            json_response(['error' => 'Unauthorized'], 401);
        }

        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

if (!function_exists('has_role')) {
    function has_role(array|string $roles): bool
    {
        $user = current_user();
        if (!$user) {
            return false;
        }

        $role = (string)($user['role'] ?? '');
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        return in_array($role, $allowedRoles, true);
    }
}

if (!function_exists('require_role')) {
    function require_role(array|string $roles): void
    {
        require_login();

        if (has_role($roles)) {
            return;
        }

        if (is_json_request()) {
            json_response(['error' => 'Forbidden'], 403);
        }

        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
