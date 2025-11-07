<?php
require_once 'session_init.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>

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
    </style>
</head>
<body class="auth-page">
    <?php 
      $usuario_logueado = isAuthenticated();
      if ($usuario_logueado) {
        $menuLogoHref = 'index.php'; 
        include __DIR__ . '/partials/menu.php';
      } else { ?>
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
      <div class="mobile-menu">
          <div class="menu-header">
              <div class="user-info">
                  <span class="user-icon-default"></span>
                  <span>Bienvenido</span>
              </div>
          </div>
          <div class="menu-items">
              <div class="menu-item">
                  <a href="login.php"><span class="login-icon"></span>Iniciar sesión</a>
              </div>
              <div class="menu-item">
                  <a href="register.php"><span class="register-icon"></span>Registrarse</a>
              </div>
              <div class="menu-item toggle-item">
                  <span><span class="dark-mode-icon"></span>Modo oscuro</span>
                  <label class="switch">
                      <input type="checkbox" id="darkModeToggle">
                      <span class="slider"></span>
                  </label>
              </div>
          </div>
      </div>
    <?php } ?>

    <div class="auth-container">
        <div class="auth-header">
            <h1>Recuperar Contraseña</h1>
            <p>Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña</p>
        </div>
        
        <div class="auth-body">
            <form id="forgotPasswordForm" class="login-form">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Ingresa tu email" required>
                </div>
                
                <button type="submit" class="login-submit-btn">Generar enlace</button>
                
                <div id="submitFeedback" class="submit-feedback">
                    <div class="spinner" style="display: none;"></div>
                    <span id="feedbackMessage"></span>
                </div>
                
                <div class="login-footer">
                    <p><a href="login.php">← Volver al login</a></p>
                    <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="notifications.js"></script>
    <script>
        // Manejar el formulario de recuperación de contraseña
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const feedback = document.getElementById('submitFeedback');
            const feedbackMessage = document.getElementById('feedbackMessage');
            const spinner = feedback.querySelector('.spinner');
            const submitBtn = form.querySelector('button[type="submit"]');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = document.getElementById('email').value.trim();
                if (!email) { return showFeedback('Por favor ingresa tu email', 'error'); }
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) { return showFeedback('Por favor ingresa un email válido', 'error'); }

                showFeedback('Enviando enlace...', 'loading');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando...';

                const formData = new FormData();
                formData.append('email', email);
                fetch('forgot_password_start.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(async r => {
                    console.log('Status HTTP:', r.status);
                    console.log('Headers:', r.headers);
                    
                    const txt = await r.text();
                    console.log('Respuesta raw:', txt);
                    console.log('Longitud respuesta:', txt.length);
                    
                    try { 
                        const parsed = JSON.parse(txt);
                        console.log('JSON parseado:', parsed);
                        return parsed;
                    } catch(e) { 
                        console.error('Error al parsear respuesta:', txt);
                        console.error('Error detalle:', e);
                        throw new Error('Respuesta del servidor inválida'); 
                    }
                })
                .then(response => {
                    console.log('Response objeto:', response);
                    if (response.success) {
                        showFeedback('Enlace enviado a tu email correctamente', 'success');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Generar enlace';
                        // Redirigir a la página de espera donde puede reenviar después de 60s
                        setTimeout(() => { window.location.href = 'verificar-codigo.php'; }, 1200);
                    } else {
                        showFeedback(response.message || 'El correo electrónico no está registrado', 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Generar enlace';
                    }
                })
                .catch((error) => {
                    console.error('Error en fetch:', error);
                    console.error('Stack trace:', error.stack);
                    showFeedback('Error al procesar la solicitud. Inténtalo de nuevo.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Generar enlace';
                });
            });

            function showFeedback(message, type) {
                feedbackMessage.textContent = message;
                feedback.className = `submit-feedback show ${type}`;
                spinner.style.display = (type === 'loading') ? 'block' : 'none';
            }

            // Si no hay sesión iniciada, inicializar el menú público y el modo oscuro (como en index.php)
            <?php if (!$usuario_logueado): ?>
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

            // Modo oscuro público
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
            <?php endif; ?>
        });
    </script>
</body>
</html> 