<?php
/**
 * report_alarm_execution.php
 * Endpoint para que el ESP32 reporte ejecuciones de alarmas
 * Registra en la tabla esp32_alarm_exec y esp32_eventos
 */

// Configurar zona horaria Argentina (UTC-3)
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once 'conexion.php';
require_once 'notificaciones_dispensado_utils.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Log de debugging
function logDebug($message) {
    error_log("[report_alarm_execution] " . $message);
}

// Helper para verificar si existe una tabla (evita fallos cuando se eliminaron tablas IOT)
function tableExists($conn, $tableName) {
    if (!($conn instanceof mysqli)) return false;
    try {
        $t = $conn->real_escape_string($tableName);
        $res = $conn->query("SHOW TABLES LIKE '$t'");
        return $res && $res->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Validación de método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    exit;
}

// Obtener datos del POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logDebug("Error decodificando JSON: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON', 'success' => false]);
    exit;
}

// Validar API key (aceptar por header X-API-Key o en JSON/GET)
$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
$apiKey = $_GET['api_key'] ?? $data['api_key'] ?? $apiKeyHeader;
if ($apiKey !== 'esp32_alarm_2024_secure_key_987654321') {
    logDebug("API Key inválida");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

// Extraer parámetros
$deviceCode = $data['device_code'] ?? ($data['device_id'] ?? '');
$alarmId = (int)($data['alarm_id'] ?? 0);
$executed = (bool)($data['executed'] ?? false);
$moduleNum = (int)($data['module_num'] ?? 0);
$timestamp = $data['timestamp'] ?? time() * 1000; // millis
$executionTime = $data['execution_time'] ?? date('H:i:s');

logDebug("========================================");
logDebug("REPORTE DE EJECUCIÓN RECIBIDO");
logDebug("Device: $deviceCode");
logDebug("Alarm ID: $alarmId");
logDebug("Executed: " . ($executed ? 'SÍ' : 'NO'));
logDebug("Module: $moduleNum");
logDebug("Execution Time: $executionTime");
logDebug("Raw Data: " . json_encode($data));
logDebug("========================================");

// Validar parámetros requeridos: permitir deviceCode vacío si podemos inferir desde la alarma
if (!$alarmId) {
    logDebug("Parámetros faltantes - Alarm ID vacío");
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters', 'success' => false]);
    exit;
}

// Obtener conexión
$conn = obtenerConexion();
if (!$conn) {
    logDebug("Error de conexión a BD");
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'success' => false]);
    exit;
}

try {
    // Iniciar transacción
    $conn->autocommit(false);
    
    // Resolver espId desde deviceCode o alarma
    $espId = null;
    
    if ($deviceCode) {
        $espStmt = $conn->prepare("SELECT id_esp FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
        $espStmt->bind_param("s", $deviceCode);
        $espStmt->execute();
        $espResult = $espStmt->get_result();
        if ($espRow = $espResult->fetch_assoc()) {
            $espId = (int)$espRow['id_esp'];
            logDebug("ESP encontrado por device_code - ID: $espId");
        }
        $espStmt->close();
    }

    if ($espId === null) {
        // Inferir por la alarma
        $stmtA = $conn->prepare("SELECT id_esp_alarma, nombre_alarma FROM alarmas WHERE id_alarma = ? LIMIT 1");
        $stmtA->bind_param('i', $alarmId);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        if ($rowA = $resA->fetch_assoc()) {
            $espId = (int)$rowA['id_esp_alarma'];
            // Si no vino moduleNum, intentar parsearlo del nombre
            if (!$moduleNum || $moduleNum <= 0) {
                $nom = $rowA['nombre_alarma'] ?? '';
                if (preg_match('/M[óo]dulo\s*(\d+)/ui', $nom, $m)) {
                    $moduleNum = (int)$m[1];
                }
                if (!$moduleNum) { $moduleNum = 1; }
            }
            // Recuperar deviceCode si podemos
            $stmtE = $conn->prepare("SELECT nombre_esp FROM codigos_esp WHERE id_esp = ? LIMIT 1");
            $stmtE->bind_param('i', $espId);
            $stmtE->execute();
            $resE = $stmtE->get_result();
            if ($rowE = $resE->fetch_assoc()) { $deviceCode = $rowE['nombre_esp']; }
            $stmtE->close();
            logDebug("ESP inferido por alarma - ID: $espId, módulo: $moduleNum");
        } else {
            throw new Exception("No se pudo inferir ESP desde la alarma $alarmId");
        }
        $stmtA->close();
    }
    
    if (!$espId) {
        throw new Exception("No se pudo determinar el ESP");
    }
    
    // Calcular exec_minute (minuto del día 0-1439)
    $execMinute = calculateMinuteOfDay($executionTime);
    
    // Si la ejecución fue exitosa, registrar todo
    if ($executed) {
        // Registrar ejecución exitosa en esp32_alarm_exec (si existe la tabla)
        if (tableExists($conn, 'esp32_alarm_exec')) {
            $execStmt = $conn->prepare("
                INSERT INTO esp32_alarm_exec (id_esp, id_alarma, exec_minute, ejecutado_en) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE ejecutado_en = NOW()
            ");
            if ($execStmt) {
                $execStmt->bind_param("iii", $espId, $alarmId, $execMinute);
                if (!$execStmt->execute()) {
                    logDebug("WARN: Error registrando ejecución: " . $execStmt->error);
                }
                $execStmt->close();
                logDebug("Ejecución registrada en esp32_alarm_exec - Minuto: $execMinute");
            } else {
                logDebug("WARN: No se pudo preparar insert en esp32_alarm_exec: " . $conn->error);
            }
        } else {
            logDebug("INFO: Tabla esp32_alarm_exec no existe; se omite log de ejecución");
        }
        
        // Crear notificación para el usuario sobre pastilla dispensada
        logDebug(">>> INTENTANDO CREAR NOTIFICACIÓN <<<");
        logDebug("ESP ID: $espId, Alarm ID: $alarmId, Module: $moduleNum");
        try {
            // VALIDACIONES antes de crear la notificación:
            
            // 1. Verificar que la alarma existe
            $stmtValidate = $conn->prepare("
                SELECT hora_alarma, dias_semana 
                FROM alarmas 
                WHERE id_alarma = ? 
                LIMIT 1
            ");
            $stmtValidate->bind_param("i", $alarmId);
            $stmtValidate->execute();
            $validateResult = $stmtValidate->get_result();
            
            if (!$validateRow = $validateResult->fetch_assoc()) {
                logDebug("❌ Alarma ID $alarmId no existe - no se creará notificación");
                $stmtValidate->close();
                throw new Exception("Alarma no existe");
            }
            
            $alarmTime = $validateRow['hora_alarma'];
            $diasSemana = $validateRow['dias_semana'];
            $stmtValidate->close();
            
            // 1b. Verificar que hoy sea un día configurado
            if (!isDayActive($diasSemana)) {
                logDebug("📅 Hoy no es día configurado - Días: $diasSemana, Hoy: " . date('w'));
                throw new Exception("Día no configurado");
            }
            
            // 2. Verificar ventana de tiempo (4.5 minutos desde la hora de alarma)
            if (!isWithinAlarmWindow($alarmTime, 270)) {
                logDebug("⏰ Fuera de ventana - Alarma: $alarmTime, Ahora: " . date('H:i:s'));
                throw new Exception("Fuera de ventana de tiempo");
            }
            
            // 3. Obtener userId para verificar duplicados
            $userStmt = $conn->prepare("SELECT id_usuario FROM codigos_esp WHERE id_esp = ? LIMIT 1");
            $userStmt->bind_param("i", $espId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if (!$userRow = $userResult->fetch_assoc()) {
                logDebug("❌ No se encontró usuario para ESP ID: $espId");
                $userStmt->close();
                throw new Exception("Usuario no encontrado");
            }
            
            $userId = $userRow['id_usuario'];
            $userStmt->close();
            
            // 4. Verificar que NO exista ya una notificación reciente
            if (notificationRecentlyExists($conn, $userId, $alarmId, $moduleNum, 270)) {
                logDebug("✅ Ya existe notificación reciente - no duplicar");
                throw new Exception("Notificación ya existe");
            }
            
            // TODAS las validaciones pasaron - crear notificación
            createPillDispensedNotification($conn, $espId, $alarmId, $moduleNum);
            logDebug("✅ Notificación de pastilla dispensada creada EXITOSAMENTE para módulo $moduleNum");
        } catch (Exception $notifError) {
            logDebug("ℹ️  No se creó notificación: " . $notifError->getMessage());
            // No interrumpir el proceso por error de notificación
        }

        // Decrementar la cantidad de pastillas del módulo (si la columna existe)
        try {
            asegurarColumnaCantidadPastillas($conn);
            // Obtener usuario dueño del ESP32 (quien tiene los módulos)
            $usrStmt = $conn->prepare("SELECT id_usuario FROM codigos_esp WHERE id_esp = ? LIMIT 1");
            if ($usrStmt) {
                $usrStmt->bind_param('i', $espId);
                $usrStmt->execute();
                $usrRes = $usrStmt->get_result();
                if ($usrRow = $usrRes->fetch_assoc()) {
                    $ownerId = $usrRow['id_usuario'];
                    // Hacer decremento atómico con piso 0
                    $upd = $conn->prepare("UPDATE modulos SET cantidad_pastillas_modulo = CASE WHEN cantidad_pastillas_modulo IS NULL THEN NULL WHEN cantidad_pastillas_modulo > 0 THEN cantidad_pastillas_modulo - 1 ELSE 0 END WHERE id_usuario = ? AND numero_modulo = ?");
                    if ($upd) {
                        $upd->bind_param('si', $ownerId, $moduleNum);
                        $upd->execute();
                        logDebug("Stock de módulo $moduleNum decrementado para usuario $ownerId");
                    }
                }
            }
        } catch (Exception $eDec) {
            logDebug("No se pudo decrementar stock: " . $eDec->getMessage());
        }
    }
    
    // Registrar evento en esp32_eventos (si existe la tabla)
    if (tableExists($conn, 'esp32_eventos')) {
        $eventType = $executed ? 'alarm_executed' : 'alarm_execution_failed';
        $description = $executed ? 
            "Alarma ejecutada exitosamente en módulo $moduleNum" : 
            "Fallo al ejecutar alarma en módulo $moduleNum";
        
        $eventData = json_encode([
            'alarm_id' => $alarmId,
            'module_num' => $moduleNum,
            'execution_time' => $executionTime,
            'device_timestamp' => $timestamp,
            'success' => $executed
        ], JSON_UNESCAPED_UNICODE);
        
        $eventStmt = $conn->prepare("
            INSERT INTO esp32_eventos (id_esp, tipo_evento, descripcion, datos_json, timestamp_evento) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        if ($eventStmt) {
            $eventStmt->bind_param("isss", $espId, $eventType, $description, $eventData);
            if (!$eventStmt->execute()) {
                logDebug("WARN: Error registrando evento: " . $eventStmt->error);
            }
            $eventStmt->close();
        } else {
            logDebug("WARN: No se pudo preparar insert en esp32_eventos: " . $conn->error);
        }
    } else {
        logDebug("INFO: Tabla esp32_eventos no existe; se omite log de evento");
    }
    
    $conn->commit(); // Confirmar transacción
    
    logDebug("Evento registrado - Tipo: " . ($executed ? 'alarm_executed' : 'alarm_execution_failed'));
    
    // Obtener información adicional de la alarma para la respuesta
    $alarmInfo = getAlarmInfo($conn, $alarmId);
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => $executed ? 'Ejecución registrada exitosamente' : 'Fallo de ejecución registrado',
        'data' => [
            'device_code' => $deviceCode,
            'esp_id' => $espId,
            'alarm_id' => $alarmId,
            'module_num' => $moduleNum,
            'executed' => $executed,
            'execution_time' => $executionTime,
            'exec_minute' => $execMinute,
            'alarm_info' => $alarmInfo
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback(); // Revertir transacción en caso de error
    
    logDebug("Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug_message' => $e->getMessage(),
        'device_code' => $deviceCode,
        'alarm_id' => $alarmId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} finally {
    if (isset($conn) && $conn) {
        $conn->autocommit(true); // Restaurar autocommit
        $conn->close();
    }
}

// Reutilizamos helper para asegurar columna de stock en modulos
function asegurarColumnaCantidadPastillas($conn) {
    try {
        if (!($conn instanceof mysqli)) return;
        $res = $conn->query("SHOW COLUMNS FROM modulos LIKE 'cantidad_pastillas_modulo'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE modulos ADD COLUMN cantidad_pastillas_modulo INT DEFAULT NULL AFTER nombre_medicamento");
        }
    } catch (Exception $e) {
        // Silencioso
    }
}

/**
 * Calcula el minuto del día (0-1439) desde una hora
 */
function calculateMinuteOfDay($timeString) {
    $parts = explode(':', $timeString);
    $hour = (int)($parts[0] ?? 0);
    $minute = (int)($parts[1] ?? 0);
    
    return ($hour * 60) + $minute;
}

/**
 * Obtiene información adicional de la alarma
 */
function getAlarmInfo($conn, $alarmId) {
    try {
        $stmt = $conn->prepare("
            SELECT nombre_alarma, hora_alarma, dias_semana, modificado_por 
            FROM alarmas 
            WHERE id_alarma = ? 
            LIMIT 1
        ");
        $stmt->bind_param("i", $alarmId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'nombre' => $row['nombre_alarma'],
                'hora' => $row['hora_alarma'],
                'dias' => $row['dias_semana'],
                'usuario' => $row['modificado_por']
            ];
        }
        
        $stmt->close();
        return null;
        
    } catch (Exception $e) {
        logDebug("Error obteniendo info de alarma: " . $e->getMessage());
        return null;
    }
}
?>