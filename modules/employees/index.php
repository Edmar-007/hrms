<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$canEdit = in_array($u['role'], ['Admin', 'HR Officer']);

// Handle delete (POST with CSRF to prevent CSRF attacks)
if(is_post() && isset($_POST['delete_id']) && $canEdit && verify_csrf()) {
    $deleteId = intval($_POST['delete_id']);
    // In SaaS mode also verify the employee belongs to the current company
    $hasSaasCheck = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
    if($hasSaasCheck) {
        $cid = company_id() ?? 1;
        $pdo->prepare("UPDATE employees SET status='inactive' WHERE id=? AND company_id=?")->execute([$deleteId, $cid]);
    } else {
        $pdo->prepare("UPDATE employees SET status='inactive' WHERE id=?")->execute([$deleteId]);
    }
    header("Location: index.php?msg=deleted"); exit;
}

// Get departments and positions for dropdown
$hasSaas = $pdo->query("SHOW COLUMNS FROM departments LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT * FROM departments WHERE company_id=? ORDER BY name");
    $st->execute([$cid]); $departments = $st->fetchAll();
    $st = $pdo->prepare("SELECT * FROM positions WHERE company_id=? ORDER BY name");
    $st->execute([$cid]); $positions = $st->fetchAll();
    $st = $pdo->prepare("SELECT e.*, d.name as dept_name, p.name as pos_name 
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        WHERE e.company_id=?
        ORDER BY e.status DESC, e.last_name, e.first_name");
    $st->execute([$cid]); $rows = $st->fetchAll();
} else {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
    $positions = $pdo->query("SELECT * FROM positions ORDER BY name")->fetchAll();
    $rows = $pdo->query("SELECT e.*, d.name as dept_name, p.name as pos_name 
        FROM employees e 
        LEFT JOIN departments d ON d.id=e.department_id 
        LEFT JOIN positions p ON p.id=e.position_id 
        ORDER BY e.status DESC, e.last_name, e.first_name")->fetchAll();
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-people me-2"></i>Employees</h4>
    <?php if($canEdit): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-person-plus me-2"></i>Add Employee
    </button>
    <?php endif; ?>
</div>

<?php if(isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <?= $_GET['msg'] === 'added' ? 'Employee added successfully!' : ($_GET['msg'] === 'updated' ? 'Employee updated!' : 'Employee deactivated!') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Status</th>
                    <?php if($canEdit): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No employees found</td></tr>
            <?php else: foreach($rows as $r): ?>
                <tr>
                    <td><code><?= e($r['employee_code']) ?></code></td>
                    <td><i class="bi bi-person-circle me-2 text-muted"></i><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                    <td><?= e($r['email']) ?></td>
                    <td><?= e($r['dept_name'] ?? '-') ?></td>
                    <td><?= e($r['pos_name'] ?? '-') ?></td>
                    <td>
                        <?php if($r['status'] === 'active'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                        <?php endif; ?>
                    </td>
                    <?php if($canEdit): ?>
                    <td class="action-btns">
                        <a href="qrcode.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary" title="QR Code"><i class="bi bi-qr-code"></i></a>
                        <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-info" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if($r['status'] === 'active'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this employee?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="delete_id" value="<?= intval($r['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Deactivate"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Employee Modal -->
<?php if($canEdit): ?>
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="add.php">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee Code</label>
                            <input type="text" class="form-control" name="employee_code" placeholder="EMP-XXX" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" step="0.01" class="form-control" name="basic_salary" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">-- Select --</option>
                                <?php foreach($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position_id">
                                <option value="">-- Select --</option>
                                <?php foreach($positions as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
