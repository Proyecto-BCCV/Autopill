<?php
require_once 'session_init.php';

// Si el usuario ya está logueado, redirigir según su rol y estado de vinculación ESP
if (isAuthenticated()) {
    if (function_exists('isCuidador') && isCuidador()) {
        header('Location: dashboard_cuidador.php');
    } else {
        // Para pacientes, verificar si necesita vincular ESP32
        if (!empty($_SESSION['needs_esp32'])) {
            header('Location: vincular_esp.php');
        } else {
            header('Location: dashboard.php');
        }
    }
    exit;
}

// Definir la variable $user_authenticated para evitar errores
$user_authenticated = false;

// El login se maneja ahora a través de JavaScript/AJAX en auth.js
// que envía los datos a login_process.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header">
        <nav class="nav-container">
            <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            <a href="index.php" class="logo">
                <span class="logo-icon"></span>
                Autopill
            </a>
            <div class="user-menu">
                <div class="user-dropdown">
                    <a href="login.php" class="dropdown-item"><span class="login-icon"></span>Iniciar sesión</a>
                    <a href="register.php" class="dropdown-item"><span class="register-icon"></span>Registrarse</a>
                </div>
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
                <a href="register.php"><span class="register-icon"></span>Registrarse</a>
            </div>
            <div class="menu-item">
                <div class="toggle-item">
                    <span><span class="dark-mode-icon"></span>Modo oscuro</span>
                    <label class="switch">
                        <input type="checkbox" id="darkModeToggle">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-header">
            <h1>Iniciar Sesión</h1>
            <?php
                $errorMsg = '';
                if (!empty($_GET['error'])) {
                    switch ($_GET['error']) {
                        case 'oauth_config':
                            $errorMsg = 'Error de configuración de Google OAuth. Revisa Client ID/Secret y Redirect URI.';
                            break;
                        case 'oauth_invalid_client':
                            $errorMsg = 'Google devolvió invalid_client. Verifica tu Client ID/Secret y el tipo de credencial (Aplicación web).';
                            break;
                        case 'oauth_redirect_mismatch':
                            $errorMsg = 'Redirect URI no coincide. Debe ser exactamente igual a la registrada en Google Cloud.';
                            break;
                        case 'oauth_invalid_grant':
                            $errorMsg = 'Código de autorización inválido/expirado. Intenta iniciar el flujo otra vez.';
                            break;
                        case 'oauth_token':
                        case 'oauth_token_error':
                            $errorMsg = 'No se pudo obtener el token de Google. Revisa conexión y credenciales.';
                            break;
                        case 'oauth_idtoken':
                            $errorMsg = 'No se recibió id_token. Revisa scopes y configuración en Google Cloud.';
                            break;
                        case 'oauth_state':
                            $errorMsg = 'State CSRF inválido. Vuelve a intentar.';
                            break;
                        case 'jwt_claims':
                            $errorMsg = 'Token recibido inválido. Verifica Client ID y hora del sistema.';
                            break;
                        default:
                            $errorMsg = 'Error en login: ' . htmlspecialchars($_GET['error']);
                    }
                }
                if ($errorMsg) {
                    echo '<div class="alert error" style="margin-top:10px;">' . $errorMsg . ' <a href="google_oauth_diagnostics.php">Diagnóstico</a></div>';
                }
            ?>
        </div>
        
        <div class="auth-body">
            <form id="loginForm" class="login-form" method="POST">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Ingresa tu email" required>
                </div>
                
                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                        <span class="password-toggle"><span class="open-eye-icon"></span></span>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember">
                        <label for="remember">Recordarme</label>
                    </div>
                    <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="login-submit-btn">Iniciar Sesión</button>
                
                <!-- Botón para iniciar sesión con Google -->
                <div class="social-login">
                    <a href="google_login.php" class="google-login-btn" rel="noopener" id="googleLoginLink">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google Icon"> Iniciar sesión con Google
                    </a>
                </div>
                
                <div class="login-footer">
                    <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="notifications.js"></script>
    <script src="auth.js?v=3"></script>
    <script>
        // Función para alternar el menú móvil
        function toggleMenu(event) {
            if (event) event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            const navMenu = document.querySelector('.nav-menu');
            
            mobileMenu.classList.toggle('active');
            
            if (navMenu && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Verificar y aplicar el modo oscuro al cargar la página
            const savedMode = localStorage.getItem('darkMode');
            const darkModeToggle = document.getElementById('darkModeToggle');
            
            // Verificar si el elemento existe antes de manipularlo
            if (darkModeToggle) {
                // Establecer el estado del toggle según el modo guardado
                if (savedMode === 'enabled') {
                    document.body.classList.add('dark-mode');
                    darkModeToggle.checked = true;
                } else {
                    document.body.classList.remove('dark-mode');
                    darkModeToggle.checked = false;
                }
                
                // Agregar el evento para cambiar el modo
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

            // Cerrar menú al presionar la tecla Escape
            document.addEventListener('keydown', function(e) {
                const mobileMenu = document.querySelector('.mobile-menu');
                if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
                    mobileMenu.classList.remove('active');
                }
            });
        });

        // Asegurar navegación del botón de Google sin interferencias
        const gLink = document.getElementById('googleLoginLink');
        if (gLink) {
            gLink.addEventListener('click', function(e){
                // No prevenir por defecto; solo forzar navegación
                window.location.href = this.href;
            });
        }
    </script>
</body>
</html>