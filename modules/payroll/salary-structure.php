<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'create' && verify_csrf($_POST['csrf'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($name)) {
            try {
                $st = $pdo->prepare("INSERT INTO salary_structures (company_id, name, description) VALUES (?, ?, ?)");
                $st->execute([$cid, $name, $description]);
                redirect('/modules/payroll/salary-structure.php?msg=created');
            } catch (PDOException $e) {
                $error = 'Error creating structure';
            }
        }
    }

    if ($action === 'toggle' && verify_csrf($_POST['csrf'] ?? '')) {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare("UPDATE salary_structures SET is_active = NOT is_active WHERE id = ? AND company_id = ?");
        $st->execute([$id, $cid]);
        redirect('/modules/payroll/salary-structure.php?msg=updated');
    }
}

// Get all salary structures
$structures = $pdo->prepare("
    SELECT ss.*,
           COUNT(sc.id) as component_count,
           COUNT(DISTINCT es.employee_id) as employee_count
    FROM salary_structures ss
    LEFT JOIN salary_components sc ON sc.salary_structure_id = ss.id
    LEFT JOIN (
        SELECT DISTINCT salary_structure_id, employee_id FROM (
            SELECT salary_structure_id FROM employees WHERE salary_structure_id IS NOT NULL
        ) e
    ) es ON es.salary_structure_id = ss.id
    WHERE ss.company_id = ?
    GROUP BY ss.id
    ORDER BY ss.created_at DESC
");
$structures->execute([$cid]);
$allStructures = $structures->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calculator me-2"></i>Salary Structures</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-2"></i>New Structure
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'created' ? 'Salary structure created successfully!' : $_GET['msg'] === 'updated' ? 'Updated successfully!' : '' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <?php if (empty($allStructures)): ?>
    <div class="col-md-8">
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>No salary structures defined yet</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                Create First Structure
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12">
        <div class="row g-3">
            <?php foreach ($allStructures as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0"><?= e($s['name']) ?></h6>
                            <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-3"><?= e($s['description']) ?></p>

                        <div class="stats-row mb-3">
                            <div class="stat-item">
                                <div class="stat-value"><?= $s['component_count'] ?></div>
                                <div class="stat-label">Components</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $s['employee_count'] ?></div>
                                <div class="stat-label">Employees</div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="/modules/payroll/salary-components.php?struct_id=<?= $s['id'] ?>" class="btn btn-sm btn-info flex-grow-1">
                                <i class="bi bi-gear me-1"></i>Configure
                            </a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-<?= $s['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Salary Structure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Structure Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., Standard, Management" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of this structure"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stats-row {
    display: flex;
    gap: 1rem;
}
.stat-item {
    flex: 1;
    text-align: center;
}
.stat-value {
    font-weight: bold;
    font-size: 1.5rem;
    color: #6c757d;
}
.stat-label {
    font-size: 0.75rem;
    color: #999;
}
</style>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
