<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u   = $_SESSION['user'];
$cid = company_id() ?? 1;

// Determine which employee's breaks to show
$isAdmin = in_array($u['role'], ['Admin', 'HR Officer', 'Manager']);
$employeeId = null;

if ($u['employee_id']) {
    $employeeId = (int)$u['employee_id'];
}

// Admins can view/manage any employee
$viewEmpId = (int)($_GET['employee_id'] ?? $employeeId);
if ($isAdmin && $viewEmpId > 0) {
    $employeeId = $viewEmpId;
}

if (!$employeeId) {
    redirect('/modules/dashboard.php');
}

// Get today's attendance record
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

$attendanceSt = $pdo->prepare("SELECT * FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ?");
$attendanceSt->execute([$cid, $employeeId, $today]);
$todayAttendance = $attendanceSt->fetch();

// Handle break start / end
if (is_post() && verify_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'break_start' && $todayAttendance) {
        // Check there isn't an open break already
        $openBreak = $pdo->prepare("SELECT id FROM break_records WHERE attendance_id = ? AND break_end IS NULL");
        $openBreak->execute([$todayAttendance['id']]);
        if ($openBreak->fetch()) {
            $error = 'You already have an open break. Please end it first.';
        } else {
            $pdo->prepare("INSERT INTO break_records (company_id, employee_id, attendance_id, break_start) VALUES (?, ?, ?, ?)")
                ->execute([$cid, $employeeId, $todayAttendance['id'], $now]);
            header("Location: breaks.php?employee_id=$employeeId&msg=break_started"); exit;
        }
    }

    if ($action === 'break_end') {
        $breakId = (int)($_POST['break_id'] ?? 0);
        $breakSt = $pdo->prepare("SELECT * FROM break_records WHERE id = ? AND company_id = ? AND employee_id = ?");
        $breakSt->execute([$breakId, $cid, $employeeId]);
        $openBreak = $breakSt->fetch();
        if ($openBreak && !$openBreak['break_end']) {
            $startTs   = strtotime($openBreak['break_start']);
            $duration  = (int)round((time() - $startTs) / 60);
            $pdo->prepare("UPDATE break_records SET break_end = ?, duration_minutes = ? WHERE id = ?")
                ->execute([$now, $duration, $breakId]);
            header("Location: breaks.php?employee_id=$employeeId&msg=break_ended"); exit;
        } else {
            $error = 'Break record not found or already ended.';
        }
    }
}

// Get employee info
$empSt = $pdo->prepare("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ? AND e.company_id = ?");
$empSt->execute([$employeeId, $cid]);
$employee = $empSt->fetch();

if (!$employee) {
    redirect('/modules/attendance/index.php');
}

// Get today's breaks
$breaksSt = $pdo->prepare("SELECT * FROM break_records WHERE company_id = ? AND employee_id = ? AND DATE(break_start) = ? ORDER BY break_start ASC");
$breaksSt->execute([$cid, $employeeId, $today]);
$todayBreaks = $breaksSt->fetchAll();

$openBreakRecord = null;
$totalBreakMin   = 0;
foreach ($todayBreaks as $br) {
    if (!$br['break_end']) {
        $openBreakRecord = $br;
    } else {
        $totalBreakMin += (int)$br['duration_minutes'];
    }
}

// Get all employees for admin dropdown
$allEmployees = [];
if ($isAdmin) {
    $allEmpSt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE company_id = ? AND status = 'active' ORDER BY last_name, first_name");
    $allEmpSt->execute([$cid]);
    $allEmployees = $allEmpSt->fetchAll();
}
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-cup-hot me-2"></i>Break Tracking</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Attendance</a>
</div>

<?php if (isset($_GET['msg'])): ?>
<?php $msg = $_GET['msg'] ?? ''; ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <?= $msg === 'break_started' ? 'Break started!' : ($msg === 'break_ended' ? 'Break ended!' : 'Done!') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($isAdmin && !empty($allEmployees)): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 me-2 text-nowrap">View Employee:</label>
            <select class="form-select" name="employee_id" onchange="this.form.submit()">
                <?php foreach ($allEmployees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employeeId === (int)$emp['id'] ? 'selected' : '' ?>>
                    <?= e($emp['first_name'].' '.$emp['last_name']) ?> (<?= e($emp['employee_code']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: Status & Actions -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i><?= e($employee['first_name'].' '.$employee['last_name']) ?></h6>
                <small class="text-muted"><?= e($employee['dept_name'] ?? '') ?></small>
            </div>
            <div class="card-body text-center py-4">
                <?php if (!$todayAttendance): ?>
                    <div class="text-muted">
                        <i class="bi bi-clock fs-1 d-block mb-2"></i>
                        Not yet timed in today
                    </div>
                <?php elseif ($openBreakRecord): ?>
                    <div class="text-warning mb-3">
                        <i class="bi bi-cup-hot fs-1 d-block mb-2"></i>
                        <strong>On Break</strong>
                        <div class="small text-muted mt-1">Since <?= date('h:i A', strtotime($openBreakRecord['break_start'])) ?></div>
                    </div>
                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="break_end">
                        <input type="hidden" name="break_id" value="<?= $openBreakRecord['id'] ?>">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-play-fill me-2"></i>End Break
                        </button>
                    </form>
                <?php elseif ($todayAttendance['time_out']): ?>
                    <div class="text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                        Already timed out today
                    </div>
                <?php else: ?>
                    <div class="text-success mb-3">
                        <i class="bi bi-person-workspace fs-1 d-block mb-2"></i>
                        <strong>Working</strong>
                        <div class="small text-muted mt-1">Timed in at <?= date('h:i A', strtotime($todayAttendance['time_in'])) ?></div>
                    </div>
                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="break_start">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cup-hot me-2"></i>Start Break
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center text-muted small">
                Total break time today:
                <strong><?= $totalBreakMin ?> min</strong>
                <?php if ($openBreakRecord): ?>
                    <span class="text-warning"> + ongoing</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Today's Break Log -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Today's Breaks</h6>
                <span class="badge bg-primary"><?= count($todayBreaks) ?> break<?= count($todayBreaks) !== 1 ? 's' : '' ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todayBreaks)): ?>
                <div class="empty-state p-4 text-center text-muted">
                    <i class="bi bi-inbox"></i>
                    <p>No breaks recorded today</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayBreaks as $i => $br): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= date('h:i A', strtotime($br['break_start'])) ?></td>
                                <td>
                                    <?php if ($br['break_end']): ?>
                                        <?= date('h:i A', strtotime($br['break_end'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($br['duration_minutes'] !== null): ?>
                                        <?= $br['duration_minutes'] ?> min
                                    <?php elseif (!$br['break_end']): ?>
                                        <span class="text-warning" id="live-timer" data-start="<?= strtotime($br['break_start']) ?>">...</span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($br['break_end']): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">In Progress</span>
                                    <?php endif; ?>
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
</div>

<script>
// Live timer for ongoing break
const timerEl = document.getElementById('live-timer');
if (timerEl) {
    const startEpoch = parseInt(timerEl.dataset.start, 10);
    function updateTimer() {
        const elapsed = Math.floor(Date.now() / 1000) - startEpoch;
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        timerEl.textContent = mins + 'm ' + String(secs).padStart(2, '0') + 's';
    }
    updateTimer();
    setInterval(updateTimer, 1000);
}
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
