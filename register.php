<?php
require_once 'session_init.php';

// Si el usuario ya está logueado, redirigir según su rol y estado de vinculación ESP
if (isAuthenticated()) {
    if (function_exists('isCuidador') && isCuidador()) {
        header('Location: dashboard_cuidador.php');
    } else {
        // Para usuarios, verificar si necesita vincular ESP32
        if (!empty($_SESSION['needs_esp32'])) {
            header('Location: vincular_esp.php');
        } else {
            header('Location: dashboard.php');
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        /* Estilos específicos para los roles en modo oscuro */
        body.dark-mode .role-option label {
            background-color: #333 !important;
            color: #fff !important;
            border: 2px solid #555 !important;
        }

        body.dark-mode .role-option input[type="radio"]:checked + label {
            background-color: #C154C1 !important;
            color: white !important;
            border: 2px solid #C154C1 !important;
        }

        /* Estilos para validación de contraseña en tiempo real */
        .password-rules li {
            transition: color 0.3s ease;
            margin: 4px 0;
        }

        .password-rules li.valid {
            color: #28a745;
        }

        .password-rules li.invalid {
            color: #dc3545;
        }

        .password-rules li.neutral {
            color: #666;
        }

        /* Modo oscuro para reglas de contraseña */
        body.dark-mode .password-rules li.neutral {
            color: #b0b0b0;
        }
    </style>
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
                <a href="login.php"><span class="login-icon"></span>Iniciar sesión</a>
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
            <h1>Registro</h1>
        </div>
        
        <div class="auth-body">
            <div class="role-selector">
                <div class="role-options">
                    <div class="role-option">
                        <input type="radio" id="rolePatient" name="role" value="paciente" required>
                        <label for="rolePatient">Usuario</label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="roleAssistant" name="role" value="cuidador" required>
                        <label for="roleAssistant">Cuidador</label>
                    </div>
                </div>
            </div>
            
            <form id="registerForm" class="login-form">
                <div class="input-group">
                    <label for="registerEmail">Email</label>
                    <input type="email" id="registerEmail" name="email" placeholder="sucorreo@dominio.com" required>
                </div>
                
                <div class="input-group">
                    <label for="registerPassword">Contraseña</label>
                    <div class="password-input-container">
                        <input type="password" id="registerPassword" name="password" placeholder="Contraseña" required>
                        <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false"><span class="open-eye-icon"></span></button>
                    </div>
                    <ul class="password-rules">
                        <li id="lengthRule">8 dígitos o más</li>
                        <li id="numberRule">Un número</li>
                        <li id="specialRule">Un carácter especial (#, @, etc.)</li>
                    </ul>
                </div>
                
                <div class="input-group">
                    <label for="confirmPassword">Confirmar Contraseña</label>
                    <div class="password-input-container">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirma tu contraseña" required>
                        <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false"><span class="open-eye-icon"></span></button>
                    </div>
                </div>
                
                <button type="submit" class="login-submit-btn">Registrarse</button>
                
                <!-- Botón para iniciar sesión con Google -->
                <div class="social-login">
                    <a href="google_login.php" class="google-login-btn" rel="noopener" id="googleRegisterLink">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google Icon"> Continuar con Google
                    </a>
                </div>
                
                <div class="login-footer">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="notifications.js"></script>
    <script src="auth.js?v=3"></script>
    <script>
        // Verificar y aplicar el modo oscuro al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const savedMode = localStorage.getItem('darkMode');
            const darkModeToggle = document.getElementById('darkModeToggle');
            
            if (darkModeToggle) {
                if (savedMode === 'enabled') {
                    document.body.classList.add('dark-mode');
                    darkModeToggle.checked = true;
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

        // Función para alternar el menú móvil
        function toggleMenu() {
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
        }

        // Función para manejar los botones de rol
        function setupRoleButtons() {
            const roleButtons = document.querySelectorAll('input[name="role"]');
            
            roleButtons.forEach(button => {
                button.addEventListener('change', function() {
                    // Resetear todos los botones al estado por defecto
                    roleButtons.forEach(btn => {
                        const label = btn.nextElementSibling;
                        // Remover estilos inline para que funcionen los estilos CSS
                        label.removeAttribute('style');
                    });
                    
                    // Marcar el botón seleccionado
                    if (this.checked) {
                        const label = this.nextElementSibling;
                        // Remover estilos inline para que funcionen los estilos CSS
                        label.removeAttribute('style');
                    }
                });
            });
        }

        // Inicializar botones de rol cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            setupRoleButtons();
            setupPasswordValidation();
            
            // Preseleccionar rol si viene por URL
            const urlParams = new URLSearchParams(window.location.search);
            const tipo = urlParams.get('tipo');
            if (tipo === 'usuario' || tipo === 'paciente') {
                document.getElementById('rolePatient').checked = true;
                document.getElementById('rolePatient').dispatchEvent(new Event('change'));
            } else if (tipo === 'cuidador') {
                document.getElementById('roleAssistant').checked = true;
                document.getElementById('roleAssistant').dispatchEvent(new Event('change'));
            }
        });

        // Función para validación de contraseña en tiempo real
        function setupPasswordValidation() {
            const passwordInput = document.getElementById('registerPassword');
            const lengthRule = document.getElementById('lengthRule');
            const numberRule = document.getElementById('numberRule');
            const specialRule = document.getElementById('specialRule');
            
            if (!passwordInput || !lengthRule || !numberRule || !specialRule) return;
            
            // Inicializar reglas como neutrales
            lengthRule.classList.add('neutral');
            numberRule.classList.add('neutral');
            specialRule.classList.add('neutral');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Validar longitud
                if (password.length >= 8) {
                    lengthRule.classList.remove('invalid', 'neutral');
                    lengthRule.classList.add('valid');
                } else if (password.length > 0) {
                    lengthRule.classList.remove('valid', 'neutral');
                    lengthRule.classList.add('invalid');
                } else {
                    lengthRule.classList.remove('valid', 'invalid');
                    lengthRule.classList.add('neutral');
                }
                
                // Validar número
                if (/\d/.test(password)) {
                    numberRule.classList.remove('invalid', 'neutral');
                    numberRule.classList.add('valid');
                } else if (password.length > 0) {
                    numberRule.classList.remove('valid', 'neutral');
                    numberRule.classList.add('invalid');
                } else {
                    numberRule.classList.remove('valid', 'invalid');
                    numberRule.classList.add('neutral');
                }
                
                // Validar carácter especial
                if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                    specialRule.classList.remove('invalid', 'neutral');
                    specialRule.classList.add('valid');
                } else if (password.length > 0) {
                    specialRule.classList.remove('valid', 'neutral');
                    specialRule.classList.add('invalid');
                } else {
                    specialRule.classList.remove('valid', 'invalid');
                    specialRule.classList.add('neutral');
                }
            });
        }

    // El manejo de mostrar/ocultar contraseña ahora se realiza en auth.js
    </script>
</body>
</html> 