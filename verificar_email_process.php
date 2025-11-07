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

// Obtener código de verificación
$verificationCode = trim($_POST['verificationCode'] ?? '');

// Validaciones básicas
if (empty($verificationCode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Código de verificación requerido']);
    exit;
}

if (strlen($verificationCode) !== 6 || !ctype_digit($verificationCode)) {
    http_response_code(400);
    echo json_encode(['error' => 'El código debe tener 6 dígitos numéricos']);
    exit;
}

try {
    app_log('[VerifyEmail] Inicio', ['user_id' => $userId, 'code' => $verificationCode]);
    
    // Buscar código de verificación válido
    $sql = "SELECT new_email FROM email_verification WHERE id_usuario = ? AND verification_code = ? AND expires_at > NOW()";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare check verification: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $userId, $verificationCode);
    if (!$stmt->execute()) {
        throw new Exception('Execute check verification: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        app_log('[VerifyEmail] Código inválido o expirado', ['user_id' => $userId, 'code' => $verificationCode]);
        http_response_code(400);
        echo json_encode(['error' => 'Código de verificación incorrecto o expirado']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $newEmail = $row['new_email'];
    
    // Verificar nuevamente que el email no esté en uso (por si acaso)
    $sql = "SELECT id_usuario FROM usuarios WHERE email_usuario = ? AND id_usuario != ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare check email exists: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $newEmail, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute check email exists: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'El correo electrónico ya está en uso por otro usuario']);
        exit;
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Actualizar el email del usuario
    $sql = "UPDATE usuarios SET email_usuario = ? WHERE id_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare update email: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $newEmail, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute update email: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No se pudo actualizar el email');
    }
    
    // Eliminar el código de verificación usado
    $sql = "DELETE FROM email_verification WHERE id_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare delete verification: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute delete verification: ' . $stmt->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Actualizar la sesión con el nuevo email
    $_SESSION['email'] = $newEmail;
    
    app_log('[VerifyEmail] Email cambiado exitosamente', [
        'user_id' => $userId, 
        'new_email' => $newEmail
    ]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Correo electrónico cambiado exitosamente',
        'redirect' => 'mi_cuenta.php'
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    app_log('[VerifyEmail] Error', ['user_id' => $userId, 'message' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor. Por favor intenta de nuevo.']);
}
?>