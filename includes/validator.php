<?php

if (!function_exists('v_email')) {
    function v_email(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('v_password')) {
    function v_password(string $password, int $minLength = 8): bool
    {
        return strlen($password) >= $minLength;
    }
}
