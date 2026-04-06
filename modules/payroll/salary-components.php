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
$structId = (int)($_GET['struct_id'] ?? 0);

// Validate structure exists
$struct = $pdo->prepare("SELECT * FROM salary_structures WHERE id = ? AND company_id = ?");
$struct->execute([$structId, $cid]);
$structure = $struct->fetch();
if (!$structure) redirect('/modules/payroll/salary-structure.php?error=not_found');

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'add_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $componentType = $_POST['component_type'] ?? null;
        $name = trim($_POST['name'] ?? '');
        if (!empty($name) && in_array($componentType, ['earning', 'deduction'])) {
            try {
                $st = $pdo->prepare("INSERT INTO salary_components (company_id, salary_structure_id, component_type, name, type, value, order_seq) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $st->execute([$cid, $structId, $componentType, $name, $_POST['type'] ?? 'fixed', (float)($_POST['value'] ?? 0), (int)($_POST['order_seq'] ?? 0)]);
                redirect("/modules/payroll/salary-components.php?struct_id=$structId&msg=added");
            } catch (PDOException $e) { $error = 'Error adding component'; }
        }
    }

    if ($action === 'update_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $st = $pdo->prepare("UPDATE salary_components SET name = ?, type = ?, value = ?, order_seq = ? WHERE id = ? AND salary_structure_id = ? AND company_id = ?");
        $st->execute([trim($_POST['name'] ?? ''), $_POST['type'] ?? 'fixed', (float)($_POST['value'] ?? 0), (int)($_POST['order_seq'] ?? 0), (int)$_POST['comp_id'], $structId, $cid]);
        redirect("/modules/payroll/salary-components.php?struct_id=$structId&msg=updated");
    }

    if ($action === 'delete_component' && verify_csrf($_POST['csrf'] ?? '')) {
        $st = $pdo->prepare("DELETE FROM salary_components WHERE id = ? AND salary_structure_id = ? AND company_id = ?");
        $st->execute([(int)$_POST['comp_id'], $structId, $cid]);
        redirect("/modules/payroll/salary-components.php?struct_id=$structId&msg=deleted");
    }
}

$allComponents = get_salary_components($pdo, $cid, $structId);
$earnings = array_filter($allComponents, fn($c) => $c['component_type'] === 'earning');
$deductions = array_filter($allComponents, fn($c) => $c['component_type'] === 'deduction');
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-gear me-2"></i>Salary Components</h4>
        <p class="text-muted mb-0">Structure: <strong><?= e($structure['name']) ?></strong></p>
    </div>
    <a href="<?= url('/modules/payroll/salary-structure.php') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>Component <?= e($_GET['msg']) ?>!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <?php render_components_table($earnings, 'earning'); ?>
        <?php render_components_table($deductions, 'deduction'); ?>
    </div>
    <div class="col-lg-4">
        <?php include __DIR__.'/_add-component-sidebar.php'; ?>
    </div>
</div>

<?php foreach ($allComponents as $comp): render_component_edit_modal($comp); endforeach; ?>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
