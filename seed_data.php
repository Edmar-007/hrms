<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$cid = 1;

try {
    // 1. Create a dummy company if not exists
    $pdo->prepare("INSERT IGNORE INTO companies (id, name, slug) VALUES (?, 'Demo Corp', 'demo-corp')")
        ->execute([$cid]);

    // 2. Create departments
    $depts = ['Engineering', 'Marketing', 'Human Resources', 'Sales'];
    foreach ($depts as $dept) {
        $pdo->prepare("INSERT IGNORE INTO departments (name, company_id) VALUES (?, ?)")
            ->execute([$dept, $cid]);
    }

    // 3. Create positions
    $roles = ['Software Engineer', 'Marketing Specialist', 'HR Manager', 'Sales Representative'];
    foreach ($roles as $role) {
        $pdo->prepare("INSERT IGNORE INTO positions (name, company_id) VALUES (?, ?)")
            ->execute([$role, $cid]);
    }

    // 4. Create sample employees
    $employees = [
        ['Alice', 'Johnson', 'alice@example.com', 'Engineering', 'Software Engineer'],
        ['Bob', 'Smith', 'bob@example.com', 'Marketing', 'Marketing Specialist'],
        ['Charlie', 'Davis', 'charlie@example.com', 'Human Resources', 'HR Manager'],
        ['Diana', 'Evans', 'diana@example.com', 'Sales', 'Sales Representative'],
    ];

    foreach ($employees as $emp) {
        $deptId = $pdo->query("SELECT id FROM departments WHERE name = '{$emp[3]}' LIMIT 1")->fetchColumn();
        $posId = $pdo->query("SELECT id FROM positions WHERE name = '{$emp[4]}' LIMIT 1")->fetchColumn();
        
        $st = $pdo->prepare("INSERT IGNORE INTO employees (company_id, first_name, last_name, email, department_id, position_id, status, employee_code, hire_date) 
                             VALUES (?, ?, ?, ?, ?, ?, 'active', ?, CURDATE())");
        $st->execute([$cid, $emp[0], $emp[1], $emp[2], $deptId, $posId, 'EMP' . rand(1000, 9999)]);
    }

    // 5. Create attendance for today
    $empIds = $pdo->query("SELECT id FROM employees WHERE company_id = $cid")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($empIds as $id) {
        $st = $pdo->prepare("INSERT IGNORE INTO attendance (company_id, employee_id, date, time_in, time_out, status) 
                             VALUES (?, ?, CURDATE(), '09:00:00', '18:00:00', 'present')");
        $st->execute([$cid, $id]);
    }

    echo "Seed data inserted successfully!";
} catch (Exception $e) {
    echo "Seed error: " . $e->getMessage();
}
