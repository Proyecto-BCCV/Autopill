<?php
/* =============================================================
    CONFIGURACION DE BASE DE DATOS (elige UNA forma)

    ORDEN DE PRIORIDAD (si existe algo arriba, ya no hace falta tocar abajo):
        1. Variables de entorno: BG03_DB_HOST, BG03_DB_USER, BG03_DB_PASS, BG03_DB_NAME
        2. Bloques manuales (LOCAL / COLEGIO) comentando / descomentando
        3. Fallbacks automáticos (localhost root sin pass)

    A) VARIABLE DE ENTORNO (recomendada producción):
         set BG03_DB_HOST=...
         (Si están definidas, ignoran lo que pongas en los bloques manuales.)

    B) BLOQUES MANUALES: Descomenta SOLO UN bloque (LOCAL o COLEGIO).
         El otro debe quedar comentado para evitar confusiones.

    C) SI TODO FALLA: Se intentará localhost/root sin contraseña con base bg03.

    Nota: Este archivo se limita a escoger credenciales. La lógica de reconexión
    y fallback está más abajo (lista de $candidates). Si agregas nuevos entornos,
    solo añade otra entrada ahí o modifica los bloques de arriba.
============================================================= */

// --- BLOQUE LOCAL (descomenta para usar en tu PC) ---
// $DB_HOST = 'localhost';
// $DB_USER = 'root';
// $DB_PASS = '';
// $DB_NAME = 'bg03';

// --- BLOQUE COLEGIO (descomenta para usar en la red del colegio) ---
$DB_HOST = '192.168.101.93';
$DB_USER = 'BG03';
$DB_PASS = 'St2025#QkcwMw';
$DB_NAME = 'bg03';

// Si NO se han definido arriba manualmente, intentar tomar variables de entorno o defaults COLEGIO.
if (!isset($DB_HOST)) { $DB_HOST = getenv('BG03_DB_HOST') ?: '192.168.101.93'; }
if (!isset($DB_USER)) { $DB_USER = getenv('BG03_DB_USER') ?: 'BG03'; }
if (!isset($DB_PASS)) { $DB_PASS = getenv('BG03_DB_PASS') ?: 'St2025#QkcwMw'; }
if (!isset($DB_NAME)) { $DB_NAME = getenv('BG03_DB_NAME') ?: 'bg03'; }

// Función helper: intenta conectar con timeouts cortos y devuelve mysqli o null
function bg03_try_connect($host, $user, $pass, $name) {
    $m = mysqli_init();
    if (!$m) return null;
    @mysqli_options($m, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @mysqli_options($m, MYSQLI_OPT_READ_TIMEOUT, 5);
    if (@mysqli_real_connect($m, $host, $user, $pass, $name)) {
        return $m;
    }
    return null;
}

// Candidatos de conexión, en orden
$candidates = [
    // Preferir lo configurado explícitamente (entorno o valores por defecto)
    [$DB_HOST, $DB_USER, $DB_PASS, $DB_NAME],
    // Fallbacks comunes locales
    ['localhost', 'root', '', 'bg03'],
    ['127.0.0.1', 'root', '', 'bg03'],
];

$conn = null;
$lastError = null;
// Bandera global para que otros scripts detecten fallo de conexión (sin depender de salida JSON)
$GLOBALS['BG03_DB_CONNECTION_OK'] = false;
foreach ($candidates as $cfg) {
    [$h, $u, $p, $n] = $cfg;
    $mysqli = bg03_try_connect($h, $u, $p, $n);
    if ($mysqli) {
        $conn = $mysqli;
        // Exponer las credenciales efectivas por si las necesita otro código
        $DB_HOST = $h; $DB_USER = $u; $DB_PASS = $p; $DB_NAME = $n;
        break;
    } else {
        $lastError = mysqli_connect_error();
    }
}

if (!$conn) {
    $error = $lastError ?: 'unknown_error';
    // Determinar si el contexto espera JSON (peticiones AJAX / endpoints *_process.php / fetch)
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    $looksLikeApi = preg_match('/(_process|guardar_|actualizar_|eliminar_|obtener_|fix_|test_|monitor_alarmas)/i', $script) ||
                    (strpos($script, 'login_process.php') !== false) ||
                    (strpos($script, 'register_process.php') !== false);
    $wantsJson = $isAjax || $looksLikeApi || stripos($accept, 'application/json') !== false;

    if ($wantsJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo conectar a la base de datos',
            'detail' => $error
        ]);
        exit;
    } else {
        // En páginas HTML no abortar: permitir que la UI cargue sin datos
        $GLOBALS['DB_CONN_ERROR'] = $error;
        $conn = null;
        // No hacer return aquí, continuar para que $conn quede definida como null
    }
}

// Establecer el conjunto de caracteres y modo estricto de MySQLi si se requiere
if ($conn) { 
    mysqli_set_charset($conn, 'utf8mb4');
    // Configurar zona horaria de MySQL a Argentina (UTC-3)
    @$conn->query("SET time_zone = '-03:00'");
    $GLOBALS['BG03_DB_CONNECTION_OK'] = true;
}

// Función helper para obtener conexión en otros scripts
function obtenerConexion() {
    global $conn;
    // Verificar si la conexión existe y sigue activa
    if ($conn) {
        try {
            // En lugar de mysqli_ping(), hacer una consulta simple
            $result = @$conn->query("SELECT 1");
            if ($result) {
                return $conn;
            }
        } catch (Exception $e) {
            // Conexión perdida, reconectar
        }
    }
    
    // Si no hay conexión o se perdió, intentar reconectar
    $DB_HOST = getenv('BG03_DB_HOST') ?: '192.168.101.93';
    $DB_USER = getenv('BG03_DB_USER') ?: 'BG03';
    $DB_PASS = getenv('BG03_DB_PASS') ?: 'St2025#QkcwMw';
    $DB_NAME = getenv('BG03_DB_NAME') ?: 'bg03';
    
    $candidates = [
        [$DB_HOST, $DB_USER, $DB_PASS, $DB_NAME],
        ['localhost', 'root', '', 'bg03'],
        ['127.0.0.1', 'root', '', 'bg03'],
    ];
    
    foreach ($candidates as $cfg) {
        [$h, $u, $p, $n] = $cfg;
        $mysqli = bg03_try_connect($h, $u, $p, $n);
        if ($mysqli) {
            $conn = $mysqli;
            mysqli_set_charset($conn, 'utf8mb4');
            // Configurar zona horaria de MySQL a Argentina (UTC-3)
            @$conn->query("SET time_zone = '-03:00'");
            return $conn;
        }
    }
    
    return null;
}