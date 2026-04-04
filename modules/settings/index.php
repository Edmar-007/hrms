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
        $name    = trim($_POST["company_name"]);
        $email   = trim($_POST["company_email"]);
        $phone   = trim($_POST["company_phone"]);
        $address = trim($_POST["company_address"]);
        $tz      = $_POST["timezone"];
        $cur     = $_POST["currency"];
        $wdStart = $_POST["work_day_start"] ?? "08:00";
        $wdEnd   = $_POST["work_day_end"]   ?? "17:00";
        $dateFmt = $_POST["date_format"]    ?? "M j, Y";

        $st = $pdo->prepare("UPDATE companies SET name=?, email=?, phone=?, address=?, timezone=?, currency=? WHERE id=?");
        if($st->execute([$name, $email, $phone, $address, $tz, $cur, company_id()])) {
            $_SESSION["company"]["name"] = $name;
            $success = "Company information updated!";
            log_activity("update", "company", company_id());
        }
        // Store optional work schedule in company meta if columns exist
        try {
            $pdo->prepare("UPDATE companies SET work_day_start=?, work_day_end=?, date_format=? WHERE id=?")
                ->execute([$wdStart, $wdEnd, $dateFmt, company_id()]);
        } catch(PDOException $e) { /* columns may not exist yet – silently skip */ }
    }

    if($action === "add_department") {
        $name = trim($_POST["dept_name"]);
        if($name) {
            $pdo->prepare("INSERT IGNORE INTO departments (company_id, name) VALUES (?, ?)")->execute([company_id(), $name]);
            $success = "Department added!";
        }
    }

    if($action === "edit_department") {
        $id   = (int)$_POST["id"];
        $name = trim($_POST["dept_name"]);
        if($name && $id) {
            $pdo->prepare("UPDATE departments SET name=? WHERE id=? AND company_id=?")->execute([$name, $id, company_id()]);
            $success = "Department updated!";
        }
    }

    if($action === "delete_department") {
        $id = (int)$_POST["id"];
        $pdo->prepare("DELETE FROM departments WHERE id=? AND company_id=?")->execute([$id, company_id()]);
        $success = "Department deleted!";
    }

    if($action === "add_position") {
        $name = trim($_POST["pos_name"]);
        if($name) {
            $pdo->prepare("INSERT IGNORE INTO positions (company_id, name) VALUES (?, ?)")->execute([company_id(), $name]);
            $success = "Position added!";
        }
    }

    if($action === "edit_position") {
        $id   = (int)$_POST["id"];
        $name = trim($_POST["pos_name"]);
        if($name && $id) {
            $pdo->prepare("UPDATE positions SET name=? WHERE id=? AND company_id=?")->execute([$name, $id, company_id()]);
            $success = "Position updated!";
        }
    }

    if($action === "delete_position") {
        $id = (int)$_POST["id"];
        $pdo->prepare("DELETE FROM positions WHERE id=? AND company_id=?")->execute([$id, company_id()]);
        $success = "Position deleted!";
    }

    if($action === "add_leave_type") {
        $name    = trim($_POST["lt_name"]);
        $days    = (int)$_POST["lt_days"];
        $isPaid  = isset($_POST["lt_paid"]) ? 1 : 0;
        if($name && $days > 0) {
            $hasCid = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
            if($hasCid) {
                $pdo->prepare("INSERT IGNORE INTO leave_types (company_id, name, days_allowed, is_paid) VALUES (?,?,?,?)")
                    ->execute([company_id(), $name, $days, $isPaid]);
            } else {
                $pdo->prepare("INSERT IGNORE INTO leave_types (name, days_allowed, is_paid) VALUES (?,?,?)")
                    ->execute([$name, $days, $isPaid]);
            }
            $success = "Leave type added!";
        }
    }

    if($action === "edit_leave_type") {
        $id      = (int)$_POST["id"];
        $name    = trim($_POST["lt_name"]);
        $days    = (int)$_POST["lt_days"];
        $isPaid  = isset($_POST["lt_paid"]) ? 1 : 0;
        if($name && $days > 0 && $id) {
            $hasCid = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
            if($hasCid) {
                $pdo->prepare("UPDATE leave_types SET name=?, days_allowed=?, is_paid=? WHERE id=? AND company_id=?")
                    ->execute([$name, $days, $isPaid, $id, company_id()]);
            } else {
                $pdo->prepare("UPDATE leave_types SET name=?, days_allowed=?, is_paid=? WHERE id=?")
                    ->execute([$name, $days, $isPaid, $id]);
            }
            $success = "Leave type updated!";
        }
    }

    if($action === "delete_leave_type") {
        $id = (int)$_POST["id"];
        $hasCid = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
        if($hasCid) {
            $pdo->prepare("DELETE FROM leave_types WHERE id=? AND company_id=?")->execute([$id, company_id()]);
        } else {
            $pdo->prepare("DELETE FROM leave_types WHERE id=?")->execute([$id]);
        }
        $success = "Leave type deleted!";
    }

    if($action === "change_password") {
        $current = $_POST["current_password"] ?? "";
        $new     = $_POST["new_password"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";

        $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $st->execute([$user["id"]]);
        $row = $st->fetch();

        if(!$row || !password_verify($current, $row["password"])) {
            $error = "Current password is incorrect.";
        } elseif(strlen($new) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user["id"]]);
            $success = "Password changed successfully!";
            log_activity("update", "user_password", $user["id"]);
        }
    }

    // Reload company data
    $st = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $st->execute([company_id()]);
    $company = $st->fetch();
    $_SESSION["company"] = array_merge($_SESSION["company"] ?? [], $company);
}

// Get departments and positions
$departments = $pdo->prepare("SELECT * FROM departments WHERE company_id = ? ORDER BY name");
$departments->execute([company_id()]); $departments = $departments->fetchAll();

$positions = $pdo->prepare("SELECT * FROM positions WHERE company_id = ? ORDER BY name");
$positions->execute([company_id()]); $positions = $positions->fetchAll();

// Leave types
$hasCidLt = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
if($hasCidLt) {
    $ltSt = $pdo->prepare("SELECT * FROM leave_types WHERE company_id=? ORDER BY name");
    $ltSt->execute([company_id()]); $leaveTypes = $ltSt->fetchAll();
} else {
    $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
}

// Subscription plan
$plan = $pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
$plan->execute([$company["plan"] ?? "free"]);
$plan = $plan->fetch();

$empCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE company_id=? AND status='active'");
$empCount->execute([company_id()]); $empCount = $empCount->fetch()["cnt"];

// Determine active tab from query string (for post-redirect-get pattern)
$activeTab = $_GET["tab"] ?? "company";
$allowed = ["company","org","leaves","schedule","appearance","security","subscription"];
if(!in_array($activeTab, $allowed)) $activeTab = "company";

require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
?>

<div class="main-content">
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-gear me-2"></i>Settings</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol></nav>
        </div>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sidebar Nav Tabs -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2">
                    <nav class="settings-tabs nav flex-column" id="settingsTabs">
                        <button class="nav-link <?= $activeTab==='company'?'active':'' ?>" onclick="switchTab('company')">
                            <i class="bi bi-building"></i> Company Info
                        </button>
                        <button class="nav-link <?= $activeTab==='org'?'active':'' ?>" onclick="switchTab('org')">
                            <i class="bi bi-diagram-3"></i> Organization
                        </button>
                        <button class="nav-link <?= $activeTab==='leaves'?'active':'' ?>" onclick="switchTab('leaves')">
                            <i class="bi bi-calendar-week"></i> Leave Types
                        </button>
                        <button class="nav-link <?= $activeTab==='schedule'?'active':'' ?>" onclick="switchTab('schedule')">
                            <i class="bi bi-clock-history"></i> Work Schedule
                        </button>
                        <button class="nav-link <?= $activeTab==='appearance'?'active':'' ?>" onclick="switchTab('appearance')">
                            <i class="bi bi-palette"></i> Appearance
                        </button>
                        <button class="nav-link <?= $activeTab==='security'?'active':'' ?>" onclick="switchTab('security')">
                            <i class="bi bi-shield-lock"></i> Security
                        </button>
                        <hr class="my-2">
                        <button class="nav-link <?= $activeTab==='subscription'?'active':'' ?>" onclick="switchTab('subscription')">
                            <i class="bi bi-award"></i> Subscription
                        </button>
                        <a class="nav-link" href="holidays.php">
                            <i class="bi bi-calendar-event"></i> Holidays
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Content Panels -->
        <div class="col-lg-9">

            <!-- ===== COMPANY INFO ===== -->
            <div id="tab-company" class="settings-section <?= $activeTab==='company'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header"><i class="bi bi-building me-2"></i>Company Information</div>
                    <div class="card-body">
                        <form method="POST" action="?tab=company">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_company">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company["name"]) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($company["email"]) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($company["phone"] ?? "") ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-select">
                                        <?php foreach(["Asia/Manila","Asia/Singapore","Asia/Jakarta","Asia/Kuala_Lumpur","Asia/Bangkok","UTC","America/New_York","America/Los_Angeles","Europe/London"] as $tz): ?>
                                        <option value="<?= $tz ?>" <?= ($company["timezone"]??"")===$tz?"selected":"" ?>><?= $tz ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <option value="PHP" <?= ($company["currency"]??"")==="PHP"?"selected":"" ?>>PHP (₱)</option>
                                        <option value="USD" <?= ($company["currency"]??"")==="USD"?"selected":"" ?>>USD ($)</option>
                                        <option value="SGD" <?= ($company["currency"]??"")==="SGD"?"selected":"" ?>>SGD (S$)</option>
                                        <option value="EUR" <?= ($company["currency"]??"")==="EUR"?"selected":"" ?>>EUR (€)</option>
                                        <option value="GBP" <?= ($company["currency"]??"")==="GBP"?"selected":"" ?>>GBP (£)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($company["address"] ?? "") ?></textarea>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ===== ORGANIZATION (Departments + Positions) ===== -->
            <div id="tab-org" class="settings-section <?= $activeTab==='org'?'active':'' ?>">
                <div class="row g-4">
                    <!-- Departments -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-diagram-3 me-2"></i>Departments</span>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                                    <i class="bi bi-plus-lg me-1"></i>Add
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                <?php foreach($departments as $d): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-folder2 text-primary me-2"></i><?= htmlspecialchars($d["name"]) ?></span>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info" onclick="editDept(<?= $d['id'] ?>, '<?= e(addslashes($d['name'])) ?>')" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete department?')">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_department">
                                            <input type="hidden" name="id" value="<?= $d["id"] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if(empty($departments)): ?>
                                <li class="list-group-item text-muted text-center py-3">No departments yet</li>
                                <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Positions -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-badge me-2"></i>Positions</span>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPosModal">
                                    <i class="bi bi-plus-lg me-1"></i>Add
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                <?php foreach($positions as $p): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-briefcase text-success me-2"></i><?= htmlspecialchars($p["name"]) ?></span>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info" onclick="editPos(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete position?')">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_position">
                                            <input type="hidden" name="id" value="<?= $p["id"] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if(empty($positions)): ?>
                                <li class="list-group-item text-muted text-center py-3">No positions yet</li>
                                <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== LEAVE TYPES ===== -->
            <div id="tab-leaves" class="settings-section <?= $activeTab==='leaves'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-calendar-week me-2"></i>Leave Types</span>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLtModal">
                            <i class="bi bi-plus-lg me-1"></i>Add Leave Type
                        </button>
                    </div>
                    <div class="table-responsive-wrapper">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Days / Year</th>
                                    <th>Paid?</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(empty($leaveTypes)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No leave types defined yet</td></tr>
                            <?php else: foreach($leaveTypes as $lt): ?>
                            <tr>
                                <td><i class="bi bi-calendar2-check text-primary me-2"></i><?= htmlspecialchars($lt["name"]) ?></td>
                                <td><span class="badge bg-info bg-opacity-75 text-dark"><?= (int)$lt["days_allowed"] ?> days</span></td>
                                <td>
                                    <?php if($lt["is_paid"]): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns text-center">
                                    <button class="btn btn-sm btn-outline-info" onclick="editLt(<?= $lt['id'] ?>, '<?= e(addslashes($lt['name'])) ?>', <?= (int)$lt['days_allowed'] ?>, <?= (int)$lt['is_paid'] ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this leave type?')">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_leave_type">
                                        <input type="hidden" name="id" value="<?= $lt["id"] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===== WORK SCHEDULE ===== -->
            <div id="tab-schedule" class="settings-section <?= $activeTab==='schedule'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Work Schedule &amp; Date Format</div>
                    <div class="card-body">
                        <form method="POST" action="?tab=schedule">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_company">
                            <!-- Pass company fields through as hidden to preserve them -->
                            <input type="hidden" name="company_name"    value="<?= htmlspecialchars($company["name"]) ?>">
                            <input type="hidden" name="company_email"   value="<?= htmlspecialchars($company["email"]) ?>">
                            <input type="hidden" name="company_phone"   value="<?= htmlspecialchars($company["phone"] ?? "") ?>">
                            <input type="hidden" name="company_address" value="<?= htmlspecialchars($company["address"] ?? "") ?>">
                            <input type="hidden" name="timezone"  value="<?= htmlspecialchars($company["timezone"] ?? "Asia/Manila") ?>">
                            <input type="hidden" name="currency"  value="<?= htmlspecialchars($company["currency"] ?? "PHP") ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Work Day Start</label>
                                    <input type="time" name="work_day_start" class="form-control" value="<?= htmlspecialchars($company["work_day_start"] ?? "08:00") ?>">
                                    <small class="text-muted">Default shift start time</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Work Day End</label>
                                    <input type="time" name="work_day_end" class="form-control" value="<?= htmlspecialchars($company["work_day_end"] ?? "17:00") ?>">
                                    <small class="text-muted">Default shift end time</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date Format</label>
                                    <select name="date_format" class="form-select">
                                        <?php $df = $company["date_format"] ?? "M j, Y"; ?>
                                        <option value="M j, Y"    <?= $df==="M j, Y"?"selected":"" ?>>Jan 1, 2025</option>
                                        <option value="d/m/Y"     <?= $df==="d/m/Y"?"selected":"" ?>>01/01/2025</option>
                                        <option value="m/d/Y"     <?= $df==="m/d/Y"?"selected":"" ?>>01/01/2025 (US)</option>
                                        <option value="Y-m-d"     <?= $df==="Y-m-d"?"selected":"" ?>>2025-01-01</option>
                                        <option value="F j, Y"    <?= $df==="F j, Y"?"selected":"" ?>>January 1, 2025</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        To manage individual employee shifts, go to
                                        <a href="../attendance/shifts/index.php" class="alert-link">Attendance → Shifts</a>.
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ===== APPEARANCE ===== -->
            <div id="tab-appearance" class="settings-section <?= $activeTab==='appearance'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header"><i class="bi bi-palette me-2"></i>Appearance &amp; Display</div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Theme</h6>
                                <div class="d-flex gap-3">
                                    <button class="theme-preview-btn <?= ($user['theme']??'light')==='light'?'active':'' ?>"
                                            onclick="applyTheme('light')" id="btnLight">
                                        <div class="preview-card light-preview">
                                            <div class="preview-bar"></div>
                                            <div class="preview-content"></div>
                                        </div>
                                        <div class="mt-2 text-center fw-semibold"><i class="bi bi-sun me-1"></i>Light</div>
                                    </button>
                                    <button class="theme-preview-btn <?= ($user['theme']??'light')==='dark'?'active':'' ?>"
                                            onclick="applyTheme('dark')" id="btnDark">
                                        <div class="preview-card dark-preview">
                                            <div class="preview-bar"></div>
                                            <div class="preview-content"></div>
                                        </div>
                                        <div class="mt-2 text-center fw-semibold"><i class="bi bi-moon me-1"></i>Dark</div>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Current Settings</h6>
                                <table class="table table-sm">
                                    <tr><td class="text-muted">Active Theme</td><td><span id="currentTheme" class="badge bg-primary"><?= ucfirst($user['theme']??'light') ?></span></td></tr>
                                    <tr><td class="text-muted">Currency</td><td><?= htmlspecialchars($company["currency"] ?? "PHP") ?></td></tr>
                                    <tr><td class="text-muted">Timezone</td><td><?= htmlspecialchars($company["timezone"] ?? "Asia/Manila") ?></td></tr>
                                </table>
                                <small class="text-muted">Theme is saved per user account. Currency and timezone are set in Company Info.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== SECURITY ===== -->
            <div id="tab-security" class="settings-section <?= $activeTab==='security'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
                    <div class="card-body">
                        <form method="POST" action="?tab=security" style="max-width:460px;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password" id="newPwd">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password" id="confirmPwd">
                            </div>
                            <!-- Password strength indicator -->
                            <div class="mb-3">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar" id="pwdStrengthBar" style="width:0%;transition:width 0.3s;"></div>
                                </div>
                                <small id="pwdStrengthLabel" class="text-muted"></small>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-key me-2"></i>Update Password</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ===== SUBSCRIPTION ===== -->
            <div id="tab-subscription" class="settings-section <?= $activeTab==='subscription'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white"><i class="bi bi-award me-2"></i>Subscription Plan</div>
                    <div class="card-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-5 text-center">
                                <div class="mb-3">
                                    <span class="badge bg-primary fs-5 px-4 py-2"><?= htmlspecialchars($plan["name"] ?? "Free") ?></span>
                                </div>
                                <p class="text-muted mb-1"><i class="bi bi-people me-2"></i><strong><?= $empCount ?></strong> / <?= ($plan["max_employees"]??0) == 9999 ? "Unlimited" : ($plan["max_employees"]??0) ?> employees</p>
                                <p class="text-muted mb-1"><i class="bi bi-currency-dollar me-2"></i>₱<?= number_format($plan["price_monthly"] ?? 0, 2) ?> / month</p>
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary w-100" disabled>
                                        <i class="bi bi-arrow-up-circle me-2"></i>Upgrade Plan
                                    </button>
                                    <small class="text-muted d-block mt-2">Contact support to upgrade</small>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h6 class="fw-bold mb-3">Plan Features</h6>
                                <?php
                                $features = json_decode($plan["features"] ?? "{}", true);
                                foreach($features as $feat => $enabled): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-<?= $enabled ? "check-circle-fill text-success" : "x-circle text-muted" ?> me-2"></i>
                                    <span class="<?= $enabled ? "" : "text-muted" ?>"><?= ucfirst(str_replace("_", " ", $feat)) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-9 -->
    </div><!-- /row -->
</div><!-- /container-fluid -->
</div><!-- /main-content -->

<!-- ===== MODALS ===== -->

<!-- Add / Edit Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?tab=org" id="deptForm">
                <?= csrf_input() ?>
                <input type="hidden" name="action" id="deptAction" value="add_department">
                <input type="hidden" name="id"     id="deptId"     value="">
                <div class="modal-header"><h5 class="modal-title" id="deptModalTitle">Add Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Department Name</label>
                    <input type="text" name="dept_name" id="deptName" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add / Edit Position -->
<div class="modal fade" id="addPosModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?tab=org" id="posForm">
                <?= csrf_input() ?>
                <input type="hidden" name="action" id="posAction" value="add_position">
                <input type="hidden" name="id"     id="posId"     value="">
                <div class="modal-header"><h5 class="modal-title" id="posModalTitle">Add Position</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Position Name</label>
                    <input type="text" name="pos_name" id="posName" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add / Edit Leave Type -->
<div class="modal fade" id="addLtModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?tab=leaves" id="ltForm">
                <?= csrf_input() ?>
                <input type="hidden" name="action" id="ltAction" value="add_leave_type">
                <input type="hidden" name="id"     id="ltId"     value="">
                <div class="modal-header"><h5 class="modal-title" id="ltModalTitle">Add Leave Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Leave Type Name</label>
                        <input type="text" name="lt_name" id="ltName" class="form-control" required placeholder="e.g., Sick Leave">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Days Allowed per Year</label>
                        <input type="number" name="lt_days" id="ltDays" class="form-control" min="1" max="365" value="5" required>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="lt_paid" id="ltPaid" checked>
                            <label class="form-check-label" for="ltPaid">Paid Leave</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab switching
function switchTab(name) {
    document.querySelectorAll('.settings-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#settingsTabs .nav-link').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name)?.classList.add('active');
    document.querySelectorAll('#settingsTabs .nav-link').forEach(el => {
        if(el.getAttribute('onclick') === "switchTab('" + name + "')") el.classList.add('active');
    });
    history.replaceState(null, '', '?tab=' + name);
}

// Department edit
function editDept(id, name) {
    document.getElementById('deptAction').value = 'edit_department';
    document.getElementById('deptId').value = id;
    document.getElementById('deptName').value = name;
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    new bootstrap.Modal(document.getElementById('addDeptModal')).show();
}
document.getElementById('addDeptModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('deptAction').value = 'add_department';
    document.getElementById('deptId').value = '';
    document.getElementById('deptName').value = '';
    document.getElementById('deptModalTitle').textContent = 'Add Department';
});

// Position edit
function editPos(id, name) {
    document.getElementById('posAction').value = 'edit_position';
    document.getElementById('posId').value = id;
    document.getElementById('posName').value = name;
    document.getElementById('posModalTitle').textContent = 'Edit Position';
    new bootstrap.Modal(document.getElementById('addPosModal')).show();
}
document.getElementById('addPosModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('posAction').value = 'add_position';
    document.getElementById('posId').value = '';
    document.getElementById('posName').value = '';
    document.getElementById('posModalTitle').textContent = 'Add Position';
});

// Leave type edit
function editLt(id, name, days, paid) {
    document.getElementById('ltAction').value = 'edit_leave_type';
    document.getElementById('ltId').value = id;
    document.getElementById('ltName').value = name;
    document.getElementById('ltDays').value = days;
    document.getElementById('ltPaid').checked = paid === 1;
    document.getElementById('ltModalTitle').textContent = 'Edit Leave Type';
    new bootstrap.Modal(document.getElementById('addLtModal')).show();
}
document.getElementById('addLtModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('ltAction').value = 'add_leave_type';
    document.getElementById('ltId').value = '';
    document.getElementById('ltName').value = '';
    document.getElementById('ltDays').value = '5';
    document.getElementById('ltPaid').checked = true;
    document.getElementById('ltModalTitle').textContent = 'Add Leave Type';
});

// Theme switching
function applyTheme(theme) {
    fetch('<?= BASE_URL ?>/api/preferences.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({theme})
    }).then(() => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.body.className = document.body.className.replace(/theme-\w+/, 'theme-' + theme);
        document.getElementById('currentTheme').textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
        document.querySelectorAll('.theme-preview-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(theme === 'light' ? 'btnLight' : 'btnDark').classList.add('active');
    });
}

// Password strength meter
const newPwd = document.getElementById('newPwd');
if (newPwd) {
    newPwd.addEventListener('input', function() {
        const v = this.value, bar = document.getElementById('pwdStrengthBar'), lbl = document.getElementById('pwdStrengthLabel');
        let score = 0;
        if (v.length >= 8)  score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^a-zA-Z0-9]/.test(v)) score++;
        const colors = ['#ef4444','#f59e0b','#10b981','#059669'];
        const labels = ['Weak','Fair','Good','Strong'];
        bar.style.width = (score * 25) + '%';
        bar.style.background = colors[score - 1] || '#e2e8f0';
        lbl.textContent = score > 0 ? labels[score - 1] : '';
    });
}
</script>

<style>
.theme-preview-btn {
    background: none;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    width: 110px;
}
.theme-preview-btn.active, .theme-preview-btn:hover {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
}
.preview-card {
    border-radius: 8px;
    padding: 0.5rem;
    height: 70px;
    overflow: hidden;
}
.light-preview { background: #f1f5f9; }
.dark-preview  { background: #0f172a; }
.preview-bar   { height: 10px; border-radius: 4px; margin-bottom: 4px; }
.light-preview .preview-bar   { background: #1e293b; }
.dark-preview  .preview-bar   { background: #334155; }
.preview-content { height: 36px; border-radius: 6px; }
.light-preview .preview-content { background: #ffffff; }
.dark-preview  .preview-content { background: #1e293b; }
</style>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
