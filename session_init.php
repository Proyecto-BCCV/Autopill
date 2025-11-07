<?php
// Inicializar sesiones con vida extendida (evitar cierre por inactividad)
if (session_status() === PHP_SESSION_NONE) {
    // 30 días de vida de sesión y cookie
    $SESSION_LIFETIME = 60 * 60 * 24 * 30;
    @ini_set('session.gc_maxlifetime', (string)$SESSION_LIFETIME);
    // Configurar cookie de sesión antes de iniciar
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    if (function_exists('session_set_cookie_params')) {
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            session_set_cookie_params([
                'lifetime' => $SESSION_LIFETIME,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            // Compatibilidad con PHP < 7.3: firma antigua
            session_set_cookie_params($SESSION_LIFETIME, '/');
        }
    }
    session_start();
}

// Configurar zona horaria a Argentina (UTC-3)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Bandera de depuración global (apagada por defecto)
if (!defined('AP_DEBUG')) { define('AP_DEBUG', false); }

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    // Verificar si existe la sesión del usuario
    if (isset($_SESSION['usuario']) && !empty($_SESSION['usuario'])) {
        // Expiración por inactividad deshabilitada: mantener sesión activa
        // Si se desea activar en el futuro, ajustar $timeout > 0
        $timeout = 0; // segundos; 0 = sin límite por inactividad
        if ($timeout > 0 && isset($_SESSION['last_activity']) && (time() - ($_SESSION['last_activity'] ?? 0) > $timeout)) {
            session_destroy();
            return false;
        }
        // Actualizar tiempo de última actividad
        $_SESSION['last_activity'] = time();
        // Actualizar last_seen del usuario autenticado para presencia
        try {
            if (isset($_SESSION['user_id'])) {
                require_once 'conexion.php';
                if (isset($conn) && $conn instanceof mysqli) {
                    $stmtLS = $conn->prepare("UPDATE usuarios SET last_seen = NOW() WHERE id_usuario = ?");
                    if ($stmtLS) {
                        $uid = $_SESSION['user_id'];
                        $stmtLS->bind_param('s', $uid);
                        $stmtLS->execute();
                        $stmtLS->close();
                    } else {
                        if (AP_DEBUG) error_log("[session_init] Error preparando UPDATE last_seen: " . $conn->error);
                    }
                }
            }
        } catch (Throwable $e) { 
            if (AP_DEBUG) error_log("[session_init] Exception updating last_seen: " . $e->getMessage());
        }
        return true;
    }
    
    // Verificar cookie de "recordarme"
    if (isset($_COOKIE['remember_token'])) {
        try {
            require_once 'conexion.php';
            if (isset($conn) && $conn instanceof mysqli) {
                // Buscar el token en la base de datos
                $sql = "SELECT prt.user_id, u.nombre_usuario, u.email_usuario, u.rol 
                        FROM password_reset_tokens prt 
                        INNER JOIN usuarios u ON prt.user_id = u.id_usuario 
                        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0";
                if ($stmt = @$conn->prepare($sql)) {
                    $rememberToken = 'remember_' . $_COOKIE['remember_token'];
                    @$stmt->bind_param("s", $rememberToken);
                    @$stmt->execute();
                    $result = @$stmt->get_result();
                    if ($result && $result->num_rows === 1) {
                        $row = $result->fetch_assoc();
                        // Establecer sesión desde el token
                        $_SESSION['usuario'] = $row['nombre_usuario'];
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['email'] = $row['email_usuario'];
                        $_SESSION['rol'] = $row['rol'] ?? 'paciente';
                        $_SESSION['last_activity'] = time();
                        
                        // Verificar ESP sólo para pacientes (igual que en login_process.php)
                        if ($row['rol'] === 'paciente') {
                            asociarEspSiNoExiste($row['user_id'], $row['nombre_usuario']);
                        } else {
                            // Cuidadores nunca necesitan ESP
                            $_SESSION['needs_esp32'] = false;
                        }
                        
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Silencioso: si la BD no está disponible, no romper autenticación
        }
        
        // Si el token no es válido, eliminar la cookie
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }
    
    return false;
}

// Determina si un usuario está "activo" (visto en los últimos N segundos)
function isUserActive($userId, $thresholdSeconds = 120){
    try {
        require_once 'conexion.php';
        global $conn;
        if (!$conn) return false;
        // Calcular en la BD para evitar desajustes de zona horaria
        $sql = "SELECT CASE WHEN last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= ? THEN 1 ELSE 0 END AS activo FROM usuarios WHERE id_usuario = ?";
        if (!$stmt = $conn->prepare($sql)) return false;
        $stmt->bind_param('is', $thresholdSeconds, $userId);
        if (!$stmt->execute()) return false;
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()){
            return (int)($row['activo'] ?? 0) === 1;
        }
        return false;
    } catch (Exception $e) { return false; }
}

// Función para obtener el nombre del usuario
function getUserName() {
    if (isAuthenticated()) {
        return $_SESSION['usuario'] ?? '';
    }
    return '';
}

// Función para obtener el ID del usuario
function getUserId() {
    if (isAuthenticated()) {
        return $_SESSION['user_id'] ?? null;
    }
    return null;
}

// Función para obtener el email del usuario
function getUserEmail() {
    if (isAuthenticated()) {
        return $_SESSION['email'] ?? '';
    }
    return '';
}

// Función para obtener el rol del usuario
function getUserRole() {
    if (isAuthenticated()) {
        return $_SESSION['rol'] ?? '';
    }
    return '';
}

// Función para verificar si el usuario es cuidador
function isCuidador() {
    return getUserRole() === 'cuidador';
}

// Función para verificar si el usuario es paciente
function isPaciente() {
    return getUserRole() === 'paciente';
}

// Recuperar información del ESP asociado al usuario (cacheada en sesión)
function obtenerEspAsignado($id_usuario = null, $forceRefresh = false) {
    // Asegurar acceso a la conexión global o reconectar si es necesario
    global $conn;
    if ($id_usuario === null) {
        $id_usuario = getUserId();
    }
    if (!$id_usuario) {
        return null;
    }

    if (!$forceRefresh && isset($_SESSION['esp_info']) && ($_SESSION['esp_info']['id_usuario'] ?? null) === $id_usuario) {
        return $_SESSION['esp_info'];
    }

    try {
        // Si no hay conexión disponible en este scope, intentar obtenerla
        if (!isset($conn) || !$conn) {
            require_once 'conexion.php';
            if (!isset($conn) || !$conn) {
                // último intento: función helper
                if (function_exists('obtenerConexion')) {
                    $conn = obtenerConexion();
                }
                if (!$conn) {
                    return null;
                }
            }
        }
        $tryIds = [$id_usuario];
        // Si el ID tiene ceros a la izquierda, intentar también sin ellos
        if (preg_match('/^0[0-9]+$/', $id_usuario)) {
            $stripped = ltrim($id_usuario, '0');
            if ($stripped === '') $stripped = '0';
            if (!in_array($stripped, $tryIds, true)) $tryIds[] = $stripped;
        }
        // Intentar versión padded a 6 si es numérico corto
        if (ctype_digit($id_usuario) && strlen($id_usuario) < 6) {
            $padded = str_pad($id_usuario, 6, '0', STR_PAD_LEFT);
            if (!in_array($padded, $tryIds, true)) $tryIds[] = $padded;
        }
        $logged = false;
        foreach ($tryIds as $candidateId) {
            if ($stmt = @$conn->prepare('SELECT id_esp, id_usuario, modulos_conectados_esp, nombre_esp FROM codigos_esp WHERE id_usuario = ? LIMIT 1')) {
                @$stmt->bind_param('s', $candidateId);
                if (@$stmt->execute()) {
                    $res = @$stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        if (!$logged) {
                            if (defined('AP_DEBUG') && AP_DEBUG) {
                                error_log('[obtenerEspAsignado] MATCH id_sesion=' . $id_usuario . ' candidate=' . $candidateId . ' nombre_esp=' . ($row['nombre_esp'] ?? 'null'));
                            }
                            $logged = true;
                        }
                        $_SESSION['esp_info'] = $row;
                        $_SESSION['esp_codigo'] = $row['nombre_esp'] ?? null;
                        $_SESSION['needs_esp32'] = false;
                        $_SESSION['esp_vinculado_once'] = true; // marcar que alguna vez vinculó
                        return $row;
                    }
                } else if (!$logged) {
                    if (defined('AP_DEBUG') && AP_DEBUG) {
                        error_log('[obtenerEspAsignado] FAIL execute para candidate=' . $candidateId);
                    }
                }
            }
            if (!$logged) {
                if (defined('AP_DEBUG') && AP_DEBUG) {
                    error_log('[obtenerEspAsignado] Sin coincidencia para candidate=' . $candidateId);
                }
            }
        }
        if (!$logged) {
            if (defined('AP_DEBUG') && AP_DEBUG) {
                error_log('[obtenerEspAsignado] Sin ESP para id final=' . $id_usuario . ' variantes=' . implode(',', $tryIds));
            }
        }
    } catch (Throwable $e) {
        // Silencioso: los errores se loguean vía conexion.php
    }

    $_SESSION['esp_info'] = null;
    return null;
}

function usuarioTieneEsp($id_usuario = null, $forceRefresh = false) {
    return obtenerEspAsignado($id_usuario, $forceRefresh) !== null;
}

// Verificar si el cuidador autenticado puede gestionar al paciente indicado
function canManagePaciente($pacienteId) {
    if (!$pacienteId || !isCuidador()) return false;
    $pacienteId = trim($pacienteId);
    // No permitir que un cuidador pase su propio id como paciente
    if ($pacienteId === getUserId()) return false;
    try {
        require_once 'conexion.php';
        global $conn;
        if (!$conn) return false;
        $sql = "SELECT 1 FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = 'activo' LIMIT 1";
        if (!$stmt = $conn->prepare($sql)) return false;
        $cid = getUserId();
        $stmt->bind_param('ss', $cid, $pacienteId);
        if (!$stmt->execute()) return false;
        $stmt->store_result();
        if ($stmt->num_rows === 1) return true;
        // Fallback: intentar sin ceros a la izquierda (IDs numéricos) si aplica
        if (preg_match('/^0[0-9]+$/', $pacienteId)) {
            $alt = ltrim($pacienteId, '0');
            if ($alt === '') $alt = '0';
            if ($alt !== $pacienteId) {
                if ($stmt2 = $conn->prepare($sql)) {
                    $stmt2->bind_param('ss', $cid, $alt);
                    if ($stmt2->execute()) {
                        $stmt2->store_result();
                        if ($stmt2->num_rows === 1) return true;
                    }
                }
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Función para redirigir si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

// Función para redirigir si no es cuidador
function requireCuidador() {
    requireAuth();
    if (!isCuidador()) {
        header('Location: index.php');
        exit;
    }
}

// Función para limpiar datos de entrada
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Configurar headers de seguridad básicos
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// (asociarEspSiNoExiste original restaurada)
function asociarEspSiNoExiste($id_usuario, $nombre_usuario) {
    $id_usuario = $id_usuario ?: getUserId();
    if (!$id_usuario) {
        return false;
    }

    if (isCuidador()) {
        $_SESSION['needs_esp32'] = false;
        return true;
    }

    // Verificar realmente si el usuario tiene ESP en BD (sin usar cache)
    if (usuarioTieneEsp($id_usuario, true)) { // forzar refresh
        $_SESSION['needs_esp32'] = false;
        $_SESSION['esp_vinculado_once'] = true; // persistimos la marca solo si realmente tiene ESP
        error_log('[asociarEspSiNoExiste] Usuario ' . $id_usuario . ' SÍ tiene ESP vinculado');
        return true;
    }

    // Usuario no tiene ESP - necesita vincularlo
    $_SESSION['needs_esp32'] = true;
    $_SESSION['esp_vinculado_once'] = false; // asegurar que esté en false
    if (!isset($_SESSION['post_link_redirect'])) {
        $_SESSION['post_link_redirect'] = isCuidador() ? 'dashboard_cuidador.php' : 'dashboard.php';
    }
    error_log('[asociarEspSiNoExiste] Usuario ' . $id_usuario . ' NO tiene ESP vinculado - needs_esp32=true');
    return false;
}

// Nota: Se omite la etiqueta de cierre PHP intencionalmente para evitar espacios/lineas accidentales en output