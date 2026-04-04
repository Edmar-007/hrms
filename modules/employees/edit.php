<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/security.php';
require_once __DIR__.'/../../includes/validator.php';
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
    
    if (!v_required($code) || !v_email($email) || !v_required($first) || !v_required($last) || !v_phone($phone) || !v_non_negative_number($salary)) {
        header("Location: edit.php?id=".$id."&err=validation");
        exit;
    }

    $photoPath = $emp['photo_path'] ?? null;
    if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$ok, $mimeOrErr] = upload_is_allowed($_FILES['photo'], ['image/jpeg', 'image/png'], 2 * 1024 * 1024);
        if ($ok) {
            $stored = store_upload($_FILES['photo'], __DIR__.'/../../public/uploads/employees', 'emp_'.$id, ['image/jpeg' => 'jpg', 'image/png' => 'png']);
            if ($stored) $photoPath = '/public/uploads/employees/'.$stored;
        }
    }
    
    $st = $pdo->prepare("UPDATE employees SET employee_code=?, email=?, first_name=?, last_name=?, phone=?, basic_salary=?, department_id=?, position_id=?, status=?, photo_path=? WHERE id=?");
    $st->execute([$code, $email, $first, $last, $phone, $salary, $dept, $pos, $status, $photoPath, $id]);
    
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
        <form method="post" enctype="multipart/form-data">
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
                    <label class="form-label">Profile Photo</label>
                    <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                    <?php if(!empty($emp['photo_path'])): ?>
                        <small class="text-muted">Current: <a href="<?= BASE_URL . e($emp['photo_path']) ?>" target="_blank">View photo</a></small>
                    <?php endif; ?>
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
