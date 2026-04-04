<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$id = intval($_GET['id'] ?? 0);
$emp = $pdo->prepare("SELECT * FROM employees WHERE id=?");
$emp->execute([$id]);
$emp = $emp->fetch();
if(!$emp) { header("Location: index.php"); exit; }

if(is_post() && verify_csrf()) {
    $code = trim($_POST['employee_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $salary = floatval($_POST['basic_salary'] ?? 0);
    $dept = $_POST['department_id'] ?: null;
    $pos = $_POST['position_id'] ?: null;
    $status = $_POST['status'] ?? 'active';
    
    $st = $pdo->prepare("UPDATE employees SET employee_code=?, email=?, first_name=?, last_name=?, phone=?, basic_salary=?, department_id=?, position_id=?, status=? WHERE id=?");
    $st->execute([$code, $email, $first, $last, $phone, $salary, $dept, $pos, $status, $id]);
    
    header("Location: index.php?msg=updated");
    exit;
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$positions = $pdo->query("SELECT * FROM positions ORDER BY name")->fetchAll();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-pencil me-2"></i>Edit Employee</h4>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_input() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employee Code</label>
                    <input type="text" class="form-control" name="employee_code" value="<?= e($emp['employee_code']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= e($emp['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?= e($emp['first_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?= e($emp['last_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?= e($emp['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Basic Salary</label>
                    <input type="number" step="0.01" class="form-control" name="basic_salary" value="<?= e($emp['basic_salary']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">-- Select --</option>
                        <?php foreach($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $emp['department_id']==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Position</label>
                    <select class="form-select" name="position_id">
                        <option value="">-- Select --</option>
                        <?php foreach($positions as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $emp['position_id']==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= $emp['status']==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $emp['status']==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Update Employee</button>
                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
