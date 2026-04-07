<?php
require_once __DIR__ . '/../config/config.php';

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $ip = trim(explode(',', $candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }
}

if (!function_exists('rate_limit_check')) {
    function rate_limit_check(string $key, int $limit, int $windowSeconds): bool
    {
        $dir = __DIR__ . '/../.cache/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . sha1($key) . '.json';
        $now = time();
        $state = ['window_start' => $now, 'count' => 0];

        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded)) {
                $state = array_merge($state, $decoded);
            }
        }

        $windowStart = (int)($state['window_start'] ?? $now);
        $count = (int)($state['count'] ?? 0);

        if (($now - $windowStart) >= $windowSeconds) {
            $windowStart = $now;
            $count = 0;
        }

        $count++;
        file_put_contents($file, json_encode([
            'window_start' => $windowStart,
            'count' => $count,
        ]));

        return $count <= $limit;
    }
}
