
<?php
require_once 'session_init.php';
require_once 'conexion.php';
requireAuth();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['nombre'])) {
        throw new Exception('Datos incompletos');
    }

    $alarmaId = intval($data['id']);
    $nuevoNombre = $data['nombre'];
    $userId = $_SESSION['user_id'];

    // Actualizar el nombre de la alarma
    $sql = "UPDATE alarmas SET nombre_alarma = ? WHERE id_alarma = ? AND modificado_por = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $nuevoNombre, $alarmaId, $userId);
    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Nombre actualizado correctamente']);
    } else {
        throw new Exception('Error al actualizar el nombre');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}