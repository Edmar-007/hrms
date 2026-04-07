<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($raw['email'] ?? ''));
$password = (string)($raw['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Email and password are required'], 422);
}

try {
    $st = $pdo->prepare(
        "SELECT u.*, e.first_name, e.last_name, c.id as comp_id, c.name as comp_name,
                c.slug as comp_slug, c.currency, c.timezone, c.features
         FROM users u
         LEFT JOIN employees e ON e.id = u.employee_id
         LEFT JOIN companies c ON c.id = u.company_id
         WHERE u.email = ? AND u.is_active = 1
         LIMIT 1"
    );
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || !password_verify($password, $u['password_hash'])) {
        json_response(['error' => 'Invalid credentials'], 401);
    }

    $theme = 'light';
    try {
        $prefs = $pdo->prepare('SELECT theme FROM user_preferences WHERE user_id = ? LIMIT 1');
        $prefs->execute([$u['id']]);
        $pref = $prefs->fetch();
        if ($pref && !empty($pref['theme'])) {
            $theme = $pref['theme'];
        }
    } catch (Exception $e) {
        // Keep default theme when preferences table or row is unavailable.
    }

    $_SESSION['user'] = [
        'id' => (int)$u['id'],
        'company_id' => (int)($u['company_id'] ?? 1),
        'employee_id' => isset($u['employee_id']) ? (int)$u['employee_id'] : null,
        'role' => (string)($u['role'] ?? 'Employee'),
        'email' => (string)$u['email'],
        'name' => trim((string)(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))),
        'theme' => $theme,
    ];

    $_SESSION['company'] = [
        'id' => (int)($u['comp_id'] ?? 1),
        'name' => (string)($u['comp_name'] ?? 'My Company'),
        'slug' => (string)($u['comp_slug'] ?? 'default'),
        'currency' => (string)($u['currency'] ?? 'PHP'),
        'timezone' => (string)($u['timezone'] ?? 'Asia/Manila'),
        'features' => (string)($u['features'] ?? '{}'),
    ];

    try {
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
    } catch (Exception $e) {
        // Ignore when the column is not present in older schemas.
    }

    $displayName = $_SESSION['user']['name'];
    if ($displayName === '') {
        $displayName = (string)$u['email'];
    }

    json_response([
        'success' => true,
        'user' => [
            'id' => (int)$u['id'],
            'name' => $displayName,
            'email' => (string)$u['email'],
            'role' => (string)($u['role'] ?? 'Employee'),
            'theme' => $theme,
        ],
    ]);
} catch (PDOException $e) {
    error_log('API login error: ' . $e->getMessage());
    json_response(['error' => 'Unable to process login'], 500);
}
