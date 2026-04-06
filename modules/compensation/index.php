<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$msg = $err = '';

try {
    $pdo->query("SELECT 1 FROM compensation_packages LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS compensation_packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        base_salary DECIMAL(12,2) DEFAULT 0,
        allowances DECIMAL(12,2) DEFAULT 0,
        benefits TEXT,
        description TEXT,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

if (is_post() && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $baseSalary = (float)($_POST['base_salary'] ?? 0);
        $allowances = (float)($_POST['allowances'] ?? 0);
        $benefits = trim($_POST['benefits'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $err = 'Package name is required.';
        } else {
            $pdo->prepare("INSERT INTO compensation_packages (company_id, name, base_salary, allowances, benefits, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$cid, $name, $baseSalary, $allowances, $benefits, $description]);
            $msg = 'Compensation package created.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $baseSalary = (float)($_POST['base_salary'] ?? 0);
        $allowances = (float)($_POST['allowances'] ?? 0);
        $benefits = trim($_POST['benefits'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $pdo->prepare("UPDATE compensation_packages SET name = ?, base_salary = ?, allowances = ?, benefits = ?, description = ?, is_active = ? WHERE id = ? AND company_id = ?")
            ->execute([$name, $baseSalary, $allowances, $benefits, $description, $isActive, $id, $cid]);
        $msg = 'Package updated.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM compensation_packages WHERE id = ? AND company_id = ?")->execute([$id, $cid]);
        $msg = 'Package deleted.';
    }
}

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM compensation_packages WHERE company_id = ? ORDER BY name");
    $stmt->execute([$cid]);
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-currency-dollar me-2"></i>Compensation Packages</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPackageModal">
        <i class="bi bi-plus-circle me-2"></i>New Package
    </button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<?php require_once __DIR__.'/_compensation-table.php'; ?>
<?php require_once __DIR__.'/_compensation-modals.php'; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
