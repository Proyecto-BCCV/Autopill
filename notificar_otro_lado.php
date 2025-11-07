<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';

// Crea una notificación hacia la "otra persona" involucrada (paciente<->cuidador)
// Parámetros esperados (POST JSON): {
//   tipo: 'modulo_modificado' | 'alarma_modificada' | 'alarma_creada' | 'alarma_eliminada' | 'modulo_creado' | 'modulo_eliminado',
//   paciente_id: 'xxxxxx',   // paciente propietario del pastillero
//   actor_id: 'xxxxxx',       // quien hizo el cambio (paciente o cuidador)
//   detalles: { modulo, ... }
// }
header('Content-Type: application/json');
try{
    requireAuth();
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if(!is_array($payload)) throw new Exception('JSON inválido');
    $tipo = $payload['tipo'] ?? '';
    $pacienteId = trim($payload['paciente_id'] ?? '');
    $actorId = trim($payload['actor_id'] ?? ($_SESSION['user_id'] ?? ''));
    $detalles = $payload['detalles'] ?? [];
    if(!$tipo || !$pacienteId) throw new Exception('Faltan parámetros');

    // Determinar destinatario: si actor es paciente -> notificar a cuidadores (ya se hace en notificar_cuidador.php)
    // si actor es cuidador -> notificar al paciente (una sola notificación)
    $esCuidador = function_exists('isCuidador') ? isCuidador() : false;
    if ($esCuidador){
        // crear notificación hacia el paciente
        $actorNombre = obtenerNombreUsuario($actorId);
        $pacienteNombre = obtenerNombreUsuario($pacienteId);
        $mensajeBase = 'Se realizaron cambios por parte de ' . $actorNombre;
        $mensaje = $mensajeBase;
        // Guardar detalles
        $detallesPayload = [
            'tipo' => $tipo,
            'detalles' => $detalles,
            'origen' => 'cuidador',
            'actor_id' => $actorId,
            'actor_nombre' => $actorNombre,
            'paciente_id' => $pacienteId,
            'paciente_nombre' => $pacienteNombre,
            'timestamp' => time()
        ];
        crearNotificacion($pacienteId, $actorId, 'cambio_cuidador', $mensaje, $detallesPayload);
    }

    echo json_encode(['success'=>true]);
}catch(Exception $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
