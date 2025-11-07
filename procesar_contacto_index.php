<?php
require_once 'session_init.php';
include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
    
    // Validar campos obligatorios
    if (empty($nombre) || empty($email) || empty($comentario)) {
        echo json_encode([
            'success' => false,
            'message' => 'Todos los campos son obligatorios.'
        ]);
        exit;
    }
    
    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor ingresa un email válido.'
        ]);
        exit;
    }
    
    // Insertar en la base de datos
    $query = "INSERT INTO contactos (nombre, email, comentario, fecha_envio) VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $nombre, $email, $comentario);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Mensaje enviado correctamente. Te responderemos pronto.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Hubo un error al enviar tu mensaje. Por favor intenta nuevamente.'
            ]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error en el servidor. Por favor intenta más tarde.'
        ]);
    }
    
    mysqli_close($conn);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}
?>
