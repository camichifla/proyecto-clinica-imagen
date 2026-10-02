<?php
// includes/session_bootstrap.php — overrides XAMPP's insecure session.ini
// defaults at the application layer only. /opt/lampp/etc/php.ini is never
// edited; this file makes the app correct regardless of it.
require_once __DIR__ . '/config.php';

const SESION_TIMEOUT_SEGUNDOS = 1500; // 25 minutes

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1'); // XAMPP php.ini ships 0
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => APP_HTTPS, // config-driven: forcing true over HTTP silently drops the cookie
        'httponly' => true,      // XAMPP php.ini ships this empty
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['cuenta_id']) && isset($_SESSION['ultimo_acceso'])
    && (time() - $_SESSION['ultimo_acceso']) > SESION_TIMEOUT_SEGUNDOS
) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesion expirada.', 'expirado' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . APP_URL . '/login.html?expirado=1');
    exit;
}

$_SESSION['ultimo_acceso'] = time();

/**
 * Returns the CSRF token for the current session, generating one on first use.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Validates a submitted CSRF token against the session's token. Responds
 * with 403 and exits on mismatch or missing token — never returns false.
 */
function csrf_check(?string $token): void
{
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        // PHP drops $_POST entirely when the body exceeds post_max_size.
        $excedido = empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
        http_response_code($excedido ? 413 : 403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $excedido
            ? 'Los archivos superan el tamano maximo permitido.'
            : 'La sesion cambio. Recarga la pagina e intenta nuevamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Prologue for JSON POST-only endpoints: JSON content type, 405 on any other
 * method, CSRF check.
 */
function require_post_json(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    csrf_check($_POST['csrf'] ?? null);
}

/**
 * Returns the authenticated account's session data, or null if none.
 *
 * @return array{cuenta_id:int,rol:string,ref_id:int,nombre:string}|null
 */
function usuario_actual(): ?array
{
    if (empty($_SESSION['cuenta_id'])) {
        return null;
    }
    return [
        'cuenta_id' => (int) $_SESSION['cuenta_id'],
        'rol'       => (string) $_SESSION['rol'],
        'ref_id'    => (int) $_SESSION['ref_id'],
        'nombre'    => (string) $_SESSION['nombre'],
    ];
}
