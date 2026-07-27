<?php
require_once './auth.php';
header('Content-Type: application/json; charset=utf-8');

$debug = [
    'session_id' => session_id(),
    'session' => $_SESSION,
    'isLoggedIn' => isLoggedIn(),
    'userType' => getUserType(),
    'userId' => getUserId(),
];

if (isLoggedIn()) {
    $host = '127.0.0.1';
    $db   = 'users_db';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $stmt = $pdo->prepare('SELECT name, surname, CI FROM users WHERE CI = ?');
        $stmt->execute([getUserId()]);
        $debug['queryResult'] = $stmt->fetch();
    } catch (PDOException $e) {
        $debug['error'] = $e->getMessage();
    }
}

echo json_encode($debug);
