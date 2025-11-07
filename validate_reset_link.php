<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'logger.php';

// Respuesta HTML mínima + redirección
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(400);
    echo 'Token inválido';
    exit;
}

// Validar token vigente y no usado
$stmt = $conn->prepare("SELECT user_id, expires_at, used FROM password_reset_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $userId = $row['user_id'];
    // Chequear expiración y usado
    $expired = (strtotime($row['expires_at']) <= time());
    $used = ((int)$row['used'] === 1);
    if ($expired || $used) {
        app_log('[PwdReset] token rechazado', ['reason' => $expired ? 'expired' : 'used', 'token' => substr($token,0,24) . '...']);
        http_response_code(400);
        echo 'Token inválido o expirado. Intenta generar un nuevo enlace.';
        exit;
    }

    // NO marcar como usado aún - se marcará en set_new_password después de cambiar la contraseña
    
    // Limpiar cualquier sesión de reseteo anterior
    unset($_SESSION['pwd_reset_verified_user']);
    unset($_SESSION['pwd_reset_token']);
    unset($_SESSION['pwd_reset_email']);
    
    // Preparar sesión para permitir set_new_password
    $_SESSION['pwd_reset_verified_user'] = $userId;
    $_SESSION['pwd_reset_token'] = $token; // Guardar token para marcarlo como usado después
    app_log('[PwdReset] token validado', ['user_id' => $userId]);

    // Redirigir a nueva-password.php
    header('Location: nueva-password.php');
    exit;
}

app_log('[PwdReset] token no encontrado', ['token' => substr($token,0,24) . '...']);
http_response_code(400);
?>Token inválido o expirado. Intenta generar un nuevo enlace.
