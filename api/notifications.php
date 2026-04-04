<?php
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/auth.php";

header("Content-Type: application/json");

if(empty($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION["user"]["id"];
$companyId = company_id();

if(isset($_GET["action"]) && $_GET["action"] === "read_all" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND company_id = ?")
        ->execute([$userId, $companyId]);
    echo json_encode(["success" => true]);
    exit;
}

$st = $pdo->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND company_id = ? ORDER BY created_at DESC LIMIT 10");
$st->execute([$userId, $companyId]);
$notifications = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND company_id = ? AND is_read = 0");
$st->execute([$userId, $companyId]);
$unread = $st->fetch()["cnt"];

foreach($notifications as &$n) {
    $n["time_ago"] = time_ago($n["created_at"]);
}

echo json_encode(["notifications" => $notifications, "unread" => (int)$unread]);
