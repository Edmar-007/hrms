<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ?? 1;
$role = (string)($_SESSION['user']['role'] ?? 'Employee');
$canManage = in_array($role, ['Admin', 'HR Officer', 'Manager'], true);

function parse_json_body(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function split_name(string $fullName): array {
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    if (count($parts) === 0) {
        return ['', ''];
    }

    if (count($parts) === 1) {
        return [$parts[0], '.'];
    }

    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return [$first, $last];
}

function ensure_lookup_id(PDO $pdo, string $table, string $name, int $cid): ?int {
    $clean = trim($name);
    if ($clean === '') {
        return null;
    }

    $find = $pdo->prepare("SELECT id FROM {$table} WHERE name = ? LIMIT 1");
    $find->execute([$clean]);
    $existing = $find->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    try {
        $insert = $pdo->prepare("INSERT INTO {$table}(name, company_id) VALUES(?, ?)");
        $insert->execute([$clean, $cid]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        $find->execute([$clean]);
        $existing = $find->fetchColumn();
        return $existing ? (int)$existing : null;
    }
}

function employee_to_payload(array $row): array {
    $name = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    return [
        'id' => (int)$row['id'],
        'employeeCode' => (string)($row['employee_code'] ?? ''),
        'name' => $name !== '' ? $name : 'Unknown Employee',
        'email' => (string)$row['email'],
        'department' => (string)($row['department'] ?? 'Unassigned'),
        'role' => (string)($row['position'] ?? 'Employee'),
        'status' => strtolower((string)($row['status'] ?? 'inactive')) === 'active' ? 'Active' : 'Inactive',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $search = trim((string)($_GET['search'] ?? ''));

    $where = "WHERE e.company_id = ?";
    $params = [$cid];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where .= " AND (
            CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
            OR COALESCE(e.email, '') LIKE ?
            OR COALESCE(e.employee_code, '') LIKE ?
            OR COALESCE(d.name, 'Unassigned') LIKE ?
            OR COALESCE(p.name, 'Employee') LIKE ?
            OR COALESCE(e.status, '') LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $countSt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN positions p ON p.id = e.position_id
         {$where}"
    );
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql =
        "SELECT e.*, d.name AS department, p.name AS position
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN positions p ON p.id = e.position_id
         {$where}
         ORDER BY e.last_name, e.first_name
         LIMIT ? OFFSET ?";
    $st = $pdo->prepare($sql);
    $index = 1;
    foreach ($params as $param) {
        $st->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->bindValue($index++, $limit, PDO::PARAM_INT);
    $st->bindValue($index, $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    $employees = array_map('employee_to_payload', $rows ?: []);
    json_response([
        'employees' => $employees,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
}

if (!$canManage) {
    json_response(['error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = parse_json_body();
    $name = trim((string)($body['name'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $department = trim((string)($body['department'] ?? ''));
    $position = trim((string)($body['role'] ?? ''));
    $status = strtolower((string)($body['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';

    if ($name === '' || $email === '') {
        json_response(['error' => 'Name and email are required'], 422);
    }

    [$firstName, $lastName] = split_name($name);
    $departmentId = ensure_lookup_id($pdo, 'departments', $department, $cid);
    $positionId = ensure_lookup_id($pdo, 'positions', $position, $cid);

    try {
        $code = generate_employee_code($cid);
        $st = $pdo->prepare(
            "INSERT INTO employees(
                employee_code, first_name, last_name, email, department_id, position_id,
                basic_salary, status, company_id, hire_date
            ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, CURDATE())"
        );
        $st->execute([
            $code,
            $firstName,
            $lastName,
            $email,
            $departmentId,
            $positionId,
            $status,
            $cid,
        ]);

        $id = (int)$pdo->lastInsertId();
        $fetch = $pdo->prepare(
            "SELECT e.*, d.name AS department, p.name AS position
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.id = ? LIMIT 1"
        );
        $fetch->execute([$id]);
        $row = $fetch->fetch();

        log_activity('create_employee', 'employee', $id, ['email' => $email]);
        json_response(['success' => true, 'employee' => employee_to_payload($row ?: [])]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            json_response(['error' => 'Employee email already exists'], 409);
        }
        error_log('Employees API create error: ' . $e->getMessage());
        json_response(['error' => 'Unable to create employee'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = parse_json_body();
    $id = (int)($body['id'] ?? 0);
    $name = trim((string)($body['name'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $department = trim((string)($body['department'] ?? ''));
    $position = trim((string)($body['role'] ?? ''));
    $status = strtolower((string)($body['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';

    if ($id <= 0 || $name === '' || $email === '') {
        json_response(['error' => 'Invalid employee payload'], 422);
    }

    [$firstName, $lastName] = split_name($name);
    $departmentId = ensure_lookup_id($pdo, 'departments', $department, $cid);
    $positionId = ensure_lookup_id($pdo, 'positions', $position, $cid);

    try {
        $st = $pdo->prepare(
            'UPDATE employees
             SET first_name = ?, last_name = ?, email = ?, department_id = ?, position_id = ?, status = ?
             WHERE id = ? AND company_id = ?'
        );
        $st->execute([
            $firstName,
            $lastName,
            $email,
            $departmentId,
            $positionId,
            $status,
            $id,
            $cid,
        ]);

        $fetch = $pdo->prepare(
            "SELECT e.*, d.name AS department, p.name AS position
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.id = ? LIMIT 1"
        );
        $fetch->execute([$id]);
        $row = $fetch->fetch();

        log_activity('update_employee', 'employee', $id, ['email' => $email]);
        json_response(['success' => true, 'employee' => employee_to_payload($row ?: [])]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            json_response(['error' => 'Employee email already exists'], 409);
        }
        error_log('Employees API update error: ' . $e->getMessage());
        json_response(['error' => 'Unable to update employee'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body = parse_json_body();
    $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        json_response(['error' => 'Invalid employee id'], 422);
    }

    $st = $pdo->prepare('UPDATE employees SET status = ? WHERE id = ? AND company_id = ?');
    $st->execute(['inactive', $id, $cid]);
    log_activity('deactivate_employee', 'employee', $id, []);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
