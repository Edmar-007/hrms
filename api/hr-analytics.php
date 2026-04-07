<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hr-analytics-data.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ? company_id() : 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $range = hr_analytics_parse_range($_GET['range'] ?? 'month');
        $payload = hr_analytics_build_dashboard($pdo, $cid, $range);
        $payload['range'] = $range;
        json_response($payload);
    } catch (Exception $e) {
        error_log('HR analytics API error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load HR analytics.'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
