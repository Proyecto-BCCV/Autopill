<?php
require_once "session_init.php";
require_once "conexion.php";

// Verificar si el usuario está autenticado
requireAuth();

$userName = getUserName();
$userRole = getUserRole();
$userId = getUserId();
$userEmail = getUserEmail();
?>
<!DOCTYPE             <div class="info-message">
     
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Correo - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        .container { 
            max-width: 550px; 
            margin: 120px auto 40px; 
            background: var(--element-bg, #ffffff); 
            padding: 32px; 
            border-radius: 18px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .email-change-page {
            max-width: 100%;
            margin: 0 auto;
        }

        .email-header {
            display: flex;
            align-items: center;
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

        .email-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
            flex: 1;
            text-align: center;
            margin-right: 40px; /* Compensar el botón de atrás */
        }

        .email-form {
            padding: 0 10px;
        }

        .email-input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .email-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            background: #f8f9fa;
            box-sizing: border-box;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            height: 52px;
        }

        .email-input:focus {
            outline: none;
            border-color: #C154C1;
            background: white;
        }

        .email-input:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }

        .current-email-display {
            background: #e9ecef !important;
            color: #495057;
            cursor: not-allowed;
            font-weight: 500;
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

        .submit-button:active {
            transform: translateY(0);
        }

        .submit-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
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

        .info-message {
            background: #f8f9fa;
            color: #495057;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #e9ecef;
        }

        /* Modo oscuro */
        .dark-mode .container { 
            background: var(--element-bg, #2d2d2d); 
            box-shadow: 0 8px 24px rgba(0,0,0,0.35); 
            border: 1px solid var(--border-color, #3d3d3d); 
        }
        
        .dark-mode .email-change-page {
            background: transparent;
        }

        .dark-mode .email-title {
            color: #ffffff;
        }

        .dark-mode .email-input {
            background: #2d2d2d;
            border-color: #555;
            color: #ffffff;
        }

        .dark-mode .email-input:focus {
            background: #333;
            border-color: #C154C1;
        }

        .dark-mode .email-input:disabled {
            background: #333;
            color: #999;
        }

        .dark-mode .current-email-display {
            background: #333 !important;
            color: #b0b0b0 !important;
        }

        .dark-mode .back-link {
            color: #b0b0b0;
        }

        .dark-mode .back-link:hover {
            color: #C154C1;
        }

        .dark-mode .info-message {
            background: #2d2d2d;
            color: #b0b0b0;
            border-color: #555;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 100px 16px 32px;
                padding: 24px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 90px 12px 24px;
                padding: 20px;
                border-radius: 10px;
            }
            
            .email-change-page {
                padding: 0;
                max-width: 100%;
            }
            
            .email-header {
                margin-bottom: 30px;
            }
            
            .email-title {
                font-size: 16px;
            }
            
            .email-input {
                padding: 12px;
                font-size: 15px;
                height: 48px;
            }
            
            .submit-button {
                padding: 14px;
                font-size: 15px;
            }
        }
        
        @media (max-width: 360px) {
            .container {
                margin: 80px 8px 20px;
                padding: 16px;
                border-radius: 8px;
            }
        }
    </style>
</head>
<body>
    <?php $menuHideDashboard = ($userRole === 'cuidador'); $menuLogoHref = ($userRole === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <div class="container">
        <main class="email-change-page">
            <div class="email-header">
                <button class="back-button" onclick="window.location.href='mi_cuenta.php'">‹</button>
                <h1 class="email-title">Cambiar correo</h1>
            </div>

            <form class="email-form" id="emailChangeForm">
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>
            
            <div class="info-message">
                Se enviará un enlace de verificación a tu correo actual (<?php echo htmlspecialchars($userEmail); ?>) para confirmar el cambio
            </div>

            <div class="email-input-group">
                <input 
                    type="email" 
                    id="currentEmail" 
                    name="currentEmail" 
                    class="email-input current-email-display" 
                    value="<?php echo htmlspecialchars($userEmail); ?>"
                    readonly
                    placeholder="Correo electrónico actual"
                >
            </div>

            <div class="email-input-group">
                <input 
                    type="email" 
                    id="newEmail" 
                    name="newEmail" 
                    class="email-input" 
                    placeholder="Ingresa el nuevo correo a utilizar:"
                    required
                >
            </div>

            <div class="email-input-group">
                <input 
                    type="email" 
                    id="confirmEmail" 
                    name="confirmEmail" 
                    class="email-input" 
                    placeholder="Repetir nuevo correo:"
                    required
                >
            </div>

            <button type="submit" class="submit-button" id="submitBtn">Restablecer Correo</button>
            
            <a href="mi_cuenta.php" class="back-link">Volver</a>
        </form>
        </main>
    </div>

    <script>
        // Manejo del formulario
        document.getElementById('emailChangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentEmail = document.getElementById('currentEmail').value;
            const newEmail = document.getElementById('newEmail').value.trim();
            const confirmEmail = document.getElementById('confirmEmail').value.trim();
            
            // Limpiar mensajes previos
            hideMessages();
            
            // Validaciones
            if (!newEmail || !confirmEmail) {
                showError('Todos los campos son requeridos');
                return;
            }
            
            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(newEmail)) {
                showError('Por favor ingresa un email válido');
                return;
            }
            
            if (newEmail !== confirmEmail) {
                showError('Los correos electrónicos no coinciden');
                return;
            }
            
            if (newEmail === currentEmail) {
                showError('El nuevo correo debe ser diferente al actual');
                return;
            }
            
            // Enviar al servidor
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Procesando...';
            
            const formData = new FormData();
            formData.append('currentEmail', currentEmail);
            formData.append('newEmail', newEmail);
            formData.append('confirmEmail', confirmEmail);
            
            fetch('cambiar_email_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message || 'Se ha enviado un enlace de verificación a tu correo actual. Revisa tu bandeja de entrada.');
                    // Limpiar solo los campos nuevos
                    document.getElementById('newEmail').value = '';
                    document.getElementById('confirmEmail').value = '';
                } else {
                    showError(data.error || 'Error al procesar el cambio de correo');
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

        // Aplicar modo oscuro si está habilitado
        document.addEventListener('DOMContentLoaded', function() {
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
