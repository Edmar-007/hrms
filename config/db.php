<?php
require_once __DIR__ . '/config.php';
$host='localhost'; $db='hrms_db'; $user='root'; $pass=''; $charset='utf8mb4'; $port=33060;
$dsn="mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
try{
    $pdo=new PDO($dsn,$user,$pass,$options);
}catch(PDOException $e){
    error_log('Database connection failed: '.$e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}
