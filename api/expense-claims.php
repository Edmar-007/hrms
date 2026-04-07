<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ?? 1;

function table_columns(PDO $pdo, string $table): array {
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $st->fetchAll() ?: [];
    $cache[$table] = array_map(function ($row) {
        return (string)$row['Field'];
    }, $rows);

    return $cache[$table];
}

function has_column(PDO $pdo, string $table, string $column): bool {
    return in_array($column, table_columns($pdo, $table), true);
}

function parse_json_body_claims(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function generate_claim_number(PDO $pdo, int $cid): string {
    $year = date('Y');
    $st = $pdo->prepare("SELECT COUNT(*) + 1 AS next_num FROM expense_claims WHERE company_id = ? AND YEAR(created_at) = ?");
    $st->execute([$cid, $year]);
    $next = (int)($st->fetchColumn() ?: 1);
    return 'CLM-' . $year . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $search = trim((string)($_GET['search'] ?? ''));
    try {
        $hasModernFields =
            has_column($pdo, 'expense_claims', 'claim_number') &&
            has_column($pdo, 'expense_claims', 'title') &&
            has_column($pdo, 'expense_claims', 'total_amount');

        $where = "WHERE c.company_id = ?";
        $params = [$cid];

        if ($search !== '') {
            $like = '%' . $search . '%';
            if ($hasModernFields) {
                $where .= " AND (
                    COALESCE(c.claim_number, '') LIKE ?
                    OR COALESCE(c.title, '') LIKE ?
                    OR COALESCE(c.status, '') LIKE ?
                    OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
                )";
                array_push($params, $like, $like, $like, $like);
            } else {
                $where .= " AND (
                    CONCAT('LEG-', LPAD(CAST(c.id AS CHAR), 5, '0')) LIKE ?
                    OR COALESCE(c.category, '') LIKE ?
                    OR COALESCE(c.status, '') LIKE ?
                    OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
                )";
                array_push($params, $like, $like, $like, $like);
            }
        }

        $countSql = $hasModernFields
            ? "SELECT COUNT(*)
               FROM expense_claims c
               LEFT JOIN employees e ON e.id = c.employee_id
               {$where}"
            : "SELECT COUNT(*)
               FROM expense_claims c
               LEFT JOIN employees e ON e.id = c.employee_id
               {$where}";
        $countSt = $pdo->prepare($countSql);
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        if ($hasModernFields) {
            $sql =
                "SELECT c.id, c.claim_number, c.title, c.total_amount, c.currency, c.status, c.created_at,
                        e.first_name, e.last_name
                 FROM expense_claims c
                 LEFT JOIN employees e ON e.id = c.employee_id
                 {$where}
                 ORDER BY c.created_at DESC
                 LIMIT ? OFFSET ?";
        } else {
            $sql =
                "SELECT c.id, c.category AS title, c.amount AS total_amount, c.status, c.created_at,
                        e.first_name, e.last_name
                 FROM expense_claims c
                 LEFT JOIN employees e ON e.id = c.employee_id
                 {$where}
                 ORDER BY c.created_at DESC
                 LIMIT ? OFFSET ?";
        }

        $st = $pdo->prepare($sql);
        $index = 1;
        foreach ($params as $param) {
            $st->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue($index++, $limit, PDO::PARAM_INT);
        $st->bindValue($index, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $claims = array_map(function ($row) use ($hasModernFields) {
            return [
                'id' => (int)$row['id'],
                'claimNumber' => $hasModernFields
                    ? (string)($row['claim_number'] ?? '')
                    : 'LEG-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT),
                'title' => (string)$row['title'],
                'amount' => (float)$row['total_amount'],
                'currency' => (string)($row['currency'] ?? 'PHP'),
                'status' => (string)$row['status'],
                'employeeName' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: 'Unknown',
                'createdAt' => (string)$row['created_at'],
            ];
        }, $rows);

        json_response([
            'claims' => $claims,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'legacyApprovalsUrl' => '/hrms/modules/claims/index.php',
        ]);
    } catch (Exception $e) {
        error_log('Expense claims API get error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load expense claims.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = parse_json_body_claims();
    $title = trim((string)($body['title'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $amount = (float)($body['amount'] ?? 0);

    if ($title === '' || $amount <= 0) {
        json_response(['error' => 'Title and amount are required'], 422);
    }

    try {
        $hasModernFields =
            has_column($pdo, 'expense_claims', 'claim_number') &&
            has_column($pdo, 'expense_claims', 'title') &&
            has_column($pdo, 'expense_claims', 'total_amount');

        $employeeId = (int)($_SESSION['user']['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            $emp = $pdo->prepare("SELECT id FROM employees WHERE company_id = ? ORDER BY id ASC LIMIT 1");
            $emp->execute([$cid]);
            $employeeId = (int)($emp->fetchColumn() ?: 0);
        }

        if ($employeeId <= 0) {
            json_response(['error' => 'No employee profile found for this company'], 422);
        }

        if ($hasModernFields) {
            $claimNumber = generate_claim_number($pdo, $cid);
            $status = 'submitted';

            $st = $pdo->prepare(
                "INSERT INTO expense_claims(
                    company_id, employee_id, claim_number, title, description,
                    total_amount, currency, status, submitted_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'PHP', ?, NOW())"
            );
            $st->execute([$cid, $employeeId, $claimNumber, $title, $description !== '' ? $description : null, $amount, $status]);
        } else {
            $claimNumber = 'LEG-' . date('Ymd-His');
            $status = 'pending';

            $st = $pdo->prepare(
                "INSERT INTO expense_claims(
                    company_id, employee_id, claim_date, category, description,
                    amount, status
                ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)"
            );
            $st->execute([$cid, $employeeId, substr($title, 0, 100), $description !== '' ? $description : null, $amount, $status]);
        }

        $id = (int)$pdo->lastInsertId();
        log_activity('create_claim', 'expense_claim', $id, ['claim_number' => $claimNumber]);

        json_response([
            'success' => true,
            'claim' => [
                'id' => $id,
                'claimNumber' => $claimNumber,
                'title' => $title,
                'amount' => $amount,
                'currency' => 'PHP',
                'status' => $status,
                'employeeName' => trim((string)(($_SESSION['user']['name'] ?? 'Employee'))),
                'createdAt' => date('Y-m-d H:i:s'),
            ],
        ]);
    } catch (Exception $e) {
        error_log('Expense claims API post error: ' . $e->getMessage());
        json_response(['error' => 'Unable to submit claim. Ensure claim tables exist.'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
