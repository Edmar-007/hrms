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
if($u['employee_id']) {
    $st = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? AND date=CURDATE()");
    $st->execute([$u['employee_id']]);
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
    $st = $pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_code FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.employee_id=? ORDER BY a.date DESC");
    $st->execute([$u['employee_id']]);
    $rows = $st->fetchAll();
}
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-qr-code-scan me-2"></i>Attendance</h4>
    <div class="d-flex gap-2 flex-wrap">
        <?php if($isAdmin): ?>
        <a href="scanner.php" class="btn btn-primary"><i class="bi bi-qr-code-scan me-2"></i>QR Scanner</a>
        <a href="breaks.php" class="btn btn-outline-secondary"><i class="bi bi-cup-hot me-2"></i>Break Tracking</a>
        <?php endif; ?>
        <?php if($u['employee_id']): ?>
            <?php if(!$todayRecord): ?>
                <a class="btn btn-success" href="time_in.php"><i class="bi bi-box-arrow-in-right me-2"></i>Manual Time In</a>
            <?php elseif(!$todayRecord['time_out']): ?>
                <a class="btn btn-warning" href="time_out.php"><i class="bi bi-box-arrow-right me-2"></i>Manual Time Out</a>
                <a class="btn btn-outline-secondary" href="breaks.php"><i class="bi bi-cup-hot me-2"></i>Breaks</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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
        — <span class="text-success">Currently working</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Attendance Records</span>
        <span class="badge bg-primary"><?= count($rows) ?> records</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No attendance records found</td></tr>
            <?php else: foreach($rows as $r): 
                $hours = '';
                if($r['time_in'] && $r['time_out']) {
                    $diff = strtotime($r['time_out']) - strtotime($r['time_in']);
                    $hours = round($diff / 3600, 1) . 'h';
                }
            ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:0.8rem;">
                                <?= strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                                <small class="text-muted"><?= e($r['employee_code']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= date('M j, Y', strtotime($r['date'])) ?></td>
                    <td>
                        <?php if($r['time_in']): ?>
                            <i class="bi bi-box-arrow-in-right text-success me-1"></i><?= date('h:i A', strtotime($r['time_in'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['time_out']): ?>
                            <i class="bi bi-box-arrow-right text-warning me-1"></i><?= date('h:i A', strtotime($r['time_out'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= $hours ?: '-' ?></td>
                    <td>
                        <?php if($r['time_out']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                        <?php else: ?>
                            <span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Working</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
