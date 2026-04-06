<?php
// Create required directories and files
$base = __DIR__;
$root = dirname(__DIR__);

// Directories to create
$dirs = [
    $base . '/settings',
    $base . '/claims',
    $base . '/analytics',
    $base . '/compensation',
    $root . '/api',
    $root . '/database',
    $root . '/public/uploads',
    $root . '/public/uploads/logos',
    $root . '/public/uploads/claim_receipts'
];

echo "<h3>HRMS Setup</h3>";
echo "<pre>";

foreach($dirs as $dir) {
    if(!is_dir($dir)) {
        if(mkdir($dir, 0755, true)) {
            echo "✓ Created: $dir\n";
        } else {
            echo "✗ Failed: $dir\n";
        }
    } else {
        echo "• Exists: $dir\n";
    }
}

// Create API files
$apiDir = $root . '/api';

// notifications.php
$notificationsApi = '<?php
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/auth.php";

header("Content-Type: application/json");

if(empty($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION["user"]["id"];
$companyId = company_id();

if(isset($_GET["action"]) && $_GET["action"] === "read_all" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND company_id = ?")
        ->execute([$userId, $companyId]);
    echo json_encode(["success" => true]);
    exit;
}

$st = $pdo->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND company_id = ? ORDER BY created_at DESC LIMIT 10");
$st->execute([$userId, $companyId]);
$notifications = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND company_id = ? AND is_read = 0");
$st->execute([$userId, $companyId]);
$unread = $st->fetch()["cnt"];

foreach($notifications as &$n) {
    $n["time_ago"] = time_ago($n["created_at"]);
}

echo json_encode(["notifications" => $notifications, "unread" => (int)$unread]);
';

if(file_put_contents($apiDir . '/notifications.php', $notificationsApi)) {
    echo "✓ Created: api/notifications.php\n";
}

// preferences.php  
$preferencesApi = '<?php
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/auth.php";

header("Content-Type: application/json");

if(empty($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION["user"]["id"];

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if(isset($input["theme"])) {
        $theme = in_array($input["theme"], ["light", "dark"]) ? $input["theme"] : "light";
        
        $st = $pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme = ?");
        $st->execute([$userId, $theme, $theme]);
        
        $_SESSION["user"]["theme"] = $theme;
        
        echo json_encode(["success" => true, "theme" => $theme]);
        exit;
    }
}

$st = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$st->execute([$userId]);
$prefs = $st->fetch() ?: ["theme" => "light"];

echo json_encode($prefs);
';

if(file_put_contents($apiDir . '/preferences.php', $preferencesApi)) {
    echo "✓ Created: api/preferences.php\n";
}

// Create settings page
$settingsDir = $base . '/settings';
$settingsIndex = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_login();
require_role(["Admin","HR Officer"]);

$pageTitle = "Settings";
$company = company();
$user = $_SESSION["user"];

// Handle form submissions
$success = $error = "";

if($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $action = $_POST["action"] ?? "";
    
    if($action === "update_company") {
        $name = trim($_POST["company_name"]);
        $email = trim($_POST["company_email"]);
        $phone = trim($_POST["company_phone"]);
        $address = trim($_POST["company_address"]);
        $timezone = $_POST["timezone"];
        $currency = $_POST["currency"];
        
        $st = $pdo->prepare("UPDATE companies SET name=?, email=?, phone=?, address=?, timezone=?, currency=? WHERE id=?");
        if($st->execute([$name, $email, $phone, $address, $timezone, $currency, company_id()])) {
            $_SESSION["company"]["name"] = $name;
            $success = "Company settings updated successfully!";
            log_activity("update", "company", company_id());
        }
    }
    
    if($action === "add_department") {
        $name = trim($_POST["dept_name"]);
        if($name) {
            $st = $pdo->prepare("INSERT IGNORE INTO departments (company_id, name) VALUES (?, ?)");
            $st->execute([company_id(), $name]);
            $success = "Department added!";
        }
    }
    
    if($action === "add_position") {
        $name = trim($_POST["pos_name"]);
        if($name) {
            $st = $pdo->prepare("INSERT IGNORE INTO positions (company_id, name) VALUES (?, ?)");
            $st->execute([company_id(), $name]);
            $success = "Position added!";
        }
    }
    
    if($action === "delete_department") {
        $id = (int)$_POST["id"];
        $pdo->prepare("DELETE FROM departments WHERE id=? AND company_id=?")->execute([$id, company_id()]);
        $success = "Department deleted!";
    }
    
    if($action === "delete_position") {
        $id = (int)$_POST["id"];
        $pdo->prepare("DELETE FROM positions WHERE id=? AND company_id=?")->execute([$id, company_id()]);
        $success = "Position deleted!";
    }
    
    // Reload company data
    $st = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $st->execute([company_id()]);
    $company = $st->fetch();
}

// Get departments and positions
$departments = $pdo->prepare("SELECT * FROM departments WHERE company_id = ? ORDER BY name");
$departments->execute([company_id()]);
$departments = $departments->fetchAll();

$positions = $pdo->prepare("SELECT * FROM positions WHERE company_id = ? ORDER BY name");
$positions->execute([company_id()]);
$positions = $positions->fetchAll();

// Get employee count
$empCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE company_id = ? AND status = \'active\'");
$empCount->execute([company_id()]);
$empCount = $empCount->fetch()["cnt"];

require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="bi bi-gear me-2"></i>Settings</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Company Overview Card -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Company Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <span class="badge bg-primary fs-5 px-4 py-2"><?= htmlspecialchars($company["name"] ?? "My Company") ?></span>
                        </div>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-people text-primary me-2"></i>
                                <strong>Active Employees:</strong> <?= $empCount ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-building text-primary me-2"></i>
                                <strong>Departments:</strong> <?= count($departments) ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-person-badge text-primary me-2"></i>
                                <strong>Positions:</strong> <?= count($positions) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Company Settings -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Company Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_company">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company["name"]) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($company["email"]) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($company["phone"] ?? "") ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-select">
                                        <option value="Asia/Manila" <?= ($company["timezone"] ?? "") === "Asia/Manila" ? "selected" : "" ?>>Asia/Manila</option>
                                        <option value="Asia/Singapore" <?= ($company["timezone"] ?? "") === "Asia/Singapore" ? "selected" : "" ?>>Asia/Singapore</option>
                                        <option value="UTC" <?= ($company["timezone"] ?? "") === "UTC" ? "selected" : "" ?>>UTC</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <option value="PHP" <?= ($company["currency"] ?? "") === "PHP" ? "selected" : "" ?>>PHP (₱)</option>
                                        <option value="USD" <?= ($company["currency"] ?? "") === "USD" ? "selected" : "" ?>>USD ($)</option>
                                        <option value="SGD" <?= ($company["currency"] ?? "") === "SGD" ? "selected" : "" ?>>SGD (S$)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($company["address"] ?? "") ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Departments -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Departments</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach($departments as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-folder2 text-primary me-2"></i><?= htmlspecialchars($d["name"]) ?></span>
                                <form method="POST" class="d-inline" onsubmit="return confirm(\'Delete this department?\')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_department">
                                    <input type="hidden" name="id" value="<?= $d["id"] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                            <?php if(empty($departments)): ?>
                            <li class="list-group-item text-muted">No departments yet</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Positions -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Positions</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPosModal">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach($positions as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-briefcase text-success me-2"></i><?= htmlspecialchars($p["name"]) ?></span>
                                <form method="POST" class="d-inline" onsubmit="return confirm(\'Delete this position?\')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_position">
                                    <input type="hidden" name="id" value="<?= $p["id"] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                            <?php if(empty($positions)): ?>
                            <li class="list-group-item text-muted">No positions yet</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_department">
                <div class="modal-header">
                    <h5 class="modal-title">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" name="dept_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Position Modal -->
<div class="modal fade" id="addPosModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_position">
                <div class="modal-header">
                    <h5 class="modal-title">Add Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Position Name</label>
                        <input type="text" name="pos_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Position</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($settingsDir . '/index.php', $settingsIndex)) {
    echo "✓ Created: modules/settings/index.php\n";
}

// =====================================================
// CLAIMS MODULE FILES
// =====================================================
$claimsDir = $base . '/claims';

$claimsHandler = '<?php
/**
 * Claim Handler Functions
 */

function generate_claim_number($pdo, $companyId) {
    $year = date("Y");
    $prefix = "CLM-{$year}-";
    $stmt = $pdo->prepare("SELECT claim_number FROM expense_claims WHERE company_id = ? AND claim_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$companyId, $prefix . "%"]);
    $last = $stmt->fetch();
    $newNum = $last ? str_pad((int)substr($last["claim_number"], -5) + 1, 5, "0", STR_PAD_LEFT) : "00001";
    return $prefix . $newNum;
}

function get_claim_categories($pdo, $companyId) {
    $stmt = $pdo->prepare("SELECT * FROM claim_categories WHERE company_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

function calculate_claim_total($pdo, $claimId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM claim_items WHERE claim_id = ?");
    $stmt->execute([$claimId]);
    return (float)$stmt->fetch()["total"];
}

function update_claim_total($pdo, $claimId) {
    $total = calculate_claim_total($pdo, $claimId);
    $pdo->prepare("UPDATE expense_claims SET total_amount = ? WHERE id = ?")->execute([$total, $claimId]);
    return $total;
}

function claim_status_badge($status) {
    $badges = [
        "draft" => "<span class=\"badge bg-secondary\">Draft</span>",
        "submitted" => "<span class=\"badge bg-info\">Submitted</span>",
        "under_review" => "<span class=\"badge bg-warning text-dark\">Under Review</span>",
        "approved" => "<span class=\"badge bg-success\">Approved</span>",
        "rejected" => "<span class=\"badge bg-danger\">Rejected</span>",
        "paid" => "<span class=\"badge bg-primary\">Paid</span>",
        "cancelled" => "<span class=\"badge bg-dark\">Cancelled</span>"
    ];
    return $badges[$status] ?? "<span class=\"badge bg-secondary\">Unknown</span>";
}

function log_claim_action($pdo, $claimId, $userId, $action, $comments = null) {
    $pdo->prepare("INSERT INTO claim_approvals (claim_id, user_id, action, comments) VALUES (?, ?, ?, ?)")
        ->execute([$claimId, $userId, $action, $comments]);
}

function get_claim_items($pdo, $claimId) {
    $stmt = $pdo->prepare("SELECT ci.*, cc.name as category_name FROM claim_items ci LEFT JOIN claim_categories cc ON cc.id = ci.category_id WHERE ci.claim_id = ? ORDER BY ci.expense_date DESC");
    $stmt->execute([$claimId]);
    return $stmt->fetchAll();
}

function get_claim_history($pdo, $claimId) {
    $stmt = $pdo->prepare("SELECT ca.*, u.email FROM claim_approvals ca LEFT JOIN users u ON u.id = ca.user_id WHERE ca.claim_id = ? ORDER BY ca.created_at ASC");
    $stmt->execute([$claimId]);
    return $stmt->fetchAll();
}
';

if(file_put_contents($claimsDir . '/claims-handler.php', $claimsHandler)) {
    echo "✓ Created: modules/claims/claims-handler.php\n";
}

$claimsIndex = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_once __DIR__."/claims-handler.php";
require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
require_login();

$u = $_SESSION["user"];
$canApprove = in_array($u["role"], ["Admin", "HR Officer", "Manager"]);
$hasSaas = $pdo->query("SHOW COLUMNS FROM expense_claims LIKE \'company_id\'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : 1;
$empId = $u["employee_id"] ?? null;

// Handle actions
if(is_post() && verify_csrf()) {
    $action = $_POST["action"] ?? "";
    $id = (int)($_POST["id"] ?? 0);
    
    if($action === "submit" && $id) {
        $pdo->prepare("UPDATE expense_claims SET status=\'submitted\', submitted_at=NOW() WHERE id=? AND employee_id=? AND status=\'draft\'")
            ->execute([$id, $empId]);
        log_claim_action($pdo, $id, $u["id"], "submitted");
        header("Location: index.php?msg=submitted"); exit;
    }
    
    if($canApprove && in_array($action, ["approve", "reject", "pay"])) {
        $comments = trim($_POST["comments"] ?? "");
        if($action === "approve") {
            $pdo->prepare("UPDATE expense_claims SET status=\'approved\', approved_by=?, approved_at=NOW() WHERE id=? AND company_id=?")
                ->execute([$u["id"], $id, $cid]);
            log_claim_action($pdo, $id, $u["id"], "approved", $comments);
            $emp = $pdo->prepare("SELECT employee_id FROM expense_claims WHERE id=?");
            $emp->execute([$id]);
            if($row = $emp->fetch()) {
                $usr = $pdo->prepare("SELECT id FROM users WHERE employee_id=? AND is_active=1 LIMIT 1");
                $usr->execute([$row["employee_id"]]);
                if($x = $usr->fetch()) notify((int)$x["id"], "claim", "Claim Approved", "Your expense claim has been approved.", "/hrms/modules/claims/index.php");
            }
        } elseif($action === "reject") {
            $pdo->prepare("UPDATE expense_claims SET status=\'rejected\', rejection_reason=? WHERE id=? AND company_id=?")
                ->execute([$comments, $id, $cid]);
            log_claim_action($pdo, $id, $u["id"], "rejected", $comments);
        } elseif($action === "pay") {
            $payRef = trim($_POST["payment_reference"] ?? "");
            $pdo->prepare("UPDATE expense_claims SET status=\'paid\', paid_at=NOW(), paid_by=?, payment_reference=? WHERE id=? AND company_id=?")
                ->execute([$u["id"], $payRef, $id, $cid]);
            log_claim_action($pdo, $id, $u["id"], "paid", $payRef);
        }
        header("Location: index.php?msg=$action"); exit;
    }
}

// Fetch claims
if($canApprove) {
    $stmt = $pdo->prepare("SELECT ec.*, e.first_name, e.last_name, (SELECT COUNT(*) FROM claim_items WHERE claim_id=ec.id) as item_count 
        FROM expense_claims ec JOIN employees e ON e.id=ec.employee_id WHERE ec.company_id=? ORDER BY ec.status=\'submitted\' DESC, ec.created_at DESC");
    $stmt->execute([$cid]);
} else {
    $stmt = $pdo->prepare("SELECT ec.*, e.first_name, e.last_name, (SELECT COUNT(*) FROM claim_items WHERE claim_id=ec.id) as item_count 
        FROM expense_claims ec JOIN employees e ON e.id=ec.employee_id WHERE ec.employee_id=? ORDER BY ec.created_at DESC");
    $stmt->execute([$empId]);
}
$claims = $stmt->fetchAll();

$categories = get_claim_categories($pdo, $cid);
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-receipt me-2"></i>Expense Claims</h4>
    <div class="d-flex gap-2">
        <?php if($empId): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClaimModal">
            <i class="bi bi-plus-lg me-2"></i>New Claim
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($_GET["msg"])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <?php
    $msgs = ["submitted"=>"Claim submitted for approval!","approve"=>"Claim approved!","reject"=>"Claim rejected.","pay"=>"Claim marked as paid!","added"=>"Claim created!"];
    echo $msgs[$_GET["msg"]] ?? "Done!";
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-toolbar">
        <div class="input-group" style="max-width:300px;">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="claimSearch" class="form-control border-start-0 ps-0" placeholder="Search claims...">
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select id="claimStatusFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="paid">Paid</option>
            </select>
            <span class="badge bg-primary table-count-badge"><?= count($claims) ?> total</span>
        </div>
    </div>

    <div class="table-responsive-wrapper">
        <table class="table table-hover mb-0" id="claimsTable">
            <thead>
                <tr>
                    <th>Claim #</th>
                    <?php if($canApprove): ?><th>Employee</th><?php endif; ?>
                    <th>Title</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($claims)): ?>
                <tr><td colspan="<?= $canApprove ? 8 : 7 ?>" class="text-center py-4 text-muted">
                    <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>No claims found
                </td></tr>
            <?php else: foreach($claims as $c): ?>
                <tr data-status="<?= e($c["status"]) ?>" data-search="<?= strtolower(e($c["claim_number"]." ".$c["title"]." ".$c["first_name"]." ".$c["last_name"])) ?>">
                    <td><code><?= e($c["claim_number"]) ?></code></td>
                    <?php if($canApprove): ?>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm" style="background:linear-gradient(135deg,#8b5cf6,#a855f7);">
                                <?= strtoupper(substr($c["first_name"],0,1).substr($c["last_name"],0,1)) ?>
                            </div>
                            <span class="fw-semibold"><?= e($c["first_name"]." ".$c["last_name"]) ?></span>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td><?= e($c["title"]) ?></td>
                    <td><span class="badge bg-secondary"><?= (int)$c["item_count"] ?></span></td>
                    <td class="fw-semibold">₱<?= number_format($c["total_amount"], 2) ?></td>
                    <td><?= claim_status_badge($c["status"]) ?></td>
                    <td><?= date("M j, Y", strtotime($c["created_at"])) ?></td>
                    <td class="action-btns">
                        <a href="view.php?id=<?= (int)$c["id"] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <?php if($c["status"] === "draft" && $c["employee_id"] == $empId): ?>
                        <a href="edit.php?id=<?= (int)$c["id"] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm(\'Submit this claim for approval?\')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-send"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if($canApprove && $c["status"] === "submitted"): ?>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= (int)$c["id"] ?>"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$c["id"] ?>"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                        <?php if($canApprove && $c["status"] === "approved"): ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#payModal<?= (int)$c["id"] ?>"><i class="bi bi-cash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <!-- Approve Modal -->
                <div class="modal fade" id="approveModal<?= (int)$c["id"] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Approve Claim</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Approve claim <strong><?= e($c["claim_number"]) ?></strong> for <strong>₱<?= number_format($c["total_amount"], 2) ?></strong>?</p>
                                    <div class="mb-3">
                                        <label class="form-label">Comments (optional)</label>
                                        <textarea class="form-control" name="comments" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal<?= (int)$c["id"] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject Claim</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Reason for rejection</label>
                                        <textarea class="form-control" name="comments" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Pay Modal -->
                <div class="modal fade" id="payModal<?= (int)$c["id"] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Mark as Paid</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Mark claim <strong><?= e($c["claim_number"]) ?></strong> as paid?</p>
                                    <div class="mb-3">
                                        <label class="form-label">Payment Reference</label>
                                        <input type="text" class="form-control" name="payment_reference" placeholder="Check #, transaction ID, etc.">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Mark as Paid</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Claim Modal -->
<div class="modal fade" id="addClaimModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="add.php">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>New Expense Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g., Business Trip to Manila">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of expenses..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Create Claim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const claimSearch = document.getElementById("claimSearch");
const claimFilter = document.getElementById("claimStatusFilter");
function filterClaims() {
    const q = claimSearch.value.toLowerCase();
    const s = claimFilter.value;
    document.querySelectorAll("#claimsTable tbody tr[data-status]").forEach(row => {
        const ms = !q || row.dataset.search.includes(q);
        const mf = !s || row.dataset.status === s;
        row.style.display = (ms && mf) ? "" : "none";
    });
}
if(claimSearch) claimSearch.addEventListener("input", filterClaims);
if(claimFilter) claimFilter.addEventListener("change", filterClaims);
</script>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($claimsDir . '/index.php', $claimsIndex)) {
    echo "✓ Created: modules/claims/index.php\n";
}

$claimsAdd = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_once __DIR__."/claims-handler.php";
require_login();

if(is_post() && verify_csrf()) {
    $u = $_SESSION["user"];
    $empId = $u["employee_id"] ?? null;
    $cid = company_id() ?? 1;
    
    if(!$empId) {
        header("Location: index.php?msg=error"); exit;
    }
    
    $title = trim($_POST["title"] ?? "");
    $desc = trim($_POST["description"] ?? "");
    
    if($title) {
        $claimNum = generate_claim_number($pdo, $cid);
        $stmt = $pdo->prepare("INSERT INTO expense_claims (company_id, employee_id, claim_number, title, description, status) VALUES (?, ?, ?, ?, ?, \'draft\')");
        $stmt->execute([$cid, $empId, $claimNum, $title, $desc]);
        $newId = $pdo->lastInsertId();
        log_activity("create", "expense_claim", $newId, ["title" => $title]);
        header("Location: edit.php?id=$newId"); exit;
    }
}

header("Location: index.php");
exit;
';

if(file_put_contents($claimsDir . '/add.php', $claimsAdd)) {
    echo "✓ Created: modules/claims/add.php\n";
}

$claimsEdit = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_once __DIR__."/../../includes/security.php";
require_once __DIR__."/claims-handler.php";
require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
require_login();

$u = $_SESSION["user"];
$empId = $u["employee_id"] ?? null;
$cid = company_id() ?? 1;
$id = (int)($_GET["id"] ?? 0);

// Fetch claim
$stmt = $pdo->prepare("SELECT * FROM expense_claims WHERE id = ? AND employee_id = ? AND status = \'draft\'");
$stmt->execute([$id, $empId]);
$claim = $stmt->fetch();

if(!$claim) {
    header("Location: index.php"); exit;
}

// Handle add item
if(is_post() && verify_csrf()) {
    $action = $_POST["action"] ?? "";
    
    if($action === "add_item") {
        $catId = (int)($_POST["category_id"] ?? 0) ?: null;
        $desc = trim($_POST["item_description"] ?? "");
        $date = $_POST["expense_date"] ?? "";
        $amount = (float)($_POST["amount"] ?? 0);
        
        if($desc && $date && $amount > 0) {
            $receiptPath = null;
            if(!empty($_FILES["receipt"]) && $_FILES["receipt"]["error"] === UPLOAD_ERR_OK) {
                $allowed = ["application/pdf", "image/jpeg", "image/png"];
                if(in_array($_FILES["receipt"]["type"], $allowed) && $_FILES["receipt"]["size"] <= 5*1024*1024) {
                    $ext = pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION);
                    $filename = "receipt_".$id."_".time().".".$ext;
                    $uploadDir = __DIR__."/../../public/uploads/claim_receipts/";
                    if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    if(move_uploaded_file($_FILES["receipt"]["tmp_name"], $uploadDir.$filename)) {
                        $receiptPath = "/public/uploads/claim_receipts/".$filename;
                    }
                }
            }
            
            $pdo->prepare("INSERT INTO claim_items (claim_id, category_id, description, expense_date, amount, receipt_path) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$id, $catId, $desc, $date, $amount, $receiptPath]);
            update_claim_total($pdo, $id);
        }
        header("Location: edit.php?id=$id"); exit;
    }
    
    if($action === "delete_item") {
        $itemId = (int)($_POST["item_id"] ?? 0);
        $pdo->prepare("DELETE FROM claim_items WHERE id = ? AND claim_id = ?")->execute([$itemId, $id]);
        update_claim_total($pdo, $id);
        header("Location: edit.php?id=$id"); exit;
    }
    
    if($action === "update_claim") {
        $title = trim($_POST["title"] ?? "");
        $desc = trim($_POST["description"] ?? "");
        if($title) {
            $pdo->prepare("UPDATE expense_claims SET title = ?, description = ? WHERE id = ?")->execute([$title, $desc, $id]);
        }
        header("Location: edit.php?id=$id"); exit;
    }
}

$items = get_claim_items($pdo, $id);
$categories = get_claim_categories($pdo, $cid);
$claim = $pdo->prepare("SELECT * FROM expense_claims WHERE id = ?");
$claim->execute([$id]);
$claim = $claim->fetch();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-2"></i>Back to Claims</a>
        <h4 class="mt-2"><i class="bi bi-receipt me-2"></i>Edit Claim: <?= e($claim["claim_number"]) ?></h4>
    </div>
    <form method="post" action="index.php" onsubmit="return confirm(\'Submit this claim for approval?\')">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-success" <?= empty($items) ? "disabled" : "" ?>>
            <i class="bi bi-send me-2"></i>Submit for Approval
        </button>
    </form>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Claim Details</h6></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_claim">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" value="<?= e($claim["title"]) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?= e($claim["description"]) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <div class="form-control bg-light fw-bold">₱<?= number_format($claim["total_amount"], 2) ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Details</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Expense Items</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <?php if(empty($items)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
                    <p>No expense items yet. Add your first item.</p>
                </div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Receipt</th>
                            <th class="text-end">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><?= date("M j, Y", strtotime($item["expense_date"])) ?></td>
                            <td><span class="badge bg-info"><?= e($item["category_name"] ?? "Uncategorized") ?></span></td>
                            <td><?= e($item["description"]) ?></td>
                            <td>
                                <?php if($item["receipt_path"]): ?>
                                <a href="<?= BASE_URL . e($item["receipt_path"]) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i></a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold">₱<?= number_format($item["amount"], 2) ?></td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm(\'Delete this item?\')">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item["id"] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold">Total:</td>
                            <td class="text-end fw-bold">₱<?= number_format($claim["total_amount"], 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add_item">
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id">
                            <option value="">— Select Category —</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat["id"] ?>"><?= e($cat["name"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="item_description" required placeholder="e.g., Grab ride to client meeting">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="expense_date" required value="<?= date("Y-m-d") ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Receipt (optional)</label>
                        <input type="file" class="form-control" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($claimsDir . '/edit.php', $claimsEdit)) {
    echo "✓ Created: modules/claims/edit.php\n";
}

$claimsView = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/claims-handler.php";
require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
require_login();

$u = $_SESSION["user"];
$canApprove = in_array($u["role"], ["Admin", "HR Officer", "Manager"]);
$empId = $u["employee_id"] ?? null;
$cid = company_id() ?? 1;
$id = (int)($_GET["id"] ?? 0);

// Fetch claim
$stmt = $pdo->prepare("SELECT ec.*, e.first_name, e.last_name, e.email, e.employee_code, d.name as department
    FROM expense_claims ec
    JOIN employees e ON e.id = ec.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE ec.id = ? AND (ec.employee_id = ? OR ? = 1)");
$stmt->execute([$id, $empId, $canApprove ? 1 : 0]);
$claim = $stmt->fetch();

if(!$claim) {
    header("Location: index.php"); exit;
}

$items = get_claim_items($pdo, $id);
$history = get_claim_history($pdo, $id);
?>
<div class="page-header">
    <a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-2"></i>Back to Claims</a>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <h4><i class="bi bi-receipt me-2"></i><?= e($claim["claim_number"]) ?></h4>
        <?= claim_status_badge($claim["status"]) ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Claim Information</h6></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Employee</dt>
                    <dd class="col-7 fw-semibold"><?= e($claim["first_name"]." ".$claim["last_name"]) ?></dd>
                    
                    <dt class="col-5 text-muted">Department</dt>
                    <dd class="col-7"><?= e($claim["department"] ?? "-") ?></dd>
                    
                    <dt class="col-5 text-muted">Title</dt>
                    <dd class="col-7"><?= e($claim["title"]) ?></dd>
                    
                    <dt class="col-5 text-muted">Total Amount</dt>
                    <dd class="col-7 fw-bold fs-5 text-success">₱<?= number_format($claim["total_amount"], 2) ?></dd>
                    
                    <dt class="col-5 text-muted">Submitted</dt>
                    <dd class="col-7"><?= $claim["submitted_at"] ? date("M j, Y", strtotime($claim["submitted_at"])) : "-" ?></dd>
                    
                    <?php if($claim["status"] === "rejected"): ?>
                    <dt class="col-5 text-muted">Rejection Reason</dt>
                    <dd class="col-7 text-danger"><?= e($claim["rejection_reason"]) ?></dd>
                    <?php endif; ?>
                    
                    <?php if($claim["payment_reference"]): ?>
                    <dt class="col-5 text-muted">Payment Ref</dt>
                    <dd class="col-7"><code><?= e($claim["payment_reference"]) ?></code></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <?php if(!empty($history)): ?>
        <div class="card mt-4">
            <div class="card-header"><h6 class="mb-0">Approval History</h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                <?php foreach($history as $h): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="text-capitalize fw-semibold"><?= e($h["action"]) ?></span>
                            <small class="text-muted"><?= date("M j, Y h:i A", strtotime($h["created_at"])) ?></small>
                        </div>
                        <?php if($h["comments"]): ?>
                        <small class="text-muted"><?= e($h["comments"]) ?></small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Expense Items (<?= count($items) ?>)</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Receipt</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><?= date("M j, Y", strtotime($item["expense_date"])) ?></td>
                            <td><span class="badge bg-info"><?= e($item["category_name"] ?? "Uncategorized") ?></span></td>
                            <td><?= e($item["description"]) ?></td>
                            <td>
                                <?php if($item["receipt_path"]): ?>
                                <a href="<?= BASE_URL . e($item["receipt_path"]) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i> View</a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold">₱<?= number_format($item["amount"], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold">Total:</td>
                            <td class="text-end fw-bold fs-5">₱<?= number_format($claim["total_amount"], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($claimsDir . '/view.php', $claimsView)) {
    echo "✓ Created: modules/claims/view.php\n";
}

// =====================================================
// HR ANALYTICS MODULE
// =====================================================
$analyticsDir = $base . '/analytics';

$analyticsIndex = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
require_login();
require_role(["Admin", "HR Officer", "Manager"]);

$cid = company_id() ?? 1;
$year = date("Y");

// Total employees
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status=\'active\') as active FROM employees WHERE company_id = ?");
$stmt->execute([$cid]);
$empStats = $stmt->fetch();

// Department breakdown
$stmt = $pdo->prepare("SELECT d.name, COUNT(e.id) as count FROM departments d LEFT JOIN employees e ON e.department_id = d.id AND e.status = \'active\' WHERE d.company_id = ? GROUP BY d.id ORDER BY count DESC");
$stmt->execute([$cid]);
$deptData = $stmt->fetchAll();

// Leave stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status=\'approved\') as approved, SUM(status=\'pending\') as pending FROM leave_requests WHERE company_id = ? AND YEAR(created_at) = ?");
$stmt->execute([$cid, $year]);
$leaveStats = $stmt->fetch();

// Attendance this month
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as present, AVG(TIMESTAMPDIFF(HOUR, time_in, time_out)) as avg_hours FROM attendance WHERE company_id = ? AND DATE_FORMAT(date, \'%Y-%m\') = ?");
$stmt->execute([$cid, date("Y-m")]);
$attStats = $stmt->fetch();

// Payroll YTD
$stmt = $pdo->prepare("SELECT SUM(net_pay) as total FROM payroll_records WHERE company_id = ? AND YEAR(pay_date) = ?");
$stmt->execute([$cid, $year]);
$payrollTotal = $stmt->fetch()["total"] ?? 0;

// New hires
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ? AND YEAR(hire_date) = ?");
$stmt->execute([$cid, $year]);
$newHires = $stmt->fetch()["count"];

// Headcount trend
$trend = [];
for($i = 5; $i >= 0; $i--) {
    $m = date("Y-m", strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM employees WHERE company_id = ? AND DATE_FORMAT(hire_date, \'%Y-%m\') <= ?");
    $stmt->execute([$cid, $m]);
    $trend[] = ["month" => date("M", strtotime("-$i months")), "count" => $stmt->fetch()["c"]];
}

// Dept attendance
$stmt = $pdo->prepare("SELECT d.name, COUNT(DISTINCT a.employee_id) as present, (SELECT COUNT(*) FROM employees WHERE department_id = d.id AND status = \'active\') as total FROM departments d LEFT JOIN employees e ON e.department_id = d.id LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE() WHERE d.company_id = ? GROUP BY d.id");
$stmt->execute([$cid]);
$deptAtt = $stmt->fetchAll();

// Claims
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status IN (\'submitted\',\'under_review\')) as pending, SUM(CASE WHEN status=\'paid\' THEN total_amount ELSE 0 END) as paid FROM expense_claims WHERE company_id = ? AND YEAR(created_at) = ?");
$stmt->execute([$cid, $year]);
$claimStats = $stmt->fetch();
?>
<div class="page-header">
    <h4><i class="bi bi-graph-up me-2"></i>HR Analytics Dashboard</h4>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Total Employees</h6>
                        <h2 class="mb-0"><?= $empStats["active"] ?? 0 ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
                <small class="text-white-50"><?= $newHires ?> new this year</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Present Today</h6>
                        <h2 class="mb-0"><?= $attStats["present"] ?? 0 ?></h2>
                    </div>
                    <i class="bi bi-clock fs-1 opacity-50"></i>
                </div>
                <small class="text-white-50">Avg <?= number_format($attStats["avg_hours"] ?? 0, 1) ?>h/day</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>Pending Leaves</h6>
                        <h2 class="mb-0"><?= $leaveStats["pending"] ?? 0 ?></h2>
                    </div>
                    <i class="bi bi-calendar fs-1 opacity-50"></i>
                </div>
                <small><?= $leaveStats["approved"] ?? 0 ?> approved YTD</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Payroll YTD</h6>
                        <h2 class="mb-0">₱<?= number_format($payrollTotal, 0) ?></h2>
                    </div>
                    <i class="bi bi-cash fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Headcount Trend</h6></div>
            <div class="card-body"><canvas id="trendChart" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Department Distribution</h6></div>
            <div class="card-body"><canvas id="deptChart" height="150"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Department Attendance</h6></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Department</th><th>Present</th><th>Total</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php foreach($deptAtt as $d): $r = $d["total"] > 0 ? ($d["present"]/$d["total"])*100 : 0; ?>
                        <tr><td><?= e($d["name"]) ?></td><td><?= $d["present"] ?></td><td><?= $d["total"] ?></td>
                        <td><div class="progress" style="width:80px;height:6px"><div class="progress-bar" style="width:<?= $r ?>%"></div></div></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Claims Summary (<?= $year ?>)</h6></div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4"><div class="border rounded p-3"><h4 class="text-warning"><?= $claimStats["pending"] ?? 0 ?></h4><small>Pending</small></div></div>
                    <div class="col-4"><div class="border rounded p-3"><h4 class="text-primary"><?= $claimStats["total"] ?? 0 ?></h4><small>Total</small></div></div>
                    <div class="col-4"><div class="border rounded p-3"><h4 class="text-success">₱<?= number_format($claimStats["paid"] ?? 0, 0) ?></h4><small>Paid</small></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById("trendChart"), {
    type: "line",
    data: {labels: <?= json_encode(array_column($trend, "month")) ?>, datasets: [{label: "Employees", data: <?= json_encode(array_column($trend, "count")) ?>, borderColor: "#0d6efd", fill: true, backgroundColor: "rgba(13,110,253,0.1)", tension: 0.3}]},
    options: {plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});
new Chart(document.getElementById("deptChart"), {
    type: "doughnut",
    data: {labels: <?= json_encode(array_column($deptData, "name")) ?>, datasets: [{data: <?= json_encode(array_column($deptData, "count")) ?>, backgroundColor: ["#0d6efd","#198754","#ffc107","#dc3545","#6f42c1","#0dcaf0"]}]},
    options: {plugins: {legend: {position: "bottom"}}}
});
</script>
<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($analyticsDir . '/index.php', $analyticsIndex)) {
    echo "✓ Created: modules/analytics/index.php\n";
}

// =====================================================
// COMPENSATION MODULE
// =====================================================
$compensationDir = $base . '/compensation';

$compensationIndex = '<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
require_login();
require_role(["Admin", "HR Officer"]);

$cid = company_id() ?? 1;

if(is_post() && verify_csrf()) {
    $action = $_POST["action"] ?? "";
    
    if($action === "add_bonus") {
        $pdo->prepare("INSERT INTO bonus_records (company_id, employee_id, bonus_type, amount, description, status) VALUES (?, ?, ?, ?, ?, \'pending\')")
            ->execute([$cid, (int)$_POST["employee_id"], $_POST["bonus_type"], (float)$_POST["amount"], trim($_POST["description"] ?? "")]);
        header("Location: index.php?msg=bonus_added"); exit;
    }
    
    if($action === "approve_bonus") {
        $pdo->prepare("UPDATE bonus_records SET status = \'approved\', approved_by = ?, approved_at = NOW() WHERE id = ? AND company_id = ?")
            ->execute([$_SESSION["user"]["id"], (int)$_POST["id"], $cid]);
        header("Location: index.php?msg=approved"); exit;
    }
    
    if($action === "salary_change") {
        $empId = (int)$_POST["employee_id"];
        $new = (float)$_POST["new_salary"];
        $emp = $pdo->prepare("SELECT basic_salary FROM employees WHERE id = ?");
        $emp->execute([$empId]);
        $prev = $emp->fetch()["basic_salary"] ?? 0;
        
        $pdo->prepare("INSERT INTO salary_history (company_id, employee_id, previous_salary, new_salary, change_type, change_reason, effective_date, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$cid, $empId, $prev, $new, $_POST["change_type"], trim($_POST["reason"] ?? ""), $_POST["effective_date"], $_SESSION["user"]["id"]]);
        $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?")->execute([$new, $empId]);
        header("Location: index.php?msg=updated"); exit;
    }
}

$employees = $pdo->prepare("SELECT e.*, p.name as position FROM employees e LEFT JOIN positions p ON p.id = e.position_id WHERE e.company_id = ? AND e.status = \'active\' ORDER BY e.first_name");
$employees->execute([$cid]);
$employees = $employees->fetchAll();

$bonuses = $pdo->prepare("SELECT b.*, e.first_name, e.last_name FROM bonus_records b JOIN employees e ON e.id = b.employee_id WHERE b.company_id = ? ORDER BY b.created_at DESC LIMIT 20");
$bonuses->execute([$cid]);
$bonuses = $bonuses->fetchAll();

$history = $pdo->prepare("SELECT sh.*, e.first_name, e.last_name FROM salary_history sh JOIN employees e ON e.id = sh.employee_id WHERE sh.company_id = ? ORDER BY sh.created_at DESC LIMIT 20");
$history->execute([$cid]);
$history = $history->fetchAll();

$stats = $pdo->prepare("SELECT AVG(basic_salary) as avg, MIN(basic_salary) as min, MAX(basic_salary) as max FROM employees WHERE company_id = ? AND status = \'active\'");
$stats->execute([$cid]);
$stats = $stats->fetch();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-currency-dollar me-2"></i>Compensation Planning</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bonusModal"><i class="bi bi-gift me-2"></i>Add Bonus</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salaryModal"><i class="bi bi-graph-up me-2"></i>Salary Change</button>
    </div>
</div>

<?php if(isset($_GET["msg"])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Done!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h6 class="text-muted">Average Salary</h6><h3 class="text-primary">₱<?= number_format($stats["avg"] ?? 0, 0) ?></h3></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h6 class="text-muted">Salary Range</h6><h5>₱<?= number_format($stats["min"] ?? 0, 0) ?> - ₱<?= number_format($stats["max"] ?? 0, 0) ?></h5></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h6 class="text-muted">Active Employees</h6><h3 class="text-info"><?= count($employees) ?></h3></div></div></div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent Bonuses</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Employee</th><th>Type</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($bonuses as $b): ?>
                        <tr>
                            <td><?= e($b["first_name"]." ".$b["last_name"]) ?></td>
                            <td><span class="badge bg-info"><?= ucfirst($b["bonus_type"]) ?></span></td>
                            <td>₱<?= number_format($b["amount"], 2) ?></td>
                            <td><span class="badge bg-<?= $b["status"]==="pending"?"warning":($b["status"]==="approved"?"success":"primary") ?>"><?= ucfirst($b["status"]) ?></span></td>
                            <td><?php if($b["status"]==="pending"): ?><form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="approve_bonus"><input type="hidden" name="id" value="<?= $b["id"] ?>"><button class="btn btn-sm btn-success"><i class="bi bi-check"></i></button></form><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Salary Changes</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Employee</th><th>Type</th><th>Change</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($history as $h): $d = $h["new_salary"] - $h["previous_salary"]; ?>
                        <tr>
                            <td><?= e($h["first_name"]." ".$h["last_name"]) ?></td>
                            <td><span class="badge bg-secondary"><?= ucwords(str_replace("_"," ",$h["change_type"])) ?></span></td>
                            <td class="<?= $d >= 0 ? "text-success" : "text-danger" ?>"><?= $d >= 0 ? "+" : "" ?>₱<?= number_format($d, 0) ?></td>
                            <td><?= date("M j, Y", strtotime($h["effective_date"])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bonusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="add_bonus">
<div class="modal-header"><h5 class="modal-title">Add Bonus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><option value="">Select...</option><?php foreach($employees as $e): ?><option value="<?= $e["id"] ?>"><?= e($e["first_name"]." ".$e["last_name"]) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="bonus_type"><option value="performance">Performance</option><option value="attendance">Attendance</option><option value="holiday">Holiday</option><option value="referral">Referral</option><option value="other">Other</option></select></div>
    <div class="mb-3"><label class="form-label">Amount (₱)</label><input type="number" class="form-control" name="amount" step="0.01" required></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Add</button></div>
</form></div></div></div>

<div class="modal fade" id="salaryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="salary_change">
<div class="modal-header"><h5 class="modal-title">Salary Change</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><option value="">Select...</option><?php foreach($employees as $e): ?><option value="<?= $e["id"] ?>"><?= e($e["first_name"]." ".$e["last_name"]) ?> - ₱<?= number_format($e["basic_salary"], 0) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="change_type"><option value="promotion">Promotion</option><option value="annual_increase">Annual Increase</option><option value="adjustment">Adjustment</option></select></div>
    <div class="mb-3"><label class="form-label">New Salary (₱)</label><input type="number" class="form-control" name="new_salary" step="0.01" required></div>
    <div class="mb-3"><label class="form-label">Effective Date</label><input type="date" class="form-control" name="effective_date" value="<?= date("Y-m-d") ?>" required></div>
    <div class="mb-3"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
';

if(file_put_contents($compensationDir . '/index.php', $compensationIndex)) {
    echo "✓ Created: modules/compensation/index.php\n";
}

echo "\n</pre>";
echo "<p><strong>Done!</strong></p>";
echo "<p><a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a></p>";
echo "<p><a href='" . dirname($_SERVER['PHP_SELF']) . "/settings/index.php'>Go to Settings</a></p>";


