<?php
require_once 'session_init.php';
require_once 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']);
    exit;
}
$val = $_POST['enabled'] ?? '';
$enabled = ($val==='1'||$val===1||$val==='true'||$val===true)?1:0;
$userId = $_SESSION['user_id'];
try {
    if(!$conn) throw new Exception('DB no disponible');
    $stmt = $conn->prepare("INSERT INTO configuracion_usuario (id_usuario, formato_hora_config, modo_oscuro_config, cuidador_flag_config, notificaciones_config)
        VALUES (?, 0, ?, 0, 1)
        ON DUPLICATE KEY UPDATE modo_oscuro_config = VALUES(modo_oscuro_config)");
    if(!$stmt) throw new Exception('Prepare fallo: '. $conn->error);
    $stmt->bind_param('si', $userId, $enabled);
    if(!$stmt->execute()) throw new Exception('Execute fallo: '.$stmt->error);
    echo json_encode(['success'=>true,'darkMode'=>$enabled]);
} catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Error interno','detail'=>$e->getMessage()]);
}
