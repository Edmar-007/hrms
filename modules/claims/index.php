<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();

$cid = company_id() ?? 1;
$uid = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$isApprover = in_array($role, ['Admin', 'HR Officer'], true);
$msg = $err = '';
$linkedEmployeeId = null;

// Check if expense_claims table exists
$tableExists = false;
try {
    $pdo->query("SELECT 1 FROM expense_claims LIMIT 1");
    $tableExists = true;
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        claim_date DATE NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT,
        amount DECIMAL(12,2) NOT NULL,
        receipt_url VARCHAR(255),
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        approved_by INT NULL,
        approved_at DATETIME NULL,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

if (!$isApprover) {
    try {
        $empStmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ? LIMIT 1");
        $empStmt->execute([$uid]);
        $linkedEmployeeId = (int)($empStmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        $linkedEmployeeId = 0;
    }
}

// Handle form submissions
if (is_post()) {
    if (!verify_csrf()) {
        $err = 'Invalid request. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $employeeId = $isApprover
                ? (int)($_POST['employee_id'] ?? 0)
                : (int)$linkedEmployeeId;
            $claimDate = $_POST['claim_date'] ?? date('Y-m-d');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            
            if ($employeeId <= 0) {
                $err = $isApprover
                    ? 'Please select an employee.'
                    : 'Your account is not linked to an employee record yet.';
            } elseif ($amount <= 0) {
                $err = 'Amount must be greater than zero.';
            } elseif (empty($category)) {
                $err = 'Please select a category.';
            } else {
                $employeeCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND company_id = ? AND status = 'active' LIMIT 1");
                $employeeCheck->execute([$employeeId, $cid]);
                $validEmployeeId = (int)($employeeCheck->fetchColumn() ?: 0);

                if ($validEmployeeId <= 0) {
                    $err = 'Selected employee is invalid or inactive.';
                } else {
                    $pdo->prepare("INSERT INTO expense_claims (company_id, employee_id, claim_date, category, description, amount) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$cid, $validEmployeeId, $claimDate, $category, $description, $amount]);
                    $msg = 'Expense claim submitted successfully.';
                }
            }
        } elseif ($action === 'approve' && $isApprover) {
            $id = (int)($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $pdo->prepare("UPDATE expense_claims SET status = 'approved', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ? AND company_id = ?")
                ->execute([$uid, $remarks, $id, $cid]);
            $msg = 'Claim approved.';
        } elseif ($action === 'reject' && $isApprover) {
            $id = (int)($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $pdo->prepare("UPDATE expense_claims SET status = 'rejected', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ? AND company_id = ?")
                ->execute([$uid, $remarks, $id, $cid]);
            $msg = 'Claim rejected.';
        }
    }
}

// Fetch employees for dropdown
$employees = [];
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE company_id = ? AND status = 'active' ORDER BY last_name");
    $stmt->execute([$cid]);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {}

// Fetch claims
$claims = [];
try {
    if ($isApprover) {
        $stmt = $pdo->prepare("SELECT c.*, e.first_name, e.last_name, e.employee_code 
            FROM expense_claims c 
            JOIN employees e ON e.id = c.employee_id 
            WHERE c.company_id = ? 
            ORDER BY c.created_at DESC");
        $stmt->execute([$cid]);
    } else {
        $stmt = $pdo->prepare("SELECT c.*, e.first_name, e.last_name, e.employee_code 
            FROM expense_claims c 
            JOIN employees e ON e.id = c.employee_id 
            WHERE c.company_id = ? AND c.employee_id = ? 
            ORDER BY c.created_at DESC");
        $stmt->execute([$cid, $linkedEmployeeId ?: 0]);
    }
    $claims = $stmt->fetchAll();
} catch (PDOException $e) {}

$categories = ['Transportation', 'Meals', 'Accommodation', 'Office Supplies', 'Training', 'Communication', 'Other'];
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-receipt me-2"></i>Expense Claims</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClaimModal">
        <i class="bi bi-plus-circle me-2"></i>New Claim
    </button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<?php require_once __DIR__.'/_claims-table.php'; ?>
<?php require_once __DIR__.'/_claims-modals.php'; ?>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
