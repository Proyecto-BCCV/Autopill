<?php
require_once 'session_init.php';
requireAuth();
$userName = getUserName();

// Obtener el número de módulo desde la URL
$moduleId = isset($_GET['modulo']) ? intval($_GET['modulo']) : 1;

// Validar que el módulo esté entre 1 y 5
if ($moduleId < 1 || $moduleId > 5) {
    $moduleId = 1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Módulo - Autopill</title>
    <script>
        // Aplicar modo oscuro inmediatamente antes de que se renderice la página
        (function() {
            try {
                const darkMode = localStorage.getItem('darkMode');
                if (darkMode === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                }
            } catch(e) {}
        })();
    </script>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
    </style>
</head>
<body>
    <?php $menuLogoHref = 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="module-config">
        <div class="config-section">
            <div class="config-title-row">
                <div class="module-selector">
                    <button id="moduleSelectorToggle" class="module-selector-toggle" aria-haspopup="dialog" aria-expanded="false" title="Seleccionar módulo">▾</button>
                </div>
                <h3 class="config-title">Configurar Módulo <?php echo $moduleId; ?></h3>
            </div>
            <div class="medication-info">
                <input type="text" class="medication-input" id="medicationName" placeholder="Nombre del medicamento" required>
            </div>
        </div>

        <!-- Modal para selección de módulo -->
        <div id="moduleSelectModal" class="modal" style="display:none;">
            <div class="modal-content module-select-modal">
                <div class="modal-header">
                    <h3>Selecciona un módulo</h3>
                    <span id="closeModuleSelect" class="close">×</span>
                </div>
                <div class="modal-body">
                    <div class="module-select-buttons">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i === $moduleId): ?>
                                <!-- Omitimos el módulo actual -->
                            <?php else: ?>
                                <button type="button" class="module-select-button" data-target="configurar-modulo.php?modulo=<?php echo $i; ?>">Módulo <?php echo $i; ?></button>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

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

        <div class="config-section">
            <h3>Hora de toma</h3>
            <div class="time-selector">
                <div class="time-input">
                    <input type="number" class="hour-input" id="hourInput" min="1" max="12" placeholder="12" required>
                    <span class="time-separator">:</span>
                    <input type="number" class="minute-input" id="minuteInput" min="0" max="59" placeholder="00" required>
                    <div class="ampm-toggle">
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

        <div class="config-section">
            <button type="submit" class="config-submit-btn" onclick="saveConfiguration()">Guardar Configuración</button>
            <a href="dashboard.php" class="back-btn">← Volver al Dashboard</a>
        </div>
    </main>

    <script>
    // toggleMobileMenu proviene del parcial compartido

        document.addEventListener('DOMContentLoaded', function() {
            // Verificar y aplicar el modo oscuro INMEDIATAMENTE al body también
            const savedMode = localStorage.getItem('darkMode');
            if (savedMode === 'enabled') {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            } else if (savedMode === 'disabled') {
                // Si explícitamente está deshabilitado, asegurarse de removerlo
                document.documentElement.classList.remove('dark-mode');
                document.body.classList.remove('dark-mode');
            }
            
            // Configurar el toggle del modo oscuro
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                // Establecer el estado inicial del toggle
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

            // Cierre fuera manejado por el parcial

            // Manejo de días de la semana
            const dayButtons = document.querySelectorAll('.day-btn');
            dayButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.classList.toggle('selected');
                });
            });

            // Manejo de AM/PM
            const ampmButtons = document.querySelectorAll('.ampm-btn');
            ampmButtons.forEach(button => {
                button.addEventListener('click', function() {
                    ampmButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Modal de selección de módulo
            const moduleToggle = document.getElementById('moduleSelectorToggle');
            const moduleSelectModal = document.getElementById('moduleSelectModal');
            const moduleSelectClose = document.getElementById('closeModuleSelect');
            if (moduleToggle && moduleSelectModal) {
                moduleToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    moduleSelectModal.style.display = 'flex';
                    moduleToggle.textContent = '▴';
                    moduleToggle.setAttribute('aria-expanded', 'true');
                });
                // Navegar al hacer clic en los botones del modal
                document.querySelectorAll('.module-select-button').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const target = btn.getAttribute('data-target');
                        if (target) window.location.href = target;
                    });
                });
                if (moduleSelectClose) {
                    moduleSelectClose.addEventListener('click', function() {
                        moduleSelectModal.style.display = 'none';
                        moduleToggle.textContent = '▾';
                        moduleToggle.setAttribute('aria-expanded', 'false');
                    });
                }
                // Cerrar al hacer click fuera del contenido
                moduleSelectModal.addEventListener('click', function(e) {
                    if (e.target === moduleSelectModal) {
                        moduleSelectModal.style.display = 'none';
                        moduleToggle.textContent = '▾';
                        moduleToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Validación de inputs de hora
            const hourInput = document.getElementById('hourInput');
            const minuteInput = document.getElementById('minuteInput');

            hourInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (value < 1) this.value = 1;
                if (value > 12) this.value = 12;
            });

            minuteInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (value < 0) this.value = 0;
                if (value > 59) this.value = 59;
            });
        });

        function saveConfiguration() {
            const medicationName = document.getElementById('medicationName').value;
            const hourInput = document.getElementById('hourInput').value;
            const minuteInput = document.getElementById('minuteInput').value;
            const selectedPeriod = document.querySelector('.ampm-btn.active').dataset.period;
            const selectedDays = Array.from(document.querySelectorAll('.day-btn.selected')).map(btn => btn.dataset.day);

            if (!medicationName || !hourInput || !minuteInput || selectedDays.length === 0) {
                alert('Por favor completa todos los campos');
                return;
            }

            // Crear FormData para enviar al servidor
            const formData = new FormData();
            formData.append('medication', medicationName);
            formData.append('hour', hourInput);
            formData.append('minute', minuteInput);
            formData.append('period', selectedPeriod);
            formData.append('days', JSON.stringify(selectedDays));
            formData.append('module_id', <?php echo $moduleId; ?>);

            // Enviar petición al servidor
            fetch('guardar_modulo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Status de respuesta:', response.status);
                console.log('Headers:', response.headers);
                
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Respuesta del servidor (no JSON):', text);
                        throw new Error(`HTTP error! status: ${response.status}, response: ${text.substring(0, 200)}...`);
                    });
                }
                
                return response.text().then(text => {
                    console.log('Respuesta cruda del servidor:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Error al parsear JSON:', e);
                        console.error('Contenido recibido:', text);
                        throw new Error('Respuesta del servidor no es JSON válido. Contenido: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('Datos procesados:', data);
                if (data.success) {
                    alert(data.message);
                    window.location.href = data.redirect;
                } else {
                    console.error('Error del servidor:', data);
                    alert('Error: ' + (data.error || 'Error desconocido'));
                    
                    // Mostrar información de debug si está disponible
                    if (data.debug_info) {
                        console.log('Debug info:', data.debug_info);
                    }
                }
            })
            .catch(error => {
                console.error('Error completo:', error);
                alert('Error: ' + error.message);
            });
        }
    </script>
</body>
</html> 
