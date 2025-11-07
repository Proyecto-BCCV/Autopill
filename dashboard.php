<?php
require_once 'session_init.php';
require_once 'conexion.php';

// Auto-ejecutar monitor de alarmas (cron automático)
@include_once 'cron_monitor.php';

// Verificar si el usuario está autenticado
requireAuth();

// Bloquear acceso si el usuario es cuidador
if (isCuidador()) {
    header('Location: dashboard_cuidador.php');
    exit;
}

// Comentado completamente: ya no verificamos ESP al cargar dashboard
// if (!isCuidador()) {
//     $userId = getUserId();
//     // Verificar si necesita vincular ESP (forzar verificación)
//     asociarEspSiNoExiste($userId, getUserName());
//     
//     // Comentado: ya no redirigimos automáticamente a vincular_esp
//     // if (!empty($_SESSION['needs_esp32']) && empty($_SESSION['esp_vinculado_once'])) {
//     //     $_SESSION['post_link_redirect'] = 'dashboard.php';
//     //     header('Location: vincular_esp.php');
//     //     exit;
//     // }
// }

// Obtener información del usuario
$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();

// Obtener información del cuidador si existe
$cuidadorNombre = null;
if ($userRole === 'paciente') {
    $pacienteId = getUserId();
    $sql = "SELECT u.nombre_usuario FROM cuidadores c 
            JOIN usuarios u ON c.cuidador_id = u.id_usuario 
            WHERE c.paciente_id = ? AND c.estado = 'activo' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pacienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $cuidadorData = $result->fetch_assoc();
        $cuidadorNombre = $cuidadorData['nombre_usuario'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="dashboard-container">
        <div class="pastillero-card">
            <!-- Modal de confirmación -->
            <div id="confirmModal" class="modal">
                <div class="modal-content">
                    <h3>¿Eliminar cuidador?</h3>
                    <p>¿Estás seguro de que deseas eliminar a <?php echo $cuidadorNombre ? htmlspecialchars($cuidadorNombre) : 'tu cuidador'; ?> como tu cuidador?</p>
                    <div class="modal-buttons">
                        <button class="modal-btn confirm-btn" onclick="eliminarCuidador()">Sí, eliminar</button>
                        <button class="modal-btn cancel-btn" onclick="cerrarModal()">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Cartel de cuidador confirmado -->
            <div id="cuidadorAlert" class="cuidador-alert" style="display: none;">
                <div class="cuidador-alert-content">
                    <span class="cuidador-icon">👤</span>
                    <span class="cuidador-text"><?php echo $cuidadorNombre ? htmlspecialchars($cuidadorNombre) : 'Tu cuidador'; ?> es tu cuidador(a)</span>
                    <button class="cuidador-close" onclick="cerrarAlertaCuidador()">×</button>
                </div>
            </div>

            <!-- Información del cuidador -->
            <div id="cuidadorInfo" class="cuidador-info" style="display: none;">
                <div class="cuidador-info-content">
                    <span class="cuidador-icon">👤</span>
                    <span class="cuidador-name"><?php echo $cuidadorNombre ? htmlspecialchars($cuidadorNombre) : 'Tu cuidador'; ?> es tu cuidador(a)</span>
                    <button class="cuidador-delete" onclick="mostrarConfirmacion()">✖</button>
                </div>
            </div>

            <div class="pastillero-header">
                <h2>Pastillero A</h2>
                <p class="subtitle">Tus módulos</p>
            </div>


            <div class="modules-list">
                <div class="module-item" data-module="1">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 1</span>
                            <span class="pill-name" id="pill-name-1">Sin programar</span>
                        </div>
                    </div>
                    <div class="module-right">
                        <div class="alarmas-container" id="alarmas-modulo-1">
                            <!-- Las alarmas se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>

                <div class="module-item" data-module="2">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 2</span>
                            <span class="pill-name" id="pill-name-2">Sin programar</span>
                        </div>
                    </div>
                    <div class="module-right">
                        <div class="alarmas-container" id="alarmas-modulo-2">
                            <!-- Las alarmas se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>

                <div class="module-item" data-module="3">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 3</span>
                            <span class="pill-name" id="pill-name-3">Sin programar</span>
                        </div>
                    </div>
                    <div class="module-right">
                        <div class="alarmas-container" id="alarmas-modulo-3">
                            <!-- Las alarmas se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>

                <div class="module-item" data-module="4">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 4</span>
                            <span class="pill-name" id="pill-name-4">Sin programar</span>
                        </div>
                    </div>
                    <div class="module-right">
                        <div class="alarmas-container" id="alarmas-modulo-4">
                            <!-- Las alarmas se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>

                <div class="module-item" data-module="5">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 5</span>
                            <span class="pill-name" id="pill-name-5">Sin programar</span>
                        </div>
                    </div>
                    <div class="module-right">
                        <div class="alarmas-container" id="alarmas-modulo-5">
                            <!-- Las alarmas se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Indicador de actualización automática -->
    <div id="indicador-actualizacion" class="indicador-actualizacion" style="display:none;">
        <div class="punto"></div>
        <span>Actualización automática activa</span>
    </div>

    <script>
    // Menú móvil unificado
    function toggleMobileMenu(event) {
        if (event) event.stopPropagation();
        const mobileMenu = document.querySelector('.mobile-menu');
        if (mobileMenu) mobileMenu.classList.toggle('active');
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

        // Mostrar información del cuidador si está confirmado
        const cuidadorConfirmado = localStorage.getItem('cuidadorConfirmado');
        if (cuidadorConfirmado === 'true') {
            document.getElementById('cuidadorInfo').style.display = 'block';
            actualizarEspacioCuidador();
        }

        // Cerrar menú al hacer clic fuera y con Escape
        document.addEventListener('click', function(e) {
            const mobileMenu = document.querySelector('.mobile-menu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            if (mobileMenu && mobileMenu.classList.contains('active') && 
                !mobileMenu.contains(e.target) && 
                (!menuBtn || !menuBtn.contains(e.target))) {
                mobileMenu.classList.remove('active');
            }
        });
        document.addEventListener('keydown', function(e) {
            const mobileMenu = document.querySelector('.mobile-menu');
            if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
            }
        });

        // Sincronizar con el formato de hora del menú (formatoHora24)
        // El menú ya maneja la sincronización, solo escuchamos cambios
        function aplicarFormatoHora() {
            const formato24 = localStorage.getItem('formatoHora24') === '1';
            document.body.classList.toggle('24-hour-format', formato24);
            // Recargar alarmas para aplicar nuevo formato
            cargarConfiguracionesModulos();
        }
        
        // Escuchar cambios del formato desde el menú u otras pestañas
        window.addEventListener('formatoHora24Changed', aplicarFormatoHora);
        window.addEventListener('storage', (e) => {
            if (e.key === 'formatoHora24') {
                aplicarFormatoHora();
            }
        });
        
        // Aplicar formato inicial
        aplicarFormatoHora();

    // Cargar configuraciones de módulos
    cargarConfiguracionesModulos();
    // Iniciar actualización automática
    iniciarActualizacionAutomatica();
    });

    // Actualizar badge de notificaciones no leídas
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
            }catch(e){ /* silencioso */ }
        }
        refreshBadge();
        setInterval(refreshBadge, 10000);
        window.addEventListener('focus', refreshBadge);
    })();

    function mostrarConfirmacion() {
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function cerrarModal() {
        const modal = document.getElementById('confirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Función para actualizar el espacio del cuidador (definida fuera del DOMContentLoaded para acceso global)
    function actualizarEspacioCuidador() {
        const pastilleroCard = document.querySelector('.pastillero-card');
        const cuidadorInfo = document.getElementById('cuidadorInfo');
        const cuidadorAlert = document.getElementById('cuidadorAlert');

        if ((cuidadorInfo && cuidadorInfo.style.display === 'block') || 
            (cuidadorAlert && cuidadorAlert.style.display === 'block')) {
            pastilleroCard.classList.add('has-cuidador');
        } else {
            pastilleroCard.classList.remove('has-cuidador');
        }
    }

    function eliminarCuidador() {
        localStorage.removeItem('cuidadorConfirmado');
        document.getElementById('cuidadorInfo').style.display = 'none';
        actualizarEspacioCuidador();
        cerrarModal();
    }

    function cerrarAlertaCuidador() {
        document.getElementById('cuidadorAlert').style.display = 'none';
        document.getElementById('cuidadorInfo').style.display = 'block';
        actualizarEspacioCuidador();
    }

    // Función para manejar la selección de módulos
    document.querySelectorAll('.module-item').forEach(module => {
        module.addEventListener('click', function(e) {
            // Remover la clase selected de todos los módulos
            document.querySelectorAll('.module-item').forEach(m => {
                m.classList.remove('selected');
            });
            
            // Añadir la clase selected al módulo clickeado
            this.classList.add('selected');
            
            // Obtener el número de módulo
            const moduleNumber = this.dataset.module;
            
            // Redirigir a la página de configuración del módulo
            setTimeout(() => {
                window.location.href = `module-config.php?modulo=${moduleNumber}`;
            }, 300);
        });
    });

    // Función para cargar las configuraciones de los módulos
    function cargarConfiguracionesModulos() {
        fetchJSON('obtener_modulos.php?_ts=' + Date.now())
            .then(data => {
                if (!data) return;
                if (data.success) {
                    data.modulos.forEach(modulo => actualizarVistaModulo(modulo));
                } else {
                    console.error('Error al cargar módulos:', data.error);
                }
            });
        // Cargar alarmas para cada módulo
        cargarAlarmasModulos();
    }

    // Función para cargar las alarmas de todos los módulos
    function cargarAlarmasModulos() {
        for (let i = 1; i <= 5; i++) {
            cargarAlarmasModulo(i);
        }
    }

    // Función para cargar las alarmas de un módulo específico
    function cargarAlarmasModulo(numeroModulo) {
        fetchJSON(`obtener_alarmas_modulo.php?modulo=${numeroModulo}&_ts=${Date.now()}`)
            .then(data => {
                if (!data) return;
                if (data.success) {
                    mostrarAlarmasModulo(numeroModulo, data.alarmas);
                    actualizarIndicadorPastillas(numeroModulo, data.cantidad_pastillas);
                } else {
                    console.error(`Error al cargar alarmas del módulo ${numeroModulo}:`, data.error);
                }
            });
    }

    // Función para mostrar las alarmas de un módulo
    function mostrarAlarmasModulo(numeroModulo, alarmas) {
        const container = document.getElementById(`alarmas-modulo-${numeroModulo}`);
        if (!container) return;

        if (alarmas.length === 0) {
            container.innerHTML = '<div class="no-alarmas">Sin programar</div>';
            return;
        }

        // Fijar nombre del módulo desde la primera alarma si el nombre del módulo no está seteado
        try {
            const pillNameEl = document.getElementById(`pill-name-${numeroModulo}`);
            if (pillNameEl && (!pillNameEl.textContent || pillNameEl.textContent.trim() === 'Sin programar')) {
                const first = alarmas[0] || {};
                let derived = first.nombre_medicamento || '';
                if (!derived && first.nombre_alarma) {
                    // ejemplo esperado: "Módulo 1 - Vitamina D"
                    const parts = String(first.nombre_alarma).split(' - ');
                    if (parts.length > 1) derived = parts.slice(1).join(' - ');
                }
                if (derived) pillNameEl.textContent = derived;
            }
        } catch(e) { /* silencioso */ }

        let html = '';
    const formato24 = localStorage.getItem('formatoHora24') === '1';
    alarmas.forEach(alarma => {
        const hora = new Date(`2000-01-01T${alarma.hora_alarma}`);
        let horaFormateada = '';
        if (!formato24) {
            let horas = hora.getHours();
            let minutos = hora.getMinutes();
            let ampm = horas >= 12 ? 'PM' : 'AM';
            horas = horas % 12;
            horas = horas ? horas : 12; // 0 -> 12
            horaFormateada = `${horas}:${minutos.toString().padStart(2, '0')} ${ampm}`;
        } else {
            let horas = hora.getHours();
            let minutos = hora.getMinutes();
            horaFormateada = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
        }
        const dias = alarma.dias_semana;
        const diasNombres = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        let diasHTML = '';
        for (let i = 0; i < 7; i++) {
            const isActive = dias[i] === '1';
            const clase = isActive ? 'day active' : 'day';
            diasHTML += `<span class="${clase}">${diasNombres[i]}</span>`;
        }
        html += `
            <div class="alarma-item-dashboard">
                <div class="alarma-hora">${horaFormateada}</div>
                <div class="alarma-dias">${diasHTML}</div>
            </div>
        `;
    });

        container.innerHTML = html;
    }

    // Función para actualizar indicador de pastillas
    function actualizarIndicadorPastillas(numeroModulo, cantidadPastillas) {
        const moduleItem = document.querySelector(`.module-item[data-module="${numeroModulo}"]`);
        if (!moduleItem) return;

        // Eliminar indicador existente si lo hay
        const indicadorExistente = moduleItem.querySelector('.no-pills-indicator');
        if (indicadorExistente) {
            indicadorExistente.remove();
        }

        // Si cantidad es 0, agregar el indicador AL FINAL del módulo
        if (cantidadPastillas === 0) {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const iconPath = isDarkMode ? '/icons/darkmode/incorrect.png' : '/icons/lightmode/incorrect.png';
            
            const indicador = document.createElement('div');
            indicador.className = 'no-pills-indicator';
            indicador.innerHTML = `
                <img src="${iconPath}" alt="Sin pastillas">
                <span>¡Sin pastillas!</span>
            `;
            
            // Insertar AL FINAL del module-item
            moduleItem.appendChild(indicador);
        }
    }

    // Función para actualizar la vista de un módulo
    function actualizarVistaModulo(modulo) {
        const moduleNumber = modulo.numero_modulo;
        
        // Actualizar nombre del medicamento - usar directamente el valor del backend
        const pillName = document.getElementById(`pill-name-${moduleNumber}`);
        if (pillName) {
            pillName.textContent = modulo.nombre_medicamento || 'Sin programar';
        }
    }

    // Cargar módulos al iniciar la página
    cargarConfiguracionesModulos();

    // Auto-actualización y manual
    function iniciarActualizacionAutomatica() {
        let lastSig = null;
        async function tick() {
            const data = await fetchJSON('estado_dashboard.php?_ts=' + Date.now());
            if (!data || !data.success) return;
            if (lastSig !== null && data.sig !== lastSig) {
                cargarConfiguracionesModulos();
                mostrarNotificacionCambio();
            }
            lastSig = data.sig;
        }
        setInterval(tick, 8000);
        window.addEventListener('focus', tick);
        setTimeout(tick, 1200);
        mostrarIndicadorActualizacion();
    }

    function mostrarIndicadorActualizacion() {
        const ind = document.getElementById('indicador-actualizacion');
        if (!ind) return;
        ind.style.display = 'flex';
        setTimeout(() => { ind.style.display = 'none'; }, 5000);
    }

    function mostrarNotificacionCambio() {
        const notif = document.createElement('div');
        notif.className = 'notificacion-cambio';
        
        // Crear icono según el tema actual
        const isDarkMode = document.body.classList.contains('dark-mode');
        const iconPath = isDarkMode ? '/icons/darkmode/refresh.png' : '/icons/lightmode/refresh.png';
        
        const icon = document.createElement('img');
        icon.src = iconPath;
        icon.style.cssText = 'width:16px;height:16px;margin-right:6px;vertical-align:middle;';
        
        const text = document.createTextNode('Datos actualizados');
        
        notif.appendChild(icon);
        notif.appendChild(text);
        notif.style.cssText = 'position:fixed;top:20px;right:20px;background:#4CAF50;color:#fff;padding:8px 14px;border-radius:6px;z-index:1000;font-size:13px;box-shadow:0 2px 10px rgba(0,0,0,0.2);display:flex;align-items:center;';
        document.body.appendChild(notif);
        setTimeout(()=>{ if (notif.parentNode) notif.parentNode.removeChild(notif); }, 2200);
    }

    // Utilidad robusta para JSON
    async function fetchJSON(url, options) {
        try {
            const resp = await fetch(url, options);
            const ct = resp.headers.get('content-type') || '';
            const text = await resp.text();
            if (!ct.includes('application/json')) {
                console.error('[fetchJSON] Respuesta no JSON', {url, status: resp.status, ct, preview: text.slice(0,300)});
                return null;
            }
            try { return JSON.parse(text); }
            catch(e){ console.error('[fetchJSON] Error parseando JSON', {url, err:e, raw:text.slice(0,300)}); return null; }
        } catch (err) {
            console.error('[fetchJSON] Falla de red', {url, err});
            return null;
        }
    }

    // Estilos mínimos para el indicador
    (function addStyles(){
        const style = document.createElement('style');
        style.textContent = `
        .indicador-actualizacion{position:fixed;bottom:20px;right:20px;background:rgba(0,0,0,0.7);color:#fff;padding:8px 12px;border-radius:20px;font-size:12px;z-index:1000;display:flex;align-items:center;gap:8px}
        .indicador-actualizacion .punto{width:8px;height:8px;border-radius:50%;background:#fff;animation:pulse 2s infinite}
        @keyframes pulse{0%{opacity:1}50%{opacity:.5}100%{opacity:1}}
        `;
        document.head.appendChild(style);
    })();
    </script>
    
    <!-- Sistema de monitoreo automático deshabilitado - ahora se ejecuta via cron_monitor.php -->
</body>
</html>
