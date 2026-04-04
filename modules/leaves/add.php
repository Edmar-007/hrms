<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_login();

if(is_post() && verify_csrf()) {
    $empId = $_SESSION['user']['employee_id'];
    $typeId = intval($_POST['leave_type_id'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if($empId && $typeId && $start && $end) {
        // Check if SaaS mode
        $hasSaas = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'company_id'")->fetch();
        
        if($hasSaas) {
            $cid = company_id() ?? 1;
            $st = $pdo->prepare("INSERT INTO leave_requests (company_id, employee_id, leave_type_id, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $st->execute([$cid, $empId, $typeId, $start, $end, $reason]);
        } else {
            $st = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')");
            $st->execute([$empId, $typeId, $start, $end, $reason]);
        }
    }
    
    header("Location: index.php?msg=added");
    exit;
}

header("Location: index.php");
exit;
