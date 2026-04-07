<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ?? 1;
$role = (string)($_SESSION['user']['role'] ?? 'Employee');
$canManage = in_array($role, ['Admin', 'HR Officer', 'Manager'], true);

function parse_json_body(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function get_company_settings($pdo, $cid) {
    $st = $pdo->prepare('SELECT name, email, timezone, date_format, notification_prefs FROM companies WHERE id = ? LIMIT 1');
    $st->execute([$cid]);
    $company = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $prefs = json_decode($company['notification_prefs'] ?? '{}', true) ?: [];

    return [
        'companyName' => (string)($company['name'] ?? ''),
        'supportEmail' => (string)($company['email'] ?? ''),
        'timezone' => (string)($company['timezone'] ?? 'Asia/Manila'),
        'locale' => (string)($company['date_format'] ?? 'English (US)'),
        'emailAlerts' => (bool)($prefs['emailAlerts'] ?? true),
        'weeklyDigest' => (bool)($prefs['weeklyDigest'] ?? true),
        'twoFactor' => (bool)($prefs['twoFactor'] ?? false),
        'ipRestriction' => (bool)($prefs['ipRestriction'] ?? false),
    ];
}

function update_company_settings($pdo, $cid, $data, $body) {
    $prefs = [
        'emailAlerts' => (bool)($body['emailAlerts'] ?? true),
        'weeklyDigest' => (bool)($body['weeklyDigest'] ?? true),
        'twoFactor' => (bool)($body['twoFactor'] ?? false),
        'ipRestriction' => (bool)($body['ipRestriction'] ?? false),
    ];

    $sql = 'UPDATE companies SET name = ?, email = ?, timezone = ?, date_format = ?, notification_prefs = ? WHERE id = ?';
    $st = $pdo->prepare($sql);
    $st->execute([
        $data['companyName'],
        $data['supportEmail'],
        $data['timezone'],
        $data['locale'],
        json_encode($prefs),
        $cid,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = get_company_settings($pdo, $cid);
    json_response($settings);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $body = parse_json_body();
    $companyName = trim((string)($body['companyName'] ?? ''));
    $supportEmail = trim((string)($body['supportEmail'] ?? ''));

    if ($companyName === '' || $supportEmail === '') {
        json_response(['error' => 'Company name and support email required'], 422);
    }

    $newSettings = [
        'companyName' => $companyName,
        'supportEmail' => $supportEmail,
        'timezone' => trim((string)($body['timezone'] ?? 'Asia/Manila')),
        'locale' => trim((string)($body['locale'] ?? 'English (US)')),
    ];

    update_company_settings($pdo, $cid, $newSettings, $body);

    $_SESSION['company']['name'] = $newSettings['companyName'];
    $_SESSION['company']['timezone'] = $newSettings['timezone'];

    log_activity('update_company_settings', 'company', $cid, $newSettings);
    json_response(['success' => true, 'settings' => $newSettings]);
}

json_response(['error' => 'Method not allowed'], 405);
?>

