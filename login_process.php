<?php
// Forzar ausencia de BOM / espacios previos y activar control de errores a log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

require_once 'session_init.php';
require_once 'conexion.php';

// Evitar doble envío de headers si ya falló la conexión
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Buffer de salida para evitar que warnings rompan el JSON
ob_start();

function flush_json($data){
    // Limpia cualquier salida previa (warnings) y devuelve JSON puro
    $buffer = ob_get_clean();
    if ($buffer !== '') {
        // Loguear lo que se haya colado
        error_log('[login_process][buffer_previo] ' . substr($buffer,0,500));
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Captura de errores fatales para devolver JSON en caso de 500 silencioso
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Si no se envió salida JSON (buffer aún no limpiado)
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        // Limpiar buffer previo
        if (ob_get_level() > 0) { ob_end_clean(); }
        $safeMsg = $e['message'];
        error_log('[login_process][FATAL] ' . $safeMsg . ' in ' . $e['file'] . ':' . $e['line']);
        echo json_encode([
            'error' => 'Error interno del servidor (fatal)',
            'code' => 'FATAL_LOGIN',
            'detail' => $safeMsg
        ], JSON_UNESCAPED_UNICODE);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flush_json(['error' => 'Método no permitido']);
}

// Obtener datos del formulario
$usuario = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validaciones básicas
if (empty($usuario) || empty($password)) {
    http_response_code(400);
    flush_json(['error' => 'Email y contraseña son requeridos']);
}

// Validar que sea un email válido
if (filter_var($usuario, FILTER_VALIDATE_EMAIL) === false) {
    http_response_code(400);
    flush_json(['error' => 'Debes ingresar un email válido']);
}

// Verificar que la conexión a la base de datos esté disponible
if (!isset($GLOBALS['BG03_DB_CONNECTION_OK']) || $GLOBALS['BG03_DB_CONNECTION_OK'] !== true || !isset($conn) || !$conn) {
    http_response_code(503);
    flush_json(['error' => 'Servicio temporalmente no disponible (DB offline)']);
}

// Buscar usuario en la base de datos
// Loguear la base de datos en uso para depurar "no veo la fila en phpMyAdmin"
try {
    if (isset($conn) && $conn instanceof mysqli) {
        if ($resDb = @$conn->query('SELECT DATABASE() AS db')) {
            $rowDb = $resDb->fetch_assoc();
            error_log('[login_process] DB en uso=' . ($rowDb['db'] ?? 'null'));
        }
    }
} catch (Throwable $e) { /* silencioso */ }

$sql = "SELECT u.id_usuario, u.email_usuario, u.nombre_usuario, u.rol, al.contrasena_usuario 
        FROM usuarios u 
        INNER JOIN autenticacion_local al ON u.id_usuario = al.id_usuario 
        WHERE u.email_usuario = ? LIMIT 1";
error_log('[login_process] Iniciando consulta usuario=' . $usuario);
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    flush_json(['error' => 'Error interno (prepare usuarios)', 'detail' => $conn->error]);
}
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
error_log('[login_process] Resultado filas=' . $result->num_rows);

if ($result->num_rows !== 1) {
    http_response_code(401);
    flush_json(['error' => 'Email o contraseña incorrectos']);
}

$row = $result->fetch_assoc();

// Verificar contraseña
if (!password_verify($password, $row['contrasena_usuario'])) {
    http_response_code(401);
    flush_json(['error' => 'Email o contraseña incorrectos']);
}

// Establecer sesión
$_SESSION['usuario'] = $row['nombre_usuario'];
$_SESSION['user_id'] = $row['id_usuario'];
$_SESSION['email'] = $row['email_usuario'];
$_SESSION['rol'] = $row['rol'] ?? 'paciente';
$_SESSION['last_activity'] = time();
error_log('[login_process] Sesión establecida para user_id=' . $row['id_usuario']);

// Verificar ESP sólo para pacientes (SIEMPRE verificar en BD, no cache)
if ($row['rol'] === 'paciente') {
    // Forzar verificación en BD para cada login (sin cache)
    unset($_SESSION['esp_info']); // Limpiar cache de ESP
    unset($_SESSION['needs_esp32']); // Limpiar estado previo
    
    // Esta función marca $_SESSION['needs_esp32'] true/false según si ya tiene ESP vinculado
    asociarEspSiNoExiste($row['id_usuario'], $row['nombre_usuario']);
    
    error_log('[login_process] Verificación ESP para user_id=' . $row['id_usuario'] . 
              ' resultado needs_esp32=' . ($_SESSION['needs_esp32'] ? 'true' : 'false'));
    
    // No hacer asignación automática - el usuario debe vincular manualmente en vincular_esp.php
} else {
    // Cuidadores nunca necesitan ESP
    $_SESSION['needs_esp32'] = false;
}

// Cookie de "recordarme" (opcional)
if ($remember) {
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Exception $ex) {
        // Fallback simple si random_bytes falla (muy raro)
        $token = bin2hex(substr(hash('sha256', microtime(true) . mt_rand()), 0, 32));
    }
    $expiry = time() + 60 * 60 * 24 * 30; // 30 días
    
    // Configurar cookie con flags adecuados (usar secure sólo en HTTPS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    @setcookie('remember_token', $token, [
        'expires' => $expiry,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Guardar token en la base de datos
    try {
        // Primero eliminar tokens anteriores del usuario
        $sql = "DELETE FROM password_reset_tokens WHERE user_id = ? AND token LIKE 'remember_%'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // id_usuario es CHAR(6), usar tipo string
            $stmt->bind_param("s", $row['id_usuario']);
            $stmt->execute();
        }
        
        // Insertar nuevo token de recordarme
        $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $rememberToken = 'remember_' . $token;
            // user_id es CHAR(6): usar 's' en lugar de 'i'
            $stmt->bind_param("sss", $row['id_usuario'], $rememberToken, date('Y-m-d H:i:s', $expiry));
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Si hay error, simplemente continuar sin recordarme
    }
}

// Determinar redirección según el rol y si tiene ESP vinculado
if ($row['rol'] === 'cuidador') {
    $redirect = 'dashboard_cuidador.php';
    error_log('[login_process] Cuidador -> dashboard_cuidador.php');
} else if ($row['rol'] === 'paciente') {
    // Verificar si tiene ESP vinculado
    if (empty($_SESSION['needs_esp32'])) {
        // Usuario YA TIENE ESP vinculado -> Dashboard directo
        $redirect = 'dashboard.php';
        error_log('[login_process] Paciente CON ESP vinculado -> dashboard.php');
    } else {
        // Usuario NO TIENE ESP vinculado -> Vincular primero
        $_SESSION['post_link_redirect'] = 'dashboard.php';
        $redirect = 'vincular_esp.php';
        error_log('[login_process] Paciente SIN ESP vinculado -> vincular_esp.php');
    }
} else {
    // Rol por defecto
    $redirect = 'dashboard.php';
    error_log('[login_process] Rol desconocido -> dashboard.php por defecto');
}

error_log('[login_process] Login exitoso - Usuario: ' . $row['nombre_usuario'] . 
          ' (ID: ' . $row['id_usuario'] . ') -> ' . $redirect);

// Respuesta exitosa
flush_json([
    'success' => true,
    'redirect' => $redirect,
    'message' => 'Inicio de sesión exitoso'
]);
?> 