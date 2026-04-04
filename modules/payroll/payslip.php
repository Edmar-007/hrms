<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$id = intval($_GET['id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');

$emp = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name 
    FROM employees e 
    LEFT JOIN departments d ON d.id=e.department_id 
    LEFT JOIN positions p ON p.id=e.position_id 
    WHERE e.id=?");
$emp->execute([$id]);
$emp = $emp->fetch();

if(!$emp) { header("Location: index.php"); exit; }

$periodStart = $month . '-01';
$periodEnd   = date('Y-m-t', strtotime($periodStart));

// Try to load from payroll_records first
$payrollRecord = null;
$payrollItems  = [];
$fromRecord    = false;

$prSt = $pdo->prepare("SELECT * FROM payroll_records WHERE employee_id = ? AND payroll_period_start = ?");
$prSt->execute([$id, $periodStart]);
$payrollRecord = $prSt->fetch();

if ($payrollRecord) {
    $piSt = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_record_id = ? ORDER BY component_type DESC, id ASC");
    $piSt->execute([$payrollRecord['id']]);
    $payrollItems = $piSt->fetchAll();
    $fromRecord = true;

    $grossPay        = $payrollRecord['total_earnings'];
    $totalDeductions = $payrollRecord['total_deductions'];
    $netPay          = $payrollRecord['net_pay'];
    // Calculate daysWorked from items or attendance for display
    $daysWorked = $pdo->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE employee_id=? AND DATE_FORMAT(date,'%Y-%m')=?");
    $daysWorked->execute([$id, $month]);
    $daysWorked = $daysWorked->fetch()['cnt'];
    $workingDays = 22;
    $dailyRate   = $emp['basic_salary'] / $workingDays;
} else {
    // Real-time calculation fallback
    $daysWorked = $pdo->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE employee_id=? AND DATE_FORMAT(date,'%Y-%m')=?");
    $daysWorked->execute([$id, $month]);
    $daysWorked = $daysWorked->fetch()['cnt'];

    $workingDays = 22;
    $dailyRate   = $emp['basic_salary'] / $workingDays;
    $grossPay    = $dailyRate * $daysWorked;
    $sss         = $grossPay * 0.045;
    $philhealth  = $grossPay * 0.035;
    $pagibig     = 100;
    $tax         = $grossPay * 0.02;
    $totalDeductions = $sss + $philhealth + $pagibig + $tax;
    $netPay      = $grossPay - $totalDeductions;
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-file-earmark-text me-2"></i>Payslip</h4>
    <div>
        <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-2"></i>Print</button>
        <a href="index.php?month=<?= $month ?>" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-building me-2"></i>Microfinance HRMS</span>
        <span>Pay Period: <?= date('F Y', strtotime($month.'-01')) ?></span>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h5><?= e($emp['first_name'].' '.$emp['last_name']) ?></h5>
                <p class="text-muted mb-1"><i class="bi bi-hash me-2"></i><?= e($emp['employee_code']) ?></p>
                <p class="text-muted mb-1"><i class="bi bi-building me-2"></i><?= e($emp['dept_name'] ?? 'N/A') ?></p>
                <p class="text-muted mb-0"><i class="bi bi-briefcase me-2"></i><?= e($emp['pos_name'] ?? 'N/A') ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Basic Salary:</strong> ₱<?= number_format($emp['basic_salary'], 2) ?></p>
                <p class="mb-1"><strong>Daily Rate:</strong> ₱<?= number_format($dailyRate, 2) ?></p>
                <p class="mb-0"><strong>Days Worked:</strong> <?= $daysWorked ?> / <?= $workingDays ?></p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-header bg-success text-white"><i class="bi bi-plus-circle me-2"></i>Earnings</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <?php if ($fromRecord && !empty($payrollItems)): ?>
                                <?php foreach ($payrollItems as $item): if ($item['component_type'] !== 'earning') continue; ?>
                                <tr><td><?= e($item['component_name']) ?></td><td class="text-end">₱<?= number_format($item['amount'], 2) ?></td></tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <tr><td>Basic Pay (<?= $daysWorked ?> days)</td><td class="text-end">₱<?= number_format($grossPay, 2) ?></td></tr>
                            <?php endif; ?>
                            <tr class="table-success fw-bold"><td>Gross Pay</td><td class="text-end">₱<?= number_format($grossPay, 2) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-header bg-danger text-white"><i class="bi bi-dash-circle me-2"></i>Deductions</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <?php if ($fromRecord && !empty($payrollItems)): ?>
                                <?php foreach ($payrollItems as $item): if ($item['component_type'] !== 'deduction') continue; ?>
                                <tr><td><?= e($item['component_name']) ?></td><td class="text-end">₱<?= number_format($item['amount'], 2) ?></td></tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <tr><td>SSS (4.5%)</td><td class="text-end">₱<?= number_format($sss, 2) ?></td></tr>
                            <tr><td>PhilHealth (3.5%)</td><td class="text-end">₱<?= number_format($philhealth, 2) ?></td></tr>
                            <tr><td>Pag-IBIG</td><td class="text-end">₱<?= number_format($pagibig, 2) ?></td></tr>
                            <tr><td>Withholding Tax (2%)</td><td class="text-end">₱<?= number_format($tax, 2) ?></td></tr>
                            <?php endif; ?>
                            <tr class="table-danger fw-bold"><td>Total Deductions</td><td class="text-end">₱<?= number_format($totalDeductions, 2) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($fromRecord): ?>
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i>
            Payslip generated from processed payroll record
            <?php if ($payrollRecord['processed_at']): ?>
                on <?= date('M j, Y', strtotime($payrollRecord['processed_at'])) ?>.
            <?php else: ?>
                .
            <?php endif; ?>
            Status: <span class="badge bg-<?= $payrollRecord['status'] === 'paid' ? 'success' : ($payrollRecord['status'] === 'processed' ? 'primary' : 'secondary') ?>"><?= ucfirst($payrollRecord['status']) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="alert alert-success mt-4 text-center">
            <h4 class="mb-0">Net Pay: <strong>₱<?= number_format($netPay, 2) ?></strong></h4>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
