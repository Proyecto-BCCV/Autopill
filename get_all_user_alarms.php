<?php
/**
 * get_all_user_alarms.php
 * Endpoint optimizado para ESP32 - Obtiene TODAS las alarmas de un usuario
 * Compatible con la estructura real de la base de datos bg03
 */

// CRÍTICO: Configuración para que SOLO salga JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');  // DESHABILITAR logs completamente para ESP32

// CRÍTICO: Limpiar TODOS los buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once 'conexion.php';

// CRÍTICO: Limpiar output de conexion.php
ob_end_clean();
ob_start();

// Headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Log de debugging - DESHABILITADO para ESP32
function logDebug($message) {
    // COMENTADO TEMPORALMENTE - Los logs están interfiriendo con JSON
    // error_log("[get_all_user_alarms] " . $message);
}

// Validación de parámetros
$apiKey = $_GET['api_key'] ?? '';
$userId = $_GET['user_id'] ?? '';

logDebug("=================================================================");
logDebug("VERSIÓN ACTUALIZADA - DETECCIÓN MEJORADA DE MÓDULOS v2.0");
logDebug("=================================================================");
logDebug("Recibida petición - API Key: " . substr($apiKey, 0, 10) . "... - User ID: $userId");

// Validar API key
if ($apiKey !== 'esp32_alarm_2024_secure_key_987654321') {
    logDebug("API Key inválida");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

// Validar user_id
if (!$userId) {
    logDebug("User ID faltante");
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id parameter', 'success' => false]);
    exit;
}

// Obtener conexión
try {
    $conn = obtenerConexion();
    if (!$conn) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
    logDebug("Conexión a BD establecida correctamente");
} catch (Exception $e) {
    logDebug("Error de conexión a BD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage(), 'success' => false]);
    exit;
}

try {
    // Consulta optimizada para obtener TODAS las alarmas del usuario
    // Incluye datos del ESP asociado para verificación
    $sql = "
        SELECT 
            a.id_alarma,
            a.nombre_alarma,
            a.hora_alarma,
            a.dias_semana,
            a.id_esp_alarma,
            a.modificado_por,
            a.fecha_creacion,
            a.fecha_actualizacion,
            e.nombre_esp,
            e.id_usuario as esp_usuario,
            e.modulos_conectados_esp,
            e.validado_fisicamente
        FROM alarmas a
        LEFT JOIN codigos_esp e ON a.id_esp_alarma = e.id_esp
        WHERE (a.modificado_por = ? OR e.id_usuario = ?)
        ORDER BY a.hora_alarma ASC
    ";
    
    logDebug("Ejecutando consulta SQL para usuario: $userId");
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $userId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $alarms = [];
    $currentTime = date('H:i:s');
    $currentDay = date('N'); // 1=Lunes, 7=Domingo
    
    logDebug("Procesando resultados - Día actual: $currentDay - Hora actual: $currentTime");
    
    while ($row = $result->fetch_assoc()) {
        // Convertir día actual al formato de dias_semana (0=Lunes, 6=Domingo)
        $dayIndex = ($currentDay == 7) ? 6 : $currentDay - 1;
        
        // Verificar si la alarma está activa hoy
        $diasSemana = $row['dias_semana'] ?: '1111111';
        $isActiveToday = (strlen($diasSemana) > $dayIndex) ? ($diasSemana[$dayIndex] === '1') : true;
        
        // Determinar módulo desde el nombre de la alarma
        $moduleNum = detectModuleFromAlarmName($row['nombre_alarma']);
        
        $alarm = [
            'id_alarma' => (int)$row['id_alarma'],
            'nombre_alarma' => $row['nombre_alarma'] ?: 'Sin nombre',
            'hora_alarma' => $row['hora_alarma'] ?: '00:00:00',
            'dias_semana' => $diasSemana,
            'id_esp_alarma' => (int)$row['id_esp_alarma'],
            'modificado_por' => $row['modificado_por'],
            'esp_nombre' => $row['nombre_esp'],
            'esp_validado' => (bool)$row['validado_fisicamente'],
            'modulo_detectado' => $moduleNum,
            'activa_hoy' => $isActiveToday,
            'es_futura' => ($row['hora_alarma'] > $currentTime),
            'minutos_hasta_alarma' => calculateMinutesUntilAlarm($row['hora_alarma'], $currentTime)
        ];
        
        $alarms[] = $alarm;
        
        logDebug("Alarma procesada - ID: {$row['id_alarma']} - Hora: {$row['hora_alarma']} - Módulo: $moduleNum - Activa hoy: " . ($isActiveToday ? 'SÍ' : 'NO'));
    }
    
    $stmt->close();
    
    // Estadísticas adicionales
    $activeToday = array_filter($alarms, function($alarm) {
        return $alarm['activa_hoy'];
    });
    
    $futureToday = array_filter($activeToday, function($alarm) {
        return $alarm['es_futura'];
    });
    
    // Respuesta final
    $response = [
        'success' => true,
        'user_id' => $userId,
        'alarm_count' => count($alarms),
        'alarms' => $alarms,
        'statistics' => [
            'total_alarms' => count($alarms),
            'active_today' => count($activeToday),
            'future_today' => count($futureToday),
            'modules_detected' => getUniqueModules($alarms)
        ],
        'server_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_time' => $currentTime,
            'current_day' => $currentDay,
            'day_name' => getDayName($currentDay)
        ],
        'debug' => [
            'query_executed' => true,
            'user_searched' => $userId,
            'current_day_index' => $dayIndex
        ]
    ];
    
    logDebug("Respuesta generada - Total alarmas: " . count($alarms) . " - Activas hoy: " . count($activeToday));
    
    // CRÍTICO: Limpiar cualquier output no deseado antes de enviar JSON
    ob_end_clean();
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    logDebug("Error: " . $e->getMessage());
    
    // CRÍTICO: Limpiar buffer en caso de error también
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug_message' => $e->getMessage(),
        'user_id' => $userId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

/**
 * Detecta el número de módulo desde el nombre de la alarma
 */
function detectModuleFromAlarmName($alarmName) {
    $name = strtolower(trim($alarmName));
    
    logDebug("detectModuleFromAlarmName() - Input: '$alarmName'");
    logDebug("detectModuleFromAlarmName() - Lowercase: '$name'");
    
    // MÉTODO 1: Buscar cualquier dígito del 1-5 directamente en el nombre
    for ($i = 1; $i <= 5; $i++) {
        if (strpos($name, (string)$i) !== false) {
            logDebug("detectModuleFromAlarmName() - Encontrado dígito '$i' en el nombre");
            return $i;
        }
    }
    
    // MÉTODO 2 (FALLBACK): Buscar patrones específicos
    $patterns = [
        '/m[óo]dulo\s*(\d+)/i',
        '/module\s*(\d+)/i',
        '/mod\s*(\d+)/i',
        '/compartimento\s*(\d+)/i',
        '/slot\s*(\d+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name, $matches)) {
            $moduleNum = (int)$matches[1];
            if ($moduleNum >= 1 && $moduleNum <= 5) {
                logDebug("detectModuleFromAlarmName() - Patrón '$pattern' encontró: $moduleNum");
                return $moduleNum;
            }
        }
    }
    
    // Si no se encuentra patrón específico, usar módulo 1 por defecto
    logDebug("detectModuleFromAlarmName() - NO se encontró módulo, usando default: 1");
    return 1;
}

/**
 * Calcula minutos hasta la próxima alarma
 */
function calculateMinutesUntilAlarm($alarmTime, $currentTime) {
    $alarmTimestamp = strtotime($alarmTime);
    $currentTimestamp = strtotime($currentTime);
    
    if ($alarmTimestamp <= $currentTimestamp) {
        // Si la alarma ya pasó hoy, calcular para mañana
        $alarmTimestamp += 24 * 60 * 60;
    }
    
    $diffSeconds = $alarmTimestamp - $currentTimestamp;
    return (int)($diffSeconds / 60);
}

/**
 * Obtiene módulos únicos detectados
 */
function getUniqueModules($alarms) {
    $modules = [];
    foreach ($alarms as $alarm) {
        $modules[] = $alarm['modulo_detectado'];
    }
    return array_unique($modules);
}

/**
 * Obtiene nombre del día
 */
function getDayName($dayNum) {
    $days = [
        1 => 'Lunes',
        2 => 'Martes', 
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo'
    ];
    
    return $days[$dayNum] ?? 'Desconocido';
}
?>