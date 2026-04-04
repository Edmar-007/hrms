<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$isAdmin = in_array($u['role'], ['Admin', 'HR Officer', 'Manager']);

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Check if already timed in today
$todayRecord = null;
$empId = $u['employee_id'] ?? null;
if($empId) {
    $st = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? AND date=CURDATE()");
    $st->execute([$empId]);
    $todayRecord = $st->fetch();
}

if($isAdmin) {
    if($hasSaas && $cid) {
        $st = $pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_code FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.company_id=? ORDER BY a.date DESC, a.time_in DESC LIMIT 100");
        $st->execute([$cid]); $rows = $st->fetchAll();
    } else {
        $rows = $pdo->query("SELECT a.*,e.first_name,e.last_name,e.employee_code FROM attendance a JOIN employees e ON e.id=a.employee_id ORDER BY a.date DESC, a.time_in DESC LIMIT 100")->fetchAll();
    }
} else {
    if($empId) {
        $st = $pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_code FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.employee_id=? ORDER BY a.date DESC");
        $st->execute([$empId]);
        $rows = $st->fetchAll();
    } else {
        $rows = [];
    }
}
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-qr-code-scan me-2"></i>Attendance</h4>
    <div class="d-flex gap-2 flex-wrap">
        <?php if($isAdmin): ?>
        <a href="scanner.php" class="btn btn-primary"><i class="bi bi-qr-code-scan me-2"></i>QR Scanner</a>
        <a href="breaks.php" class="btn btn-outline-secondary"><i class="bi bi-cup-hot me-2"></i>Break Tracking</a>
        <a href="correct.php" class="btn btn-outline-primary"><i class="bi bi-pencil-square me-2"></i>Corrections</a>
        <?php endif; ?>
        <?php if($empId): ?>
            <?php if(!$todayRecord): ?>
                <a class="btn btn-success" href="time_in.php"><i class="bi bi-box-arrow-in-right me-2"></i>Manual Time In</a>
            <?php elseif(!$todayRecord['time_out']): ?>
                <a class="btn btn-warning" href="time_out.php"><i class="bi bi-box-arrow-right me-2"></i>Manual Time Out</a>
                <a class="btn btn-outline-secondary" href="breaks.php"><i class="bi bi-cup-hot me-2"></i>Breaks</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if(!$empId && !$isAdmin): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Your user account is not linked to an employee profile. Please contact HR to link your account.
</div>
<?php endif; ?>

<?php if(isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'in' ? 'Time In recorded successfully!' : 'Time Out recorded successfully!' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if($todayRecord): ?>
<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Today's Status:</strong> 
    Timed in at <?= date('h:i A', strtotime($todayRecord['time_in'])) ?>
    <?php if($todayRecord['time_out']): ?>
        — Timed out at <?= date('h:i A', strtotime($todayRecord['time_out'])) ?>
    <?php else: ?>
        — <span class="fw-semibold text-success">Currently working</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="input-group" style="max-width:300px;">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="attSearch" class="form-control border-start-0 ps-0" placeholder="Search employee or code…">
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select id="attStatusFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Status</option>
                <option value="complete">Complete</option>
                <option value="working">Working</option>
            </select>
            <span class="badge bg-primary table-count-badge" id="attCount"><?= count($rows) ?> records</span>
        </div>
    </div>

    <div class="table-responsive-wrapper">
        <table class="table table-hover mb-0" id="attTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-clock-history fs-2 d-block mb-2 opacity-25"></i>No attendance records found
                </td></tr>
            <?php else: foreach($rows as $r): 
                $hours = '';
                $durMin = 0;
                if($r['time_in'] && $r['time_out']) {
                    $diff = strtotime($r['time_out']) - strtotime($r['time_in']);
                    $durMin = round($diff / 60);
                    $h = floor($durMin / 60);
                    $m = $durMin % 60;
                    $hours = $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
                }
                $statusKey = $r['time_out'] ? 'complete' : 'working';
            ?>
                <tr data-status="<?= $statusKey ?>"
                    data-search="<?= strtolower(e($r['first_name'].' '.$r['last_name'].' '.$r['employee_code'])) ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                                <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                                <small class="text-muted"><code><?= e($r['employee_code']) ?></code></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= date('M j, Y', strtotime($r['date'])) ?></div>
                        <small class="text-muted"><?= date('l', strtotime($r['date'])) ?></small>
                    </td>
                    <td>
                        <?php if($r['time_in']): ?>
                            <span class="text-success fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i><?= date('h:i A', strtotime($r['time_in'])) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['time_out']): ?>
                            <span class="text-warning fw-semibold"><i class="bi bi-box-arrow-right me-1"></i><?= date('h:i A', strtotime($r['time_out'])) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($hours): ?>
                            <span class="badge bg-info bg-opacity-75 text-dark">
                                <i class="bi bi-clock me-1"></i><?= $hours ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['time_out']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Working</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-btns text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                data-bs-toggle="modal" data-bs-target="#viewAttModal<?= (int)$r['id'] ?>" 
                                title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if($isAdmin): ?>
                        <a href="../employees/qrcode.php?id=<?= (int)$r['employee_id'] ?>" class="btn btn-sm btn-primary" title="View QR Code">
                            <i class="bi bi-qr-code"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Attendance Modals -->
<?php if(!empty($rows)): foreach($rows as $r): 
    $hours = '';
    if($r['time_in'] && $r['time_out']) {
        $diff = strtotime($r['time_out']) - strtotime($r['time_in']);
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        $hours = $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
    }
?>
<div class="modal fade" id="viewAttModal<?= (int)$r['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="avatar-sm" style="width:50px;height:50px;font-size:1.1rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                        <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                    </div>
                    <div>
                        <h5 class="mb-0"><?= e($r['first_name'].' '.$r['last_name']) ?></h5>
                        <code><?= e($r['employee_code']) ?></code>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Date</label>
                        <div class="fw-semibold"><?= date('F j, Y', strtotime($r['date'])) ?></div>
                        <small class="text-muted"><?= date('l', strtotime($r['date'])) ?></small>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Status</label>
                        <div>
                            <?php if($r['time_out']): ?>
                                <span class="badge bg-success">Complete</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Working</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Time In</label>
                        <div class="fw-semibold text-success"><?= $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '—' ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Time Out</label>
                        <div class="fw-semibold text-warning"><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—' ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Duration</label>
                        <div class="fw-semibold"><?= $hours ?: 'Still working...' ?></div>
                    </div>
                    <?php if(!empty($r['notes'])): ?>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Notes</label>
                        <div class="p-3 bg-light rounded"><?= e($r['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

<script>
const attSearch = document.getElementById('attSearch');
const attFilter = document.getElementById('attStatusFilter');
const attCount  = document.getElementById('attCount');

function filterAtt() {
    const q = attSearch.value.toLowerCase();
    const s = attFilter.value;
    let v = 0;
    document.querySelectorAll('#attTable tbody tr[data-status]').forEach(row => {
        const ms = !q || row.dataset.search.includes(q);
        const mf = !s || row.dataset.status === s;
        row.style.display = (ms && mf) ? '' : 'none';
        if (ms && mf) v++;
    });
    attCount.textContent = v + ' records';
}
if (attSearch) attSearch.addEventListener('input',  filterAtt);
if (attFilter) attFilter.addEventListener('change', filterAtt);
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
