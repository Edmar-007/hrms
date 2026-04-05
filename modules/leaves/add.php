<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/security.php';
require_once __DIR__.'/../../includes/validator.php';
require_login();

if(is_post() && verify_csrf()) {
    $empId = $_SESSION['user']['employee_id'] ?? null;
    $typeId = intval($_POST['leave_type_id'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if($empId && $typeId && $start && $end && v_date($start) && v_date($end) && $start <= $end) {
        $attachmentPath = null;
        if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$ok, $mimeOrErr] = upload_is_allowed($_FILES['attachment'], ['application/pdf', 'image/jpeg', 'image/png'], 5 * 1024 * 1024);
            if ($ok) {
                $stored = store_upload($_FILES['attachment'], __DIR__.'/../../public/uploads/leave_attachments', 'leave_'.$empId, [
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ]);
                if ($stored) $attachmentPath = '/public/uploads/leave_attachments/'.$stored;
            }
        }

        // Check if SaaS mode
        $hasSaas = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'company_id'")->fetch();
        
        try {
            if($hasSaas) {
                $cid = company_id() ?? 1;
                $st = $pdo->prepare("INSERT INTO leave_requests (company_id, employee_id, leave_type_id, start_date, end_date, reason, attachment_path, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $st->execute([$cid, $empId, $typeId, $start, $end, $reason, $attachmentPath]);
                $newId = (int)$pdo->lastInsertId();
                $notify = $pdo->prepare("SELECT id FROM users WHERE company_id = ? AND role IN ('Admin','HR Officer','Manager') AND is_active = 1");
                $notify->execute([$cid]);
                foreach ($notify->fetchAll() as $n) {
                    notify((int)$n['id'], 'leave', 'New Leave Request', 'A leave request was submitted.', '/hrms/modules/leaves/index.php');
                }
                log_activity('create', 'leave_request', $newId, ['employee_id' => $empId]);
            } else {
                $st = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, attachment_path, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $st->execute([$empId, $typeId, $start, $end, $reason, $attachmentPath]);
            }
            
            header("Location: index.php?msg=added");
            exit;
        } catch(PDOException $e) {
            error_log("Leave request insert failed: " . $e->getMessage());
            header("Location: index.php?msg=error");
            exit;
        }
    } else {
        // Missing required fields or invalid dates
        header("Location: index.php?msg=error");
        exit;
    }
}

header("Location: index.php");
exit;
