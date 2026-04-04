<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$canApprove = in_array($u['role'], ['Admin', 'HR Officer', 'Manager']);
$actionFailed = false;

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Handle approve/reject (POST + CSRF)
if(is_post() && $canApprove && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if(in_array($action, ['approved', 'rejected', 'cancelled'])) {
        // Fetch current leave request before updating
        if($hasSaas && $cid) {
            $leaveReq = $pdo->prepare("SELECT lr.*, lt.is_paid FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.id = ? AND lr.company_id = ?");
            $leaveReq->execute([$id, $cid]);
        } else {
            $leaveReq = $pdo->prepare("SELECT lr.*, lt.is_paid FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.id = ?");
            $leaveReq->execute([$id]);
        }
        $leaveData = $leaveReq->fetch();

        if($hasSaas && $cid) {
            if ($action === 'cancelled') {
                $st = $pdo->prepare("UPDATE leave_requests SET status='rejected', cancelled_at=NOW(), cancelled_by=? WHERE id=? AND company_id=?");
                $st->execute([$u['id'], $id, $cid]);
            } else {
                $st = $pdo->prepare("UPDATE leave_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND company_id=?");
                $st->execute([$action, $u['id'], $id, $cid]);
            }
        } else {
            $st = $pdo->prepare("UPDATE leave_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=?");
            $st->execute([$action, $u['id'], $id]);
        }

        // Auto-update leave balance
        if ($leaveData && $st->rowCount() > 0) {
            $leaveDays = (int) ceil((strtotime($leaveData['end_date']) - strtotime($leaveData['start_date'])) / 86400) + 1;
            $year = (int) date('Y', strtotime($leaveData['start_date']));
            $empId = $leaveData['employee_id'];
            $ltId  = $leaveData['leave_type_id'];
            $prevStatus = $leaveData['status'];

            if ($action === 'approved' && $prevStatus === 'pending') {
                // Deduct days from leave balance
                $pdo->prepare("UPDATE employee_leave_balance SET used = used + ? WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $cid, $empId, $ltId, $year]);
            } elseif ($action === 'rejected' && $prevStatus === 'approved') {
                // Revert previously approved leave
                $pdo->prepare("UPDATE employee_leave_balance SET used = GREATEST(0, used - ?) WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$leaveDays, $cid, $empId, $ltId, $year]);
            }
            if ($action === 'approved') {
                $usr = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1");
                $usr->execute([$leaveData['employee_id']]);
                if ($x = $usr->fetch()) notify((int)$x['id'], 'leave', 'Leave Approved', 'Your leave request has been approved.', '/hrms/modules/leaves/index.php');
            } elseif ($action === 'rejected' || $action === 'cancelled') {
                $usr = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1");
                $usr->execute([$leaveData['employee_id']]);
                if ($x = $usr->fetch()) notify((int)$x['id'], 'leave', 'Leave Rejected/Cancelled', 'Your leave request was rejected or cancelled.', '/hrms/modules/leaves/index.php');
            }
            log_activity($action, 'leave_request', $id, ['employee_id' => $leaveData['employee_id']]);
        }

        header("Location: index.php?msg=$action"); exit;
    }
    $actionFailed = true;
}

// Get leave types
if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT * FROM leave_types WHERE company_id=? ORDER BY name");
    $st->execute([$cid]); $leaveTypes = $st->fetchAll();
} else {
    $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
}

// Get requests
if($canApprove) {
    if($hasSaas && $cid) {
        $st = $pdo->prepare("SELECT lr.*, lt.name as leave_type, e.first_name, e.last_name 
            FROM leave_requests lr 
            JOIN leave_types lt ON lt.id=lr.leave_type_id 
            JOIN employees e ON e.id=lr.employee_id 
            WHERE lr.company_id=?
            ORDER BY lr.status='pending' DESC, lr.created_at DESC");
        $st->execute([$cid]); $rows = $st->fetchAll();
    } else {
        $rows = $pdo->query("SELECT lr.*, lt.name as leave_type, e.first_name, e.last_name 
            FROM leave_requests lr 
            JOIN leave_types lt ON lt.id=lr.leave_type_id 
            JOIN employees e ON e.id=lr.employee_id 
            ORDER BY lr.status='pending' DESC, lr.created_at DESC")->fetchAll();
    }
} else {
    $st = $pdo->prepare("SELECT lr.*, lt.name as leave_type, e.first_name, e.last_name 
        FROM leave_requests lr 
        JOIN leave_types lt ON lt.id=lr.leave_type_id 
        JOIN employees e ON e.id=lr.employee_id 
        WHERE lr.employee_id=? 
        ORDER BY lr.created_at DESC");
    $st->execute([$u['employee_id']]);
    $rows = $st->fetchAll();
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calendar-check me-2"></i>Leave Requests</h4>
    <div class="d-flex gap-2">
        <a href="calendar.php" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-2"></i>Calendar</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-2"></i>Request Leave
        </button>
    </div>
</div>

<?php if(isset($_GET['msg']) || $actionFailed): ?>
<div class="alert <?= $actionFailed ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <?php 
    if($actionFailed) {
        echo 'Unable to process leave action.';
    } else {
        $msgs = ['added'=>'Leave request submitted!', 'approved'=>'Leave approved!', 'rejected'=>'Leave rejected!', 'cancelled' => 'Leave cancelled!'];
        echo $msgs[$_GET['msg']] ?? 'Done!';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Attachment</th>
                    <th>Status</th>
                    <?php if($canApprove): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No leave requests found</td></tr>
            <?php else: foreach($rows as $r): 
                $days = (strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400 + 1;
            ?>
                <tr>
                    <td><i class="bi bi-person-circle me-2 text-muted"></i><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                    <td><span class="badge bg-info"><?= e($r['leave_type']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($r['start_date'])) ?></td>
                    <td><?= date('M j, Y', strtotime($r['end_date'])) ?></td>
                    <td><?= $days ?></td>
                    <td>
                        <?php if(!empty($r['attachment_path'])): ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= BASE_URL . e($r['attachment_path']) ?>"><i class="bi bi-paperclip"></i></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['status'] === 'pending'): ?>
                            <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                        <?php elseif($r['status'] === 'approved'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                        <?php endif; ?>
                    </td>
                    <?php if($canApprove): ?>
                    <td class="action-btns">
                        <?php if($r['status'] === 'pending'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Approve this leave?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="approved">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Reject this leave?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="rejected">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Cancel this leave?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="cancelled">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-slash-circle"></i></button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Request Leave Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="add.php" enctype="multipart/form-data">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Request Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Leave Type</label>
                        <select class="form-select" name="leave_type_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= e($lt['name']) ?> (<?= $lt['days_allowed'] ?> days/year)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional reason..."></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Supporting Document (optional)</label>
                        <input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
