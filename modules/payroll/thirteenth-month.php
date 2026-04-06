<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/payroll-helpers.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$year = (int)($_GET['year'] ?? date('Y'));

// Handle compute action
if (is_post() && ($_POST['action'] ?? '') === 'compute') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'CSRF token invalid';
    } else {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $result = save_13th_month_record($pdo, $cid, $employeeId, $year);
            if ($result === true) {
                redirect("/modules/payroll/thirteenth-month.php?year=$year&msg=computed");
            } else {
                $error = $result;
            }
        }
    }
}

// Handle finalize status
if (is_post() && ($_POST['action'] ?? '') === 'finalize') {
    if (verify_csrf($_POST['csrf'] ?? '')) {
        finalize_13th_month_record($pdo, (int)$_POST['record_id'], $cid);
        redirect("/modules/payroll/thirteenth-month.php?year=$year&msg=finalized");
    }
}

// Fetch data
$allRecords = get_13th_month_records($pdo, $cid, $year);
$totalFinalAmount = array_sum(array_column($allRecords, 'final_amount'));
$pendingEmployees = get_pending_employees_for_13th($pdo, $cid, $year);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-calendar-check me-2"></i>13th Month Pay</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem">Year: <strong><?= $year ?></strong></p>
    </div>
    <a href="<?= url('/modules/payroll/index.php') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Payroll
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= ($_GET['msg'] === 'computed') ? '13th month pay computed!' : 'Record finalized!' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <?php include __DIR__.'/_thirteenth-month-table.php'; ?>
        
        <!-- Computation Formula -->
        <div class="card mt-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Computation Method</h6></div>
            <div class="card-body small">
                <div class="formula-box p-3 bg-light rounded">
                    <code>13th Month Pay = (Total Basic Salary Earned ÷ 12) - Absences - Unpaid leave</code>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php include __DIR__.'/_thirteenth-month-sidebar.php'; ?>
    </div>
</div>

<?php foreach ($allRecords as $rec): render_13th_month_modal($rec); endforeach; ?>
<?php render_payroll_styles(); ?>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
