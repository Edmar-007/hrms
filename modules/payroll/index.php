<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

// Get month filter and validate format
$month = $_GET['month'] ?? date('Y-m');
if(!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// Check SaaS mode
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Get employees with salary info (use prepared statement for safety)
if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')=?) as days_worked
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.company_id=? AND e.status='active'
        ORDER BY e.last_name, e.first_name");
    $st->execute([$month, $cid]);
    $rows = $st->fetchAll();
} else {
    $st = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name,
        (SELECT COUNT(*) FROM attendance a WHERE a.employee_id=e.id AND DATE_FORMAT(a.date,'%Y-%m')=?) as days_worked
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.status='active'
        ORDER BY e.last_name, e.first_name");
    $st->execute([$month]);
    $rows = $st->fetchAll();
}

$totalPayroll = 0;
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-cash-stack me-2"></i>Payroll</h4>
    <form class="d-flex gap-2" method="get">
        <input type="month" class="form-control" name="month" value="<?= e($month) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i></button>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Basic Salary</th>
                    <th>Days Worked</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No employees found</td></tr>
            <?php else: foreach($rows as $r): 
                $workingDays = 22; // Assume 22 working days per month
                $dailyRate = $r['basic_salary'] / $workingDays;
                $grossPay = $dailyRate * $r['days_worked'];
                $deductions = $grossPay * 0.1; // 10% deductions (tax, etc)
                $netPay = $grossPay - $deductions;
                $totalPayroll += $netPay;
            ?>
                <tr>
                    <td><i class="bi bi-person-circle me-2 text-muted"></i><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                    <td><?= e($r['dept_name'] ?? '-') ?></td>
                    <td>₱<?= number_format($r['basic_salary'], 2) ?></td>
                    <td><span class="badge bg-info"><?= $r['days_worked'] ?> days</span></td>
                    <td class="text-danger">-₱<?= number_format($deductions, 2) ?></td>
                    <td class="fw-bold text-success">₱<?= number_format($netPay, 2) ?></td>
                    <td>
                        <a href="payslip.php?id=<?= $r['id'] ?>&month=<?= $month ?>" class="btn btn-sm btn-info">
                            <i class="bi bi-file-earmark-text"></i> Payslip
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-dark">
                    <td colspan="5" class="text-end fw-bold">Total Payroll:</td>
                    <td colspan="2" class="fw-bold text-success">₱<?= number_format($totalPayroll, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <h6><i class="bi bi-people me-2"></i>Active Employees</h6>
            <h2><?= count($rows) ?></h2>
            <i class="bi bi-people-fill icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card success">
            <h6><i class="bi bi-cash me-2"></i>Total Payroll</h6>
            <h2>₱<?= number_format($totalPayroll, 0) ?></h2>
            <i class="bi bi-cash-stack icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card info">
            <h6><i class="bi bi-calendar me-2"></i>Period</h6>
            <h2><?= date('M Y', strtotime($month.'-01')) ?></h2>
            <i class="bi bi-calendar3 icon"></i>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
