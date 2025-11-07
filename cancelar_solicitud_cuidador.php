<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!isCuidador()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo los cuidadores pueden cancelar solicitudes']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$payloadRaw = file_get_contents('php://input');
$payload = json_decode($payloadRaw ?: 'null', true);

$requestId = $payload['request_id'] ?? null;
$pacienteId = $payload['paciente_id'] ?? null;
$cuidadorId = getUserId();

if ((!$requestId || $requestId === '') && (!$pacienteId || $pacienteId === '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Debes indicar request_id o paciente_id']);
    exit;
}

try {
    $transactionStarted = false;
    if (!$conn->begin_transaction()) {
        throw new Exception('No se pudo iniciar la transacción');
    }
    $transactionStarted = true;

    if ($requestId) {
        $stmt = $conn->prepare('SELECT id, paciente_id FROM cuidadores WHERE id = ? AND cuidador_id = ? AND estado = \"pendiente\" FOR UPDATE');
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $conn->error);
        }
        $stmt->bind_param('ss', $requestId, $cuidadorId);
    } else {
        $stmt = $conn->prepare('SELECT id, paciente_id FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = \"pendiente\" FOR UPDATE');
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $conn->error);
        }
        $stmt->bind_param('ss', $cuidadorId, $pacienteId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'No se encontró una solicitud pendiente para cancelar']);
        exit;
    }

    $row = $res->fetch_assoc();
    $solicitudId = $row['id'];
    $pacienteId = $row['paciente_id'];

    $stmtDel = $conn->prepare('DELETE FROM cuidadores WHERE id = ?');
    if (!$stmtDel) {
        throw new Exception('Error preparando eliminación: ' . $conn->error);
    }
    $stmtDel->bind_param('s', $solicitudId);
    if (!$stmtDel->execute()) {
        throw new Exception('No se pudo eliminar la solicitud: ' . $stmtDel->error);
    }

    rebuildCaregiverRequestNotifications($pacienteId);

    $conn->commit();
    $transactionStarted = false;

    echo json_encode(['success' => true, 'message' => 'Solicitud cancelada']);
} catch (Exception $e) {
    if (isset($transactionStarted) && $transactionStarted) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
