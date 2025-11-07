<?php
// Configuración básica
header('Content-Type: application/json');

// Validar que es una solicitud POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener y sanitizar el email
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

// Validar el email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email no válido']);
    exit;
}

// Configurar los detalles del email
$to = $email;
$subject = "Recuperación de contraseña - Elderly Care";
$message = "
<html>
<head>
    <title>Recuperación de contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #c154c1; color: white; padding: 10px; text-align: center; }
        .content { padding: 20px; }
        .footer { margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Elderly Care</h1>
        </div>
        <div class='content'>
            <h2>Recuperación de contraseña</h2>
            <p>Hemos recibido una solicitud para restablecer la contraseña asociada a este email.</p>
            <p>Si no realizaste esta solicitud, por favor ignora este mensaje.</p>
            <p>Para continuar con el proceso, haz clic en el siguiente enlace:</p>
            <p><a href='https://tudominio.com/reset-password?token=abc123' style='background-color: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer contraseña</a></p>
        </div>
        <div class='footer'>
            <p>© ".date('Y')." Elderly Care. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
";

// Cabeceras para email HTML
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: no-reply@elderlycare.com\r\n";
$headers .= "Reply-To: soporte@elderlycare.com\r\n";
$headers .= "X-Mailer: PHP/".phpversion();

// Intentar enviar el email
if (mail($to, $subject, $message, $headers)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al enviar el correo']);
}
?>