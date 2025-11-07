<?php
// Prevenir cualquier output antes del JSON
ob_start();

require_once 'session_init.php';
require_once 'conexion.php';

// Limpiar cualquier output buffer previo
ob_clean();
header('Content-Type: application/json');

// Utilidad para responder y terminar
function respond($code, $data){
    ob_clean(); // Limpiar buffer antes de enviar JSON
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['success'=>false,'error' => 'Método no permitido']);
    }

    if (!isAuthenticated()) {
        respond(401, ['success'=>false,'error' => 'No autenticado']);
    }

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['target_id'])) {
        respond(400, ['success'=>false,'error' => 'Datos inválidos']);
    }

    $actorId = getUserId();
    $targetId = $input['target_id'];

    if (!$actorId || !$targetId) {
        respond(400, ['success'=>false,'error' => 'IDs inválidos']);
    }

    // Intentar localizar registro en ambas direcciones
    $row = null;
    $dir = null;

    // Dirección 1: actor es cuidador
    if ($stmt = $conn->prepare('SELECT id, estado FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? LIMIT 1')) {
        $stmt->bind_param('ss', $actorId, $targetId);
        if ($stmt->execute()) { 
            $res = $stmt->get_result(); 
            if ($tmp=$res->fetch_assoc()) { 
                $row=$tmp; 
                $dir='cuidador'; 
            } 
        }
    }
    
    // Dirección 2: actor es paciente (solo si no se halló antes)
    if (!$row) {
        if ($stmt = $conn->prepare('SELECT id, estado FROM cuidadores WHERE paciente_id = ? AND cuidador_id = ? LIMIT 1')) {
            $stmt->bind_param('ss', $actorId, $targetId);
            if ($stmt->execute()) { 
                $res = $stmt->get_result(); 
                if ($tmp=$res->fetch_assoc()) { 
                    $row=$tmp; 
                    $dir='paciente'; 
                } 
            }
        }
    }

    if (!$row) {
        respond(404, ['success'=>false,'error'=>'Vínculo no encontrado']);
    }

    // Procesar desvinculación - siempre eliminar el registro
    $del = $conn->prepare('DELETE FROM cuidadores WHERE id = ?');
    if (!$del) throw new Exception('Prepare delete fallo: '.$conn->error);
    $del->bind_param('i', $row['id']);
    if(!$del->execute()) throw new Exception('Execute delete fallo: '.$del->error);
    
    if ($del->affected_rows > 0) {
        respond(200, ['success'=>true,'desvinculado'=>$targetId,'modo'=>'eliminado','direccion'=>$dir,'estado_anterior'=>$row['estado']]);
    } else {
        respond(500, ['success'=>false,'error'=>'No se pudo eliminar el vínculo']);
    }
} catch (Throwable $e) {
    // Log más detallado para debug
    error_log('Error en unlink_vinculo.php: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    ob_clean(); // Asegurar que no haya output previo
    respond(500, ['success'=>false,'error' => 'Error interno', 'detail' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
