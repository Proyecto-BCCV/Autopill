<?php
require_once __DIR__ . '/session_init.php';
requireAuth();

if (isCuidador()) {
    $target = $_SESSION['post_link_redirect'] ?? 'dashboard_cuidador.php';
} else {
    $target = $_SESSION['post_link_redirect'] ?? 'dashboard.php';
}

$usuarioId = getUserId();
$espInfo = obtenerEspAsignado($usuarioId, true);
if ($espInfo) {
    $_SESSION['needs_esp32'] = false;
    $_SESSION['esp_vinculado_once'] = true;
    $destino = $_SESSION['post_link_redirect'] ?? $target;
    unset($_SESSION['post_link_redirect']);
    header('Location: ' . $destino);
    exit;
}

require_once __DIR__ . '/conexion.php';

// Establecer conexión de base de datos
$conn = obtenerConexion();
$pdo = null;

// Verificar preferencias de modo oscuro
$prefDark = 0;
if ($conn) {
    try {
        $stmtPref = $conn->prepare('SELECT modo_oscuro_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1');
        $stmtPref->bind_param('s', $usuarioId);
        $stmtPref->execute();
        $resPref = $stmtPref->get_result();
        if ($rowP = $resPref->fetch_assoc()) {
            $prefDark = (int)($rowP['modo_oscuro_config'] ?? 0);
        }
    } catch (Throwable $e) { 
        error_log('[vincular_esp] Error obteniendo preferencias: ' . $e->getMessage());
    }
}

$error = null;
$codigoIngresado = '';

// NO crear ESPs ficticios - solo trabajar con dispositivos físicos reales
// Los ESPs se registran cuando el dispositivo físico hace su primer heartbeat

// Función para verificar si un ESP está validado físicamente
function verificarEspFisico($conn, $pdo, $codigoNormalizado) {
    $device = null;
    $validado = false;
    $ultimaConexion = null;
    $estado = 'desconocido';
    
    try {
        if (isset($conn) && $conn) {
            // Ruta MySQLi - obtener información del ESP
            $stmt = $conn->prepare('SELECT id_esp, id_usuario, modulos_conectados_esp, nombre_esp, validado_fisicamente, primera_conexion FROM codigos_esp WHERE nombre_esp = ? LIMIT 1');
            $stmt->bind_param('s', $codigoNormalizado);
            $stmt->execute();
            $res = $stmt->get_result();
            $device = $res->fetch_assoc();
            
            if ($device) {
                // Si no está validado físicamente pero existe, validarlo automáticamente
                if (!$device['validado_fisicamente']) {
                    $updateStmt = $conn->prepare('UPDATE codigos_esp SET validado_fisicamente = 1, primera_conexion = COALESCE(primera_conexion, NOW()) WHERE id_esp = ?');
                    $updateStmt->bind_param('i', $device['id_esp']);
                    if ($updateStmt->execute()) {
                        $device['validado_fisicamente'] = 1;
                        if (!$device['primera_conexion']) {
                            $device['primera_conexion'] = date('Y-m-d H:i:s');
                        }
                    }
                }
                
                $validado = (bool)($device['validado_fisicamente'] ?? false);
                $ultimaConexion = $device['primera_conexion'];
                $estado = $validado ? 'conectado' : 'no_validado';
            }
        } elseif ($pdo) {
            // Ruta PDO - obtener información del ESP
            $stmt = $pdo->prepare('SELECT id_esp, id_usuario, modulos_conectados_esp, nombre_esp, validado_fisicamente, primera_conexion FROM codigos_esp WHERE nombre_esp = ? LIMIT 1');
            $stmt->execute([$codigoNormalizado]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device) {
                // Si no está validado físicamente pero existe, validarlo automáticamente
                if (!$device['validado_fisicamente']) {
                    $updateStmt = $pdo->prepare('UPDATE codigos_esp SET validado_fisicamente = 1, primera_conexion = COALESCE(primera_conexion, NOW()) WHERE id_esp = ?');
                    $updateStmt->execute([$device['id_esp']]);
                    $device['validado_fisicamente'] = 1;
                    if (!$device['primera_conexion']) {
                        $device['primera_conexion'] = date('Y-m-d H:i:s');
                    }
                }
                
                $validado = (bool)($device['validado_fisicamente'] ?? false);
                $ultimaConexion = $device['primera_conexion'];
                $estado = $validado ? 'conectado' : 'no_validado';
            }
        }
    } catch (Throwable $e) {
        error_log('[vincular_esp] Error verificación física: ' . $e->getMessage());
        // En caso de error, permitir la vinculación si el device existe
        if ($device) {
            $validado = true; // Permitir vinculación en caso de error de base de datos
            $estado = 'error_verificacion';
        }
    }
    
    return [
        'device' => $device,
        'validado_fisicamente' => $validado,
        'estado' => $estado,
        'ultima_conexion' => $ultimaConexion
    ];
}

function normalizarCodigoEsp($codigo)
{
    $codigo = strtoupper(trim($codigo));
    $codigo = str_replace([' ', '-'], '_', $codigo);
    if ($codigo === '') {
        return null;
    }
    if (preg_match('/^ESP32_[A-Z0-9_]+$/', $codigo)) {
        return $codigo;
    }
    if (preg_match('/^ESP32[A-Z0-9_]+$/', $codigo)) {
        return 'ESP32_' . substr($codigo, 5);
    }
    if (preg_match('/^\d{1,6}$/', $codigo)) {
        return 'ESP32_' . str_pad($codigo, 3, '0', STR_PAD_LEFT);
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoIngresado = trim($_POST['codigo_esp'] ?? '');
    $codigoNormalizado = normalizarCodigoEsp($codigoIngresado);

    if (!$codigoNormalizado) {
        $error = 'El código ingresado no tiene un formato válido. Usa por ejemplo "001" o "ESP32_001".';
    } elseif (!$conn) {
        $error = 'Servicio temporalmente no disponible. No se pudo acceder a la base de datos. Inténtalo más tarde.';
    } else {
        try {
            // 1. Buscar el ESP en la base de datos
            $stmt = $conn->prepare('SELECT id_esp, id_usuario, modulos_conectados_esp, nombre_esp, validado_fisicamente, primera_conexion FROM codigos_esp WHERE nombre_esp = ? LIMIT 1');
            $stmt->bind_param('s', $codigoNormalizado);
            $stmt->execute();
            $result = $stmt->get_result();
            $esp = $result->fetch_assoc();
            
            if (!$esp) {
                $error = 'El código ESP "' . htmlspecialchars($codigoIngresado) . '" no existe en el sistema. Contacta al administrador para registrar este dispositivo.';
            } else {
                // 2. Validar físicamente el ESP si no lo está
                if (!$esp['validado_fisicamente']) {
                    $validarStmt = $conn->prepare('UPDATE codigos_esp SET validado_fisicamente = 1, primera_conexion = COALESCE(primera_conexion, NOW()) WHERE id_esp = ?');
                    $validarStmt->bind_param('i', $esp['id_esp']);
                    if ($validarStmt->execute()) {
                        $esp['validado_fisicamente'] = 1;
                        if (!$esp['primera_conexion']) {
                            $esp['primera_conexion'] = date('Y-m-d H:i:s');
                        }
                    }
                }
                
                // 3. Verificar el estado de vinculación actual
                if ($esp['id_usuario'] && $esp['id_usuario'] !== '0' && $esp['id_usuario'] !== '') {
                    if ($esp['id_usuario'] === $usuarioId) {
                        // Ya está vinculado a este usuario - permitir acceso
                        $vinculacionValida = true;
                    } else {
                        // Vinculado a otro usuario - obtener información del otro usuario
                        $otroUsuarioStmt = $conn->prepare('SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?');
                        $otroUsuarioStmt->bind_param('s', $esp['id_usuario']);
                        $otroUsuarioStmt->execute();
                        $otroUsuarioResult = $otroUsuarioStmt->get_result();
                        $otroUsuario = $otroUsuarioResult->fetch_assoc();
                        
                        $nombreOtroUsuario = $otroUsuario ? $otroUsuario['nombre_usuario'] : 'Usuario desconocido';
                        $error = 'Este dispositivo ya está vinculado a otro usuario (' . htmlspecialchars($nombreOtroUsuario) . '). Contacta al administrador si necesitas desvincular el dispositivo.';
                    }
                } else {
                    // ESP disponible para vinculación
                    $vincularStmt = $conn->prepare('UPDATE codigos_esp SET id_usuario = ? WHERE id_esp = ? AND (id_usuario IS NULL OR id_usuario = "" OR id_usuario = "0")');
                    $vincularStmt->bind_param('si', $usuarioId, $esp['id_esp']);
                    
                    if ($vincularStmt->execute()) {
                        if ($vincularStmt->affected_rows > 0) {
                            $esp['id_usuario'] = $usuarioId;
                            $vinculacionValida = true;
                        } else {
                            $error = 'No se pudo vincular el dispositivo. Es posible que haya sido asignado a otro usuario en este momento.';
                        }
                    } else {
                        $error = 'Error al vincular el dispositivo: ' . $conn->error;
                    }
                }
                
                // 4. Si la vinculación es válida, configurar sesión y redirigir
                if (isset($vinculacionValida) && $vinculacionValida && !$error) {
                    // Configurar información de sesión
                    $_SESSION['needs_esp32'] = false;
                    $_SESSION['esp_vinculado_once'] = true;
                    $_SESSION['esp_info'] = $esp;
                    $_SESSION['esp_codigo'] = $esp['nombre_esp'];
                    
                    // Redirigir al destino apropiado
                    $destino = $_SESSION['post_link_redirect'] ?? $target;
                    unset($_SESSION['post_link_redirect']);
                    
                    header('Location: ' . $destino);
                    exit;
                }
            }
            
        } catch (Exception $e) {
            $error = 'Ocurrió un error inesperado al procesar la vinculación.';
        }
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular Pastillero - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
      /* Estilos específicos para la página de vinculación */
      .dashboard-container {
        padding-top: 20px;
      }
      
      .vincular-section {
        max-width: 600px;
        margin: 0 auto;
        padding: 0 20px;
      }
      
      .vincular-card {
        background: var(--element-bg);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 40px;
        box-shadow: var(--sombra);
        margin-bottom: 30px;
      }
      
      .vincular-card h1 {
        color: var(--color-principal);
        font-size: 28px;
        margin: 0 0 20px;
        text-align: center;
      }
      
      .vincular-card p {
        color: var(--text-color);
        line-height: 1.6;
        margin: 0 0 20px;
        text-align: center;
      }
      
      /* Los estilos de form-group, input y error ya están definidos en styles.css */
      
      .tips {
        background: linear-gradient(135deg, #fdf4ff, #fae8ff);
        border: 1px solid rgba(193, 84, 193, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
        color: var(--text-color);
        font-size: 14px;
      }
      
      .tips code {
        background: rgba(193, 84, 193, 0.1);
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
        color: var(--color-principal);
        font-weight: 500;
      }
      
      .tips ol {
        margin: 12px 0;
        padding-left: 24px;
        line-height: 1.6;
      }
      
      /* Modo oscuro para .tips */
      body.dark-mode .tips {
        background: linear-gradient(135deg, rgba(193, 84, 193, 0.1), rgba(147, 51, 234, 0.1));
        border-color: rgba(193, 84, 193, 0.3);
        color: var(--text-color);
      }
      
      body.dark-mode .tips code {
        background: rgba(193, 84, 193, 0.2);
        color: #e879f9;
      }
      
      /* Estilos específicos para el input del código ESP */
      .form-group input[type="text"] {
        width: 100%;
        padding: 16px 20px;
        border: 2px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        font-size: 16px;
        font-weight: 500;
        background: var(--element-bg, #ffffff);
        color: var(--text-color, #1f2937);
        box-sizing: border-box;
        transition: all 0.3s ease;
        text-align: center;
        letter-spacing: 1px;
        text-transform: uppercase;
      }
      
      .form-group input[type="text"]:focus {
        outline: none;
        border-color: var(--color-principal, #c154c1);
        box-shadow: 0 0 0 4px rgba(193, 84, 193, 0.15);
        transform: translateY(-1px);
      }
      
      .form-group input[type="text"]::placeholder {
        color: var(--text-color-light, #9ca3af);
        text-transform: none;
        letter-spacing: normal;
      }
      
      /* Modo oscuro para el input */
      body.dark-mode .form-group input[type="text"] {
        background: var(--element-bg, #2d3748);
        border-color: var(--border-color, #4a5568);
        color: var(--text-color, #f7fafc);
      }
      
      body.dark-mode .form-group input[type="text"]:focus {
        border-color: var(--color-principal, #e879f9);
        box-shadow: 0 0 0 4px rgba(232, 121, 249, 0.15);
      }
      
      /* Estilos para centrar el formulario y el botón */
      .vincular-card form {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
      }
      
      .vincular-card .form-group {
        width: 100%;
        max-width: 400px;
      }
      
      .vincular-card .btn-primary {
        width: auto;
        min-width: 280px;
        max-width: 400px;
        margin-top: 20px;
        display: block;
        margin-left: auto;
        margin-right: auto;
      }
      
      /* Los estilos de btn-primary ya están definidos en styles.css */
      
      .security-note {
        background: linear-gradient(135deg, var(--element-bg-secondary, #f8fafc), var(--element-bg, #ffffff));
        border: 1px solid var(--border-color, #e5e7eb);
        border-left: 4px solid var(--color-principal, #c154c1);
        padding: 20px;
        border-radius: 12px;
        font-size: 14px;
        color: var(--text-color, #374151);
        margin-top: 25px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      }
      
      /* Modo oscuro para la nota de seguridad */
      body.dark-mode .security-note {
        background: linear-gradient(135deg, var(--element-bg-secondary, #2d3748), var(--element-bg, #1a202c));
        border-color: var(--border-color, #4a5568);
        border-left-color: var(--color-principal, #e879f9);
        color: var(--text-color, #e2e8f0);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="dashboard-container">
        <div class="vincular-section">
            <div class="vincular-card">
        <h1>🔗 Vincular Pastillero</h1>
        <p>Para comenzar a usar tu pastillero inteligente, necesitas vincularlo a tu cuenta ingresando el código único del dispositivo.</p>
        <div class="tips">
            <p><strong>� Pasos para vincular:</strong></p>
            <ol style="margin: 12px 0; padding-left: 24px; font-size: 14px; line-height: 1.6;">
                <li>Ingresa el código de tu pastillero (ej: <code>001</code>, <code>002</code>)</li>
                <li>El sistema verificará que el dispositivo esté disponible</li>
                <li>Se vinculará permanentemente a tu cuenta</li>
                <li>Podrás acceder al panel de control para configurar alarmas</li>
            </ol>
            <p><strong>ℹ️ Formatos válidos:</strong> <code>001</code> • <code>ESP32-001</code> • <code>ESP32_001</code></p>
        </div>
        <form method="POST" novalidate>
            <div class="form-group">
                <label for="codigo_esp">Código del Pastillero</label>
                <input type="text" id="codigo_esp" name="codigo_esp" value="<?php echo htmlspecialchars($codigoIngresado, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ingresa el código (ej: 001)" autocomplete="off" required>
                <?php if ($error): ?><div class="alert-error"><strong>❌ Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <button type="submit" class="btn-primary">🔗 Vincular Mi Pastillero</button>
        </form>
        <div class="security-note">
          <strong>🔒 Vinculación Segura:</strong> Una vez vinculado, solo tú podrás acceder a este pastillero desde tu cuenta.
        </div>
      </div>
    </main>
    <script src="script.js"></script>
    <script>
        // Aplicar focus automático al campo de código
        document.addEventListener('DOMContentLoaded', function() {
            const codigoInput = document.getElementById('codigo_esp');
            if (codigoInput && !codigoInput.value) {
                codigoInput.focus();
            }
        });
    </script>
</body>
</html>
