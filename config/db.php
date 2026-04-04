<?php
require_once __DIR__ . '/config.php';

// Load .env file if it exists (simple key=value parser)
$envFile = __DIR__ . '/../.env';
if(is_readable($envFile)) {
    foreach(file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if(strpos(trim($line), '#') === 0) continue;
        if(strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            // Strip inline comments and surrounding quotes from value
            $v = trim(explode(' #', $v, 2)[0]);
            $v = trim($v, '"\'');
            if($k !== '' && !isset($_ENV[$k])) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

$host    = getenv('DB_HOST')  ?: 'localhost';
$db      = getenv('DB_NAME')  ?: 'hrms_db';
$user    = getenv('DB_USER')  ?: 'root';
$pass    = getenv('DB_PASS')  ?: '';
$port    = (int)(getenv('DB_PORT') ?: 3306);
$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch(PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
