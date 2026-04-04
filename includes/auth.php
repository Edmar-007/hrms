<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/functions.php';

function user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if(empty($_SESSION['user'])) redirect('/auth/login.php');
}

function require_role($roles = []) {
    require_login();
    if(!in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function has_feature($feature) {
    $company = company();
    if(!$company) return false;
    $features = json_decode($company['features'] ?? '{}', true);
    return $features[$feature] ?? false;
}

function log_activity($action, $entityType = null, $entityId = null, $details = null) {
    global $pdo;
    try {
        $companyId = company_id() ?? 1;
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $st = $pdo->prepare("INSERT INTO activity_logs (company_id, user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$companyId, $userId, $action, $entityType, $entityId, json_encode($details), $ip]);
    } catch(Exception $e) { /* Table may not exist */ }
}

function notify($userId, $type, $title, $message = '', $link = '') {
    global $pdo;
    try {
        $companyId = company_id() ?? 1;
        $st = $pdo->prepare("INSERT INTO notifications (company_id, user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$companyId, $userId, $type, $title, $message, $link]);
    } catch(Exception $e) { /* Table may not exist */ }
}

function get_theme() {
    return $_SESSION['user']['theme'] ?? 'light';
}
