<?php 
$u=$_SESSION['user']??null; 
$company=$_SESSION['company']??null;
$currentPage = basename($_SERVER['PHP_SELF']); 
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$features = json_decode($company['features'] ?? '{}', true);
$navSettings = json_decode($company['nav_settings'] ?? '{}', true);
$sidebarColor = $navSettings['sidebar_color'] ?? 'default';
$companyLogo = $company['logo_url'] ?? '';
$companyPlanLabel = !empty($company['plan']) ? ucfirst((string) $company['plan']) . ' workspace' : 'Live workspace';
?>
<?php if($u): ?>
<div class="mobile-header d-lg-none" id="mobileHeader">
    <button class="sidebar-toggle-btn" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
        <i class="bi bi-list fs-5"></i>
    </button>
    <span class="mobile-brand">
        <?php if($companyLogo): ?>
            <img src="<?= e($companyLogo) ?>" alt="<?= e($company['name'] ?? APP_NAME) ?>" style="height:26px;" class="me-2">
        <?php else: ?>
            <i class="bi bi-building-check me-2" style="color:#818cf8"></i>
        <?php endif; ?>
        <?= e($company['name'] ?? APP_NAME) ?>
    </span>
    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="sidebar-toggle-btn" onclick="toggleTheme()" title="Toggle theme" type="button">
            <i class="bi bi-<?= ($u['theme']??'light')==='dark'?'sun':'moon' ?>" data-theme-icon></i>
        </button>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<aside class="sidebar <?= $sidebarColor !== 'default' ? 'sidebar-'.$sidebarColor : '' ?>" id="sidebar">
    <div class="sidebar-header">
        <?php if($companyLogo): ?>
            <img src="<?= e($companyLogo) ?>" alt="Logo" style="height:34px;border-radius:8px;object-fit:contain;" class="sidebar-logo">
        <?php else: ?>
            <div class="sidebar-icon-wrap"><i class="bi bi-building-check"></i></div>
        <?php endif; ?>
        <div class="sidebar-brand-copy">
            <span class="sidebar-eyebrow">HR operations</span>
            <strong class="sidebar-brand-name"><?= e($company['name'] ?? 'HRMS') ?></strong>
            <small class="sidebar-brand-plan"><?= e($companyPlanLabel) ?></small>
        </div>
        <button class="sidebar-compact-toggle d-none d-lg-inline-flex" type="button" data-sidebar-toggle="desktop" title="Collapse sidebar" aria-label="Collapse sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">MAIN MENU</div>
        <a href="<?= BASE_URL ?>/modules/dashboard.php" class="nav-item <?= $currentPage==='dashboard.php'?'active':'' ?>">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/employees/index.php" class="nav-item <?= $currentDir==='employees'?'active':'' ?>">
            <i class="bi bi-people-fill"></i><span>Employees</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="nav-item <?= $currentDir==='attendance'?'active':'' ?>">
            <i class="bi bi-qr-code-scan"></i><span>Attendance</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/leaves/index.php" class="nav-item <?= $currentDir==='leaves'?'active':'' ?>">
            <i class="bi bi-calendar-event-fill"></i><span>Leave Requests</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/claims/index.php" class="nav-item <?= $currentDir==='claims'?'active':'' ?>">
            <i class="bi bi-receipt"></i><span>Expense Claims</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/profile/index.php" class="nav-item <?= $currentDir==='profile'?'active':'' ?>">
            <i class="bi bi-person-circle"></i><span>My Profile</span>
        </a>
        <?php if(($features['payroll'] ?? true) && !in_array($u['role'], ['Admin','HR Officer'])): ?>
        <a href="<?= BASE_URL ?>/modules/payroll/my-summary.php" class="nav-item <?= $currentPage==='my-summary.php'?'active':'' ?>">
            <i class="bi bi-cash-coin"></i><span>My Payroll</span>
        </a>
        <?php endif; ?>
        <?php if(in_array($u['role'], ['Admin', 'HR Officer'])): ?>
        <div class="nav-section-label">MANAGEMENT</div>
        <?php if($u['role'] === 'Admin'): ?>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="nav-item <?= $currentDir==='users'?'active':'' ?>">
            <i class="bi bi-person-gear"></i><span>User Accounts</span>
        </a>
        <?php endif; ?>
        <?php if($features['payroll'] ?? true): ?>
        <a href="<?= BASE_URL ?>/modules/payroll/index.php" class="nav-item <?= $currentDir==='payroll'?'active':'' ?>">
            <i class="bi bi-wallet2"></i><span>Payroll</span>
        </a>
        <?php endif; ?>
        <?php if($features['reports'] ?? true): ?>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-item <?= $currentDir==='reports'?'active':'' ?>">
            <i class="bi bi-bar-chart-fill"></i><span>Reports</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/analytics/index.php" class="nav-item <?= $currentDir==='analytics'?'active':'' ?>">
            <i class="bi bi-graph-up"></i><span>HR Analytics</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/compensation/index.php" class="nav-item <?= $currentDir==='compensation'?'active':'' ?>">
            <i class="bi bi-currency-dollar"></i><span>Compensation</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/audit/logs.php" class="nav-item <?= $currentDir==='audit'?'active':'' ?>">
            <i class="bi bi-journal-text"></i><span>Audit Logs</span>
        </a>
        <div class="nav-section-label">SYSTEM</div>
        <a href="<?= BASE_URL ?>/modules/settings/index.php" class="nav-item <?= $currentDir==='settings'?'active':'' ?>">
            <i class="bi bi-gear-fill"></i><span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($u['name'] ?: $u['email'], 0, 1)) ?></div>
            <div class="details">
                <div class="name"><?= e($u['name'] ?: $u['email']) ?></div>
                <div class="role-badge"><?= e($u['role']) ?></div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme" type="button">
                <i class="bi bi-<?= ($u['theme']??'light')==='dark'?'sun':'moon' ?>" data-theme-icon></i>
            </button>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="icon-btn logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>
<main class="main-content" id="mainContent">
    <div class="top-bar d-none d-lg-flex">
        <div class="top-bar-meta">
            <div class="top-bar-title-group">
                <span class="top-bar-kicker">Workspace</span>
                <span class="top-bar-heading"><?= e($company['name'] ?? APP_NAME) ?></span>
            </div>
            <span class="top-bar-date"><i class="bi bi-calendar3 me-2"></i><?= date('D, M j, Y') ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="workspace-status"><i class="bi bi-activity"></i>Live</span>
            <button class="topbar-icon-btn d-none d-lg-inline-flex" type="button" data-sidebar-toggle="desktop" title="Toggle compact sidebar" aria-label="Toggle compact sidebar">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
            <button class="topbar-icon-btn" onclick="toggleTheme()" title="Toggle theme" type="button">
                <i class="bi bi-<?= ($u['theme']??'light')==='dark'?'sun':'moon' ?>" data-theme-icon></i>
            </button>
        </div>
    </div>
<?php endif; ?>
