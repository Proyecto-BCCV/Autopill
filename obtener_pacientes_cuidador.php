<?php
require_once 'session_init.php';
require_once 'conexion.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isAuthenticated() || !isCuidador()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = getUserId();
$pendientes = [];
$activos = [];

$sql = "SELECT c.id, c.paciente_id, c.estado, c.fecha_creacion, u.nombre_usuario, u.email_usuario, u.last_seen,
    TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) as segundos_inactivo
    FROM cuidadores c 
    INNER JOIN usuarios u ON c.paciente_id = u.id_usuario 
    WHERE c.cuidador_id = ? 
    ORDER BY c.fecha_creacion DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Formatear fecha para mostrar igual que en el dashboard
    $row['fecha_creacion'] = date("d/m/Y H:i", strtotime($row['fecha_creacion']));
    
    // Calcular estado en vivo con umbral explícito (120s)
    $isActive = false;
    if (function_exists('isUserActive')) {
        $isActive = isUserActive($row['paciente_id'], 120);
    }
    
    $row['estado_vivo'] = $isActive ? 'activo' : 'inactivo';
    
    // Limpiar campos internos antes de enviar
    unset($row['last_seen'], $row['segundos_inactivo']);
    
    if ($row['estado'] === 'activo') {
        $activos[] = $row;
    } else {
        $pendientes[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'pendientes' => $pendientes,
    'activos' => $activos
]);
