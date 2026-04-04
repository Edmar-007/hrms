<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$u = $_SESSION['user'];
$canEdit = in_array($u['role'], ['Admin', 'HR Officer']);
$deleteFailed = false;

// Get departments and positions for dropdown
$hasSaas = $pdo->query("SHOW COLUMNS FROM departments LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

// Handle reactivate via POST + CSRF
if(is_post() && $canEdit && (($_POST['action'] ?? '') === 'reactivate') && verify_csrf()) {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if ($employeeId > 0) {
        if($hasSaas && $cid) {
            $st = $pdo->prepare("UPDATE employees SET status='active' WHERE id=? AND company_id=?");
            $st->execute([$employeeId, $cid]);
        } else {
            $st = $pdo->prepare("UPDATE employees SET status='active' WHERE id=?");
            $st->execute([$employeeId]);
        }
        if($st->rowCount() > 0) {
            header("Location: index.php?msg=reactivated"); exit;
        }
    }
}

// Handle deactivate via POST + CSRF
if(is_post() && $canEdit && (($_POST['action'] ?? '') === 'deactivate') && verify_csrf()) {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if ($employeeId > 0) {
        if($hasSaas && $cid) {
            $st = $pdo->prepare("UPDATE employees SET status='inactive' WHERE id=? AND company_id=?");
            $st->execute([$employeeId, $cid]);
        } else {
            $st = $pdo->prepare("UPDATE employees SET status='inactive' WHERE id=?");
            $st->execute([$employeeId]);
        }
        if($st->rowCount() > 0) {
            header("Location: index.php?msg=deleted"); exit;
        }
    }
    $deleteFailed = true;
}

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

$totalActive   = count(array_filter($rows, fn($r) => $r['status'] === 'active'));
$totalInactive = count($rows) - $totalActive;
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-people me-2"></i>Employees</h4>
        <small class="text-muted"><?= $totalActive ?> active &bull; <?= $totalInactive ?> inactive</small>
    </div>
    <?php if($canEdit): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-person-plus me-2"></i>Add Employee
    </button>
    <?php endif; ?>
</div>

<?php if(isset($_GET['msg']) || $deleteFailed): ?>
<div class="alert <?= $deleteFailed ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
    <i class="bi bi-<?= $deleteFailed ? 'exclamation-circle' : 'check-circle' ?> me-2"></i>
    <?php
    if($deleteFailed) echo 'Unable to deactivate employee.';
    else {
        $msgs = ['added'=>'Employee added successfully!','updated'=>'Employee updated!','deleted'=>'Employee deactivated!','reactivated'=>'Employee reactivated!'];
        echo $msgs[$_GET['msg']] ?? 'Done!';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="input-group" style="max-width:300px;">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="empSearch" class="form-control border-start-0 ps-0" placeholder="Search name, code, department…">
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select id="statusFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <span class="badge bg-primary table-count-badge" id="rowCount"><?= count($rows) ?> total</span>
        </div>
    </div>

    <div class="table-responsive-wrapper">
        <table class="table table-hover mb-0" id="empTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Contact</th>
                    <th>Department / Position</th>
                    <th>Status</th>
                    <?php if($canEdit): ?><th class="text-center">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>No employees found
                </td></tr>
            <?php else: foreach($rows as $idx => $r):
                $colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899'];
                $color = $colors[$idx % count($colors)];
            ?>
                <tr data-status="<?= $r['status'] ?>" data-search="<?= strtolower(e($r['first_name'].' '.$r['last_name'].' '.$r['employee_code'].' '.($r['dept_name']??'').' '.($r['pos_name']??''))) ?>">
                    <td class="text-muted"><?= $idx+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm" style="background:<?= $color ?>;">
                                <?= strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                                <small class="text-muted"><code><?= e($r['employee_code']) ?></code></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= e($r['email']) ?></div>
                        <?php if($r['phone'] ?? ''): ?>
                        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($r['phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?= e($r['dept_name'] ?? '—') ?></div>
                        <small class="text-muted"><?= e($r['pos_name'] ?? '—') ?></small>
                    </td>
                    <td>
                        <?php if($r['status'] === 'active'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                        <?php endif; ?>
                    </td>
                    <?php if($canEdit): ?>
                    <td class="action-btns text-center">
                        <a href="qrcode.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary" title="View QR Code" data-bs-toggle="tooltip"><i class="bi bi-qr-code"></i></a>
                        <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-info" title="Edit Employee" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>
                        <?php if($r['status'] === 'active'): ?>
                        <form method="post" class="d-inline confirm-form"
                              data-msg="<?= htmlspecialchars('Deactivate ' . $r['first_name'] . ' ' . $r['last_name'] . '?', ENT_QUOTES) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="employee_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Deactivate" data-bs-toggle="tooltip"><i class="bi bi-person-dash"></i></button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline confirm-form"
                              data-msg="<?= htmlspecialchars('Reactivate ' . $r['first_name'] . ' ' . $r['last_name'] . '?', ENT_QUOTES) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="reactivate">
                            <input type="hidden" name="employee_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Reactivate" data-bs-toggle="tooltip"><i class="bi bi-person-check"></i></button>
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
                            <input type="text" class="form-control" name="employee_code" placeholder="EMP-XXXX" required>
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
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">— Select Department —</option>
                                <?php foreach($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position_id">
                                <option value="">— Select Position —</option>
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

<script>
// Live search + status filter
const searchInput  = document.getElementById('empSearch');
const statusFilter = document.getElementById('statusFilter');
const rowCountEl   = document.getElementById('rowCount');

function filterTable() {
    const q      = searchInput.value.toLowerCase();
    const status = statusFilter.value;
    let visible  = 0;
    document.querySelectorAll('#empTable tbody tr[data-status]').forEach(row => {
        const matchSearch = !q || row.dataset.search.includes(q);
        const matchStatus = !status || row.dataset.status === status;
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
        if (matchSearch && matchStatus) visible++;
    });
    rowCountEl.textContent = visible + ' total';
}

if (searchInput)  searchInput.addEventListener('input',  filterTable);
if (statusFilter) statusFilter.addEventListener('change', filterTable);

// Confirm forms — read message from data-msg attribute (XSS-safe)
document.querySelectorAll('form.confirm-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const msg = this.dataset.msg || 'Are you sure?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// Bootstrap tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
