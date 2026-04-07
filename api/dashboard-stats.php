<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$cid = company_id() ?? 1;

$stats = [];

try {
  // Active employees
  $st = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
  $st->execute([$cid]);
  $stats['activeEmployees'] = $st->fetchColumn();

  // Pending leaves
  $st = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'");
  $st->execute([$cid]);
  $stats['pendingLeaves'] = $st->fetchColumn();

  // Today's attendance
  $st = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE company_id = ? AND date = CURDATE()");
  $st->execute([$cid]);
  $stats['todayAttendance'] = $st->fetchColumn();

  // System users
  $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND is_active = 1");
  $st->execute([$cid]);
  $stats['activeUsers'] = $st->fetchColumn();

  echo json_encode($stats);
} catch (PDOException $e) {
  error_log('Dashboard stats API error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Unable to load dashboard statistics']);
}
?>

