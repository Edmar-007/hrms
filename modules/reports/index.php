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
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$deptFilter = (int)($_GET['department_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$lateThreshold = '09:00:00';

// Get stats with SaaS support
if($hasSaas && $cid) {
    $lateCfg = $pdo->prepare("SELECT grace_period_minutes FROM attendance_settings WHERE company_id=? LIMIT 1");
    $lateCfg->execute([$cid]);
    $lateRow = $lateCfg->fetch();
    $graceMins = (int)($lateRow['grace_period_minutes'] ?? 10);
    // Baseline shift start used for late summary when per-employee shift is unavailable.
    $lateThreshold = date('H:i:s', strtotime('08:00:00 +' . $graceMins . ' minutes'));
    $st = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE company_id=? AND status='active'");
    $st->execute([$cid]); $totalEmployees = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM departments WHERE company_id=?");
    $st->execute([$cid]); $totalDepartments = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM leave_requests WHERE company_id=? AND status='pending'");
    $st->execute([$cid]); $pendingLeaves = $st->fetch()['c'];
    
    $st = $pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE company_id=? AND date=CURDATE()");
    $st->execute([$cid]); $todayAttendance = $st->fetch()['c'];

    $baseFilter = " FROM employees e
        LEFT JOIN attendance a ON a.employee_id=e.id AND a.company_id=? AND a.date BETWEEN ? AND ?
        WHERE e.company_id=? AND e.status='active' ";
    $params = [$cid, $from, $to, $cid];
    if ($deptFilter > 0) { $baseFilter .= " AND e.department_id = ? "; $params[] = $deptFilter; }

    $presentSt = $pdo->prepare("SELECT COUNT(DISTINCT e.id) c " . $baseFilter . " AND a.time_in IS NOT NULL");
    $presentSt->execute($params); $presentCount = (int)$presentSt->fetch()['c'];

    $lateSt = $pdo->prepare("SELECT COUNT(*) c FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.company_id=? AND e.company_id=? AND a.date BETWEEN ? AND ? AND a.time_in > ?".($deptFilter>0?" AND e.department_id=?":""));
    $lateParams = [$cid, $cid, $from, $to, $lateThreshold]; if ($deptFilter>0) $lateParams[] = $deptFilter;
    $lateSt->execute($lateParams); $lateCount = (int)$lateSt->fetch()['c'];

    $onLeaveSt = $pdo->prepare("SELECT COUNT(DISTINCT lr.employee_id) c FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.company_id=? AND e.company_id=? AND lr.status='approved' AND lr.start_date<=? AND lr.end_date>=?".($deptFilter>0?" AND e.department_id=?":""));
    $leaveParams = [$cid, $cid, $to, $from]; if ($deptFilter>0) $leaveParams[] = $deptFilter;
    $onLeaveSt->execute($leaveParams); $onLeaveCount = (int)$onLeaveSt->fetch()['c'];

    $st = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE company_id=? AND status='active'".($deptFilter>0?" AND department_id=?":""));
    $empParams = [$cid]; if ($deptFilter>0) $empParams[] = $deptFilter;
    $st->execute($empParams); $activeEmployeesFiltered = (int)$st->fetch()['c'];
    $absentCount = max(0, $activeEmployeesFiltered - $presentCount - $onLeaveCount);
    
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
    $presentCount = 0; $lateCount = 0; $onLeaveCount = 0; $absentCount = 0;
    
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

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-md-3"><label class="form-label mb-1">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
            <div class="col-md-3"><label class="form-label mb-1">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-3"><label class="form-label mb-1">Department ID</label><input class="form-control" type="number" min="0" name="department_id" value="<?= $deptFilter ?: '' ?>" placeholder="All"></div>
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-filter me-2"></i>Apply Filters</button></div>
        </form>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=attendance&format=csv&month=<?= e(substr($from,0,7)) ?>">Export Attendance CSV</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=leaves&format=csv&month=<?= e(substr($from,0,7)) ?>">Export Leave CSV</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=audit&format=csv">Export Audit CSV</a>
            <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= BASE_URL ?>/api/export-report.php?type=attendance&format=print&month=<?= e(substr($from,0,7)) ?>">Printable Attendance</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="stat-card success"><h6>Present</h6><h2><?= (int)$presentCount ?></h2></div></div>
    <div class="col-md-3"><div class="stat-card warning"><h6>Late</h6><h2><?= (int)$lateCount ?></h2></div></div>
    <div class="col-md-3"><div class="stat-card info"><h6>On Leave</h6><h2><?= (int)$onLeaveCount ?></h2></div></div>
    <div class="col-md-3"><div class="stat-card danger"><h6>Absent</h6><h2><?= (int)$absentCount ?></h2></div></div>
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
