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

function parse_json_body_attendance(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $search = trim((string)($_GET['search'] ?? ''));
    try {
        $where = "WHERE a.company_id = ?";
        $params = [$cid];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (
                e.employee_code LIKE ?
                OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
                OR COALESCE(d.name, 'Unassigned') LIKE ?
                OR a.date LIKE ?
                OR COALESCE(a.time_in, '') LIKE ?
                OR COALESCE(a.time_out, '') LIKE ?
                OR COALESCE(a.notes, '') LIKE ?
            )";
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        $countSt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             {$where}"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = 
            "SELECT a.id, a.employee_id, a.date, a.time_in, a.time_out, a.notes,
                    e.employee_code, e.first_name, e.last_name,
                    d.name AS department
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             {$where}
             ORDER BY a.date DESC, a.time_in DESC
             LIMIT ? OFFSET ?";
        $st = $pdo->prepare($sql);

        $index = 1;
        foreach ($params as $param) {
            $st->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue($index++, $limit, PDO::PARAM_INT);
        $st->bindValue($index, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $attendance = array_map(function ($row) {
            $status = 'Incomplete';
            if (!empty($row['time_in']) && !empty($row['time_out'])) {
                $status = 'Complete';
            } elseif (!empty($row['time_in'])) {
                $status = 'In Progress';
            }

            return [
                'id' => (int)$row['id'],
                'employeeId' => (int)$row['employee_id'],
                'employeeCode' => (string)($row['employee_code'] ?? ''),
                'employeeName' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
                'department' => (string)($row['department'] ?? 'Unassigned'),
                'date' => (string)$row['date'],
                'timeIn' => (string)($row['time_in'] ?? ''),
                'timeOut' => (string)($row['time_out'] ?? ''),
                'notes' => (string)($row['notes'] ?? ''),
                'status' => $status,
            ];
        }, $rows);

        json_response([
            'attendance' => $attendance,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'scannerUrl' => '/hrms/attendance',
            'logsUrl' => '/hrms/attendance',
        ]);
    } catch (Exception $e) {
        error_log('Attendance API get error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load attendance records.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $body = parse_json_body_attendance();
    $employeeId = (int)($body['employeeId'] ?? 0);
    $date = trim((string)($body['date'] ?? date('Y-m-d')));
    $timeIn = trim((string)($body['timeIn'] ?? ''));
    $timeOut = trim((string)($body['timeOut'] ?? ''));
    $notes = trim((string)($body['notes'] ?? ''));

    if ($employeeId <= 0) {
        json_response(['error' => 'Employee is required'], 422);
    }

    $st = $pdo->prepare(
        "INSERT INTO attendance(company_id, employee_id, date, time_in, time_out, notes)
         VALUES(?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            time_in = COALESCE(VALUES(time_in), time_in),
            time_out = COALESCE(VALUES(time_out), time_out),
            notes = COALESCE(VALUES(notes), notes)"
    );
    $st->execute([$cid, $employeeId, $date, $timeIn !== '' ? $timeIn : null, $timeOut !== '' ? $timeOut : null, $notes !== '' ? $notes : null]);

    log_activity('upsert_attendance', 'attendance', $employeeId, ['date' => $date]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
