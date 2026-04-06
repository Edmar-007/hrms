<?php
/**
 * Leave Handler Functions
 * Extracted helper functions for leave management
 */

/**
 * Calculate the number of days between two dates (inclusive)
 */
function calculate_leave_days($startDate, $endDate) {
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    if ($start === false || $end === false) return 0;
    return (int) ceil(($end - $start) / 86400) + 1;
}

/**
 * Process leave approval/rejection action
 */
function process_leave_action($pdo, $leaveId, $action, $user, $companyId, $hasSaas) {
    // Fetch current leave request
    if ($hasSaas && $companyId) {
        $stmt = $pdo->prepare("SELECT lr.*, lt.is_paid FROM leave_requests lr 
            JOIN leave_types lt ON lt.id = lr.leave_type_id 
            WHERE lr.id = ? AND lr.company_id = ?");
        $stmt->execute([$leaveId, $companyId]);
    } else {
        $stmt = $pdo->prepare("SELECT lr.*, lt.is_paid FROM leave_requests lr 
            JOIN leave_types lt ON lt.id = lr.leave_type_id 
            WHERE lr.id = ?");
        $stmt->execute([$leaveId]);
    }
    $leaveData = $stmt->fetch();
    
    if (!$leaveData) {
        return ['success' => false, 'message' => 'Leave request not found', 'data' => null];
    }

    // Update leave request status
    if ($hasSaas && $companyId) {
        if ($action === 'cancelled') {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status='rejected', cancelled_at=NOW(), cancelled_by=? WHERE id=? AND company_id=?");
            $stmt->execute([$user['id'], $leaveId, $companyId]);
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND company_id=?");
            $stmt->execute([$action, $user['id'], $leaveId, $companyId]);
        }
    } else {
        if ($action === 'cancelled') {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status='rejected', cancelled_at=NOW(), cancelled_by=? WHERE id=?");
            $stmt->execute([$user['id'], $leaveId]);
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=?");
            $stmt->execute([$action, $user['id'], $leaveId]);
        }
    }

    return ['success' => $stmt->rowCount() > 0, 'message' => $action, 'data' => $leaveData];
}

/**
 * Update employee leave balance after approval/rejection
 */
function update_leave_balance($pdo, $leaveData, $action, $companyId, $hasSaas) {
    $leaveDays = calculate_leave_days($leaveData['start_date'], $leaveData['end_date']);
    $year = (int) date('Y', strtotime($leaveData['start_date']));
    $empId = $leaveData['employee_id'];
    $ltId = $leaveData['leave_type_id'];
    $prevStatus = $leaveData['status'];

    try {
        if ($action === 'approved' && $prevStatus === 'pending') {
            if ($hasSaas && $companyId) {
                $pdo->prepare("UPDATE employee_leave_balance SET used = used + ? WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $companyId, $empId, $ltId, $year]);
            } else {
                $pdo->prepare("UPDATE employee_leave_balance SET used = used + ? WHERE employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $empId, $ltId, $year]);
            }
        } elseif ($action === 'rejected' && $prevStatus === 'approved') {
            if ($hasSaas && $companyId) {
                $pdo->prepare("UPDATE employee_leave_balance SET used = GREATEST(0, used - ?) WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $companyId, $empId, $ltId, $year]);
            } else {
                $pdo->prepare("UPDATE employee_leave_balance SET used = GREATEST(0, used - ?) WHERE employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $empId, $ltId, $year]);
            }
        }
    } catch (PDOException $e) {
        error_log("Leave balance update failed: " . $e->getMessage());
    }
}

/**
 * Send notifications about leave status changes
 */
function send_leave_notification($pdo, $leaveData, $action) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$leaveData['employee_id']]);
        $userRecord = $stmt->fetch();
        
        if ($userRecord) {
            if ($action === 'approved') {
                notify((int)$userRecord['id'], 'leave', 'Leave Approved', 'Your leave request has been approved.', '/hrms/modules/leaves/index.php');
            } elseif ($action === 'rejected' || $action === 'cancelled') {
                notify((int)$userRecord['id'], 'leave', 'Leave Rejected/Cancelled', 'Your leave request was rejected or cancelled.', '/hrms/modules/leaves/index.php');
            }
        }
    } catch (Exception $e) {
        error_log("Leave notification failed: " . $e->getMessage());
    }
}

/**
 * Render status badge HTML
 */
function render_status_badge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>';
        case 'approved':
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>';
        default:
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
    }
}

/**
 * Render action buttons for leave requests
 */
function render_leave_actions($row, $canApprove) {
    if (!$canApprove) {
        return '<span class="text-muted small">-</span>';
    }
    
    $id = (int)$row['id'];
    $html = '';
    
    if ($row['status'] === 'pending') {
        $html .= '<form method="post" class="d-inline" onsubmit="return confirm(\'Approve this leave?\')">
            ' . csrf_input() . '
            <input type="hidden" name="action" value="approved">
            <input type="hidden" name="id" value="' . $id . '">
            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm(\'Reject this leave?\')">
            ' . csrf_input() . '
            <input type="hidden" name="action" value="rejected">
            <input type="hidden" name="id" value="' . $id . '">
            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm(\'Cancel this leave?\')">
            ' . csrf_input() . '
            <input type="hidden" name="action" value="cancelled">
            <input type="hidden" name="id" value="' . $id . '">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-slash-circle"></i></button>
        </form>';
    } elseif ($row['status'] === 'approved') {
        $html .= '<form method="post" class="d-inline" onsubmit="return confirm(\'Revoke this approved leave?\')">
            ' . csrf_input() . '
            <input type="hidden" name="action" value="rejected">
            <input type="hidden" name="id" value="' . $id . '">
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
        </form>';
    } else {
        $html = '<span class="text-muted small">-</span>';
    }
    
    return $html;
}

/**
 * Get employees list for company (for admin to request leave on behalf)
 */
function get_company_employees($pdo, $companyId, $hasSaas) {
    if ($hasSaas && $companyId) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE company_id = ? AND status = 'active' ORDER BY first_name, last_name");
        $stmt->execute([$companyId]);
    } else {
        $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name, last_name");
    }
    return $stmt->fetchAll();
}
