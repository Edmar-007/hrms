<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_login();
require_role(['Admin', 'HR Officer', 'Payroll']);

$cid = company_id() ?? 1;

// Payroll periods
$currentPeriod = $pdo->query("SELECT period_type, cutoff_date FROM payroll_settings WHERE company_id = $cid LIMIT 1")->fetch();
$periodType = $currentPeriod['period_type'] ?? 'semi-monthly';
$cutoffDate = $currentPeriod['cutoff_date'] ?? '15';

// Tax settings
$stmt = $pdo->prepare("SELECT * FROM payroll_tax_settings WHERE company_id = ?");
$stmt->execute([$cid]);
$taxSettings = $stmt->fetch() ?: [];

// Deduction types
$deductions = $pdo->prepare("SELECT * FROM payroll_deduction_types WHERE company_id = ? ORDER BY name");
$deductions->execute([$cid]);
$deductions->fetchAll();

// Allowances
$allowances = $pdo->prepare("SELECT * FROM payroll_allowance_types WHERE company_id = ? ORDER BY name");
$allowances->execute([$cid]);
$allowances->fetchAll();

if($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_period') {
        $pdo->prepare("INSERT INTO payroll_settings (company_id, period_type, cutoff_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE period_type=VALUES(period_type), cutoff_date=VALUES(cutoff_date)")
            ->execute([$cid, $_POST['period_type'], $_POST['cutoff_date']]);
        $_SESSION['success'] = "Payroll period updated!";
    }

    if ($action === 'add_deduction') {
        $name = trim($_POST['deduction_name']);
        $isFixed = isset($_POST['is_fixed']) ? 1 : 0;
        $pdo->prepare("INSERT INTO payroll_deduction_types (company_id, name, is_fixed_amount) VALUES (?, ?, ?)")
            ->execute([$cid, $name, $isFixed]);
        $_SESSION['success'] = "Deduction type added!";
    }

    if ($action === 'add_allowance') {
        $name = trim($_POST['allowance_name']);
        $isFixed = isset($_POST['is_fixed']) ? 1 : 0;
        $pdo->prepare("INSERT INTO payroll_allowance_types (company_id, name, is_fixed_amount) VALUES (?, ?, ?)")
            ->execute([$cid, $name, $isFixed]);
        $_SESSION['success'] = "Allowance type added!";
    }
}
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar3 me-2"></i>Payroll Period Configuration
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_period">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Period Type</label>
                            <select name="period_type" class="form-select">
                                <option value="daily" <?= $periodType == 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $periodType == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="semi-monthly" <?= $periodType == 'semi-monthly' ? 'selected' : '' ?>>Semi-Monthly</option>
                                <option value="monthly" <?= $periodType == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cutoff Day</label>
                            <input type="number" name="cutoff_date" class="form-control" value="<?= $cutoffDate ?>" min="1" max="28" placeholder="15">
                            <small class="text-muted">Day of month (1-28)</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Save Payroll Period</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-calculator me-2"></i>Next Payroll
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <h3 class="text-primary fw-bold"><?= $totalEmployees ?> employees</h3>
                    <small class="text-muted">Ready for processing</small>
                </div>
                <a href="../payroll/" class="btn btn-primary w-100">Process Payroll</a>
                <hr>
                <small class="text-muted">
                    Next cutoff: <br>
                    <strong><?= date('M d', strtotime('last day of this month')) ?></strong>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-subtract me-2"></i>Deduction Types</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addDeductionModal">Add Deduction</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Name</th><th>Type</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic from DB -->
                            <tr>
                                <td>SSS</td>
                                <td><span class="badge bg-secondary">Fixed %</span></td>
                                <td><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>
                            </tr>
                            <tr>
                                <td>PhilHealth</td>
                                <td><span class="badge bg-secondary">Fixed %</span></td>
                                <td><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-plus-circle me-2"></i>Allowance Types</span>
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addAllowanceModal">Add Allowance</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Name</th><th>Type</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Transportation</td>
                                <td><span class="badge bg-success">Fixed</span></td>
                                <td><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>
                            </tr>
                            <tr>
                                <td>Meal Allowance</td>
                                <td><span class="badge bg-success">Fixed</span></td>
                                <td><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tax Settings -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-calculator me-2"></i>Tax Settings
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tax Method</label>
                <select class="form-select">
                    <option>Net Pay</option>
                    <option>Gross Pay</option>
                    <option>Tax Table</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tax Year</label>
                <input type="number" class="form-control" value="2024">
            </div>
            <div class="col-md-3">
                <label class="form-label">SSS Rate</label>
                <input type="text" class="form-control" value="4.7%">
            </div>
            <div class="col-md-3">
                <label class="form-label">PhilHealth Rate</label>
                <input type="text" class="form-control" value="2.5%">
            </div>
        </div>
        <button class="btn btn-primary mt-3">Save Tax Settings</button>
    </div>
</div>

<!-- Add Deduction Modal -->
<div class="modal fade" id="addDeductionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add_deduction">
                <div class="modal-header">
                    <h5>Add Deduction Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="deduction_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_fixed" id="ded_fixed">
                            <label class="form-check-label" for="ded_fixed">Fixed Amount (not %)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Allowance Modal -->
<div class="modal fade" id="addAllowanceModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add_allowance">
                <div class="modal-header">
                    <h5>Add Allowance Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="allowance_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_fixed" id="allow_fixed">
                            <label class="form-check-label" for="allow_fixed">Fixed Amount</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__."/../../includes/footer.php"; ?> 

