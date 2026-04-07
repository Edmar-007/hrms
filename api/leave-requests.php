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
$canManage = in_array($role, ['Super Admin', 'Admin', 'HR Officer', 'Manager'], true);

function parse_json_body_leave_requests(): array {
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
        $where = "WHERE lr.company_id = ?";
        $params = [$cid];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (
                e.employee_code LIKE ?
                OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
                OR COALESCE(lt.name, 'Leave') LIKE ?
                OR lr.status LIKE ?
                OR COALESCE(lr.reason, '') LIKE ?
                OR lr.start_date LIKE ?
                OR lr.end_date LIKE ?
            )";
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        $countSt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             {$where}"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql =
            "SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.reason,
                    lr.status, lr.approved_by, lr.approved_at, lr.created_at,
                    e.employee_code, e.first_name, e.last_name,
                    lt.name AS leave_type,
                    approver.first_name AS approver_first_name,
                    approver.last_name AS approver_last_name
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             LEFT JOIN employees approver ON approver.id = lr.approved_by
             {$where}
             ORDER BY lr.created_at DESC
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

        $requests = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'employeeId' => (int)$row['employee_id'],
                'employeeCode' => (string)($row['employee_code'] ?? ''),
                'employeeName' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
                'leaveType' => (string)($row['leave_type'] ?? 'Leave'),
                'startDate' => (string)$row['start_date'],
                'endDate' => (string)$row['end_date'],
                'reason' => (string)($row['reason'] ?? ''),
                'status' => (string)$row['status'],
                'approvedBy' => trim((string)(($row['approver_first_name'] ?? '') . ' ' . ($row['approver_last_name'] ?? ''))),
                'approvedAt' => (string)($row['approved_at'] ?? ''),
                'createdAt' => (string)$row['created_at'],
            ];
        }, $rows);

        $leaveTypeSt = $pdo->prepare(
            "SELECT id, name
             FROM leave_types
             WHERE company_id = ?
             ORDER BY name ASC"
        );
        $leaveTypeSt->execute([$cid]);
        $leaveTypes = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
            ];
        }, $leaveTypeSt->fetchAll() ?: []);

        json_response([
            'requests' => $requests,
            'leaveTypes' => $leaveTypes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } catch (Exception $e) {
        error_log('Leave requests API get error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load leave requests.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = parse_json_body_leave_requests();

    $employeeId = (int)($body['employeeId'] ?? ($_SESSION['user']['employee_id'] ?? 0));
    $leaveTypeId = (int)($body['leaveTypeId'] ?? 0);
    $startDate = trim((string)($body['startDate'] ?? ''));
    $endDate = trim((string)($body['endDate'] ?? ''));
    $reason = trim((string)($body['reason'] ?? ''));

    if ($employeeId <= 0 || $leaveTypeId <= 0 || $startDate === '' || $endDate === '') {
        json_response(['error' => 'Employee, leave type, and date range are required.'], 422);
    }

    if ($endDate < $startDate) {
        json_response(['error' => 'End date cannot be earlier than start date.'], 422);
    }

    if (!$canManage && (int)($_SESSION['user']['employee_id'] ?? 0) !== $employeeId) {
        json_response(['error' => 'You can only submit your own leave requests.'], 403);
    }

    try {
        $st = $pdo->prepare(
            'INSERT INTO leave_requests(company_id, employee_id, leave_type_id, start_date, end_date, reason, status)
             VALUES(?, ?, ?, ?, ?, ?, "pending")'
        );
        $st->execute([$cid, $employeeId, $leaveTypeId, $startDate, $endDate, $reason !== '' ? $reason : null]);

        $id = (int)$pdo->lastInsertId();
        log_activity('create_leave_request', 'leave_request', $id, [
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        json_response(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        error_log('Leave requests API post error: ' . $e->getMessage());
        json_response(['error' => 'Unable to submit leave request.'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
