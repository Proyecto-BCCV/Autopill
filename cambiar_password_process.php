<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'logger.php';

// Configurar respuesta como JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar que el usuario esté autenticado
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$userId = getUserId();

// Obtener datos del formulario
$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validaciones básicas
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son requeridos']);
    exit;
}

// Validar nueva contraseña
if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña debe tener al menos 8 caracteres']);
    exit;
}

if (!preg_match('/\d/', $newPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña debe contener al menos un número']);
    exit;
}

if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña debe contener al menos un carácter especial']);
    exit;
}

// Validar que las contraseñas coincidan
if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'Las nuevas contraseñas no coinciden']);
    exit;
}

// Validar que la nueva contraseña sea diferente a la actual
if ($currentPassword === $newPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña debe ser diferente a la actual']);
    exit;
}

try {
    app_log('[ChangePassword] Inicio', ['user_id' => $userId]);
    
    // Verificar la contraseña actual
    $sql = "SELECT contrasena_usuario FROM autenticacion_local WHERE id_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare verify password: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute verify password: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado o sin autenticación local']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $currentHashedPassword = $row['contrasena_usuario'];
    
    // Verificar contraseña actual
    if (!password_verify($currentPassword, $currentHashedPassword)) {
        app_log('[ChangePassword] Contraseña actual incorrecta', ['user_id' => $userId]);
        http_response_code(400);
        echo json_encode(['error' => 'La contraseña actual es incorrecta']);
        exit;
    }
    
    // Hash de la nueva contraseña
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Actualizar contraseña
    $sql = "UPDATE autenticacion_local SET contrasena_usuario = ? WHERE id_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare update password: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $newHashedPassword, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute update password: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No se pudo actualizar la contraseña');
    }
    
    // Confirmar transacción
    $conn->commit();
    
    app_log('[ChangePassword] Contraseña cambiada exitosamente', ['user_id' => $userId]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Contraseña cambiada exitosamente'
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    app_log('[ChangePassword] Error', ['user_id' => $userId, 'message' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor. Por favor intenta de nuevo.']);
}
?>