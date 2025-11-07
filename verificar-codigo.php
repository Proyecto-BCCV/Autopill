<?php
require_once 'session_init.php';

// Si no hay email en sesión, redirigir a forgot-password
if (!isset($_SESSION['pwd_reset_email']) || empty($_SESSION['pwd_reset_email'])) {
    // Redirigir de vuelta a forgot-password.php si no hay email en sesión
    header('Location: forgot-password.php');
    exit;
}

// Si el usuario ya está logueado, redirigir al dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Revisa tu correo - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        .verification-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }

        .verification-input {
            width: 42px;
            height: 48px;
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            border: 2px solid var(--color-principal);
            border-radius: 8px;
            background-color: white;
            transition: all 0.3s ease;
            color: black;
            -webkit-appearance: none;
            appearance: none;
            padding: 0;
            margin: 0;
            outline: none;
        }

        .verification-input:focus {
            border-color: var(--color-principal);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .verification-input.valid {
            border-color: #27ae60;
            background-color: #d4edda;
        }

        .verification-input.error {
            border-color: #e74c3c;
            background-color: #f8d7da;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .verification-message {
            text-align: center;
            margin: 15px 0;
            font-size: 14px;
        }

        .verification-message.success {
            color: #27ae60;
        }

        .verification-message.error {
            color: #e74c3c;
        }

        .resend-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .resend-text {
            color: var(--text-secondary, #666);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .resend-timer {
            color: var(--color-principal, #3498db);
            font-weight: bold;
            font-size: 16px;
            margin: 10px 0;
        }

        .email-destination {
            font-size: 13px;
            color: var(--text-secondary, #666);
            margin-top: 6px;
        }

        .email-destination b {
            color: #F5F5F5;
        }

        .instruction-text {
            margin-top: 8px;
            font-size: 13px;
            color: #F5F5F5;
        }

        /* Estilos para modo oscuro */
        .dark-mode .resend-text {
            color: #ccc;
        }

        .dark-mode .email-destination {
            color: #ccc;
        }

        .dark-mode .email-destination b {
            color: #fff;
        }

        .dark-mode .instruction-text {
            color: #ccc;
        }

        .dark-mode .resend-timer {
            color: #C154C1;
        }

        .dark-mode .verification-input {
            background-color: #374151;
            color: #fff;
            border-color: #6b7280;
        }

        .dark-mode .verification-input:focus {
            border-color: var(--color-principal, #3498db);
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        .dark-mode .verification-input.valid {
            border-color: #10b981;
            background-color: #065f46;
            color: #fff;
        }

        .dark-mode .verification-input.error {
            border-color: #ef4444;
            background-color: #7f1d1d;
            color: #fff;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #C154C1;
            text-decoration: underline;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            margin: 0;
        }

        .resend-btn:hover {
            color: #a743a7;
        }

        .resend-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
            text-decoration: none;
        }

        /* Estilos adicionales para modo oscuro */
        .dark-mode .resend-btn {
            color: #C154C1;
        }

        .dark-mode .resend-btn:hover {
            color: #d96ed9;
        }

        .dark-mode .resend-btn:disabled {
            color: #6b7280;
        }

        .dark-mode .resend-section {
            border-top-color: #374151;
        }

        @media (max-width: 480px) {
            .verification-input {
                width: 35px;
                height: 42px;
                font-size: 18px;
            }
            
            .verification-container {
                gap: 6px;
            }
        }
    </style>
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header">
        <nav class="nav-container">
            <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            <div class="logo"><a href="index.php"><span class="logo-icon"></span> Autopill</a></div>
            <div class="user-menu">
            </div>
        </nav>
    </header>

    <!-- Menú móvil -->
    <div class="mobile-menu">
        <div class="menu-header">
            <div class="user-info">
                <span class="user-icon-default"></span>
                <span>Bienvenido</span>
            </div>
        </div>
        <div class="menu-items">
            <div class="menu-item">
                <a href="index.php"><span class="home-icon"></span>Inicio</a>
            </div>
            <div class="menu-item">
                <a href="login.php"><span class="login-icon"></span>Iniciar Sesión</a>
            </div>
            <div class="menu-item">
                <a href="register.php"><span class="register-icon"></span>Registrarse</a>
            </div>
            <div class="menu-item">
                <div class="toggle-item">
                    <span><span class="dark-mode-icon"></span>Modo oscuro</span>
                    <label class="switch">
                        <input type="checkbox" id="darkModeToggleMobile">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-header">
            <h1>Revisa tu correo</h1>
            <p>Te enviamos un enlace para restablecer tu contraseña.</p>
            <?php if (!empty($_SESSION['pwd_reset_email'])): ?>
                <p class="email-destination">Correo destino: <b><?php echo htmlspecialchars($_SESSION['pwd_reset_email']); ?></b></p>
            <?php endif; ?>
            <div class="instruction-text">
                Si no lo ves, revisa también tu carpeta de spam. Puedes reenviar pasado el contador.
            </div>
        </div>
        
        <div class="auth-body">
            <div class="login-form">
                <div class="verification-message" id="statusMsg">Enlace enviado. Espera unos segundos…</div>
                <div class="resend-section">
                    <div class="resend-text">¿No te llegó el email?</div>
                    <div id="resendTimer" class="resend-timer" style="display: none;">
                        Reenviar en <span id="timerCount">60</span>s
                    </div>
                    <button type="button" id="resendBtn" class="resend-btn">Reenviar enlace</button>
                </div>
                <div class="login-footer" style="margin-top:22px;">
                    <p><a href="login.php">← Volver al login</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="notifications.js"></script>
    <script>
        // Email del usuario desde la sesión PHP (variable global)
        const userEmail = <?php echo json_encode($_SESSION['pwd_reset_email'] ?? ''); ?>;
        
        // Función para alternar el menú móvil
        function toggleMenu(event) {
            if (event) event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            const navMenu = document.querySelector('.nav-menu');
            
            mobileMenu.classList.toggle('active');
            
            // Asegurar que el nav-menu esté cerrado cuando se abre el menú móvil
            if (navMenu && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
            }
        }

        // Función para alternar el menú desplegable del usuario
        function toggleUserMenu(event) {
            if (event) event.stopPropagation();
            const dropdownMenu = document.querySelector('.dropdown-menu');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            if (dropdownMenu) {
                dropdownMenu.classList.toggle('active');
            }
            
            // Cerrar menú móvil si está abierto
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Limpiar el flag de reseteo completado al cargar esta página
            // (solo debe estar activo mientras se está en nueva-password.php)
            localStorage.removeItem('pwdResetDone');
            
            // Escuchar notificación de finalización desde la pestaña del enlace
            try {
                window.addEventListener('storage', function(e) {
                    if (e.key === 'pwdResetDone' && e.newValue) {
                        // Redirigir al login automáticamente solo cuando se establece el valor
                        window.location.href = 'login.php';
                    }
                });
            } catch (e) { /* ignorar */ }
            
            // Verificar y aplicar el modo oscuro al cargar la página
            const savedMode = localStorage.getItem('darkMode');
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeToggleMobile = document.getElementById('darkModeToggleMobile');
            
            // Aplicar modo oscuro si está guardado
            if (savedMode === 'enabled') {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
                if (darkModeToggleMobile) darkModeToggleMobile.checked = true;
            } else {
                document.body.classList.remove('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = false;
                if (darkModeToggleMobile) darkModeToggleMobile.checked = false;
            }
            
            // Función para cambiar el modo oscuro
            function toggleDarkMode(isEnabled) {
                if (isEnabled) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('darkMode', 'enabled');
                    if (darkModeToggle) darkModeToggle.checked = true;
                    if (darkModeToggleMobile) darkModeToggleMobile.checked = true;
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('darkMode', 'disabled');
                    if (darkModeToggle) darkModeToggle.checked = false;
                    if (darkModeToggleMobile) darkModeToggleMobile.checked = false;
                }
            }
            
            // Evento para el toggle del desktop
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    toggleDarkMode(this.checked);
                });
            }
            
            // Evento para el toggle del móvil
            if (darkModeToggleMobile) {
                darkModeToggleMobile.addEventListener('change', function() {
                    toggleDarkMode(this.checked);
                });
            }

            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', function(e) {
                const mobileMenu = document.querySelector('.mobile-menu');
                const menuBtn = document.querySelector('.mobile-menu-btn');
                const dropdownMenu = document.querySelector('.dropdown-menu');
                const userIcon = document.querySelector('.user-icon');
                
                // Cerrar menú móvil
                if (mobileMenu && mobileMenu.classList.contains('active') && 
                    !mobileMenu.contains(e.target) && 
                    !menuBtn.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                }
                
                // Cerrar dropdown menu del header
                if (dropdownMenu && dropdownMenu.classList.contains('active') &&
                    !dropdownMenu.contains(e.target) &&
                    !userIcon.contains(e.target)) {
                    dropdownMenu.classList.remove('active');
                }
            });

            // Cerrar menú al presionar la tecla Escape
            document.addEventListener('keydown', function(e) {
                const mobileMenu = document.querySelector('.mobile-menu');
                const dropdownMenu = document.querySelector('.dropdown-menu');
                
                if (e.key === 'Escape') {
                    if (mobileMenu && mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                    }
                    if (dropdownMenu && dropdownMenu.classList.contains('active')) {
                        dropdownMenu.classList.remove('active');
                    }
                }
            });

            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            const timerCount = document.getElementById('timerCount');
            const statusMsg = document.getElementById('statusMsg');

            let timer = null;
            let timeLeft = 60;

            // Temporizador de reenvío
            function startResendTimer() {
                timeLeft = 60;
                resendBtn.style.display = 'none';
                resendTimer.style.display = 'block';
                
                timer = setInterval(() => {
                    timeLeft--;
                    timerCount.textContent = timeLeft;
                    
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        resendTimer.style.display = 'none';
                        resendBtn.style.display = 'inline-block';
                        resendBtn.disabled = false;
                    }
                }, 1000);
            }

            // Reenviar enlace
            resendBtn.addEventListener('click', function() {
                resendBtn.disabled = true;
                
                // Verificar que tenemos el email
                if (!userEmail) {
                    statusMsg.className = 'verification-message error';
                    statusMsg.textContent = 'Error: No se pudo obtener el email. Por favor, vuelve a solicitar la recuperación de contraseña.';
                    resendBtn.disabled = false;
                    return;
                }
                
                // Enviar solicitud con el email
                const formData = new FormData();
                formData.append('email', userEmail);
                
                fetch('forgot_password_start.php', { 
                    method: 'POST', 
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        statusMsg.className = 'verification-message success';
                        statusMsg.textContent = data.success ? 'Enlace reenviado correctamente. Revisa tu correo.' : 'Enlace reenviado (si existe una cuenta).';
                        startResendTimer();
                    })
                    .catch(() => {
                        statusMsg.className = 'verification-message success';
                        statusMsg.textContent = 'Enlace reenviado (si existe una cuenta).';
                        startResendTimer();
                    });
            });

            // Iniciar temporizador al cargar la página
            startResendTimer();
        });
    </script>
</body>
</html> 
