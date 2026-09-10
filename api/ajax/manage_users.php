<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireAdminUser();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? 'get';
$tables = ['patient' => 'users', 'admin' => 'Administradores', 'professional' => 'Profesionales'];

function respondUsers(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function normalizeUser(array $input, bool $passwordRequired = false): array {
    $user = [
        'ci' => trim((string)($input['ci'] ?? '')),
        'name' => trim((string)($input['name'] ?? '')),
        'surname' => trim((string)($input['surname'] ?? '')),
        'address' => trim((string)($input['address'] ?? '')),
        'phone' => trim((string)($input['phone'] ?? '')),
        'email' => trim((string)($input['email'] ?? '')),
        'role' => (string)($input['role'] ?? 'patient'),
        'password' => (string)($input['password'] ?? ''),
    ];
    if (!ctype_digit($user['ci']) || (int)$user['ci'] <= 0) respondUsers(false, 'La CI debe contener solo números.');
    if ($user['name'] === '' || $user['surname'] === '' || $user['address'] === '' || $user['phone'] === '' || $user['email'] === '') respondUsers(false, 'Completá todos los campos obligatorios.');
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) respondUsers(false, 'El email no es válido.');
    if (!array_key_exists($user['role'], ['patient' => true, 'admin' => true, 'professional' => true])) respondUsers(false, 'El tipo de usuario no es válido.');
    if ($passwordRequired && strlen($user['password']) < 6) respondUsers(false, 'La contraseña debe tener al menos 6 caracteres.');
    return $user;
}

function findUser(PDO $conn, string $ci): ?array {
    $stmt = $conn->prepare("SELECT CI AS ci, name, surname, address, phone, email, role FROM users WHERE CI = ?
        UNION ALL SELECT CI, name, surname, address, phone, email, 'admin' FROM Administradores WHERE CI = ?
        UNION ALL SELECT CI, name, surname, address, phone, email, 'professional' FROM Profesionales WHERE CI = ? LIMIT 1");
    $stmt->execute([$ci, $ci, $ci]);
    return $stmt->fetch() ?: null;
}

function ensureUnique(PDO $conn, string $ci, string $email, ?string $ignoreCi = null): void {
    $stmt = $conn->prepare("SELECT ci FROM (
        SELECT CI AS ci, email FROM users UNION ALL
        SELECT CI, email FROM Administradores UNION ALL
        SELECT CI, email FROM Profesionales
    ) all_users WHERE email = ? AND (? IS NULL OR ci <> ?) LIMIT 1");
    $stmt->execute([$email, $ignoreCi, $ignoreCi]);
    if ($stmt->fetch()) respondUsers(false, 'Ese email ya está registrado. Elegí otro.', ['code' => 'email_exists']);

    $stmt = $conn->prepare("SELECT ci FROM (
        SELECT CI AS ci, email FROM users UNION ALL
        SELECT CI, email FROM Administradores UNION ALL
        SELECT CI, email FROM Profesionales
    ) all_users WHERE ci = ? AND (? IS NULL OR ci <> ?) LIMIT 1");
    $stmt->execute([$ci, $ignoreCi, $ignoreCi]);
    if ($stmt->fetch()) respondUsers(false, 'Esa CI ya está registrada. Podés restablecer su contraseña.', ['code' => 'ci_exists']);
}

try {
    if ($action === 'get') {
        $ci = trim((string)($_GET['ci'] ?? $input['ci'] ?? ''));
        if (!ctype_digit($ci) || (int)$ci <= 0) respondUsers(false, 'Ingresá una CI válida.');
        $user = findUser($conn, $ci);
        if (!$user) respondUsers(false, 'No se encontró ningún usuario con esa CI.');
        respondUsers(true, '', ['user' => $user]);
    }

    if ($action === 'create') {
        $user = normalizeUser($input, true);
        ensureUnique($conn, $user['ci'], $user['email']);
        $table = $tables[$user['role']];
        $stmt = $conn->prepare("INSERT INTO `$table` (CI, name, surname, address, phone, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['ci'], $user['name'], $user['surname'], $user['address'], $user['phone'], $user['email'], password_hash($user['password'], PASSWORD_DEFAULT), $user['role']]);
        respondUsers(true, 'Usuario creado correctamente.');
    }

    if ($action === 'reset_password') {
        $ci = trim((string)($input['ci'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if (!ctype_digit($ci) || (int)$ci <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            respondUsers(false, 'Ingresá una CI, email válido y contraseña de al menos 6 caracteres.');
        }
        foreach ($tables as $table) {
            $stmt = $conn->prepare("SELECT CI FROM `$table` WHERE CI = ? AND email = ? LIMIT 1");
            $stmt->execute([$ci, $email]);
            if ($stmt->fetch()) {
                $update = $conn->prepare("UPDATE `$table` SET password = ? WHERE CI = ?");
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $ci]);
                respondUsers(true, 'Contraseña restablecida correctamente.');
            }
        }
        respondUsers(false, 'La CI y el email no coinciden con un usuario registrado.');
    }

    if ($action === 'update') {
        $user = normalizeUser($input);
        $current = findUser($conn, $user['ci']);
        if (!$current) respondUsers(false, 'No se encontró ningún usuario con esa CI.');
        if ($current['role'] !== $user['role']) respondUsers(false, 'El tipo de usuario no puede cambiarse desde esta pantalla.');
        ensureUnique($conn, $user['ci'], $user['email'], $user['ci']);
        $table = $tables[$user['role']];
        $passwordSql = $user['password'] !== '' ? ', password = ?' : '';
        $params = [$user['name'], $user['surname'], $user['address'], $user['phone'], $user['email']];
        if ($user['password'] !== '') $params[] = password_hash($user['password'], PASSWORD_DEFAULT);
        $params[] = $user['ci'];
        $stmt = $conn->prepare("UPDATE `$table` SET name = ?, surname = ?, address = ?, phone = ? , email = ?$passwordSql WHERE CI = ?");
        $stmt->execute($params);
        respondUsers(true, 'Usuario actualizado correctamente.');
    }

    if ($action === 'delete') {
        $ci = trim((string)($input['ci'] ?? ''));
        $user = findUser($conn, $ci);
        if (!$user) respondUsers(false, 'No se encontró ningún usuario con esa CI.');
        $stmt = $conn->prepare("DELETE FROM `{$tables[$user['role']]}` WHERE CI = ?");
        $stmt->execute([$ci]);
        respondUsers(true, 'Usuario eliminado correctamente.');
    }

    respondUsers(false, 'Operación no válida.');
} catch (PDOException $error) {
    error_log('Gestión de usuarios: ' . $error->getMessage());
    respondUsers(false, 'No se pudo completar la operación. Verificá los datos e intentá nuevamente.');
}