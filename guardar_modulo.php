<?php
// Prevenir cualquier output antes del JSON
ob_start();

try {
    require_once 'session_init.php';
    require_once 'conexion.php';
    
    // Limpiar cualquier output previo
    ob_clean();
    
    header('Content-Type: application/json');
    
    // Verificar que el usuario esté autenticado
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    
    // Verificar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }
    
    // Verificar que existe la conexión a la base de datos
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos: ' . mysqli_connect_error());
    }
    
    // Obtener datos del formulario
    $medicationName = $_POST['medication'] ?? '';
    $hour = $_POST['hour'] ?? '';
    $minute = $_POST['minute'] ?? '';
    $period = $_POST['period'] ?? 'AM';
    $daysJson = $_POST['days'] ?? '[]';
    $days = json_decode($daysJson, true) ?: [];
    $moduleId = isset($_POST['module_id']) ? intval($_POST['module_id']) : 1; // Por defecto módulo 1
    if ($moduleId < 1 || $moduleId > 5) { $moduleId = 1; }
    
    // Validar datos
    if (empty($medicationName) || empty($hour) || empty($minute) || empty($days)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Todos los campos son requeridos',
            'received_data' => [
                'medication' => $medicationName,
                'hour' => $hour,
                'minute' => $minute,
                'days' => $days
            ]
        ]);
        exit;
    }
    
    // Validar hora
    if ($hour < 1 || $hour > 12) {
        http_response_code(400);
        echo json_encode(['error' => 'Hora inválida: ' . $hour]);
        exit;
    }
    
    // Validar minutos
    if ($minute < 0 || $minute > 59) {
        http_response_code(400);
        echo json_encode(['error' => 'Minutos inválidos: ' . $minute]);
        exit;
    }
    
    // Convertir a formato 24 horas
    $hour24 = $hour;
    if ($period === 'PM' && $hour != 12) {
        $hour24 = $hour + 12;
    } elseif ($period === 'AM' && $hour == 12) {
        $hour24 = 0;
    }
    
    // Formatear hora para la base de datos
    $time = sprintf('%02d:%02d:00', $hour24, $minute);
    
    // Convertir días a formato de base de datos (LMXJVSD)
    $daysMap = [
        'L' => 0, // Lunes
        'M' => 1, // Martes (primer M)
        'X' => 2, // Miércoles
        'W' => 2, // Compatibilidad retro (aceptar 'W' si viene de clientes viejos)
        'J' => 3, // Jueves
        'V' => 4, // Viernes
        'S' => 5, // Sábado
        'D' => 6  // Domingo
    ];
    
    $daysString = '0000000'; // Por defecto todos los días desactivados
    foreach ($days as $day) {
        if (isset($daysMap[$day])) {
            $daysString[$daysMap[$day]] = '1';
        }
    }
    
    // Verificar sesión de usuario
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('ID de usuario no encontrado en la sesión');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Detectar estructura de tabla para compatibilidad
    $tableInfo = mysqli_query($conn, "DESCRIBE modulos");
    $columns = [];
    while ($row = mysqli_fetch_assoc($tableInfo)) {
        $columns[] = $row['Field'];
    }
    
    // Determinar nombres de columnas según estructura
    $idColumn = in_array('id_modulo', $columns) ? 'id_modulo' : 'id';
    $userColumn = in_array('id_usuario', $columns) ? 'id_usuario' : 'user_id';
    
    // Verificar si ya existe una configuración para este módulo
    $sql = "SELECT $idColumn FROM modulos WHERE $userColumn = ? AND numero_modulo = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar consulta SELECT: ' . $conn->error . ' (SQL: ' . $sql . ')');
    }
    
    // user_id es CHAR(6) -> tratar como string
    $stmt->bind_param("si", $userId, $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar configuración existente
        $row = $result->fetch_assoc();
        $moduloId = $row[$idColumn];
        
        $sql = "UPDATE modulos SET 
                nombre_medicamento = ?, 
                hora_toma = ?, 
                dias_semana = ?";
        
        // Agregar fecha_actualizacion solo si existe la columna
        if (in_array('fecha_actualizacion', $columns)) {
            $sql .= ", fecha_actualizacion = NOW()";
        }
        
        $sql .= " WHERE $idColumn = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error al preparar consulta UPDATE: ' . $conn->error);
        }
        
        $stmt->bind_param("sssi", $medicationName, $time, $daysString, $moduloId);
    } else {
        // Crear nueva configuración
    $insertColumns = "$userColumn, numero_modulo, nombre_medicamento, hora_toma, dias_semana";
        $insertValues = "?, ?, ?, ?, ?";
    // user_id es CHAR(6) -> debe bindearse como string
    $bindTypes = "sisss";
        $bindParams = [$userId, $moduleId, $medicationName, $time, $daysString];
        
        // Agregar columna activo solo si existe
        if (in_array('activo', $columns)) {
            $insertColumns .= ", activo";
            $insertValues .= ", 1";
            // No necesitamos cambiar bind_param para un valor fijo
        }
        
        $sql = "INSERT INTO modulos ($insertColumns) VALUES ($insertValues)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error al preparar consulta INSERT: ' . $conn->error);
        }
        
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    
    if ($stmt->execute()) {
        // Notificar automáticamente a los cuidadores del paciente
        try {
            require_once 'notificar_cuidador.php';
            require_once 'notificaciones_utils.php';
            
            // Obtener el nombre del paciente
            $pacienteNombre = obtenerNombrePaciente($userId);
            
            // Determinar si es creación o modificación
            $tipoCambio = $result->num_rows > 0 ? 'modulo_modificado' : 'modulo_creado';
            
            // Notificar a los cuidadores
            $notificacion = notificarCuidadorCambio($userId, $tipoCambio, [
                'paciente_nombre' => $pacienteNombre,
                'modulo' => $moduleId,
                'actor' => $_SESSION['user_id'],
                'actor_nombre' => $pacienteNombre,
                'origen' => (function_exists('isCuidador') && isCuidador()) ? 'cuidador' : 'paciente'
            ]);

            // Si el que actúa es cuidador, notificar también al paciente con una sola notificación
            if (function_exists('isCuidador') && isCuidador()) {
                $actorId = $_SESSION['user_id'];
                $actorNombre = obtenerNombreUsuario($actorId);
                $mensaje = 'Se realizaron cambios por parte de ' . $actorNombre;
                $detallesPayload = [
                    'tipo' => $tipoCambio,
                    'detalles' => [ 'modulo' => $moduleId, 'hora' => $time, 'dias' => $daysString, 'medicamento' => $medicationName ],
                    'origen' => 'cuidador',
                    'actor_id' => $actorId,
                    'actor_nombre' => $actorNombre,
                    'paciente_id' => $userId,
                    'paciente_nombre' => $pacienteNombre,
                    'timestamp' => time()
                ];
                crearNotificacion($userId, $actorId, 'cambio_cuidador', $mensaje, $detallesPayload);
            }
            
            // Log de la notificación
            error_log("Notificación enviada: " . json_encode($notificacion));
            
        } catch (Exception $notifError) {
            // Si falla la notificación, no afectar la operación principal
            error_log("Error al notificar cuidadores: " . $notifError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente',
            'redirect' => 'dashboard.php',
            'data' => [
                'medication' => $medicationName,
                'time' => $time,
                'days' => $daysString,
                'module_id' => $moduleId
            ]
        ]);
    } else {
        throw new Exception('Error al ejecutar consulta: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    // Limpiar cualquier output previo en caso de error
    ob_clean();
    
    // Asegurar que el header JSON esté establecido
    header('Content-Type: application/json');
    
    // Log del error para debugging
    error_log("Error en guardar_modulo.php: " . $e->getMessage());
    error_log("Datos recibidos: " . print_r($_POST, true));
    error_log("Usuario ID: " . ($_SESSION['user_id'] ?? 'No definido'));
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor: ' . $e->getMessage(),
        'debug_info' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'module_id' => $moduleId ?? null,
            'medication' => $medicationName ?? null,
            'time' => $time ?? null,
            'days' => $daysString ?? null,
            'session_data' => [
                'authenticated' => isAuthenticated(),
                'session_vars' => array_keys($_SESSION)
            ]
        ]
    ]);
} finally {
    // Asegurar que se envíe el output
    ob_end_flush();
}
?>