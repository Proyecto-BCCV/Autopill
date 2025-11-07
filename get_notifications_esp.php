<?php
// Endpoint ESP32 - Version basada en estructura REAL de BD
require_once 'conexion.php';

// Headers básicos
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Validación directa de parámetros
$apiKey = $_GET['api_key'] ?? '';
$code = $_GET['code'] ?? '';
$userId = $_GET['user_id'] ?? '';
$module = $_GET['module'] ?? '1';

// Validar API key
if ($apiKey !== 'esp32_alarm_2024_secure_key_XXXXXXXXX') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validar parámetros requeridos
if (!$code) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing device code']);
    exit;
}

// Obtener conexión a BD
$conn = obtenerConexion();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Función para obtener estructura real de tabla
function getTableColumns($conn, $tableName) {
    $result = @$conn->query("SHOW TABLES LIKE '$tableName'");
    if (!$result || $result->num_rows == 0) {
        return null;
    }
    
    $columns = [];
    $descResult = $conn->query("DESCRIBE $tableName");
    while ($row = $descResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

// Verificar vinculación ESP-Usuario usando estructura real
$linkedUserId = null;
$espTables = ['codigos_esp', 'esp_devices', 'devices'];

foreach ($espTables as $table) {
    $columns = getTableColumns($conn, $table);
    if (!$columns) continue;
    
    // Buscar columnas de código ESP y usuario
    $codeColumns = [];
    $userColumns = [];
    
    foreach ($columns as $col) {
        if (stripos($col, 'esp') !== false || stripos($col, 'device') !== false || stripos($col, 'codigo') !== false) {
            $codeColumns[] = $col;
        }
        if (stripos($col, 'user') !== false || stripos($col, 'usuario') !== false) {
            $userColumns[] = $col;
        }
    }
    
    // Intentar encontrar vinculación
    foreach ($codeColumns as $codeCol) {
        foreach ($userColumns as $userCol) {
            try {
                $sql = "SELECT $userCol FROM $table WHERE $codeCol = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $code);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $linkedUserId = $row[$userCol];
                            $stmt->close();
                            break 3; // Salir de todos los loops
                        }
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }
}

// Si no hay vinculación, usar el user_id proporcionado
if (!$linkedUserId) {
    $linkedUserId = $userId ?: '769572';
}

// Buscar alarmas en tablas reales
$alarms = [];
$alarmTables = ['alarmas', 'modulos', 'medicamentos', 'notifications'];

foreach ($alarmTables as $table) {
    $columns = getTableColumns($conn, $table);
    if (!$columns) continue;
    
    // Mapear columnas disponibles
    $mappedColumns = [];
    
    foreach ($columns as $col) {
        $colLower = strtolower($col);
        
        // Mapear nombres de medicamento
        if (stripos($col, 'medicamento') !== false || stripos($col, 'nombre') !== false || $col === 'drug_name') {
            $mappedColumns['nombre_medicamento'] = $col;
        }
        
        // Mapear horas
        if (stripos($col, 'hora') !== false || $col === 'time' || stripos($col, 'alarm_time') !== false) {
            $mappedColumns['hora_toma'] = $col;
        }
        
        // Mapear días
        if (stripos($col, 'dias') !== false || $col === 'days' || stripos($col, 'week') !== false) {
            $mappedColumns['dias_semana'] = $col;
        }
        
        // Mapear usuario
        if (stripos($col, 'usuario') !== false || stripos($col, 'user') !== false) {
            $mappedColumns['user_id'] = $col;
        }
        
        // Mapear estado activo
        if ($col === 'activo' || $col === 'estado' || $col === 'active' || $col === 'enabled') {
            $mappedColumns['activo'] = $col;
        }
    }
    
    // Si tenemos mapeo suficiente, hacer consulta
    if (isset($mappedColumns['user_id']) && (isset($mappedColumns['nombre_medicamento']) || isset($mappedColumns['hora_toma']))) {
        
        $selectFields = [];
        $selectAliases = [];
        
        // Construir SELECT con aliases
        foreach (['nombre_medicamento', 'hora_toma', 'dias_semana'] as $field) {
            if (isset($mappedColumns[$field])) {
                $selectFields[] = $mappedColumns[$field];
                $selectAliases[] = $mappedColumns[$field] . " as " . $field;
            } else {
                $selectAliases[] = "'' as " . $field;
            }
        }
        
        $whereClause = $mappedColumns['user_id'] . " = ?";
        if (isset($mappedColumns['activo'])) {
            $whereClause .= " AND " . $mappedColumns['activo'] . " = 1";
        }
        
        $sql = "SELECT " . implode(', ', $selectAliases) . " FROM $table WHERE $whereClause LIMIT 10";
        
        try {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $linkedUserId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        // Convertir al formato ESP32
                        $alarm = [
                            'id' => rand(1000, 9999),
                            'alarm_time' => $row['hora_toma'] ?: '08:00:00',
                            'alarm_name' => $row['nombre_medicamento'] ?: 'Medicamento',
                            'module_id' => intval($module),
                            'quantity' => 1,
                            'days' => $row['dias_semana'] ?: '1,2,3,4,5,6,7',
                            'active' => 1
                        ];
                        
                        $alarms[] = $alarm;
                    }
                }
                $stmt->close();
                
                // Si encontramos alarmas, salir del loop
                if (!empty($alarms)) {
                    break;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
}

// Si no se encontraron alarmas, crear una de prueba para testing
if (empty($alarms)) {
    $currentHour = date('H');
    $currentMinute = date('i');
    
    $alarms[] = [
        'id' => 9999,
        'alarm_time' => date('H:i:s', strtotime('+2 minutes')),
        'alarm_name' => 'Test Alarm - ' . date('H:i:s'),
        'module_id' => intval($module),
        'quantity' => 1,
        'days' => '1,2,3,4,5,6,7',
        'active' => 1
    ];
}

// Respuesta en formato ESP32
$response = [
    'success' => true,
    'user_id' => $linkedUserId,
    'device_code' => $code,
    'module' => intval($module),
    'alarm_count' => count($alarms),
    'alarms' => $alarms,
    'timestamp' => date('Y-m-d H:i:s'),
    'server_time' => date('H:i:s'),
    'debug' => [
        'tables_checked' => $alarmTables,
        'linked_user' => $linkedUserId
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>