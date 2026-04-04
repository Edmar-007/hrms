<?php
function e($s) {
    return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8");
}

function redirect($p) {
    header("Location: " . BASE_URL . $p);
    exit;
}

function is_post() {
    return $_SERVER["REQUEST_METHOD"] === "POST";
}

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function format_currency($amount) {
    $currency = $_SESSION['company']['currency'] ?? 'PHP';
    $symbols = ['PHP' => '₱', 'USD' => '$', 'EUR' => '€'];
    return ($symbols[$currency] ?? $currency . ' ') . number_format($amount, 2);
}

function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

function format_time($time) {
    return date('h:i A', strtotime($time));
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

function generate_employee_code($companyId) {
    global $pdo;
    $st = $pdo->prepare("SELECT COUNT(*) + 1 as next FROM employees WHERE company_id = ?");
    $st->execute([$companyId]);
    $next = $st->fetch()['next'];
    return 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function avatar_initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: '?';
}
