<?php
require_once 'session_init.php';
require_once 'conexion.php';
requireAuth();

// Manejo dual: conservar parámetro original para enlaces aunque la validación falle
$pacienteIdOriginal = isset($_GET['paciente_id']) ? trim($_GET['paciente_id']) : null; // para UI
$pacienteId = null; // validado para operaciones (se añadirá al FormData solo si pasa)
if ($pacienteIdOriginal && function_exists('isCuidador') && isCuidador()) {
    if (function_exists('canManagePaciente') && canManagePaciente($pacienteIdOriginal)) {
        $pacienteId = $pacienteIdOriginal;
    } elseif (preg_match('/^0[0-9]+$/', $pacienteIdOriginal)) { // fallback sin ceros
        $alt = ltrim($pacienteIdOriginal, '0'); if ($alt==='') $alt='0';
        if (function_exists('canManagePaciente') && canManagePaciente($alt)) {
            $pacienteId = $pacienteIdOriginal;
        }
    }
}

// Obtener información del usuario
$userName = getUserName();

// Obtener el módulo desde la URL
$moduleId = isset($_GET['modulo']) ? max(1, min(5, intval($_GET['modulo']))) : 1;

// Validar que el módulo esté entre 1 y 5
if ($moduleId < 1 || $moduleId > 5) {
    $moduleId = 1;
}

// Verificar si ya existe un nombre de medicamento para este módulo
$nombreMedicamento = null;
$moduloExiste = false;
// Leer formato horario del usuario: 0=12h, 1=24h
$formato24 = 0;

try {
    $ownerId = $pacienteId ? $pacienteId : $_SESSION['user_id'];
    // Preferencia de formato horario
    if ($stmt = $conn->prepare("SELECT formato_hora_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1")) {
        $stmt->bind_param("s", $ownerId);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($row = $r->fetch_assoc()) { $formato24 = intval($row['formato_hora_config'] ?? 0); }
    }
    // Verificar si existen alarmas activas para este módulo
    // Esto evita que módulos sin alarmas muestren nombres de medicamentos obsoletos
    $sqlAlarmas = "SELECT COUNT(*) as total FROM alarmas a 
                   INNER JOIN codigos_esp c ON c.id_esp = a.id_esp_alarma 
                   WHERE c.id_usuario = ? AND a.nombre_alarma LIKE ?";
    $modulePattern = "Módulo " . $moduleId . "%";
    
    if ($stmt = $conn->prepare($sqlAlarmas)) {
        $stmt->bind_param("ss", $ownerId, $modulePattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $totalAlarmas = intval($row['total']);
        
        // Solo si hay alarmas existentes, obtener el nombre del medicamento
        if ($totalAlarmas > 0) {
            $sqlModulo = "SELECT nombre_medicamento FROM modulos WHERE id_usuario = ? AND numero_modulo = ?";
            if ($stmtModulo = $conn->prepare($sqlModulo)) {
                $stmtModulo->bind_param("si", $ownerId, $moduleId);
                $stmtModulo->execute();
                $resultModulo = $stmtModulo->get_result();
                if ($resultModulo->num_rows > 0) {
                    $rowModulo = $resultModulo->fetch_assoc();
                    $nombreMedicamento = $rowModulo['nombre_medicamento'];
                    $moduloExiste = true;
                }
            }
        }
        // Si no hay alarmas, tratar como primera vez (módulo vacío)
    }
} catch (Exception $e) {
    $nombreMedicamento = null;
    $moduloExiste = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Alarma - Módulo <?php echo $moduleId; ?> - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        
        .medication-hint {
            color: #666; 
            font-size: 12px; 
            margin-top: 5px; 
            display: block;
        }
        
        .dark-mode .medication-hint {
            color: #ccc;
        }
        
        .medication-hint strong {
            color: inherit;
        }
        
        /* Indicador de campo obligatorio */
        .medication-label {
            font-weight: 600;
        }
        
        .medication-input:invalid {
            border-color: #dc3545;
        }
        
        .medication-input:focus:invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2);
        }
    </style>
</head>
<body>
    <?php $menuLogoHref = (function_exists('isCuidador') && isCuidador()) ? 'dashboard_cuidador.php' : 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="module-config">
        <div class="config-section">
            <div class="config-title-row">
                <a href="<?php echo $pacienteIdOriginal ? 'module-config.php?paciente_id=' . urlencode($pacienteIdOriginal) . '&modulo=' . $moduleId : 'module-config.php?modulo=' . $moduleId; ?>" class="back-arrow" style="text-decoration:none; color:var(--text-color); font-size:24px;">←</a>
                <h3 class="config-title">Agregar Alarma - Módulo <?php echo $moduleId; ?></h3>
            </div>
            
            <?php if (!$moduloExiste || empty($nombreMedicamento)): ?>
                <!-- Primera alarma del módulo: pedir nombre del medicamento -->
                <div class="medication-info">
                    <label for="nombreMedicamento" class="medication-label">Nombre del medicamento: *</label>
                    <input type="text" id="nombreMedicamento" class="medication-input" 
                           placeholder="Nombre del medicamento" 
                           value="" 
                           required>
                </div>
            <?php else: ?>
                <!-- Módulo ya tiene nombre: mostrar nombre existente -->
                <div class="medication-info">
                    <label class="medication-label">Medicamento del módulo:</label>
                    <p class="medication-name"><?php echo htmlspecialchars($nombreMedicamento); ?></p>
                    <small class="medication-hint">
                        Agregando una nueva alarma para este medicamento. 
                        <?php if (isset($totalAlarmas) && $totalAlarmas > 0): ?>
                            <a href="modificar-nombre-alarma.php?modulo=<?php echo $moduleId; ?><?php echo $pacienteId ? '&paciente_id=' . urlencode($pacienteId) : ''; ?>" 
                               style="color: var(--color-principal); text-decoration: none;">
                                Cambiar nombre del medicamento
                            </a>
                        <?php else: ?>
                            <span style="color: #999; cursor: not-allowed;" title="No hay alarmas en este módulo">
                                Cambiar nombre del medicamento
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <div class="config-section">
            <h3>Hora de la alarma</h3>
            <!-- El formato se controla desde el sidebar (menu.php) -->
            <div class="time-selector">
                <div class="time-input">
                    <input type="number" class="hour-input" id="hourInput" min="<?php echo $formato24===1?'0':'1'; ?>" max="<?php echo $formato24===1?'23':'12'; ?>" placeholder="<?php echo $formato24===1?'00':'12'; ?>" required aria-label="Hora" oninput="if(this.value.length > 2) this.value = this.value.slice(0, 2);">
                    <span class="time-separator">:</span>
                    <input type="number" class="minute-input" id="minuteInput" min="0" max="59" placeholder="00" required aria-label="Minutos (00-59)" oninput="if(this.value.length > 2) this.value = this.value.slice(0, 2);">
                    <div class="ampm-toggle" id="ampmToggle" style="display: <?php echo $formato24===1?'none':'flex'; ?>; gap:6px; margin-left:8px;">
                        <button type="button" class="ampm-btn active" data-period="AM">AM</button>
                        <button type="button" class="ampm-btn" data-period="PM">PM</button>
                    </div>
                </div>
                <div class="time-labels">
                    <span>Hora</span>
                    <span>Minuto</span>
                </div>
            </div>
        </div>

        <?php if (!$moduloExiste || empty($nombreMedicamento)): ?>
        <div class="config-section">
            <h3>Cantidad de pastillas</h3>
            <div class="medication-info">
                <label for="pillCount" class="medication-label">Cantidad de pastillas en el módulo</label>
                <input type="number" id="pillCount" class="medication-input" min="1" max="50" step="1" placeholder="0" aria-label="Cantidad de pastillas" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                <small class="medication-hint">Este valor se mostrará en la configuración del módulo y se irá reduciendo en 1 cada vez que se dispense una pastilla. Máximo: 50 pastillas.</small>
            </div>
        </div>
        <?php endif; ?>

        <div class="config-section">
            <h3>Días de la semana</h3>
            <div class="days-selector">
                <button type="button" class="day-btn" data-day="L">L</button>
                <button type="button" class="day-btn" data-day="M">M</button>
                <button type="button" class="day-btn" data-day="X">X</button>
                <button type="button" class="day-btn" data-day="J">J</button>
                <button type="button" class="day-btn" data-day="V">V</button>
                <button type="button" class="day-btn" data-day="S">S</button>
                <button type="button" class="day-btn" data-day="D">D</button>
            </div>
        </div>

        <!-- Mensajes de estado -->
        <div id="statusMessage" class="alert" style="display:none;"></div>

        <div class="config-section">
            <button type="submit" class="config-submit-btn" onclick="saveConfiguration()" id="submitBtn">Crear Alarma</button>
            <a href="<?php echo $pacienteIdOriginal ? 'module-config.php?paciente_id=' . urlencode($pacienteIdOriginal) . '&modulo=' . $moduleId : 'module-config.php?modulo=' . $moduleId; ?>" class="back-btn">← Volver</a>
        </div>
    </main>

    <script>
    // toggleMobileMenu y overlay provienen del parcial compartido

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

            // Cierre fuera manejado por el parcial

            // Manejo de días de la semana
            const dayButtons = document.querySelectorAll('.day-btn');
            dayButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.classList.toggle('selected');
                });
            });

            // Manejo de AM/PM (si existen)
            const ampmButtons = document.querySelectorAll('.ampm-btn');
            if (ampmButtons && ampmButtons.length) {
                ampmButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        ampmButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                    });
                });
            }

            // Validación de inputs de hora
            const hourInput = document.getElementById('hourInput');
            const minuteInput = document.getElementById('minuteInput');

            hourInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                const min = parseInt(this.getAttribute('min')) || 0;
                const max = parseInt(this.getAttribute('max')) || 23;
                if (isNaN(value)) value = min;
                if (value < min) this.value = min;
                if (value > max) this.value = max;
            });

            minuteInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (value < 0) this.value = 0;
                if (value > 59) this.value = 59;
            });
        });

    // Sincronización de formato 12/24h desde el toggle del sidebar (localStorage)
        (function() {
            const hourInput = document.getElementById('hourInput');
            const minuteInput = document.getElementById('minuteInput');
            const ampmToggle = document.getElementById('ampmToggle');

            function applyTimeFormat(is24) {
                // Ajustar min/max/placeholder
                hourInput.min = is24 ? '0' : '1';
                hourInput.max = is24 ? '23' : '12';
                hourInput.placeholder = is24 ? '00' : '12';

                // Convertir valor si es posible
                const currentHour = parseInt(hourInput.value);
                
                // Solo convertir si hay un valor, dejar vacío si no hay nada
                if (!isNaN(currentHour)) {
                    let h = currentHour;

                    if (is24) {
                        // Convertir de 12h -> 24h solo si h está en 1..12
                        if (h >= 1 && h <= 12) {
                            let period = document.querySelector('.ampm-btn.active')?.dataset.period || 'AM';
                            if (period === 'PM' && h !== 12) h = h + 12;
                            if (period === 'AM' && h === 12) h = 0;
                        }
                        ampmToggle.style.display = 'none';
                        // Limpiar selección AM/PM para evitar que se envíe por error
                        document.querySelectorAll('.ampm-btn').forEach(btn => btn.classList.remove('active'));
                    } else {
                        // De 24h -> 12h
                        let period = 'AM';
                        if (h === 0) { h = 12; period = 'AM'; }
                        else if (h === 12) { h = 12; period = 'PM'; }
                        else if (h > 12) { h = h - 12; period = 'PM'; }
                        else { period = 'AM'; }
                        ampmToggle.style.display = 'flex';
                        // Activar botón periodo
                        document.querySelectorAll('.ampm-btn').forEach(btn => btn.classList.remove('active'));
                        const toActivate = document.querySelector(`.ampm-btn[data-period="${period}"]`);
                        toActivate && toActivate.classList.add('active');
                    }
                    hourInput.value = h;
                } else {
                    // Si no hay valor, solo actualizar la visualización del toggle AM/PM
                    if (is24) {
                        ampmToggle.style.display = 'none';
                        document.querySelectorAll('.ampm-btn').forEach(btn => btn.classList.remove('active'));
                    } else {
                        ampmToggle.style.display = 'flex';
                    }
                }
            }

            // Inicial desde localStorage si existe
            const ls = localStorage.getItem('formatoHora24');
            if (ls === '0' || ls === '1') {
                applyTimeFormat(ls === '1');
            } else {
                // Publicar el estado actual para sincronizar
                localStorage.setItem('formatoHora24', '<?php echo $formato24 ? '1' : '0'; ?>');
            }

            // Reaccionar a cambios desde otra pestaña
            window.addEventListener('storage', (e) => {
                if (e.key === 'formatoHora24') {
                    applyTimeFormat(e.newValue === '1');
                }
            });

            // Reaccionar inmediatamente al cambio en esta misma pestaña (sidebar)
            window.addEventListener('formatoHora24Changed', (e) => {
                if (e && e.detail && typeof e.detail.is24 === 'boolean') {
                    applyTimeFormat(e.detail.is24);
                }
            });
        })();

        // Manejo del formulario - FUNCIÓN GLOBAL
        function saveConfiguration() {
            const nombreMedicamento = document.getElementById('nombreMedicamento');
            const hourInput = document.getElementById('hourInput').value;
            const minuteInput = document.getElementById('minuteInput').value;
            const periodBtn = document.querySelector('.ampm-btn.active');
            const is24 = (localStorage.getItem('formatoHora24') === '1');
            const selectedPeriod = (!is24 && periodBtn) ? periodBtn.dataset.period : null;
            const selectedDays = Array.from(document.querySelectorAll('.day-btn.selected')).map(btn => btn.dataset.day);
            const pillCountInput = document.getElementById('pillCount');
            const pillCountVal = pillCountInput && pillCountInput.value !== '' ? pillCountInput.value.trim() : '';

            // Validar el nombre del medicamento solo si el campo existe (primera alarma del módulo)
            if (nombreMedicamento && !nombreMedicamento.value.trim()) {
                showMessage('El nombre del medicamento es obligatorio', 'error');
                nombreMedicamento.focus(); // Enfocar el campo para facilitar la corrección
                return;
            }

            // Validar cantidad de pastillas SOLO si el campo existe (primera alarma del módulo)
            if (pillCountInput) {
                if (pillCountVal === '' || pillCountVal === null) {
                    showMessage('Debe ingresar la cantidad de pastillas', 'error');
                    pillCountInput.focus();
                    return;
                }

                // Validar que la cantidad de pastillas solo contenga números y esté en el rango válido (1-50)
                const pillCountNumber = parseInt(pillCountVal);
                if (!/^\d+$/.test(pillCountVal) || pillCountNumber < 1 || pillCountNumber > 50) {
                    showMessage('La cantidad de pastillas debe ser un número entre 1 y 50', 'error');
                    pillCountInput.focus();
                    return;
                }
            }

            if (!hourInput || !minuteInput || selectedDays.length === 0) {
                showMessage('Por favor completa todos los campos', 'error');
                return;
            }

            // Deshabilitar botón y mostrar estado
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando...';
            showMessage('Creando alarma...', 'info');

            // Crear FormData para enviar al servidor
            const formData = new FormData();
            // Solo enviar nombre del medicamento si es la primera alarma del módulo
            if (nombreMedicamento) {
                formData.append('nombre_medicamento', nombreMedicamento.value.trim());
            }
            formData.append('hour', hourInput);
            formData.append('minute', minuteInput);
            if (selectedPeriod) { formData.append('period', selectedPeriod); }
            formData.append('days', JSON.stringify(selectedDays));
            formData.append('modulo', <?php echo $moduleId; ?>);
            if (pillCountVal !== '') { formData.append('cantidad_pastillas_modulo', pillCountVal); }
            <?php if ($pacienteId): ?>
            // Solo enviamos paciente_id si se validó el permiso
            formData.append('paciente_id', '<?php echo $pacienteId; ?>');
            <?php endif; ?>

            // Enviar petición al servidor
            fetch('guardar_alarma.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: formData
            })
            .then(async (response) => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Respuesta no válida del servidor: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                if (data.success) {
                    showMessage('Alarma creada exitosamente', 'success');
                    const target = (data.redirect && typeof data.redirect === 'string') ? data.redirect : (
                        <?php if ($pacienteId): ?>
                        'module-config.php?paciente_id=<?php echo urlencode($pacienteId); ?>&modulo=<?php echo $moduleId; ?>'
                        <?php else: ?>
                        'module-config.php?modulo=<?php echo $moduleId; ?>'
                        <?php endif; ?>
                    );
                    setTimeout(() => { window.location.href = target; }, 800);
                } else {
                    showMessage('Error: ' + (data.error || 'Error desconocido'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error al crear la alarma: ' + (error && error.message ? error.message : error), 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

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