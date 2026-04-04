<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_login();
require_role(['Admin', 'HR Officer']);

if(is_post() && verify_csrf()) {
    $code = trim($_POST['employee_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $salary = floatval($_POST['basic_salary'] ?? 0);
    $dept = $_POST['department_id'] ?: null;
    $pos = $_POST['position_id'] ?: null;
    
    // Check if SaaS mode (company_id column exists)
    $hasSaas = $pdo->query("SHOW COLUMNS FROM employees LIKE 'company_id'")->fetch();
    
    if($hasSaas) {
        $cid = company_id() ?? 1;
        $st = $pdo->prepare("INSERT INTO employees (company_id, employee_code, email, first_name, last_name, phone, basic_salary, department_id, position_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $st->execute([$cid, $code, $email, $first, $last, $phone, $salary, $dept, $pos]);
    } else {
        $st = $pdo->prepare("INSERT INTO employees (employee_code, email, first_name, last_name, phone, basic_salary, department_id, position_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $st->execute([$code, $email, $first, $last, $phone, $salary, $dept, $pos]);
    }
    
    header("Location: index.php?msg=added");
    exit;
}

header("Location: index.php");
exit;
