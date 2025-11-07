<?php
require_once 'session_init.php';
require_once 'conexion.php';

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

// Obtener datos del JSON

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;
$deleteAfter = !empty($input['delete_after']);

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de notificación requerido']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    if ($deleteAfter) {
        // Eliminar la notificación después de leerla
        $sql = "DELETE FROM notificaciones WHERE id_notificacion = ? AND id_usuario_destinatario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $notificationId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Notificación leída y eliminada']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Notificación no encontrada']);
        }
    } else {
        // Solo marcar como leída
        $sql = "UPDATE notificaciones SET leida = 1 
                WHERE id_notificacion = ? AND id_usuario_destinatario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $notificationId, $userId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Notificación marcada como leída']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Notificación no encontrada o ya leída']);
            }
        } else {
            throw new Exception('Error al actualizar notificación');
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
