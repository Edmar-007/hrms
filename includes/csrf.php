<?php
function csrf_token(){ if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token'];}
function csrf_input(){ return '<input type="hidden" name="csrf_token" value="'.csrf_token().'">';}
function verify_csrf($token=null){
    if (!isset($_SESSION['csrf_token'])) return false;
    if ($token === null) $token = $_POST['csrf_token'] ?? $_POST['csrf'] ?? null;
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
