<?php
require_once 'session_init.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

// Verificar que el token haya sido validado previamente
// (validate_reset_link.php establece esta variable de sesión)
if (!isset($_SESSION['pwd_reset_verified_user'])) {
    // No hay sesión de reseteo válida, redirigir a forgot-password
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        .password-rules {
            list-style: none;
            padding: 0;
            margin: 10px 0;
            font-size: 0.85rem;
            color: #666;
        }

        .password-rules li {
            margin: 5px 0;
            padding-left: 20px;
            position: relative;
            transition: color 0.3s ease;
        }

        .password-rules li::before {
            content: "✗";
            position: absolute;
            left: 0;
            color: #e74c3c;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .password-rules li.valid::before {
            content: "✓";
            color: #27ae60;
        }

        .password-rules li.valid {
            color: #27ae60;
        }

        .password-strength {
            margin: 10px 0;
            height: 4px;
            background-color: #eee;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0%;
        }

        .strength-weak {
            background-color: #e74c3c;
            width: 25%;
        }

        .strength-fair {
            background-color: #f39c12;
            width: 50%;
        }

        .strength-good {
            background-color: #f1c40f;
            width: 75%;
        }

        .strength-strong {
            background-color: #27ae60;
            width: 100%;
        }

        .submit-feedback {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            margin-top: 10px;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .submit-feedback.show {
            opacity: 1;
            transform: translateY(0);
        }

        .submit-feedback.success {
            color: #28a745;
        }

        .submit-feedback.error {
            color: #dc3545;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-disabled {
            pointer-events: none;
            opacity: 0.6;
        }
    </style>
</head>
<body class="auth-page">
    <!-- Header para usuarios no logueados -->
    <header class="header">
        <nav class="nav-container">
            <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            <a href="index.php" class="logo">
                <span class="logo-icon"></span>
                Autopill
            </a>
            <div class="user-menu">
                <div class="register-now-text" style="color: #C154C1; font-weight: 600; cursor: pointer;" onclick="toggleUserMenu()">Ingresa aquí</div>
                <div class="user-dropdown">
                    <a href="login.php" class="dropdown-item"><span class="login-icon"></span>Iniciar sesión</a>
                    <a href="register.php" class="dropdown-item"><span class="register-icon"></span>Registrarse</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Menú móvil para usuarios no logueados -->
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
            <h1>Nueva Contraseña</h1>
            <p>Crea una nueva contraseña segura para tu cuenta</p>
        </div>
        
        <div class="auth-body">
            <form id="newPasswordForm" class="login-form">
                <div class="input-group">
                    <label for="password">Nueva Contraseña</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Ingresa tu nueva contraseña" required>
                        <span class="password-toggle" onclick="togglePassword('password')"><span class="open-eye-icon"></span></span>
                    </div>
                    
                    <!-- Barra de fortaleza de contraseña -->
                    <div class="password-strength">
                        <div id="strengthBar" class="password-strength-bar"></div>
                    </div>
                    
                    <!-- Reglas de contraseña -->
                    <ul class="password-rules">
                        <li id="rule-length">Al menos 8 caracteres</li>
                        <li id="rule-number">Al menos un número</li>
                        <li id="rule-special">Al menos un carácter especial</li>
                        <li id="rule-uppercase">Al menos una mayúscula</li>
                    </ul>
                </div>
                
                <div class="input-group">
                    <label for="confirmPassword">Confirmar Contraseña</label>
                    <div class="password-input-container">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirma tu nueva contraseña" required>
                        <span class="password-toggle" onclick="togglePassword('confirmPassword')"><span class="open-eye-icon"></span></span>
                    </div>
                    <div id="passwordMatch" class="password-match"></div>
                </div>
                
                <button type="submit" class="login-submit-btn" disabled>Cambiar Contraseña</button>
                
                <div id="submitFeedback" class="submit-feedback">
                    <div class="spinner" style="display: none;"></div>
                    <span id="feedbackMessage"></span>
                </div>
                
                <div class="login-footer">
                    <p><a href="login.php">← Volver al login</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Función para alternar visibilidad de contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling;
            const eyeIcon = toggle.querySelector('span');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.className = 'closed-eye-icon';
            } else {
                input.type = 'password';
                eyeIcon.className = 'open-eye-icon';
            }
        }

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
            // Verificar y aplicar el modo oscuro
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

            // Lógica de validación de contraseña
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const submitBtn = document.querySelector('button[type="submit"]');
            const strengthBar = document.getElementById('strengthBar');
            const form = document.getElementById('newPasswordForm');
            const feedback = document.getElementById('submitFeedback');
            const feedbackMessage = document.getElementById('feedbackMessage');

            let isPasswordValid = false;
            let doPasswordsMatch = false;

            // Validar contraseña en tiempo real
            passwordInput.addEventListener('input', function() {
                validatePassword(this.value);
                checkPasswordMatch();
                updateSubmitButton();
            });

            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordMatch();
                updateSubmitButton();
            });

            function validatePassword(password) {
                const rules = {
                    length: password.length >= 8,
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password),
                    uppercase: /[A-Z]/.test(password)
                };

                // Actualizar indicadores visuales de reglas
                Object.keys(rules).forEach(rule => {
                    const element = document.getElementById(`rule-${rule}`);
                    if (rules[rule]) {
                        element.classList.add('valid');
                    } else {
                        element.classList.remove('valid');
                    }
                });

                // Calcular fortaleza
                const validRules = Object.values(rules).filter(valid => valid).length;
                let strengthClass = '';
                
                if (validRules === 1) strengthClass = 'strength-weak';
                else if (validRules === 2) strengthClass = 'strength-fair';
                else if (validRules === 3) strengthClass = 'strength-good';
                else if (validRules === 4) strengthClass = 'strength-strong';

                strengthBar.className = `password-strength-bar ${strengthClass}`;
                
                isPasswordValid = validRules === 4;
                return isPasswordValid;
            }

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const matchElement = document.getElementById('passwordMatch');

                if (confirmPassword.length > 0) {
                    if (password === confirmPassword) {
                        matchElement.textContent = '✓ Las contraseñas coinciden';
                        matchElement.style.color = '#27ae60';
                        doPasswordsMatch = true;
                    } else {
                        matchElement.textContent = '✗ Las contraseñas no coinciden';
                        matchElement.style.color = '#e74c3c';
                        doPasswordsMatch = false;
                    }
                } else {
                    matchElement.textContent = '';
                    doPasswordsMatch = false;
                }
            }

            function updateSubmitButton() {
                if (isPasswordValid && doPasswordsMatch) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.6';
                }
            }

            // Manejar envío del formulario
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!isPasswordValid || !doPasswordsMatch) {
                    showFeedback('Por favor corrige los errores antes de continuar', 'error');
                    return;
                }
                showFeedback('Actualizando contraseña...', 'loading');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Actualizando...';
                form.classList.add('form-disabled');
                const formData = new FormData();
                formData.append('password', passwordInput.value);
                formData.append('confirm', confirmPasswordInput.value);
                fetch('set_new_password.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            showFeedback('Contraseña actualizada correctamente', 'success');
                            try {
                                // Notificar a otras pestañas (verificar-codigo) que ya se restableció
                                localStorage.setItem('pwdResetDone', String(Date.now()));
                            } catch (e) { /* ignorar */ }
                            // Intentar cerrar esta pestaña (puede estar bloqueado por el navegador)
                            setTimeout(() => { try { window.open('', '_self'); window.close(); } catch (e) {} }, 150);
                            // Fallback: si no se pudo cerrar, ofrecer ir al login
                            setTimeout(() => {
                                const link = document.createElement('a');
                                link.href = 'login.php';
                                link.textContent = 'Ir al login';
                                link.style.display = 'inline-block';
                                link.style.marginTop = '10px';
                                const footer = document.querySelector('.login-footer');
                                if (footer) { footer.appendChild(link); }
                            }, 800);
                        } else {
                            const msg = (data && data.error) ? data.error : 'No se pudo actualizar la contraseña';
                            showFeedback(msg, 'error');
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Cambiar Contraseña';
                            form.classList.remove('form-disabled');
                        }
                    })
                    .catch(() => {
                        showFeedback('Error de red. Inténtalo de nuevo.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Cambiar Contraseña';
                        form.classList.remove('form-disabled');
                    });
            });

            function showFeedback(message, type) {
                feedbackMessage.textContent = message;
                feedback.className = `submit-feedback show ${type}`;
                
                const spinner = feedback.querySelector('.spinner');
                if (type === 'loading') {
                    spinner.style.display = 'block';
                } else {
                    spinner.style.display = 'none';
                }
            }
        });

        // Funciones del menú (igual que index.php)
        window.toggleMenu = function() {
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenu) mobileMenu.classList.toggle('active');
        }

        function toggleUserMenu() {
            const userDropdown = document.querySelector('.user-dropdown');
            if (userDropdown) userDropdown.classList.toggle('active');
        }

        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(e) {
            const mobileMenu = document.querySelector('.mobile-menu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            if (mobileMenu && mobileMenu.classList.contains('active') && !mobileMenu.contains(e.target) && !menuBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }

            const userMenu = document.querySelector('.user-menu');
            const userDropdown = document.querySelector('.user-dropdown');
            const registerText = document.querySelector('.register-now-text');
            if (userDropdown && userDropdown.classList.contains('active') && !userMenu.contains(e.target) && registerText && !registerText.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });

        // Modo oscuro
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            const savedMode = localStorage.getItem('darkMode');
            if (savedMode === 'enabled') {
                document.body.classList.add('dark-mode');
                darkModeToggle.checked = true;
            }
            darkModeToggle.addEventListener('change', function() {
                const enabled = this.checked;
                document.body.classList.toggle('dark-mode', enabled);
                localStorage.setItem('darkMode', enabled ? 'enabled' : 'disabled');
            });
        }
    </script>
</body>
</html> 
