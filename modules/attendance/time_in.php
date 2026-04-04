<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_login();

$eid = $_SESSION['user']['employee_id'];
if(!$eid) { header("Location: index.php"); exit; }

$d = date('Y-m-d');
$t = date('H:i:s');

$st = $pdo->prepare("SELECT id FROM attendance WHERE employee_id=? AND date=?");
$st->execute([$eid, $d]);

if(!$st->fetch()) {
    // Check if SaaS mode
    $hasSaas = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'company_id'")->fetch();
    
    if($hasSaas) {
        $cid = company_id() ?? 1;
        $pdo->prepare("INSERT INTO attendance(company_id, employee_id, date, time_in) VALUES(?,?,?,?)")->execute([$cid, $eid, $d, $t]);
    } else {
        $pdo->prepare("INSERT INTO attendance(employee_id, date, time_in) VALUES(?,?,?)")->execute([$eid, $d, $t]);
    }
}

header("Location: index.php?msg=in");
exit;
