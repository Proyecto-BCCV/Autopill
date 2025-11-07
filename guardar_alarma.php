<?php
// Asegurar salida JSON y capturar fatales (IIS)
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}
if (function_exists('ob_get_level') && ob_get_level() === 0) { @ob_start(); } else { @ob_clean(); }
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');
@error_reporting(E_ALL);
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); http_response_code(200); }
        if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
        echo json_encode(['success'=>false,'error'=>'Fatal: '.$e['message'],'where'=>$e['file'].':'.$e['line']]);
    }
});

require_once 'session_init.php';
require_once 'conexion.php';

// Verificar conexión a la base de datos (seguro si $conn es null)
if (!$conn || !($conn instanceof mysqli)) {
    if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
    echo json_encode(['success'=>false,'error'=>'Base de datos no disponible']);
    exit;
}

// Log de depuración (redirigido a archivo)
// error_log("Iniciando guardar_alarma.php");

try {
    // Verificar autenticación
    if (!isAuthenticated()) {
        throw new Exception('No autorizado');
    }

    // Log de datos recibidos
    // error_log("POST data: " . print_r($_POST, true));

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $actorId = $_SESSION['user_id']; // quien realiza la acción (paciente o cuidador)
    $alarmaId = isset($_POST['id']) ? intval($_POST['id']) : null;
    $moduleId = isset($_POST['modulo']) ? intval($_POST['modulo']) : null;
    $targetPacienteId = isset($_POST['paciente_id']) ? trim($_POST['paciente_id']) : null; // paciente sobre el cual actúa un cuidador
    $nombreMedicamentoNuevo = isset($_POST['nombre_medicamento']) ? trim($_POST['nombre_medicamento']) : null;
    // Nueva: cantidad de pastillas cargadas en el módulo (stock)
    $pillCountRaw = isset($_POST['cantidad_pastillas_modulo']) ? trim((string)$_POST['cantidad_pastillas_modulo']) : null;
    $pillCountVal = null;
    if ($pillCountRaw !== null && $pillCountRaw !== '') {
        if (!ctype_digit($pillCountRaw)) {
            throw new Exception('La cantidad de pastillas debe ser un número entero no negativo');
        }
        $pillCountVal = intval($pillCountRaw);
        if ($pillCountVal < 0) { $pillCountVal = 0; }
    }

    // Determinar contexto: si llega paciente_id y el usuario es cuidador válido, operamos sobre ese paciente
    $esCuidador = function_exists('isCuidador') ? isCuidador() : false;
    $operandoComoCuidador = false;
    $pacienteObjetivoId = $actorId; // por defecto el propio usuario
    if ($targetPacienteId && $esCuidador) {
        if (function_exists('canManagePaciente') && canManagePaciente($targetPacienteId)) {
            $operandoComoCuidador = true;
            $pacienteObjetivoId = $targetPacienteId;
        } else {
            throw new Exception('No tienes permiso para gestionar este paciente');
        }
    } elseif ($targetPacienteId && !$esCuidador) {
        // Un paciente no puede pasar paciente_id distinto a sí mismo
        if ($targetPacienteId !== $actorId) {
            throw new Exception('Operación no permitida');
        }
    }

    // Generar nombre de alarma automáticamente (mantener convención existente)
    $nombreAlarma = "Módulo " . $moduleId;
    $hour = intval($_POST['hour'] ?? 0);
    $minute = intval($_POST['minute'] ?? 0);
    $period = isset($_POST['period']) ? $_POST['period'] : null; // null en 24h
    $daysJson = $_POST['days'] ?? '[]';

    // Log para depuración
    // error_log("Datos recibidos: " . json_encode($_POST));

    // Validaciones comunes
    if (empty($nombreAlarma)) {
        throw new Exception('Nombre de alarma no puede estar vacío');
    }
    // Validación de hora según si hay periodo (12h) o no (24h)
    if ($period === null || $period === '') {
        // Formato 24h: 0-23
        if ($hour < 0 || $hour > 23) {
            throw new Exception('Hora no válida (24h)');
        }
    } else {
        // Formato 12h: 1-12 y periodo AM/PM válido
        if (!in_array($period, ['AM','PM'])) {
            throw new Exception('Periodo inválido');
        }
        if ($hour < 1 || $hour > 12) {
            throw new Exception('Hora no válida (12h)');
        }
    }
    if ($minute < 0 || $minute > 59) {
        throw new Exception('Minutos no válidos');
    }

    // Convertir días
    $days = json_decode($daysJson, true);
    if (!is_array($days) || empty($days)) {
        throw new Exception('Debes seleccionar al menos un día');
    }

    // Convertir a formato 24 horas
    if ($period === null || $period === '') {
        // ya viene en 24h
        $hour24 = $hour;
    } else {
        $hour24 = $hour;
        if ($period === 'PM' && $hour != 12) {
            $hour24 = $hour + 12;
        } elseif ($period === 'AM' && $hour == 12) {
            $hour24 = 0;
        }
    }

    // Formatear hora para la base de datos
    $time = sprintf('%02d:%02d:00', $hour24, $minute);

    // Convertir días a formato de base de datos (LMXJVSD = 0000000)
    $daysMap = [
        'L' => 0, // Lunes
        'M' => 1, // Martes (primer M)
        'X' => 2, // Miércoles
        'W' => 2, // Compatibilidad retro (antes se usaba 'W')
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

    // Verificar que la tabla existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'alarmas'");
    if ($tableCheck->num_rows === 0) {
        throw new Exception('La tabla alarmas no existe en la base de datos');
    }

    // Verificar estructura de la tabla
    $columnCheck = $conn->query("SHOW COLUMNS FROM alarmas");
    $requiredColumns = ['id_alarma', 'nombre_alarma', 'hora_alarma', 'dias_semana', 'modificado_por'];
    $existingColumns = [];
    while ($col = $columnCheck->fetch_assoc()) {
        $existingColumns[] = $col['Field'];
    }
    
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $existingColumns)) {
            throw new Exception("Falta la columna requerida: $column");
        }
    }

    $esCreacion = !$alarmaId; // flag para mensajes

    // Si no hay alarmaId, es una nueva alarma
    if ($esCreacion) {
        // Verificar módulo
        if (!$moduleId) {
            throw new Exception('ID de módulo no válido');
        }

        // Obtener el ID del ESP asociado al paciente objetivo
        $espQuery = "SELECT id_esp FROM codigos_esp WHERE id_usuario = ? LIMIT 1";
        $espStmt = $conn->prepare($espQuery);
        if (!$espStmt) {
            throw new Exception('Error preparando consulta ESP: ' . $conn->error);
        }
        $espStmt->bind_param("s", $pacienteObjetivoId);
        $espStmt->execute();
        $espResult = $espStmt->get_result();
        $espRow = $espResult->fetch_assoc();
        
        if (!$espRow) {
            throw new Exception('No se encontró un ESP asociado al usuario');
        }
        
        $espId = $espRow['id_esp'];

        // Asegurar columna de stock en modulos si vamos a tocarla
        if ($pillCountVal !== null) {
            asegurarColumnaCantidadPastillas($conn);
        }

        // Crear/actualizar registro de módulo según corresponda
        if ($nombreMedicamentoNuevo !== null && $nombreMedicamentoNuevo !== '') {
            // Insertar/actualizar nombre y opcionalmente el stock
            $sqlModulo = "INSERT INTO modulos (id_usuario, numero_modulo, nombre_medicamento, dias_semana, hora_toma, activo" . ($pillCountVal !== null ? ", cantidad_pastillas_modulo" : "") . ")
                           VALUES (?,?,?,?,?,1" . ($pillCountVal !== null ? ",?" : "") . ")
                           ON DUPLICATE KEY UPDATE nombre_medicamento = VALUES(nombre_medicamento), activo = 1" . ($pillCountVal !== null ? ", cantidad_pastillas_modulo = VALUES(cantidad_pastillas_modulo)" : "");
            $stmtModulo = $conn->prepare($sqlModulo);
            if ($stmtModulo) {
                $diasDummy = $daysString; // reutilizamos formato 7 chars
                $horaDummy = $time;      // hora de la primera alarma
                if ($pillCountVal !== null) {
                    $stmtModulo->bind_param('sisssi', $pacienteObjetivoId, $moduleId, $nombreMedicamentoNuevo, $diasDummy, $horaDummy, $pillCountVal);
                } else {
                    $stmtModulo->bind_param('sisss', $pacienteObjetivoId, $moduleId, $nombreMedicamentoNuevo, $diasDummy, $horaDummy);
                }
                $stmtModulo->execute();
            }
        } elseif ($pillCountVal !== null) {
            // Actualizar solo el stock del módulo (crea fila si no existe)
            $sqlStock = "INSERT INTO modulos (id_usuario, numero_modulo, cantidad_pastillas_modulo) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE cantidad_pastillas_modulo = VALUES(cantidad_pastillas_modulo)";
            $stmtStock = $conn->prepare($sqlStock);
            if ($stmtStock) {
                $stmtStock->bind_param('sii', $pacienteObjetivoId, $moduleId, $pillCountVal);
                $stmtStock->execute();
            }
        }

        // Validar que no exista otra alarma con el mismo módulo, hora y días
        $checkDuplicateQuery = "SELECT a.id_alarma, a.nombre_alarma, a.hora_alarma, a.dias_semana 
                                FROM alarmas a 
                                WHERE a.id_esp_alarma = ? 
                                AND a.nombre_alarma = ? 
                                AND a.hora_alarma = ? 
                                AND a.dias_semana = ?";
        $checkStmt = $conn->prepare($checkDuplicateQuery);
        if (!$checkStmt) {
            throw new Exception('Error preparando validación de duplicados: ' . $conn->error);
        }
        $checkStmt->bind_param("ssss", $espId, $nombreAlarma, $time, $daysString);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $duplicate = $checkResult->fetch_assoc();
            throw new Exception('Ya existe una alarma en el ' . $nombreAlarma . ' a las ' . $time . ' con los mismos días seleccionados. No se pueden crear alarmas duplicadas en el mismo módulo con el mismo horario y días.');
        }
        $checkStmt->close();

        // Insertar nueva alarma
        $sql = "INSERT INTO alarmas (nombre_alarma, hora_alarma, dias_semana, id_esp_alarma, modificado_por) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error preparando inserción: ' . $conn->error);
        }
        
    if (!$stmt->bind_param("sssss", $nombreAlarma, $time, $daysString, $espId, $actorId)) {
            throw new Exception('Error vinculando parámetros: ' . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Error insertando alarma: ' . $stmt->error);
        }
        
        $alarmaId = $stmt->insert_id;
        
        // Notificar al ESP32 sobre la nueva alarma
        notificarEspNuevaAlarma($espId, $alarmaId, $nombreAlarma, $time, $moduleId);
        
    } else {
        // Verificar que la alarma existe y pertenece al usuario
    // Permitir actualización si la alarma pertenece al paciente objetivo (modificado_por = paciente) o fue creada por el cuidador previo para ese paciente
    $checkSql = "SELECT a.id_alarma, a.modificado_por, c.id_usuario AS paciente_dueno
             FROM alarmas a 
             INNER JOIN codigos_esp c ON c.id_esp = a.id_esp_alarma 
             WHERE a.id_alarma = ? AND c.id_usuario = ?";
        $checkStmt = $conn->prepare($checkSql);
        
        if ($checkStmt === false) {
            throw new Exception('Error preparando consulta de verificación: ' . $conn->error);
        }
        
    $checkStmt->bind_param("is", $alarmaId, $pacienteObjetivoId);
        
        if (!$checkStmt->execute()) {
            throw new Exception('Error ejecutando verificación: ' . $checkStmt->error);
        }
        
        $result = $checkStmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Alarma no encontrada o no tienes permiso para editarla');
        }
        
        // Obtener datos actuales de la alarma para validación
        $alarmaActual = $result->fetch_assoc();
        
        // Obtener el ESP ID de esta alarma
        $espQuery = "SELECT id_esp_alarma FROM alarmas WHERE id_alarma = ?";
        $espStmt = $conn->prepare($espQuery);
        $espStmt->bind_param("i", $alarmaId);
        $espStmt->execute();
        $espResult = $espStmt->get_result();
        $espRow = $espResult->fetch_assoc();
        $espId = $espRow['id_esp_alarma'];
        $espStmt->close();
        
        // Validar que no exista otra alarma con el mismo módulo, hora y días (excluyendo la alarma actual)
        $checkDuplicateQuery = "SELECT a.id_alarma, a.nombre_alarma, a.hora_alarma, a.dias_semana 
                                FROM alarmas a 
                                WHERE a.id_esp_alarma = ? 
                                AND a.nombre_alarma = ? 
                                AND a.hora_alarma = ? 
                                AND a.dias_semana = ?
                                AND a.id_alarma != ?";
        $checkDupStmt = $conn->prepare($checkDuplicateQuery);
        if (!$checkDupStmt) {
            throw new Exception('Error preparando validación de duplicados: ' . $conn->error);
        }
        $checkDupStmt->bind_param("ssssi", $espId, $nombreAlarma, $time, $daysString, $alarmaId);
        $checkDupStmt->execute();
        $checkDupResult = $checkDupStmt->get_result();
        
        if ($checkDupResult->num_rows > 0) {
            $duplicate = $checkDupResult->fetch_assoc();
            throw new Exception('Ya existe otra alarma en el ' . $nombreAlarma . ' a las ' . $time . ' con los mismos días seleccionados. No se pueden tener alarmas duplicadas en el mismo módulo con el mismo horario y días.');
        }
        $checkDupStmt->close();

        // Actualizar solo la alarma específica
        $sql = "UPDATE alarmas 
                SET hora_alarma = ?, 
                    dias_semana = ?, 
                    fecha_actualizacion = NOW(),
                    modificado_por = ? 
                WHERE id_alarma = ?"; // ya validada la pertenencia arriba
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Error preparando la consulta: " . $conn->error);
        }
        
        // Nota: Ya no actualizamos nombre_alarma ya que es automático
    if (!$stmt->bind_param("sssi", $time, $daysString, $actorId, $alarmaId)) {
            throw new Exception('Error vinculando parámetros: ' . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando actualización: ' . $stmt->error);
        }

        // Si se proporcionó un nuevo stock, actualizar el módulo también al editar la alarma
        if ($pillCountVal !== null && $moduleId) {
            asegurarColumnaCantidadPastillas($conn);
            $sqlStockUpd = "INSERT INTO modulos (id_usuario, numero_modulo, cantidad_pastillas_modulo) VALUES (?,?,?)
                            ON DUPLICATE KEY UPDATE cantidad_pastillas_modulo = VALUES(cantidad_pastillas_modulo)";
            if ($stmt2 = $conn->prepare($sqlStockUpd)) {
                $stmt2->bind_param('sii', $pacienteObjetivoId, $moduleId, $pillCountVal);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        
        // Notificar al ESP32 sobre la alarma actualizada
        notificarEspNuevaAlarma($espId, $alarmaId, $nombreAlarma, $time, $moduleId);
    }

    // Notificar automáticamente a los cuidadores del paciente
    try {
        require_once 'notificar_cuidador.php';
        require_once 'notificaciones_utils.php';
        
        // Obtener el nombre del paciente
    $pacienteNombre = obtenerNombrePaciente($pacienteObjetivoId);
        
        // Determinar si es creación o modificación
    $tipoCambio = $alarmaId ? 'alarma_modificada' : 'alarma_creada';
        
        // Notificar a los cuidadores
    // Primer parámetro: paciente cuyos cuidadores serán notificados
    $notificacion = notificarCuidadorCambio($pacienteObjetivoId, $tipoCambio, [
            'paciente_nombre' => $pacienteNombre,
            'modulo' => $moduleId,
            'actor' => $actorId,
            'actor_nombre' => obtenerNombrePaciente($actorId),
            'origen' => $operandoComoCuidador ? 'cuidador' : 'paciente',
            'como_cuidador' => $operandoComoCuidador
        ]);
        
        // Si quien actúa es cuidador, además notificar al paciente (una sola notificación)
        if ($operandoComoCuidador) {
            $actorNombre = obtenerNombrePaciente($actorId);
            $mensaje = 'Se realizaron cambios por parte de ' . $actorNombre;
            $detallesPayload = [
                'tipo' => $tipoCambio,
                'detalles' => [ 'modulo' => $moduleId, 'hora' => $time, 'dias' => $daysString ],
                'origen' => 'cuidador',
                'actor_id' => $actorId,
                'actor_nombre' => $actorNombre,
                'paciente_id' => $pacienteObjetivoId,
                'paciente_nombre' => $pacienteNombre,
                'timestamp' => time()
            ];
            crearNotificacion($pacienteObjetivoId, $actorId, 'cambio_cuidador', $mensaje, $detallesPayload);
        }

    // Log de la notificación
    // error_log("Notificación enviada: " . json_encode($notificacion));
        
    } catch (Exception $notifError) {
        // Si falla la notificación, no afectar la operación principal
        error_log("Error al notificar cuidadores: " . $notifError->getMessage());
    }

    // Calcular redirección según rol/contexto
    $redirect = 'dashboard.php';
    if (function_exists('isCuidador') && isCuidador()) {
        // Si el cuidador está operando sobre un paciente, redirigir al dashboard del paciente; si no, al dashboard del cuidador
        if ($operandoComoCuidador && !empty($pacienteObjetivoId)) {
            $redirect = 'dashboard_paciente.php?paciente_id=' . urlencode($pacienteObjetivoId);
        } else {
            $redirect = 'dashboard_cuidador.php';
        }
    } else {
        $redirect = 'dashboard.php';
    }

    // Preparar respuesta
    $response = [
        'success' => true,
        'message' => $esCreacion ? 'Alarma creada exitosamente' : 'Alarma actualizada exitosamente',
        'redirect' => $redirect,
        'data' => [
            'id_alarma' => $moduleId ? $alarmaId : $stmt->insert_id,
            'nombre_alarma' => $nombreAlarma,
            'hora_alarma' => $time,
            'dias_semana' => $daysString,
            'module_id' => $moduleId
        ]
    ];

    // Log de respuesta
    // error_log("Respuesta a enviar: " . json_encode($response));

    // Enviar respuesta
    if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
    echo json_encode($response);

} catch (Exception $e) {
    // error_log("Error en guardar_alarma.php: " . $e->getMessage());
    if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'sql_error' => $conn->error ?? 'No SQL error',
            'post_data' => $_POST
        ]
    ]);
}

/**
 * Notifica al ESP32 sobre una nueva alarma o alarma actualizada INSTANTÁNEAMENTE
 */
function notificarEspNuevaAlarma($espId, $alarmaId, $nombreAlarma, $hora, $moduleId) {
    global $conn;
    
    try {
        // Obtener información del ESP
        $espQuery = "SELECT nombre_esp FROM codigos_esp WHERE id_esp = ? LIMIT 1";
        $espStmt = $conn->prepare($espQuery);
        $espStmt->bind_param('i', $espId);
        $espStmt->execute();
        $espResult = $espStmt->get_result();
        
        if ($espResult->num_rows === 0) {
            error_log("[notificarEspNuevaAlarma] ESP ID $espId no encontrado");
            return false;
        }
        
        $espData = $espResult->fetch_assoc();
        $deviceCode = $espData['nombre_esp'];
        
        error_log("[notificarEspNuevaAlarma] 🚀 NOTIFICACIÓN INSTANTÁNEA - ESP $deviceCode sobre alarma ID $alarmaId: $nombreAlarma a las $hora para módulo $moduleId");
        
        // Solo notificar si es para el módulo 1 (que es el que maneja este ESP)
        if ($moduleId == 1) {
            // Preparar datos de notificación para logging
            $notificationData = [
                'device_code' => $deviceCode,
                'alarm_id' => $alarmaId,
                'alarm_name' => $nombreAlarma,
                'alarm_time' => $hora,
                'module_id' => $moduleId,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Sistema IOT eliminado - notificación directa al ESP32 no disponible
            // El ESP32 debe consultar las alarmas periódicamente usando el dashboard principal
            // La alarma se almacena en BD y el ESP la recogerá en su próxima consulta
            
            error_log("[notificarEspNuevaAlarma] 📝 Alarma registrada para ESP $deviceCode: " . json_encode($notificationData));
            error_log("[notificarEspNuevaAlarma] 🔄 ESP $deviceCode recogerá la alarma en próxima consulta periódica");
            
            return true;
            
        } else {
            error_log("[notificarEspNuevaAlarma] Módulo $moduleId ignorado por ESP configurado solo para módulo 1");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[notificarEspNuevaAlarma] Error: " . $e->getMessage());
        return false;
    }
}

// Asegurar que no hay más salida
exit();

/**
 * Asegura que la columna cantidad_pastillas_modulo exista en la tabla modulos
 */
function asegurarColumnaCantidadPastillas(mysqli $conn) {
    try {
        $res = $conn->query("SHOW COLUMNS FROM modulos LIKE 'cantidad_pastillas_modulo'");
        if ($res && $res->num_rows === 0) {
            // Agregar columna como entero no negativo por simplicidad (permite NULL)
            $conn->query("ALTER TABLE modulos ADD COLUMN cantidad_pastillas_modulo INT DEFAULT NULL AFTER nombre_medicamento");
        }
    } catch (Exception $e) {
        // Silencioso: no impedimos flujo principal
    }
}