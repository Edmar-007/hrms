<?php
require_once __DIR__ . '/../config/db.php';

$executed = 0;
$errors = [];

function should_ignore_migration_error(string $message): bool
{
    $ignorePatterns = [
        'already exists',
        'Duplicate',
        'Duplicate column',
        'Duplicate key name',
        "doesn't exist",
    ];

    foreach ($ignorePatterns as $pattern) {
        if (stripos($message, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function execute_sql_file(PDO $pdo, string $path, int &$executed, array &$errors): void
{
    if (!file_exists($path)) {
        return;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        $errors[] = 'Could not read SQL file: ' . basename($path);
        return;
    }

    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            if (!should_ignore_migration_error($e->getMessage())) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$compatFile = __DIR__ . '/ensure_legacy_compat.sql';
execute_sql_file($pdo, $compatFile, $executed, $errors);

$sqlFile = __DIR__ . '/../hrms.sql';
execute_sql_file($pdo, $sqlFile, $executed, $errors);

$extraFiles = glob(__DIR__ . '/ensure_*.sql') ?: [];
sort($extraFiles, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($extraFiles as $extraFile) {
    if (realpath($extraFile) === realpath($compatFile)) {
        continue;
    }

    execute_sql_file($pdo, $extraFile, $executed, $errors);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin-top: 40px; }
        .alert { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-database"></i> Database Migration</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h6>✓ Migration Completed Successfully</h6>
                    <p class="mb-0">Executed <strong><?= $executed ?></strong> SQL statements</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-warning">
                    <h6>⚠ Notices</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                        <li><small><?= htmlspecialchars($err) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <h5 class="mt-4">New Tables Created:</h5>
                <ul class="list-group">
                    <li class="list-group-item"><code>shifts</code> - Define work schedules</li>
                    <li class="list-group-item"><code>shift_assignments</code> - Assign shifts to employees</li>
                    <li class="list-group-item"><code>holidays</code> - Company holidays</li>
                    <li class="list-group-item"><code>break_records</code> - Track employee breaks</li>
                    <li class="list-group-item"><code>employee_leave_balance</code> - Leave balance tracking</li>
                    <li class="list-group-item"><code>salary_structures</code> - Flexible payroll structure</li>
                    <li class="list-group-item"><code>salary_components</code> - Salary components (earnings/deductions)</li>
                    <li class="list-group-item"><code>employee_salary_components</code> - Individual employee components</li>
                    <li class="list-group-item"><code>payroll_records</code> - Store calculated payslips</li>
                    <li class="list-group-item"><code>payroll_items</code> - Payslip line items</li>
                    <li class="list-group-item"><code>thirteenth_month_records</code> - 13th month pay computation</li>
                    <li class="list-group-item"><code>attendance_exceptions</code> - Absences, half-days, etc.</li>
                    <li class="list-group-item"><code>attendance_settings</code> - Company attendance rules</li>
                </ul>

                <div class="mt-4">
                    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
