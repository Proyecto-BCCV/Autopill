<?php
require_once 'session_init.php';
require_once 'conexion.php';

// Si ya está autenticado, ir al dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$pending = $_SESSION['google_pending'] ?? null;
if (!$pending || empty($pending['sub']) || empty($pending['email'])) {
    header('Location: login.php?error=oauth_missing_pending');
    exit;
}

$name = htmlspecialchars($pending['name'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegí tu rol - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body class="auth-page">
    <?php $menuLogoHref = 'index.php'; include __DIR__ . '/partials/menu.php'; ?>

    <div class="auth-container">
        <div class="auth-header">
            <h1>Completa tu registro</h1>
            <p>Ingresaste con Google como <strong><?php echo $name ?: $email; ?></strong></p>
            <p>Elegí tu rol para finalizar:</p>
        </div>

        <div class="auth-body">
            <form id="googleRoleForm">
                <div class="role-selector">
                    <div class="role-options">
                        <div class="role-option">
                            <input type="radio" id="rolePatient" name="role" value="paciente" required>
                            <label for="rolePatient">Paciente</label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="roleAssistant" name="role" value="cuidador" required>
                            <label for="roleAssistant">Cuidador</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="registro-submit-btn">Finalizar</button>
            </form>
        </div>
    </div>

    <script>
        // Inicializar modo oscuro y manejar envío del formulario
        document.addEventListener('DOMContentLoaded', function() {
            // Aplicar modo oscuro de inmediato si estaba guardado
            const savedMode = localStorage.getItem('darkMode');
            if (savedMode === 'enabled') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }

            // Hook al toggle del menú parcial
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.checked = (savedMode === 'enabled');
                darkModeToggle.addEventListener('change', function(){
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'enabled');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'disabled');
                    }
                });
            }

            // Manejo del formulario de selección de rol
            const form = document.getElementById('googleRoleForm');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const role = document.querySelector('input[name="role"]:checked')?.value;
                if (!role) { alert('Seleccioná un rol'); return; }
                const btn = form.querySelector('button[type="submit"]');
                const original = btn.textContent; btn.disabled = true; btn.textContent = 'Guardando...';
                try {
                    const resp = await fetch('google_finalize_signup.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ role })
                    });
                    const data = await resp.json();
                    if (!resp.ok || !data.success) throw new Error(data.error || 'Error al finalizar');
                    window.location.href = data.redirect || 'dashboard.php';
                } catch (err) {
                    alert(err.message);
                    btn.disabled = false; btn.textContent = original;
                }
            });
        });
    </script>
</body>
</html>
