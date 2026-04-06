<?php require_once __DIR__.'/config/config.php'; if(!empty($_SESSION['user'])) header("Location: ".BASE_URL."/modules/dashboard.php"); else header("Location: ".BASE_URL."/auth/login.php"); exit;
