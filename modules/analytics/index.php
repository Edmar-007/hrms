<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$totalEmployees = $activeEmployees = $avgTenure = 0;
$departmentStats = $monthlyHires = [];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ?");
    $stmt->execute([$cid]);
    $totalEmployees = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$cid]);
    $activeEmployees = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT AVG(DATEDIFF(CURDATE(), hire_date)/365) FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$cid]);
    $avgTenure = round($stmt->fetchColumn() ?: 0, 1);
    
    $stmt = $pdo->prepare("SELECT d.name, COUNT(e.id) cnt FROM departments d LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active' WHERE d.company_id = ? GROUP BY d.id ORDER BY cnt DESC LIMIT 10");
    $stmt->execute([$cid]);
    $departmentStats = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(hire_date, '%Y-%m') as month, COUNT(*) cnt FROM employees WHERE company_id = ? AND hire_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");
    $stmt->execute([$cid]);
    $monthlyHires = $stmt->fetchAll();
} catch (PDOException $e) {}

$turnoverRate = $totalEmployees > 0 ? round((($totalEmployees - $activeEmployees) / $totalEmployees) * 100, 1) : 0;
?>
<div class="page-header">
    <h4><i class="bi bi-graph-up me-2"></i>HR Analytics</h4>
</div>

<?php require_once __DIR__.'/_analytics-cards.php'; ?>
<?php require_once __DIR__.'/_analytics-charts.php'; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
