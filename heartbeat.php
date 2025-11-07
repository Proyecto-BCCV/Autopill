<?php
// Configurar zona horaria Argentina (UTC-3)
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once 'conexion.php';
require_once 'session_init.php';
require_once 'notificaciones_dispensado_utils.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Para ESP32: verificar API key y código de dispositivo
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $deviceCode = $_GET['code'] ?? $_POST['device_code'] ?? '';
    
    error_log("[heartbeat.php] ========================================");
    error_log("[heartbeat.php] HEARTBEAT RECIBIDO - " . date('Y-m-d H:i:s'));
    error_log("[heartbeat.php] API Key presente: " . (!empty($apiKey) ? 'SÍ' : 'NO'));
    error_log("[heartbeat.php] Device Code: " . ($deviceCode ?: 'VACÍO'));
    error_log("[heartbeat.php] ========================================");
    
    if (!empty($apiKey) && !empty($deviceCode)) {
        // Modo ESP32
        $validApiKey = 'esp32_alarm_2024_secure_key_987654321';
        
        if ($apiKey !== $validApiKey) {
            http_response_code(401);
            echo json_encode(['error' => 'API key inválida']);
            exit;
        }
        
        // Verificar que el dispositivo existe y obtener vinculación de usuario
        $stmt = $conn->prepare("SELECT id_esp, nombre_esp, id_usuario FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
        $stmt->bind_param("s", $deviceCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo no encontrado', 'device_code' => $deviceCode]);
            exit;
        }
        
        $device = $result->fetch_assoc();
        $espId = $device['id_esp'];
        $userId = $device['id_usuario']; // PUEDE SER STRING O INT dependiendo de la BD
        
        error_log("[heartbeat.php] 🔍 Device vinculado - ESP ID: $espId, Usuario ID: $userId (tipo: " . gettype($userId) . ")");
        
        // Verificar si viene data de ejecución de alarma (firmware v3)
        $input = file_get_contents('php://input');
        $postData = json_decode($input, true);
        
        error_log("[heartbeat.php] POST Data recibido: " . json_encode($postData));
        
        if ($postData && isset($postData['alarm_id']) && isset($postData['executed']) && $postData['executed'] === true) {
            error_log("[heartbeat.php] ⚠️  ESP32 reporta alarma ejecutada - AlarmID: " . $postData['alarm_id']);
            
            // El ESP32 ejecutó una alarma - crear notificación usando función compartida
            $alarmId = (int)$postData['alarm_id'];
            
            // PRIMERO: Verificar que la alarma existe
            $stmtAlarm = $conn->prepare("
                SELECT nombre_alarma, hora_alarma, dias_semana 
                FROM alarmas 
                WHERE id_alarma = ? 
                LIMIT 1
            ");
            $stmtAlarm->bind_param("i", $alarmId);
            $stmtAlarm->execute();
            $alarmResult = $stmtAlarm->get_result();
            
            if (!$alarmRow = $alarmResult->fetch_assoc()) {
                error_log("[heartbeat.php] ❌ Alarma ID $alarmId no existe - ignorando");
                $stmtAlarm->close();
                echo json_encode([
                    'success' => true,
                    'serverTime' => date('c'),
                    'device_code' => $deviceCode,
                    'device_id' => $espId,
                    'linked_user_id' => $userId,
                    'message' => 'Heartbeat recibido - alarma no existe',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
            
            $alarmName = $alarmRow['nombre_alarma'] ?: "Medicamento";
            $alarmTime = $alarmRow['hora_alarma'];
            $diasSemana = $alarmRow['dias_semana'];
            
            // VALIDAR DÍA DE LA SEMANA: Verificar que hoy sea un día configurado
            require_once 'notificaciones_dispensado_utils.php';
            if (!isDayActive($diasSemana)) {
                error_log("[heartbeat.php] 📅 Hoy no es día configurado - Días: $diasSemana, Hoy: " . date('w'));
                $stmtAlarm->close();
                echo json_encode([
                    'success' => true,
                    'serverTime' => date('c'),
                    'device_code' => $deviceCode,
                    'device_id' => $espId,
                    'linked_user_id' => $userId,
                    'message' => 'Heartbeat recibido - día no configurado',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
            
            $moduleNum = 1; // Default
            // Extraer número de módulo del nombre
            if (preg_match('/M[óo]dulo\s*(\d+)/ui', $alarmName, $m)) {
                $moduleNum = (int)$m[1];
            }
            $stmtAlarm->close();
            
            // SEGUNDO: Verificar que estamos dentro de la ventana de tiempo de la alarma (4.5 minutos)
            if (!isWithinAlarmWindow($alarmTime, 270)) {
                error_log("[heartbeat.php] ⏰ Fuera de ventana - Alarma: $alarmTime, Ahora: " . date('H:i:s'));
                echo json_encode([
                    'success' => true,
                    'serverTime' => date('c'),
                    'device_code' => $deviceCode,
                    'device_id' => $espId,
                    'linked_user_id' => $userId,
                    'message' => 'Heartbeat recibido - fuera de ventana',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
            
            // TERCERO: Crear la notificación (validaciones de día y ventana pasaron)
            // NOTA: La verificación de duplicados se hace DENTRO de createPillDispensedNotification()
            // usando una transacción con FOR UPDATE para evitar race conditions
            error_log("[heartbeat.php] 🚀 Validaciones pasadas - Creando notificación");
            error_log("[heartbeat.php] Llamando a createPillDispensedNotification(espId=$espId, alarmId=$alarmId, moduleNum=$moduleNum)");
            
            try {
                createPillDispensedNotification($conn, $espId, $alarmId, $moduleNum);
                error_log("[heartbeat.php] ✅ Notificación creada - Usuario: $userId, Módulo: $moduleNum, AlarmID: $alarmId");
            } catch (Exception $notifError) {
                error_log("[heartbeat.php] ❌ Error creando notificación: " . $notifError->getMessage());
                error_log("[heartbeat.php] Stack trace: " . $notifError->getTraceAsString());
                // No interrumpir el proceso por error de notificación
            }
        }
        
        echo json_encode([
            'success' => true,
            'serverTime' => date('c'),
            'device_code' => $deviceCode,
            'device_id' => $espId,
            'linked_user_id' => $userId,
            'message' => 'Heartbeat recibido',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        // Modo usuario web
        require_once 'session_init.php';
        
        if (!isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'No autenticado',
                'mode' => 'web_user'
            ]);
            exit;
        }
        
        // Actualizar last_seen explícitamente (además de lo que hace isAuthenticated)
        $userId = getUserId();
        if ($userId && isset($conn)) {
            $stmtUpdate = $conn->prepare("UPDATE usuarios SET last_seen = NOW() WHERE id_usuario = ?");
            if ($stmtUpdate) {
                $stmtUpdate->bind_param('s', $userId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }
        
        echo json_encode([
            'success' => true,
            'serverTime' => date('c'),
            'mode' => 'web_user',
            'user_id' => $userId ?? null
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'mode' => !empty($deviceCode) ? 'esp32' : 'web_user'
    ]);
}
?>
