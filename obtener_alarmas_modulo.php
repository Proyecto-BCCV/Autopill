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

// Obtener el número de módulo desde la URL
$modulo = isset($_GET['modulo']) ? intval($_GET['modulo']) : 1;

// Validar que el módulo esté entre 1 y 5
if ($modulo < 1 || $modulo > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Módulo inválido']);
    exit;
}

// Obtener todas las alarmas del módulo específico para este usuario
$userId = $_SESSION['user_id'];
$sql = "SELECT a.*, c.nombre_esp, m.nombre_medicamento, m.cantidad_pastillas_modulo
    FROM alarmas a 
    INNER JOIN codigos_esp c ON a.id_esp_alarma = c.id_esp 
    LEFT JOIN modulos m ON m.id_usuario = c.id_usuario AND m.numero_modulo = ?
    WHERE c.id_usuario = ? 
    AND a.nombre_alarma LIKE ?
    ORDER BY a.hora_alarma ASC";

$stmt = $conn->prepare($sql);
$moduloPattern = "Módulo " . $modulo;
$stmt->bind_param("iss", $modulo, $userId, $moduloPattern);
$stmt->execute();
$result = $stmt->get_result();

$alarmas = [];
$cantidadPastillas = null;
while ($row = $result->fetch_assoc()) {
    // Capturar cantidad de pastillas (será la misma para todas las alarmas del módulo)
    if ($cantidadPastillas === null && isset($row['cantidad_pastillas_modulo'])) {
        $cantidadPastillas = $row['cantidad_pastillas_modulo'];
    }
    
    $alarmas[] = [
        'id_alarma' => $row['id_alarma'],
        'nombre_alarma' => $row['nombre_alarma'],
        'nombre_medicamento' => isset($row['nombre_medicamento']) ? $row['nombre_medicamento'] : null,
        'hora_alarma' => $row['hora_alarma'],
        'dias_semana' => $row['dias_semana'],
        'id_esp_alarma' => $row['id_esp_alarma'],
        'nombre_esp' => $row['nombre_esp']
    ];
}

echo json_encode([
    'success' => true,
    'modulo' => $modulo,
    'alarmas' => $alarmas,
    'cantidad_pastillas' => $cantidadPastillas,
    'total_alarmas' => count($alarmas)
]);
?> 