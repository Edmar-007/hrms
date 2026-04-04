<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Get stats with SaaS support
if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE company_id=? AND status='active'");
    $st->execute([$cid]); $totalEmployees = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM departments WHERE company_id=?");
    $st->execute([$cid]); $totalDepartments = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM leave_requests WHERE company_id=? AND status='pending'");
    $st->execute([$cid]); $pendingLeaves = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE company_id=? AND date=CURDATE()");
    $st->execute([$cid]); $todayAttendance = $st->fetch()['c'];
    
    // Department distribution
    $st = $pdo->prepare("SELECT d.name, COUNT(e.id) as cnt FROM departments d 
        LEFT JOIN employees e ON e.department_id=d.id AND e.status='active' 
        WHERE d.company_id=?
        GROUP BY d.id ORDER BY cnt DESC");
    $st->execute([$cid]); $deptStats = $st->fetchAll();
    
    // Monthly attendance
    $st = $pdo->prepare("SELECT DATE_FORMAT(date,'%Y-%m') as month, COUNT(*) as cnt 
        FROM attendance 
        WHERE company_id=? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date,'%Y-%m') 
        ORDER BY month");
    $st->execute([$cid]); $monthlyAttendance = $st->fetchAll();
    
    // Leave summary
    $st = $pdo->prepare("SELECT lt.name, 
        SUM(CASE WHEN lr.status='approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN lr.status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN lr.status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM leave_types lt 
        LEFT JOIN leave_requests lr ON lr.leave_type_id=lt.id 
        WHERE lt.company_id=?
        GROUP BY lt.id");
    $st->execute([$cid]); $leaveStats = $st->fetchAll();
} else {
    $totalEmployees = $pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
    $totalDepartments = $pdo->query("SELECT COUNT(*) c FROM departments")->fetch()['c'];
    $pendingLeaves = $pdo->query("SELECT COUNT(*) c FROM leave_requests WHERE status='pending'")->fetch()['c'];
    $todayAttendance = $pdo->query("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE()")->fetch()['c'];
    
    $deptStats = $pdo->query("SELECT d.name, COUNT(e.id) as cnt FROM departments d 
        LEFT JOIN employees e ON e.department_id=d.id AND e.status='active' 
        GROUP BY d.id ORDER BY cnt DESC")->fetchAll();
    
    $monthlyAttendance = $pdo->query("SELECT DATE_FORMAT(date,'%Y-%m') as month, COUNT(*) as cnt 
        FROM attendance 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date,'%Y-%m') 
        ORDER BY month")->fetchAll();
    
    $leaveStats = $pdo->query("SELECT lt.name, 
        SUM(CASE WHEN lr.status='approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN lr.status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN lr.status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM leave_types lt 
        LEFT JOIN leave_requests lr ON lr.leave_type_id=lt.id 
        GROUP BY lt.id")->fetchAll();
}
?>
<div class="page-header">
    <h4><i class="bi bi-graph-up me-2"></i>Reports & Analytics</h4>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <h6><i class="bi bi-people me-2"></i>Total Employees</h6>
            <h2><?= $totalEmployees ?></h2>
            <i class="bi bi-people-fill icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <h6><i class="bi bi-building me-2"></i>Departments</h6>
            <h2><?= $totalDepartments ?></h2>
            <i class="bi bi-buildings icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <h6><i class="bi bi-hourglass me-2"></i>Pending Leaves</h6>
            <h2><?= $pendingLeaves ?></h2>
            <i class="bi bi-calendar-x icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <h6><i class="bi bi-clock me-2"></i>Today's Attendance</h6>
            <h2><?= $todayAttendance ?></h2>
            <i class="bi bi-clock-history icon"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Department Distribution -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Employees by Department</div>
            <div class="card-body">
                <?php if(empty($deptStats)): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p>No data available</p></div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Department</th><th>Employees</th><th>Distribution</th></tr></thead>
                    <tbody>
                    <?php foreach($deptStats as $d): 
                        $pct = $totalEmployees > 0 ? round(($d['cnt'] / $totalEmployees) * 100) : 0;
                    ?>
                    <tr>
                        <td><i class="bi bi-building me-2 text-muted"></i><?= e($d['name']) ?></td>
                        <td><span class="badge bg-primary"><?= $d['cnt'] ?></span></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" style="width: <?= $pct ?>%"><?= $pct ?>%</div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Leave Summary -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-check me-2"></i>Leave Summary</div>
            <div class="card-body">
                <?php if(empty($leaveStats)): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p>No data available</p></div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Leave Type</th><th>Approved</th><th>Pending</th><th>Rejected</th></tr></thead>
                    <tbody>
                    <?php foreach($leaveStats as $ls): ?>
                    <tr>
                        <td><i class="bi bi-calendar me-2 text-muted"></i><?= e($ls['name']) ?></td>
                        <td><span class="badge bg-success"><?= $ls['approved'] ?? 0 ?></span></td>
                        <td><span class="badge bg-warning"><?= $ls['pending'] ?? 0 ?></span></td>
                        <td><span class="badge bg-danger"><?= $ls['rejected'] ?? 0 ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Attendance Chart -->
<div class="card mt-4">
    <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Monthly Attendance (Last 6 Months)</div>
    <div class="card-body">
        <?php if(empty($monthlyAttendance)): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i><p>No attendance data available</p></div>
        <?php else: ?>
        <div class="row">
            <?php 
            $maxAtt = max(array_column($monthlyAttendance, 'cnt'));
            foreach($monthlyAttendance as $ma): 
                $pct = $maxAtt > 0 ? round(($ma['cnt'] / $maxAtt) * 100) : 0;
            ?>
            <div class="col text-center">
                <div style="height: 150px; display: flex; flex-direction: column; justify-content: flex-end;">
                    <div class="bg-primary rounded-top mx-auto" style="width: 40px; height: <?= $pct ?>%; min-height: 20px;"></div>
                </div>
                <small class="d-block mt-2 fw-bold"><?= $ma['cnt'] ?></small>
                <small class="text-muted"><?= date('M', strtotime($ma['month'].'-01')) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
