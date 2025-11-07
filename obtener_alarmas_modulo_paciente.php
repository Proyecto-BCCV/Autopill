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

// Verificar que el usuario sea cuidador
if (!isCuidador()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo los cuidadores pueden acceder.']);
    exit;
}

// Obtener parámetros desde la URL
$modulo = $_GET['modulo'] ?? null;
$pacienteId = $_GET['paciente_id'] ?? null;

if (!$modulo || !$pacienteId) {
    http_response_code(400);
    echo json_encode(['error' => 'Módulo y ID de paciente requeridos']);
    exit;
}

// Verificar que el cuidador tenga acceso a este paciente
$cuidadorId = getUserId();
$sql = "SELECT * FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = 'activo'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $cuidadorId, $pacienteId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes acceso a este paciente']);
    exit;
}

try {
    // Obtener alarmas del módulo usando patrón del nombre y ESP del paciente (misma lógica que paciente propio)
    $sql = "SELECT a.*, m.nombre_medicamento, m.cantidad_pastillas_modulo FROM alarmas a
        INNER JOIN codigos_esp c ON a.id_esp_alarma = c.id_esp
        LEFT JOIN modulos m ON m.id_usuario = c.id_usuario AND m.numero_modulo = ?
        WHERE c.id_usuario = ? AND a.nombre_alarma LIKE ?
        ORDER BY a.hora_alarma";
    $stmt = $conn->prepare($sql);
    $moduloPattern = 'Módulo ' . intval($modulo);
    $stmt->bind_param("iss", $modulo, $pacienteId, $moduloPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alarmas = [];
    $cantidadPastillas = null;
    while ($row = $result->fetch_assoc()) {
        // Capturar cantidad de pastillas (será la misma para todas las alarmas del módulo)
        if ($cantidadPastillas === null && isset($row['cantidad_pastillas_modulo'])) {
            $cantidadPastillas = $row['cantidad_pastillas_modulo'];
        }
        $alarmas[] = $row; // incluye campos como nombre_medicamento si están presentes en la tabla
    }
    
    echo json_encode([
        'success' => true,
        'alarmas' => $alarmas,
        'cantidad_pastillas' => $cantidadPastillas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
