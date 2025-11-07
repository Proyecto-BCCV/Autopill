<?php
/*
 * Endpoint directo para enviar notificaciones ESP32 (sin URL rewrite)
 * Acceso: POST /send_notification_esp.php
 */

require_once 'conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }
    
    $espCode = $input['esp_code'] ?? '';
    $message = $input['message'] ?? '';
    $type = $input['type'] ?? 'notification';
    
    if (empty($espCode) || empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'esp_code y message son requeridos']);
        exit;
    }
    
    // Insertar notificación en la base de datos
    $stmt = $conn->prepare("
        INSERT INTO esp32_notifications (esp_code, message, type, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->bind_param("sss", $espCode, $message, $type);
    $stmt->execute();
    
    $notificationId = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'notification_id' => $notificationId,
        'esp_code' => $espCode,
        'message' => $message,
        'type' => $type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error sending notification: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor', 
        'details' => $e->getMessage()
    ]);
}
?>