<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/security.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$requestedAction = trim((string)($input['action'] ?? 'auto'));
$gps = $input['gps'] ?? null;
$nowTs = time();

if(empty($code)) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received']);
    exit;
}

$ip = client_ip();
if (!rate_limit_check('scan:'.$ip, 120, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many scan requests. Please wait a moment.']);
    exit;
}

$cid = company_id() ?? 1;

$settingsStmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE company_id = ? LIMIT 1");
$settingsStmt->execute([$cid]);
$settings = $settingsStmt->fetch() ?: [];
$duplicateWindow = max(1, (int)($settings['duplicate_scan_seconds'] ?? 3));
$strictSequence = (int)($settings['require_action_sequence'] ?? 1) === 1;
$gpsEnabled = (int)($settings['gps_capture_enabled'] ?? 0) === 1;
$graceBefore = max(0, (int)($settings['out_of_shift_grace_before_minutes'] ?? 60));
$graceAfter = max(0, (int)($settings['out_of_shift_grace_after_minutes'] ?? 60));

// Find employee by employee_code (QR code contains employee_code)
$st = $pdo->prepare("SELECT id, employee_code, first_name, last_name FROM employees WHERE company_id = ? AND employee_code = ? AND status = 'active'");
$st->execute([$cid, $code]);
$employee = $st->fetch();

if(!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found: ' . $code]);
    exit;
}

$employeeId = $employee['id'];
$employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
$date = date('Y-m-d');
$time = date('H:i:s');
$nowDt = date('Y-m-d H:i:s');
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); // attendance.user_agent column is VARCHAR(255)
$lat = null;
$lng = null;
if ($gpsEnabled && is_array($gps)) {
    $latVal = $gps['lat'] ?? null;
    $lngVal = $gps['lng'] ?? null;
    if (is_numeric($latVal) && is_numeric($lngVal)) {
        $lat = round((float)$latVal, 7);
        $lng = round((float)$lngVal, 7);
    }
}

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
$shiftSt->execute([$cid, $employeeId, $date, $date]);
$empShift = $shiftSt->fetch();

if ($empShift) {
    $nowMinutes = (int)date('H') * 60 + (int)date('i');
    $shiftStart = (int)substr($empShift['start_time'], 0, 2) * 60 + (int)substr($empShift['start_time'], 3, 2);
    $shiftEnd   = (int)substr($empShift['end_time'],   0, 2) * 60 + (int)substr($empShift['end_time'],   3, 2);

    if ($nowMinutes < ($shiftStart - $graceBefore) || $nowMinutes > ($shiftEnd + $graceAfter)) {
        $shiftWarning = 'Scan outside shift hours (' . date('h:i A', strtotime($empShift['start_time'])) . ' – ' . date('h:i A', strtotime($empShift['end_time'])) . ')';
    }
}

// Check today's attendance
$st = $pdo->prepare("SELECT * FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ?");
$st->execute([$cid, $employeeId, $date]);
$attendance = $st->fetch();

$requestedAction = strtolower($requestedAction);
$allowedActions = ['auto', 'time_in', 'break_in', 'break_out', 'time_out'];
if (!in_array($requestedAction, $allowedActions, true)) $requestedAction = 'auto';

$lastScanAt = $attendance['last_scan_at'] ?? null;
if ($lastScanAt && ($nowTs - strtotime($lastScanAt)) < $duplicateWindow) {
    echo json_encode(['success' => false, 'message' => 'Duplicate scan detected. Please wait '.($duplicateWindow).' seconds.']);
    exit;
}

$nextAction = 'time_in';
if ($attendance) {
    $lastAction = $attendance['last_action'] ?? null;
    if ($lastAction === null) {
        if (!empty($attendance['time_out'])) $lastAction = 'time_out';
        elseif (!empty($attendance['time_in'])) $lastAction = 'time_in';
    }
    switch ($lastAction) {
        case 'time_in':  $nextAction = 'break_in'; break;
        case 'break_in': $nextAction = 'break_out'; break;
        case 'break_out': $nextAction = 'time_out'; break;
        case 'time_out': $nextAction = 'done'; break;
        default:         $nextAction = 'time_in'; break;
    }
}

if ($nextAction === 'done') {
    echo json_encode(['success' => false, 'message' => $employeeName . ' already completed attendance today']);
    exit;
}

$action = ($requestedAction === 'auto') ? $nextAction : $requestedAction;
if ($strictSequence && $action !== $nextAction) {
    echo json_encode(['success' => false, 'message' => 'Invalid action order. Expected: '.str_replace('_', ' ', $nextAction)]);
    exit;
}

if (!$attendance && $action !== 'time_in') {
    echo json_encode(['success' => false, 'message' => 'First scan must be Time In']);
    exit;
}

if ($action === 'time_in') {
    if ($attendance) {
        echo json_encode(['success' => false, 'message' => 'Time In already recorded']);
        exit;
    }
    $st = $pdo->prepare("INSERT INTO attendance (company_id, employee_id, date, time_in, last_action, last_scan_at, scan_ip, user_agent, latitude, longitude) VALUES (?, ?, ?, ?, 'time_in', ?, ?, ?, ?, ?)");
    $st->execute([$cid, $employeeId, $date, $time, $nowDt, $ip, $ua, $lat, $lng]);
} elseif ($action === 'break_in') {
    if (!$attendance) { echo json_encode(['success' => false, 'message' => 'Attendance record not found']); exit; }
    $pdo->prepare("INSERT INTO break_records (company_id, employee_id, attendance_id, break_start) VALUES (?, ?, ?, NOW())")
        ->execute([$cid, $employeeId, $attendance['id']]);
    $pdo->prepare("UPDATE attendance SET last_action='break_in', last_scan_at=?, scan_ip=?, user_agent=?, latitude=?, longitude=? WHERE id=?")
        ->execute([$nowDt, $ip, $ua, $lat, $lng, $attendance['id']]);
} elseif ($action === 'break_out') {
    if (!$attendance) { echo json_encode(['success' => false, 'message' => 'Attendance record not found']); exit; }
    $br = $pdo->prepare("SELECT * FROM break_records WHERE company_id=? AND attendance_id=? AND employee_id=? AND break_end IS NULL ORDER BY id DESC LIMIT 1");
    $br->execute([$cid, $attendance['id'], $employeeId]);
    $openBreak = $br->fetch();
    if (!$openBreak) {
        echo json_encode(['success' => false, 'message' => 'No active break found']);
        exit;
    }
    $dur = max(0, (int)round((time() - strtotime($openBreak['break_start'])) / 60));
    $pdo->prepare("UPDATE break_records SET break_end=NOW(), duration_minutes=? WHERE id=?")->execute([$dur, $openBreak['id']]);
    $pdo->prepare("UPDATE attendance SET last_action='break_out', last_scan_at=?, scan_ip=?, user_agent=?, latitude=?, longitude=? WHERE id=?")
        ->execute([$nowDt, $ip, $ua, $lat, $lng, $attendance['id']]);
} elseif ($action === 'time_out') {
    if (!$attendance) { echo json_encode(['success' => false, 'message' => 'Attendance record not found']); exit; }
    $st = $pdo->prepare("UPDATE attendance SET time_out = ?, last_action='time_out', last_scan_at=?, scan_ip=?, user_agent=?, latitude=?, longitude=? WHERE id = ?");
    $st->execute([$time, $nowDt, $ip, $ua, $lat, $lng, $attendance['id']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

log_activity('attendance_scan', 'attendance', $attendance['id'] ?? null, ['employee_id' => $employeeId, 'action' => $action, 'ip' => $ip]);

$actionLabels = [
    'time_in' => 'Time In',
    'break_in' => 'Break In',
    'break_out' => 'Break Out',
    'time_out' => 'Time Out'
];
echo json_encode([
    'success' => true,
    'action' => $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action)),
    'employee' => $employeeName,
    'time' => date('h:i A'),
    'message' => $employeeName . ' - ' . ($actionLabels[$action] ?? $action) . ' recorded',
    'shift_warning' => $shiftWarning
]);
