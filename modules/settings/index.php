<?php
require_once __DIR__."/../../config/db.php";
require_once __DIR__."/../../includes/auth.php";
require_once __DIR__."/../../includes/csrf.php";
require_login();
require_role(["Admin","HR Officer"]);

$pageTitle = "Settings";
$user = $_SESSION["user"];

// Always load fresh company data from DB to get all fields (session only has subset)
$cid = company_id() ?? 1;
$companyStmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$companyStmt->execute([$cid]);
$company = $companyStmt->fetch() ?: (company() ?? []);

$accentThemeOptions = [
    "teal" => [
        "label" => "Coastal Teal",
        "hint" => "Balanced and modern with a bright accent.",
        "colors" => ["#0f766e", "#14b8a6", "#f97316"]
    ],
    "indigo" => [
        "label" => "Indigo Pulse",
        "hint" => "Sharper and more executive for admin-heavy work.",
        "colors" => ["#312e81", "#4f46e5", "#fb7185"]
    ],
    "sunset" => [
        "label" => "Sunset Signal",
        "hint" => "Warm and bold with a more editorial feel.",
        "colors" => ["#9a3412", "#ea580c", "#fbbf24"]
    ],
    "emerald" => [
        "label" => "Emerald Desk",
        "hint" => "Fresh and energetic without being loud.",
        "colors" => ["#065f46", "#047857", "#38bdf8"]
    ],
    "mono" => [
        "label" => "Monochrome Slate",
        "hint" => "Minimal and high-contrast for focused teams.",
        "colors" => ["#111827", "#374151", "#9ca3af"]
    ],
];

$surfaceStyleOptions = [
    "glass" => ["label" => "Glass", "hint" => "Soft blur and layered panels."],
    "solid" => ["label" => "Solid", "hint" => "Cleaner blocks with less blur."],
    "contrast" => ["label" => "Contrast", "hint" => "Sharper cards for denser work."],
];

$sidebarStateOptions = [
    "expanded" => ["label" => "Expanded", "hint" => "Open the full navigation by default."],
    "collapsed" => ["label" => "Compact", "hint" => "Start with the smaller sidebar and remember user toggles."],
];

$sidebarColorOptions = [
    "default" => ["label" => "Midnight", "colors" => ["#0f172a", "#1e293b"]],
    "indigo" => ["label" => "Indigo", "colors" => ["#312e81", "#4338ca"]],
    "teal" => ["label" => "Teal", "colors" => ["#134e4a", "#0d9488"]],
    "rose" => ["label" => "Rose", "colors" => ["#881337", "#be123c"]],
    "emerald" => ["label" => "Emerald", "colors" => ["#064e3b", "#059669"]],
    "amber" => ["label" => "Amber", "colors" => ["#78350f", "#d97706"]],
    "purple" => ["label" => "Purple", "colors" => ["#581c87", "#9333ea"]],
    "blue" => ["label" => "Blue", "colors" => ["#1e3a5f", "#2563eb"]],
];

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

    if($action === "update_appearance") {
        $sidebarColor = $_POST["sidebar_color"] ?? "default";
        $accentTheme = $_POST["accent_theme"] ?? "teal";
        $surfaceStyle = $_POST["surface_style"] ?? "glass";
        $sidebarDefaultState = $_POST["sidebar_default_state"] ?? "expanded";
        $logoUrl = trim($_POST["logo_url"] ?? "");

        if(!isset($sidebarColorOptions[$sidebarColor])) {
            $sidebarColor = "default";
        }
        if(!isset($accentThemeOptions[$accentTheme])) {
            $accentTheme = "teal";
        }
        if(!isset($surfaceStyleOptions[$surfaceStyle])) {
            $surfaceStyle = "glass";
        }
        if(!isset($sidebarStateOptions[$sidebarDefaultState])) {
            $sidebarDefaultState = "expanded";
        }
        
        // Handle logo upload
        if(!empty($_FILES["logo_file"]["name"])) {
            $uploadDir = __DIR__ . "/../../public/uploads/logos/";
            if(!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/svg+xml"];
            $fileType = $_FILES["logo_file"]["type"];
            
            if(in_array($fileType, $allowedTypes) && $_FILES["logo_file"]["size"] <= 2097152) {
                $ext = pathinfo($_FILES["logo_file"]["name"], PATHINFO_EXTENSION);
                $filename = "logo_" . company_id() . "_" . time() . "." . $ext;
                $targetPath = $uploadDir . $filename;
                
                if(move_uploaded_file($_FILES["logo_file"]["tmp_name"], $targetPath)) {
                    $logoUrl = BASE_URL . "/public/uploads/logos/" . $filename;
                }
            }
        }
        
        $existingNavSettings = json_decode($company["nav_settings"] ?? "{}", true);
        if(!is_array($existingNavSettings)) {
            $existingNavSettings = [];
        }

        $navSettings = json_encode(array_merge($existingNavSettings, [
            "sidebar_color" => $sidebarColor,
            "accent_theme" => $accentTheme,
            "surface_style" => $surfaceStyle,
            "sidebar_default_state" => $sidebarDefaultState
        ]));
        
        try {
            $pdo->prepare("UPDATE companies SET logo_url=?, nav_settings=? WHERE id=?")
                ->execute([$logoUrl, $navSettings, company_id()]);
            $_SESSION["company"]["logo_url"] = $logoUrl;
            $_SESSION["company"]["nav_settings"] = $navSettings;
            $success = "Appearance settings updated!";
        } catch(PDOException $e) {
            // Columns may not exist, try adding them
            try {
                $pdo->exec("ALTER TABLE companies ADD COLUMN logo_url VARCHAR(500) DEFAULT NULL");
                $pdo->exec("ALTER TABLE companies ADD COLUMN nav_settings JSON DEFAULT NULL");
                $pdo->prepare("UPDATE companies SET logo_url=?, nav_settings=? WHERE id=?")
                    ->execute([$logoUrl, $navSettings, company_id()]);
                $_SESSION["company"]["logo_url"] = $logoUrl;
                $_SESSION["company"]["nav_settings"] = $navSettings;
                $success = "Appearance settings updated!";
            } catch(PDOException $e2) {
                $error = "Could not save appearance settings.";
            }
        }
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

    if($action === "update_attendance_policy") {
        $grace = max(0, (int)($_POST["grace_period_minutes"] ?? 10));
        $dupInput = $_POST["duplicate_scan_seconds"] ?? 3;
        $dup = max(1, (int)$dupInput);
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

        $st = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $st->execute([$user["id"]]);
        $row = $st->fetch();

        if(!$row || !password_verify($current, $row["password_hash"])) {
            $error = "Current password is incorrect.";
        } elseif(strlen($new) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user["id"]]);
            $success = "Password changed successfully!";
            log_activity("update", "user_password", $user["id"]);
        }
    }

    // Reload company data
    $cid = company_id() ?? 1;
    $st = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $st->execute([$cid]);
    $company = $st->fetch();
    if ($company) {
        $_SESSION["company"] = array_merge($_SESSION["company"] ?? [], $company);
    }
}

// Get company ID with fallback
$cid = company_id() ?? 1;

// Get departments and positions
$departments = [];
$positions = [];
$leaveTypes = [];
$empCount = 0;

try {
    $deptStmt = $pdo->prepare("SELECT * FROM departments WHERE company_id = ? ORDER BY name");
    $deptStmt->execute([$cid]); 
    $departments = $deptStmt->fetchAll();

    $posStmt = $pdo->prepare("SELECT * FROM positions WHERE company_id = ? ORDER BY name");
    $posStmt->execute([$cid]); 
    $positions = $posStmt->fetchAll();

    // Leave types
    $hasCidLt = $pdo->query("SHOW COLUMNS FROM leave_types LIKE 'company_id'")->fetch();
    if($hasCidLt) {
        $ltSt = $pdo->prepare("SELECT * FROM leave_types WHERE company_id=? ORDER BY name");
        $ltSt->execute([$cid]); 
        $leaveTypes = $ltSt->fetchAll();
    } else {
        $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
    }

    $empStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE company_id=? AND status='active'");
    $empStmt->execute([$cid]); 
    $empCount = $empStmt->fetch()["cnt"] ?? 0;
} catch (PDOException $e) {
    error_log("Settings data load error: " . $e->getMessage());
}

// Determine active tab from query string (for post-redirect-get pattern)
$activeTab = $_GET["tab"] ?? "company";
$allowed = ["company","org","leaves","schedule","appearance","security","attendance","email","notifications","payroll","api"];
if(!in_array($activeTab, $allowed)) $activeTab = "company";

// Attendance settings - create table if needed
try {
    $pdo->query("SELECT 1 FROM attendance_settings LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_settings (
        company_id INT PRIMARY KEY,
        grace_period_minutes INT DEFAULT 10,
        duplicate_scan_seconds INT DEFAULT 3,
        require_action_sequence TINYINT DEFAULT 1,
        gps_capture_enabled TINYINT DEFAULT 0,
        out_of_shift_grace_before_minutes INT DEFAULT 60,
        out_of_shift_grace_after_minutes INT DEFAULT 60
    )");
}

$attSet = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE company_id = ? LIMIT 1");
    $stmt->execute([company_id()]);
    $attSet = $stmt->fetch() ?: [];
} catch (PDOException $e) {}

$attSet = array_merge([
    "grace_period_minutes" => 10,
    "duplicate_scan_seconds" => 3,
    "require_action_sequence" => 1,
    "gps_capture_enabled" => 0,
    "out_of_shift_grace_before_minutes" => 60,
    "out_of_shift_grace_after_minutes" => 60
], $attSet ?: []);

$companyNavSettings = json_decode($company["nav_settings"] ?? "{}", true);
if(!is_array($companyNavSettings)) {
    $companyNavSettings = [];
}

$appearanceSettings = array_merge([
    "sidebar_color" => "default",
    "accent_theme" => "teal",
    "surface_style" => "glass",
    "sidebar_default_state" => "expanded"
], $companyNavSettings);

$currentLogo = $company["logo_url"] ?? "";

require_once __DIR__."/../../includes/header.php";
require_once __DIR__."/../../includes/nav.php";
?>

<div class="container-fluid pt-2 pb-4">
    <div class="settings-shell">
        <section class="settings-hero">
            <div class="settings-hero__copy">
                <span class="settings-hero__eyebrow"><i class="bi bi-sliders"></i> Admin Studio</span>
                <h1>Settings</h1>
                <p>Control your workspace identity, HR policies, security, and design system from one place. The design studio below now changes the live shell colors, surfaces, and sidebar behavior across the site.</p>
            </div>
            <div class="settings-stat">
                <span class="settings-stat__label">Active Staff</span>
                <strong class="settings-stat__value"><?= number_format((int)$empCount) ?></strong>
                <span class="settings-stat__meta">Employees currently active in this workspace.</span>
            </div>
            <div class="settings-stat">
                <span class="settings-stat__label">Structure</span>
                <strong class="settings-stat__value"><?= number_format(count($departments) + count($positions)) ?></strong>
                <span class="settings-stat__meta"><?= number_format(count($departments)) ?> departments and <?= number_format(count($positions)) ?> positions.</span>
            </div>
            <div class="settings-stat">
                <span class="settings-stat__label">Brand Mode</span>
                <strong class="settings-stat__value"><?= e(ucfirst($appearanceSettings["accent_theme"])) ?></strong>
                <span class="settings-stat__meta"><?= e(ucfirst($appearanceSettings["surface_style"])) ?> surface with <?= e($appearanceSettings["sidebar_default_state"] === "collapsed" ? "compact" : "expanded") ?> nav.</span>
            </div>
        </section>

    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
        <!-- Sidebar Nav Tabs -->
        <div class="col-lg-3">
            <div class="settings-sticky-nav">
                <div class="card border-0 shadow-sm settings-nav-card">
                    <div class="card-body">
                        <div class="settings-nav-intro">
                            <h2>Workspace controls</h2>
                            <p>Switch between company data, HR rules, design controls, and security without leaving the page.</p>
                        </div>
                        <nav class="settings-tabs nav flex-column" id="settingsTabs">
                            <button class="nav-link <?= $activeTab==='company'?'active':'' ?>" data-tab="company" onclick="switchTab('company')" type="button">
                                <i class="bi bi-building"></i> Company Info
                            </button>
                            <button class="nav-link <?= $activeTab==='org'?'active':'' ?>" data-tab="org" onclick="switchTab('org')" type="button">
                                <i class="bi bi-diagram-3"></i> Organization
                            </button>
                            <button class="nav-link <?= $activeTab==='attendance'?'active':'' ?>" data-tab="attendance" onclick="switchTab('attendance')" type="button">
                                <i class="bi bi-shield-check"></i> Attendance Policy
                            </button>
                            <button class="nav-link <?= $activeTab==='leaves'?'active':'' ?>" data-tab="leaves" onclick="switchTab('leaves')" type="button">
                                <i class="bi bi-calendar-week"></i> Leave Types
                            </button>
                            <button class="nav-link <?= $activeTab==='schedule'?'active':'' ?>" data-tab="schedule" onclick="switchTab('schedule')" type="button">
                                <i class="bi bi-clock-history"></i> Work Schedule
                            </button>
                            <button class="nav-link <?= $activeTab==='appearance'?'active':'' ?>" data-tab="appearance" onclick="switchTab('appearance')" type="button">
                                <i class="bi bi-palette"></i> Design Studio
                            </button>
<button class="nav-link <?= $activeTab==='security'?'active':'' ?>" data-tab="security" onclick="switchTab('security')" type="button">
                                <i class="bi bi-shield-lock"></i> Security
                            </button>
                            <button class="nav-link <?= $activeTab==='email'?'active':'' ?>" data-tab="email" onclick="switchTab('email')" type="button">
                                <i class="bi bi-envelope"></i> Email & SMS
                            </button>
                            <button class="nav-link <?= $activeTab==='notifications'?'active':'' ?>" data-tab="notifications" onclick="switchTab('notifications')" type="button">
                                <i class="bi bi-bell"></i> Notifications
                            </button>
                            <button class="nav-link <?= $activeTab==='payroll'?'active':'' ?>" data-tab="payroll" onclick="switchTab('payroll')" type="button">
                                <i class="bi bi-calculator"></i> Payroll
                            </button>
                            <button class="nav-link <?= $activeTab==='api'?'active':'' ?>" data-tab="api" onclick="switchTab('api')" type="button">
                                <i class="bi bi-key"></i> Integrations
                            </button>
                            <hr class="my-2">
                            <a class="nav-link" href="holidays.php">
                                <i class="bi bi-calendar-event"></i> Holidays
                            </a>
                        </nav>
                    </div>
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
                                    <input type="text" name="company_name" class="form-control" value="<?= e($company["name"] ?? "") ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="company_email" class="form-control" value="<?= e($company["email"] ?? "") ?>" required>
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

            <!-- ===== ATTENDANCE POLICY ===== -->
            <div id="tab-attendance" class="settings-section <?= $activeTab==='attendance'?'active':'' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <i class="bi bi-shield-check me-2"></i>Attendance Scan Policy
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?tab=attendance">
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
                                    <span><i class="bi bi-folder2 text-primary me-2"></i><?= e($d["name"] ?? "") ?></span>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info btn-edit-dept"
                                                data-id="<?= (int)$d['id'] ?>"
                                                data-name="<?= e($d['name']) ?>"
                                                title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete department?')">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_department">
                                            <input type="hidden" name="id" value="<?= (int)$d["id"] ?>">
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
                                    <span><i class="bi bi-briefcase text-success me-2"></i><?= e($p["name"] ?? "") ?></span>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info btn-edit-pos"
                                                data-id="<?= (int)$p['id'] ?>"
                                                data-name="<?= e($p['name']) ?>"
                                                title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete position?')">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_position">
                                            <input type="hidden" name="id" value="<?= (int)$p["id"] ?>">
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
                                <td><i class="bi bi-calendar2-check text-primary me-2"></i><?= e($lt["name"] ?? "") ?></td>
                                <td><span class="badge bg-info bg-opacity-75 text-dark"><?= (int)$lt["days_allowed"] ?> days</span></td>
                                <td>
                                    <?php if($lt["is_paid"]): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns text-center">
                                    <button class="btn btn-sm btn-outline-info btn-edit-lt"
                                            data-id="<?= (int)$lt['id'] ?>"
                                            data-name="<?= e($lt['name']) ?>"
                                            data-days="<?= (int)$lt['days_allowed'] ?>"
                                            data-paid="<?= (int)$lt['is_paid'] ?>"><i class="bi bi-pencil"></i></button>
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
                            <input type="hidden" name="company_name"    value="<?= e($company["name"] ?? "") ?>">
                            <input type="hidden" name="company_email"   value="<?= e($company["email"] ?? "") ?>">
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
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header"><i class="bi bi-stars me-2"></i>Display Mode</div>
                    <div class="card-body">
                        <div class="row g-4 align-items-start">
                            <div class="col-lg-7">
                                <div class="design-panel">
                                    <div class="design-panel__header">
                                        <h6>Your personal theme</h6>
                                        <p>This changes light or dark mode for your account only.</p>
                                    </div>
                                    <div class="theme-preview-grid">
                                        <button class="theme-preview-btn <?= ($user['theme']??'light')==='light'?'active':'' ?>" onclick="applyTheme('light')" id="btnLight" type="button">
                                            <div class="preview-card light-preview">
                                                <div class="preview-bar"></div>
                                                <div class="preview-content"></div>
                                            </div>
                                            <div class="fw-semibold"><i class="bi bi-sun me-1"></i>Light workspace</div>
                                        </button>
                                        <button class="theme-preview-btn <?= ($user['theme']??'light')==='dark'?'active':'' ?>" onclick="applyTheme('dark')" id="btnDark" type="button">
                                            <div class="preview-card dark-preview">
                                                <div class="preview-bar"></div>
                                                <div class="preview-content"></div>
                                            </div>
                                            <div class="fw-semibold"><i class="bi bi-moon me-1"></i>Dark workspace</div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="design-panel h-100">
                                    <div class="design-panel__header">
                                        <h6>Current workspace profile</h6>
                                        <p>These values help you keep the brand system consistent.</p>
                                    </div>
                                    <table class="table table-sm settings-mini-table mb-3">
                                        <tr><td class="text-muted">Active Theme</td><td><span id="currentTheme" class="badge bg-primary"><?= ucfirst($user['theme']??'light') ?></span></td></tr>
                                        <tr><td class="text-muted">Accent Theme</td><td><?= e($accentThemeOptions[$appearanceSettings["accent_theme"]]["label"] ?? ucfirst($appearanceSettings["accent_theme"])) ?></td></tr>
                                        <tr><td class="text-muted">Surface Style</td><td><?= e($surfaceStyleOptions[$appearanceSettings["surface_style"]]["label"] ?? ucfirst($appearanceSettings["surface_style"])) ?></td></tr>
                                        <tr><td class="text-muted">Default Nav</td><td><?= e($sidebarStateOptions[$appearanceSettings["sidebar_default_state"]]["label"] ?? ucfirst($appearanceSettings["sidebar_default_state"])) ?></td></tr>
                                    </table>
                                    <div class="settings-note">
                                        <strong>Note:</strong> the compact sidebar setting becomes the default first view, and each user can still expand or collapse it later from the top bar.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="design-studio-grid">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><i class="bi bi-palette2 me-2"></i>Branding &amp; Layout Controls</div>
                        <div class="card-body">
                            <form method="POST" action="?tab=appearance" enctype="multipart/form-data">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_appearance">

                                <div class="design-stack">
                                    <section class="design-panel">
                                        <div class="design-panel__header">
                                            <h6>Brand identity</h6>
                                            <p>Update the logo and keep the workspace recognizable across the sidebar and auth pages.</p>
                                        </div>
                                        <div class="logo-preview mb-3">
                                            <?php if($currentLogo): ?>
                                                <img src="<?= e($currentLogo) ?>" alt="<?= e($company["name"] ?? "Company logo") ?>">
                                            <?php else: ?>
                                                <span class="logo-preview__placeholder"><i class="bi bi-building"></i></span>
                                            <?php endif; ?>
                                            <div>
                                                <strong class="d-block"><?= e($company["name"] ?? "Your company") ?></strong>
                                                <span class="text-muted d-block small">Current logo shown in the sidebar and public auth screens.</span>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Upload Logo</label>
                                                <input type="file" name="logo_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/svg+xml">
                                                <small class="text-muted">Max 2MB. JPG, PNG, GIF, or SVG.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Logo URL</label>
                                                <input type="url" name="logo_url" class="form-control" value="<?= e($currentLogo) ?>" placeholder="https://example.com/logo.png">
                                                <small class="text-muted">Leave blank if you only want to upload a local file.</small>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="design-panel">
                                        <div class="design-panel__header">
                                            <h6>Accent palette</h6>
                                            <p>Pick the main visual direction for buttons, highlights, and shell details.</p>
                                        </div>
                                        <div class="design-option-grid">
                                            <?php foreach($accentThemeOptions as $key => $option): ?>
                                            <label class="design-option <?= $appearanceSettings["accent_theme"] === $key ? "is-active" : "" ?>">
                                                <input type="radio" name="accent_theme" value="<?= e($key) ?>" <?= $appearanceSettings["accent_theme"] === $key ? "checked" : "" ?>>
                                                <div class="design-option__swatches">
                                                    <?php foreach($option["colors"] as $color): ?>
                                                    <span class="design-option__swatch" style="background:<?= e($color) ?>"></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <span class="design-option__label"><?= e($option["label"]) ?></span>
                                                <span class="design-option__hint"><?= e($option["hint"]) ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <section class="design-panel">
                                        <div class="design-panel__header">
                                            <h6>Surface style</h6>
                                            <p>Choose how soft or crisp the cards, menus, and top bar should feel.</p>
                                        </div>
                                        <div class="design-option-grid">
                                            <?php foreach($surfaceStyleOptions as $key => $option): ?>
                                            <label class="design-option <?= $appearanceSettings["surface_style"] === $key ? "is-active" : "" ?>">
                                                <input type="radio" name="surface_style" value="<?= e($key) ?>" <?= $appearanceSettings["surface_style"] === $key ? "checked" : "" ?>>
                                                <div class="design-option__swatches">
                                                    <span class="design-option__swatch" style="background:<?= $key === 'glass' ? 'linear-gradient(135deg, rgba(255,255,255,0.86), rgba(15,118,110,0.18))' : ($key === 'solid' ? 'linear-gradient(135deg, #fff7ed, #fde68a)' : 'linear-gradient(135deg, #ffffff, #dbeafe)') ?>"></span>
                                                </div>
                                                <span class="design-option__label"><?= e($option["label"]) ?></span>
                                                <span class="design-option__hint"><?= e($option["hint"]) ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <section class="design-panel">
                                        <div class="design-panel__header">
                                            <h6>Navigation behavior</h6>
                                            <p>Choose the default sidebar size and the color of the navigation rail.</p>
                                        </div>
                                        <div class="design-option-grid mb-3">
                                            <?php foreach($sidebarStateOptions as $key => $option): ?>
                                            <label class="design-option <?= $appearanceSettings["sidebar_default_state"] === $key ? "is-active" : "" ?>">
                                                <input type="radio" name="sidebar_default_state" value="<?= e($key) ?>" <?= $appearanceSettings["sidebar_default_state"] === $key ? "checked" : "" ?>>
                                                <span class="design-option__label"><?= e($option["label"]) ?></span>
                                                <span class="design-option__hint"><?= e($option["hint"]) ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="design-option-grid">
                                            <?php foreach($sidebarColorOptions as $key => $option): ?>
                                            <label class="sidebar-color-option <?= $appearanceSettings["sidebar_color"] === $key ? "selected" : "" ?>">
                                                <input type="radio" name="sidebar_color" value="<?= e($key) ?>" <?= $appearanceSettings["sidebar_color"] === $key ? "checked" : "" ?> class="d-none">
                                                <div class="color-preview" style="background:linear-gradient(135deg, <?= e($option["colors"][0]) ?>, <?= e($option["colors"][1]) ?>);" title="<?= e($option["label"]) ?>"></div>
                                                <small><?= e($option["label"]) ?></small>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                </div>

                                <hr class="my-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Design Studio Changes</button>
                            </form>
                        </div>
                    </div>

                    <div class="design-stack">
                        <div class="design-preview">
                            <span class="design-preview__badge"><i class="bi bi-eye"></i> Live direction</span>
                            <div>
                                <h4><?= e($company["name"] ?? "Your workspace") ?></h4>
                                <p><?= e($accentThemeOptions[$appearanceSettings["accent_theme"]]["label"] ?? ucfirst($appearanceSettings["accent_theme"])) ?> accent, <?= e($surfaceStyleOptions[$appearanceSettings["surface_style"]]["label"] ?? ucfirst($appearanceSettings["surface_style"])) ?> surfaces, and a <?= e($appearanceSettings["sidebar_default_state"] === "collapsed" ? "compact" : "full") ?> sidebar by default.</p>
                            </div>
                            <div class="design-preview__frame">
                                <div class="design-preview__sidebar">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div class="design-preview__main">
                                    <div class="design-preview__hero"></div>
                                    <div class="design-preview__row">
                                        <div class="design-preview__card"></div>
                                        <div class="design-preview__card"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-kpi-list">
                                <div class="settings-kpi">
                                    <strong><?= number_format(count($leaveTypes)) ?></strong>
                                    <span>Leave types configured</span>
                                </div>
                                <div class="settings-kpi">
                                    <strong><?= number_format(count($departments)) ?></strong>
                                    <span>Departments in structure</span>
                                </div>
                                <div class="settings-kpi">
                                    <strong><?= number_format(count($positions)) ?></strong>
                                    <span>Position templates</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><i class="bi bi-magic me-2"></i>Design Notes</div>
                            <div class="card-body">
                                <div class="settings-note mb-3">
                                    <strong>Accent theme</strong> changes shared buttons, highlights, and the mood of the shell.
                                </div>
                                <div class="settings-note mb-3">
                                    <strong>Surface style</strong> makes cards feel softer or more structured without changing your data layout.
                                </div>
                                <div class="settings-note">
                                    <strong>Sidebar mode</strong> gives your team a compact option for smaller laptops while keeping the nav toggle available everywhere.
                                </div>
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

            <!-- ===== EMAIL SETTINGS ===== -->
            <div id="tab-email" class="settings-section <?= $activeTab==='email'?'active':'' ?>">
                <?php include 'email.php'; ?>
            </div>

            <!-- ===== NOTIFICATIONS ===== -->
            <div id="tab-notifications" class="settings-section <?= $activeTab==='notifications'?'active':'' ?>">
                <?php include 'notifications.php'; ?>
            </div>

            <!-- ===== PAYROLL ===== -->
            <div id="tab-payroll" class="settings-section <?= $activeTab==='payroll'?'active':'' ?>">
                <?php include 'payroll.php'; ?>
            </div>

            <!-- ===== API/INTEGRATIONS ===== -->
            <div id="tab-api" class="settings-section <?= $activeTab==='api'?'active':'' ?>">
                <?php include 'api.php'; ?>
            </div>

            </div><!-- /col-lg-9 -->
        </div><!-- /row -->
    </div><!-- /.settings-shell -->
</div><!-- /container-fluid -->

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
// Tab switching — uses data-tab attribute for reliable matching
function switchTab(name) {
    document.querySelectorAll('.settings-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#settingsTabs .nav-link').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name)?.classList.add('active');
    document.querySelectorAll('#settingsTabs .nav-link[data-tab]').forEach(el => {
        if (el.dataset.tab === name) el.classList.add('active');
    });
    history.replaceState(null, '', '?tab=' + name);
}

// Department edit — use data attributes (XSS-safe)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-edit-dept');
    if (!btn) return;
    document.getElementById('deptAction').value = 'edit_department';
    document.getElementById('deptId').value = btn.dataset.id;
    document.getElementById('deptName').value = btn.dataset.name;
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    new bootstrap.Modal(document.getElementById('addDeptModal')).show();
});
document.getElementById('addDeptModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('deptAction').value = 'add_department';
    document.getElementById('deptId').value = '';
    document.getElementById('deptName').value = '';
    document.getElementById('deptModalTitle').textContent = 'Add Department';
});

// Position edit — use data attributes (XSS-safe)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-edit-pos');
    if (!btn) return;
    document.getElementById('posAction').value = 'edit_position';
    document.getElementById('posId').value = btn.dataset.id;
    document.getElementById('posName').value = btn.dataset.name;
    document.getElementById('posModalTitle').textContent = 'Edit Position';
    new bootstrap.Modal(document.getElementById('addPosModal')).show();
});
document.getElementById('addPosModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('posAction').value = 'add_position';
    document.getElementById('posId').value = '';
    document.getElementById('posName').value = '';
    document.getElementById('posModalTitle').textContent = 'Add Position';
});

// Leave type edit — use data attributes (XSS-safe)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-edit-lt');
    if (!btn) return;
    document.getElementById('ltAction').value = 'edit_leave_type';
    document.getElementById('ltId').value = btn.dataset.id;
    document.getElementById('ltName').value = btn.dataset.name;
    document.getElementById('ltDays').value = btn.dataset.days;
    document.getElementById('ltPaid').checked = btn.dataset.paid === '1';
    document.getElementById('ltModalTitle').textContent = 'Edit Leave Type';
    new bootstrap.Modal(document.getElementById('addLtModal')).show();
});
document.getElementById('addLtModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('ltAction').value = 'add_leave_type';
    document.getElementById('ltId').value = '';
    document.getElementById('ltName').value = '';
    document.getElementById('ltDays').value = '5';
    document.getElementById('ltPaid').checked = true;
    document.getElementById('ltModalTitle').textContent = 'Add Leave Type';
});

function bindSelectableCards() {
    document.querySelectorAll('.design-option input, .sidebar-color-option input').forEach((input) => {
        input.addEventListener('change', function() {
            const card = this.closest('.design-option, .sidebar-color-option');
            if (!card) return;

            const isSidebarColor = card.classList.contains('sidebar-color-option');
            const activeClass = isSidebarColor ? 'selected' : 'is-active';
            const selector = isSidebarColor
                ? '.sidebar-color-option input[name="' + this.name + '"]'
                : '.design-option input[name="' + this.name + '"]';

            document.querySelectorAll(selector).forEach((other) => {
                other.closest('.design-option, .sidebar-color-option')?.classList.remove(activeClass);
            });

            card.classList.add(activeClass);
        });
    });
}

function applyTheme(theme) {
    fetch('<?= BASE_URL ?>/api/preferences.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({theme, csrf_token: '<?= csrf_token() ?>'})
    }).then((response) => {
        if (!response.ok) {
            throw new Error('Could not save theme.');
        }
        return response.json();
    }).then(() => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add('theme-' + theme);
        document.getElementById('currentTheme').textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
        document.querySelectorAll('.theme-preview-btn').forEach((button) => button.classList.remove('active'));
        document.getElementById(theme === 'light' ? 'btnLight' : 'btnDark').classList.add('active');
        if (typeof showToast === 'function') {
            showToast('Theme updated.', 'success');
        }
    }).catch(() => {
        if (typeof showToast === 'function') {
            showToast('Could not save the theme right now.', 'error');
        }
    });
}

const newPwd = document.getElementById('newPwd');
if (newPwd) {
    newPwd.addEventListener('input', function() {
        const v = this.value;
        const bar = document.getElementById('pwdStrengthBar');
        const lbl = document.getElementById('pwdStrengthLabel');
        let score = 0;

        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^a-zA-Z0-9]/.test(v)) score++;

        const colors = ['#ef4444', '#f59e0b', '#10b981', '#059669'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        bar.style.width = (score * 25) + '%';
        bar.style.background = colors[score - 1] || '#e2e8f0';
        lbl.textContent = score > 0 ? labels[score - 1] : '';
    });
}

bindSelectableCards();
</script>

<?php require_once __DIR__."/../../includes/footer.php"; ?>
