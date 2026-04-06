<?php
require_once __DIR__ . '/config/db.php';

$isLocal = PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');

function hc_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = $table . '.' . $column;

    if (!array_key_exists($cacheKey, $cache)) {
        try {
            $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute([$column]);
            $cache[$cacheKey] = (bool) $st->fetch();
        } catch (Exception $e) {
            $cache[$cacheKey] = false;
        }
    }

    return $cache[$cacheKey];
}

function hc_count_rows(PDO $pdo, string $table, ?int $companyId = null): int
{
    if ($companyId !== null && hc_table_has_column($pdo, $table, 'company_id')) {
        $st = $pdo->prepare("SELECT COUNT(*) AS cnt FROM `$table` WHERE company_id = ?");
        $st->execute([$companyId]);
        return (int) ($st->fetch()['cnt'] ?? 0);
    }

    return (int) ($pdo->query("SELECT COUNT(*) AS cnt FROM `$table`")->fetch()['cnt'] ?? 0);
}

$checks = [
    'database' => false,
    'tables' => [],
    'admin' => false,
    'seed_data' => [],
    'errors' => []
];

// 1. Check database connection
try {
    $test = $pdo->query("SELECT 1");
    $checks['database'] = true;
} catch (Exception $e) {
    $checks['errors'][] = "Database connection failed: " . $e->getMessage();
}

// 2. Check all required tables
$requiredTables = [
    'companies', 'users', 'employees', 'departments', 'positions',
    'attendance', 'leave_types', 'leave_requests',
    'shifts', 'shift_assignments', 'holidays', 'break_records',
    'employee_leave_balance', 'salary_structures', 'salary_components',
    'employee_salary_components', 'payroll_records', 'payroll_items',
    'thirteenth_month_records', 'attendance_settings', 'attendance_exceptions',
    'notifications', 'activity_logs', 'user_preferences', 'announcements'
];

foreach ($requiredTables as $table) {
    try {
        $st = $pdo->prepare("SHOW TABLES LIKE ?");
        $st->execute([$table]);
        $result = $st;
        $exists = $result->rowCount() > 0;
        $checks['tables'][$table] = $exists;
        if (!$exists) {
            $checks['errors'][] = "Table missing: $table";
        }
    } catch (Exception $e) {
        $checks['tables'][$table] = false;
        $checks['errors'][] = "Error checking table $table: " . $e->getMessage();
    }
}

// 3. Check admin account
try {
    $admin = $pdo->query("SELECT * FROM users WHERE email = 'admin@hrms.local' AND role = 'Admin'")->fetch();
    $checks['admin'] = $admin ? true : false;
    if (!$admin) {
        $checks['errors'][] = "Admin account not found (admin@hrms.local)";
    }
} catch (Exception $e) {
    $checks['errors'][] = "Error checking admin: " . $e->getMessage();
}

// 4. Check seed data
try {
    $companyId = 1;
    $shifts = hc_count_rows($pdo, 'shifts', $companyId);
    $structures = hc_count_rows($pdo, 'salary_structures', $companyId);
    $components = hc_count_rows($pdo, 'salary_components', $companyId);
    $employees = hc_count_rows($pdo, 'employees', $companyId);
    $balances = hc_count_rows($pdo, 'employee_leave_balance', $companyId);

    $checks['seed_data'] = [
        'shifts' => $shifts,
        'salary_structures' => $structures,
        'salary_components' => $components,
        'employees' => $employees,
        'leave_balances' => $balances
    ];
} catch (Exception $e) {
    $checks['errors'][] = "Error checking seed data: " . $e->getMessage();
}

// Output results
$allTablesPresent = count($checks['tables']) > 0 && count(array_filter($checks['tables'])) === count($checks['tables']);
$hasErrors = !empty($checks['errors']);
$seedDataCount = array_sum($checks['seed_data']);
$seedDataStatusClass = $hasErrors ? 'warning' : 'success';
$overallGood = $checks['database'] && $allTablesPresent && $checks['admin'] && !$hasErrors;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS System Health Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 30px 0; }
        .container { max-width: 900px; }
        .health-card { border-left: 5px solid #dc3545; margin: 20px 0; }
        .health-card.success { border-left-color: #28a745; background: #f0f9f7; }
        .health-card.warning { border-left-color: #ffc107; background: #fff8f0; }
        .badge-ok { background: #28a745; }
        .badge-missing { background: #dc3545; }
        .progress { height: 25px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card filter-drop-shadow" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1)">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-heart-pulse"></i> HRMS System Health Check</h4>
            </div>
            <div class="card-body">

                <!-- Database Connection -->
                <div class="health-card <?= $checks['database'] ? 'success' : 'warning' ?>">
                    <h5>
                        <?= $checks['database'] ? '✓' : '✗' ?>
                        Database Connection
                    </h5>
                    <p class="mb-0">
                        <?= $checks['database']
                            ? '<span class="badge bg-success">Connected</span> hrms_db'
                            : '<span class="badge bg-danger">Failed</span> Cannot connect to database'
                        ?>
                    </p>
                </div>

                <!-- Database Tables -->
                <div class="health-card <?= $allTablesPresent ? 'success' : 'warning' ?>">
                    <h5>
                        <?= count(array_filter($checks['tables'])) === count($checks['tables']) ? '✓' : '⚠' ?>
                        Database Tables (<?= count(array_filter($checks['tables'])) ?>/<?= count($checks['tables']) ?>)
                    </h5>

                    <div class="progress mb-3" style="height: 20px">
                        <div class="progress-bar" role="progressbar"
                             style="width: <?= (count(array_filter($checks['tables'])) / max(1, count($checks['tables'])) * 100) ?>%">
                            <?= round(count(array_filter($checks['tables'])) / max(1, count($checks['tables'])) * 100) ?>%
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Table Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks['tables'] as $table => $exists): ?>
                                <tr>
                                    <td><code><?= $table ?></code></td>
                                    <td>
                                        <span class="badge <?= $exists ? 'badge-ok' : 'badge-missing' ?>">
                                            <?= $exists ? '✓ Exists' : '✗ Missing' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Admin Account -->
                <div class="health-card <?= $checks['admin'] ? 'success' : 'warning' ?>">
                    <h5>
                        <?= $checks['admin'] ? '✓' : '✗' ?>
                        Admin Account
                    </h5>
                    <p class="mb-0">
                        <?= $checks['admin']
                            ? '<span class="badge bg-success">Ready</span> admin@hrms.local'
                            : '<span class="badge bg-warning">Not Found</span> Cannot find admin account'
                        ?>
                    </p>
                    <small class="text-muted d-block mt-2">Password: admin123</small>
                </div>

                <!-- Seed Data -->
                <div class="health-card <?= $seedDataStatusClass ?>">
                    <h5>✓ Seeded Test Data</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Work Shifts</strong></td>
                                    <td><span class="badge bg-info"><?= $checks['seed_data']['shifts'] ?? 0 ?></span></td>
                                    <td><small class="text-muted">e.g., "Standard (8AM-5PM)"</small></td>
                                </tr>
                                <tr>
                                    <td><strong>Salary Structures</strong></td>
                                    <td><span class="badge bg-info"><?= $checks['seed_data']['salary_structures'] ?? 0 ?></span></td>
                                    <td><small class="text-muted">e.g., "Standard"</small></td>
                                </tr>
                                <tr>
                                    <td><strong>Salary Components</strong></td>
                                    <td><span class="badge bg-info"><?= $checks['seed_data']['salary_components'] ?? 0 ?></span></td>
                                    <td><small class="text-muted">Basic, SSS, PhilHealth, Pag-IBIG, Tax</small></td>
                                </tr>
                                <tr>
                                    <td><strong>Active Employees</strong></td>
                                    <td><span class="badge bg-info"><?= $checks['seed_data']['employees'] ?? 0 ?></span></td>
                                    <td><small class="text-muted">Ready for testing</small></td>
                                </tr>
                                <tr>
                                    <td><strong>Leave Balances</strong></td>
                                    <td><span class="badge bg-info"><?= $checks['seed_data']['leave_balances'] ?? 0 ?></span></td>
                                    <td><small class="text-muted">Pre-initialized for <?= date('Y') ?></small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Errors and Warnings -->
                <?php if (!empty($checks['errors'])): ?>
                <div class="alert alert-danger" role="alert">
                    <h5><i class="bi bi-exclamation-triangle"></i> Issues Found</h5>
                    <ul class="mb-0">
                        <?php foreach ($checks['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Overall Status -->
                <div class="alert alert-info mt-4">
                    <h5><i class="bi bi-info-circle"></i> System Status</h5>
                    <p class="mb-0">
                        <?php if ($overallGood): ?>
                            <strong class="text-success">✓ All Systems Ready</strong><br>
                            The system is properly configured and ready for testing.
                            <br><br>
                            <a href="/hrms/" class="btn btn-primary mt-2">
                                <i class="bi bi-box-arrow-right"></i> Go to HRMS Dashboard
                            </a>
                        <?php else: ?>
                            <strong class="text-warning">⚠ Issues Detected</strong><br>
                            Please fix the issues above before proceeding with testing.
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Testing Guide Link -->
                <div class="alert alert-primary mt-3">
                    <h5><i class="bi bi-clipboard-check"></i> Ready to Test?</h5>
                    <p class="mb-0">
                        Check out the comprehensive testing guide:
                        <br><br>
                        <a href="/hrms/TESTING_GUIDE.md" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-file-text"></i> View Testing Guide
                        </a>
                    </p>
                </div>

            </div>
        </div>

        <!-- Technical Details -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Technical Details</h6>
            </div>
            <div class="card-body small text-muted">
                <table class="table table-sm">
                    <tr>
                        <td style="width:150px"><strong>PHP Version</strong></td>
                        <td><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server</strong></td>
                        <td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database</strong></td>
                        <td>MySQL/PDO</td>
                    </tr>
                    <tr>
                        <td><strong>Session Status</strong></td>
                        <td><?= session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Time</strong></td>
                        <td><?= date('Y-m-d H:i:s') ?></td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
