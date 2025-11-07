<?php
session_start();

// Obtener el user_id antes de destruir la sesión para limpiar tokens de "recordarme"
$user_id = $_SESSION['user_id'] ?? null;

// Destruir la sesión
session_destroy();

// Eliminar la cookie de "recordarme" si existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Invalidar tokens de "recordarme" en la base de datos si tenemos el user_id
if ($user_id) {
    try {
        require_once 'conexion.php';
        if (isset($conn) && $conn instanceof mysqli) {
            $sql = "DELETE FROM password_reset_tokens WHERE user_id = ? AND token LIKE 'remember_%'";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
            }
        }
    } catch (Exception $e) {
        // Silencioso: si hay error en BD, al menos eliminamos la cookie
    }
}

header("Location: login.php");
exit();
?>