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

// Try to parse as JSON first (new format)
$qrParsed = @json_decode($code, true);
$employeeCode = null;
$employeeId = null;

if($qrParsed && isset($qrParsed['type']) && $qrParsed['type'] === 'hrms_employee') {
    // New JSON format - use employee ID directly
    $employeeId = intval($qrParsed['id'] ?? 0);
    $employeeCode = $qrParsed['code'] ?? null;
} else {
    // Legacy format - code is just the employee_code string
    $employeeCode = $code;
}

// Find employee
$employee = null;
if($employeeId) {
    $st = $pdo->prepare("SELECT id, employee_code, first_name, last_name FROM employees WHERE id = ? AND status = 'active'");
    $st->execute([$employeeId]);
    $employee = $st->fetch();
}

if(!$employee && $employeeCode) {
    // Fallback to employee_code search
    $st = $pdo->prepare("SELECT id, employee_code, first_name, last_name FROM employees WHERE employee_code = ? AND status = 'active'");
    $st->execute([$employeeCode]);
    $employee = $st->fetch();
}

if(!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found or inactive']);
    exit;
}

$employeeId = $employee['id'];
$employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
$date = date('Y-m-d');
$time = date('H:i:s');

// Check employee's shift for today and validate time
$shiftWarning = null;
$shiftSt = $pdo->prepare("
    SELECT s.* FROM shifts s
    JOIN shift_assignments sa ON sa.shift_id = s.id
    WHERE sa.company_id = ? AND sa.employee_id = ?
    AND sa.effective_from <= ?
    AND (sa.effective_to IS NULL OR sa.effective_to >= ?)
    AND s.is_active = 1
    LIMIT 1
");
$hasSaasCheck = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'company_id'")->fetch();
$cidForShift = $hasSaasCheck ? (company_id() ?? 1) : 1;
$shiftSt->execute([$cidForShift, $employeeId, $date, $date]);
$empShift = $shiftSt->fetch();

if ($empShift) {
    $nowMinutes = (int)date('H') * 60 + (int)date('i');
    $shiftStart = (int)substr($empShift['start_time'], 0, 2) * 60 + (int)substr($empShift['start_time'], 3, 2);
    $shiftEnd   = (int)substr($empShift['end_time'],   0, 2) * 60 + (int)substr($empShift['end_time'],   3, 2);

    // Allow 60 minutes before shift start and after shift end
    $graceBefore = 60;
    $graceAfter  = 60;

    if ($nowMinutes < ($shiftStart - $graceBefore) || $nowMinutes > ($shiftEnd + $graceAfter)) {
        $shiftWarning = 'Scan outside shift hours (' . date('h:i A', strtotime($empShift['start_time'])) . ' – ' . date('h:i A', strtotime($empShift['end_time'])) . ')';
    }
}

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
        'employee_code' => $employee['employee_code'],
        'time' => date('h:i A'),
        'message' => $employeeName . ' - Time In recorded',
        'shift_warning' => $shiftWarning
    ]);
} elseif(!$attendance['time_out']) {
    // Time Out
    $st = $pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ?");
    $st->execute([$time, $attendance['id']]);
    
    echo json_encode([
        'success' => true,
        'action' => 'Time Out',
        'employee' => $employeeName,
        'employee_code' => $employee['employee_code'],
        'time' => date('h:i A'),
        'message' => $employeeName . ' - Time Out recorded',
        'shift_warning' => $shiftWarning
    ]);
} else {
    // Already completed
    echo json_encode([
        'success' => false,
        'message' => $employeeName . ' already completed attendance today'
    ]);
}
