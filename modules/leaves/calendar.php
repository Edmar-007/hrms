<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$cid = company_id() ?? 1;
$month = $_GET['month'] ?? date('Y-m');
$start = date('Y-m-01', strtotime($month.'-01'));
$end = date('Y-m-t', strtotime($month.'-01'));

$st = $pdo->prepare("SELECT lr.start_date, lr.end_date, lr.status, e.first_name, e.last_name, lt.name as leave_type
    FROM leave_requests lr
    JOIN employees e ON e.id = lr.employee_id
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.company_id = ? AND lr.start_date <= ? AND lr.end_date >= ?
    ORDER BY lr.start_date ASC");
$st->execute([$cid, $end, $start]);
$rows = $st->fetchAll();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calendar3 me-2"></i>Leave Calendar</h4>
    <div class="d-flex gap-2">
        <form method="get"><input type="month" name="month" value="<?= e($month) ?>" class="form-control"></form>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Leave Type</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No leave entries for this month</td></tr>
            <?php else: foreach($rows as $r): ?>
                <tr>
                    <td><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                    <td><?= e($r['leave_type']) ?></td>
                    <td><?= e($r['start_date']) ?></td>
                    <td><?= e($r['end_date']) ?></td>
                    <td><span class="badge bg-<?= $r['status']==='approved' ? 'success' : ($r['status']==='pending' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>

