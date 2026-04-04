<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/validator.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin']);

$cid = company_id() ?? 1;
$u = $_SESSION['user'];
$msg = '';
$err = '';

function company_active_admin_count($pdo, $cid) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE company_id = ? AND role = 'Admin' AND is_active = 1");
    $st->execute([$cid]);
    return (int)$st->fetch()['c'];
}

if (is_post()) {
    if (!verify_csrf()) {
        $err = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = $_POST['role'] ?? 'Employee';
            $employeeId = (int)($_POST['employee_id'] ?? 0);

            if (!v_email($email) || strlen($password) < 8 || !v_in($role, ['Admin','HR Officer','Manager','Employee'])) {
                $err = 'Invalid user data.';
            } else {
                $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $st->execute([$email]);
                if ($st->fetch()) {
                    $err = 'Email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (company_id, employee_id, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                        ->execute([$cid, $employeeId ?: null, $email, $hash, $role]);
                    log_activity('create', 'user', (int)$pdo->lastInsertId(), ['email' => $email, 'role' => $role]);
                    $msg = 'User account created.';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $role = $_POST['role'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $password = (string)($_POST['password'] ?? '');
            if ($id <= 0 || !v_in($role, ['Admin','HR Officer','Manager','Employee'])) {
                $err = 'Invalid update request.';
            } else {
                $st = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
                $st->execute([$id, $cid]);
                $target = $st->fetch();
                if (!$target) $err = 'User not found.';
                elseif ((int)$target['id'] === (int)$u['id'] && $isActive === 0) $err = 'You cannot deactivate your own account.';
                elseif ($target['role'] === 'Admin' && $isActive === 0 && company_active_admin_count($pdo, $cid) <= 1) $err = 'At least one active admin is required.';
                else {
                    if ($password !== '') {
                        if (strlen($password) < 8) {
                            $err = 'Password must be at least 8 characters.';
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $pdo->prepare("UPDATE users SET role = ?, is_active = ?, password_hash = ? WHERE id = ? AND company_id = ?")
                                ->execute([$role, $isActive, $hash, $id, $cid]);
                            log_activity('update', 'user', $id, ['role' => $role, 'is_active' => $isActive, 'password_changed' => true]);
                            $msg = 'User updated.';
                        }
                    } else {
                        $pdo->prepare("UPDATE users SET role = ?, is_active = ? WHERE id = ? AND company_id = ?")
                            ->execute([$role, $isActive, $id, $cid]);
                        log_activity('update', 'user', $id, ['role' => $role, 'is_active' => $isActive]);
                        $msg = 'User updated.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $err = 'Invalid user.';
            elseif ($id === (int)$u['id']) $err = 'You cannot delete your own account.';
            else {
                $st = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
                $st->execute([$id, $cid]);
                $target = $st->fetch();
                if (!$target) $err = 'User not found.';
                elseif ($target['role'] === 'Admin' && company_active_admin_count($pdo, $cid) <= 1) $err = 'At least one admin account is required.';
                else {
                    $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?")->execute([$id, $cid]);
                    log_activity('delete', 'user', $id, ['email' => $target['email']]);
                    $msg = 'User deleted.';
                }
            }
        }
    }
}

$employees = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE company_id = ? AND status = 'active' ORDER BY last_name, first_name");
$employees->execute([$cid]);
$employees = $employees->fetchAll();

$users = $pdo->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_code FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.company_id = ? ORDER BY u.created_at DESC");
$users->execute([$cid]);
$users = $users->fetchAll();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-person-gear me-2"></i>User Accounts</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-person-plus me-2"></i>Add User</button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Email</th><th>Employee</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($users as $row): ?>
                <tr>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e(trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: '-') ?> <?= !empty($row['employee_code']) ? '<small class="text-muted">('.e($row['employee_code']).')</small>' : '' ?></td>
                    <td><span class="badge bg-info"><?= e($row['role']) ?></span></td>
                    <td><?= (int)$row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td><?= $row['last_login'] ? date('M j, Y h:i A', strtotime($row['last_login'])) : '-' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= (int)$row['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <?php if((int)$row['id'] !== (int)$u['id']): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>

                <div class="modal fade" id="editUserModal<?= (int)$row['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($row['email']) ?>" disabled></div>
                                    <div class="mb-2">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="role" required>
                                            <?php foreach(['Admin','HR Officer','Manager','Employee'] as $role): ?>
                                            <option value="<?= $role ?>" <?= $row['role'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int)$row['id'] ?>" <?= (int)$row['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="active<?= (int)$row['id'] ?>">Active</label>
                                    </div>
                                    <div><label class="form-label">New Password (optional)</label><input class="form-control" type="password" name="password" minlength="8"></div>
                                </div>
                                <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                    <div class="mb-2"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" required></div>
                    <div class="mb-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="Employee">Employee</option>
                            <option value="Manager">Manager</option>
                            <option value="HR Officer">HR Officer</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Linked Employee (optional)</label>
                        <select class="form-select" name="employee_id">
                            <option value="0">-- None --</option>
                            <?php foreach($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"><?= e($e['last_name'].', '.$e['first_name'].' ('.$e['employee_code'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>

