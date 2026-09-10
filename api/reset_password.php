<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$ci = trim((string)($_POST['ci'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if (!ctype_digit($ci) || (int)$ci <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ingresá una CI, email válido y contraseña de al menos 6 caracteres.']);
    exit;
}

$sources = [
    ['table' => 'users', 'role' => 'patient'],
    ['table' => 'Administradores', 'role' => 'admin'],
    ['table' => 'Profesionales', 'role' => 'professional'],
];

try {
    foreach ($sources as $source) {
        $stmt = $conn->prepare("SELECT CI FROM `{$source['table']}` WHERE CI = ? AND email = ? LIMIT 1");
        $stmt->execute([$ci, $email]);
        if ($stmt->fetch()) {
            $update = $conn->prepare("UPDATE `{$source['table']}` SET password = ? WHERE CI = ?");
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $ci]);
            echo json_encode(['ok' => true, 'message' => 'Contraseña restablecida correctamente.']);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'La CI y el email no coinciden con un usuario registrado.']);
} catch (PDOException $error) {
    error_log('Restablecer contraseña: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo restablecer la contraseña.']);
}