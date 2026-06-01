<?php
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

require_once 'config:Database.php';
require_once 'config:db_config.php';

// Obtener método y acción
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($method === 'OPTIONS') {
    // Soporte para preflight CORS y evitar 405 en algunos servidores
    http_response_code(200);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'register') {
        registrarUsuario($data);
    } elseif ($action === 'login') {
        autenticarUsuario($data);
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

/**
 * Registra un nuevo usuario en la base de datos
 */
function registrarUsuario($data) {
    try {
        // Validar datos
        if (empty($data['cedula']) || empty($data['password']) || empty($data['rol'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            return;
        }
        
        $cedula = trim($data['cedula']);
        $password = $data['password'];
        $rol = trim($data['rol']); // 'Socio' o 'Empleado'
        
        // Validar cedula (formato básico)
        if (!preg_match('/^\d{1,20}$/', $cedula)) {
            echo json_encode(['success' => false, 'message' => 'Número de cédula inválido']);
            return;
        }
        
        // Validar contraseña (mínimo 6 caracteres)
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        $conn = Database::getInstance();
        
        // Verificar si la cédula ya existe
        $query = 'SELECT id FROM usuarios WHERE cedula = ?';
        $stmt = $conn->prepare($query);
        $stmt->execute([$cedula]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Esta cédula ya está registrada']);
            return;
        }
        
        // Obtener el rol_id basado en el nombre del rol
        $queryRole = 'SELECT id FROM roles WHERE nombre_rol = ?';
        $stmtRole = $conn->prepare($queryRole);
        $stmtRole->execute([$rol]);
        $roleResult = $stmtRole->fetch();
        
        if (!$roleResult) {
            echo json_encode(['success' => false, 'message' => 'Rol no válido']);
            return;
        }
        
        $rol_id = $roleResult['id'];
        
        // Hashear la contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Insertar nuevo usuario
        $queryInsert = 'INSERT INTO usuarios (cedula, nombre, apellido, email, password, rol_id) 
                       VALUES (?, ?, ?, ?, ?, ?)';
        $stmtInsert = $conn->prepare($queryInsert);
        $stmtInsert->execute([
            $cedula,
            'Usuario',
            'Pendiente',
            $cedula . '@clinica-imagen.local',
            $passwordHash,
            $rol_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso. Por favor, inicia sesión.',
            'cedula' => $cedula,
            'rol' => $rol
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al registrar']);
    }
}

/**
 * Autentica un usuario verificando cédula y contraseña
 */
function autenticarUsuario($data) {
    try {
        // Validar datos
        if (empty($data['cedula']) || empty($data['password']) || empty($data['rolEsperado'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            return;
        }
        
        $cedula = trim($data['cedula']);
        $password = $data['password'];
        $rolEsperado = trim($data['rolEsperado']); // 'Socio' o 'Empleado'
        
        $conn = Database::getInstance();
        
        // Buscar usuario por cédula con su rol
        $query = 'SELECT u.id, u.password, u.rol_id, r.nombre_rol 
                 FROM usuarios u
                 JOIN roles r ON u.rol_id = r.id
                 WHERE u.cedula = ?';
        $stmt = $conn->prepare($query);
        $stmt->execute([$cedula]);
        
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Cédula no encontrada']);
            return;
        }
        
        // Verificar contraseña
        if (!password_verify($password, $usuario['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            return;
        }
        
        // Verificar que el rol coincida
        if ($usuario['nombre_rol'] !== $rolEsperado) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos errados. El rol de esta cédula es: ' . $usuario['nombre_rol']
            ]);
            return;
        }
        
        // Login exitoso
        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'user' => [
                'id' => $usuario['id'],
                'rol' => $usuario['nombre_rol'],
                'cedula' => $cedula
            ]
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al autenticar']);
    }
}
?>