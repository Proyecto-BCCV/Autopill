<?php
/**
 * notificaciones_dispensado_utils.php
 * Funciones compartidas para crear notificaciones de pastilla dispensada
 */

// Configurar zona horaria Argentina (UTC-3)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Incluir servicio de email
require_once __DIR__ . '/email_service.php';

/**
 * Log de debugging
 */
function logDispensadoDebug($message) {
    $logFile = __DIR__ . '/logs/monitor_alarmas.log';
    $timestamp = date('Y-m-d H:i:s');
    @error_log("[$timestamp] [dispensado] $message\n", 3, $logFile);
}

/**
 * Crea notificación cuando se dispensa una pastilla
 * Esta función es compartida entre report_alarm_execution.php y monitor_alarmas.php
 * NOTA: Las validaciones de ventana de tiempo y duplicados deben hacerse ANTES de llamar a esta función
 * @return bool true si se creó la notificación, false si se abortó por duplicado
 */
function createPillDispensedNotification($conn, $espId, $alarmId, $moduleNum) {
    try {
        logDispensadoDebug("[createPillDispensedNotification] Iniciando creación de notificación");
        logDispensadoDebug("[createPillDispensedNotification] Parámetros: ESP=$espId, Alarm=$alarmId, Module=$moduleNum");
        
        // BLOQUEO CRÍTICO: Usar transacción para evitar race conditions
        $conn->begin_transaction();
        
        try {
            // Obtener el usuario propietario del ESP32
            $userStmt = $conn->prepare("
                SELECT u.id_usuario, u.nombre_usuario, u.email_usuario 
                FROM usuarios u 
                INNER JOIN codigos_esp e ON u.id_usuario = e.id_usuario 
                WHERE e.id_esp = ? 
                LIMIT 1
                FOR UPDATE
            ");
            $userStmt->bind_param("i", $espId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if (!$userRow = $userResult->fetch_assoc()) {
                logDispensadoDebug("[createPillDispensedNotification] ❌ No se encontró usuario para ESP ID: $espId");
                throw new Exception("No se encontró usuario propietario del ESP");
            }
            
            $userId = $userRow['id_usuario'];
            $userName = $userRow['nombre_usuario'];
            $userEmail = $userRow['email_usuario'];
            logDispensadoDebug("[createPillDispensedNotification] Usuario encontrado: ID=$userId, Nombre=$userName, Email=$userEmail");
            $userStmt->close();
            
            // BLOQUEO EXCLUSIVO: Evitar race condition entre procesos concurrentes
            // Usar GET_LOCK con nombre único por usuario+módulo (NO por alarma, porque pueden ser múltiples alarmas del mismo módulo)
            $lockName = "notif_{$userId}_{$moduleNum}";
            $lockStmt = $conn->query("SELECT GET_LOCK('$lockName', 10) as got_lock");
            $lockRow = $lockStmt->fetch_assoc();
            if ($lockRow['got_lock'] != 1) {
                logDispensadoDebug("[createPillDispensedNotification] ⏸️ No se pudo obtener lock - Otro proceso está creando esta notificación");
                $conn->rollback();
                return false; // No se creó porque otro proceso la está creando
            }
            logDispensadoDebug("[createPillDispensedNotification] 🔒 Lock obtenido: $lockName");
            
            // VERIFICACIÓN DE DUPLICADOS POR MÓDULO
            // Verificar si hay CUALQUIER notificación reciente del mismo módulo, sin importar la alarma
            // Esto evita múltiples notificaciones del mismo módulo en un período corto
            // IMPORTANTE: Usar 270 segundos (4.5 min) que es la misma ventana que la alarma
            logDispensadoDebug("[createPillDispensedNotification] === VERIFICACIÓN DE DUPLICADOS ===");
            logDispensadoDebug("[createPillDispensedNotification] Verificando notificaciones recientes del ESP $espId y módulo $moduleNum");
            
            // Buscar CUALQUIER alarma del mismo ESP que tenga el mismo número de módulo en su nombre
            // y que haya enviado notificación recientemente
            $checkModuleStmt = $conn->prepare("
                SELECT id_alarma, nombre_alarma, ultima_notificacion,
                       TIMESTAMPDIFF(SECOND, ultima_notificacion, NOW()) as segundos_desde
                FROM alarmas
                WHERE id_esp_alarma = ?
                AND nombre_alarma REGEXP ?
                AND ultima_notificacion IS NOT NULL
                AND ultima_notificacion >= DATE_SUB(NOW(), INTERVAL 270 SECOND)
                ORDER BY ultima_notificacion DESC
                LIMIT 1
                FOR UPDATE
            ");
            
            // Patrón regex para buscar "Módulo X" donde X es el número del módulo
            $moduloPattern = "Módulo[[:space:]]+{$moduleNum}([[:space:]]|$)";
            $checkModuleStmt->bind_param("is", $espId, $moduloPattern);
            $checkModuleStmt->execute();
            $moduleResult = $checkModuleStmt->get_result();
            
            if ($moduleRow = $moduleResult->fetch_assoc()) {
                $segundosDesde = (int)$moduleRow['segundos_desde'];
                logDispensadoDebug("[createPillDispensedNotification] 🛑 ABORTANDO - Módulo $moduleNum ya notificó hace $segundosDesde segundos");
                logDispensadoDebug("[createPillDispensedNotification] Alarma previa: ID={$moduleRow['id_alarma']}, Nombre='{$moduleRow['nombre_alarma']}'");
                logDispensadoDebug("[createPillDispensedNotification] Última notificación: {$moduleRow['ultima_notificacion']}");
                $checkModuleStmt->close();
                // LIBERAR LOCK antes de abortar
                $conn->query("SELECT RELEASE_LOCK('$lockName')");
                logDispensadoDebug("[createPillDispensedNotification] 🔓 Lock liberado (duplicado por módulo detectado): $lockName");
                $conn->rollback();
                return false; // No se creó porque el módulo ya notificó recientemente
            }
            
            $checkModuleStmt->close();
            logDispensadoDebug("[createPillDispensedNotification] ✅ No hay notificaciones recientes del módulo $moduleNum - Continuando");
            
            // VALIDACIÓN OPCIONAL: Verificar si el módulo existe (si existe, debe estar activo)
            logDispensadoDebug("[createPillDispensedNotification] Verificando existencia del módulo $moduleNum para usuario $userId");
            $moduleCheckStmt = $conn->prepare("
                SELECT id_modulo, nombre_medicamento, activo 
                FROM modulos 
                WHERE id_usuario = ? AND numero_modulo = ? 
                LIMIT 1
            ");
            $moduleCheckStmt->bind_param("si", $userId, $moduleNum);
            $moduleCheckStmt->execute();
            $moduleResult = $moduleCheckStmt->get_result();
            
            if ($moduleResult->num_rows === 0) {
                // Módulo NO existe en tabla modulos - Permitir notificación igualmente
                logDispensadoDebug("[createPillDispensedNotification] ⚠️ Módulo $moduleNum NO registrado en tabla modulos - Continuando igual");
                $moduleCheckStmt->close();
            } else {
                // Módulo SÍ existe - Verificar que esté activo
                $moduleRow = $moduleResult->fetch_assoc();
                $moduloActivo = $moduleRow['activo'];
                $nombreMedicamento = $moduleRow['nombre_medicamento'];
                $moduleCheckStmt->close();
                
                logDispensadoDebug("[createPillDispensedNotification] ✅ Módulo encontrado - Activo: $moduloActivo, Medicamento: $nombreMedicamento");
                
                if ($moduloActivo != 1) {
                    logDispensadoDebug("[createPillDispensedNotification] 🛑 ABORTANDO - Módulo $moduleNum existe pero está INACTIVO");
                    $conn->rollback();
                    return; // No crear notificación de módulos inactivos
                }
            }
            
            // Obtener información de la alarma
            $alarmStmt = $conn->prepare("
                SELECT nombre_alarma, hora_alarma 
                FROM alarmas 
                WHERE id_alarma = ? 
                LIMIT 1
            ");
            $alarmStmt->bind_param("i", $alarmId);
            $alarmStmt->execute();
            $alarmResult = $alarmStmt->get_result();
            
            $alarmName = "Medicamento";
            $alarmTime = null;
            if ($alarmRow = $alarmResult->fetch_assoc()) {
                $alarmName = $alarmRow['nombre_alarma'] ?: "Medicamento";
                $alarmTime = $alarmRow['hora_alarma'];
            }
            $alarmStmt->close();
            
            // Crear la notificación DIRECTAMENTE
            $mensaje = "Se dispensó la pastilla del Módulo $moduleNum";
            $detalles = [
                'tipo' => 'pastilla_dispensada',
                'modulo' => (int)$moduleNum,  // Forzar a entero
                'alarma_id' => (int)$alarmId,  // Forzar a entero
                'alarma_nombre' => $alarmName,
                'hora_alarma' => $alarmTime,  // Hora programada de la alarma
                'timestamp' => time()
            ];
            
            logDispensadoDebug("[createPillDispensedNotification] Insertando notificación directamente");
            logDispensadoDebug("[createPillDispensedNotification] Mensaje: $mensaje");
            logDispensadoDebug("[createPillDispensedNotification] Detalles: " . json_encode($detalles));
            
            // INSERT directo a la tabla de notificaciones
            $detJson = json_encode($detalles, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $tipo = 'pastilla_dispensada';
            $stmt = $conn->prepare("INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, detalles_json, leida, fecha_creacion) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if (!$stmt) {
                throw new Exception("Error preparando INSERT de notificación: " . $conn->error);
            }
            // Usar 's' para strings ya que id_usuario es CHAR(6)
            $stmt->bind_param("sssss", $userId, $userId, $tipo, $mensaje, $detJson);
            if (!$stmt->execute()) {
                throw new Exception("Error ejecutando INSERT de notificación: " . $stmt->error);
            }
            $stmt->close();
            
            // ACTUALIZAR campo ultima_notificacion en la tabla alarmas para trackear permanentemente
            logDispensadoDebug("[createPillDispensedNotification] Actualizando ultima_notificacion en alarmas");
            $updateAlarmStmt = $conn->prepare("UPDATE alarmas SET ultima_notificacion = NOW() WHERE id_alarma = ?");
            if ($updateAlarmStmt) {
                $updateAlarmStmt->bind_param("i", $alarmId);
                if ($updateAlarmStmt->execute()) {
                    logDispensadoDebug("[createPillDispensedNotification] ✅ Campo ultima_notificacion actualizado");
                } else {
                    logDispensadoDebug("[createPillDispensedNotification] ⚠️ No se pudo actualizar ultima_notificacion: " . $updateAlarmStmt->error);
                }
                $updateAlarmStmt->close();
            }
            
            // REDUCIR contador de pastillas del módulo
            logDispensadoDebug("[createPillDispensedNotification] Reduciendo contador de pastillas del módulo $moduleNum");
            $updatePillStmt = $conn->prepare("
                UPDATE modulos 
                SET cantidad_pastillas_modulo = CASE 
                    WHEN cantidad_pastillas_modulo IS NULL THEN NULL 
                    WHEN cantidad_pastillas_modulo > 0 THEN cantidad_pastillas_modulo - 1 
                    ELSE 0 
                END 
                WHERE id_usuario = ? AND numero_modulo = ?
            ");
            if ($updatePillStmt) {
                $updatePillStmt->bind_param("si", $userId, $moduleNum);
                if ($updatePillStmt->execute()) {
                    $affectedRows = $updatePillStmt->affected_rows;
                    if ($affectedRows > 0) {
                        logDispensadoDebug("[createPillDispensedNotification] ✅ Contador de pastillas reducido en módulo $moduleNum");
                    } else {
                        logDispensadoDebug("[createPillDispensedNotification] ℹ️ No se modificó contador (módulo no existe o ya en NULL)");
                    }
                } else {
                    logDispensadoDebug("[createPillDispensedNotification] ⚠️ Error reduciendo contador: " . $updatePillStmt->error);
                }
                $updatePillStmt->close();
            }
            
            // COMMIT de la transacción
            $conn->commit();
            
            // LIBERAR LOCK
            $conn->query("SELECT RELEASE_LOCK('$lockName')");
            logDispensadoDebug("[createPillDispensedNotification] 🔓 Lock liberado: $lockName");
            
            logDispensadoDebug("[createPillDispensedNotification] ✅ Notificación creada EXITOSAMENTE");
            logDispensadoDebug("[createPillDispensedNotification] Usuario: $userId - Módulo: $moduleNum - Alarma: $alarmName");
            
        } catch (Exception $txError) {
            // Rollback en caso de error
            $conn->rollback();
            // LIBERAR LOCK también en caso de error
            if (isset($lockName)) {
                $conn->query("SELECT RELEASE_LOCK('$lockName')");
                logDispensadoDebug("[createPillDispensedNotification] 🔓 Lock liberado (error): $lockName");
            }
            throw $txError;
        }
        
        // Notificar también a los cuidadores activos del usuario (FUERA de la transacción principal)
        try {
            $cuidadoresStmt = $conn->prepare("
                SELECT cuidador_id 
                FROM cuidadores 
                WHERE paciente_id = ? AND estado = 'activo'
            ");
            $cuidadoresStmt->bind_param("s", $userId);
            $cuidadoresStmt->execute();
            $cuidadoresResult = $cuidadoresStmt->get_result();
            
            $cuidadoresNotificados = 0;
            while ($cuidadorRow = $cuidadoresResult->fetch_assoc()) {
                $cuidadorId = $cuidadorRow['cuidador_id'];
                
                // Crear notificación para el cuidador
                $mensajeCuidador = "Se dispensó la pastilla del Módulo $moduleNum de $userName";
                $detallesCuidador = [
                    'tipo' => 'pastilla_dispensada_paciente',
                    'modulo' => (int)$moduleNum,
                    'alarma_id' => (int)$alarmId,
                    'alarma_nombre' => $alarmName,
                    'hora_alarma' => $alarmTime,  // Hora programada de la alarma
                    'paciente_id' => $userId,
                    'paciente_nombre' => $userName,
                    'timestamp' => time()
                ];
                
                $detJsonCuidador = json_encode($detallesCuidador, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $stmtCuidador = $conn->prepare("INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, detalles_json, leida, fecha_creacion) VALUES (?, ?, 'pastilla_dispensada', ?, ?, 0, NOW())");
                
                if ($stmtCuidador) {
                    // Usar 's' para strings ya que id_usuario es CHAR(6)
                    $stmtCuidador->bind_param("ssss", $cuidadorId, $userId, $mensajeCuidador, $detJsonCuidador);
                    if ($stmtCuidador->execute()) {
                        $cuidadoresNotificados++;
                        logDispensadoDebug("[createPillDispensedNotification] Notificación enviada a cuidador ID: $cuidadorId");
                    } else {
                        logDispensadoDebug("[createPillDispensedNotification] ⚠️ Error insertando notificación para cuidador $cuidadorId: " . $stmtCuidador->error);
                    }
                    $stmtCuidador->close();
                }
            }
            $cuidadoresStmt->close();
            
            if ($cuidadoresNotificados > 0) {
                logDispensadoDebug("[createPillDispensedNotification] ✅ Total cuidadores notificados: $cuidadoresNotificados");
            } else {
                logDispensadoDebug("[createPillDispensedNotification] ℹ️  Usuario no tiene cuidadores activos");
            }
            
        } catch (Exception $cuidadorError) {
            // No interrumpir el proceso si falla la notificación a cuidadores
            logDispensadoDebug("[createPillDispensedNotification] ⚠️  Error notificando cuidadores: " . $cuidadorError->getMessage());
        }
        
        // ENVIAR EMAIL AL USUARIO (FUERA de la transacción principal)
        try {
            if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                logDispensadoDebug("[createPillDispensedNotification] Enviando email a: $userEmail");
                
                // Formatear la hora de la alarma
                $horaFormateada = $alarmTime ? date('H:i', strtotime($alarmTime)) : 'N/A';
                
                // Crear el HTML del correo
                $subject = 'AutoPill - Pastilla Dispensada';
                $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #C154C1, #9b44c5); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .content { padding: 30px 20px; }
        .notification-box { background: #f9f9f9; border-left: 4px solid #C154C1; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .notification-box .title { font-size: 18px; font-weight: bold; color: #C154C1; margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; color: #666; }
        .info-value { color: #333; }
        .success-badge { display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; border-radius: 20px; font-size: 14px; font-weight: bold; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; }
        .footer a { color: #C154C1; text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .info-row { flex-direction: column; }
            .info-label, .info-value { margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AutoPill - Pastilla Dispensada</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            
            <p>Te informamos que se ha dispensado exitosamente una pastilla de tu pastillero automático.</p>
            
            <div class="notification-box">
                <div class="title">Dispensación Exitosa</div>
                <div class="info-row">
                    <span class="info-label">Módulo:</span>
                    <span class="info-value">' . htmlspecialchars($alarmName, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Número de Módulo:</span>
                    <span class="info-value">' . $moduleNum . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hora Programada:</span>
                    <span class="info-value">' . htmlspecialchars($horaFormateada, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha y Hora:</span>
                    <span class="info-value">' . date('d/m/Y H:i:s') . '</span>
                </div>
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <span class="success-badge">Estado: Completado</span>
            </p>
            
            <p style="color: #666; font-size: 14px;">
                Recordá tomar tu medicación según las indicaciones de tu médico.
            </p>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático de AutoPill</p>
            <p>Si tenés alguna consulta, contactanos a través de nuestra plataforma</p>
            <p style="margin-top: 15px;">
                <a href="https://pastillero.webhop.net">Ir a AutoPill</a>
            </p>
        </div>
    </div>
</body>
</html>';
                
                // Enviar el email
                $emailResult = send_email($userEmail, $subject, $html);
                
                if ($emailResult['success']) {
                    logDispensadoDebug("[createPillDispensedNotification] ✅ Email enviado exitosamente a $userEmail");
                } else {
                    $errorMsg = $emailResult['error'] ?? 'Error desconocido';
                    logDispensadoDebug("[createPillDispensedNotification] ⚠️ Error enviando email: $errorMsg");
                }
            } else {
                logDispensadoDebug("[createPillDispensedNotification] ℹ️  Email no enviado - Usuario sin email válido");
            }
            
        } catch (Exception $emailError) {
            // No interrumpir el proceso si falla el envío de email
            logDispensadoDebug("[createPillDispensedNotification] ⚠️  Error en servicio de email: " . $emailError->getMessage());
        }
        
        return true; // Notificación creada exitosamente
        
    } catch (Exception $e) {
        throw new Exception("Error creando notificación de pastilla dispensada: " . $e->getMessage());
    }
}

/**
 * Verifica si ya existe una notificación de dispensado para esta alarma en el día actual
 * Evita duplicados
 */
function notificationAlreadyExists($conn, $alarmId, $moduleNum) {
    try {
        // Buscar notificaciones del día actual para esta alarma
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM notificaciones 
            WHERE tipo_notificacion = 'pastilla_dispensada'
            AND detalles_json LIKE CONCAT('%\"alarma_id\":', ?, '%')
            AND detalles_json LIKE CONCAT('%\"modulo\":', ?, '%')
            AND DATE(fecha_creacion) = CURDATE()
        ");
        $stmt->bind_param("ii", $alarmId, $moduleNum);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)$row['count'] > 0;
    } catch (Exception $e) {
        logDispensadoDebug("[notificationAlreadyExists] Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si ya existe una notificación de dispensado reciente (en ventana de tiempo específica)
 * Evita duplicados en la ventana de tiempo para un usuario específico
 * NOTA: Usa ventana de 270 segundos (4.5 min) - mismo tiempo que la ventana de la alarma
 */
function notificationRecentlyExists($conn, $userId, $alarmId, $moduleNum, $windowSeconds = 270) {
    try {
        // CRÍTICO: Usar la misma ventana que las alarmas (270 segundos / 4.5 minutos)
        // para evitar notificaciones duplicadas de la misma alarma
        $searchWindow = 270; // Misma ventana que isWithinAlarmWindow
        
        logDispensadoDebug("[notificationRecentlyExists] === INICIO VERIFICACIÓN ===");
        logDispensadoDebug("[notificationRecentlyExists] Buscando: Usuario=$userId (tipo: " . gettype($userId) . "), Alarma=$alarmId (tipo: " . gettype($alarmId) . "), Módulo=$moduleNum (tipo: " . gettype($moduleNum) . ")");
        logDispensadoDebug("[notificationRecentlyExists] Ventana de duplicados: últimos {$searchWindow} segundos");
        
        // DEPURACIÓN: Mostrar query exacto
        logDispensadoDebug("[notificationRecentlyExists] SQL: SELECT ... WHERE tipo='pastilla_dispensada' AND id_usuario_destinatario='$userId' AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL $searchWindow SECOND)");
        
        $stmt = $conn->prepare("
            SELECT id_notificacion, fecha_creacion, detalles_json,
                   TIMESTAMPDIFF(SECOND, fecha_creacion, NOW()) as segundos_desde_creacion
            FROM notificaciones 
            WHERE tipo_notificacion = 'pastilla_dispensada'
            AND id_usuario_destinatario = ?
            AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY fecha_creacion DESC
        ");
        $stmt->bind_param("si", $userId, $searchWindow);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $totalNotifs = 0;
        $matchingNotifs = 0;
        
        logDispensadoDebug("[notificationRecentlyExists] Registros encontrados: " . $result->num_rows);
        
        while ($row = $result->fetch_assoc()) {
            $totalNotifs++;
            
            // Decodificar JSON y verificar coincidencia EXACTA de alarma Y módulo
            $detalles = json_decode($row['detalles_json'], true);
            
            logDispensadoDebug("[notificationRecentlyExists]   Notif ID {$row['id_notificacion']}: detalles_json = " . $row['detalles_json']);
            
            if (is_array($detalles)) {
                $alarmIdJson = isset($detalles['alarma_id']) ? (int)$detalles['alarma_id'] : 0;
                $moduleNumJson = isset($detalles['modulo']) ? (int)$detalles['modulo'] : 0;
                
                logDispensadoDebug("[notificationRecentlyExists]   Notif ID {$row['id_notificacion']}: Alarma=$alarmIdJson, Módulo=$moduleNumJson, Hace: {$row['segundos_desde_creacion']}s");
                logDispensadoDebug("[notificationRecentlyExists]   Comparando: $alarmIdJson === " . (int)$alarmId . " ? " . ($alarmIdJson === (int)$alarmId ? 'SÍ' : 'NO') . " | $moduleNumJson === " . (int)$moduleNum . " ? " . ($moduleNumJson === (int)$moduleNum ? 'SÍ' : 'NO'));
                
                // Solo contar si coinciden AMBOS: alarma Y módulo
                if ($alarmIdJson === (int)$alarmId && $moduleNumJson === (int)$moduleNum) {
                    $matchingNotifs++;
                    logDispensadoDebug("[notificationRecentlyExists]   ⚠️  COINCIDENCIA EXACTA - Bloqueando");
                } else {
                    logDispensadoDebug("[notificationRecentlyExists]   ✓ Notificación de otra alarma/módulo - ignorando");
                }
            } else {
                logDispensadoDebug("[notificationRecentlyExists]   ⚠️  JSON inválido o no es array");
            }
        }
        $stmt->close();
        
        logDispensadoDebug("[notificationRecentlyExists] Total notificaciones en ventana: $totalNotifs");
        logDispensadoDebug("[notificationRecentlyExists] Notificaciones de esta alarma/módulo: $matchingNotifs");
        
        if ($matchingNotifs > 0) {
            logDispensadoDebug("[notificationRecentlyExists] 🛑 RESULTADO: BLOQUEADO (ya existe notificación de esta alarma)");
            return true;
        }
        
        logDispensadoDebug("[notificationRecentlyExists] ✅ RESULTADO: PERMITIDO (no hay notificaciones de esta alarma)");
        logDispensadoDebug("[notificationRecentlyExists] === FIN VERIFICACIÓN ===");
        return false;
        
    } catch (Exception $e) {
        logDispensadoDebug("[notificationRecentlyExists] ❌ Error: " . $e->getMessage());
        logDispensadoDebug("[notificationRecentlyExists] Stack: " . $e->getTraceAsString());
        // En caso de error, PERMITIR para no bloquear notificaciones legítimas
        return false;
    }
}

/**
 * Detecta el número de módulo desde el nombre de la alarma
 */
function detectModuleFromAlarmName($alarmName) {
    // Buscar patrón "Módulo X" o "Modulo X"
    if (preg_match('/M[óo]dulo\s*(\d+)/ui', $alarmName, $matches)) {
        return (int)$matches[1];
    }
    // Si no se encuentra, asumir módulo 1
    return 1;
}

/**
 * Verifica si un día de la semana está activo según el formato de la BD
 * Formato de BD: String indexado de 7 caracteres "0000000" a "1111111"
 * Índices: 0=Lun, 1=Mar, 2=Mié, 3=Jue, 4=Vie, 5=Sáb, 6=Dom
 * 
 * Esta función es compatible con el formato usado por isDayOk() en monitor_alarmas.php
 */
function isDayActive($diasSemana) {
    if (empty($diasSemana)) {
        return true; // Si no hay días especificados, asumir todos los días
    }
    
    // Obtener día actual según PHP (0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb)
    $phpDay = (int)date('w');
    
    // Convertir al índice del sistema (0=Lun, 1=Mar, 2=Mié, 3=Jue, 4=Vie, 5=Sáb, 6=Dom)
    $sysDay = ($phpDay + 6) % 7;
    
    // Verificar si el string tiene el formato indexado (7 caracteres)
    if (strlen($diasSemana) === 7) {
        // Formato indexado: "1111100" (7 caracteres)
        $result = isset($diasSemana[$sysDay]) && $diasSemana[$sysDay] === '1';
        logDispensadoDebug("[isDayActive] Formato indexado - String: '$diasSemana', phpDay: $phpDay, sysDay: $sysDay, Resultado: " . ($result ? 'ACTIVO' : 'INACTIVO'));
        return $result;
    }
    
    // Fallback: formato legacy separado por comas "1,3,5" o "L,M,V"
    logDispensadoDebug("[isDayActive] Formato legacy detectado - String: '$diasSemana'");
    
    $currentDay = $phpDay;
    $currentDayAlt = $currentDay === 0 ? 7 : $currentDay;
    
    // Mapeo de letras a números
    $dayMap = [
        'L' => 1, 'M' => 2, 'X' => 3, 'J' => 4, 'V' => 5, 'S' => 6, 'D' => 0
    ];
    
    // Separar por comas
    $days = explode(',', $diasSemana);
    
    foreach ($days as $day) {
        $day = trim($day);
        
        // Si es un número
        if (is_numeric($day)) {
            $dayNum = (int)$day;
            if ($dayNum === $currentDay || $dayNum === $currentDayAlt) {
                logDispensadoDebug("[isDayActive] Legacy: Match encontrado - día $dayNum");
                return true;
            }
        }
        // Si es una letra
        elseif (isset($dayMap[$day])) {
            if ($dayMap[$day] === $currentDay) {
                logDispensadoDebug("[isDayActive] Legacy: Match encontrado - letra $day");
                return true;
            }
        }
    }
    
    logDispensadoDebug("[isDayActive] Legacy: No match encontrado");
    return false;
}

/**
 * Verifica si la hora actual está dentro de una ventana de tiempo de la hora de alarma
 * La ventana se abre DESDE 30 segundos ANTES hasta windowSeconds DESPUÉS de la alarma
 * Esto compensa pequeños desfases de reloj entre servidor y dispositivos
 * 
 * Por ejemplo, con windowSeconds=270 (4.5 minutos):
 * - Ventana: desde -30s hasta +270s desde la hora de alarma
 * - Si alarma es a las 08:00:00, acepta desde 07:59:30 hasta 08:04:30
 */
function isWithinAlarmWindow($alarmTime, $windowSeconds = 270) {
    try {
        // Obtener hora actual
        $now = time();
        
        // Convertir hora de alarma a timestamp de hoy
        $alarmDateTime = strtotime(date('Y-m-d') . ' ' . $alarmTime);
        
        if ($alarmDateTime === false) {
            logDispensadoDebug("[isWithinAlarmWindow] Error parseando hora de alarma: $alarmTime");
            return false;
        }
        
        // Calcular diferencia en segundos (positivo = después de la alarma, negativo = antes)
        $diff = $now - $alarmDateTime;
        
        // Margen de tolerancia: -30 segundos (antes) hasta +windowSeconds (después)
        $toleranciaAntes = 30; // 30 segundos antes
        $withinWindow = ($diff >= -$toleranciaAntes && $diff <= $windowSeconds);
        
        $statusMsg = $withinWindow ? 'DENTRO' : 'FUERA';
        logDispensadoDebug("[isWithinAlarmWindow] Alarma: $alarmTime, Ahora: " . date('H:i:s', $now) . ", Diff: {$diff}s, Ventana: -{$toleranciaAntes}s a +{$windowSeconds}s, Resultado: $statusMsg");
        
        return $withinWindow;
    } catch (Exception $e) {
        logDispensadoDebug("[isWithinAlarmWindow] Error: " . $e->getMessage());
        return false;
    }
}
?>
