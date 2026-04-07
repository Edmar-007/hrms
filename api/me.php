<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$user = $_SESSION['user'];
$name = trim((string)($user['name'] ?? ''));
if ($name === '') {
    $name = (string)($user['email'] ?? 'User');
}

json_response([
    'id' => (int)($user['id'] ?? 0),
    'name' => $name,
    'email' => (string)($user['email'] ?? ''),
    'role' => (string)($user['role'] ?? 'Employee'),
    'theme' => (string)($user['theme'] ?? 'light'),
]);
