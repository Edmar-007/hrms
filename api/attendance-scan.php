<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$role = (string)($_SESSION['user']['role'] ?? 'Employee');
$canScan = in_array($role, ['Super Admin', 'Admin', 'HR Officer', 'Manager'], true);
if (!$canScan) {
    json_response(['error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = json_decode(file_get_contents('php://input') ?: '{}', true);
$code = trim((string)($raw['code'] ?? ''));
if ($code === '') {
    json_response(['error' => 'No QR code data received'], 422);
}

$cid = company_id() ?? 1;
$today = date('Y-m-d');
$time = date('H:i:s');
$nowReadable = date('h:i A');

$parsed = json_decode($code, true);
$employeeId = 0;
$employeeCode = '';

if (is_array($parsed) && ($parsed['type'] ?? '') === 'hrms_employee') {
    $employeeId = (int)($parsed['id'] ?? 0);
    $employeeCode = trim((string)($parsed['code'] ?? ''));
} else {
    $employeeCode = $code;
}

$employee = null;
if ($employeeId > 0) {
    $st = $pdo->prepare('SELECT id, employee_code, first_name, last_name FROM employees WHERE company_id = ? AND id = ? AND status = "active" LIMIT 1');
    $st->execute([$cid, $employeeId]);
    $employee = $st->fetch();
}

if (!$employee && $employeeCode !== '') {
    $st = $pdo->prepare('SELECT id, employee_code, first_name, last_name FROM employees WHERE company_id = ? AND employee_code = ? AND status = "active" LIMIT 1');
    $st->execute([$cid, $employeeCode]);
    $employee = $st->fetch();
}

if (!$employee) {
    json_response(['error' => 'Employee not found or inactive'], 404);
}

$employeeId = (int)$employee['id'];
$employeeName = trim((string)(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')));

$attendanceSt = $pdo->prepare('SELECT id, time_in, time_out FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ? LIMIT 1');
$attendanceSt->execute([$cid, $employeeId, $today]);
$attendance = $attendanceSt->fetch();

if (!$attendance) {
    $insert = $pdo->prepare('INSERT INTO attendance(company_id, employee_id, date, time_in, notes) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$cid, $employeeId, $today, $time, 'QR scanner time in']);

    log_activity('attendance_qr_scan', 'attendance', (int)$pdo->lastInsertId(), [
        'employee_id' => $employeeId,
        'action' => 'time_in',
    ]);

    json_response([
        'success' => true,
        'action' => 'Time In',
        'employee' => $employeeName,
        'time' => $nowReadable,
        'message' => $employeeName . ' - Time In recorded',
    ]);
}

if (!empty($attendance['time_out'])) {
    json_response(['error' => $employeeName . ' already completed attendance today'], 409);
}

$update = $pdo->prepare('UPDATE attendance SET time_out = ?, notes = COALESCE(notes, ?) WHERE id = ?');
$update->execute([$time, 'QR scanner time out', (int)$attendance['id']]);

log_activity('attendance_qr_scan', 'attendance', (int)$attendance['id'], [
    'employee_id' => $employeeId,
    'action' => 'time_out',
]);

json_response([
    'success' => true,
    'action' => 'Time Out',
    'employee' => $employeeName,
    'time' => $nowReadable,
    'message' => $employeeName . ' - Time Out recorded',
]);
