<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$cid = company_id() ?? 1;
if (empty($u['employee_id'])) { echo '<div class="alert alert-warning">No employee profile linked.</div>'; require_once __DIR__.'/../../includes/footer.php'; exit; }

$month = $_GET['month'] ?? date('Y-m');
$start = $month.'-01';
$end = date('Y-m-t', strtotime($start));
$year = (int)date('Y', strtotime($start));
$pay = calculate_payroll((int)$u['employee_id'], $start, $end);
$th = calculate_13th_month((int)$u['employee_id'], $year);
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-wallet2 me-2"></i>My Payroll Summary</h4>
    <form method="get"><input type="month" class="form-control" name="month" value="<?= e($month) ?>"></form>
</div>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Payroll (<?= e(date('F Y', strtotime($start))) ?>)</div>
            <div class="card-body">
                <?php if(!$pay): ?><div class="text-muted">No payroll data.</div>
                <?php else: ?>
                <div><strong>Days Worked:</strong> <?= (int)$pay['days_worked'] ?></div>
                <div><strong>Gross Pay:</strong> <?= format_currency($pay['gross_pay']) ?></div>
                <div><strong>Total Earnings:</strong> <?= format_currency($pay['total_earnings']) ?></div>
                <div><strong>Total Deductions:</strong> <?= format_currency($pay['total_deductions']) ?></div>
                <div class="mt-2"><strong>Net Pay:</strong> <span class="text-success"><?= format_currency($pay['net_pay']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">13th Month Estimate (<?= (int)$year ?>)</div>
            <div class="card-body">
                <?php if(!$th): ?><div class="text-muted">No estimate available.</div>
                <?php else: ?>
                <div><strong>Total Basic Earned:</strong> <?= format_currency($th['total_basic_earned']) ?></div>
                <div><strong>Base 13th Month:</strong> <?= format_currency($th['thirteenth_month_amount']) ?></div>
                <div><strong>Less Absences:</strong> <?= format_currency($th['less_absences']) ?></div>
                <div><strong>Less Unpaid Leave:</strong> <?= format_currency($th['less_unpaid_leave']) ?></div>
                <div class="mt-2"><strong>Estimated Final:</strong> <span class="text-success"><?= format_currency($th['final_amount']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>

