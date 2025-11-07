<?php
require_once "session_init.php";
require_once "conexion.php";

// Verificar si el usuario está autenticado
requireAuth();

// Verificar que el usuario sea cuidador
if (!isCuidador()) {
    header("Location: dashboard.php");
    exit;
}

$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Usuario - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        /* Estilos específicos para seleccionar usuario */
        .select-user-page {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            min-height: 100vh;
        }

        .select-user-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 10px;
        }

        .select-user-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .user-form {
            background-color: var(--element-bg);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
            text-align: center;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #C154C1;
        }

        .form-submit-btn {
            background: #C154C1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .form-submit-btn:hover {
            background: #a743a7;
        }

        .form-submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .back-link {
            display: inline-block;
            color: #C154C1;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            padding: 10px 0;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .select-user-page {
                padding: 15px;
            }
            
            .user-form {
                padding: 16px;
            }
            
            .select-user-title {
                font-size: 20px;
            }
        }

        /* Modo oscuro */
        .dark-mode .select-user-page {
            background: #1a1a1a;
        }

        .dark-mode .select-user-title {
            color: #ffffff;
        }

        .dark-mode .user-form {
            background: #2d2d2d;
        }

        .dark-mode .form-label {
            color: #ffffff;
        }

        .dark-mode .form-input {
            background: #3d3d3d;
            border-color: #555;
            color: #ffffff;
        }

        .dark-mode .form-input:focus {
            border-color: #C154C1;
        }

        .dark-mode .back-link {
            color: #C154C1;
        }
    </style>
</head>
<body>
    <?php $menuLogoHref = 'dashboard_cuidador.php'; include __DIR__ . '/partials/menu.php'; ?>

    <main class="select-user-page">
        <div class="select-user-header">
            <h1 class="select-user-title">Agregar Paciente</h1>
        </div>

        <div class="user-form">
            <form id="seleccionarUsuarioForm">
                <div class="form-group">
                    <label for="usuarioId" class="form-label">ID del Usuario</label>
                    <input type="text" id="usuarioId" name="usuario_id" class="form-input" 
                           placeholder="Ingresa el ID del usuario que quieres cuidar" required>
                </div>
                
                <button type="submit" class="form-submit-btn" id="submitBtn">
                    Enviar Solicitud
                </button>
            </form>
        </div>

        <!-- Mensajes de estado -->
        <div id="statusMessage" class="alert" style="display:none;"></div>


    </main>

    <script>
        // Función para alternar el menú móvil
        function toggleMobileMenu(event) {
            event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
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

            // Manejar el envío del formulario
            document.getElementById('seleccionarUsuarioForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const usuarioId = document.getElementById('usuarioId').value.trim();
                
                if (!usuarioId) {
                    showMessage('Por favor ingresa el ID del usuario', 'error');
                    return;
                }

                // Deshabilitar botón y mostrar estado
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando...';
                showMessage('Enviando solicitud...', 'info');

                // Enviar petición al servidor
                fetch('enviar_solicitud_cuidado.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        usuario_id: usuarioId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Solicitud enviada exitosamente a ' + data.paciente_nombre, 'success');
                        document.getElementById('usuarioId').value = '';
                        setTimeout(() => {
                            window.location.href = 'dashboard_cuidador.php';
                        }, 2000);
                    } else {
                        showMessage('Error: ' + (data.error || 'Error desconocido'), 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error al enviar la solicitud', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        });

        // Función para mostrar mensajes
        function showMessage(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.textContent = message;
            statusDiv.className = `alert alert-${type}`;
            statusDiv.style.display = 'block';
            
            // Ocultar mensaje después de 5 segundos (excepto para errores)
            if (type !== 'error') {
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 5000);
            }
        }
    </script>
</body>
</html>
