<?php
// Configuración de respuesta JSON
header('Content-Type: application/json');

// Inicializar array de respuesta
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Verificar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener y validar datos del formulario
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    // Validar campos obligatorios
    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }

    if (empty($email)) {
        throw new Exception('El email es obligatorio');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }

    if (empty($comentario)) {
        throw new Exception('El comentario es obligatorio');
    }

    // Conectar a la base de datos
    require_once 'conexion.php';

    // Preparar la consulta SQL para insertar el contacto
    $stmt = $conn->prepare("INSERT INTO contactos (nombre, email, comentario, fecha_creacion) VALUES (?, ?, ?, NOW())");
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta');
    }

    $stmt->bind_param("sss", $nombre, $email, $comentario);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Mensaje enviado correctamente. Te responderemos pronto.';
    } else {
        throw new Exception('Error al guardar el mensaje');
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>