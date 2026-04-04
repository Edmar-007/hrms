<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$year = (int)($_GET['year'] ?? date('Y'));

// Handle compute action
if (is_post() && $_POST['action'] === 'compute') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'CSRF token invalid';
    } else {
        $employeeId = (int)$_POST['employee_id'] ?? 0;

        if ($employeeId > 0) {
            // Calculate 13th month for this employee
            $calc = calculate_13th_month($employeeId, $year);

            if ($calc) {
                try {
                    // Insert or update 13th month record
                    $st = $pdo->prepare("
                        INSERT INTO thirteenth_month_records
                        (company_id, employee_id, year, total_basic_earned, thirteenth_month_amount,
                         less_absences, less_unpaid_leave, final_amount, computation_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
                        ON DUPLICATE KEY UPDATE
                        total_basic_earned = VALUES(total_basic_earned),
                        thirteenth_month_amount = VALUES(thirteenth_month_amount),
                        less_absences = VALUES(less_absences),
                        less_unpaid_leave = VALUES(less_unpaid_leave),
                        final_amount = VALUES(final_amount),
                        computation_date = VALUES(computation_date)
                    ");
                    $st->execute([
                        $cid, $employeeId, $year,
                        $calc['total_basic_earned'],
                        $calc['thirteenth_month_amount'],
                        $calc['less_absences'],
                        $calc['less_unpaid_leave'],
                        $calc['final_amount'],
                        date('Y-m-d')
                    ]);

                    log_activity('compute_13th_month', 'thirteenth_month_records', $_POST['employee_id'],
                        ['year' => $year, 'amount' => $calc['final_amount']]);

                    redirect("?year=$year&msg=computed");
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Could not calculate 13th month pay';
            }
        }
    }
}

// Handle finalize status
if (is_post() && $_POST['action'] === 'finalize') {
    if (verify_csrf($_POST['csrf'] ?? '')) {
        $recordId = (int)$_POST['record_id'];
        $st = $pdo->prepare("
            UPDATE thirteenth_month_records
            SET status = 'finalized'
            WHERE id = ? AND company_id = ?
        ");
        $st->execute([$recordId, $cid]);
        redirect("?year=$year&msg=finalized");
    }
}

// Get all 13th month records for this year
$records = $pdo->prepare("
    SELECT tm.*, e.first_name, e.last_name, e.employee_code,
           d.name as dept_name, p.name as pos_name
    FROM thirteenth_month_records tm
    JOIN employees e ON e.id = tm.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN positions p ON p.id = e.position_id
    WHERE tm.company_id = ? AND tm.year = ?
    ORDER BY e.last_name, e.first_name
");
$records->execute([$cid, $year]);
$allRecords = $records->fetchAll();

// Calculate totals
$totalFinalAmount = array_sum(array_column($allRecords, 'final_amount'));

// Get all employees not yet computed
$employees = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.employee_code
    FROM employees e
    WHERE e.company_id = ? AND e.status = 'active'
    AND e.id NOT IN (
        SELECT DISTINCT employee_id FROM thirteenth_month_records
        WHERE company_id = ? AND year = ?
    )
    ORDER BY e.last_name, e.first_name
");
$employees->execute([$cid, $cid, $year]);
$pendingEmployees = $employees->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-calendar-check me-2"></i>13th Month Pay</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem">Year: <strong><?= $year ?></strong></p>
    </div>
    <a href="/modules/payroll/index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Payroll
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?= $_GET['msg'] === 'computed' ? '13th month pay computed successfully!' :
            ($_GET['msg'] === 'finalized' ? 'Record finalized!' : '') ?>
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
        <!-- Computed Records -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Computed 13th Month Pay (<?= count($allRecords) ?> employees)</h5>
                <span class="badge bg-primary"><?= $year ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allRecords)): ?>
                <div class="empty-state p-4 text-center">
                    <i class="bi bi-inbox"></i>
                    <p>No 13th month pay computed yet for <?= $year ?></p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Months Worked</th>
                                <th class="text-end">Basic Earned</th>
                                <th class="text-end">13th Month</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Final Amount</th>
                                <th>Status</th>
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRecords as $rec):
                                $totalDed = $rec['less_absences'] + $rec['less_unpaid_leave'];
                            ?>
                            <tr>
                                <td>
                                    <div><?= e($rec['first_name'].' '.$rec['last_name']) ?></div>
                                    <small class="text-muted"><?= e($rec['employee_code']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info">-</span>
                                </td>
                                <td class="text-end">₱<?= number_format($rec['total_basic_earned'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($rec['thirteenth_month_amount'], 2) ?></td>
                                <td class="text-end text-danger">-₱<?= number_format($totalDed, 2) ?></td>
                                <td class="text-end fw-bold text-success">₱<?= number_format($rec['final_amount'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $rec['status'] === 'finalized' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($rec['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#detailModal<?= $rec['id'] ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($rec['status'] === 'draft'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="finalize">
                                        <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link p-0" title="Finalize">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <td colspan="4" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bold text-warning">-₱<?= number_format(array_sum(array_map(function($r) { return $r['less_absences'] + $r['less_unpaid_leave']; }, $allRecords)), 2) ?></td>
                                <td class="text-end fw-bold text-success">₱<?= number_format($totalFinalAmount, 2) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Computation Formula -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Computation Method</h6>
            </div>
            <div class="card-body small">
                <div class="formula-box p-3 bg-light rounded">
                    <code>13th Month Pay = (Total Basic Salary Earned ÷ 12) - Absences - Unpaid leave</code>
                </div>
                <p class="mt-3 mb-0">
                    <strong>Where:</strong>
                    <ul class="mt-2 mb-0">
                        <li><span class="badge bg-info">Total Basic Earned</span> = Sum of monthly basic salaries for the year</li>
                        <li><span class="badge bg-warning">Absences</span> = Number of approved absence days × daily rate</li>
                        <li><span class="badge bg-danger">Unpaid Leave</span> = Number of approved unpaid leave days × daily rate</li>
                    </ul>
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Compute for Employee -->
        <?php if (!empty($pendingEmployees)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Compute 13th Month</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="compute">

                    <div class="mb-3">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($pendingEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= e($emp['first_name'].' '.$emp['last_name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calculator me-2"></i>Compute
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Year Selector -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Select Year</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <div class="col-6">
                        <a href="?year=<?= $y ?>" class="btn btn-outline-secondary w-100 <?= $year === $y ? 'active' : '' ?>">
                            <?= $y ?>
                        </a>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Summary</h6>
            </div>
            <div class="card-body">
                <div class="stat-item mb-3">
                    <div class="stat-label">Computed Employees</div>
                    <div class="stat-value text-primary"><?= count($allRecords) ?></div>
                </div>
                <div class="stat-item mb-3">
                    <div class="stat-label">Pending Employees</div>
                    <div class="stat-value text-warning"><?= count($pendingEmployees) ?></div>
                </div>
                <div class="stat-item border-top pt-3">
                    <div class="stat-label">Total Final Amount</div>
                    <div class="stat-value text-success fw-bold">₱<?= number_format($totalFinalAmount, 2) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modals -->
<?php foreach ($allRecords as $rec):
    $totalDed = $rec['less_absences'] + $rec['less_unpaid_leave'];
?>
<div class="modal fade" id="detailModal<?= $rec['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= e($rec['first_name'].' '.$rec['last_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="computation-breakdown">
                    <table class="table table-sm">
                        <tr>
                            <td>Employee ID:</td>
                            <td><?= e($rec['employee_code']) ?></td>
                        </tr>
                        <tr>
                            <td>Year:</td>
                            <td><?= $rec['year'] ?></td>
                        </tr>
                        <tr class="table-light">
                            <td><strong>Total Basic Earned:</strong></td>
                            <td class="text-end"><strong>₱<?= number_format($rec['total_basic_earned'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <td>÷ 12 months =</td>
                            <td class="text-end">₱<?= number_format($rec['thirteenth_month_amount'], 2) ?></td>
                        </tr>
                        <tr class="table-warning">
                            <td>Less: Absences</td>
                            <td class="text-end text-danger">-₱<?= number_format($rec['less_absences'], 2) ?></td>
                        </tr>
                        <tr class="table-warning">
                            <td>Less: Unpaid Leave</td>
                            <td class="text-end text-danger">-₱<?= number_format($rec['less_unpaid_leave'], 2) ?></td>
                        </tr>
                        <tr class="table-success">
                            <td><strong>Final Payable Amount:</strong></td>
                            <td class="text-end"><strong class="text-success">₱<?= number_format($rec['final_amount'], 2) ?></strong></td>
                        </tr>
                    </table>
                </div>
                <div class="mt-3 pt-3 border-top small text-muted">
                    <strong>Computed on:</strong> <?= date('M d, Y', strtotime($rec['computation_date'])) ?>
                    <br>
                    <strong>Status:</strong> <span class="badge <?= $rec['status'] === 'finalized' ? 'bg-success' : 'bg-warning' ?>">
                        <?= ucfirst($rec['status']) ?>
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<style>
.stat-item {
    text-align: center;
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
.formula-box {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    border-left: 3px solid #0d6efd;
}
.computation-breakdown table tr {
    font-size: 0.9rem;
}
</style>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
