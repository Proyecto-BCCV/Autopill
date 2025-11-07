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

try {
    app_log('[ResendEmailCode] Inicio', ['user_id' => $userId]);
    
    // Buscar proceso de cambio de email pendiente
    $sql = "SELECT new_email FROM email_verification WHERE id_usuario = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare check pending: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute check pending: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No hay proceso de cambio de email pendiente']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $newEmail = $row['new_email'];
    
    // Generar nuevo código de verificación
    $verificationCode = str_pad(strval(rand(0, 999999)), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expira en 1 hora
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Actualizar el código de verificación
    $sql = "UPDATE email_verification SET verification_code = ?, expires_at = ?, created_at = NOW() WHERE id_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare update code: ' . $conn->error);
    }
    
    $stmt->bind_param("sss", $verificationCode, $expiresAt, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute update code: ' . $stmt->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Enviar email con nuevo código
    $emailSent = false;
    try {
        require_once 'email_service.php';
        
        $subject = 'Nuevo código de verificación - Autopill';
        $message = "
        <h2>Nuevo código de verificación</h2>
        <p>Has solicitado un nuevo código para cambiar tu correo electrónico en Autopill.</p>
        <p><strong>Código de verificación:</strong> <span style='font-size: 24px; font-weight: bold; color: #C154C1;'>{$verificationCode}</span></p>
        <p>Este código expira en 1 hora.</p>
        <p>Si no solicitaste este cambio, ignora este correo.</p>
        <hr>
        <p style='color: #666; font-size: 12px;'>Autopill - Sistema de gestión de medicamentos</p>
        ";
        
        $emailSent = sendEmail($newEmail, $subject, $message);
        
        if ($emailSent) {
            app_log('[ResendEmailCode] Código reenviado', ['user_id' => $userId, 'new_email' => $newEmail]);
        } else {
            app_log('[ResendEmailCode] Error enviando email', ['user_id' => $userId, 'new_email' => $newEmail]);
        }
        
    } catch (Exception $emailError) {
        app_log('[ResendEmailCode] Error email service', ['error' => $emailError->getMessage()]);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => $emailSent 
            ? 'Nuevo código enviado exitosamente'
            : 'Código generado. Por favor contacta al administrador para obtenerlo.',
        'email_sent' => $emailSent
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    app_log('[ResendEmailCode] Error', ['user_id' => $userId, 'message' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor. Por favor intenta de nuevo.']);
}
?>