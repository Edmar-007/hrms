<?php
require_once __DIR__.'/../config/db.php';

echo "<h2>HRMS Database Seeder</h2><pre>";

// Check schema features
$hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
$hasHireDate = $pdo->query("SHOW COLUMNS FROM employees LIKE 'hire_date'")->fetch();
$companyId = 1;

// Create company if SaaS mode and not exists
if($hasSaas) {
    $check = $pdo->query("SELECT id FROM companies LIMIT 1")->fetch();
    if(!$check) {
        $pdo->exec("INSERT INTO companies (id, name, slug, email) VALUES (1, 'Demo Company', 'demo', 'admin@hrms.local')");
        echo "✓ Created company\n";
    }
}

// Departments
$departments = ['Operations', 'Finance', 'Human Resources', 'IT Department', 'Marketing', 'Sales', 'Customer Service', 'Administration'];
foreach($departments as $d) {
    try {
        if($hasSaas) {
            $pdo->prepare("INSERT IGNORE INTO departments (company_id, name) VALUES (?, ?)")->execute([$companyId, $d]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)")->execute([$d]);
        }
    } catch(Exception $e) {}
}
echo "✓ Added " . count($departments) . " departments\n";

// Positions
$positions = ['Manager', 'Senior Developer', 'Junior Developer', 'Loan Officer', 'Accountant', 'HR Specialist', 'Sales Representative', 'Marketing Specialist', 'Customer Support', 'Team Lead', 'Intern', 'Analyst'];
foreach($positions as $p) {
    try {
        if($hasSaas) {
            $pdo->prepare("INSERT IGNORE INTO positions (company_id, name) VALUES (?, ?)")->execute([$companyId, $p]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO positions (name) VALUES (?)")->execute([$p]);
        }
    } catch(Exception $e) {}
}
echo "✓ Added " . count($positions) . " positions\n";

// Leave Types
$leaveTypes = [
    ['Vacation Leave', 15, 1],
    ['Sick Leave', 15, 1],
    ['Maternity Leave', 60, 1],
    ['Paternity Leave', 7, 1],
    ['Emergency Leave', 5, 1],
    ['Birthday Leave', 1, 1]
];
foreach($leaveTypes as $lt) {
    try {
        if($hasSaas) {
            $pdo->prepare("INSERT IGNORE INTO leave_types (company_id, name, days_allowed, is_paid) VALUES (?, ?, ?, ?)")->execute([$companyId, $lt[0], $lt[1], $lt[2]]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO leave_types (name, days_allowed, is_paid) VALUES (?, ?, ?)")->execute([$lt[0], $lt[1], $lt[2]]);
        }
    } catch(Exception $e) {}
}
echo "✓ Added " . count($leaveTypes) . " leave types\n";

// Get department and position IDs
$deptIds = $pdo->query("SELECT id FROM departments")->fetchAll(PDO::FETCH_COLUMN);
$posIds = $pdo->query("SELECT id FROM positions")->fetchAll(PDO::FETCH_COLUMN);

// Filipino names for employees
$firstNames = ['Maria', 'Juan', 'Ana', 'Pedro', 'Sofia', 'Carlos', 'Rosa', 'Miguel', 'Elena', 'Jose', 'Carmen', 'Antonio', 'Lucia', 'Roberto', 'Isabella', 'Francisco', 'Teresa', 'Manuel', 'Patricia', 'Ricardo', 'Angela', 'Gabriel', 'Beatriz', 'Daniel', 'Victoria', 'Rafael', 'Cristina', 'Eduardo', 'Mariana', 'Fernando'];
$lastNames = ['Santos', 'Dela Cruz', 'Garcia', 'Reyes', 'Martinez', 'Lopez', 'Fernandez', 'Torres', 'Ramos', 'Villanueva', 'Cruz', 'Mendoza', 'Bautista', 'Aquino', 'Gonzales', 'Pascual', 'Rivera', 'Castro', 'Diaz', 'Flores', 'Navarro', 'Morales', 'Ramirez', 'Herrera', 'Aguilar', 'Romero', 'Salazar', 'Ortega', 'Vargas', 'Jimenez'];

$employeeCount = 0;
$startId = 2; // Start from EMP-0002

// Clear existing data (be careful!)
try { $pdo->exec("DELETE FROM attendance WHERE employee_id > 1"); } catch(Exception $e) {}
try { $pdo->exec("DELETE FROM leave_requests WHERE employee_id > 1"); } catch(Exception $e) {}
try { $pdo->exec("DELETE FROM employees WHERE id > 1"); } catch(Exception $e) {}

for($i = 0; $i < 30; $i++) {
    $code = 'EMP-' . str_pad($startId + $i, 4, '0', STR_PAD_LEFT);
    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];
    $email = strtolower(preg_replace('/\s+/', '', $first) . '.' . preg_replace('/\s+/', '', $last) . ($i+1) . '@demo.com');
    $phone = '09' . rand(10, 99) . rand(1000000, 9999999);
    $deptId = !empty($deptIds) ? $deptIds[array_rand($deptIds)] : 1;
    $posId = !empty($posIds) ? $posIds[array_rand($posIds)] : 1;
    $salary = rand(25, 80) * 1000;
    
    try {
        if($hasSaas && $hasHireDate) {
            $hireDate = date('Y-m-d', strtotime('-' . rand(30, 1000) . ' days'));
            $st = $pdo->prepare("INSERT INTO employees (company_id, employee_code, first_name, last_name, email, phone, department_id, position_id, hire_date, basic_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->execute([$companyId, $code, $first, $last, $email, $phone, $deptId, $posId, $hireDate, $salary]);
        } elseif($hasSaas) {
            $st = $pdo->prepare("INSERT INTO employees (company_id, employee_code, first_name, last_name, email, phone, department_id, position_id, basic_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->execute([$companyId, $code, $first, $last, $email, $phone, $deptId, $posId, $salary]);
        } elseif($hasHireDate) {
            $hireDate = date('Y-m-d', strtotime('-' . rand(30, 1000) . ' days'));
            $st = $pdo->prepare("INSERT INTO employees (employee_code, first_name, last_name, email, phone, department_id, position_id, hire_date, basic_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->execute([$code, $first, $last, $email, $phone, $deptId, $posId, $hireDate, $salary]);
        } else {
            $st = $pdo->prepare("INSERT INTO employees (employee_code, first_name, last_name, email, phone, department_id, position_id, basic_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->execute([$code, $first, $last, $email, $phone, $deptId, $posId, $salary]);
        }
        $employeeCount++;
    } catch(Exception $e) {
        echo "Employee error: " . $e->getMessage() . "\n";
    }
}
echo "✓ Added $employeeCount employees\n";

// Get employee IDs
$empIds = $pdo->query("SELECT id FROM employees WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
$leaveTypeIds = $pdo->query("SELECT id FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);

if(empty($empIds)) {
    echo "⚠ No employees found, skipping attendance and leaves\n";
} else {
    // Attendance records for last 30 days
    $attendanceCount = 0;
    for($day = 0; $day < 30; $day++) {
        $date = date('Y-m-d', strtotime("-$day days"));
        $dayOfWeek = date('N', strtotime($date));
        
        // Skip weekends
        if($dayOfWeek >= 6) continue;
        
        // Random employees present each day (70-90%)
        $presentCount = max(1, rand((int)(count($empIds) * 0.7), count($empIds)));
        shuffle($empIds);
        $presentEmps = array_slice($empIds, 0, $presentCount);
        
        foreach($presentEmps as $empId) {
            $timeInHour = rand(7, 9);
            $timeInMin = rand(0, 59);
            $timeIn = sprintf('%02d:%02d:00', $timeInHour, $timeInMin);
            
            // 80% chance of time out
            $timeOut = null;
            if(rand(1, 100) <= 80 || $day > 0) {
                $timeOutHour = rand(17, 19);
                $timeOutMin = rand(0, 59);
                $timeOut = sprintf('%02d:%02d:00', $timeOutHour, $timeOutMin);
            }
            
            try {
                if($hasSaas) {
                    $st = $pdo->prepare("INSERT IGNORE INTO attendance (company_id, employee_id, date, time_in, time_out) VALUES (?, ?, ?, ?, ?)");
                    $st->execute([$companyId, $empId, $date, $timeIn, $timeOut]);
                } else {
                    $st = $pdo->prepare("INSERT IGNORE INTO attendance (employee_id, date, time_in, time_out) VALUES (?, ?, ?, ?)");
                    $st->execute([$empId, $date, $timeIn, $timeOut]);
                }
                $attendanceCount++;
            } catch(Exception $e) {}
        }
    }
    echo "✓ Added $attendanceCount attendance records\n";

    // Leave requests
    $leaveCount = 0;
    if(!empty($leaveTypeIds)) {
        $statuses = ['pending', 'approved', 'approved', 'approved', 'rejected'];
        $reasons = ['Family vacation', 'Medical appointment', 'Personal matters', 'Wedding attendance', 'Family emergency', 'Rest and recuperation', 'Travel abroad', 'Home renovation', 'School event', 'Religious observance'];

        for($i = 0; $i < 25; $i++) {
            $empId = $empIds[array_rand($empIds)];
            $leaveTypeId = $leaveTypeIds[array_rand($leaveTypeIds)];
            $status = $statuses[array_rand($statuses)];
            $reason = $reasons[array_rand($reasons)];
            
            $startOffset = rand(-60, 30);
            $startDate = date('Y-m-d', strtotime("$startOffset days"));
            $duration = rand(1, 5);
            $endDate = date('Y-m-d', strtotime("$startOffset days + $duration days"));
            
            try {
                if($hasSaas) {
                    $st = $pdo->prepare("INSERT INTO leave_requests (company_id, employee_id, leave_type_id, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $st->execute([$companyId, $empId, $leaveTypeId, $startDate, $endDate, $reason, $status]);
                } else {
                    $st = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $st->execute([$empId, $leaveTypeId, $startDate, $endDate, $reason, $status]);
                }
                $leaveCount++;
            } catch(Exception $e) {}
        }
    }
    echo "✓ Added $leaveCount leave requests\n";
}

// Create admin user if not exists
$adminCheck = $pdo->query("SELECT id FROM users WHERE email='admin@hrms.local'")->fetch();
if(!$adminCheck) {
    $adminEmp = $pdo->query("SELECT id FROM employees WHERE employee_code='EMP-0001'")->fetch();
    if(!$adminEmp) {
        if($hasSaas) {
            $pdo->exec("INSERT INTO employees (company_id, employee_code, first_name, last_name, email, department_id, position_id, basic_salary, status) VALUES ($companyId, 'EMP-0001', 'System', 'Admin', 'admin@hrms.local', 1, 1, 50000, 'active')");
        } else {
            $pdo->exec("INSERT INTO employees (employee_code, first_name, last_name, email, department_id, position_id, basic_salary, status) VALUES ('EMP-0001', 'System', 'Admin', 'admin@hrms.local', 1, 1, 50000, 'active')");
        }
        $adminEmp = $pdo->query("SELECT id FROM employees WHERE employee_code='EMP-0001'")->fetch();
    }
    
    $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    if($hasSaas) {
        $pdo->prepare("INSERT INTO users (company_id, employee_id, email, password_hash, role, is_active) VALUES (?, ?, 'admin@hrms.local', ?, 'Admin', 1)")->execute([$companyId, $adminEmp['id'], $hash]);
    } else {
        $pdo->prepare("INSERT INTO users (employee_id, email, password_hash, role, is_active) VALUES (?, 'admin@hrms.local', ?, 'Admin', 1)")->execute([$adminEmp['id'], $hash]);
    }
    echo "✓ Created admin user (admin@hrms.local / admin123)\n";
} else {
    echo "• Admin user already exists\n";
}

// Create additional user accounts
$userEmps = $pdo->query("SELECT id, email FROM employees WHERE id > 1 LIMIT 5")->fetchAll();
$roles = ['HR Officer', 'Manager', 'Employee', 'Employee', 'Employee'];
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$userCount = 0;
foreach($userEmps as $idx => $emp) {
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$emp['email']]);
    if(!$check->fetch()) {
        try {
            if($hasSaas) {
                $pdo->prepare("INSERT INTO users (company_id, employee_id, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)")->execute([$companyId, $emp['id'], $emp['email'], $hash, $roles[$idx] ?? 'Employee']);
            } else {
                $pdo->prepare("INSERT INTO users (employee_id, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)")->execute([$emp['id'], $emp['email'], $hash, $roles[$idx] ?? 'Employee']);
            }
            $userCount++;
        } catch(Exception $e) {}
    }
}
echo "✓ Created $userCount additional user accounts\n";

// SaaS-specific data
if($hasSaas) {
    try {
        $pdo->exec("DELETE FROM notifications WHERE company_id = $companyId");
        $pdo->exec("INSERT INTO notifications (company_id, user_id, type, title, message, is_read, created_at) VALUES 
            ($companyId, 1, 'leave', 'New Leave Request', 'Maria Santos requested vacation leave', 0, NOW()),
            ($companyId, 1, 'attendance', 'Late Arrival Alert', '3 employees arrived late today', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
            ($companyId, NULL, 'announcement', 'System Maintenance', 'Scheduled maintenance this weekend', 0, DATE_SUB(NOW(), INTERVAL 3 DAY))");
        echo "✓ Added notifications\n";
    } catch(Exception $e) {}
    
    try {
        $pdo->exec("DELETE FROM announcements WHERE company_id = $companyId");
        $pdo->exec("INSERT INTO announcements (company_id, title, content, priority, is_pinned, published_at, created_by) VALUES 
            ($companyId, 'Welcome to HRMS!', 'We are excited to launch our new HR Management System.', 'high', 1, NOW(), 1),
            ($companyId, 'QR Code Attendance', 'We have implemented QR code based attendance system.', 'normal', 0, DATE_SUB(NOW(), INTERVAL 5 DAY), 1)");
        echo "✓ Added announcements\n";
    } catch(Exception $e) {}
    
    try {
        $pdo->exec("INSERT IGNORE INTO user_preferences (user_id, theme) VALUES (1, 'light')");
        echo "✓ Added user preferences\n";
    } catch(Exception $e) {}
}

echo "\n</pre>";
echo "<div style='background:#d4edda;padding:20px;border-radius:8px;margin:20px 0;font-family:sans-serif;'>";
echo "<h3 style='color:#155724;margin:0 0 10px 0;'>✅ Database Seeded Successfully!</h3>";
echo "<p><strong>Summary:</strong></p>";
echo "<ul>";
echo "<li><strong>$employeeCount</strong> employees</li>";
echo "<li><strong>" . ($attendanceCount ?? 0) . "</strong> attendance records</li>";
echo "<li><strong>" . ($leaveCount ?? 0) . "</strong> leave requests</li>";
echo "<li><strong>" . count($departments) . "</strong> departments</li>";
echo "<li><strong>" . count($positions) . "</strong> positions</li>";
echo "</ul>";
echo "<p><strong>Login credentials:</strong><br>";
echo "Email: <code style='background:#f8f9fa;padding:2px 6px;border-radius:3px;'>admin@hrms.local</code><br>";
echo "Password: <code style='background:#f8f9fa;padding:2px 6px;border-radius:3px;'>admin123</code></p>";
echo "</div>";
echo "<p><a href='dashboard.php' style='display:inline-block;padding:12px 24px;background:#0d6efd;color:white;text-decoration:none;border-radius:6px;font-family:sans-serif;'>Go to Dashboard →</a></p>";
