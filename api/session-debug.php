<?php
require_once 'auth.php';
header('Content-Type: application/json; charset=utf-8');

session_start();

echo json_encode([
    'session_id' => session_id(),
    'session_save_path' => session_save_path(),
    'cookie' => $_COOKIE,
    'session' => $_SESSION,
]);
