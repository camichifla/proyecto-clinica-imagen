<?php
require_once './auth.php';
requireRole('patient');

$usuario = false;
$ordenActiva = null;
$citasProgramadas = [];

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
        $id_usuario = getUserId();

        $stmt = $pdo->prepare('SELECT name, surname, CI FROM users WHERE CI = ?');
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM agenda WHERE paciente_ci = ? AND estado IN ('pendiente','confirmada') ORDER BY fecha_hora ASC LIMIT 1");
        $stmt->execute([$id_usuario]);
        $ordenActiva = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT * FROM agenda WHERE paciente_ci = ? ORDER BY fecha_hora ASC');
        $stmt->execute([$id_usuario]);
        $citasProgramadas = $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error de conexión: " . $e->getMessage());
        $usuario = false;
    }
}
?>
