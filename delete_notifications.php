<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = $input['ids'] ?? null; // array opcional de IDs a borrar
$notificationId = $input['notification_id'] ?? null; // ID individual
$onlyRead = $input['onlyRead'] ?? true; // por defecto borrar solo leídas

try {
    $userId = $_SESSION['user_id'];
    
    // Si se proporciona un ID individual
    if ($notificationId !== null) {
        $sql = "DELETE FROM notificaciones WHERE id_notificacion = ? AND id_usuario_destinatario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $notificationId, $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
    } else if (is_array($ids) && count($ids) > 0) {
        // Borrar solo los IDs indicados que pertenezcan al usuario
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids)) . 's';
        $params = $ids;
        $params[] = $userId;
        $sql = "DELETE FROM notificaciones WHERE id_notificacion IN ($placeholders) AND id_usuario_destinatario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
    } else {
        // Borrar por estado de lectura
        if ($onlyRead) {
            $sql = "DELETE FROM notificaciones WHERE id_usuario_destinatario = ? AND leida = 1";
        } else {
            $sql = "DELETE FROM notificaciones WHERE id_usuario_destinatario = ?";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>
