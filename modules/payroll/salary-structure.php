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
            } catch (PDOException $e) { $error = 'Error creating structure'; }
        }
    }

    if ($action === 'toggle' && verify_csrf($_POST['csrf'] ?? '')) {
        $st = $pdo->prepare("UPDATE salary_structures SET is_active = NOT is_active WHERE id = ? AND company_id = ?");
        $st->execute([(int)$_POST['id'], $cid]);
        redirect('/modules/payroll/salary-structure.php?msg=updated');
    }
}

$allStructures = get_salary_structures($pdo, $cid);
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
    <?= ($_GET['msg'] === 'created') ? 'Salary structure created!' : 'Updated successfully!' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php include __DIR__.'/_salary-structure-cards.php'; ?>

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
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description"></textarea>
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

<?php render_payroll_styles(); ?>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
