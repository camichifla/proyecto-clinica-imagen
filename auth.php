<?php
// ========================================
// HEADERS DE SEGURIDAD
// ========================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'');

// CORS headers
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

require_once 'config:Database.php';
require_once 'config:db_config.php';

// ========================================
// FUNCIONES DE SEGURIDAD
// ========================================

/**
 * Valida un rol contra una lista whitelist
 */
function esRolValido($rol) {
    $rolesValidos = ['Socio', 'Empleado', 'Paciente', 'Recepcionista', 'Doctor'];
    return in_array($rol, $rolesValidos, true);
}

/**
 * Valida formato de cédula: solo dígitos, máximo 20
 */
function validarFormatoCedula($cedula) {
    return preg_match('/^\d{1,20}$/', $cedula) === 1;
}

/**
 * Escapa HTML para evitar XSS
 */
function escaparHTML($texto) {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

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
        // Validar que los datos existan y sean del tipo correcto
        if (!is_array($data) || empty($data['cedula']) || empty($data['password']) || empty($data['rol'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            return;
        }
        
        $cedula = trim($data['cedula']);
        $password = $data['password'];
        $rol = trim($data['rol']);
        
        // Validar formato de cédula
        if (!validarFormatoCedula($cedula)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Número de cédula inválido']);
            return;
        }
        
        // Validar que el rol sea válido (whitelist)
        if (!esRolValido($rol)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rol no válido']);
            return;
        }
        
        // Validar contraseña (mínimo 6 caracteres, máximo 128)
        if (strlen($password) < 6 || strlen($password) > 128) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener entre 6 y 128 caracteres']);
            return;
        }
        
        $conn = Database::getInstance();
        
        // Verificar si la cédula ya existe (prepared statement contra SQL injection)
        $query = 'SELECT id FROM usuarios WHERE cedula = ?';
        $stmt = $conn->prepare($query);
        $stmt->execute([$cedula]);
        
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Esta cédula ya está registrada']);
            return;
        }
        
        // Obtener el rol_id basado en el nombre del rol (whitelist validada)
        $queryRole = 'SELECT id FROM roles WHERE nombre_rol = ?';
        $stmtRole = $conn->prepare($queryRole);
        $stmtRole->execute([$rol]);
        $roleResult = $stmtRole->fetch();
        
        if (!$roleResult) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rol no válido']);
            return;
        }
        
        $rol_id = $roleResult['id'];
        
        // Hashear la contraseña con BCRYPT
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
        
        // Devolver datos escapados para XSS
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso. Por favor, inicia sesión.',
            'cedula' => escaparHTML($cedula),
            'rol' => escaparHTML($rol)
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al registrar']);
    }
}

/**
 * Autentica un usuario verificando cédula y contraseña
 */
function autenticarUsuario($data) {
    try {
        // Validar que los datos existan
        if (!is_array($data) || empty($data['cedula']) || empty($data['password']) || empty($data['rolEsperado'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            return;
        }
        
        $cedula = trim($data['cedula']);
        $password = $data['password'];
        $rolEsperado = trim($data['rolEsperado']);
        
        // Validar formato de cédula
        if (!validarFormatoCedula($cedula)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Número de cédula inválido']);
            return;
        }
        
        // Validar que el rol esperado sea válido (whitelist)
        if (!esRolValido($rolEsperado)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rol no válido']);
            return;
        }
        
        $conn = Database::getInstance();
        
        // Buscar usuario por cédula con su rol (prepared statement contra SQL injection)
        $query = 'SELECT u.id, u.password, u.rol_id, r.nombre_rol 
                 FROM usuarios u
                 JOIN roles r ON u.rol_id = r.id
                 WHERE u.cedula = ?';
        $stmt = $conn->prepare($query);
        $stmt->execute([$cedula]);
        
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            // No revelar si la cédula existe o no (seguridad)
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
            return;
        }
        
        // Verificar contraseña
        if (!password_verify($password, $usuario['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
            return;
        }
        
        // Verificar que el rol coincida
        if ($usuario['nombre_rol'] !== $rolEsperado) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Rol incorrecto'
            ]);
            return;
        }
        
        // Login exitoso - devolver datos escapados
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'user' => [
                'id' => intval($usuario['id']),
                'rol' => escaparHTML($usuario['nombre_rol']),
                'cedula' => escaparHTML($cedula)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al autenticar']);
    }
}
?>