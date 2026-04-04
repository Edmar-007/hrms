<?php
require_once __DIR__.'/../config/db.php'; require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/header.php'; require_once __DIR__.'/../includes/nav.php'; require_login();

// Check if company_id column exists (SaaS mode)
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

if($hasSaas && $cid) {
    $emp=$pdo->prepare("SELECT COUNT(*) c FROM employees WHERE company_id=? AND status='active'");
    $emp->execute([$cid]); $emp=$emp->fetch()['c'];
    $lv=$pdo->prepare("SELECT COUNT(*) c FROM leave_requests WHERE company_id=? AND status='pending'");
    $lv->execute([$cid]); $lv=$lv->fetch()['c'];
    $att=$pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE company_id=? AND date=CURDATE()");
    $att->execute([$cid]); $att=$att->fetch()['c'];
    $users=$pdo->prepare("SELECT COUNT(*) c FROM users WHERE company_id=? AND is_active=1");
    $users->execute([$cid]); $users=$users->fetch()['c'];
} else {
    $emp=$pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
    $lv=$pdo->query("SELECT COUNT(*) c FROM leave_requests WHERE status='pending'")->fetch()['c'];
    $att=$pdo->query("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE()")->fetch()['c'];
    $users=$pdo->query("SELECT COUNT(*) c FROM users WHERE is_active=1")->fetch()['c'];
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
  <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
  <span class="text-muted"><?= date('l, F j, Y') ?></span>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-6 col-lg-3">
    <div class="stat-card primary">
      <h6><i class="bi bi-people me-2"></i>Active Employees</h6>
      <h2><?= $emp ?></h2>
      <i class="bi bi-people-fill icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card warning">
      <h6><i class="bi bi-hourglass-split me-2"></i>Pending Leaves</h6>
      <h2><?= $lv ?></h2>
      <i class="bi bi-calendar-x icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card success">
      <h6><i class="bi bi-clock me-2"></i>Today's Attendance</h6>
      <h2><?= $att ?></h2>
      <i class="bi bi-clock-history icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card info">
      <h6><i class="bi bi-person-badge me-2"></i>System Users</h6>
      <h2><?= $users ?></h2>
      <i class="bi bi-person-gear icon"></i>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6"><a href="employees/index.php" class="btn btn-outline-primary w-100 py-3"><i class="bi bi-person-plus d-block fs-4 mb-1"></i>Add Employee</a></div>
          <div class="col-6"><a href="attendance/index.php" class="btn btn-outline-success w-100 py-3"><i class="bi bi-clock d-block fs-4 mb-1"></i>Attendance</a></div>
          <div class="col-6"><a href="leaves/index.php" class="btn btn-outline-warning w-100 py-3"><i class="bi bi-calendar-check d-block fs-4 mb-1"></i>Leave Requests</a></div>
          <div class="col-6"><a href="payroll/index.php" class="btn btn-outline-info w-100 py-3"><i class="bi bi-cash d-block fs-4 mb-1"></i>Payroll</a></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Recent Attendance</div>
      <div class="card-body p-0">
        <?php
        // Check SaaS mode
        $hasSaas = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'company_id'")->fetch();
        $cid = $hasSaas ? (company_id() ?? 1) : null;
        
        if($hasSaas && $cid) {
            $st = $pdo->prepare("SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.company_id=? ORDER BY a.date DESC, a.time_in DESC LIMIT 5");
            $st->execute([$cid]);
            $recent = $st->fetchAll();
        } else {
            $recent = $pdo->query("SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id ORDER BY a.date DESC, a.time_in DESC LIMIT 5")->fetchAll();
        }
        if($recent): ?>
        <table class="table table-hover mb-0">
          <thead><tr><th>Employee</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recent as $r): ?>
          <tr>
            <td><i class="bi bi-person-circle me-2 text-muted"></i><?= e($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= date('M j', strtotime($r['date'])) ?></td>
            <td>
              <?php if($r['time_out']): ?>
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
              <?php else: ?>
                <span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Working</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>No recent attendance records</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
