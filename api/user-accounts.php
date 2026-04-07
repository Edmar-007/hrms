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
$canManage = in_array($role, ['Super Admin', 'Admin', 'HR Officer'], true);

function parse_json_body_users(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        $search = trim((string)($_GET['search'] ?? ''));

        $where = "WHERE u.company_id = ?";
        $params = [$cid];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (
                COALESCE(u.email, '') LIKE ?
                OR COALESCE(u.role, '') LIKE ?
                OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
            )";
            array_push($params, $like, $like, $like);
        }

        $countSt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             {$where}"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $usersSql =
            "SELECT u.id, u.email, u.role, u.is_active, u.last_login, u.created_at,
                    e.first_name, e.last_name
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             {$where}
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?";
        $usersSt = $pdo->prepare($usersSql);
        $index = 1;
        foreach ($params as $param) {
            $usersSt->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $usersSt->bindValue($index++, $limit, PDO::PARAM_INT);
        $usersSt->bindValue($index, $offset, PDO::PARAM_INT);
        $usersSt->execute();
        $rows = $usersSt->fetchAll() ?: [];

        $users = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'email' => (string)$row['email'],
                'role' => (string)$row['role'],
                'isActive' => (int)$row['is_active'] === 1,
                'lastLogin' => (string)($row['last_login'] ?? ''),
                'name' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: 'Unlinked Account',
                'createdAt' => (string)$row['created_at'],
            ];
        }, $rows);

        $empSt = $pdo->prepare(
            "SELECT e.id, e.email, e.first_name, e.last_name
             FROM employees e
             WHERE e.company_id = ?
             ORDER BY e.last_name, e.first_name"
        );
        $empSt->execute([$cid]);
        $employees = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
                'email' => (string)$row['email'],
            ];
        }, $empSt->fetchAll() ?: []);

        json_response([
            'users' => $users,
            'employees' => $employees,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    } catch (Exception $e) {
        error_log('User accounts API get error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load user accounts data.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $body = parse_json_body_users();
    $email = trim((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $newRole = trim((string)($body['role'] ?? 'Employee'));
    $employeeId = (int)($body['employeeId'] ?? 0);

    $allowedRoles = ['Super Admin', 'Admin', 'HR Officer', 'Manager', 'Employee'];
    if ($email === '' || $password === '' || !in_array($newRole, $allowedRoles, true)) {
        json_response(['error' => 'Invalid account payload'], 422);
    }

    try {
        $st = $pdo->prepare(
            "INSERT INTO users(company_id, employee_id, email, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $st->execute([
            $cid,
            $employeeId > 0 ? $employeeId : null,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $newRole,
        ]);

        $id = (int)$pdo->lastInsertId();
        log_activity('create_user_account', 'user', $id, ['email' => $email, 'role' => $newRole]);
        json_response(['success' => true]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            json_response(['error' => 'User email already exists'], 409);
        }
        error_log('User accounts API post error: ' . $e->getMessage());
        json_response(['error' => 'Unable to create user account'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
