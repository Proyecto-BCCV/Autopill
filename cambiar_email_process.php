<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'email_service.php';
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
$currentUserEmail = getUserEmail();

// Obtener datos del formulario
$currentEmail = trim($_POST['currentEmail'] ?? '');
$newEmail = trim($_POST['newEmail'] ?? '');
$confirmEmail = trim($_POST['confirmEmail'] ?? '');

// Validaciones básicas
if (empty($currentEmail) || empty($newEmail) || empty($confirmEmail)) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son requeridos']);
    exit;
}

// Validar formato de email
if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Por favor ingresa un email válido']);
    exit;
}

// Validar que los emails coincidan
if ($newEmail !== $confirmEmail) {
    http_response_code(400);
    echo json_encode(['error' => 'Los correos electrónicos no coinciden']);
    exit;
}

// Validar que el email actual sea correcto
if ($currentEmail !== $currentUserEmail) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo actual no coincide']);
    exit;
}

// Validar que el nuevo email sea diferente
if ($newEmail === $currentEmail) {
    http_response_code(400);
    echo json_encode(['error' => 'El nuevo correo debe ser diferente al actual']);
    exit;
}

try {
    app_log('[ChangeEmail] Inicio', ['user_id' => $userId, 'current_email' => $currentEmail, 'new_email' => $newEmail]);
    
    // Verificar/crear tabla email_change_tokens si no existe
    $createTableSql = "CREATE TABLE IF NOT EXISTS `email_change_tokens` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(255) NOT NULL,
      `new_email` varchar(255) NOT NULL,
      `token` varchar(255) NOT NULL,
      `expires_at` datetime NOT NULL,
      `used` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_token` (`token`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($createTableSql)) {
        throw new Exception('Error al crear tabla email_change_tokens: ' . $conn->error);
    }
    
    // Verificar que el nuevo email no esté en uso
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
        echo json_encode(['error' => 'El correo electrónico ya está registrado por otro usuario']);
        exit;
    }
    
    // Generar token para enlace de verificación (similar a forgot password)
    try {
        $rawToken = bin2hex(random_bytes(32));
    } catch (Exception $ex) {
        $rawToken = bin2hex(openssl_random_pseudo_bytes(32));
    }
    $token = 'email_change_' . $rawToken;
    $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60); // Expira en 30 minutos
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Eliminar tokens de verificación previos para este usuario
    $sql = "DELETE FROM email_change_tokens WHERE user_id = ?";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare delete old tokens: ' . $conn->error);
    }
    $stmt->bind_param("s", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Execute delete old tokens: ' . $stmt->error);
    }
    
    // Insertar nuevo token de verificación
    $sql = "INSERT INTO email_change_tokens (user_id, new_email, token, expires_at, used) VALUES (?, ?, ?, ?, 0)";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare insert verification: ' . $conn->error);
    }
    
    $stmt->bind_param("ssss", $userId, $newEmail, $token, $expiresAt);
    if (!$stmt->execute()) {
        throw new Exception('Execute insert verification: ' . $stmt->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    app_log('[ChangeEmail] Token creado', ['user_id' => $userId, 'new_email' => $newEmail, 'token' => $token, 'expires' => $expiresAt]);
    
    // Construir enlace absoluto de verificación
    if (!defined('EMAIL_BASE_URL')) { require_once 'email_config.php'; }
    $base = rtrim(EMAIL_BASE_URL, '/');
    $link = $base . '/validate_email_change.php?token=' . urlencode($token);
    
    $subject = 'Verificar cambio de correo electrónico - Autopill';
    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6">'
          . '<h2>Cambio de correo electrónico</h2>'
          . '<p>Has solicitado cambiar tu correo electrónico en Autopill.</p>'
          . '<p><strong>Nuevo correo:</strong> ' . htmlspecialchars($newEmail) . '</p>'
          . '<p>Haz clic en el siguiente botón para confirmar el cambio. El enlace es válido por 30 minutos.</p>'
          . '<p><a href="' . htmlspecialchars($link) . '" style="background:#C154C1;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;display:inline-block;">Confirmar cambio de correo</a></p>'
          . '<p style="font-size:12px;color:#666">Si el botón no funciona, copia y pega este enlace en tu navegador:<br>' . htmlspecialchars($link) . '</p>'
          . '<p><strong>Importante:</strong> Si no solicitaste este cambio, ignora este mensaje o cambia tu contraseña inmediatamente.</p>'
          . '</div>';
    
    $sendResult = send_email($currentEmail, $subject, $html);
    app_log('[Email] resultado envío cambio email', ['email' => $currentEmail, 'new_email' => $newEmail, 'result' => $sendResult]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Se ha enviado un enlace de verificación a tu correo actual (' . $currentEmail . '). Revisa tu bandeja de entrada.',
        'email_sent' => $sendResult['success'] ?? false
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    app_log('[ChangeEmail] Error', ['user_id' => $userId, 'message' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor. Por favor intenta de nuevo.']);
}
?>