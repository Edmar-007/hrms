<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/validator.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$cid = company_id() ?? 1;
$msg = ''; $err = '';

$emp = null;
if (!empty($u['employee_id'])) {
    $st = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ? LIMIT 1");
    $st->execute([$u['employee_id'], $cid]);
    $emp = $st->fetch();
}

if (is_post() && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_contact' && $emp) {
        $phone = trim($_POST['phone'] ?? '');
        if (!v_phone($phone)) $err = 'Invalid phone format.';
        else {
            $pdo->prepare("UPDATE employees SET phone = ? WHERE id = ? AND company_id = ?")->execute([$phone, $emp['id'], $cid]);
            log_activity('update', 'profile', $emp['id'], ['phone_updated' => true]);
            $msg = 'Profile updated.';
            $refetch = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ? LIMIT 1");
            $refetch->execute([$u['employee_id'], $cid]);
            $emp = $refetch->fetch();
        }
    }
    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $usr = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
        $usr->execute([$u['id'], $cid]);
        $userRow = $usr->fetch();
        if (!$userRow || !password_verify($current, $userRow['password_hash'])) $err = 'Current password is incorrect.';
        elseif (strlen($new) < 8) $err = 'New password must be at least 8 characters.';
        elseif ($new !== $confirm) $err = 'New passwords do not match.';
        else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND company_id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $u['id'], $cid]);
            log_activity('password_change', 'user', $u['id'], null);
            $msg = 'Password changed.';
        }
    }
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-person-circle me-2"></i>My Profile</h4>
</div>
<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Contact Info</div><div class="card-body">
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_contact">
                <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" disabled></div>
                <div class="mb-2"><label class="form-label">Name</label><input class="form-control" value="<?= e($u['name']) ?>" disabled></div>
                <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($emp['phone'] ?? '') ?>"></div>
                <button class="btn btn-primary">Update Contact</button>
            </form>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Change Password</div><div class="card-body">
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-2"><label class="form-label">Current Password</label><input class="form-control" type="password" name="current_password" required></div>
                <div class="mb-2"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_password" minlength="8" required></div>
                <div class="mb-2"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="confirm_password" minlength="8" required></div>
                <button class="btn btn-primary">Change Password</button>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
