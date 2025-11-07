<?php
require_once 'session_init.php';

// Obtener información del usuario si está logueado
$userName = isAuthenticated() ? getUserName() : 'Invitado';
$isLoggedIn = isAuthenticated();
// Definir destino del logo del menú (override para el parcial)
if ($isLoggedIn) {
    $menuLogoHref = isCuidador() ? 'dashboard_cuidador.php' : 'dashboard.php';
} else {
    $menuLogoHref = 'index.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Problema - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        /* Estilos consistentes para los campos del formulario */
        .contact-form .input-group {
            margin-bottom: 20px;
        }

        .contact-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color, #555);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .contact-form input,
        .contact-form select,
        .contact-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color, #e1e8ed);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--bg-secondary, #fff);
            color: var(--text-color, #333);
            box-sizing: border-box;
        }

        .contact-form input:focus,
        .contact-form select:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: var(--accent-color, #C154C1);
            box-shadow: 0 0 0 3px rgba(193, 84, 193, 0.1);
        }

        .contact-form select {
            cursor: pointer;
        }

        .contact-form textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .contact-form .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent-color, #C154C1) 0%, var(--accent-hover, #A13BA1) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .contact-form .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(193, 84, 193, 0.3);
        }

        /* Estilos para feedback de envío */
        .submit-feedback {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }

        .submit-feedback.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .submit-feedback.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Modo oscuro */
        body.dark-mode .contact-form label {
            color: var(--text-color, #fff);
        }

        body.dark-mode .contact-form input,
        body.dark-mode .contact-form select,
        body.dark-mode .contact-form textarea {
            background-color: var(--bg-secondary, #333);
            border-color: var(--border-color, #555);
            color: var(--text-color, #fff);
        }

        body.dark-mode .contact-form input:focus,
        body.dark-mode .contact-form select:focus,
        body.dark-mode .contact-form textarea:focus {
            border-color: var(--accent-color, #C154C1);
            box-shadow: 0 0 0 3px rgba(193, 84, 193, 0.2);
        }

        /* Estilos para el formulario en filas */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row .input-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Estilos para iconos de contacto */
        .contact-icon {
            width: auto;
            height: auto;
            max-width: 20px;
            max-height: 20px;
            margin-right: 8px;
            vertical-align: middle;
            display: inline-block;
        }

        .telephone-icon {
            content: url('/icons/lightmode/telephone.png');
        }

        .email-icon {
            content: url('/icons/lightmode/email-dash.png');
        }

        /* Modo oscuro - cambiar iconos */
        body.dark-mode .telephone-icon {
            content: url('/icons/darkmode/telephone.png');
        }

        body.dark-mode .email-icon {
            content: url('/icons/darkmode/email-dash.png');
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-row .input-group {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'partials/menu.php'; ?>

    <main class="contact-container">
        <div class="contact-header">
            <h1>Reportar Problema / Contacto</h1>
            <p style="margin-bottom: 40px;">¿Tienes algún problema o sugerencia? Nos encantaría ayudarte.</p>
        </div>

        <div class="contact-content">
            <form id="contactForm" class="contact-form">
                <div class="form-row">
                    <div class="input-group">
                        <label for="name">Nombre completo</label>
                        <input type="text" id="name" name="name" value="<?php echo $isLoggedIn ? htmlspecialchars($userName) : ''; ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $isLoggedIn ? htmlspecialchars(getUserEmail()) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="subject">Asunto</label>
                    <select id="subject" name="subject" required>
                        <option value="">Selecciona un tema</option>
                        <option value="problema_tecnico">Problema técnico</option>
                        <option value="problema_medicamento">Problema con medicamentos</option>
                        <option value="problema_cuenta">Problema con mi cuenta</option>
                        <option value="sugerencia">Sugerencia</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                
                <div class="input-group">
                    <label for="message">Mensaje</label>
                    <textarea id="message" name="message" rows="6" placeholder="Describe tu problema o sugerencia en detalle..." required></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Enviar Mensaje</button>
                
                <div id="submitFeedback" class="submit-feedback" style="display: none;">
                    <span id="feedbackMessage"></span>
                </div>
            </form>

            <div class="contact-info" style="margin-top: 40px;">
                <br>
                <h3>Otras formas de contacto</h3>
                <div class="contact-methods">
                    <div class="contact-method">
                        <div>
                            <br>
                            <strong>Email</strong>
                            <p><img src="/icons/lightmode/email-dash.png" alt="Email" class="contact-icon email-icon"> pastilleroautomatico7@gmail.com</p>
                        </div>
                    </div>
                    <div class="contact-method">
                        <div>
                            <br>
                            <strong>Teléfono</strong>
                            <p><img src="/icons/lightmode/telephone.png" alt="Teléfono" class="contact-icon telephone-icon">Temporalmente no disponible</p>
                        </div>
                    </div>
                    <div class="contact-method">
                        <div>
                            <br>
                            <strong>Horario de atención</strong>
                            <p>Lunes a Viernes: 9:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modo oscuro
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

            // Manejar formulario de contacto
            const form = document.getElementById('contactForm');
            const feedback = document.getElementById('submitFeedback');
            const feedbackMessage = document.getElementById('feedbackMessage');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = form.querySelector('.submit-btn');
                const originalText = submitBtn.textContent;
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando...';
                
                const formData = new FormData(form);
                
                fetch('enviar_reporte_problema.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    feedback.style.display = 'block';
                    if (data.success) {
                        feedback.className = 'submit-feedback success';
                        feedbackMessage.textContent = data.message || '¡Mensaje enviado con éxito!';
                        form.reset();
                    } else {
                        feedback.className = 'submit-feedback error';
                        feedbackMessage.textContent = data.error || 'Error al enviar el mensaje';
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    
                    setTimeout(() => {
                        feedback.style.display = 'none';
                    }, 5000);
                })
                .catch(error => {
                    feedback.style.display = 'block';
                    feedback.className = 'submit-feedback error';
                    feedbackMessage.textContent = 'Error al enviar el mensaje. Por favor intenta nuevamente.';
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    
                    setTimeout(() => {
                        feedback.style.display = 'none';
                    }, 5000);
                });
            });
        });
    </script>
</body>
</html> 
