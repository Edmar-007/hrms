<?php
require_once __DIR__ . '/config/db.php';
try {
    echo "Starting seed...\n";
    
    // Check tables
    $st = $pdo->query('SHOW TABLES');
    $tables = $st->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";

    if (!in_array('companies', $tables)) {
        die("Error: companies table missing\n");
    }

    // 1. Company
    $pdo->exec("INSERT IGNORE INTO companies (id, name, slug, email) VALUES (1, 'Demo Corp', 'demo-corp', 'admin@demo.com')");
    echo "Company 1 check/insert done.\n";

    // 2. Department
    $pdo->exec("INSERT IGNORE INTO departments (id, company_id, name) VALUES (1, 1, 'Engineering')");
    echo "Department 1 check/insert done.\n";

    // 3. Position
    $pdo->exec("INSERT IGNORE INTO positions (id, company_id, name) VALUES (1, 1, 'Software Developer')");
    echo "Position 1 check/insert done.\n";

    // 4. Employee
    $pdo->exec("INSERT IGNORE INTO employees (id, company_id, first_name, last_name, email, department_id, position_id, employee_code, status) VALUES (1, 1, 'Alice', 'Doe', 'alice@demo.com', 1, 1, 'EMP001', 'active')");
    echo "Employee 1 check/insert done.\n";

    // 5. Attendance
    $today = date('Y-m-d');
    $pdo->exec("INSERT IGNORE INTO attendance (company_id, employee_id, date, time_in, time_out) VALUES (1, 1, '$today', '09:00:00', '17:00:00')");
    echo "Attendance for today check/insert done.\n";

    // 6. User (for login if needed)
    // admin@demo.com / password123
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (id, company_id, employee_id, email, password_hash, role, is_active) VALUES (1, 1, 1, 'admin@demo.com', '$hash', 'Admin', 1)");
    echo "User 1 check/insert done.\n";

    echo "Seed completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
