<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
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

    if($action === "update_attendance_policy") {
        $grace = max(0, (int)($_POST["grace_period_minutes"] ?? 10));
        $dup = max(1, (int)($_POST["duplicate_scan_seconds"] ?? 3));
        $seq = isset($_POST["require_action_sequence"]) ? 1 : 0;
        $gps = isset($_POST["gps_capture_enabled"]) ? 1 : 0;
        $before = max(0, (int)($_POST["out_of_shift_grace_before_minutes"] ?? 60));
        $after = max(0, (int)($_POST["out_of_shift_grace_after_minutes"] ?? 60));

        $st = $pdo->prepare("INSERT INTO attendance_settings (company_id, grace_period_minutes, duplicate_scan_seconds, require_action_sequence, gps_capture_enabled, out_of_shift_grace_before_minutes, out_of_shift_grace_after_minutes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grace_period_minutes=VALUES(grace_period_minutes), duplicate_scan_seconds=VALUES(duplicate_scan_seconds),
            require_action_sequence=VALUES(require_action_sequence), gps_capture_enabled=VALUES(gps_capture_enabled),
            out_of_shift_grace_before_minutes=VALUES(out_of_shift_grace_before_minutes), out_of_shift_grace_after_minutes=VALUES(out_of_shift_grace_after_minutes)");
        $st->execute([company_id(), $grace, $dup, $seq, $gps, $before, $after]);
        $success = "Attendance policy updated!";
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

// Get subscription plan
$plan = $pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
$plan->execute([$company["plan"] ?? "free"]);
$plan = $plan->fetch();

// Get employee count
$empCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE company_id = ? AND status = 'active'");
$empCount->execute([company_id()]);
$empCount = $empCount->fetch()["cnt"];

$attSet = $pdo->prepare("SELECT * FROM attendance_settings WHERE company_id = ? LIMIT 1");
$attSet->execute([company_id()]);
$attSet = $attSet->fetch() ?: [
    "grace_period_minutes" => 10,
    "duplicate_scan_seconds" => 3,
    "require_action_sequence" => 1,
    "gps_capture_enabled" => 0,
    "out_of_shift_grace_before_minutes" => 60,
    "out_of_shift_grace_after_minutes" => 60
];

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
            <!-- Subscription Plan Card -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-award me-2"></i>Subscription Plan</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <span class="badge bg-primary fs-5 px-4 py-2"><?= htmlspecialchars($plan["name"] ?? "Free") ?></span>
                        </div>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-people text-primary me-2"></i>
                                <strong>Employees:</strong> <?= $empCount ?> / <?= $plan["max_employees"] == 9999 ? "Unlimited" : $plan["max_employees"] ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-calendar text-primary me-2"></i>
                                <strong>Price:</strong> ₱<?= number_format($plan["price_monthly"], 2) ?>/mo
                            </li>
                        </ul>
                        <hr>
                        <h6>Features:</h6>
                        <?php 
                        $features = json_decode($plan["features"] ?? "{}", true);
                        foreach($features as $feat => $enabled): ?>
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-<?= $enabled ? "check-circle-fill text-success" : "x-circle text-muted" ?> me-2"></i>
                            <span class="<?= $enabled ? "" : "text-muted" ?>"><?= ucfirst(str_replace("_", " ", $feat)) ?></span>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-4">
                            <button class="btn btn-outline-primary w-100" disabled>
                                <i class="bi bi-arrow-up-circle me-2"></i>Upgrade Plan
                            </button>
                            <small class="text-muted d-block mt-2 text-center">Contact support to upgrade</small>
                        </div>
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
                            <?= csrf_input() ?>
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
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Attendance Scan Policy</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_attendance_policy">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">Grace (min)</label>
                                    <input type="number" min="0" class="form-control" name="grace_period_minutes" value="<?= (int)$attSet["grace_period_minutes"] ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Duplicate window (sec)</label>
                                    <input type="number" min="1" class="form-control" name="duplicate_scan_seconds" value="<?= (int)$attSet["duplicate_scan_seconds"] ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Before-shift grace (min)</label>
                                    <input type="number" min="0" class="form-control" name="out_of_shift_grace_before_minutes" value="<?= (int)$attSet["out_of_shift_grace_before_minutes"] ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">After-shift grace (min)</label>
                                    <input type="number" min="0" class="form-control" name="out_of_shift_grace_after_minutes" value="<?= (int)$attSet["out_of_shift_grace_after_minutes"] ?>">
                                </div>
                                <div class="col-md-2 d-flex flex-column justify-content-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_action_sequence" id="require_action_sequence" <?= !empty($attSet["require_action_sequence"]) ? "checked" : "" ?>>
                                        <label class="form-check-label" for="require_action_sequence">Strict sequence</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="gps_capture_enabled" id="gps_capture_enabled" <?= !empty($attSet["gps_capture_enabled"]) ? "checked" : "" ?>>
                                        <label class="form-check-label" for="gps_capture_enabled">Capture GPS</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-2"></i>Save Attendance Policy</button>
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
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?')">
                                    <?= csrf_input() ?>
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
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this position?')">
                                    <?= csrf_input() ?>
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
                <?= csrf_input() ?>
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
                <?= csrf_input() ?>
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
