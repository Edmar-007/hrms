<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$deptFilter = (int)($_GET['department_id'] ?? 0);
$lateThreshold = '09:00:00';

$departments = [];
$deptStats = [];
$leaveStats = [];
$attendanceTrendRows = [];
$departmentCoverage = [];

$totalEmployees = 0;
$totalDepartments = 0;
$pendingLeaves = 0;
$todayAttendance = 0;
$presentCount = 0;
$lateCount = 0;
$onLeaveCount = 0;
$activeEmployeesFiltered = 0;

$periodDays = ((new DateTime($from))->diff(new DateTime($to)))->days + 1;
$trendMode = $periodDays <= 45 ? 'daily' : 'monthly';
$trendTitle = $trendMode === 'daily' ? 'Daily attendance pulse' : 'Monthly attendance pulse';
$absenceLabel = $periodDays === 1 ? 'Absent' : 'No Check-In';

if ($hasSaas && $cid) {
    $deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE company_id = ? ORDER BY name");
    $deptStmt->execute([$cid]);
    $departments = $deptStmt->fetchAll();

    $lateCfg = $pdo->prepare("SELECT grace_period_minutes FROM attendance_settings WHERE company_id = ? LIMIT 1");
    $lateCfg->execute([$cid]);
    $lateRow = $lateCfg->fetch();
    $graceMins = (int)($lateRow['grace_period_minutes'] ?? 10);
    $lateThreshold = date('H:i:s', strtotime('08:00:00 +' . $graceMins . ' minutes'));

    $st = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
    $st->execute([$cid]);
    $totalEmployees = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE company_id = ?");
    $st->execute([$cid]);
    $totalDepartments = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'");
    $st->execute([$cid]);
    $pendingLeaves = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE company_id = ? AND date = CURDATE()");
    $st->execute([$cid]);
    $todayAttendance = (int)$st->fetchColumn();

    $baseFilter = " FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.company_id = ? AND a.date BETWEEN ? AND ?
        WHERE e.company_id = ? AND e.status = 'active' ";
    $params = [$cid, $from, $to, $cid];
    if ($deptFilter > 0) {
        $baseFilter .= " AND e.department_id = ? ";
        $params[] = $deptFilter;
    }

    $presentSt = $pdo->prepare("SELECT COUNT(DISTINCT e.id) " . $baseFilter . " AND a.time_in IS NOT NULL");
    $presentSt->execute($params);
    $presentCount = (int)$presentSt->fetchColumn();

    $lateSql = "SELECT COUNT(*)
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.company_id = ? AND e.company_id = ? AND a.date BETWEEN ? AND ? AND a.time_in > ?";
    $lateParams = [$cid, $cid, $from, $to, $lateThreshold];
    if ($deptFilter > 0) {
        $lateSql .= " AND e.department_id = ?";
        $lateParams[] = $deptFilter;
    }
    $lateSt = $pdo->prepare($lateSql);
    $lateSt->execute($lateParams);
    $lateCount = (int)$lateSt->fetchColumn();

    $onLeaveSql = "SELECT COUNT(DISTINCT lr.employee_id)
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        WHERE lr.company_id = ? AND e.company_id = ? AND lr.status = 'approved' AND lr.start_date <= ? AND lr.end_date >= ?";
    $leaveParams = [$cid, $cid, $to, $from];
    if ($deptFilter > 0) {
        $onLeaveSql .= " AND e.department_id = ?";
        $leaveParams[] = $deptFilter;
    }
    $onLeaveSt = $pdo->prepare($onLeaveSql);
    $onLeaveSt->execute($leaveParams);
    $onLeaveCount = (int)$onLeaveSt->fetchColumn();

    $filteredActiveSql = "SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'";
    $filteredActiveParams = [$cid];
    if ($deptFilter > 0) {
        $filteredActiveSql .= " AND department_id = ?";
        $filteredActiveParams[] = $deptFilter;
    }
    $st = $pdo->prepare($filteredActiveSql);
    $st->execute($filteredActiveParams);
    $activeEmployeesFiltered = (int)$st->fetchColumn();

    $deptStatsStmt = $pdo->prepare("SELECT d.name, COUNT(e.id) AS cnt
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.company_id = ? AND e.status = 'active'
        WHERE d.company_id = ?
        GROUP BY d.id
        ORDER BY cnt DESC, d.name ASC");
    $deptStatsStmt->execute([$cid, $cid]);
    $deptStats = $deptStatsStmt->fetchAll();

    $bucketExpr = $trendMode === 'daily'
        ? "DATE_FORMAT(a.date, '%Y-%m-%d')"
        : "DATE_FORMAT(a.date, '%Y-%m')";
    $trendSql = "SELECT {$bucketExpr} AS bucket,
            COUNT(*) AS present_count,
            SUM(CASE WHEN a.time_in > ? THEN 1 ELSE 0 END) AS late_count,
            AVG(CASE
                WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                ELSE NULL
            END) / 60 AS avg_hours
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.company_id = ? AND e.company_id = ? AND a.date BETWEEN ? AND ?";
    $trendParams = [$lateThreshold, $cid, $cid, $from, $to];
    if ($deptFilter > 0) {
        $trendSql .= " AND e.department_id = ?";
        $trendParams[] = $deptFilter;
    }
    $trendSql .= " GROUP BY bucket ORDER BY bucket";
    $trendStmt = $pdo->prepare($trendSql);
    $trendStmt->execute($trendParams);
    $attendanceTrendRows = $trendStmt->fetchAll();

    $leaveSql = "SELECT lt.name,
            SUM(CASE WHEN fl.status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN fl.status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN fl.status = 'rejected' THEN 1 ELSE 0 END) AS rejected
        FROM leave_types lt
        LEFT JOIN (
            SELECT lr.leave_type_id, lr.status
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            WHERE lr.company_id = ? AND e.company_id = ?";
    $leaveSummaryParams = [$cid, $cid];
    if ($deptFilter > 0) {
        $leaveSql .= " AND e.department_id = ?";
        $leaveSummaryParams[] = $deptFilter;
    }
    $leaveSql .= "
        ) fl ON fl.leave_type_id = lt.id
        WHERE lt.company_id = ?
        GROUP BY lt.id
        ORDER BY lt.name ASC";
    $leaveSummaryParams[] = $cid;
    $leaveStmt = $pdo->prepare($leaveSql);
    $leaveStmt->execute($leaveSummaryParams);
    $leaveStats = $leaveStmt->fetchAll();

    $coverageSql = "SELECT d.name,
            COUNT(e.id) AS active_count,
            COUNT(DISTINCT CASE WHEN a.time_in IS NOT NULL THEN e.id END) AS present_count
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.company_id = ? AND e.status = 'active'
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.company_id = ? AND a.date BETWEEN ? AND ?
        WHERE d.company_id = ?";
    $coverageParams = [$cid, $cid, $from, $to, $cid];
    if ($deptFilter > 0) {
        $coverageSql .= " AND d.id = ?";
        $coverageParams[] = $deptFilter;
    }
    $coverageSql .= " GROUP BY d.id ORDER BY active_count DESC, d.name ASC";
    $coverageStmt = $pdo->prepare($coverageSql);
    $coverageStmt->execute($coverageParams);
    $departmentCoverage = $coverageStmt->fetchAll();
} else {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

    $totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
    $totalDepartments = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
    $todayAttendance = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetchColumn();

    $baseFilter = " FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date BETWEEN ? AND ?
        WHERE e.status = 'active' ";
    $params = [$from, $to];
    if ($deptFilter > 0) {
        $baseFilter .= " AND e.department_id = ? ";
        $params[] = $deptFilter;
    }

    $presentSt = $pdo->prepare("SELECT COUNT(DISTINCT e.id) " . $baseFilter . " AND a.time_in IS NOT NULL");
    $presentSt->execute($params);
    $presentCount = (int)$presentSt->fetchColumn();

    $lateSql = "SELECT COUNT(*)
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.date BETWEEN ? AND ? AND a.time_in > ?";
    $lateParams = [$from, $to, $lateThreshold];
    if ($deptFilter > 0) {
        $lateSql .= " AND e.department_id = ?";
        $lateParams[] = $deptFilter;
    }
    $lateSt = $pdo->prepare($lateSql);
    $lateSt->execute($lateParams);
    $lateCount = (int)$lateSt->fetchColumn();

    $onLeaveSql = "SELECT COUNT(DISTINCT lr.employee_id)
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        WHERE lr.status = 'approved' AND lr.start_date <= ? AND lr.end_date >= ?";
    $leaveParams = [$to, $from];
    if ($deptFilter > 0) {
        $onLeaveSql .= " AND e.department_id = ?";
        $leaveParams[] = $deptFilter;
    }
    $onLeaveSt = $pdo->prepare($onLeaveSql);
    $onLeaveSt->execute($leaveParams);
    $onLeaveCount = (int)$onLeaveSt->fetchColumn();

    $filteredActiveSql = "SELECT COUNT(*) FROM employees WHERE status = 'active'";
    $filteredActiveParams = [];
    if ($deptFilter > 0) {
        $filteredActiveSql .= " AND department_id = ?";
        $filteredActiveParams[] = $deptFilter;
    }
    $st = $pdo->prepare($filteredActiveSql);
    $st->execute($filteredActiveParams);
    $activeEmployeesFiltered = (int)$st->fetchColumn();

    $deptStats = $pdo->query("SELECT d.name, COUNT(e.id) AS cnt
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
        GROUP BY d.id
        ORDER BY cnt DESC, d.name ASC")->fetchAll();

    $bucketExpr = $trendMode === 'daily'
        ? "DATE_FORMAT(a.date, '%Y-%m-%d')"
        : "DATE_FORMAT(a.date, '%Y-%m')";
    $trendSql = "SELECT {$bucketExpr} AS bucket,
            COUNT(*) AS present_count,
            SUM(CASE WHEN a.time_in > ? THEN 1 ELSE 0 END) AS late_count,
            AVG(CASE
                WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                ELSE NULL
            END) / 60 AS avg_hours
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.date BETWEEN ? AND ?";
    $trendParams = [$lateThreshold, $from, $to];
    if ($deptFilter > 0) {
        $trendSql .= " AND e.department_id = ?";
        $trendParams[] = $deptFilter;
    }
    $trendSql .= " GROUP BY bucket ORDER BY bucket";
    $trendStmt = $pdo->prepare($trendSql);
    $trendStmt->execute($trendParams);
    $attendanceTrendRows = $trendStmt->fetchAll();

    $leaveSql = "SELECT lt.name,
            SUM(CASE WHEN fl.status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN fl.status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN fl.status = 'rejected' THEN 1 ELSE 0 END) AS rejected
        FROM leave_types lt
        LEFT JOIN (
            SELECT lr.leave_type_id, lr.status
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            WHERE 1 = 1";
    $leaveSummaryParams = [];
    if ($deptFilter > 0) {
        $leaveSql .= " AND e.department_id = ?";
        $leaveSummaryParams[] = $deptFilter;
    }
    $leaveSql .= "
        ) fl ON fl.leave_type_id = lt.id
        GROUP BY lt.id
        ORDER BY lt.name ASC";
    $leaveStmt = $pdo->prepare($leaveSql);
    $leaveStmt->execute($leaveSummaryParams);
    $leaveStats = $leaveStmt->fetchAll();

    $coverageSql = "SELECT d.name,
            COUNT(e.id) AS active_count,
            COUNT(DISTINCT CASE WHEN a.time_in IS NOT NULL THEN e.id END) AS present_count
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date BETWEEN ? AND ?";
    $coverageParams = [$from, $to];
    if ($deptFilter > 0) {
        $coverageSql .= " WHERE d.id = ?";
        $coverageParams[] = $deptFilter;
    }
    $coverageSql .= " GROUP BY d.id ORDER BY active_count DESC, d.name ASC";
    $coverageStmt = $pdo->prepare($coverageSql);
    $coverageStmt->execute($coverageParams);
    $departmentCoverage = $coverageStmt->fetchAll();
}

$absentCount = max(0, $activeEmployeesFiltered - $presentCount - $onLeaveCount);
$attendanceRate = $activeEmployeesFiltered > 0 ? round(($presentCount / $activeEmployeesFiltered) * 100, 1) : 0;
$lateRate = 0;

$selectedDepartmentName = 'All departments';
foreach ($departments as $department) {
    if ((int)$department['id'] === $deptFilter) {
        $selectedDepartmentName = $department['name'];
        break;
    }
}

$trendLabels = [];
$trendPresent = [];
$trendLate = [];
$trendHours = [];
foreach ($attendanceTrendRows as $row) {
    $bucket = (string)$row['bucket'];
    $trendLabels[] = $trendMode === 'daily'
        ? date('M j', strtotime($bucket))
        : date('M Y', strtotime($bucket . '-01'));
    $trendPresent[] = (int)$row['present_count'];
    $trendLate[] = (int)$row['late_count'];
    $trendHours[] = round((float)($row['avg_hours'] ?? 0), 1);
}
$totalCheckinsInRange = array_sum($trendPresent);
$lateRate = $totalCheckinsInRange > 0 ? round(($lateCount / $totalCheckinsInRange) * 100, 1) : 0;

$deptLabels = [];
$deptCounts = [];
foreach ($deptStats as $row) {
    $deptLabels[] = $row['name'];
    $deptCounts[] = (int)$row['cnt'];
}

$leaveLabels = [];
$leaveApproved = [];
$leavePending = [];
$leaveRejected = [];
$leaveRequestTotal = 0;
foreach ($leaveStats as $row) {
    $leaveLabels[] = $row['name'];
    $approved = (int)($row['approved'] ?? 0);
    $pending = (int)($row['pending'] ?? 0);
    $rejected = (int)($row['rejected'] ?? 0);
    $leaveApproved[] = $approved;
    $leavePending[] = $pending;
    $leaveRejected[] = $rejected;
    $leaveRequestTotal += $approved + $pending + $rejected;
}

$topDepartment = !empty($deptStats) ? $deptStats[0]['name'] : 'No department data yet';
$periodLabel = date('M j, Y', strtotime($from)) . ' - ' . date('M j, Y', strtotime($to));
?>
<div class="page-header">
    <div>
        <span class="page-kicker">Reporting Studio</span>
        <h4><i class="bi bi-bar-chart-line me-2"></i>Reports & Analytics</h4>
        <p class="page-subtitle">A cleaner reporting workspace for attendance, leave demand, and department coverage across your selected time window.</p>
    </div>
    <div class="page-header-meta">
        <span class="page-chip page-chip--accent"><i class="bi bi-calendar3 me-1"></i><?= e($periodLabel) ?></span>
        <span class="page-chip"><i class="bi bi-diagram-3 me-1"></i><?= e($selectedDepartmentName) ?></span>
    </div>
</div>

<div class="reporting-shell">
    <div class="card reporting-toolbar">
        <div class="card-body">
            <div class="reporting-toolbar__grid">
                <div>
                    <h5 class="reporting-toolbar__title">Tune the reporting window</h5>
                    <p class="reporting-toolbar__text">Swap date ranges, focus on a department, and export the same snapshot your leaders are reviewing on screen.</p>
                    <form class="row g-3 mt-1" method="get">
                        <div class="col-md-4">
                            <label class="form-label mb-1">From</label>
                            <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">To</label>
                            <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="0">All departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int)$department['id'] ?>" <?= $deptFilter === (int)$department['id'] ? 'selected' : '' ?>>
                                        <?= e($department['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex flex-wrap gap-2 align-items-end">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-2"></i>Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/modules/reports/index.php"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</a>
                        </div>
                    </form>
                </div>
                <div class="reporting-toolbar__aside">
                    <div class="metric-strip">
                        <div class="metric-tile">
                            <span class="metric-tile__label">Active Headcount</span>
                            <strong class="metric-tile__value"><?= number_format($totalEmployees) ?></strong>
                            <span class="metric-tile__meta"><?= number_format($totalDepartments) ?> departments on file</span>
                        </div>
                        <div class="metric-tile">
                            <span class="metric-tile__label">Pending Leave</span>
                            <strong class="metric-tile__value"><?= number_format($pendingLeaves) ?></strong>
                            <span class="metric-tile__meta">Approvals waiting this cycle</span>
                        </div>
                        <div class="metric-tile">
                            <span class="metric-tile__label">Checked In Today</span>
                            <strong class="metric-tile__value"><?= number_format($todayAttendance) ?></strong>
                            <span class="metric-tile__meta">Live attendance activity</span>
                        </div>
                        <div class="metric-tile">
                            <span class="metric-tile__label">Largest Department</span>
                            <strong class="metric-tile__value metric-tile__value--sm"><?= e($topDepartment) ?></strong>
                            <span class="metric-tile__meta">Current active roster mix</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=attendance&format=csv&month=<?= e(substr($from, 0, 7)) ?>">Export Attendance CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=leaves&format=csv&month=<?= e(substr($from, 0, 7)) ?>">Export Leave CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/api/export-report.php?type=audit&format=csv">Export Audit CSV</a>
                        <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= BASE_URL ?>/api/export-report.php?type=attendance&format=print&month=<?= e(substr($from, 0, 7)) ?>">Printable Attendance</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-metrics row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card success">
                <h6>Checked In</h6>
                <h2><?= number_format($presentCount) ?></h2>
                <p class="mb-0 text-white-50"><?= $attendanceRate ?>% of the selected workforce</p>
                <i class="bi bi-check2-circle icon"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card warning">
                <h6>Late Arrivals</h6>
                <h2><?= number_format($lateCount) ?></h2>
                <p class="mb-0 text-white-50"><?= $lateRate ?>% against recorded check-ins</p>
                <i class="bi bi-alarm icon"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card info">
                <h6>On Leave</h6>
                <h2><?= number_format($onLeaveCount) ?></h2>
                <p class="mb-0 text-white-50">Approved leave overlapping this window</p>
                <i class="bi bi-calendar2-check icon"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card danger">
                <h6><?= e($absenceLabel) ?></h6>
                <h2><?= number_format($absentCount) ?></h2>
                <p class="mb-0 text-white-50"><?= $periodDays ?>-day reporting scope</p>
                <i class="bi bi-person-dash icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-activity me-2"></i><?= e($trendTitle) ?></span>
                    <span class="table-count-badge"><?= strtoupper($trendMode) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($trendLabels)): ?>
                        <div class="empty-state"><i class="bi bi-activity"></i><p>No attendance movement found for this period.</p></div>
                    <?php else: ?>
                        <div class="chart-stage chart-stage--xl">
                            <canvas id="attendancePulseChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-pie-chart me-2"></i>Department Mix</span>
                    <span class="table-count-badge">ACTIVE ROSTER</span>
                </div>
                <div class="card-body">
                    <?php if (empty($deptLabels)): ?>
                        <div class="empty-state"><i class="bi bi-diagram-3"></i><p>No department distribution available yet.</p></div>
                    <?php else: ?>
                        <div class="chart-stage chart-stage--lg">
                            <canvas id="departmentMixChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-bar-chart-steps me-2"></i>Leave Request Portfolio</span>
                    <span class="table-count-badge"><?= number_format($leaveRequestTotal) ?> REQUESTS</span>
                </div>
                <div class="card-body">
                    <?php if (empty($leaveLabels)): ?>
                        <div class="empty-state"><i class="bi bi-calendar-range"></i><p>No leave activity found for this scope.</p></div>
                    <?php else: ?>
                        <div class="chart-stage chart-stage--lg">
                            <canvas id="leavePortfolioChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-buildings me-2"></i>Department Coverage</div>
                <div class="card-body p-0">
                    <?php if (empty($departmentCoverage)): ?>
                        <div class="empty-state"><i class="bi bi-buildings"></i><p>No department coverage data available.</p></div>
                    <?php else: ?>
                        <div class="table-responsive-wrapper">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Checked In</th>
                                        <th>Active</th>
                                        <th>Coverage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departmentCoverage as $row): ?>
                                        <?php
                                        $activeCount = (int)$row['active_count'];
                                        $presentCountByDept = (int)$row['present_count'];
                                        $coverageRate = $activeCount > 0 ? round(($presentCountByDept / $activeCount) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?= e($row['name']) ?></td>
                                            <td><span class="badge bg-success"><?= $presentCountByDept ?></span></td>
                                            <td><?= $activeCount ?></td>
                                            <td>
                                                <div class="progress" style="height: 12px;">
                                                    <div class="progress-bar" style="width: <?= $coverageRate ?>%"></div>
                                                </div>
                                                <div class="small text-muted mt-1"><?= $coverageRate ?>%</div>
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

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-calendar-check me-2"></i>Leave Summary</div>
                <div class="card-body p-0">
                    <?php if (empty($leaveStats)): ?>
                        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No leave summary rows available yet.</p></div>
                    <?php else: ?>
                        <div class="table-responsive-wrapper">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Rejected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveStats as $row): ?>
                                        <tr>
                                            <td><?= e($row['name']) ?></td>
                                            <td><span class="badge bg-success"><?= (int)($row['approved'] ?? 0) ?></span></td>
                                            <td><span class="badge bg-warning text-dark"><?= (int)($row['pending'] ?? 0) ?></span></td>
                                            <td><span class="badge bg-danger"><?= (int)($row['rejected'] ?? 0) ?></span></td>
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
</div>

<?php include __DIR__ . '/_charts.php'; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
