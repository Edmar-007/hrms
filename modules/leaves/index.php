<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/leave-handler.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$canApprove = in_array($u['role'], ['Admin', 'HR Officer', 'Manager']);
$actionFailed = false;
$permissionDenied = false;

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Handle approve/reject (POST + CSRF)
if(is_post() && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if(in_array($action, ['approved', 'rejected', 'cancelled'])) {
        if (!$canApprove) {
            $permissionDenied = true;
            $actionFailed = true;
        } else {
            // Use helper function for cleaner code
            $result = process_leave_action($pdo, $id, $action, $u, $cid, $hasSaas);
            if ($result['success'] && $result['data']) {
                update_leave_balance($pdo, $result['data'], $action, $cid, $hasSaas);
                send_leave_notification($pdo, $result['data'], $action);
                log_activity($action, 'leave_request', $id, ['employee_id' => $result['data']['employee_id']]);
            }
            header("Location: index.php?msg=$action"); exit;
        }
    } else {
        $actionFailed = true;
    }
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
    $empId = $u['employee_id'] ?? null;
    if($empId) {
        $st = $pdo->prepare("SELECT lr.*, lt.name as leave_type, e.first_name, e.last_name 
            FROM leave_requests lr 
            JOIN leave_types lt ON lt.id=lr.leave_type_id 
            JOIN employees e ON e.id=lr.employee_id 
            WHERE lr.employee_id=? 
            ORDER BY lr.created_at DESC");
        $st->execute([$empId]);
        $rows = $st->fetchAll();
    } else {
        $rows = [];
    }
}
$userHasEmployeeId = !empty($u['employee_id']);
// Allow Admin/HR to request on behalf of employees even if not linked
$canRequestLeave = $userHasEmployeeId || $canApprove;
$employees = $canApprove ? get_company_employees($pdo, $cid, $hasSaas) : [];
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calendar-check me-2"></i>Leave Requests</h4>
    <div class="d-flex gap-2">
        <a href="calendar.php" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-2"></i>Calendar</a>
        <?php if($canRequestLeave): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-2"></i>Request Leave
        </button>
        <?php else: ?>
        <button class="btn btn-secondary" disabled title="Your account is not linked to an employee profile">
            <i class="bi bi-plus-lg me-2"></i>Request Leave
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if(!$userHasEmployeeId && !$canApprove): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Your user account is not linked to an employee profile. Please contact HR to link your account.
</div>
<?php endif; ?>

<?php if(isset($_GET['msg']) || $actionFailed): ?>
<?php $msgType = $_GET['msg'] ?? ''; ?>
<div class="alert <?= $actionFailed || $msgType === 'error' ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
    <i class="bi bi-<?= $actionFailed || $msgType === 'error' ? 'exclamation-circle' : 'check-circle' ?> me-2"></i>
    <?php 
    if($actionFailed) {
        echo $permissionDenied ? 'You do not have permission to perform this action.' : 'Unable to process leave action.';
    } elseif($msgType === 'error') {
        echo 'Unable to process leave request. Please ensure all fields are filled correctly.';
    } else {
        $msgs = ['added'=>'Leave request submitted!', 'approved'=>'Leave approved!', 'rejected'=>'Leave rejected!', 'cancelled' => 'Leave cancelled!'];
        echo $msgs[$msgType] ?? 'Done!';
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
                    <th>Attachment</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-25"></i>No leave requests found
                </td></tr>
            <?php else: foreach($rows as $r): 
                $days = calculate_leave_days($r['start_date'], $r['end_date']);
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
                    <td><span class="badge bg-info"><?= e($r['leave_type']) ?></span></td>
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
                        <?php if(!empty($r['attachment_path'])): ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= BASE_URL . e($r['attachment_path']) ?>"><i class="bi bi-paperclip"></i></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
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
                    <td class="action-btns">
                        <?php if($canApprove): ?>
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
                            <?php elseif($r['status'] === 'approved'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Revoke this approved leave?')">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="rejected">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Leave Modals -->
<?php if(!empty($rows)): foreach($rows as $r): 
    $days = calculate_leave_days($r['start_date'], $r['end_date']);
?>
<div class="modal fade" id="viewLeaveModal<?= (int)$r['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="avatar-sm" style="width:50px;height:50px;font-size:1.1rem;background:linear-gradient(135deg,#0d9488,#06b6d4);">
                        <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                    </div>
                    <div>
                        <h5 class="mb-0"><?= e($r['first_name'].' '.$r['last_name']) ?></h5>
                        <span class="badge bg-info"><?= e($r['leave_type']) ?></span>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Start Date</label>
                        <div class="fw-semibold"><?= date('F j, Y', strtotime($r['start_date'])) ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">End Date</label>
                        <div class="fw-semibold"><?= date('F j, Y', strtotime($r['end_date'])) ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Total Days</label>
                        <div class="fw-semibold"><?= $days ?> day<?= $days != 1 ? 's' : '' ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Status</label>
                        <div>
                            <?php if($r['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif($r['status'] === 'approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Reason</label>
                        <div class="p-3 bg-light rounded"><?= !empty($r['reason']) ? e($r['reason']) : '<em class="text-muted">No reason provided</em>' ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Filed On</label>
                        <div><?= date('F j, Y \a\t h:i A', strtotime($r['created_at'])) ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

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
                    <?php if($canApprove && !empty($employees)): ?>
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select class="form-select" name="employee_id">
                            <?php if($userHasEmployeeId): ?>
                            <option value="">— My Leave Request —</option>
                            <?php else: ?>
                            <option value="">— Select Employee —</option>
                            <?php endif; ?>
                            <?php foreach($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'].' '.$emp['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select an employee to request leave on their behalf.</small>
                    </div>
                    <?php endif; ?>
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

// Bootstrap tooltips – deferred so Bootstrap JS (loaded in footer) is available
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
