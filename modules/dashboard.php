<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/nav.php';

$user = $_SESSION['user'] ?? [];
$company = $_SESSION['company'] ?? [];
$features = json_decode($company['features'] ?? '{}', true);

// Check if company_id column exists (SaaS mode)
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$cid = $hasSaas ? (company_id() ?? 1) : null;

if($hasSaas && $cid) {
    $emp=$pdo->prepare("SELECT COUNT(*) c FROM employees WHERE company_id=? AND status='active'");
    $emp->execute([$cid]); $emp=$emp->fetch()['c'];
    $lv=$pdo->prepare("SELECT COUNT(*) c FROM leave_requests WHERE company_id=? AND status='pending'");
    $lv->execute([$cid]); $lv=$lv->fetch()['c'];
    $att=$pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE company_id=? AND date=CURDATE()");
    $att->execute([$cid]); $att=$att->fetch()['c'];
    $users=$pdo->prepare("SELECT COUNT(*) c FROM users WHERE company_id=? AND is_active=1");
    $users->execute([$cid]); $users=$users->fetch()['c'];
} else {
    $emp=$pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
    $lv=$pdo->query("SELECT COUNT(*) c FROM leave_requests WHERE status='pending'")->fetch()['c'];
    $att=$pdo->query("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE()")->fetch()['c'];
    $users=$pdo->query("SELECT COUNT(*) c FROM users WHERE is_active=1")->fetch()['c'];
}

if($hasSaas && $cid) {
    $st = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.employee_code FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.company_id = ? ORDER BY a.date DESC, a.time_in DESC LIMIT 5");
    $st->execute([$cid]);
    $recent = $st->fetchAll();
} else {
    $recent = $pdo->query("SELECT a.*, e.first_name, e.last_name, e.employee_code FROM attendance a JOIN employees e ON e.id = a.employee_id ORDER BY a.date DESC, a.time_in DESC LIMIT 5")->fetchAll();
}

$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$displayName = trim((string) ($user['name'] ?? ''));
$firstName = $displayName !== '' ? strtok($displayName, ' ') : 'team';
$workspaceName = $company['name'] ?? 'your workspace';
$attendanceCoverage = $emp > 0 ? (int) round(($att / max(1, $emp)) * 100) : 0;
$isLeadership = in_array($user['role'] ?? '', ['Admin', 'HR Officer', 'Manager'], true);
$leadershipFinalAction = ($features['payroll'] ?? true)
    ? [
        'href' => BASE_URL . '/modules/payroll/index.php',
        'icon' => 'wallet2',
        'title' => 'Run payroll',
        'subtitle' => 'Open salary structures and payslips',
    ]
    : (($features['reports'] ?? true)
        ? [
            'href' => BASE_URL . '/modules/reports/index.php',
            'icon' => 'bar-chart-line',
            'title' => 'View reports',
            'subtitle' => 'Track workforce trends and summaries',
        ]
        : [
            'href' => BASE_URL . '/modules/settings/index.php',
            'icon' => 'gear',
            'title' => 'Open settings',
            'subtitle' => 'Adjust company and system preferences',
        ]);

$quickActions = $isLeadership
    ? [
        [
            'href' => BASE_URL . '/modules/employees/add.php',
            'icon' => 'person-plus',
            'title' => 'Add employee',
            'subtitle' => 'Create a profile and onboard faster',
        ],
        [
            'href' => BASE_URL . '/modules/attendance/index.php',
            'icon' => 'qr-code-scan',
            'title' => 'Review attendance',
            'subtitle' => 'Monitor logs and today\'s activity',
        ],
        [
            'href' => BASE_URL . '/modules/leaves/index.php',
            'icon' => 'calendar-check',
            'title' => 'Approve leaves',
            'subtitle' => 'Handle pending requests in one place',
        ],
        $leadershipFinalAction,
    ]
    : [
        [
            'href' => BASE_URL . '/modules/profile/index.php',
            'icon' => 'person-circle',
            'title' => 'Update profile',
            'subtitle' => 'Keep your personal details current',
        ],
        [
            'href' => BASE_URL . '/modules/attendance/index.php',
            'icon' => 'clock-history',
            'title' => 'View attendance',
            'subtitle' => 'Check your latest time logs',
        ],
        [
            'href' => BASE_URL . '/modules/leaves/add.php',
            'icon' => 'calendar-plus',
            'title' => 'Request leave',
            'subtitle' => 'Submit time off in a few clicks',
        ],
        [
            'href' => ($features['payroll'] ?? true) ? BASE_URL . '/modules/payroll/my-summary.php' : BASE_URL . '/modules/leaves/calendar.php',
            'icon' => ($features['payroll'] ?? true) ? 'cash-coin' : 'calendar-event',
            'title' => ($features['payroll'] ?? true) ? 'Open payroll' : 'Leave calendar',
            'subtitle' => ($features['payroll'] ?? true) ? 'Track earnings and deductions' : 'See approved time off at a glance',
        ],
    ];
?>
<div class="page-header">
  <div>
    <span class="page-kicker">Operations center</span>
    <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
    <p class="page-subtitle">A live view of attendance, people activity, and day-to-day momentum for <?= e($workspaceName) ?>.</p>
  </div>
  <div class="page-header-meta">
    <span class="page-chip"><i class="bi bi-calendar3"></i><?= date('l, F j, Y') ?></span>
    <span class="page-chip page-chip--accent"><i class="bi bi-buildings"></i><?= e($workspaceName) ?></span>
  </div>
</div>

<section class="dashboard-hero">
  <div class="dashboard-hero__content">
    <span class="hero-eyebrow"><i class="bi bi-activity"></i>Live workspace</span>
    <h1><?= e($greeting) ?>, <?= e($firstName ?: 'team') ?></h1>
    <p>Keep workforce operations moving with one clear control center for people, attendance, leave approvals, and payroll readiness.</p>
    <div class="hero-actions">
      <?php foreach(array_slice($quickActions, 0, 3) as $action): ?>
      <a href="<?= e($action['href']) ?>" class="hero-action">
        <span class="hero-action__icon"><i class="bi bi-<?= e($action['icon']) ?>"></i></span>
        <span class="hero-action__copy">
          <strong><?= e($action['title']) ?></strong>
          <span><?= e($action['subtitle']) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="dashboard-hero__aside">
    <div class="hero-stat-grid">
      <div class="hero-stat">
        <span class="hero-stat__label">Attendance coverage</span>
        <strong class="hero-stat__value"><?= $attendanceCoverage ?>%</strong>
        <span class="hero-stat__meta"><?= number_format((int) $att) ?> of <?= number_format((int) $emp) ?> active employees logged attendance today.</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat__label">Pending leave queue</span>
        <strong class="hero-stat__value"><?= number_format((int) $lv) ?></strong>
        <span class="hero-stat__meta"><?= $lv > 0 ? 'Requests are waiting for review.' : 'No leave approvals are waiting right now.' ?></span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat__label">Live system users</span>
        <strong class="hero-stat__value"><?= number_format((int) $users) ?></strong>
        <span class="hero-stat__meta">Active accounts ready to use the platform across your organization.</span>
      </div>
    </div>
  </div>
</section>

<div class="row g-4 mb-4 dashboard-metrics">
  <div class="col-md-6 col-lg-3">
    <div class="stat-card primary">
      <h6><i class="bi bi-people me-2"></i>Active Employees</h6>
      <h2 data-counter="<?= (int) $emp ?>"><?= number_format((int) $emp) ?></h2>
      <i class="bi bi-people-fill icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card warning">
      <h6><i class="bi bi-hourglass-split me-2"></i>Pending Leaves</h6>
      <h2 data-counter="<?= (int) $lv ?>"><?= number_format((int) $lv) ?></h2>
      <i class="bi bi-calendar-x icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card success">
      <h6><i class="bi bi-clock me-2"></i>Today's Attendance</h6>
      <h2 data-counter="<?= (int) $att ?>"><?= number_format((int) $att) ?></h2>
      <i class="bi bi-clock-history icon"></i>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="stat-card info">
      <h6><i class="bi bi-person-badge me-2"></i>System Users</h6>
      <h2 data-counter="<?= (int) $users ?>"><?= number_format((int) $users) ?></h2>
      <i class="bi bi-person-gear icon"></i>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-lightning-charge-fill me-2"></i>Launchpad</span>
        <span class="table-count-badge">Fast actions</span>
      </div>
      <div class="card-body">
        <div class="quick-action-grid">
          <?php foreach($quickActions as $action): ?>
          <a href="<?= e($action['href']) ?>" class="quick-action-tile">
            <span class="quick-action-tile__icon"><i class="bi bi-<?= e($action['icon']) ?>"></i></span>
            <div>
              <strong><?= e($action['title']) ?></strong>
              <span class="d-block mt-2"><?= e($action['subtitle']) ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Recent Attendance</span>
        <span class="table-count-badge">Latest 5 logs</span>
      </div>
      <div class="card-body">
        <?php if($recent): ?>
        <div class="activity-list">
          <?php foreach($recent as $r): ?>
          <?php $employeeName = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')); ?>
          <div class="activity-list__item">
            <div class="activity-list__avatar">
              <?= e(strtoupper(substr($employeeName !== '' ? $employeeName : ($r['employee_code'] ?? '?'), 0, 1))) ?>
            </div>
            <div class="activity-list__content">
              <span class="activity-list__title"><?= e($employeeName) ?></span>
              <span class="activity-list__meta">
                <?= date('M j, Y', strtotime($r['date'])) ?>
                <?php if(!empty($r['time_in'])): ?>
                  · In <?= date('g:i A', strtotime($r['time_in'])) ?>
                <?php endif; ?>
                <?php if(!empty($r['employee_code'])): ?>
                  · <?= e($r['employee_code']) ?>
                <?php endif; ?>
              </span>
            </div>
            <?php if(!empty($r['time_out'])): ?>
              <span class="badge bg-success">Complete</span>
            <?php else: ?>
              <span class="badge bg-warning">Working</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>No recent attendance records yet.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
