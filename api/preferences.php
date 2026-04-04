<?php
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../includes/csrf.php";

header("Content-Type: application/json");

if(empty($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION["user"]["id"];

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!verify_csrf($input["csrf_token"] ?? null)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid CSRF"]);
        exit;
    }
    
    if(isset($input["theme"])) {
        $theme = in_array($input["theme"], ["light", "dark"]) ? $input["theme"] : "light";
        
        $st = $pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme = ?");
        $st->execute([$userId, $theme, $theme]);
        
        $_SESSION["user"]["theme"] = $theme;
        
        echo json_encode(["success" => true, "theme" => $theme]);
        exit;
    }
}

$st = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$st->execute([$userId]);
$prefs = $st->fetch() ?: ["theme" => "light"];

echo json_encode($prefs);
