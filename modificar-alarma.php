<?php
require_once 'session_init.php';
require_once 'conexion.php';
requireAuth();

// Obtener información del usuario
$userName = getUserName();

// Si un cuidador pasa paciente_id, validar permiso
$pacienteId = isset($_GET['paciente_id']) ? trim($_GET['paciente_id']) : null;
if ($pacienteId) {
    if (!function_exists('isCuidador') || !isCuidador() || !function_exists('canManagePaciente') || !canManagePaciente($pacienteId)) {
        $pacienteId = null; // ignorar si no tiene permiso
    }
}

// Obtener el módulo desde la URL
$moduleId = isset($_GET['modulo']) ? max(1, min(5, intval($_GET['modulo']))) : 1;

// Validar que el módulo esté entre 1 y 5
if ($moduleId < 1 || $moduleId > 5) {
    $moduleId = 1;
}

// Obtener las alarmas del módulo específico
$alarmas = [];
$formato24 = 0; // 0=12h, 1=24h
try {
    $userId = $_SESSION['user_id'];
    // Leer preferencia de formato de hora del usuario
    try {
        $cfgStmt = $conn->prepare("SELECT formato_hora_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1");
        if ($cfgStmt) {
            $cfgStmt->bind_param("s", $userId);
            $cfgStmt->execute();
            $cfgRes = $cfgStmt->get_result();
            if ($cfg = $cfgRes->fetch_assoc()) {
                $formato24 = intval($cfg['formato_hora_config'] ?? 0);
            }
        }
    } catch (Exception $e) { /* continuar con 12h por defecto */ }
    $pacienteObjetivo = $pacienteId ? $pacienteId : $userId;
    $sql = "SELECT a.*, c.nombre_esp, m.nombre_medicamento
            FROM alarmas a 
            INNER JOIN codigos_esp c ON a.id_esp_alarma = c.id_esp 
        INNER JOIN modulos m ON m.numero_modulo = ? AND m.id_usuario = ?
        WHERE c.id_usuario = ? AND a.nombre_alarma LIKE CONCAT('Módulo ', ?)
            ORDER BY a.hora_alarma ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $moduleId, $pacienteObjetivo, $pacienteObjetivo, $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $alarmas[] = $row;
    }
} catch (Exception $e) {
    // En caso de error, usar array vacío
    $alarmas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Alarma - Módulo <?php echo $moduleId; ?> - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body>
    <?php $menuLogoHref = $pacienteId ? ('dashboard_paciente.php?paciente_id=' . urlencode($pacienteId)) : 'dashboard.php'; include __DIR__ . '/partials/menu.php'; ?>

    <main class="module-config">
        <div class="config-section">
            <div class="config-title-row">
                <a href="<?php echo $pacienteId ? 'module-config.php?paciente_id=' . urlencode($pacienteId) : 'module-config.php?modulo=' . $moduleId; ?>" class="back-arrow" style="text-decoration:none; color:var(--text-color); font-size:24px;">←</a>
                <h3 class="config-title">Modificar Alarma - Módulo <?php echo $moduleId; ?></h3>
            </div>
        </div>

        <?php if (empty($alarmas)): ?>
            <div class="config-section" style="text-align:center;">
                <h3>No hay alarmas configuradas</h3>
                <p>No se encontraron alarmas en este módulo.</p>
                <a href="agregar-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteId ? '&paciente_id=' . urlencode($pacienteId) : ''; ?>" class="config-submit-btn">Crear Primera Alarma</a>
            </div>
        <?php else: ?>
            <div class="config-section">
                <h3>Alarmas Existentes</h3>
                <div class="alarmas-list">
                    <?php foreach ($alarmas as $alarma): ?>
                        <div class="alarma-item">
                            <div class="alarma-info">
                                <h4><?php echo htmlspecialchars($alarma['nombre_medicamento'] ?? 'Módulo ' . $moduleId); ?></h4>
                                <p class="alarma-time">
                                    <strong>Hora:</strong> 
                                    <span class="time-display" data-time="<?php echo $alarma['hora_alarma']; ?>">
                                        <?php 
                                            $hora = new DateTime($alarma['hora_alarma']);
                                            echo ($formato24 === 1) ? $hora->format('H:i') : $hora->format('g:i A'); 
                                        ?>
                                    </span>
                                </p>
                                <p class="alarma-days">
                                    <strong>Días:</strong> 
                                    <?php
                                        $dias = $alarma['dias_semana'];
                                        $diasNombres = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
                                        $diasActivos = [];
                                        for ($i = 0; $i < 7; $i++) {
                                            if ($dias[$i] === '1') {
                                                $diasActivos[] = $diasNombres[$i];
                                            }
                                        }
                                        echo implode(', ', $diasActivos);
                                    ?>
                                </p>
                            </div>
                            <div class="alarma-actions">
                                <button class="edit-alarma-btn" style="padding: 12px 32px; font-size: 16px;" onclick="window.location.href='editar-alarma.php?id=<?php echo $alarma['id_alarma']; ?><?php echo $pacienteId ? '&paciente_id=' . urlencode($pacienteId) : ''; ?>'">Editar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="config-section">
            <a href="<?php echo $pacienteId ? 'module-config.php?paciente_id=' . urlencode($pacienteId) . '&modulo=' . $moduleId : 'module-config.php?modulo=' . $moduleId; ?>" class="back-btn">← Volver</a>
        </div>
    </main>

    <script>
        // Función para formatear hora según preferencia
        function formatTime(timeString, is24) {
            const date = new Date('1970-01-01 ' + timeString);
            if (is24) {
                return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false });
            } else {
                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            }
        }

        // Actualizar todas las horas cuando cambia el formato
        function updateAllTimes(is24) {
            const timeDisplays = document.querySelectorAll('.time-display');
            timeDisplays.forEach(display => {
                const timeValue = display.getAttribute('data-time');
                if (timeValue) {
                    display.textContent = formatTime(timeValue, is24);
                }
            });
        }

        // Escuchar cambios en el formato de hora y actualizar sin recargar
        window.addEventListener('formatoHora24Changed', function(e) {
            const is24 = e.detail.is24;
            updateAllTimes(is24);
        });

        // Función para alternar el menú móvil
        function toggleMobileMenu(event) {
            event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
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

        // Función para editar alarma
        function editarAlarma(idAlarma) {
            // Por ahora solo muestra un mensaje
            alert('Función de edición en desarrollo. Alarma ID: ' + idAlarma);
            // TODO: Implementar página de edición
        }

        // Función para eliminar alarma
        function eliminarAlarma(idAlarma) {
            if (confirm('¿Estás seguro de que quieres eliminar esta alarma?')) {
                // Por ahora solo muestra un mensaje
                alert('Función de eliminación en desarrollo. Alarma ID: ' + idAlarma);
                // TODO: Implementar eliminación
            }
        }
    </script>
</body>
</html>
