<?php
require_once 'session_init.php';
require_once 'conexion.php';

// Auto-ejecutar monitor de alarmas (cron automático)
@include_once 'cron_monitor.php';

// Verificar si el usuario está autenticado
requireAuth();

// Verificar que el usuario sea cuidador
if (!isCuidador()) {
    header("Location: dashboard.php");
    exit;
}

// Obtener el ID del usuario desde la URL
$pacienteId = $_GET['paciente_id'] ?? null;
$pacienteNombre = $_GET['nombre'] ?? '';

if (!$pacienteId) {
    header("Location: dashboard_cuidador.php");
    exit;
}

// Verificar que el cuidador tenga acceso a este usuario
$cuidadorId = getUserId();
$sql = "SELECT * FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = 'activo'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $cuidadorId, $pacienteId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard_cuidador.php");
    exit;
}

$relacionCuidador = $result->fetch_assoc();

// Obtener información del usuario
$sql = "SELECT * FROM usuarios WHERE id_usuario = ? AND rol = 'paciente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $pacienteId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard_cuidador.php");
    exit;
}

$paciente = $result->fetch_assoc();

// Obtener información del cuidador
$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();
$menuLogoHref = 'dashboard_cuidador.php';

// Nombre del cuidador para mostrar en la interfaz
$cuidadorNombre = $userName;

// Preferencias del CUIDADOR para formato de hora (desde BD)
$prefTime24 = 0; // 0 = 12h, 1 = 24h
$cuidadorId = getUserId(); // ID del cuidador logueado

if ($stmtPref = $conn->prepare("SELECT formato_hora_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1")) {
    $stmtPref->bind_param('s', $cuidadorId);
    if ($stmtPref->execute()) {
        $resPref = $stmtPref->get_result();
        if ($rowPref = $resPref->fetch_assoc()) {
            $prefTime24 = (int)($rowPref['formato_hora_config'] ?? 0);
        }
    }
}
// NOTA: El modo oscuro se lee directamente de localStorage en el cliente
// para usar siempre la preferencia actual del cuidador
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard del Usuario - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body>
    <script>
        // BLOQUEAR completamente el script del menú para manejar manualmente el modo oscuro
        window.__autopillDarkModeInit = true; // Prevenir inicialización automática del menú
        
        // Leer la preferencia ACTUAL del cuidador desde localStorage
        // Esto es lo que el cuidador tiene configurado AHORA en su navegador
        const darkModeFromStorage = localStorage.getItem('darkMode');
        const cuidadorDarkMode = darkModeFromStorage === 'enabled';
        
        // Aplicar INMEDIATAMENTE las preferencias del cuidador
        if (cuidadorDarkMode) {
            document.documentElement.classList.add('dark-mode');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.classList.remove('dark-mode');
            document.body.classList.remove('dark-mode');
        }
        
        // Bloquear persistencia en BD cuando estamos viendo dashboard de paciente
        window.__blockDarkModePersist = true;
        
        // Cuando el DOM esté listo, configurar el toggle
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                // Establecer el estado inicial del toggle según localStorage
                darkModeToggle.checked = cuidadorDarkMode;
                
                // Remover cualquier listener previo del menú
                const newToggle = darkModeToggle.cloneNode(true);
                darkModeToggle.parentNode.replaceChild(newToggle, darkModeToggle);
                
                // Agregar nuestro listener exclusivo para cambios temporales
                newToggle.addEventListener('change', function() {
                    const isDark = this.checked;
                    document.body.classList.toggle('dark-mode', isDark);
                    document.documentElement.classList.toggle('dark-mode', isDark);
                    
                    // Actualizar localStorage temporalmente (solo para esta sesión)
                    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
                    
                    // Disparar evento para actualizar favicon
                    window.dispatchEvent(new CustomEvent('darkModeChanged', { 
                        detail: { mode: isDark ? 'enabled' : 'disabled' } 
                    }));
                    
                    // NO guardar en BD - el cuidador solo cambia temporalmente la vista
                });
            }
        });
    </script>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="dashboard-container">
        <!-- Botón de volver -->
        <div style="margin-bottom: 20px;">
            <a href="dashboard_cuidador.php" style="color: #C154C1; text-decoration: none; font-weight: 500;">
                ← Volver al Dashboard
            </a>
        </div>

        <!-- Información del usuario -->
        <div style="background: linear-gradient(135deg, #C154C1, #8B5A8B); color: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
            <h1 style="margin: 0 0 10px 0; font-size: 28px;">Dashboard de <?php echo htmlspecialchars($paciente['nombre_usuario']); ?></h1>
            <p style="margin: 0; opacity: 0.9;">Gestionando el pastillero del usuario</p>
            
        </div>

        <div class="pastillero-card">
            <!-- Modal de confirmación -->
            <div id="confirmModal" class="modal">
                <div class="modal-content">
                    <h3>¿Eliminar cuidador?</h3>
                    <p>¿Estás seguro de que deseas eliminar a <?php echo htmlspecialchars($cuidadorNombre); ?> como tu cuidador?</p>
                    <div class="modal-buttons">
                        <button class="modal-btn confirm-btn" onclick="eliminarCuidador()">Sí, eliminar</button>
                        <button class="modal-btn cancel-btn" onclick="cerrarModal()">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Cartel de cuidador confirmado -->
            <div id="cuidadorAlert" class="cuidador-alert" style="display: none;">
                <div class="cuidador-alert-content">
                    <span class="cuidador-icon cuidador-icon-default"></span>
                    <span class="cuidador-text"><?php echo htmlspecialchars($cuidadorNombre); ?> es tu cuidador(a)</span>
                    <button class="cuidador-close" onclick="cerrarAlertaCuidador()">×</button>
                </div>
            </div>

            <!-- Información del cuidador -->
            <div id="cuidadorInfo" class="cuidador-info" style="display: none;">
                <div class="cuidador-info-content">
                    <span class="cuidador-icon cuidador-icon-default"></span>
                    <span class="cuidador-name"><?php echo htmlspecialchars($cuidadorNombre); ?> es tu cuidador(a)</span>
                    <button class="cuidador-delete" onclick="mostrarConfirmacion()">✖</button>
                </div>
            </div>

            <div class="pastillero-header">
                <h2>Pastillero de <?php echo htmlspecialchars($paciente['nombre_usuario']); ?></h2>
                <p class="subtitle">Módulos del usuario</p>
            </div>

            <div class="modules-list">
                <div class="module-item" data-module="1">
                    <div class="module-left">
                        <div class="module-info">
                            <span class="module-name">Módulo 1</span>
                            <span class="pill-name" id="pill-name-1">Sin programar</span>
                            <button class="config-btn" data-module="1">⚙️ Configurar</button>
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
                            <button class="config-btn" data-module="2">⚙️ Configurar</button>
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
                            <button class="config-btn" data-module="3">⚙️ Configurar</button>
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
                            <button class="config-btn" data-module="4">⚙️ Configurar</button>
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
                            <button class="config-btn" data-module="5">⚙️ Configurar</button>
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
        
        <!-- Indicador de actualización automática -->
        <div id="indicador-actualizacion" class="indicador-actualizacion">
            <div class="punto"></div>
            <span>Actualización automática activa</span>
        </div>
    </main>

    <script>
    // Preferencias del CUIDADOR (quien tiene la sesión iniciada)
    const __caregiverPrefs = Object.freeze({
        time24: <?php echo (int)$prefTime24; ?>
    });
    // Función para alternar el menú móvil
    function toggleMobileMenu(event) {
        event.stopPropagation();
        const mobileMenu = document.querySelector('.mobile-menu');
        mobileMenu.classList.toggle('active');
        
        // Asegurar que el nav-menu esté cerrado cuando se abre el menú móvil
        const navMenu = document.querySelector('.nav-menu');
        if (navMenu && navMenu.classList.contains('active')) {
            navMenu.classList.remove('active');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Las preferencias del cuidador ya se aplicaron en el script anterior
        // Toggle personalizado: permite al cuidador cambiar vista temporalmente (no persiste en BD del paciente)
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                } else {
                    document.body.classList.remove('dark-mode');
                }
                // NO guardamos en localStorage ni BD del paciente - solo cambio temporal para el cuidador
            });
        }

        // Mostrar información del cuidador si está confirmado
        const cuidadorConfirmado = localStorage.getItem('cuidadorConfirmado');
        if (cuidadorConfirmado === 'true') {
            document.getElementById('cuidadorInfo').style.display = 'block';
            actualizarEspacioCuidador();
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
                }catch(e){ /* silencioso */ }
            }
            refreshBadge();
            setInterval(refreshBadge, 10000);
            window.addEventListener('focus', refreshBadge);
        })();

        // Cerrar menú al presionar la tecla Escape
        document.addEventListener('keydown', function(e) {
            const mobileMenu = document.querySelector('.mobile-menu');
            if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
            }
        });

        // Manejo del formato de hora
        const timeFormatToggle = document.getElementById('timeFormatToggle');
        const initialFormat = __caregiverPrefs.time24 === 1 ? '24' : (localStorage.getItem('timeFormat') || '12');
        if (timeFormatToggle) {
            timeFormatToggle.checked = (initialFormat === '24');
            document.body.classList.toggle('24-hour-format', initialFormat === '24');
            actualizarFormatoHora(initialFormat);
            timeFormatToggle.addEventListener('change', function() {
                const formato = this.checked ? '24' : '12';
                localStorage.setItem('timeFormat', formato);
                document.body.classList.toggle('24-hour-format', formato === '24');
                if (typeof cargarAlarmasModulosPaciente === 'function') {
                    cargarAlarmasModulosPaciente();
                } else {
                    actualizarFormatoHora(formato);
                }
            });
        } else {
            actualizarFormatoHora(initialFormat);
        }

        // Cargar configuraciones de módulos del paciente
        cargarConfiguracionesModulosPaciente();
        
    // Iniciar actualización automática (poll por cambios reales del paciente)
    iniciarActualizacionAutomatica();
    });

    // Función para actualizar el formato de hora en los módulos
    function actualizarFormatoHora(formato) {
        const times = document.querySelectorAll('.module-time');
        
        times.forEach(time => {
            const timeText = time.textContent;
            if (timeText !== 'Sin programar') {
                // Convertir a formato adecuado
                if (timeText.includes(':')) {
                    let hours, minutes, period;
                    
                    if (timeText.includes('AM') || timeText.includes('PM')) {
                        // Convertir de 12h a 24h
                        [hours, minutes] = timeText.split(':');
                        minutes = minutes.split(' ')[0];
                        period = timeText.includes('PM') ? 'PM' : 'AM';
                        
                        if (formato === '24') {
                            // Convertir a 24h
                            let hours24 = parseInt(hours);
                            if (period === 'PM' && hours24 < 12) hours24 += 12;
                            if (period === 'AM' && hours24 === 12) hours24 = 0;
                            time.textContent = `${hours24.toString().padStart(2, '0')}:${minutes}`;
                        }
                    } else {
                        // Convertir de 24h a 12h
                        [hours, minutes] = timeText.split(':');
                        
                        if (formato === '12') {
                            // Convertir a 12h
                            let hours12 = parseInt(hours) % 12 || 12;
                            period = parseInt(hours) >= 12 ? 'PM' : 'AM';
                            time.textContent = `${hours12}:${minutes} ${period}`;
                        }
                    }
                }
            }
        });
    }

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

    // Redirección a configuración del módulo con contexto de paciente
    const pacienteCtx = '<?php echo $pacienteId; ?>';
    document.querySelectorAll('.config-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const mod = btn.getAttribute('data-module');
            if (pacienteCtx) {
                window.location.href = `module-config.php?paciente_id=${encodeURIComponent(pacienteCtx)}&modulo=${mod}`;
            }
        });
    });

    // Permitir click en todo el módulo para abrir configuración si se desea
    document.querySelectorAll('.module-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const mod = item.getAttribute('data-module');
            if (pacienteCtx) {
                window.location.href = `module-config.php?paciente_id=${encodeURIComponent(pacienteCtx)}&modulo=${mod}`;
            }
        });
    });

    // Función para cargar las configuraciones de los módulos del paciente
    function cargarConfiguracionesModulosPaciente() {
        // Usar el ID del paciente para cargar sus módulos
        const pacienteId = '<?php echo $pacienteId; ?>';

        // Limpiar todos los módulos y alarmas antes de pintar
        for (let i = 1; i <= 5; i++) {
            const pillName = document.getElementById(`pill-name-${i}`);
            if (pillName) pillName.textContent = 'Sin programar';
            const moduleTime = document.getElementById(`module-time-${i}`);
            if (moduleTime) moduleTime.textContent = '';
            const alarmasContainer = document.getElementById(`alarmas-modulo-${i}`);
            if (alarmasContainer) alarmasContainer.innerHTML = '<div class="no-alarmas">Sin programar</div>';
        }

        fetchJSON(`obtener_modulos_paciente.php?paciente_id=${pacienteId}&_ts=${Date.now()}`)
            .then(data => {
                if (!data) return; // ya se logueó el problema
                if (data.success) {
                    data.modulos.forEach(modulo => actualizarVistaModulo(modulo));
                } else {
                    console.error('Error al cargar módulos del paciente (JSON válido con error):', data.error);
                }
            });

        // Cargar alarmas para cada módulo del paciente
        cargarAlarmasModulosPaciente();
    }

    // Función para cargar las alarmas de todos los módulos del paciente
    function cargarAlarmasModulosPaciente() {
        const pacienteId = '<?php echo $pacienteId; ?>';
        for (let i = 1; i <= 5; i++) {
            cargarAlarmasModuloPaciente(i, pacienteId);
        }
    }

    // Función para cargar las alarmas de un módulo específico del paciente
    function cargarAlarmasModuloPaciente(numeroModulo, pacienteId) {
        fetchJSON(`obtener_alarmas_modulo_paciente.php?modulo=${numeroModulo}&paciente_id=${pacienteId}&_ts=${Date.now()}`)
            .then(data => {
                if (!data) return;
                if (data.success) {
                    mostrarAlarmasModulo(numeroModulo, data.alarmas);
                    actualizarIndicadorPastillas(numeroModulo, data.cantidad_pastillas);
                } else {
                    console.error(`Error al cargar alarmas del módulo ${numeroModulo} del paciente (JSON válido con error):`, data.error);
                }
            });
    }

    // Función para mostrar las alarmas de un módulo
    function mostrarAlarmasModulo(numeroModulo, alarmas) {
        const container = document.getElementById(`alarmas-modulo-${numeroModulo}`);
        if (!container) return;

        if (alarmas.length === 0) {
            container.innerHTML = '<div class="no-alarmas">Sin programar</div>';
            const fmt = (document.body.classList.contains('24-hour-format') || (__caregiverPrefs.time24 === 1)) ? '24' : '12';
            actualizarFormatoHora(fmt);
            return;
        }

        // Fijar nombre del módulo desde la primera alarma si el nombre del módulo no está seteado
        try {
            const pillNameEl = document.getElementById(`pill-name-${numeroModulo}`);
            if (pillNameEl && (!pillNameEl.textContent || pillNameEl.textContent.trim() === 'Sin programar')) {
                const first = alarmas[0] || {};
                let derived = first.nombre_medicamento || '';
                if (!derived && first.nombre_alarma) {
                    const parts = String(first.nombre_alarma).split(' - ');
                    if (parts.length > 1) derived = parts.slice(1).join(' - ');
                }
                if (derived) pillNameEl.textContent = derived;
            }
        } catch(e) { /* silencioso */ }

        let html = '';
        const formato = (document.body.classList.contains('24-hour-format') || (__caregiverPrefs.time24 === 1)) ? '24' : '12';
        alarmas.forEach(alarma => {
            const hora = new Date(`2000-01-01T${alarma.hora_alarma}`);
            let horaFormateada = '';
            if (formato === '12') {
                let horas = hora.getHours();
                let minutos = hora.getMinutes();
                let ampm = horas >= 12 ? 'PM' : 'AM';
                horas = horas % 12;
                horas = horas ? horas : 12;
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
    const fmt2 = (document.body.classList.contains('24-hour-format') || (__caregiverPrefs.time24 === 1)) ? '24' : '12';
    actualizarFormatoHora(fmt2);
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

        // Si cantidad es 0, agregar el indicador
        if (cantidadPastillas === 0) {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const iconPath = isDarkMode ? '/icons/darkmode/incorrect.png' : '/icons/lightmode/incorrect.png';
            
            const indicador = document.createElement('div');
            indicador.className = 'no-pills-indicator';
            indicador.innerHTML = `
                <img src="${iconPath}" alt="Sin pastillas">
                <span>¡Sin pastillas!</span>
            `;
            
            moduleItem.insertBefore(indicador, moduleItem.firstChild);
        }
    }

    // Función para actualizar la vista de un módulo
    function actualizarVistaModulo(modulo) {
        const moduleNumber = modulo.numero_modulo;
        // Nombre del medicamento - usar directamente el valor del backend
        const pillName = document.getElementById(`pill-name-${moduleNumber}`);
        if (pillName) {
            pillName.textContent = modulo.nombre_medicamento || 'Sin programar';
        }
        // Hora principal del módulo (si existe un elemento para mostrarla)
        const moduleTime = document.getElementById(`module-time-${moduleNumber}`);
        if (moduleTime) {
            if (modulo.hora_toma) {
                const hora = new Date(`2000-01-01T${modulo.hora_toma}`);
                const formato = (document.body.classList.contains('24-hour-format') || (__caregiverPrefs.time24 === 1)) ? '24' : (localStorage.getItem('timeFormat') === '24' ? '24' : '12');
                const horas = hora.getHours();
                const minutos = hora.getMinutes();
                if (formato === '12') {
                    const ampm = horas >= 12 ? 'PM' : 'AM';
                    const horas12 = (horas % 12) || 12;
                    moduleTime.textContent = `${horas12}:${minutos.toString().padStart(2, '0')} ${ampm}`;
                } else {
                    moduleTime.textContent = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
                }
            } else {
                moduleTime.textContent = '';
            }
        }
        // Recargar las alarmas de este módulo específico
        const pacienteId = '<?php echo $pacienteId; ?>';
        cargarAlarmasModuloPaciente(moduleNumber, pacienteId);
    }
    
    // Eliminada función eliminarModulo (antes usada para botones de eliminación de módulos)

    // Cargar módulos al iniciar la página
    cargarConfiguracionesModulosPaciente();
    
    // Refuerza la función de actualización automática y manual:
    function iniciarActualizacionAutomatica() {
        const pacienteId = '<?php echo $pacienteId; ?>';
        let lastSig = null;
        async function tick() {
            const data = await fetchJSON(`estado_dashboard.php?paciente_id=${encodeURIComponent(pacienteId)}&_ts=${Date.now()}`);
            if (!data || !data.success) return;
            if (lastSig !== null && data.sig !== lastSig) {
                // Cambió algo del paciente: recargar módulos y alarmas
                cargarConfiguracionesModulosPaciente();
                cargarAlarmasModulosPaciente();
                mostrarNotificacionCambio();
            }
            lastSig = data.sig;
        }
        setInterval(tick, 7000);
        window.addEventListener('focus', tick);
        setTimeout(tick, 1000);
        mostrarIndicadorActualizacion();
    }

    
    
    // Función para mostrar el indicador de actualización automática
    function mostrarIndicadorActualizacion() {
        const indicador = document.getElementById('indicador-actualizacion');
        if (indicador) {
            indicador.style.display = 'flex';
            
            // Ocultar después de 5 segundos
            setTimeout(() => {
                indicador.style.display = 'none';
            }, 5000);
        }
    }
    
    // Función para mostrar notificación sutil de cambios
    function mostrarNotificacionCambio() {
        // Crear notificación temporal
        const notif = document.createElement('div');
        notif.className = 'notificacion-cambio';
        notif.innerHTML = '🔄 Datos actualizados';
        notif.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 1000;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notif);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 3000);
    }
    
    // Agregar estilos CSS para la animación y botones
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .config-btn { margin-top:6px; background:#C154C1; color:#fff; border:1px solid #C154C1; border-radius:6px; padding:4px 10px; cursor:pointer; font-size:12px; }
        .config-btn:hover { background:#a844a8; border-color:#a844a8; }
        
        .eliminar-modulo-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        
        .eliminar-modulo-btn:hover {
            background-color: #c82333;
        }
        
        .module-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .indicador-actualizacion {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .indicador-actualizacion.activo {
            background: rgba(76, 175, 80, 0.9);
        }
        
        .indicador-actualizacion .punto {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Utilidad centralizada para parseo robusto de JSON evitando el error: Unexpected token '<'
    async function fetchJSON(url, options) {
        try {
            const resp = await fetch(url, options);
            const contentType = resp.headers.get('content-type') || '';
            const status = resp.status;
            const text = await resp.text();
            if (!contentType.includes('application/json')) {
                console.error('[fetchJSON] Respuesta no JSON', { url, status, contentType, preview: text.slice(0, 300) });
                mostrarBadgeErrorNetwork();
                return null;
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('[fetchJSON] Error parseando JSON', { url, status, error: e, raw: text.slice(0, 300) });
                mostrarBadgeErrorNetwork();
                return null;
            }
        } catch (err) {
            console.error('[fetchJSON] Falla de red/fetch', { url, error: err });
            mostrarBadgeErrorNetwork();
            return null;
        }
    }

    // Badge discreto si hay problemas de red / parseo
    let badgeErrorTimeout;
    function mostrarBadgeErrorNetwork() {
        let badge = document.getElementById('badge-error-red');
        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'badge-error-red';
            badge.textContent = '⚠️ Problema cargando datos';
            badge.style.cssText = 'position:fixed;bottom:20px;left:20px;background:#d9534f;color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;z-index:1001;font-family:system-ui,Arial';
            document.body.appendChild(badge);
        }
        badge.style.opacity = '1';
        clearTimeout(badgeErrorTimeout);
        badgeErrorTimeout = setTimeout(()=>{ if(badge) badge.style.opacity='0'; }, 5000);
    }
    </script>
    
    <!-- Sistema de monitoreo automático deshabilitado - ahora se ejecuta via cron_monitor.php -->
</body>
</html>
