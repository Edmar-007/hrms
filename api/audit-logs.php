<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        $search = trim((string)($_GET['search'] ?? ''));

        $where = "WHERE al.company_id = ?";
        $params = [$cid];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (
                COALESCE(al.action, '') LIKE ?
                OR COALESCE(al.entity_type, '') LIKE ?
                OR CAST(al.entity_id AS CHAR) LIKE ?
                OR COALESCE(al.ip_address, '') LIKE ?
                OR COALESCE(u.email, '') LIKE ?
                OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
            )";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $countSt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN employees e ON e.id = u.employee_id
             {$where}"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql =
            "SELECT al.id, al.action, al.entity_type, al.entity_id, al.ip_address, al.created_at,
                    u.email,
                    e.first_name, e.last_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN employees e ON e.id = u.employee_id
             {$where}
             ORDER BY al.created_at DESC
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

        $logs = array_map(function ($row) {
            $name = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            if ($name === '') {
                $name = (string)($row['email'] ?? 'System');
            }

            return [
                'id' => (int)$row['id'],
                'actor' => $name,
                'action' => (string)$row['action'],
                'entityType' => (string)($row['entity_type'] ?? ''),
                'entityId' => isset($row['entity_id']) ? (int)$row['entity_id'] : null,
                'ipAddress' => (string)($row['ip_address'] ?? ''),
                'createdAt' => (string)$row['created_at'],
            ];
        }, $rows);

        json_response([
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    } catch (Exception $e) {
        error_log('Audit logs API error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load audit logs.'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
