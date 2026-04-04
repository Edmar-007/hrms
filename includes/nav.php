<?php 
$u=$_SESSION['user']??null; 
$company=$_SESSION['company']??null;
$currentPage = basename($_SERVER['PHP_SELF']); 
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$features = json_decode($company['features'] ?? '{}', true);
?>
<?php if($u): ?>
<!-- Mobile Header -->
<div class="mobile-header d-lg-none">
    <button class="btn btn-link text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="brand"><?= e($company['name'] ?? APP_NAME) ?></span>
    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-link text-white p-0" onclick="toggleTheme()">
            <i class="bi bi-<?= ($u['theme']??'light')==='dark'?'sun':'moon' ?>"></i>
        </button>
    </div>
</div>

<!-- Sidebar -->
<aside class="sidebar d-lg-block offcanvas-lg offcanvas-start" id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-building-check"></i>
        <span><?= e($company['name'] ?? 'HRMS') ?></span>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">MAIN MENU</div>
        
        <a href="<?= BASE_URL ?>/modules/dashboard.php" class="nav-item <?= $currentPage==='dashboard.php'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?= BASE_URL ?>/modules/employees/index.php" class="nav-item <?= $currentDir==='employees'?'active':'' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Employees</span>
        </a>
        
        <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="nav-item <?= $currentDir==='attendance'?'active':'' ?>">
            <i class="bi bi-qr-code-scan"></i>
            <span>Attendance</span>
        </a>
        
        <a href="<?= BASE_URL ?>/modules/leaves/index.php" class="nav-item <?= $currentDir==='leaves'?'active':'' ?>">
            <i class="bi bi-calendar-event"></i>
            <span>Leave Requests</span>
        </a>
        
        <?php if(in_array($u['role'], ['Admin', 'HR Officer'])): ?>
        <div class="nav-section">MANAGEMENT</div>
        
        <?php if($features['payroll'] ?? true): ?>
        <a href="<?= BASE_URL ?>/modules/payroll/index.php" class="nav-item <?= $currentDir==='payroll'?'active':'' ?>">
            <i class="bi bi-wallet2"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>
        
        <?php if($features['reports'] ?? true): ?>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-item <?= $currentDir==='reports'?'active':'' ?>">
            <i class="bi bi-bar-chart-line-fill"></i>
            <span>Reports</span>
        </a>
        <?php endif; ?>
        
        <div class="nav-section">SETTINGS</div>
        
        <a href="<?= BASE_URL ?>/modules/settings/index.php" class="nav-item <?= $currentDir==='settings'?'active':'' ?>">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($u['name'] ?: $u['email'], 0, 1)) ?></div>
            <div class="details">
                <div class="name"><?= e($u['name'] ?: $u['email']) ?></div>
                <div class="role"><?= e($u['role']) ?></div>
            </div>
        </div>
        <div class="d-flex">
            <button class="icon-btn me-1" onclick="toggleTheme()" title="Toggle theme">
                <i class="bi bi-<?= ($u['theme']??'light')==='dark'?'sun':'moon' ?>"></i>
            </button>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content Wrapper -->
<main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar d-none d-lg-flex">
        <div class="d-flex align-items-center">
            <span class="text-muted me-3"><i class="bi bi-calendar3 me-2"></i><?= date('l, F j, Y') ?></span>
            <?php if($company): ?>
            <span class="badge bg-<?= $company['plan']==='free'?'secondary':($company['plan']==='enterprise'?'warning':'primary') ?>">
                <i class="bi bi-star-fill me-1"></i><?= ucfirst($company['plan']) ?> Plan
            </span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-link text-muted p-0 position-relative" data-bs-toggle="dropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="notification-badge" id="notif-count"></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <a href="#" class="small text-primary" onclick="markAllRead()">Mark all read</a>
                    </div>
                    <div id="notification-list">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                            No notifications
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
