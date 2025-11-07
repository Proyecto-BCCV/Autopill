<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    if (!$conn) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }
    $userId = $_SESSION['user_id'];
    
    // Contar TODAS las notificaciones no leídas sin categorías
    $sql = "SELECT COUNT(*) AS cnt FROM notificaciones WHERE id_usuario_destinatario = ? AND leida = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = (int)($result['cnt'] ?? 0);

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>
