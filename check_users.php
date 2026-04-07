<?php
require_once __DIR__ . '/config/db.php';
try {
    $results = $pdo->query("SELECT email, password_hash FROM users")->fetchAll();
    echo json_encode($results);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
