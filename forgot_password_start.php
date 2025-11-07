<?php
/**
 * forgot_password_start.php
 * Procesa la solicitud de recuperación de contraseña
 * Genera un token y envía un email con el enlace de reseteo
 */

require_once 'session_init.php';
require_once 'conexion.php';
require_once 'email_config.php';
require_once 'email_service.php';
require_once 'logger.php';

header('Content-Type: application/json');

try {
    // Verificar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener email del formulario
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email requerido']);
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    // Verificar si el usuario existe
    $stmt = $conn->prepare("SELECT id_usuario, nombre_usuario FROM usuarios WHERE email_usuario = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Por seguridad, no revelar si el email existe o no
        echo json_encode(['success' => true, 'message' => 'Si el email existe, recibirás un enlace de recuperación']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id_usuario'];
    $userName = $user['nombre_usuario'];
    $stmt->close();

    // Invalidar todos los tokens anteriores del usuario para evitar conflictos
    $stmtInvalidate = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    $stmtInvalidate->bind_param('s', $userId);
    $stmtInvalidate->execute();
    $stmtInvalidate->close();

    // Generar token único
    $token = bin2hex(random_bytes(32));
    
    // Calcular fecha de expiración (1 hora desde ahora)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Guardar token en la base de datos
    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param('sss', $userId, $token, $expiresAt);
    
    if (!$stmt->execute()) {
        app_log('[ForgotPassword] Error al guardar token', ['user_id' => $userId, 'error' => $stmt->error]);
        throw new Exception('Error al procesar la solicitud');
    }
    $stmt->close();

    // Verificar que EMAIL_BASE_URL esté definido
    if (!defined('EMAIL_BASE_URL')) {
        app_log('[ForgotPassword] EMAIL_BASE_URL no definido', ['user_id' => $userId]);
        throw new Exception('Error de configuración del servidor. Por favor, contacta al administrador.');
    }

    // Construir URL de reseteo (debe ir primero a validate_reset_link.php)
    $resetUrl = EMAIL_BASE_URL . '/validate_reset_link.php?token=' . urlencode($token);
    
    // Crear el HTML del correo
    $subject = 'AutoPill - Recuperar Contraseña';
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #C154C1, #9b44c5); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .content { padding: 30px 20px; }
        .button { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #C154C1, #9b44c5); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
        .button:hover { background: linear-gradient(135deg, #a844a8, #8a3ab0); }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; }
        .footer a { color: #C154C1; text-decoration: none; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; border-radius: 4px; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recuperar Contraseña</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en AutoPill.</p>
            
            <p>Para crear una nueva contraseña, haz clic en el siguiente botón:</p>
            
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" class="button">Restablecer Contraseña</a>
            </p>
            
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 13px;">
                ' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '
            </p>
            
            <div class="warning">
                <strong>Importante:</strong> Este enlace expirará en 1 hora por seguridad.
            </div>
            
            <p style="color: #666; font-size: 14px; margin-top: 30px;">
                Si no solicitaste restablecer tu contraseña, puedes ignorar este correo de forma segura.
            </p>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático de AutoPill</p>
            <p>No respondas a este correo</p>
            <p style="margin-top: 15px;">
                <a href="https://pastillero.webhop.net">Ir a AutoPill</a>
            </p>
        </div>
    </div>
</body>
</html>';

    // Enviar el email
    $emailResult = send_email($email, $subject, $html);
    
    if (!$emailResult['success']) {
        app_log('[ForgotPassword] Error al enviar email', [
            'user_id' => $userId, 
            'email' => $email,
            'error' => $emailResult['error'] ?? 'Desconocido'
        ]);
        throw new Exception('Error al enviar el correo. Por favor, intenta de nuevo más tarde.');
    }

    app_log('[ForgotPassword] Email enviado exitosamente', [
        'user_id' => $userId,
        'email' => $email,
        'token_expires' => $expiresAt
    ]);

    // Guardar el email en sesión para mostrarlo en verificar-codigo.php
    $_SESSION['pwd_reset_email'] = $email;
    
    // Forzar que la sesión se escriba inmediatamente
    session_write_close();
    // Reiniciar la sesión para poder seguir usándola si es necesario
    session_start();

    echo json_encode([
        'success' => true,
        'message' => 'Se ha enviado un enlace de recuperación a tu email'
    ]);

} catch (Exception $e) {
    app_log('[ForgotPassword] Excepción capturada', ['error' => $e->getMessage()]);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
