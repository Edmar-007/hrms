<?php
require_once __DIR__ . '/config.php';

global $pdo;

// Try multiple connection configurations
$connectionConfigs = [
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 33060],
    ['host' => '127.0.0.1', 'port' => 33060],
];

$db = 'hrms_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

$pdo = null;
$connectionError = null;

foreach ($connectionConfigs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname=$db;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Connection successful, break out of loop
        break;
    } catch (PDOException $e) {
        $connectionError = $e->getMessage();
        continue; // Try next configuration
    }
}

if (!$pdo) {
    error_log('Database connection failed after trying all configurations: ' . $connectionError);
    http_response_code(500);
    echo '<h2>Database connection failed</h2>';
    echo '<pre>';
    echo 'Tried the following configurations:' . "\n";
    foreach ($connectionConfigs as $config) {
        echo "Host: {$config['host']}, Port: {$config['port']}\n";
    }
    echo "\nError: $connectionError\n";
    echo '</pre>';
    exit;
}
