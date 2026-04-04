<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
require_role(['Admin', 'HR Officer', 'Manager']);

$cid = company_id() ?? 1;
$type = $_GET['type'] ?? 'attendance';
$format = strtolower($_GET['format'] ?? 'csv');
$month = $_GET['month'] ?? date('Y-m');

if (!in_array($format, ['csv', 'print'], true)) {
    http_response_code(400);
    exit('Unsupported format');
}

$rows = [];
if ($type === 'attendance') {
    $st = $pdo->prepare("SELECT a.date, e.employee_code, e.first_name, e.last_name, a.time_in, a.time_out, a.last_action
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.company_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
        ORDER BY a.date DESC, e.last_name, e.first_name");
    $st->execute([$cid, $month]);
    $rows = $st->fetchAll();
} elseif ($type === 'leaves') {
    $st = $pdo->prepare("SELECT lr.start_date, lr.end_date, lr.status, e.employee_code, e.first_name, e.last_name, lt.name as leave_type
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.company_id = ? AND DATE_FORMAT(lr.start_date, '%Y-%m') = ?
        ORDER BY lr.start_date DESC");
    $st->execute([$cid, $month]);
    $rows = $st->fetchAll();
} elseif ($type === 'audit') {
    $st = $pdo->prepare("SELECT created_at, action, entity_type, entity_id, ip_address FROM activity_logs WHERE company_id = ? ORDER BY created_at DESC LIMIT 1000");
    $st->execute([$cid]);
    $rows = $st->fetchAll();
} else {
    http_response_code(400);
    exit('Unsupported report type');
}

if ($format === 'print') {
    ?><!doctype html><html><head><meta charset="utf-8"><title>Printable <?= htmlspecialchars($type) ?> report</title>
    <style>body{font-family:Arial,sans-serif}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px;font-size:12px}</style>
    </head><body><h3><?= htmlspecialchars(ucfirst($type)) ?> Report (<?= htmlspecialchars($month) ?>)</h3><?php if (empty($rows)): ?>
    <p>No records found.</p>
    <?php else: ?><table><thead><?php
    echo '<tr>';
    foreach(array_keys($rows[0]) as $k) echo '<th>'.htmlspecialchars($k).'</th>';
    echo '</tr>';
    echo '</thead><tbody>';
    foreach($rows as $r){ echo '<tr>'; foreach($r as $v) echo '<td>'.htmlspecialchars((string)$v).'</td>'; echo '</tr>'; }
    echo '</tbody></table>';
    endif; ?><script>window.print()</script></body></html><?php
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$type.'-'.$month.'.csv"');
$out = fopen('php://output', 'w');
if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) fputcsv($out, $r);
}
fclose($out);
exit;
