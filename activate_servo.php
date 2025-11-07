<?php
// Endpoint para activar manualmente el servomotor del ESP32
require_once 'conexion.php';
require_once 'session_init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Validar API key o autenticación
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $validApiKey = 'esp32_alarm_2024_secure_key_987654321';
    $hasValidApiKey = ($apiKey === $validApiKey);
    
    // Si no hay API key válida, requerir autenticación web
    if (!$hasValidApiKey) {
        requireAuth();
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'info';
    $deviceCode = $_GET['code'] ?? $_POST['device_code'] ?? 'ESP32_001';
    
    switch ($action) {
        case 'activate':
            // Crear una alarma para el minuto actual para activar el servomotor
            $now = new DateTime();
            $alarmTime = $now->format('H:i:00');
            $alarmName = 'Activacion Manual ' . $now->format('H:i:s');
            
            // Buscar el ESP
            $stmt = $conn->prepare("SELECT id_esp FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
            $stmt->bind_param("s", $deviceCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("ESP32 no encontrado: $deviceCode");
            }
            
            $esp = $result->fetch_assoc();
            $espId = $esp['id_esp'];
            
            // Crear alarma inmediata
            $stmt = $conn->prepare("
                INSERT INTO alarmas (nombre_alarma, hora_alarma, dias_semana, id_esp_alarma, modificado_por) 
                VALUES (?, ?, '1111111', ?, 'API')
            ");
            $stmt->bind_param("ssi", $alarmName, $alarmTime, $espId);
            
            if ($stmt->execute()) {
                $alarmaId = $conn->insert_id;
                echo json_encode([
                    'success' => true,
                    'message' => 'Servomotor activado manualmente',
                    'alarm_id' => $alarmaId,
                    'alarm_name' => $alarmName,
                    'alarm_time' => $alarmTime,
                    'device_code' => $deviceCode,
                    'esp_id' => $espId,
                    'note' => 'El ESP32 debería activar el servomotor en los próximos 10 segundos',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception('Error al crear alarma de activación');
            }
            break;
            
        case 'test_movement':
            // Crear múltiples alarmas de prueba espaciadas cada 30 segundos
            $now = new DateTime();
            $createdAlarms = [];
            
            // Buscar el ESP
            $stmt = $conn->prepare("SELECT id_esp FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
            $stmt->bind_param("s", $deviceCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("ESP32 no encontrado: $deviceCode");
            }
            
            $esp = $result->fetch_assoc();
            $espId = $esp['id_esp'];
            
            // Crear 3 alarmas de prueba
            for ($i = 0; $i < 3; $i++) {
                $testTime = clone $now;
                $testTime->add(new DateInterval('PT' . ($i * 30) . 'S')); // +30 segundos cada una
                $alarmTime = $testTime->format('H:i:s');
                $alarmName = "Test Movimiento #" . ($i + 1) . " " . $testTime->format('H:i:s');
                
                $stmt = $conn->prepare("
                    INSERT INTO alarmas (nombre_alarma, hora_alarma, dias_semana, id_esp_alarma, modificado_por) 
                    VALUES (?, ?, '1111111', ?, 'API')
                ");
                $stmt->bind_param("ssi", $alarmName, $alarmTime, $espId);
                
                if ($stmt->execute()) {
                    $createdAlarms[] = [
                        'id' => $conn->insert_id,
                        'name' => $alarmName,
                        'time' => $alarmTime,
                        'seconds_from_now' => $i * 30
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Secuencia de prueba creada',
                'alarms_created' => count($createdAlarms),
                'alarms' => $createdAlarms,
                'device_code' => $deviceCode,
                'note' => 'El servomotor se activará 3 veces con 30 segundos de intervalo',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'status':
            // Obtener estado actual del ESP32 y sus alarmas
            $stmt = $conn->prepare("SELECT id_esp FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
            $stmt->bind_param("s", $deviceCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("ESP32 no encontrado: $deviceCode");
            }
            
            $esp = $result->fetch_assoc();
            $espId = $esp['id_esp'];
            
            // Obtener estadísticas de alarmas
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total_alarms, 
                       SUM(CASE WHEN TIME(hora_alarma) > TIME(NOW()) OR TIME(hora_alarma) = TIME(NOW()) THEN 1 ELSE 0 END) as pending_today
                FROM alarmas 
                WHERE id_esp_alarma = ?
            ");
            $stmt->bind_param("i", $espId);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
            
            // Próxima alarma
            $stmt = $conn->prepare("
                SELECT nombre_alarma, hora_alarma 
                FROM alarmas 
                WHERE id_esp_alarma = ? AND TIME(hora_alarma) >= TIME(NOW())
                ORDER BY hora_alarma ASC 
                LIMIT 1
            ");
            $stmt->bind_param("i", $espId);
            $stmt->execute();
            $nextAlarm = $stmt->get_result()->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'device_code' => $deviceCode,
                'esp_id' => $espId,
                'current_time' => date('H:i:s'),
                'total_alarms' => intval($stats['total_alarms']),
                'pending_today' => intval($stats['pending_today']),
                'next_alarm' => $nextAlarm,
                'servo_config' => [
                    'pin' => 18,
                    'module' => 1,
                    'movement' => '0° → 180° → 0°',
                    'duration' => '2 segundos'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'clean_test_alarms':
            // Limpiar alarmas de prueba
            $stmt = $conn->prepare("
                DELETE FROM alarmas 
                WHERE modificado_por = 'API'
                   OR nombre_alarma LIKE 'Test %' 
                   OR nombre_alarma LIKE 'Activacion Manual %'
                   OR nombre_alarma LIKE '%ACTIVACION INMEDIATA%'
            ");
            
            if ($stmt->execute()) {
                $deletedCount = $conn->affected_rows;
                echo json_encode([
                    'success' => true,
                    'message' => 'Alarmas de prueba eliminadas',
                    'deleted_count' => $deletedCount,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception('Error al eliminar alarmas de prueba');
            }
            break;
            
        default:
            // Información del servicio
            echo json_encode([
                'service' => 'Servomotor Control API',
                'available_actions' => [
                    'activate' => 'Activar servomotor inmediatamente',
                    'test_movement' => 'Crear secuencia de prueba (3 activaciones)',
                    'status' => 'Ver estado del ESP32 y alarmas',
                    'clean_test_alarms' => 'Limpiar alarmas de prueba'
                ],
                'usage' => [
                    'GET /activate_servo.php?action=activate&code=ESP32_001',
                    'GET /activate_servo.php?action=status&code=ESP32_001',
                    'GET /activate_servo.php?action=test_movement&code=ESP32_001'
                ],
                'note' => 'Para activar servomotor, el ESP32 debe estar conectado y consultando alarmas cada 10 segundos',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>