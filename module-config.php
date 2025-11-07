<?php
// Instrumentación defensiva para IIS: capturar fatales y no dejar 500 en blanco
@ini_set('display_errors', '1');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);
require_once 'session_init.php';
// Cargar conexión de forma segura
require_once 'conexion.php';
requireAuth();

// Soporte para que un cuidador abra configuración de módulos de un paciente
$pacienteIdOriginal = isset($_GET['paciente_id']) ? trim($_GET['paciente_id']) : null; // Siempre conservar para enlaces
$pacienteId = null; // ID validado para operaciones
if ($pacienteIdOriginal !== null && $pacienteIdOriginal !== '' && function_exists('isCuidador') && isCuidador()) {
    if (function_exists('canManagePaciente') && canManagePaciente($pacienteIdOriginal)) {
        $pacienteId = $pacienteIdOriginal;
    } elseif (preg_match('/^0[0-9]+$/', $pacienteIdOriginal)) { // intentar sin ceros
        $alt = ltrim($pacienteIdOriginal, '0');
        if ($alt === '') { $alt = '0'; }
        if (function_exists('canManagePaciente') && canManagePaciente($alt)) {
            $pacienteId = $pacienteIdOriginal; // seguimos usando el original en las URLs
        }
    }
}
// Variable para enlaces (aunque validación falle se mantiene contexto visual, operaciones se protegen al enviar al backend)
$pacienteIdForLinks = ($pacienteIdOriginal && function_exists('isCuidador') && isCuidador()) ? $pacienteIdOriginal : null;

// Obtener información del usuario (por si la UI lo necesita)
$userName = function_exists('getUserName') ? getUserName() : '';

// Obtener el módulo actual
$moduleId = isset($_GET['modulo']) ? max(1, min(5, intval($_GET['modulo']))) : 1;

// Obtener preferencias del CUIDADOR si está viendo un paciente
$cuidadorPrefs = ['darkMode' => 0, 'time24' => 0];
if ($pacienteId !== null && function_exists('isCuidador') && isCuidador()) {
    // Este es un cuidador viendo un paciente - usar preferencias del cuidador
    $cuidadorActualId = getUserId();
    if (isset($conn) && $conn instanceof mysqli) {
        if ($stmtPref = $conn->prepare("SELECT modo_oscuro_config, formato_hora_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1")) {
            $stmtPref->bind_param('s', $cuidadorActualId);
            if ($stmtPref->execute()) {
                $resPref = $stmtPref->get_result();
                if ($rowPref = $resPref->fetch_assoc()) {
                    $cuidadorPrefs['darkMode'] = (int)($rowPref['modo_oscuro_config'] ?? 0);
                    $cuidadorPrefs['time24'] = (int)($rowPref['formato_hora_config'] ?? 0);
                }
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
    <title>Configuración de Módulo - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        body {
            background: var(--bg-primary, #f5f5f5);
            min-height: 100vh;
            color: var(--text-color, #333);
        }

        .module-config {
            max-width: 1400px;  /* Aumentado de 800px para hacerlo más amplio */
            margin: 60px auto 0;
            padding: 30px;
        }

        .main-content {
            border-radius: 15px;
            min-height: 700px;
            display: flex;
            flex-direction: column;
        }

        .config-section {
            width: 350px;
            color: var(--text-color, #333);
        }

        .config-section:last-child {
            width: 350px;
            flex-grow: 1;   /* Hace que esta sección ocupe el espacio disponible */
        }

        .module-select-buttons {
            gap: 25px;
            margin: 40px auto;
            max-width: 1000px;  /* Vuelto a 1000px */
        }

        .back-arrow {
            color: var(--text-color, #333);
            font-size: 28px;
        }

        .config-title {
            color: var(--text-color, #333);
            font-size: 1.8rem;
        }

        .back-btn {
            color: var(--text-secondary, #666);
            font-size: 1.1em;
        }
        
        .module-selector {
            cursor: pointer;
            color: var(--text-color, #333);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .module-selector:hover {
            color: var(--text-secondary, #666);
        }
        
        .module-selector-dropdown {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-secondary, #fff);
            border: 1px solid var(--border-color, #ddd);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 9999;
            min-width: 150px;
        }
        
        .module-option {
            display: block;
            padding: 12px 16px;
            color: var(--text-color, #333);
            text-decoration: none;
            border-bottom: 1px solid var(--border-color, #ddd);
            transition: background-color 0.3s ease;
        }
        
        .module-option:last-child {
            border-bottom: none;
        }
        
        .module-option:hover {
            background: var(--bg-hover, #f0f0f0);
        }
        
        .module-option.active {
            background: var(--accent-color, #C154C1);
            color: white;
        }
        
        .config-title-row {
            position: relative;
        }
        
        .module-selector-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9998;
        }

        @media (max-width: 768px) {
            .module-config {
                padding: 15px;
                margin-top: 60px;
            }
            
            .config-section {
                padding: 25px;  /* Ajustado para móvil */
            }
        }
    </style>
</head>
<body class="module-config-page">
    <script>
        // Aplicar modo oscuro INMEDIATAMENTE desde localStorage antes que cualquier otra cosa
        (function() {
            try {
                const darkMode = localStorage.getItem('darkMode');
                if (darkMode === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                    document.body.classList.add('dark-mode');
                }
            } catch(e) {}
        })();
        
        // Configuración para cuidador viendo paciente
        const isCaregiverViewingPatient = <?php echo $pacienteId !== null ? 'true' : 'false'; ?>;
        const caregiverPrefs = {
            darkMode: <?php echo (int)$cuidadorPrefs['darkMode']; ?>,
            time24: <?php echo (int)$cuidadorPrefs['time24']; ?>
        };
        
        // No sobrescribir - dejar que localStorage sea la fuente de verdad
        
        function toggleMobileMenu(event){ if(event) event.stopPropagation(); const m=document.querySelector('.mobile-menu'); if(m) m.classList.toggle('active'); }
        document.addEventListener('click', function(e){ const m=document.querySelector('.mobile-menu'); const b=document.querySelector('.mobile-menu-btn'); if(m&&m.classList.contains('active') && !m.contains(e.target) && (!b||!b.contains(e.target))) m.classList.remove('active'); });
        document.addEventListener('keydown', function(e){ const m=document.querySelector('.mobile-menu'); if(e.key==='Escape'&&m&&m.classList.contains('active')) m.classList.remove('active'); });
        // Badge notificaciones
        (function(){ const badge = document.getElementById('notifBadge'); const badgeHeader = document.getElementById('notifBadgeHeader'); async function refresh(){ try{ const r=await fetch('notifications_count.php?_ts='+Date.now()); const ct=r.headers.get('content-type')||''; const t=await r.text(); if(!ct.includes('application/json')) return; const d=JSON.parse(t); const c=d&&d.success?(d.count||0):0; if(badge){ if(c>0){ badge.textContent=c>99?'99+':String(c); badge.classList.add('show'); } else { badge.textContent=''; badge.classList.remove('show'); } } if(badgeHeader){ if(c>0){ badgeHeader.textContent=c>99?'99+':String(c); badgeHeader.classList.add('show'); } else { badgeHeader.textContent=''; badgeHeader.classList.remove('show'); } } }catch(e){} } refresh(); setInterval(refresh,10000); window.addEventListener('focus', refresh); })();
    </script>
    <?php
        include __DIR__ . '/partials/menu.php';
    ?>

    <main class="module-config">
        <div class="main-content">
            <div class="config-section">
                <div class="config-title-row">
                    <a href="<?php echo $pacienteIdForLinks ? 'dashboard_paciente.php?paciente_id=' . urlencode($pacienteIdForLinks) : 'dashboard.php'; ?>" class="back-arrow">←</a>
                    <h3 class="config-title">
                        Configuración del 
                        <span class="module-selector" onclick="toggleModuleSelector()">
                            Módulo <?php echo $moduleId; ?> ▼
                        </span>
                    </h3>
                </div>
                
                                 <!-- Selector de módulos -->
                 <div id="moduleSelector" class="module-selector-dropdown" style="display: none;">
                     <?php for ($i = 1; $i <= 5; $i++): ?>
                         <a href="?modulo=<?php echo $i; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>" class="module-option <?php echo ($i == $moduleId) ? 'active' : ''; ?>">
                             Módulo <?php echo $i; ?>
                         </a>
                     <?php endfor; ?>
                 </div>
                 
                 <!-- Fondo oscuro para el dropdown -->
                 <div id="moduleSelectorOverlay" class="module-selector-overlay" style="display: none;"></div>
            </div>

            <div class="config-section" style="text-align:center;">
                <?php
                // Mostrar cantidad de pastillas en el módulo (arriba del menú de acciones)
                try {
                    $conn2 = isset($conn) ? $conn : obtenerConexion();
                    $pacienteConsulta = ($pacienteId !== null) ? $pacienteId : ($_SESSION['user_id'] ?? null);
                    if ($conn2 && $pacienteConsulta) {
                        // 1) Verificar si existen alarmas para ESTE módulo de ESTE usuario
                        $totalAlarmas = 0;
                        if ($stmtAl = $conn2->prepare("SELECT COUNT(*) AS total FROM alarmas a INNER JOIN codigos_esp c ON c.id_esp = a.id_esp_alarma WHERE c.id_usuario = ? AND a.nombre_alarma LIKE ?")) {
                            $pattern = 'Módulo ' . $moduleId . '%';
                            $stmtAl->bind_param('ss', $pacienteConsulta, $pattern);
                            $stmtAl->execute();
                            $resAl = $stmtAl->get_result();
                            if ($rowAl = $resAl->fetch_assoc()) { $totalAlarmas = (int)($rowAl['total'] ?? 0); }
                            $stmtAl->close();
                        }

                        echo '<div style="margin:0 0 16px 0;color:var(--text-color);font-weight:600;">';
                        if ($totalAlarmas === 0) {
                            // Sin alarmas en este módulo: mostrar '-'
                            echo 'Cantidad de pastillas en el módulo: -';
                        } else {
                            // 2) Si hay alarmas, intentar mostrar el stock del módulo (si existe la columna)
                            $hasCol = false;
                            if ($res = $conn2->query("SHOW COLUMNS FROM modulos LIKE 'cantidad_pastillas_modulo'")) {
                                $hasCol = ($res->num_rows > 0);
                            }
                            if ($hasCol) {
                                if ($stmtCnt = $conn2->prepare("SELECT cantidad_pastillas_modulo FROM modulos WHERE id_usuario = ? AND numero_modulo = ? LIMIT 1")) {
                                    $stmtCnt->bind_param('si', $pacienteConsulta, $moduleId);
                                    $stmtCnt->execute();
                                    $resCnt = $stmtCnt->get_result();
                                    $rowCnt = $resCnt->fetch_assoc();
                                    $cantidadP = $rowCnt['cantidad_pastillas_modulo'] ?? null;
                                    if ($cantidadP === null || $cantidadP === '') {
                                        echo 'Cantidad de pastillas en el módulo: N/D';
                                    } else {
                                        echo 'Cantidad de pastillas en el módulo: ' . intval($cantidadP);
                                    }
                                    $stmtCnt->close();
                                } else {
                                    echo 'Cantidad de pastillas en el módulo: N/D';
                                }
                            } else {
                                echo 'Cantidad de pastillas en el módulo: N/D';
                            }
                        }
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    // Silencioso
                }
                ?>
                <p>¿Qué desea hacer?</p>
                <div class="module-select-buttons" style="margin-top:10px;">
                    <a class="module-select-button" href="agregar-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>">Agregar nueva alarma</a>
                    <a class="module-select-button" href="modificar-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>">Modificar alarma</a>
                    <a class="module-select-button" href="eliminar-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>" style="background:#c62828;color:#fff;border-color:#c62828;">Eliminar alarma</a>
                    <?php if ($totalAlarmas > 0): ?>
                        <a class="module-select-button" href="modificar-nombre-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>">Cambiar nombre</a>
                    <?php else: ?>
                        <a class="module-select-button" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;" title="No hay alarmas en este módulo">Cambiar nombre</a>
                    <?php endif; ?>
                    <?php if ($totalAlarmas > 0): ?>
                        <a class="module-select-button" href="modificar-cantidad-pastillas.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteIdForLinks ? '&paciente_id=' . urlencode($pacienteIdForLinks) : ''; ?>">Modificar cantidad de pastillas</a>
                    <?php else: ?>
                        <a class="module-select-button" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;" title="No hay alarmas en este módulo">Modificar cantidad de pastillas</a>
                    <?php endif; ?>
                </div>
                <a href="<?php echo $pacienteIdForLinks ? 'dashboard_paciente.php?paciente_id=' . urlencode($pacienteIdForLinks) : 'dashboard.php'; ?>" class="back-btn" style="margin-top:16px;display:inline-block;">Volver</a>
            </div>
        </div>
    </main>

    <!-- Modal de selección de módulo reutilizado -->
    <div id="moduleSelectModal" class="modal" style="display:none;">
        <div class="modal-content module-select-modal">
            <div class="modal-header">
                <h3>Selecciona un módulo</h3>
                <span id="closeModuleSelect" class="close">×</span>
            </div>
                <div class="modal-body">
                <div class="module-select-buttons">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i !== $moduleId): ?>
                            <button type="button" class="module-select-button" data-target="module-config.php?modulo=<?php echo $i; ?><?php echo $pacienteId ? '&paciente_id=' . urlencode($pacienteId) : ''; ?>">Módulo <?php echo $i; ?></button>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu(event) {
            event.stopPropagation();
            document.querySelector('.mobile-menu').classList.toggle('active');
        }
        
        // Función para alternar el selector de módulos
        function toggleModuleSelector() {
            const moduleSelector = document.getElementById('moduleSelector');
            const moduleSelectorOverlay = document.getElementById('moduleSelectorOverlay');
            
            if (moduleSelector.style.display === 'none') {
                moduleSelector.style.display = 'block';
                moduleSelectorOverlay.style.display = 'block';
            } else {
                moduleSelector.style.display = 'none';
                moduleSelectorOverlay.style.display = 'none';
            }
        }
        
        // Cerrar selector de módulos al hacer clic fuera
        document.addEventListener('click', function(e) {
            const moduleSelector = document.getElementById('moduleSelector');
            const moduleSelectorOverlay = document.getElementById('moduleSelectorOverlay');
            const moduleSelectorBtn = document.querySelector('.module-selector');
            
            if (moduleSelector && moduleSelector.style.display !== 'none' && 
                !moduleSelector.contains(e.target) && 
                !moduleSelectorBtn.contains(e.target)) {
                moduleSelector.style.display = 'none';
                moduleSelectorOverlay.style.display = 'none';
            }
        });

        // Abrir/cerrar modal selector
        document.addEventListener('DOMContentLoaded', () => {
            // SIEMPRE usar localStorage como fuente de verdad para el modo oscuro
            const savedMode = localStorage.getItem('darkMode');
            const darkModeToggle = document.getElementById('darkModeToggle');
            
            // Aplicar modo oscuro si está guardado
            if (savedMode === 'enabled') {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            } else if (savedMode === 'disabled') {
                document.documentElement.classList.remove('dark-mode');
                document.body.classList.remove('dark-mode');
            }
            
            // Configurar el toggle
            if (darkModeToggle) {
                darkModeToggle.checked = (savedMode === 'enabled');
                
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.documentElement.classList.add('dark-mode');
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'enabled');
                    } else {
                        document.documentElement.classList.remove('dark-mode');
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

            const toggle = document.getElementById('moduleSelectorToggle');
            const modal = document.getElementById('moduleSelectModal');
            const closeBtn = document.getElementById('closeModuleSelect');
            if (toggle && modal) {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    modal.style.display = 'flex';
                    toggle.textContent = '▴';
                });
                if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; toggle.textContent = '▾'; });
                modal.addEventListener('click', (e) => { if (e.target === modal) { modal.style.display = 'none'; toggle.textContent = '▾'; } });
                // Navegación por botones
                document.querySelectorAll('.module-select-button[data-target]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const target = btn.getAttribute('data-target');
                        if (target) window.location.href = target;
                    });
                });
            }
        });
    </script>
</body>
</html>