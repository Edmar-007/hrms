<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

header('Content-Type: application/json');
$cid = company_id() ?? 1;
$sinceId = (int)($_GET['since_id'] ?? 0);

$sql = "SELECT a.id, a.date, a.time_in, a.time_out, a.last_action, a.last_scan_at, e.first_name, e.last_name, e.employee_code
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.company_id = ? " . ($sinceId > 0 ? " AND a.id > ? " : "") . "
        ORDER BY a.id DESC
        LIMIT 20";
$st = $pdo->prepare($sql);
if ($sinceId > 0) $st->execute([$cid, $sinceId]); else $st->execute([$cid]);
$rows = $st->fetchAll();

echo json_encode(['success' => true, 'rows' => $rows]);

