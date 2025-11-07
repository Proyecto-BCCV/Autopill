<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
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

    $userId = $_SESSION['user_id'];
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? null;

    if ($key !== 'formato_hora_config') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Clave no soportada']);
        exit;
    }
    $valNum = ($value === '1' || $value === 1 || $value === true || $value === 'true') ? 1 : 0; // 0=12h, 1=24h

    // Asegurar existencia de la fila y luego actualizar
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO configuracion_usuario (id_usuario, formato_hora_config, modo_oscuro_config, cuidador_flag_config, notificaciones_config)
                             VALUES (?, ?, 0, 0, 1)
                             ON DUPLICATE KEY UPDATE formato_hora_config = VALUES(formato_hora_config)");
    if (!$stmt) { throw new Exception('Prepare fallo: ' . $conn->error); }
    $stmt->bind_param('si', $userId, $valNum);
    if (!$stmt->execute()) { throw new Exception('Execute fallo: ' . $stmt->error); }
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Configuración actualizada', 'formato24' => $valNum]);
} catch (Exception $e) {
    if ($conn && $conn->errno === 0) { $conn->rollback(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno', 'detail' => $e->getMessage()]);
}
?>
