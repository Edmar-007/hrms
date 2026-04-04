<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if(empty($code)) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received']);
    exit;
}

// Find employee by employee_code (QR code contains employee_code)
$st = $pdo->prepare("SELECT id, employee_code, first_name, last_name FROM employees WHERE employee_code = ? AND status = 'active'");
$st->execute([$code]);
$employee = $st->fetch();

if(!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found: ' . $code]);
    exit;
}

$employeeId = $employee['id'];
$employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
$date = date('Y-m-d');
$time = date('H:i:s');

// Check today's attendance
$st = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$st->execute([$employeeId, $date]);
$attendance = $st->fetch();

if(!$attendance) {
    // Time In - Check if SaaS mode
    $hasSaas = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'company_id'")->fetch();
    
    if($hasSaas) {
        $cid = company_id() ?? 1;
        $st = $pdo->prepare("INSERT INTO attendance (company_id, employee_id, date, time_in) VALUES (?, ?, ?, ?)");
        $st->execute([$cid, $employeeId, $date, $time]);
    } else {
        $st = $pdo->prepare("INSERT INTO attendance (employee_id, date, time_in) VALUES (?, ?, ?)");
        $st->execute([$employeeId, $date, $time]);
    }
    
    echo json_encode([
        'success' => true,
        'action' => 'Time In',
        'employee' => $employeeName,
        'time' => date('h:i A'),
        'message' => $employeeName . ' - Time In recorded'
    ]);
} elseif(!$attendance['time_out']) {
    // Time Out
    $st = $pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ?");
    $st->execute([$time, $attendance['id']]);
    
    echo json_encode([
        'success' => true,
        'action' => 'Time Out',
        'employee' => $employeeName,
        'time' => date('h:i A'),
        'message' => $employeeName . ' - Time Out recorded'
    ]);
} else {
    // Already completed
    echo json_encode([
        'success' => false,
        'message' => $employeeName . ' already completed attendance today'
    ]);
}
