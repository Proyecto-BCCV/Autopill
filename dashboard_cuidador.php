<?php
require_once "session_init.php";
require_once "conexion.php";

// Auto-ejecutar monitor de alarmas (cron automático)
@include_once 'cron_monitor.php';

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
$userId = getUserId();

// Obtener solicitudes del cuidador
$solicitudesPendientes = [];
$solicitudesActivas = [];
$sql = "SELECT c.id, c.paciente_id, c.estado, c.fecha_creacion, u.nombre_usuario, u.email_usuario 
    FROM cuidadores c 
    INNER JOIN usuarios u ON c.paciente_id = u.id_usuario 
    WHERE c.cuidador_id = ? 
    ORDER BY c.fecha_creacion DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Calcular estado en vivo inicial (umbral 120s)
    $row['estado_vivo'] = (function_exists('isUserActive') && isUserActive($row['paciente_id'], 120)) ? 'activo' : 'inactivo';
    if ($row['estado'] === 'activo') {
        $solicitudesActivas[] = $row;
    } else {
        $solicitudesPendientes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Cuidador - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="dashboard-container">
        <div class="cuidador-dashboard">
            <div class="dashboard-header">
                <h1>Dashboard de Cuidador</h1>
                <p>Gestiona tus solicitudes y pacientes</p>
                <button class="btn-primary" onclick="window.location.href='seleccionar_usuario.php'">
                    <span class="patient-plus-icon"></span>Agregar Nuevo Paciente
                </button>
            </div>
            
            <div class="solicitudes-section">
                <h2>Solicitudes pendientes</h2>
                <div class="no-solicitudes" id="pendientesEmpty" style="<?php echo empty($solicitudesPendientes) ? '' : 'display:none;'; ?>">
                    <div class="no-solicitudes-content">
                        <span class="no-solicitudes-icon"><span class="clipboard-icon"></span></span>
                        <h3>No tienes solicitudes pendientes</h3>
                        <p>Los usuarios aceptados aparecerán en la sección de confirmados.</p>
                    </div>
                </div>
                <div class="solicitudes-grid" id="pendientesGrid">
                    <?php foreach ($solicitudesPendientes as $solicitud): ?>
                        <div class="solicitud-card" data-estado="pendiente">
                            <div class="solicitud-header">
                                <div class="solicitud-info">
                                    <h3><?php echo htmlspecialchars($solicitud["nombre_usuario"]); ?></h3>
                                    <p><?php echo htmlspecialchars($solicitud["email_usuario"]); ?></p>
                                    <p class="fecha">Solicitud: <?php echo date("d/m/Y H:i", strtotime($solicitud["fecha_creacion"])); ?></p>
                                </div>
                                <div class="solicitud-status">
                                    <span class="estado-chip pendiente">Pendiente</span>
                                </div>
                            </div>
                            <div class="solicitud-actions">
                                <button class="btn-secondary" style="border-color:#b00020;color:#b00020" onclick="cancelarSolicitud('<?php echo $solicitud['id']; ?>', '<?php echo htmlspecialchars($solicitud['nombre_usuario']); ?>')">Cancelar solicitud</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="solicitudes-section" style="margin-top:32px;">
                <h2>Usuarios confirmados</h2>
                <div class="no-solicitudes" id="activosEmpty" style="<?php echo empty($solicitudesActivas) ? '' : 'display:none;'; ?>">
                    <div class="no-solicitudes-content">
                        <span class="no-solicitudes-icon"><span class="patient-icon"></span></span>
                        <h3>Aún no tienes usuarios confirmados</h3>
                        <p>Cuando un usuario acepte tu solicitud aparecerá aquí.</p>
                    </div>
                </div>
                <div class="solicitudes-grid" id="activosGrid">
                    <?php foreach ($solicitudesActivas as $solicitud): ?>
                        <div class="solicitud-card" data-estado="activo">
                            <div class="solicitud-header">
                                <div class="solicitud-info">
                                    <h3><?php echo htmlspecialchars($solicitud["nombre_usuario"]); ?></h3>
                                    <p><?php echo htmlspecialchars($solicitud["email_usuario"]); ?></p>
                                    <p class="fecha">Confirmado: <?php echo date("d/m/Y H:i", strtotime($solicitud["fecha_creacion"])); ?></p>
                                </div>
                                <div class="solicitud-status">
                                    <span class="estado-chip <?php echo ($solicitud['estado_vivo'] === 'activo' ? 'aceptado' : 'inactive'); ?>"><?php echo ucfirst($solicitud['estado_vivo']); ?></span>
                                </div>
                            </div>
                            <div class="solicitud-actions">
                                <button class="btn-primary" onclick="verPastillero('<?php echo $solicitud["paciente_id"]; ?>', '<?php echo htmlspecialchars($solicitud["nombre_usuario"]); ?>')">
                                    Ver Pastillero
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de confirmación -->
    <div class="confirmation-modal-overlay" id="confirmationModal">
        <div class="confirmation-modal">
            <div class="confirmation-modal-header" id="confirmationModalTitle">Confirmar acción</div>
            <div class="confirmation-modal-body" id="confirmationModalMessage">¿Estás seguro de realizar esta acción?</div>
            <div class="confirmation-modal-actions">
                <button class="btn-cancel" onclick="closeConfirmationModal()">Cancelar</button>
                <button class="btn-confirm" id="confirmationModalConfirmBtn">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
    // Función para alternar el menú móvil
    function toggleMobileMenu(event) {
        event.stopPropagation();
        const mobileMenu = document.querySelector(".mobile-menu");
        mobileMenu.classList.toggle("active");
        
        // Asegurar que el nav-menu esté cerrado cuando se abre el menú móvil
        const navMenu = document.querySelector(".nav-menu");
        if (navMenu && navMenu.classList.contains("active")) {
            navMenu.classList.remove("active");
        }
    }
    
    // Función para ver el pastillero de un paciente
    function verPastillero(pacienteId, pacienteNombre) {
        window.location.href = `dashboard_paciente.php?paciente_id=${pacienteId}&nombre=${encodeURIComponent(pacienteNombre)}`;
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        // Verificar y aplicar el modo oscuro INMEDIATAMENTE
        const savedMode = localStorage.getItem('darkMode');
        if (savedMode === 'enabled') {
            document.body.classList.add('dark-mode');
        }
        
        // Configurar el toggle del modo oscuro
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            // Establecer el estado inicial del toggle
            darkModeToggle.checked = (savedMode === 'enabled');
            
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
        document.addEventListener("click", function(e) {
            const mobileMenu = document.querySelector(".mobile-menu");
            const menuBtn = document.querySelector(".mobile-menu-btn");
            
            if (mobileMenu && mobileMenu.classList.contains("active") && 
                !mobileMenu.contains(e.target) && 
                !menuBtn.contains(e.target)) {
                mobileMenu.classList.remove("active");
            }
        });

        // Cerrar menú al presionar la tecla Escape
        document.addEventListener("keydown", function(e) {
            const mobileMenu = document.querySelector(".mobile-menu");
            if (e.key === "Escape" && mobileMenu && mobileMenu.classList.contains("active")) {
                mobileMenu.classList.remove("active");
            }
        });

        // Badge de notificaciones
        (function notifBadgeInit(){
            const badge = document.getElementById('notifBadge');
            const badgeHeader = document.getElementById('notifBadgeHeader');
            async function refreshBadge(){
                try{
                    const resp = await fetch('notifications_count.php?_ts=' + Date.now());
                    const ct = resp.headers.get('content-type')||'';
                    const text = await resp.text();
                    if (!ct.includes('application/json')) return;
                    const data = JSON.parse(text);
                    const count = data && data.success ? (data.count||0) : 0;
                    
                    // Actualizar badge del sidebar
                    if (badge){
                        if (count > 0){
                            badge.textContent = count > 99 ? '99+' : String(count);
                            badge.classList.add('show');
                        } else {
                            badge.textContent = '';
                            badge.classList.remove('show');
                        }
                    }
                    
                    // Actualizar badge del header
                    if (badgeHeader){
                        if (count > 0){
                            badgeHeader.textContent = count > 99 ? '99+' : String(count);
                            badgeHeader.classList.add('show');
                        } else {
                            badgeHeader.textContent = '';
                            badgeHeader.classList.remove('show');
                        }
                    }
                }catch(e){ /* silencio */ }
            }
            refreshBadge();
            setInterval(refreshBadge, 10000);
            window.addEventListener('focus', refreshBadge);
        })();
    });

    // ================= RECARGA AUTOMÁTICA DE PACIENTES =================
    function recargarPacientesActivos() {
            const url = 'obtener_pacientes_cuidador.php?_ts=' + Date.now();
            fetch(url, { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) return;

                    const pendientesGrid = document.getElementById('pendientesGrid');
                    const activosGrid = document.getElementById('activosGrid');
                    const pendientesEmpty = document.getElementById('pendientesEmpty');
                    const activosEmpty = document.getElementById('activosEmpty');

                    if (pendientesGrid) pendientesGrid.innerHTML = '';
                    if (activosGrid) activosGrid.innerHTML = '';

                    const pendientes = data.pendientes || [];
                    const activos = data.activos || [];

                    if (pendientesEmpty) {
                        pendientesEmpty.style.display = pendientes.length ? 'none' : 'block';
                    }
                    if (activosEmpty) {
                        activosEmpty.style.display = activos.length ? 'none' : 'block';
                    }

                    if (pendientesGrid) {
                        pendientes.forEach(solicitud => {
                            const card = document.createElement('div');
                            card.className = 'solicitud-card';
                            card.setAttribute('data-estado', 'pendiente');
                            card.innerHTML = `
                                <div class="solicitud-header">
                                    <div class="solicitud-info">
                                        <h3>${solicitud.nombre_usuario}</h3>
                                        <p>${solicitud.email_usuario}</p>
                                        <p class="fecha">Solicitud: ${solicitud.fecha_creacion}</p>
                                    </div>
                                    <div class="solicitud-status">
                                        <span class="estado-chip pendiente">Pendiente</span>
                                    </div>
                                </div>
                                <div class="solicitud-actions">
                                    <button class="btn-secondary" style="border-color:#b00020;color:#b00020" onclick="cancelarSolicitud('${solicitud.id}', '${(solicitud.nombre_usuario||'').replace(/'/g, "\\'")}')">Cancelar solicitud</button>
                                </div>
                            `;
                            pendientesGrid.appendChild(card);
                        });
                    }

                    if (activosGrid) {
                        activos.forEach(solicitud => {
                            const card = document.createElement('div');
                            card.className = 'solicitud-card';
                            card.setAttribute('data-estado', 'activo');
                            const liveClass = solicitud.estado_vivo === 'activo' ? 'aceptado' : 'inactive';
                            const liveText = solicitud.estado_vivo === 'activo' ? 'Activo' : 'Inactivo';
                            const nombreEscapado = (solicitud.nombre_usuario || '').replace(/'/g, "\\'");
                            card.innerHTML = `
                                <div class="solicitud-header">
                                    <div class="solicitud-info">
                                        <h3>${solicitud.nombre_usuario}</h3>
                                        <p>${solicitud.email_usuario}</p>
                                        <p class="fecha">Confirmado: ${solicitud.fecha_creacion}</p>
                                    </div>
                                    <div class="solicitud-status">
                                        <span class="estado-chip ${liveClass}">${liveText}</span>
                                    </div>
                                </div>
                                <div class="solicitud-actions">
                                    <button class="btn-primary" onclick="verPastillero('${solicitud.paciente_id}', '${nombreEscapado}')">Ver Pastillero</button>
                                </div>
                            `;
                            activosGrid.appendChild(card);
                        });
                    }
                })
                .catch((err) => {
                    console.warn('Error al recargar pacientes:', err);
                });
        }
    
    // Actualizar cada 10 segundos (más eficiente y aún responsive)
    // Como el heartbeat es cada 15s y el umbral 120s, 10s es suficiente para mostrar cambios
    setInterval(recargarPacientesActivos, 10000);
    
    // Llamar al cargar la página
    document.addEventListener('DOMContentLoaded', recargarPacientesActivos);
    
    // También actualizar cuando la ventana recupera el foco
    window.addEventListener('focus', recargarPacientesActivos);

    // Funciones del modal de confirmación
    function showConfirmationModal(title, message, onConfirm) {
        const modal = document.getElementById('confirmationModal');
        const titleEl = document.getElementById('confirmationModalTitle');
        const messageEl = document.getElementById('confirmationModalMessage');
        const confirmBtn = document.getElementById('confirmationModalConfirmBtn');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Remover listeners anteriores clonando el botón
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', function() {
            closeConfirmationModal();
            if (onConfirm) onConfirm();
        });
        
        modal.classList.add('show');
        
        // Cerrar modal al hacer clic fuera
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeConfirmationModal();
            }
        });
    }

    function closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        modal.classList.remove('show');
    }

    // Cancelar solicitud (lado cuidador)
    async function cancelarSolicitud(requestId, pacienteNombre) {
        if (!requestId) return;
        
        showConfirmationModal(
            'Cancelar solicitud',
            `¿Estás seguro de que deseas cancelar la solicitud pendiente para ${pacienteNombre}?`,
            async function() {
                try {
                    const resp = await fetch('confirmar_cuidador_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId, action: 'cancelar' })
                    });
                    const data = await resp.json().catch(() => null);
                    if (data && data.success) {
                        recargarPacientesActivos();
                    } else {
                        alert('No se pudo cancelar: ' + ((data && data.error) || 'Error desconocido'));
                    }
                } catch (e) {
                    alert('Error de red al cancelar');
                }
            }
        );
    }
    </script>
    
    <!-- Sistema de monitoreo automático deshabilitado - ahora se ejecuta via cron_monitor.php -->
</body>
</html>
