<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;
$employmentDateExpr = "COALESCE(hire_date, DATE(created_at))";

$totalEmployees = 0;
$activeEmployees = 0;
$inactiveEmployees = 0;
$avgTenure = 0;
$newHiresYtd = 0;
$pendingLeaves = 0;
$attendanceThisMonth = 0;
$avgHoursThisMonth = 0;

$departmentStats = [];
$growthLabels = [];
$growthHires = [];
$growthHeadcount = [];
$tenureLabels = [];
$tenureCounts = [];
$deptLabels = [];
$deptCounts = [];
$attendanceLabels = [];
$attendancePresent = [];
$attendanceHours = [];
$leaveLabels = [];
$leaveApproved = [];
$leavePending = [];
$leaveRejected = [];

$monthAxis = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $monthAxis[$key] = [
        'label' => date('M Y', strtotime($key . '-01')),
        'hires' => 0,
        'headcount' => 0,
    ];
}

$sixMonthAxis = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $sixMonthAxis[$key] = [
        'label' => date('M Y', strtotime($key . '-01')),
        'present' => 0,
        'hours' => 0,
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
    ];
}

try {
    if ($hasSaas && $cid) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM employees WHERE company_id = ?");
        $stmt->execute([$cid]);
        $empStats = $stmt->fetch();
        $totalEmployees = (int)($empStats['total'] ?? 0);
        $activeEmployees = (int)($empStats['active'] ?? 0);

        $stmt = $pdo->prepare("SELECT AVG(DATEDIFF(CURDATE(), {$employmentDateExpr}) / 365) FROM employees WHERE company_id = ? AND status = 'active' AND {$employmentDateExpr} IS NOT NULL");
        $stmt->execute([$cid]);
        $avgTenure = round((float)($stmt->fetchColumn() ?: 0), 1);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND YEAR({$employmentDateExpr}) = ?");
        $stmt->execute([$cid, date('Y')]);
        $newHiresYtd = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'");
        $stmt->execute([$cid]);
        $pendingLeaves = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) AS present_count,
                AVG(CASE
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, time_in, time_out)
                    ELSE NULL
                END) / 60 AS avg_hours
            FROM attendance
            WHERE company_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
        $stmt->execute([$cid, date('Y-m')]);
        $attendanceStats = $stmt->fetch();
        $attendanceThisMonth = (int)($attendanceStats['present_count'] ?? 0);
        $avgHoursThisMonth = round((float)($attendanceStats['avg_hours'] ?? 0), 1);

        $stmt = $pdo->prepare("SELECT d.name, COUNT(e.id) AS cnt
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.company_id = ? AND e.status = 'active'
            WHERE d.company_id = ?
            GROUP BY d.id
            ORDER BY cnt DESC, d.name ASC
            LIMIT 10");
        $stmt->execute([$cid, $cid]);
        $departmentStats = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT DATE_FORMAT({$employmentDateExpr}, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM employees
            WHERE company_id = ? AND {$employmentDateExpr} >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
            GROUP BY month
            ORDER BY month");
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($monthAxis[$row['month']])) {
                $monthAxis[$row['month']]['hires'] = (int)$row['cnt'];
            }
        }

        $headcountStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND {$employmentDateExpr} <= LAST_DAY(?)");
        foreach (array_keys($monthAxis) as $monthKey) {
            $headcountStmt->execute([$cid, $monthKey . '-01']);
            $monthAxis[$monthKey]['headcount'] = (int)$headcountStmt->fetchColumn();
        }

        $stmt = $pdo->prepare("SELECT CASE
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 365 THEN 'Under 1 year'
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 730 THEN '1-2 years'
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 1825 THEN '3-5 years'
                ELSE '5+ years'
            END AS band,
            COUNT(*) AS cnt
            FROM employees
            WHERE company_id = ? AND status = 'active' AND {$employmentDateExpr} IS NOT NULL
            GROUP BY band
            ORDER BY FIELD(band, 'Under 1 year', '1-2 years', '3-5 years', '5+ years')");
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $row) {
            $tenureLabels[] = $row['band'];
            $tenureCounts[] = (int)$row['cnt'];
        }

        $stmt = $pdo->prepare("SELECT DATE_FORMAT(date, '%Y-%m') AS month,
                COUNT(DISTINCT employee_id) AS present_count,
                AVG(CASE
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, time_in, time_out)
                    ELSE NULL
                END) / 60 AS avg_hours
            FROM attendance
            WHERE company_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY month
            ORDER BY month");
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($sixMonthAxis[$row['month']])) {
                $sixMonthAxis[$row['month']]['present'] = (int)$row['present_count'];
                $sixMonthAxis[$row['month']]['hours'] = round((float)($row['avg_hours'] ?? 0), 1);
            }
        }

        $stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                SUM(status = 'approved') AS approved_count,
                SUM(status = 'pending') AS pending_count,
                SUM(status = 'rejected') AS rejected_count
            FROM leave_requests
            WHERE company_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY month
            ORDER BY month");
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($sixMonthAxis[$row['month']])) {
                $sixMonthAxis[$row['month']]['approved'] = (int)$row['approved_count'];
                $sixMonthAxis[$row['month']]['pending'] = (int)$row['pending_count'];
                $sixMonthAxis[$row['month']]['rejected'] = (int)$row['rejected_count'];
            }
        }
    } else {
        $empStats = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM employees")->fetch();
        $totalEmployees = (int)($empStats['total'] ?? 0);
        $activeEmployees = (int)($empStats['active'] ?? 0);

        $avgTenure = round((float)($pdo->query("SELECT AVG(DATEDIFF(CURDATE(), {$employmentDateExpr}) / 365) FROM employees WHERE status = 'active' AND {$employmentDateExpr} IS NOT NULL")->fetchColumn() ?: 0), 1);
        $newHiresYtd = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE YEAR({$employmentDateExpr}) = " . (int)date('Y'))->fetchColumn();
        $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

        $attendanceStats = $pdo->query("SELECT COUNT(DISTINCT employee_id) AS present_count,
                AVG(CASE
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, time_in, time_out)
                    ELSE NULL
                END) / 60 AS avg_hours
            FROM attendance
            WHERE DATE_FORMAT(date, '%Y-%m') = '" . date('Y-m') . "'")->fetch();
        $attendanceThisMonth = (int)($attendanceStats['present_count'] ?? 0);
        $avgHoursThisMonth = round((float)($attendanceStats['avg_hours'] ?? 0), 1);

        $departmentStats = $pdo->query("SELECT d.name, COUNT(e.id) AS cnt
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            GROUP BY d.id
            ORDER BY cnt DESC, d.name ASC
            LIMIT 10")->fetchAll();

        foreach ($pdo->query("SELECT DATE_FORMAT({$employmentDateExpr}, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM employees
            WHERE {$employmentDateExpr} >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
            GROUP BY month
            ORDER BY month")->fetchAll() as $row) {
            if (isset($monthAxis[$row['month']])) {
                $monthAxis[$row['month']]['hires'] = (int)$row['cnt'];
            }
        }

        foreach (array_keys($monthAxis) as $monthKey) {
            $headcount = $pdo->query("SELECT COUNT(*) FROM employees WHERE {$employmentDateExpr} <= LAST_DAY('" . $monthKey . "-01')")->fetchColumn();
            $monthAxis[$monthKey]['headcount'] = (int)$headcount;
        }

        foreach ($pdo->query("SELECT CASE
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 365 THEN 'Under 1 year'
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 730 THEN '1-2 years'
                WHEN DATEDIFF(CURDATE(), {$employmentDateExpr}) < 1825 THEN '3-5 years'
                ELSE '5+ years'
            END AS band,
            COUNT(*) AS cnt
            FROM employees
            WHERE status = 'active' AND {$employmentDateExpr} IS NOT NULL
            GROUP BY band
            ORDER BY FIELD(band, 'Under 1 year', '1-2 years', '3-5 years', '5+ years')")->fetchAll() as $row) {
            $tenureLabels[] = $row['band'];
            $tenureCounts[] = (int)$row['cnt'];
        }

        foreach ($pdo->query("SELECT DATE_FORMAT(date, '%Y-%m') AS month,
                COUNT(DISTINCT employee_id) AS present_count,
                AVG(CASE
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, time_in, time_out)
                    ELSE NULL
                END) / 60 AS avg_hours
            FROM attendance
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY month
            ORDER BY month")->fetchAll() as $row) {
            if (isset($sixMonthAxis[$row['month']])) {
                $sixMonthAxis[$row['month']]['present'] = (int)$row['present_count'];
                $sixMonthAxis[$row['month']]['hours'] = round((float)($row['avg_hours'] ?? 0), 1);
            }
        }

        foreach ($pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                SUM(status = 'approved') AS approved_count,
                SUM(status = 'pending') AS pending_count,
                SUM(status = 'rejected') AS rejected_count
            FROM leave_requests
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY month
            ORDER BY month")->fetchAll() as $row) {
            if (isset($sixMonthAxis[$row['month']])) {
                $sixMonthAxis[$row['month']]['approved'] = (int)$row['approved_count'];
                $sixMonthAxis[$row['month']]['pending'] = (int)$row['pending_count'];
                $sixMonthAxis[$row['month']]['rejected'] = (int)$row['rejected_count'];
            }
        }
    }
} catch (PDOException $e) {
}

$inactiveEmployees = max(0, $totalEmployees - $activeEmployees);
$activeRate = $totalEmployees > 0 ? round(($activeEmployees / $totalEmployees) * 100, 1) : 0;
$attendanceRate = $activeEmployees > 0 ? round(($attendanceThisMonth / $activeEmployees) * 100, 1) : 0;
$largestDepartment = !empty($departmentStats) ? ($departmentStats[0]['name'] ?? 'No department data') : 'No department data';

foreach ($monthAxis as $values) {
    $growthLabels[] = $values['label'];
    $growthHires[] = $values['hires'];
    $growthHeadcount[] = $values['headcount'];
}

foreach ($departmentStats as $row) {
    $deptLabels[] = $row['name'];
    $deptCounts[] = (int)$row['cnt'];
}

foreach ($sixMonthAxis as $values) {
    $attendanceLabels[] = $values['label'];
    $attendancePresent[] = $values['present'];
    $attendanceHours[] = $values['hours'];
    $leaveLabels[] = $values['label'];
    $leaveApproved[] = $values['approved'];
    $leavePending[] = $values['pending'];
    $leaveRejected[] = $values['rejected'];
}
?>
<div class="page-header">
    <div>
        <span class="page-kicker">People Intelligence</span>
        <h4><i class="bi bi-graph-up-arrow me-2"></i>HR Analytics</h4>
        <p class="page-subtitle">A larger visual dashboard for workforce growth, tenure mix, attendance consistency, and leave demand.</p>
    </div>
    <div class="page-header-meta">
        <span class="page-chip page-chip--accent"><i class="bi bi-calendar3 me-1"></i><?= date('F Y') ?></span>
        <span class="page-chip"><i class="bi bi-diagram-3 me-1"></i><?= e($largestDepartment) ?></span>
    </div>
</div>

<?php require_once __DIR__.'/_analytics-cards.php'; ?>
<?php require_once __DIR__.'/_analytics-charts.php'; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
