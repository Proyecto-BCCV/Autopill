<?php
/**
 * Endpoint para notificar al ESP32 que debe verificar su estado de vinculación
 * Se usa cuando se desvincula un ESP desde la web
 */

require_once 'conexion.php';
header('Content-Type: application/json');

// Verificar que se proporcionó el código del ESP
$espCode = $_GET['esp_code'] ?? $_POST['esp_code'] ?? '';

if (empty($espCode)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Código de ESP no proporcionado'
    ]);
    exit;
}

try {
    // Verificar que el ESP existe
    $stmt = $conn->prepare("SELECT id_esp, nombre_esp, id_usuario FROM codigos_esp WHERE nombre_esp = ? LIMIT 1");
    $stmt->bind_param("s", $espCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'ESP no encontrado'
        ]);
        exit;
    }
    
    $device = $result->fetch_assoc();
    
    // Responder con el estado actual del ESP
    // El ESP llamará a este endpoint periódicamente o cuando reciba una señal
    echo json_encode([
        'success' => true,
        'device_code' => $device['nombre_esp'],
        'device_id' => $device['id_esp'],
        'linked_user_id' => $device['id_usuario'], // Será null si está desvinculado
        'action' => 'recheck_linkage',
        'message' => $device['id_usuario'] ? 'ESP vinculado' : 'ESP desvinculado',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
