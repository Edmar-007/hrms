<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();

$cid = company_id() ?? 1;
$userId = $_SESSION['user']['id'] ?? null;
$employeeId = null;

// Get employee ID for this user
if ($userId) {
    $userEmp = $pdo->prepare("SELECT employee_id FROM users WHERE id = ? AND company_id = ?");
    $userEmp->execute([$userId, $cid]);
    $employeeId = $userEmp->fetchColumn();
}

// If admin/HR viewing another employee's balance
$viewingEmployeeId = (int)($_GET['employee_id'] ?? 0);
if ($viewingEmployeeId > 0 && in_array($_SESSION['user']['role'], ['Admin', 'HR Officer'])) {
    $employeeId = $viewingEmployeeId;
}

if (!$employeeId) {
    redirect('/modules/dashboard.php');
}

$year = (int)($_GET['year'] ?? date('Y'));

// Get employee info
$emp = $pdo->prepare("
    SELECT e.*, d.name as dept_name, p.name as pos_name
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN positions p ON p.id = e.position_id
    WHERE e.id = ? AND e.company_id = ?
");
$emp->execute([$employeeId, $cid]);
$employee = $emp->fetch();

if (!$employee) {
    redirect('/modules/dashboard.php');
}

// Get all leave balances for this employee for the year
$balances = $pdo->prepare("
    SELECT elb.*,  lt.name as leave_type_name, lt.is_paid
    FROM employee_leave_balance elb
    JOIN leave_types lt ON lt.id = elb.leave_type_id
    WHERE elb.company_id = ? AND elb.employee_id = ? AND elb.year = ?
    ORDER BY lt.name ASC
");
$balances->execute([$cid, $employeeId, $year]);
$allBalances = $balances->fetchAll();

// Calculate totals
$totalOpening = array_sum(array_column($allBalances, 'opening_balance'));
$totalUsed = array_sum(array_column($allBalances, 'used'));
$totalCarried = array_sum(array_column($allBalances, 'carried_over'));
$totalRemaining = array_sum(array_column($allBalances, 'remaining'));
?>

<div class="page-header">
    <h4><i class="bi bi-calendar-check me-2"></i>My Leave Balances</h4>
</div>

<!-- Employee Info Card -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-9">
                        <h5><?= e($employee['first_name'].' '.$employee['last_name']) ?></h5>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-muted" style="width:120px">Employee ID:</td>
                                <td><?= e($employee['employee_code']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Department:</td>
                                <td><?= e($employee['dept_name'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Position:</td>
                                <td><?= e($employee['pos_name'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Hired:</td>
                                <td><?= date('M d, Y', strtotime($employee['hire_date'] ?? 'now')) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="stat-card">
                            <div class="stat-label">Year</div>
                            <div class="stat-value" style="font-size:2.5rem"><?= $year ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="col-md-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="stat-card primary">
                    <h6>Opening Balance</h6>
                    <h2><?= $totalOpening ?></h2>
                    <small>days</small>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card warning">
                    <h6>Used</h6>
                    <h2><?= $totalUsed ?></h2>
                    <small>days</small>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card success">
                    <h6>Remaining</h6>
                    <h2><?= $totalRemaining ?></h2>
                    <small>days</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Balances by Type -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Leave Balance Breakdown</h5>
        <select class="form-select" style="width:auto" onchange="window.location.href='?employee_id=<?= $employeeId?>&year='+this.value">
            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
            <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="card-body p-0">
        <?php if (empty($allBalances)): ?>
        <div class="empty-state p-4">
            <i class="bi bi-inbox"></i>
            <p>No leave balance data available</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th class="text-center">Opening Balance</th>
                        <th class="text-center">Carried Over</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Remaining</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allBalances as $balance):
                        $usagePercent = $balance['opening_balance'] > 0 ? ($balance['used'] / ($balance['opening_balance'] + $balance['carried_over']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($balance['leave_type_name']) ?></strong>
                            <br>
                            <small class="badge <?= $balance['is_paid'] ? 'bg-success' : 'bg-warning' ?>">
                                <?= $balance['is_paid'] ? 'Paid' : 'Unpaid' ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info"><?= $balance['opening_balance'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($balance['carried_over'] > 0): ?>
                            <span class="badge bg-secondary"><?= $balance['carried_over'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning"><?= $balance['used'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success" style="font-size:1.1rem"><?= $balance['remaining'] ?></span>
                        </td>
                        <td>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar progress-bar-striped" style="width:<?= $usagePercent ?>%">
                                    <?= round($usagePercent) ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Totals -->
                    <tr class="table-light fw-bold">
                        <td>TOTAL</td>
                        <td class="text-center"><?= $totalOpening ?></td>
                        <td class="text-center"><?= $totalCarried ?></td>
                        <td class="text-center"><?= $totalUsed ?></td>
                        <td class="text-center text-success"><?= $totalRemaining ?></td>
                        <td>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar progress-bar-striped bg-success"
                                     style="width:<?= (($totalOpening + $totalCarried) > 0 ? ($totalUsed / ($totalOpening + $totalCarried) * 100) : 0) ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Action Buttons -->
<div class="mt-4">
    <a href="/modules/leaves/add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Submit Leave Request
    </a>
    <a href="/modules/leaves/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-file-text me-2"></i>View Leave History
    </a>
</div>

<!-- Notes -->
<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Note:</strong> Leave balance is automatically calculated based on approved leave requests.
    Balances carry over to the next year up to the company's carryover policy limit.
</div>

<style>
.stat-card {
    padding: 20px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
}
.stat-card.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.stat-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.stat-card.success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.stat-card h6 {
    font-size: 0.85rem;
    text-transform: uppercase;
    opacity: 0.9;
    margin-bottom: 10px;
}
.stat-card h2 {
    margin: 0;
    font-weight: bold;
}
.stat-card small {
    display: block;
    font-size: 0.75rem;
    opacity: 0.8;
    margin-top: 5px;
}

.stat-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
}
.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #495057;
}
</style>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
