<?php
require_once "session_init.php";
require_once "conexion.php";

// Verificar si el usuario está autenticado
requireAuth();

$userId = getUserId();
$userRole = getUserRole();

// Verificar si hay un proceso de cambio de email pendiente
$newEmail = '';
if ($conn) {
    if ($stmt = $conn->prepare("SELECT new_email FROM email_verification WHERE id_usuario = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1")) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $newEmail = $row['new_email'];
            }
        }
    }
}

// Si no hay proceso pendiente, redirigir
if (empty($newEmail)) {
    header('Location: mi_cuenta.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cambio de Correo - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        .verification-page {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            min-height: 100vh;
        }

        .verification-header {
            display: flex;
            align-items: center;
            margin-top: 60px;
            margin-bottom: 40px;
            padding: 0 10px;
        }

        .back-button {
            background: none;
            border: none;
            font-size: 24px;
            color: #C154C1;
            cursor: pointer;
            padding: 8px;
            margin-right: 15px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: rgba(193, 84, 193, 0.1);
        }

        .verification-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
            flex: 1;
            text-align: center;
            margin-right: 40px;
        }

        .verification-form {
            padding: 0 10px;
        }

        .info-message {
            background: #d1ecf1;
            color: #0c5460;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 14px;
            text-align: center;
            line-height: 1.5;
        }

        .new-email-display {
            font-weight: 600;
            color: #C154C1;
        }

        .verification-input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .verification-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 20px;
            background: #f8f9fa;
            box-sizing: border-box;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            height: 60px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 4px;
        }

        .verification-input:focus {
            outline: none;
            border-color: #C154C1;
            background: white;
        }

        .submit-button {
            width: 100%;
            background: linear-gradient(135deg, #C154C1, #9c44c4);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #a844a8, #8a3ab0);
            transform: translateY(-1px);
        }

        .submit-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .resend-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #C154C1;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: #C154C1;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .countdown {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 10px;
        }

        /* Modo oscuro */
        .dark-mode .verification-page {
            background: #1a1a1a;
        }

        .dark-mode .verification-title {
            color: #ffffff;
        }

        .dark-mode .verification-input {
            background: #2d2d2d;
            border-color: #555;
            color: #ffffff;
        }

        .dark-mode .verification-input:focus {
            background: #333;
            border-color: #C154C1;
        }

        .dark-mode .back-link {
            color: #b0b0b0;
        }

        .dark-mode .back-link:hover {
            color: #C154C1;
        }

        .dark-mode .info-message {
            background: #1e3a3e;
            color: #7dd3fc;
        }

        .dark-mode .countdown {
            color: #b0b0b0;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .verification-page {
                padding: 15px;
                max-width: 100%;
            }
            
            .verification-header {
                margin-top: 70px;
                margin-bottom: 30px;
            }
            
            .verification-title {
                font-size: 16px;
            }
            
            .verification-input {
                font-size: 18px;
                height: 56px;
                letter-spacing: 3px;
            }
        }
    </style>
</head>
<body>
    <?php $menuHideDashboard = ($userRole === 'cuidador'); $menuLogoHref = ($userRole === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="verification-page">
        <div class="verification-header">
            <button class="back-button" onclick="window.location.href='mi_cuenta.php'">‹</button>
            <h1 class="verification-title">Verificar Correo</h1>
        </div>

        <form class="verification-form" id="verificationForm">
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>
            
            <div class="info-message">
                Se ha enviado un código de 6 dígitos a:<br>
                <span class="new-email-display"><?php echo htmlspecialchars($newEmail); ?></span>
            </div>

            <div class="verification-input-group">
                <input 
                    type="text" 
                    id="verificationCode" 
                    name="verificationCode" 
                    class="verification-input" 
                    placeholder="000000"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    autocomplete="off"
                >
            </div>

            <button type="submit" class="submit-button" id="submitBtn">Verificar Código</button>
            
            <a href="#" class="resend-link" id="resendLink" onclick="resendCode()">Reenviar código</a>
            <div class="countdown" id="countdown"></div>
            
            <a href="mi_cuenta.php" class="back-link">Cancelar</a>
        </form>
    </main>

    <script>
        let resendCooldown = 60; // 1 minuto
        let countdownInterval;

        // Formatear input para solo números
        document.getElementById('verificationCode').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 6) value = value.slice(0, 6);
            e.target.value = value;
        });

        // Manejo del formulario
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const verificationCode = document.getElementById('verificationCode').value.trim();
            
            // Limpiar mensajes previos
            hideMessages();
            
            // Validaciones
            if (!verificationCode) {
                showError('Por favor ingresa el código de verificación');
                return;
            }
            
            if (verificationCode.length !== 6) {
                showError('El código debe tener 6 dígitos');
                return;
            }
            
            if (!/^\d{6}$/.test(verificationCode)) {
                showError('El código debe contener solo números');
                return;
            }
            
            // Enviar al servidor
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verificando...';
            
            const formData = new FormData();
            formData.append('verificationCode', verificationCode);
            
            fetch('verificar_email_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message || 'Correo cambiado exitosamente');
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = data.redirect || 'mi_cuenta.php';
                    }, 2000);
                } else {
                    showError(data.error || 'Código de verificación incorrecto');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error de conexión. Por favor intenta de nuevo.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        function resendCode() {
            if (resendCooldown > 0) return;
            
            const resendLink = document.getElementById('resendLink');
            resendLink.style.pointerEvents = 'none';
            resendLink.textContent = 'Reenviando...';
            
            fetch('reenviar_codigo_email.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Código reenviado exitosamente');
                    startResendCooldown();
                } else {
                    showError(data.error || 'Error al reenviar el código');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error al reenviar el código');
            })
            .finally(() => {
                resendLink.style.pointerEvents = 'auto';
                resendLink.textContent = 'Reenviar código';
            });
        }

        function startResendCooldown() {
            resendCooldown = 60;
            const resendLink = document.getElementById('resendLink');
            const countdownDiv = document.getElementById('countdown');
            
            resendLink.style.pointerEvents = 'none';
            resendLink.style.color = '#999';
            
            countdownInterval = setInterval(() => {
                countdownDiv.textContent = `Podrás reenviar en ${resendCooldown} segundos`;
                resendCooldown--;
                
                if (resendCooldown < 0) {
                    clearInterval(countdownInterval);
                    resendLink.style.pointerEvents = 'auto';
                    resendLink.style.color = '#C154C1';
                    countdownDiv.textContent = '';
                }
            }, 1000);
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
        }

        function hideMessages() {
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
        }

        // Iniciar cooldown al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            startResendCooldown();
            
            // Aplicar modo oscuro
            const savedMode = localStorage.getItem('darkMode');
            const darkModeToggle = document.getElementById('darkModeToggle');
            
            if (darkModeToggle) {
                if (savedMode === 'enabled') {
                    document.body.classList.add('dark-mode');
                    darkModeToggle.checked = true;
                } else {
                    document.body.classList.remove('dark-mode');
                    darkModeToggle.checked = false;
                }
                
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'enabled');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'disabled');
                    }
                });
            }

            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', function(e) {
                const mobileMenu = document.querySelector('.mobile-menu');
                const menuBtn = document.querySelector('.mobile-menu-btn');
                if (mobileMenu && mobileMenu.classList.contains('active') && 
                    !mobileMenu.contains(e.target) && 
                    !menuBtn.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
