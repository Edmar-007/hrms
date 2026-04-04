<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_login();

$eid = $_SESSION['user']['employee_id'];
if(!$eid) { header("Location: index.php"); exit; }

$d = date('Y-m-d');
$t = date('H:i:s');

$pdo->prepare("UPDATE attendance SET time_out=? WHERE employee_id=? AND date=?")->execute([$t, $eid, $d]);

header("Location: index.php?msg=out");
exit;
