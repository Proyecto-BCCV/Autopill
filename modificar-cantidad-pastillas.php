<?php
require_once 'session_init.php';
require_once 'conexion.php';
requireAuth();

// Evitar que se impriman errores/logs en pantalla y romper redirecciones
if (function_exists('ob_get_level') && ob_get_level() === 0) { @ob_start(); }
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

$userName = getUserName();
$moduleId = isset($_GET['modulo']) ? max(1, min(5, intval($_GET['modulo']))) : 1;

// Obtener el ID del usuario
$actorId = $_SESSION['user_id'];
$pacienteId = isset($_GET['paciente_id']) ? trim($_GET['paciente_id']) : null;
if ($pacienteId) {
    if (!function_exists('isCuidador') || !isCuidador() || !function_exists('canManagePaciente') || !canManagePaciente($pacienteId)) {
        $pacienteId = null; // sin permiso
    }
}
$userId = $pacienteId ? $pacienteId : $actorId; // el dueño real del módulo

// Procesar el formulario
if (!$conn || !($conn instanceof mysqli)) {
    $error = 'Base de datos no disponible';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_cantidad'])) {
    $nuevaCantidad = trim($_POST['nueva_cantidad']);
    
    // Validar que sea un número válido en el rango permitido (1-50)
    if (!empty($nuevaCantidad) && ctype_digit($nuevaCantidad)) {
        $cantidadInt = intval($nuevaCantidad);
        
        // Validar rango (1-50)
        if ($cantidadInt < 1 || $cantidadInt > 50) {
            $error = "La cantidad debe ser un número entre 1 y 50";
        } else {
            // Verificar si el módulo existe para este usuario
            $checkStmt = $conn->prepare("SELECT id_modulo, cantidad_pastillas_modulo FROM modulos WHERE numero_modulo = ? AND id_usuario = ?");
            $checkStmt->bind_param("is", $moduleId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $moduloActual = $checkResult->fetch_assoc();
                
                // El módulo existe, actualizar la cantidad
                if ($stmt = $conn->prepare("UPDATE modulos SET cantidad_pastillas_modulo = ? WHERE id_modulo = ? AND id_usuario = ?")) {
                    $stmt->bind_param("iis", $cantidadInt, $moduloActual['id_modulo'], $userId);
                    if ($stmt->execute()) {
                        // Verificar que solo se afectó una fila
                        if ($stmt->affected_rows == 1) {
                            // Éxito - redirigir al module-config
                            if ($pacienteId) {
                                if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
                                header("Location: module-config.php?paciente_id=" . urlencode($pacienteId) . "&modulo=" . $moduleId);
                            } else {
                                if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
                                header("Location: module-config.php?modulo=" . $moduleId);
                            }
                            exit;
                        } else {
                            $error = "Error: Se afectaron " . $stmt->affected_rows . " módulos en lugar de 1";
                            error_log("Error en affected_rows: " . $stmt->affected_rows);
                        }
                    } else {
                        $error = "Error al actualizar: " . $stmt->error;
                        error_log("Error SQL: " . $stmt->error);
                    }
                } else {
                    $error = 'Error preparando actualización: ' . ($conn->error ?? 'desconocido');
                }
            } else {
                $error = "El módulo no existe. Por favor, crea una alarma primero.";
            }
        }
    } elseif (!ctype_digit($nuevaCantidad)) {
        $error = "La cantidad debe ser un número válido";
    } else {
        $error = "La cantidad no puede estar vacía";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Cantidad de Pastillas - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        .quantity-display {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-top: 10px;
        }
        
        .quantity-number {
            font-size: 18px;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .quantity-label {
            font-size: 14px;
            color: var(--text-secondary);
        }
    </style>

</head>
<body>
    <?php $menuLogoHref = $pacienteId ? ('dashboard_paciente.php?paciente_id=' . urlencode($pacienteId)) : 'dashboard.php'; include __DIR__ . '/partials/menu.php'; ?>

    <main class="module-config">
        <div class="config-section">
            <div class="config-title-row">
                <a href="<?php echo $pacienteId ? 'module-config.php?paciente_id=' . urlencode($pacienteId) . '&modulo=' . $moduleId : 'module-config.php?modulo=' . $moduleId; ?>" class="back-arrow" style="text-decoration:none; color:var(--text-color); font-size:24px;">←</a>
                <h3 class="config-title">
                    Modificar Cantidad de Pastillas - Módulo <?php echo $moduleId; ?>
                </h3>
            </div>
        </div>

        <div class="config-section">
            <h3>Modificar Cantidad de Pastillas</h3>
            
            <?php if (isset($error)): ?>
                <div style="background: #c62828; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Mostrar la cantidad actual de pastillas -->
            <div class="medication-info">
                <label class="medication-label">Cantidad Actual de Pastillas en el Módulo:</label>
                <?php 
                // Obtener la cantidad actual de pastillas del módulo
                $stmt = $conn->prepare("SELECT cantidad_pastillas_modulo FROM modulos WHERE numero_modulo = ? AND id_usuario = ?");
                $stmt->bind_param("is", $moduleId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $moduloActual = $result->fetch_assoc();
                $cantidadActual = $moduloActual && isset($moduloActual['cantidad_pastillas_modulo']) ? $moduloActual['cantidad_pastillas_modulo'] : 'N/D';
                ?>
                <div class="quantity-display">
                    <strong class="quantity-number"><?php echo htmlspecialchars($cantidadActual); ?></strong>
                </div>
            </div>
            
            <form method="POST" id="pillQuantityForm">
                <div class="medication-info" style="margin-top: 20px;">
                    <label for="nueva_cantidad" class="medication-label">Nueva Cantidad de Pastillas:</label>
                    <input type="number" name="nueva_cantidad" id="nueva_cantidad" required 
                           min="1" max="50" step="1" placeholder="Ingresa la nueva cantidad" class="medication-input"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    <small class="medication-hint">Ingresa un número entero entre 1 y 50. Este valor se mostrará en la configuración del módulo.</small>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="config-submit-btn">Actualizar Cantidad de Pastillas</button>
                </div>
            </form>
        </div>

        <div class="config-section">
            <a href="<?php echo $pacienteId ? 'module-config.php?paciente_id=' . urlencode($pacienteId) . '&modulo=' . $moduleId : 'module-config.php?modulo=' . $moduleId; ?>" class="back-btn">← Volver</a>
        </div>
    </main>

    <script>
        // Función para alternar el menú móvil
        function toggleMobileMenu(event) {
            event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
        }

        // Validación adicional antes de enviar
        document.getElementById('pillQuantityForm').addEventListener('submit', function(e) {
            const nuevaCantidad = document.getElementById('nueva_cantidad').value.trim();
            
            // Validar que solo contenga números y esté en el rango válido (1-50)
            const cantidad = parseInt(nuevaCantidad);
            if (!/^\d+$/.test(nuevaCantidad) || cantidad < 1 || cantidad > 50) {
                e.preventDefault();
                alert('La cantidad debe ser un número entre 1 y 50');
                return false;
            }
            
            return true;
        });

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

            // Configurar el toggle del formato de hora
            const timeFormatToggle = document.getElementById('timeFormatToggle');
            if (timeFormatToggle) {
                const savedTimeFormat = localStorage.getItem('timeFormat');
                timeFormatToggle.checked = (savedTimeFormat === '24hr');
                
                timeFormatToggle.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('timeFormat', '24hr');
                    } else {
                        localStorage.setItem('timeFormat', '12hr');
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
    </script>
</body>
</html>
