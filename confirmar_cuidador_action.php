<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';

@ini_set('display_errors', 0);
if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
ob_start();

$finish = function(array $payload){
    $out = ob_get_clean();
    if ($out && trim($out) !== '') {
        // descartar ruido previo
        ob_clean();
    }
    echo json_encode($payload);
    exit;
};

if (!isAuthenticated()) {
    $finish(['success'=>false,'error'=>'No autorizado']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $finish(['success'=>false,'error'=>'Método no permitido']);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: 'null', true);
$requestId = $input['request_id'] ?? null;
$action = $input['action'] ?? null;

if ($requestId === null || $requestId === '' || $action === null || $action === '') {
    $finish(['success'=>false,'error'=>'Datos requeridos faltantes']);
}

if (!in_array($action, ['confirmar','rechazar','cancelar'], true)) {
    $finish(['success'=>false,'error'=>'Acción no válida']);
}

try {
    $userId = $_SESSION['user_id'];

    $transactionStarted = false;

    try {
        if ($action === 'cancelar') {
            // Solo cuidadores pueden cancelar sus propias solicitudes pendientes
            if (!isCuidador()) {
                $finish(['success'=>false,'error'=>'Solo cuidadores pueden cancelar solicitudes']);
            }

            if (!$conn->begin_transaction()) {
                throw new Exception('No se pudo iniciar la transacción');
            }
            $transactionStarted = true;

            // Verificar que la solicitud pertenezca al cuidador y esté pendiente
            $sql = "SELECT * FROM cuidadores WHERE id = ? AND cuidador_id = ? AND estado = 'pendiente' FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Fallo prepare SELECT');
            $stmt->bind_param('ss', $requestId, $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $conn->rollback();
                $transactionStarted = false;
                $finish(['success'=>false,'error'=>'Solicitud no encontrada o no pendiente']);
            }
            $request = $res->fetch_assoc();

            // Eliminar la solicitud
            $stmtDel = $conn->prepare('DELETE FROM cuidadores WHERE id = ?');
            if (!$stmtDel) throw new Exception('Fallo prepare DELETE');
            $stmtDel->bind_param('s', $requestId);
            if (!$stmtDel->execute()) throw new Exception('Error al eliminar solicitud');

            // Reconstruir notificaciones pendientes para el paciente afectado
            rebuildCaregiverRequestNotifications($request['paciente_id']);

            $conn->commit();
            $transactionStarted = false;

            $finish(['success'=>true,'message'=>'Solicitud cancelada']);
        } else {
            // confirmar / rechazar (lado del paciente)
            if (!$conn->begin_transaction()) {
                throw new Exception('No se pudo iniciar la transacción');
            }
            $transactionStarted = true;

            $sql = "SELECT * FROM cuidadores WHERE id = ? AND paciente_id = ? AND estado = 'pendiente' FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Fallo prepare SELECT');
            $stmt->bind_param('ss', $requestId, $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $conn->rollback();
                $transactionStarted = false;
                $finish(['success'=>false,'error'=>'Solicitud no encontrada']);
            }
            $request = $res->fetch_assoc();

            $newStatus = ($action === 'confirmar') ? 'activo' : 'rechazado';
            $stmt = $conn->prepare('UPDATE cuidadores SET estado = ? WHERE id = ?');
            if (!$stmt) throw new Exception('Fallo prepare UPDATE');
            $stmt->bind_param('ss', $newStatus, $requestId);
            if (!$stmt->execute()) throw new Exception('Error al actualizar solicitud');

            $notificationType = ($action === 'confirmar') ? 'Solicitud confirmada' : 'Solicitud rechazada';
            $notificationMessage = ($action === 'confirmar')
                ? 'Tu solicitud para ser cuidador ha sido confirmada'
                : 'Tu solicitud para ser cuidador ha sido rechazada';

            $stmt = $conn->prepare('INSERT INTO notificaciones (id_usuario_destinatario, id_usuario_origen, tipo_notificacion, mensaje, leida) VALUES (?, ?, ?, ?, 0)');
            if ($stmt) {
                $stmt->bind_param('ssss', $request['cuidador_id'], $userId, $notificationType, $notificationMessage);
                $stmt->execute();
            }

            // Reconstruir notificaciones pendientes para el paciente
            rebuildCaregiverRequestNotifications($userId);

            $conn->commit();
            $transactionStarted = false;
        }
    } catch (Exception $inner) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        throw $inner;
    }

    if ($action === 'confirmar') {
        $finish(['success'=>true,'message'=>'Cuidador confirmado exitosamente']);
    } elseif ($action === 'rechazar') {
        $finish(['success'=>true,'message'=>'Cuidador rechazado']);
    }
} catch (Throwable $e) {
    $finish(['success'=>false,'error'=>'Error interno del servidor']);
}
?>
