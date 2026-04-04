<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$structId = (int)($_GET['struct_id'] ?? 0);

// Validate structure exists
$struct = $pdo->prepare("SELECT * FROM salary_structures WHERE id = ? AND company_id = ?");
$struct->execute([$structId, $cid]);
$structure = $struct->fetch();

if (!$structure) {
    redirect('/modules/payroll/salary-structure.php?error=not_found');
}

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'add_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $componentType = $_POST['component_type'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'fixed';
        $value = (float)($_POST['value'] ?? 0);
        $orderSeq = (int)($_POST['order_seq'] ?? 0);

        if (!empty($name) && in_array($componentType, ['earning', 'deduction'])) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO salary_components
                    (company_id, salary_structure_id, component_type, name, type, value, order_seq)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $st->execute([$cid, $structId, $componentType, $name, $type, $value, $orderSeq]);
                redirect("?struct_id=$structId&msg=added");
            } catch (PDOException $e) {
                $error = 'Error adding component';
            }
        }
    }

    if ($action === 'update_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $compId = (int)$_POST['comp_id'];
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'fixed';
        $value = (float)($_POST['value'] ?? 0);
        $orderSeq = (int)($_POST['order_seq'] ?? 0);

        $st = $pdo->prepare("
            UPDATE salary_components
            SET name = ?, type = ?, value = ?, order_seq = ?
            WHERE id = ? AND salary_structure_id = ? AND company_id = ?
        ");
        $st->execute([$name, $type, $value, $orderSeq, $compId, $structId, $cid]);
        redirect("?struct_id=$structId&msg=updated");
    }

    if ($action === 'delete_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $compId = (int)$_POST['comp_id'];
        $st = $pdo->prepare("
            DELETE FROM salary_components
            WHERE id = ? AND salary_structure_id = ? AND company_id = ?
        ");
        $st->execute([$compId, $structId, $cid]);
        redirect("?struct_id=$structId&msg=deleted");
    }
}

// Get all components for this structure
$components = $pdo->prepare("
    SELECT * FROM salary_components
    WHERE company_id = ? AND salary_structure_id = ?
    ORDER BY component_type DESC, order_seq ASC, name ASC
");
$components->execute([$cid, $structId]);
$allComponents = $components->fetchAll();

// Group by type
$earnings = array_filter($allComponents, fn($c) => $c['component_type'] === 'earning');
$deductions = array_filter($allComponents, fn($c) => $c['component_type'] === 'deduction');
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-gear me-2"></i>Salary Components</h4>
        <p class="text-muted mb-0">Structure: <strong><?= e($structure['name']) ?></strong></p>
    </div>
    <a href="/modules/payroll/salary-structure.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'added' ? 'Component added!' : ($_GET['msg'] === 'updated' ? 'Component updated!' : ($_GET['msg'] === 'deleted' ? 'Component deleted!' : '')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Earnings Section -->
        <div class="card mb-4">
            <div class="card-header bg-success bg-opacity-10">
                <h5 class="mb-0"><i class="bi bi-plus-circle text-success me-2"></i>Earnings</h5>
            </div>
            <div class="card-body">
                <?php if (empty($earnings)): ?>
                <p class="text-muted mb-0">No earnings configured</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earnings as $comp): ?>
                            <tr>
                                <td><?= e($comp['name']) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($comp['type']) ?></span></td>
                                <td>
                                    <?= $comp['type'] === 'percentage' ? $comp['value'].'%' : '₱'.number_format($comp['value'], 2) ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editModal<?= $comp['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this component?')">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_component">
                                        <input type="hidden" name="comp_id" value="<?= $comp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" style="margin-left:8px">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deductions Section -->
        <div class="card mb-4">
            <div class="card-header bg-danger bg-opacity-10">
                <h5 class="mb-0"><i class="bi bi-dash-circle text-danger me-2"></i>Deductions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($deductions)): ?>
                <p class="text-muted mb-0">No deductions configured</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deductions as $comp): ?>
                            <tr>
                                <td><?= e($comp['name']) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($comp['type']) ?></span></td>
                                <td>
                                    <?= $comp['type'] === 'percentage' ? $comp['value'].'%' : '₱'.number_format($comp['value'], 2) ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editModal<?= $comp['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this component?')">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_component">
                                        <input type="hidden" name="comp_id" value="<?= $comp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" style="margin-left:8px">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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

    <div class="col-lg-4">
        <!-- Quick Add Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Add Component</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add_component">

                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="component_type" required>
                            <option value="">-- Select --</option>
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Component Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., Basic Salary, SSS" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Value Type</label>
                        <select class="form-select" name="type" required>
                            <option value="fixed">Fixed Amount (₱)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Value</label>
                        <input type="number" class="form-control" name="value" step="0.01" placeholder="0.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Order (Priority)</label>
                        <input type="number" class="form-control" name="order_seq" value="0">
                        <small class="text-muted">Lower number = displayed first</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-2"></i>Add Component
                    </button>
                </form>
            </div>
        </div>

        <!-- Template Suggestions -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Common Components</h6>
            </div>
            <div class="card-body text-muted small">
                <strong>Earnings:</strong>
                <ul class="mb-3">
                    <li>Basic Salary</li>
                    <li>Transport Allowance</li>
                    <li>Phone Allowance</li>
                    <li>HRA / Housing</li>
                </ul>

                <strong>Deductions:</strong>
                <ul>
                    <li>SSS (4.5%)</li>
                    <li>PhilHealth (3.5%)</li>
                    <li>Pag-IBIG (2%)</li>
                    <li>Withholding Tax</li>
                    <li>Loans</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modals -->
<?php foreach ($allComponents as $comp): ?>
<div class="modal fade" id="editModal<?= $comp['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="update_component">
                    <input type="hidden" name="comp_id" value="<?= $comp['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Component Name</label>
                        <input type="text" class="form-control" name="name" value="<?= e($comp['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Value Type</label>
                        <select class="form-select" name="type" required>
                            <option value="fixed" <?= $comp['type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                            <option value="percentage" <?= $comp['type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Value</label>
                        <input type="number" class="form-control" name="value" value="<?= $comp['value'] ?>" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Order (Priority)</label>
                        <input type="number" class="form-control" name="order_seq" value="<?= $comp['order_seq'] ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
