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
    if(in_array($action, ['approved', 'rejected'])) {
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
            $st = $pdo->prepare("UPDATE leave_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND company_id=?");
            $st->execute([$action, $u['id'], $id, $cid]);
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
                if ($hasSaas && $cid) {
                    $pdo->prepare("UPDATE employee_leave_balance SET used = used + ? WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                        ->execute([$leaveDays, $cid, $empId, $ltId, $year]);
                } else {
                    $pdo->prepare("UPDATE employee_leave_balance SET used = used + ? WHERE employee_id = ? AND leave_type_id = ? AND year = ?")
                        ->execute([$leaveDays, $empId, $ltId, $year]);
                }
            } elseif ($action === 'rejected' && $prevStatus === 'approved') {
                // Revert previously approved leave
                if ($hasSaas && $cid) {
                    $pdo->prepare("UPDATE employee_leave_balance SET used = GREATEST(0, used - ?) WHERE company_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?")
                        ->execute([$leaveDays, $cid, $empId, $ltId, $year]);
                } else {
                    $pdo->prepare("UPDATE employee_leave_balance SET used = GREATEST(0, used - ?) WHERE employee_id = ? AND leave_type_id = ? AND year = ?")
                        ->execute([$leaveDays, $empId, $ltId, $year]);
                }
            }
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
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-2"></i>Request Leave
    </button>
</div>

<?php if(isset($_GET['msg']) || $actionFailed): ?>
<div class="alert <?= $actionFailed ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
    <i class="bi bi-<?= $actionFailed ? 'exclamation-circle' : 'check-circle' ?> me-2"></i>
    <?php 
    if($actionFailed) {
        echo 'Unable to process leave action.';
    } else {
        $msgs = ['added'=>'Leave request submitted!', 'approved'=>'Leave approved!', 'rejected'=>'Leave rejected!'];
        echo $msgs[$_GET['msg']] ?? 'Done!';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="input-group" style="max-width:300px;">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="leaveSearch" class="form-control border-start-0 ps-0" placeholder="Search employee or type…">
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select id="leaveStatusFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <span class="badge bg-primary table-count-badge" id="leaveCount"><?= count($rows) ?> total</span>
        </div>
    </div>

    <div class="table-responsive-wrapper">
        <table class="table table-hover mb-0" id="leaveTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Period</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <?php if($canApprove): ?><th class="text-center">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-25"></i>No leave requests found
                </td></tr>
            <?php else: foreach($rows as $r): 
                $days = (int)round((strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400) + 1;
            ?>
                <tr data-status="<?= e($r['status']) ?>"
                    data-search="<?= strtolower(e($r['first_name'].' '.$r['last_name'].' '.$r['leave_type'])) ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm" style="background:linear-gradient(135deg,#0d9488,#06b6d4);">
                                <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                            </div>
                            <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                        </div>
                    </td>
                    <td><span class="badge bg-info bg-opacity-75 text-dark"><?= e($r['leave_type']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= date('M j', strtotime($r['start_date'])) ?> – <?= date('M j, Y', strtotime($r['end_date'])) ?></div>
                        <small class="text-muted">Filed: <?= date('M j, Y', strtotime($r['created_at'])) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= $days ?> day<?= $days != 1 ? 's' : '' ?></span>
                    </td>
                    <td>
                        <?php if(!empty($r['reason'])): ?>
                            <span class="d-inline-block text-truncate" style="max-width:160px;" title="<?= e($r['reason']) ?>"><?= e($r['reason']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                        <?php elseif($r['status'] === 'approved'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                        <?php endif; ?>
                    </td>
                    <?php if($canApprove): ?>
                    <td class="action-btns text-center">
                        <?php if($r['status'] === 'pending'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Approve this leave request?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="approved">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Approve" data-bs-toggle="tooltip">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Reject this leave request?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="rejected">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Reject" data-bs-toggle="tooltip">
                                <i class="bi bi-x-lg me-1"></i>Reject
                            </button>
                        </form>
                        <?php elseif($r['status'] === 'approved'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Revoke this approved leave?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="rejected">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke Approval" data-bs-toggle="tooltip">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Revoke
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Re-approve this leave request?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="approved">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Re-approve" data-bs-toggle="tooltip">
                                <i class="bi bi-check-circle me-1"></i>Re-approve
                            </button>
                        </form>
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
            <form method="post" action="add.php">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Request Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Leave Type</label>
                        <select class="form-select" name="leave_type_id" required>
                            <option value="">— Select Leave Type —</option>
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
                        <label class="form-label">Reason <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Brief reason for your leave…"></textarea>
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

<script>
const leaveSearch = document.getElementById('leaveSearch');
const leaveFilter = document.getElementById('leaveStatusFilter');
const leaveCount  = document.getElementById('leaveCount');

function filterLeave() {
    const q = leaveSearch.value.toLowerCase();
    const s = leaveFilter.value;
    let v = 0;
    document.querySelectorAll('#leaveTable tbody tr[data-status]').forEach(row => {
        const ms = !q || row.dataset.search.includes(q);
        const mf = !s || row.dataset.status === s;
        row.style.display = (ms && mf) ? '' : 'none';
        if (ms && mf) v++;
    });
    leaveCount.textContent = v + ' total';
}
if (leaveSearch) leaveSearch.addEventListener('input',  filterLeave);
if (leaveFilter) leaveFilter.addEventListener('change', filterLeave);

// Bootstrap tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
