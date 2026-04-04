<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'create' && verify_csrf($_POST['csrf'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $lunchStart = trim($_POST['lunch_start'] ?? '');
        $lunchEnd = trim($_POST['lunch_end'] ?? '');
        $breakMin = (int)($_POST['break_minutes'] ?? 60);

        if (!empty($name) && !empty($startTime) && !empty($endTime)) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO shifts (company_id, name, start_time, end_time, lunch_start, lunch_end, total_break_minutes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $st->execute([$cid, $name, $startTime, $endTime, $lunchStart ?: null, $lunchEnd ?: null, $breakMin]);
                log_activity('create', 'shifts', $pdo->lastInsertId(), ['name' => $name]);
                redirect('/modules/attendance/shifts/index.php?msg=created');
            } catch (PDOException $e) {
                $error = 'Error creating shift';
            }
        }
    }

    if ($action === 'update' && verify_csrf($_POST['csrf'] ?? '')) {
        $shiftId = (int)$_POST['shift_id'];
        $name = trim($_POST['name'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $lunchStart = trim($_POST['lunch_start'] ?? '');
        $lunchEnd = trim($_POST['lunch_end'] ?? '');
        $breakMin = (int)($_POST['break_minutes'] ?? 60);

        $st = $pdo->prepare("
            UPDATE shifts
            SET name = ?, start_time = ?, end_time = ?, lunch_start = ?, lunch_end = ?, total_break_minutes = ?
            WHERE id = ? AND company_id = ?
        ");
        $st->execute([$name, $startTime, $endTime, $lunchStart ?: null, $lunchEnd ?: null, $breakMin, $shiftId, $cid]);
        log_activity('update', 'shifts', $shiftId, ['name' => $name]);
        redirect('/modules/attendance/shifts/index.php?msg=updated');
    }

    if ($action === 'toggle' && verify_csrf($_POST['csrf'] ?? '')) {
        $shiftId = (int)$_POST['shift_id'];
        $st = $pdo->prepare("UPDATE shifts SET is_active = NOT is_active WHERE id = ? AND company_id = ?");
        $st->execute([$shiftId, $cid]);
        redirect('/modules/attendance/shifts/index.php?msg=updated');
    }
}

// Get all shifts
$shifts = $pdo->prepare("
    SELECT s.*,
           COUNT(DISTINCT sa.employee_id) as emp_count
    FROM shifts s
    LEFT JOIN shift_assignments sa ON sa.shift_id = s.id
    WHERE s.company_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$shifts->execute([$cid]);
$allShifts = $shifts->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calendar-event me-2"></i>Work Shifts</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-2"></i>New Shift
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'created' ? 'Shift created successfully!' : $_GET['msg'] === 'updated' ? 'Updated successfully!' : '' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($allShifts)): ?>
                <div class="empty-state p-4 text-center">
                    <i class="bi bi-inbox"></i>
                    <p>No shifts defined yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Shift Name</th>
                                <th>Time</th>
                                <th>Break</th>
                                <th>Employees</th>
                                <th>Status</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allShifts as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= e($s['name']) ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?>
                                </td>
                                <td>
                                    <?php if ($s['lunch_start']): ?>
                                    <small><?= date('h:i', strtotime($s['lunch_start'])) ?>-<?= date('h:i', strtotime($s['lunch_end'])) ?></small>
                                    <br>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-dark"><?= $s['total_break_minutes'] ?> min</span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $s['emp_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="/modules/attendance/shifts/assign.php?shift_id=<?= $s['id'] ?>" class="btn btn-sm btn-link p-0" title="Assign Employees" style="margin-left:8px">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    <form method="POST" style="display:inline;margin-left:8px">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link p-0" title="Toggle Status">
                                            <i class="bi bi-<?= $s['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Quick Add Shift</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Shift Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., Morning Shift" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">End Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>

                    <h6 class="mt-4 mb-3">Break Period</h6>

                    <div class="mb-3">
                        <label class="form-label">Lunch Start (Optional)</label>
                        <input type="time" class="form-control" name="lunch_start">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lunch End (Optional)</label>
                        <input type="time" class="form-control" name="lunch_end">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Break Duration (minutes)</label>
                        <input type="number" class="form-control" name="break_minutes" value="60">
                        <small class="text-muted">Lunch + other breaks</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-2"></i>Create Shift
                    </button>
                </form>
            </div>
        </div>

        <!-- Common Shifts -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Common Shifts</h6>
            </div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li><strong>Morning:</strong> 8:00 AM - 5:00 PM</li>
                    <li><strong>Afternoon:</strong> 12:00 PM - 9:00 PM</li>
                    <li><strong>Evening:</strong> 3:00 PM - 12:00 AM</li>
                    <li><strong>Night:</strong> 10:00 PM - 7:00 AM</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Same form as card above -->
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modals -->
<?php foreach ($allShifts as $s): ?>
<div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Shift Name</label>
                        <input type="text" class="form-control" name="name" value="<?= e($s['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" value="<?= $s['start_time'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" value="<?= $s['end_time'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lunch Start</label>
                        <input type="time" class="form-control" name="lunch_start" value="<?= $s['lunch_start'] ?? '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lunch End</label>
                        <input type="time" class="form-control" name="lunch_end" value="<?= $s['lunch_end'] ?? '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Break Duration (minutes)</label>
                        <input type="number" class="form-control" name="break_minutes" value="<?= $s['total_break_minutes'] ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
