<?php
require_once "session_init.php";
require_once "conexion.php";

// Verificar si el usuario está autenticado
requireAuth();

$userName = getUserName();
$userRole = getUserRole();
$userId = getUserId();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Autopill</title>
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
        
        .password-change-page {
            max-width: 100%;
            margin: 0 auto;
        }

        .password-header {
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

        .password-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
            flex: 1;
            text-align: center;
            margin-right: 40px; /* Compensar el botón de atrás */
        }

        .password-form {
            padding: 0 10px;
        }

        .password-input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .password-input {
            width: 100%;
            padding: 15px 55px 15px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            background: #f8f9fa;
            box-sizing: border-box;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            height: 52px;
        }

        .password-input:focus {
            outline: none;
            border-color: #C154C1;
            background: white;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 26px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 16px;
            color: #999;
            cursor: pointer;
            padding: 0;
            border-radius: 50%;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            line-height: 1;
            z-index: 10;
        }

        .password-toggle-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
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

        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            padding-left: 5px;
        }

        .requirement {
            margin: 2px 0;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
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

        /* Modo oscuro */
        .dark-mode .container { 
            background: var(--element-bg, #2d2d2d); 
            box-shadow: 0 8px 24px rgba(0,0,0,0.35); 
            border: 1px solid var(--border-color, #3d3d3d); 
        }
        
        .dark-mode .password-change-page {
            background: transparent;
        }

        .dark-mode .password-title {
            color: #ffffff;
        }

        .dark-mode .password-input {
            background: #2d2d2d;
            border-color: #555;
            color: #ffffff;
        }

        .dark-mode .password-input:focus {
            background: #333;
            border-color: #C154C1;
        }

        .dark-mode .password-toggle-btn {
            color: #b0b0b0;
        }

        .dark-mode .password-toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .dark-mode .password-requirements {
            color: #b0b0b0;
        }

        .dark-mode .back-link {
            color: #b0b0b0;
        }

        .dark-mode .back-link:hover {
            color: #C154C1;
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
            
            .password-change-page {
                padding: 0;
                max-width: 100%;
            }
            
            .password-header {
                margin-bottom: 30px;
            }
            
            .password-title {
                font-size: 16px;
            }
            
            .password-input {
                padding: 12px 45px 12px 12px;
                font-size: 15px;
                height: 48px;
            }
            
            .password-toggle-btn {
                right: 10px;
                top: 24px;
                width: 28px;
                height: 28px;
                font-size: 14px;
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
        <main class="password-change-page">
            <div class="password-header">
                <button class="back-button" onclick="window.location.href='mi_cuenta.php'">‹</button>
                <h1 class="password-title">Cambiar contraseña</h1>
            </div>

            <form class="password-form" id="passwordChangeForm">
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>

            <div class="password-input-group">
                <input 
                    type="password" 
                    id="currentPassword" 
                    name="currentPassword" 
                    class="password-input" 
                    placeholder="Contraseña actual" 
                    required
                >
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('currentPassword', this)"><span class="open-eye-icon"></span></button>
            </div>

            <div class="password-input-group">
                <input 
                    type="password" 
                    id="newPassword" 
                    name="newPassword" 
                    class="password-input" 
                    placeholder="Nueva Contraseña" 
                    required
                >
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('newPassword', this)"><span class="open-eye-icon"></span></button>
                <div class="password-requirements">
                    <div class="requirement" id="lengthReq">• 8 caracteres o más</div>
                    <div class="requirement" id="numberReq">• Al menos un número</div>
                    <div class="requirement" id="specialReq">• Al menos un carácter especial</div>
                </div>
            </div>

            <div class="password-input-group">
                <input 
                    type="password" 
                    id="confirmPassword" 
                    name="confirmPassword" 
                    class="password-input" 
                    placeholder="Repetir Nueva Contraseña" 
                    required
                >
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirmPassword', this)"><span class="open-eye-icon"></span></button>
            </div>

            <button type="submit" class="submit-button" id="submitBtn">Restablecer Contraseña</button>
            
            <a href="mi_cuenta.php" class="back-link">Volver</a>
        </form>
        </main>
    </div>

    <script>
        // Función para mostrar/ocultar contraseña
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            const eyeIcon = button.querySelector('span');
            
            input.type = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'closed-eye-icon' : 'open-eye-icon';
        }

        // Validación en tiempo real de la nueva contraseña
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            
            // Validar longitud
            const lengthReq = document.getElementById('lengthReq');
            if (password.length >= 8) {
                lengthReq.classList.add('valid');
                lengthReq.classList.remove('invalid');
            } else {
                lengthReq.classList.add('invalid');
                lengthReq.classList.remove('valid');
            }
            
            // Validar número
            const numberReq = document.getElementById('numberReq');
            if (/\d/.test(password)) {
                numberReq.classList.add('valid');
                numberReq.classList.remove('invalid');
            } else {
                numberReq.classList.add('invalid');
                numberReq.classList.remove('valid');
            }
            
            // Validar carácter especial
            const specialReq = document.getElementById('specialReq');
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                specialReq.classList.add('valid');
                specialReq.classList.remove('invalid');
            } else {
                specialReq.classList.add('invalid');
                specialReq.classList.remove('valid');
            }
        });

        // Manejo del formulario
        document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Limpiar mensajes previos
            hideMessages();
            
            // Validaciones
            if (!currentPassword || !newPassword || !confirmPassword) {
                showError('Todos los campos son requeridos');
                return;
            }
            
            if (newPassword.length < 8) {
                showError('La nueva contraseña debe tener al menos 8 caracteres');
                return;
            }
            
            if (!/\d/.test(newPassword)) {
                showError('La nueva contraseña debe contener al menos un número');
                return;
            }
            
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(newPassword)) {
                showError('La nueva contraseña debe contener al menos un carácter especial');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showError('Las nuevas contraseñas no coinciden');
                return;
            }
            
            if (currentPassword === newPassword) {
                showError('La nueva contraseña debe ser diferente a la actual');
                return;
            }
            
            // Enviar al servidor
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Cambiando...';
            
            const formData = new FormData();
            formData.append('currentPassword', currentPassword);
            formData.append('newPassword', newPassword);
            formData.append('confirmPassword', confirmPassword);
            
            fetch('cambiar_password_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message || 'Contraseña cambiada exitosamente');
                    // Limpiar formulario
                    document.getElementById('passwordChangeForm').reset();
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = 'mi_cuenta.php';
                    }, 2000);
                } else {
                    showError(data.error || 'Error al cambiar la contraseña');
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
