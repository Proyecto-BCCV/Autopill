<?php
require_once 'session_init.php';
require_once 'conexion.php';

// Inserta o agrega a una notificación. Para cambios de dashboard ('cambio_cuidador'/'cambio_paciente')
// agrega el evento a una notificación agregada única ('cambios_dashboard') para el destinatario.
function crearNotificacion($destinatarioId, $origenId, $tipo, $mensaje, $detallesArr = []){
    global $conn;
    if (!is_array($detallesArr)) {
        $detallesArr = [];
    }

    // Normalizar información del actor y contexto
    $actorId = $detallesArr['actor_id'] ?? $origenId;
    $actorNombre = $detallesArr['actor_nombre'] ?? obtenerNombreUsuario($actorId);
    $detallesArr['actor_id'] = $actorId;
    $detallesArr['actor_nombre'] = $actorNombre;
    if (!isset($detallesArr['detalles']) || !is_array($detallesArr['detalles'])) {
        $detallesArr['detalles'] = [];
    }
    if (empty($detallesArr['detalles']['actor_nombre'])) {
        $detallesArr['detalles']['actor_nombre'] = $actorNombre;
    }
    if (empty($detallesArr['tipo'])) {
        $detallesArr['tipo'] = $tipo;
    }
    if (!isset($detallesArr['timestamp'])) {
        $detallesArr['timestamp'] = time();
    }

    // Añadir información de paciente/cuidador cuando aplique
    $esCambioDashboard = in_array($tipo, ['cambio_cuidador','cambio_paciente']);
    if ($esCambioDashboard) {
        if (!isset($detallesArr['origen'])) {
            $detallesArr['origen'] = ($tipo === 'cambio_cuidador') ? 'cuidador' : 'paciente';
        }
        if (!isset($detallesArr['paciente_id'])) {
            $detallesArr['paciente_id'] = ($detallesArr['origen'] === 'cuidador') ? $destinatarioId : $actorId;
        }
        if (empty($detallesArr['paciente_nombre'])) {
            $detallesArr['paciente_nombre'] = obtenerNombreUsuario($detallesArr['paciente_id']);
        }
        if (empty($detallesArr['detalles']['paciente_nombre'])) {
            $detallesArr['detalles']['paciente_nombre'] = $detallesArr['paciente_nombre'];
        }
    }
    
    // Si es un cambio de dashboard, consolidar en una sola notificación agregada
    if ($esCambioDashboard) {
        // Buscar notificación agregada existente
        $sel = $conn->prepare("SELECT id_notificacion, detalles_json FROM notificaciones WHERE id_usuario_destinatario = ? AND tipo_notificacion = 'cambios_dashboard' ORDER BY id_notificacion DESC LIMIT 1");
        if ($sel) {
            $sel->bind_param('s', $destinatarioId);
            $sel->execute();
            $res = $sel->get_result();
        } else {
            throw new Exception('Prepare select agregada: ' . $conn->error);
        }

        $event = [
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'timestamp' => time(),
            'detalles' => $detallesArr['detalles'] ?? [],
            'actor_id' => $detallesArr['actor_id'] ?? $origenId,
            'actor_nombre' => $detallesArr['actor_nombre'] ?? $actorNombre,
            'origen' => $detallesArr['origen'] ?? (($tipo === 'cambio_cuidador') ? 'cuidador' : 'paciente'),
            'paciente_id' => $detallesArr['paciente_id'] ?? null,
            'paciente_nombre' => $detallesArr['paciente_nombre'] ?? null
        ];

        $aggId = null;
        if ($row = $res->fetch_assoc()) {
            $aggId = (int)$row['id_notificacion'];
            $agg = $row['detalles_json'] ? json_decode($row['detalles_json'], true) : [];
            if (!is_array($agg)) $agg = [];
            if (!isset($agg['eventos']) || !is_array($agg['eventos'])) $agg['eventos'] = [];
            // Añadir al inicio
            array_unshift($agg['eventos'], $event);
            // Limitar a últimos 20 eventos
            if (count($agg['eventos']) > 20) { $agg['eventos'] = array_slice($agg['eventos'], 0, 20); }
            $total = count($agg['eventos']);
            $agg['total'] = $total;
            $agg['last_update'] = time();
            $nuevoMensaje = ($total === 1) ? 'Tienes 1 cambio reciente en el dashboard' : ('Tienes ' . $total . ' cambios recientes en el dashboard');
            $aggJson = json_encode($agg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $upd = $conn->prepare("UPDATE notificaciones SET mensaje = ?, detalles_json = ?, leida = 0, fecha_creacion = NOW() WHERE id_notificacion = ?");
            if (!$upd) throw new Exception('Prepare update agregada: ' . $conn->error);
            $upd->bind_param('ssi', $nuevoMensaje, $aggJson, $aggId);
            if (!$upd->execute()) throw new Exception('Exec update agregada: ' . $upd->error);
            return $aggId;
        } else {
            // Crear la notificación agregada inicial
            $agg = [
                'eventos' => [ $event ],
                'total' => 1,
                'last_update' => time()
            ];
            $nuevoMensaje = 'Tienes 1 cambio reciente en el dashboard';
            $sqlIns = "INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, detalles_json) VALUES (?,?,?,?,?)";
            $stmt = $conn->prepare($sqlIns);
            if(!$stmt){ throw new Exception('Prepare insert agregada: ' . $conn->error); }
            $detJson = json_encode($agg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $tipoAgregado = 'cambios_dashboard';
            $stmt->bind_param('sssss', $destinatarioId, $origenId, $tipoAgregado, $nuevoMensaje, $detJson);
            if(!$stmt->execute()){
                throw new Exception('Exec insert agregada: ' . $stmt->error);
            }
            return $conn->insert_id;
        }
    }

    // Resto de tipos: insertar notificación normal
    $sql = "INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, detalles_json) VALUES (?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if(!$stmt){ throw new Exception('Prepare notif: ' . $conn->error); }
    $detJson = $detallesArr ? json_encode($detallesArr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
    $stmt->bind_param('sssss', $destinatarioId, $origenId, $tipo, $mensaje, $detJson);
    if(!$stmt->execute()){
        throw new Exception('Exec notif: ' . $stmt->error);
    }
    return $conn->insert_id;
}

// Obtiene nombre de usuario por id
function obtenerNombreUsuario($id){
    global $conn;
    $sql = "SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return $row['nombre_usuario'];
    return $id;
}

// Reconstruye las notificaciones de solicitudes de cuidadores para un paciente
function rebuildCaregiverRequestNotifications($pacienteId){
    global $conn;

    if (!$conn) {
        throw new Exception('No hay conexión a la base de datos');
    }

    // Mapear estado de lectura existente por request_id para preservarlo
    $leidasPorRequest = [];
    $selExist = $conn->prepare("SELECT id_notificacion, detalles_json, leida FROM notificaciones WHERE id_usuario_destinatario = ? AND tipo_notificacion = 'solicitud_cuidado'");
    if ($selExist) {
        $selExist->bind_param('s', $pacienteId);
        $selExist->execute();
        $resExist = $selExist->get_result();
        while ($row = $resExist->fetch_assoc()) {
            $det = $row['detalles_json'] ? json_decode($row['detalles_json'], true) : [];
            if (is_array($det) && isset($det['request_id'])) {
                $leidasPorRequest[(string)$det['request_id']] = (int)$row['leida'];
            }
        }
    }

    // Eliminar notificaciones existentes de solicitudes para evitar duplicados o agregados
    $deleteSql = "DELETE FROM notificaciones WHERE id_usuario_destinatario = ? AND tipo_notificacion = 'solicitud_cuidado'";
    $stmtDelete = $conn->prepare($deleteSql);
    if (!$stmtDelete) {
        throw new Exception('No se pudo preparar el borrado de notificaciones: ' . $conn->error);
    }
    $stmtDelete->bind_param('s', $pacienteId);
    if (!$stmtDelete->execute()) {
        throw new Exception('No se pudieron eliminar las notificaciones existentes: ' . $stmtDelete->error);
    }

    // Obtener todas las solicitudes pendientes del paciente
    $sqlSolicitudes = "SELECT c.id, c.cuidador_id, c.fecha_creacion, u.nombre_usuario
                       FROM cuidadores c
                       INNER JOIN usuarios u ON u.id_usuario = c.cuidador_id
                       WHERE c.paciente_id = ? AND c.estado = 'pendiente'
                       ORDER BY c.fecha_creacion ASC";
    $stmtSolicitudes = $conn->prepare($sqlSolicitudes);
    if (!$stmtSolicitudes) {
        throw new Exception('No se pudo preparar la consulta de solicitudes: ' . $conn->error);
    }
    $stmtSolicitudes->bind_param('s', $pacienteId);
    $stmtSolicitudes->execute();
    $result = $stmtSolicitudes->get_result();

    if (!$result) {
        throw new Exception('No se pudieron obtener solicitudes pendientes');
    }

    if ($result->num_rows === 0) {
        return 0; // No hay solicitudes pendientes, nada más que hacer
    }

    $insertSql = "INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, detalles_json, leida, fecha_creacion)
                  VALUES (?, ?, 'solicitud_cuidado', ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($insertSql);
    if (!$stmtInsert) {
        throw new Exception('No se pudo preparar la inserción de notificaciones: ' . $conn->error);
    }

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $cuidadorId = $row['cuidador_id'];
        $caretakerName = $row['nombre_usuario'] ?? '';
        $mensaje = 'El cuidador ' . $caretakerName . ' quiere ser tu cuidador. ¿Aceptas?';
        $detalles = json_encode([
            'request_id' => $row['id'],
            'cuidador_id' => $cuidadorId,
            'cuidador_nombre' => $caretakerName,
            'paciente_id' => $pacienteId,
            'fecha_solicitud' => $row['fecha_creacion']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fechaSolicitud = $row['fecha_creacion'] ?: date('Y-m-d H:i:s');

    $leidaVal = isset($leidasPorRequest[(string)$row['id']]) ? (int)$leidasPorRequest[(string)$row['id']] : 0;
    $stmtInsert->bind_param('ssssis', $pacienteId, $cuidadorId, $mensaje, $detalles, $leidaVal, $fechaSolicitud);
        if (!$stmtInsert->execute()) {
            throw new Exception('No se pudo insertar la notificación de solicitud: ' . $stmtInsert->error);
        }
        $count++;
    }

    return $count;
}

?>
