<?php
require_once './auth.php';
requireRole('patient');

$usuario = false;

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
        ]);
        $id_usuario = getUserId();

        $stmt = $pdo->prepare('SELECT name, surname, CI FROM users WHERE CI = ?');
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Error de conexión: " . $e->getMessage());
        $usuario = false;
    }
}
?>
