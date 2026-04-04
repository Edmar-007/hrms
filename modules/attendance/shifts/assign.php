<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$shiftId = (int)($_GET['shift_id'] ?? 0);

// Get shift info
$shift = $pdo->prepare("SELECT * FROM shifts WHERE id = ? AND company_id = ?");
$shift->execute([$shiftId, $cid]);
$shiftInfo = $shift->fetch();

if (!$shiftInfo) {
    redirect('/modules/attendance/shifts/index.php?error=not_found');
}

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'assign' && verify_csrf($_POST['csrf'] ?? '')) {
        $employeeId = (int)$_POST['employee_id'];
        $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-d');

        if ($employeeId > 0) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO shift_assignments (company_id, employee_id, shift_id, effective_from)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    effective_from = VALUES(effective_from), effective_to = NULL
                ");
                $st->execute([$cid, $employeeId, $shiftId, $effectiveFrom]);
                log_activity('assign', 'shift_assignments', $pdo->lastInsertId(), ['shift_id' => $shiftId, 'emp_id' => $employeeId]);
                redirect("?shift_id=$shiftId&msg=assigned");
            } catch (PDOException $e) {
                $error = 'Error assigning shift';
            }
        }
    }

    if ($action === 'remove' && verify_csrf($_POST['csrf'] ?? '')) {
        $assignmentId = (int)$_POST['assignment_id'];
        $st = $pdo->prepare("
            DELETE FROM shift_assignments WHERE id = ? AND shift_id = ? AND company_id = ?
        ");
        $st->execute([$assignmentId, $shiftId, $cid]);
        redirect("?shift_id=$shiftId&msg=removed");
    }

    if ($action === 'end' && verify_csrf($_POST['csrf'] ?? '')) {
        $assignmentId = (int)$_POST['assignment_id'];
        $effectiveTo = $_POST['effective_to'] ?? date('Y-m-d');
        $st = $pdo->prepare("
            UPDATE shift_assignments SET effective_to = ? WHERE id = ? AND shift_id = ? AND company_id = ?
        ");
        $st->execute([$effectiveTo, $assignmentId, $shiftId, $cid]);
        redirect("?shift_id=$shiftId&msg=updated");
    }
}

// Get current assignments
$assignments = $pdo->prepare("
    SELECT sa.*, e.first_name, e.last_name, e.employee_code
    FROM shift_assignments sa
    JOIN employees e ON e.id = sa.employee_id
    WHERE sa.shift_id = ? AND sa.company_id = ?
    ORDER BY sa.effective_from DESC
");
$assignments->execute([$shiftId, $cid]);
$currentAssignments = $assignments->fetchAll();

// Get available employees (not assigned to this shift on today's date)
$today = date('Y-m-d');
$available = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.employee_code, d.name as dept_name
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.company_id = ? AND e.status = 'active'
    AND e.id NOT IN (
        SELECT DISTINCT employee_id FROM shift_assignments
        WHERE shift_id = ? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
    )
    ORDER BY e.last_name, e.first_name
");
$available->execute([$cid, $shiftId, $today, $today]);
$availableEmployees = $available->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-people me-2"></i>Assign Employees to Shift</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem">
            Shift: <strong><?= e($shiftInfo['name']) ?></strong>
            (<?= date('h:i A', strtotime($shiftInfo['start_time'])) ?> - <?= date('h:i A', strtotime($shiftInfo['end_time'])) ?>)
        </p>
    </div>
    <a href="/modules/attendance/shifts/index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'assigned' ? 'Employee assigned successfully!' : $_GET['msg'] === 'updated' ? 'Updated successfully!' : $_GET['msg'] === 'removed' ? 'Assignment removed!' : '' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Current Assignments -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Currently Assigned Employees (<?= count($currentAssignments) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($currentAssignments)): ?>
                <div class="empty-state p-4 text-center text-muted">
                    <i class="bi bi-inbox"></i>
                    <p>No employees assigned to this shift yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employee ID</th>
                                <th>Effective From</th>
                                <th>Effective To</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentAssignments as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong>
                                </td>
                                <td><small class="text-muted"><?= e($a['employee_code']) ?></small></td>
                                <td><?= date('M d, Y', strtotime($a['effective_from'])) ?></td>
                                <td>
                                    <?php if ($a['effective_to']): ?>
                                        <span class="text-muted"><?= date('M d, Y', strtotime($a['effective_to'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$a['effective_to']): ?>
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#endModal<?= $a['id'] ?>" title="End Assignment">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;margin-left:8px" onsubmit="return confirm('Remove this assignment?')">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Assign New Employee -->
        <?php if (!empty($availableEmployees)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Add Employee</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="assign">

                    <div class="mb-3">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($availableEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= e($emp['first_name'].' '.$emp['last_name']) ?> (<?= e($emp['employee_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Effective From</label>
                        <input type="date" class="form-control" name="effective_from" value="<?= date('Y-m-d') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-2"></i>Add Employee
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            All active employees are already assigned to this shift.
        </div>
        <?php endif; ?>

        <!-- Shift Info Card -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Shift Details</h6>
            </div>
            <div class="card-body small">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">Start Time:</td>
                        <td class="fw-bold"><?= date('h:i A', strtotime($shiftInfo['start_time'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">End Time:</td>
                        <td class="fw-bold"><?= date('h:i A', strtotime($shiftInfo['end_time'])) ?></td>
                    </tr>
                    <?php if ($shiftInfo['lunch_start']): ?>
                    <tr>
                        <td class="text-muted">Lunch Period:</td>
                        <td class="fw-bold"><?= date('h:i', strtotime($shiftInfo['lunch_start'])) ?> - <?= date('h:i', strtotime($shiftInfo['lunch_end'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Break Duration:</td>
                        <td class="fw-bold"><?= $shiftInfo['total_break_minutes'] ?> minutes</td>
                    </tr>
                    <tr class="table-light">
                        <td class="text-muted">Work Hours:</td>
                        <td class="fw-bold">
                            <?php
                            $start = strtotime($shiftInfo['start_time']);
                            $end = strtotime($shiftInfo['end_time']);
                            if ($end < $start) $end = strtotime('+1 day', $end);
                            $hours = ($end - $start) / 3600 - ($shiftInfo['total_break_minutes'] / 60);
                            ?>
                            <?= number_format($hours, 1) ?> hours
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- End Assignment Modals -->
<?php foreach ($currentAssignments as $a): if (!$a['effective_to']): ?>
<div class="modal fade" id="endModal<?= $a['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">End Shift Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>End assignment for <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong></p>

                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="end">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Effective To Date</label>
                        <input type="date" class="form-control" name="effective_to" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">End Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; endforeach; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
