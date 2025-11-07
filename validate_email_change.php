<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'logger.php';

// Respuesta HTML mínima + redirección
header('Content-Type: text/html; charset=utf-8');

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error - Autopill</title></head><body>';
    echo '<h2>Token inválido</h2>';
    echo '<p>El enlace de verificación no es válido.</p>';
    echo '<p><a href="mi_cuenta.php">Volver a mi cuenta</a></p>';
    echo '</body></html>';
    exit;
}

// Validar token vigente y no usado
$stmt = $conn->prepare("SELECT user_id, new_email, expires_at, used FROM email_change_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $userId = $row['user_id'];
    $newEmail = $row['new_email'];
    
    // Chequear expiración y uso
    $expired = (strtotime($row['expires_at']) <= time());
    $used = ((int)$row['used'] === 1);
    
    if ($expired || $used) {
        app_log('[EmailChange] token rechazado', ['reason' => $expired ? 'expired' : 'used', 'token' => substr($token, 0, 24) . '...']);
        http_response_code(400);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error - Autopill</title></head><body>';
        echo '<h2>Enlace expirado o ya usado</h2>';
        echo '<p>El enlace de verificación ha expirado o ya fue utilizado.</p>';
        echo '<p>Por favor, solicita un nuevo cambio de correo desde tu cuenta.</p>';
        echo '<p><a href="cambiar_email.php">Cambiar correo</a> | <a href="mi_cuenta.php">Mi cuenta</a></p>';
        echo '</body></html>';
        exit;
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();
        
        // Marcar token como usado
        $stmtU = $conn->prepare('UPDATE email_change_tokens SET used = 1 WHERE token = ?');
        $stmtU->bind_param('s', $token);
        if (!$stmtU->execute()) {
            throw new Exception('Error al marcar token como usado');
        }
        
        // Verificar una vez más que el nuevo email no esté en uso por otro usuario
        $stmtCheck = $conn->prepare('SELECT id_usuario FROM usuarios WHERE email_usuario = ? AND id_usuario != ?');
        $stmtCheck->bind_param('ss', $newEmail, $userId);
        if (!$stmtCheck->execute()) {
            throw new Exception('Error al verificar email duplicado');
        }
        
        $checkResult = $stmtCheck->get_result();
        if ($checkResult->num_rows > 0) {
            throw new Exception('El correo electrónico ya está en uso por otro usuario');
        }
        
        // Eliminar autenticación de Google vinculada al usuario (para evitar login con email anterior)
        $stmtDeleteGoogle = $conn->prepare('DELETE FROM autenticacion_google WHERE id_usuario = ?');
        $stmtDeleteGoogle->bind_param('s', $userId);
        $stmtDeleteGoogle->execute();
        
        app_log('[EmailChange] Google auth eliminada', ['user_id' => $userId]);
        
        // Actualizar el email del usuario
        $stmtUpdate = $conn->prepare('UPDATE usuarios SET email_usuario = ? WHERE id_usuario = ?');
        $stmtUpdate->bind_param('ss', $newEmail, $userId);
        if (!$stmtUpdate->execute()) {
            throw new Exception('Error al actualizar el correo electrónico');
        }
        
        // Confirmar transacción
        $conn->commit();
        
        app_log('[EmailChange] cambio exitoso', ['user_id' => $userId, 'new_email' => $newEmail]);
        
        // Actualizar la sesión si el usuario está logueado
        if (isAuthenticated() && getUserId() === $userId) {
            $_SESSION['email'] = $newEmail;
        }
        
        // Mostrar página de éxito
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Correo actualizado - Autopill</title>';
        echo '<style>body{font-family:Arial,sans-serif;max-width:500px;margin:50px auto;padding:20px;text-align:center;}';
        echo '.success{color:#155724;background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;}';
        echo '.btn{background:#C154C1;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;margin:10px;}';
        echo '.btn:hover{background:#a844a8;}</style></head><body>';
        echo '<h2>¡Correo electrónico actualizado!</h2>';
        echo '<div class="success">Tu correo electrónico ha sido cambiado exitosamente a:<br><strong>' . htmlspecialchars($newEmail) . '</strong></div>';
        echo '<p>Ya puedes utilizar tu nuevo correo electrónico para iniciar sesión.</p>';
        echo '<a href="mi_cuenta.php" class="btn">Ir a mi cuenta</a>';
        echo '<a href="dashboard.php" class="btn">Ir al dashboard</a>';
        echo '</body></html>';
        
    } catch (Exception $e) {
        // Revertir transacción
        $conn->rollback();
        
        app_log('[EmailChange] error al procesar', ['user_id' => $userId, 'error' => $e->getMessage()]);
        
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error - Autopill</title></head><body>';
        echo '<h2>Error interno</h2>';
        echo '<p>No se pudo completar el cambio de correo electrónico. Por favor, intenta de nuevo más tarde.</p>';
        echo '<p><a href="cambiar_email.php">Intentar de nuevo</a> | <a href="mi_cuenta.php">Mi cuenta</a></p>';
        echo '</body></html>';
    }
    
} else {
    app_log('[EmailChange] token no encontrado', ['token' => substr($token, 0, 24) . '...']);
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error - Autopill</title></head><body>';
    echo '<h2>Token inválido</h2>';
    echo '<p>El enlace de verificación no es válido o ha expirado.</p>';
    echo '<p><a href="cambiar_email.php">Solicitar nuevo cambio</a> | <a href="mi_cuenta.php">Mi cuenta</a></p>';
    echo '</body></html>';
}
?>