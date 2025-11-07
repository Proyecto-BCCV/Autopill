<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try{
    requireAuth();
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) throw new Exception('ID inválido');
    $userId = $_SESSION['user_id'];
    $sql = "SELECT n.id_notificacion, n.tipo_notificacion, n.mensaje, n.detalles_json, n.fecha_creacion,
           n.id_usuario_origen, uo.nombre_usuario AS origen_nombre
        FROM notificaciones n
        LEFT JOIN usuarios uo ON uo.id_usuario = n.id_usuario_origen
        WHERE n.id_notificacion = ? AND n.id_usuario_destinatario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $id, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()){
        $det = $row['detalles_json'] ? json_decode($row['detalles_json'], true) : [];
        if (!is_array($det)) { $det = []; }

        $detalleInterno = $det['detalles'] ?? [];
        if (!is_array($detalleInterno)) { $detalleInterno = []; }

        $actorId = $det['actor_id'] ?? ($detalleInterno['actor_id'] ?? $row['id_usuario_origen']);
        $actorNombre = $det['actor_nombre'] ?? ($detalleInterno['actor_nombre'] ?? $row['origen_nombre']);
        if (!$actorNombre && $actorId) {
            $actorNombre = obtenerNombreUsuario($actorId);
        }

        $pacienteNombre = $det['paciente_nombre'] ?? ($detalleInterno['paciente_nombre'] ?? null);

        // Asegurar consistencia de datos
        $det['actor_id'] = $actorId;
        $det['actor_nombre'] = $actorNombre;
        if (!isset($det['detalles']) || !is_array($det['detalles'])) {
            $det['detalles'] = [];
        }
        if (!isset($det['detalles']['actor_nombre']) && $actorNombre) {
            $det['detalles']['actor_nombre'] = $actorNombre;
        }
        if ($pacienteNombre && !isset($det['detalles']['paciente_nombre'])) {
            $det['detalles']['paciente_nombre'] = $pacienteNombre;
        }
        if (empty($det['tipo'])) {
            $det['tipo'] = $row['tipo_notificacion'];
        }

        $fechaFormateada = date('d/m/Y H:i', strtotime($row['fecha_creacion']));

        $tipoNoti = $row['tipo_notificacion'];
        // Si es agregada, asegurar clave 'eventos' y un resumen
        if ($tipoNoti === 'cambios_dashboard') {
            if (!isset($det['eventos']) || !is_array($det['eventos'])) {
                $det['eventos'] = [];
            }
            $det['resumen'] = $row['mensaje'];
        }

        // Extraer hora_alarma si está disponible en detalles
        $horaAlarma = $det['hora_alarma'] ?? null;

        echo json_encode(['success'=>true, 'data'=>[
            'id' => $row['id_notificacion'],
            'tipo' => $det['tipo'] ?? $row['tipo_notificacion'],
            'mensaje' => $row['mensaje'],
            'detalles' => $det,
            'fecha' => $fechaFormateada,
            'hora_alarma' => $horaAlarma,
            'actor_nombre' => $actorNombre,
            'actor_id' => $actorId,
            'paciente_nombre' => $pacienteNombre
        ]]);
    } else {
        echo json_encode(['success'=>false,'error'=>'No encontrada']);
    }
}catch(Exception $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
