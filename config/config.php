<?php
define('APP_NAME', 'HRMS SaaS');
define('APP_VERSION', '2.0.0');
define('BASE_URL', '/hrms');
define('TIMEZONE', 'Asia/Manila');
date_default_timezone_set(TIMEZONE);
session_start();

// Helper to get current company
function company() {
    return $_SESSION['company'] ?? null;
}

function company_id() {
    return $_SESSION['company']['id'] ?? null;
}
