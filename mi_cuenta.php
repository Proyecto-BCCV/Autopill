<?php
require_once "session_init.php";
require_once "conexion.php";

// Verificar si el usuario está autenticado
requireAuth();

$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();
$userId = getUserId();

// Obtener vínculos según rol
$vinculos = [];
if ($conn) {
    if ($userRole === 'cuidador') {
        if ($st = $conn->prepare("SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, c.estado FROM cuidadores c INNER JOIN usuarios u ON c.paciente_id = u.id_usuario WHERE c.cuidador_id = ?")) {
            $st->bind_param('s', $userId);
            if ($st->execute()) {
                $rs = $st->get_result();
                while ($r = $rs->fetch_assoc()) { $vinculos[] = $r; }
            }
        }
    } else { // paciente
        if ($st = $conn->prepare("SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, c.estado FROM cuidadores c INNER JOIN usuarios u ON c.cuidador_id = u.id_usuario WHERE c.paciente_id = ?")) {
            $st->bind_param('s', $userId);
            if ($st->execute()) {
                $rs = $st->get_result();
                while ($r = $rs->fetch_assoc()) { $vinculos[] = $r; }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        
        /* Estilos específicos para la página de cuenta */
        .account-page {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            min-height: 100vh;
        }

        .account-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 60px;
            margin-bottom: 30px;
            padding: 20px;
            flex-wrap: wrap;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            gap: 20px;
        }

        .account-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .user-id-display {
            font-size: 16px;
            color: #666;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .account-options {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 0;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .account-option {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            color: var(--element-bg);
        }

        .account-option:last-child {
            border-bottom: none;
        }

        .account-option:hover {
            background: #e9ecef;
        }

        .account-option-icon {
            font-size: 24px;
            margin-right: 16px;
            width: 40px;
            text-align: center;
        }

        .account-option-content {
            flex: 1;
        }

        .account-option-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 4px 0;
        }

        .account-option-description {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .account-option-arrow {
            font-size: 18px;
            color: #999;
        }

        .delete-option {
            color: #dc3545 !important;
        }

        .delete-option:hover {
            background: #f8d7da !important;
        }

        .delete-option .account-option-title {
            color: #dc3545;
        }



        /* Responsive */
        @media (max-width: 768px) {
            .account-page {
                padding: 15px;
                max-width: 100%;
            }
            
            .account-header {
                gap: 12px;
                margin-top: 70px;
                margin-bottom: 25px;
                padding: 16px;
            }
            
            .account-title {
                font-size: 22px;
            }
            
            .user-id-display {
                font-size: 14px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 480px) {
            .account-page {
                padding: 12px;
            }
            
            .account-header {
                padding: 14px;
                margin-top: 70px;
                margin-bottom: 20px;
                gap: 10px;
            }
            
            .account-title {
                font-size: 20px;
            }
            
            .user-id-display {
                font-size: 13px;
                padding: 6px 10px;
            }
            
            .account-option {
                padding: 16px 12px;
            }
            
            .account-option-icon {
                font-size: 20px;
                margin-right: 12px;
                width: 32px;
            }
            
            .account-option-title {
                font-size: 15px;
            }
            
            .account-option-description {
                font-size: 13px;
            }
        }

        @media (max-width: 360px) {
            .account-page {
                padding: 10px;
            }
            
            .account-header {
                padding: 12px;
                gap: 8px;
            }
            
            .account-title {
                font-size: 18px;
            }
            
            .user-id-display {
                font-size: 12px;
                padding: 5px 8px;
            }
            
            .account-option {
                padding: 14px 10px;
            }
            
            .account-option-icon {
                font-size: 18px;
                margin-right: 10px;
                width: 28px;
            }
            
            .account-option-title {
                font-size: 14px;
            }
            
            .account-option-description {
                font-size: 12px;
            }
        }

        /* Modo oscuro */
        .dark-mode .account-page {
            background: #1a1a1a;
        }

        .dark-mode .account-header {
            background: #2d2d2d;
            border-color: #555;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .dark-mode .account-title {
            color: #ffffff;
        }

        .dark-mode .user-id-display {
            background: #2d2d2d;
            color: #b0b0b0;
            border-color: #555;
        }

        .dark-mode .account-options {
            background: #2d2d2d;
        }

        .dark-mode .account-option {
            border-bottom-color: #555;
        }

        .dark-mode .account-option:hover {
            background: #3d3d3d;
        }

        .dark-mode .account-option-title {
            color: #ffffff;
        }

        .dark-mode .account-option-description {
            color: #b0b0b0;
        }

        .dark-mode .account-option-arrow {
            color: #999;
        }

        .dark-mode .delete-option:hover {
            background: #4a1a1a !important;
        }


    </style>
</head>
<body>
    <?php $menuHideDashboard = ($userRole === 'cuidador'); $menuLogoHref = ($userRole === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="account-page">
        <div class="account-header">
            <h1 class="account-title">Tu cuenta</h1>
            <div class="user-id-display">ID: <?php echo htmlspecialchars($userId); ?></div>
        </div>

        <div class="account-options">
            <a href="#" class="account-option" onclick="cambiarContrasena()">
                <div class="account-option-icon"><span class="password-change-icon"></span></div>
                <div class="account-option-content">
                    <h3 class="account-option-title">Cambiar contraseña</h3>
                    <p class="account-option-description">Actualiza tu contraseña de acceso</p>
                </div>
                <div class="account-option-arrow">›</div>
            </a>

            <a href="#" class="account-option" onclick="cambiarCorreo()">
                <div class="account-option-icon"><span class="email-change-icon"></span></div>
                <div class="account-option-content">
                    <h3 class="account-option-title">Cambiar correo electrónico</h3>
                    <p class="account-option-description">Actualiza tu dirección de email</p>
                </div>
                <div class="account-option-arrow">›</div>
            </a>

            <a href="gestionar_vinculos.php" class="account-option" style="cursor:pointer;">
                <div class="account-option-icon"><span class="connection-icon"></span></div>
                <div class="account-option-content">
                    <h3 class="account-option-title">Gestionar vínculos</h3>
                    <p class="account-option-description">Gestiona tus vínculos con otros usuarios</p>
                </div>
                <div class="account-option-arrow">›</div>
            </a>

            <?php if ($userRole === 'paciente' && function_exists('usuarioTieneEsp') && usuarioTieneEsp($userId, true)): ?>
            <a href="#" class="account-option delete-option" onclick="openDeleteEspModal(event)">
                <div class="account-option-icon"><span class="incorrect-icon"></span></div>
                <div class="account-option-content">
                    <h3 class="account-option-title">Eliminar pastillero vinculado</h3>
                    <p class="account-option-description">Elimina el vínculo con tu pastillero</p>
                </div>
                <div class="account-option-arrow">›</div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Modal confirmación eliminar vínculo ESP -->
        <div id="deleteEspModal" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>Confirmar eliminación de vínculo</h3>
                <p>Al aceptar eliminar el vínculo de tu cuenta con tu pastillero, tendrás que volver a vincularlo para acceder a tus módulos. ¿Estás seguro de de esto?</p>
                <div class="modal-buttons">
                    <button class="modal-btn cancel-btn" type="button" onclick="closeDeleteEspModal()">Cancelar</button>
                    <button class="modal-btn confirm-btn" type="button" onclick="confirmDeleteEsp(this)">Aceptar</button>
                </div>
            </div>
        </div>

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
        });

        // Funciones para las opciones de cuenta
        function cambiarFotoPerfil() {
            alert('Función de cambio de foto de perfil - Próximamente disponible');
        }

        function cambiarContrasena() {
            window.location.href = 'cambiar_password.php';
        }

        function cambiarCorreo() {
            window.location.href = 'cambiar_email.php';
        }

        // Desvincular relación cuidador-paciente
        function desvincular(targetId, btn){
            if(!targetId) return;
            if(!confirm('¿Confirmas desvincular este vínculo?')) return;
            btn.disabled = true; btn.textContent = '...';
            fetch('unlink_vinculo.php', {
                method:'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ target_id: targetId })
            }).then(r=>r.json())
            .then(data=>{
                if(!data.success){ throw new Error(data.error||'Error'); }
                const row = document.querySelector('tr[data-target-id="'+CSS.escape(targetId)+'"]');
                if(row){ row.remove(); }
                if(document.querySelectorAll('tbody tr').length===0){
                    const tbl = document.querySelector('.account-option table');
                    if(tbl){ tbl.outerHTML = '<p style="margin:0; font-size:14px; color:#666;">No tienes vínculos registrados.</p>'; }
                }
            })
            .catch(err=>{ alert('No se pudo desvincular: '+err.message); })
            .finally(()=>{ btn.disabled=false; btn.textContent='Desvincular'; });
        }

        // Delegación eventos botones desvincular
        document.addEventListener('click', function(e){
            const t = e.target;
            if(t.classList.contains('vinc-btn')){
                const id = t.getAttribute('data-id');
                desvincular(id, t);
            }
        });

        // --- Eliminar vínculo ESP (paciente) ---
        function openDeleteEspModal(e){ if(e) e.preventDefault(); document.getElementById('deleteEspModal').style.display = 'flex'; }
        function closeDeleteEspModal(){ document.getElementById('deleteEspModal').style.display = 'none'; }
        async function confirmDeleteEsp(btn){
            btn.disabled = true; btn.textContent = 'Eliminando...';
            try {
                const res = await fetch('eliminar_vinculo_esp.php', { method:'POST', headers: { 'X-Requested-With':'XMLHttpRequest' } });
                const txt = await res.text();
                let data; try { data = JSON.parse(txt); } catch(e){ throw new Error(txt || 'Respuesta inválida'); }
                if(!data.success){ throw new Error(data.error || 'No se pudo eliminar el vínculo'); }
                closeDeleteEspModal();
                alert('Se eliminó el vínculo con tu pastillero. Deberás vincular nuevamente para acceder a tus módulos.');
                // Forzar estado para que el menú muestre "Vincular pastillero"
                try { localStorage.setItem('formatoHora24', localStorage.getItem('formatoHora24') || '0'); } catch(_){ }
                // Redirigir a la página de vinculación
                window.location.href = 'vincular_esp.php';
            } catch(err){
                alert(err.message);
            } finally {
                btn.disabled = false; btn.textContent = 'Aceptar';
            }
        }
    </script>
</body>
</html>
