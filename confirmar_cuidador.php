<?php
require_once 'session_init.php';
require_once 'conexion.php';

// Verificar autenticación
requireAuth();

// Obtener información del usuario
$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();

// Si llega un id por query, filtrar para mostrar solo esa solicitud
$filterId = isset($_GET['id']) ? trim($_GET['id']) : '';

// Obtener solicitudes de cuidadores pendientes
$caregiverRequests = [];
try {
    $userId = $_SESSION['user_id'];
    if ($filterId !== '') {
        $sql = "SELECT c.*, u.nombre_usuario, u.email_usuario 
                FROM cuidadores c 
                INNER JOIN usuarios u ON c.cuidador_id = u.id_usuario 
                WHERE c.paciente_id = ? AND c.estado = 'pendiente' AND c.id = ? 
                ORDER BY c.fecha_creacion DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $userId, $filterId);
    } else {
        $sql = "SELECT c.*, u.nombre_usuario, u.email_usuario 
                FROM cuidadores c 
                INNER JOIN usuarios u ON c.cuidador_id = u.id_usuario 
                WHERE c.paciente_id = ? AND c.estado = 'pendiente' 
                ORDER BY c.fecha_creacion DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $caregiverRequests[] = $row;
    }
    // Fallback: si se filtró por id pero no hay coincidencia, cargar todas las pendientes
    if ($filterId !== '' && empty($caregiverRequests)) {
        $sqlAll = "SELECT c.*, u.nombre_usuario, u.email_usuario 
                FROM cuidadores c 
                INNER JOIN usuarios u ON c.cuidador_id = u.id_usuario 
                WHERE c.paciente_id = ? AND c.estado = 'pendiente' 
                ORDER BY c.fecha_creacion DESC";
        $stmtAll = $conn->prepare($sqlAll);
        $stmtAll->bind_param("s", $userId);
        $stmtAll->execute();
        $resAll = $stmtAll->get_result();
        while ($r = $resAll->fetch_assoc()) { $caregiverRequests[] = $r; }
    }
} catch (Exception $e) {
    // En caso de error, usar datos de ejemplo
    $caregiverRequests = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Cuidador - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        /* ==================== ESTILOS PARA CONFIRMACIÓN DE CUIDADOR ==================== */
        .caregiver-confirmation-page {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .confirmation-container {
            width: 100%;
            max-width: 400px;
            text-align: center;
            padding: 40px 20px;
        }

        .confirmation-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 40px;
            text-align: center;
        }

        .profile-placeholder {
            margin-bottom: 30px;
        }

        .profile-icon {
            width: 60px;
            height: 60px;
            background: transparent;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #333;
            margin: 0 auto;
            border: none;
        }

        .confirmation-message {
            margin-bottom: 40px;
        }

        .confirmation-message .caregiver-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .caregiver-description {
            font-size: 16px;
            color: #666;
            line-height: 1.5;
            text-align: center;
            max-width: 300px;
            margin: 0 auto;
        }

        .confirmation-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .confirmation-actions .btn-accept {
            background: #C154C1;
            color: #fff;
            border: 1px solid #C154C1;
            padding: 16px 40px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
            text-align: center;
        }

        .confirmation-actions .btn-accept:hover {
            background: #a945a9;
            border-color: #a945a9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(193, 84, 193, 0.3);
        }

        .confirmation-actions .btn-cancel-link,
        .confirmation-actions .btn-cancel-link:link,
        .confirmation-actions .btn-cancel-link:visited {
            color: #FAFAFA !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .confirmation-actions .btn-cancel-link:hover {
            color: #FAFAFA !important;
            text-decoration: underline;
        }

        .confirmation-actions .btn-cancel {
            color: #FAFAFA !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            background: transparent;
            border: none;
            transition: color 0.3s ease;
        }

        .confirmation-actions .btn-cancel:hover {
            color: #FAFAFA !important;
            text-decoration: underline;
        }

        /* Responsive para confirmación de cuidador */
        @media (max-width: 480px) {
            .caregiver-confirmation-page {
                padding: 15px;
            }
            
            .confirmation-container {
                padding: 30px 15px;
            }
            
            .confirmation-title {
                font-size: 22px;
                margin-bottom: 30px;
            }
            
            .profile-icon {
                width: 50px;
                height: 50px;
                font-size: 25px;
            }
            
            .confirmation-message .caregiver-name {
                font-size: 18px;
            }
            
            .caregiver-description {
                font-size: 15px;
            }
            
            .confirmation-actions .btn-accept {
                padding: 14px 30px;
                font-size: 16px;
                min-width: 180px;
            }
        }

        /* Modo oscuro para confirmación de cuidador */
        body.dark-mode .caregiver-confirmation-page {
            background: #1a1a1a;
        }

        body.dark-mode .confirmation-title {
            color: #ffffff;
        }

        body.dark-mode .profile-icon {
            background: transparent;
            border-color: transparent;
            color: #ffffff;
        }

        body.dark-mode .confirmation-message .caregiver-name {
            color: #ffffff;
        }

        body.dark-mode .caregiver-description {
            color: #b0b0b0;
        }

        body.dark-mode .confirmation-actions .btn-accept {
            background: #C154C1;
            border-color: #C154C1;
        }

        body.dark-mode .confirmation-actions .btn-accept:hover {
            background: #a945a9;
            border-color: #a945a9;
        }

        body.dark-mode .confirmation-actions .btn-cancel-link,
        body.dark-mode .confirmation-actions .btn-cancel-link:link,
        body.dark-mode .confirmation-actions .btn-cancel-link:visited {
            color: #FAFAFA !important;
        }

        body.dark-mode .confirmation-actions .btn-cancel-link:hover {
            color: #FAFAFA !important;
        }

        body.dark-mode .confirmation-actions .btn-cancel {
            color: #FAFAFA !important;
        }

        body.dark-mode .confirmation-actions .btn-cancel:hover {
            color: #FAFAFA !important;
        }
    </style>
</head>
<body>
    <?php $menuLogoHref = 'dashboard.php'; include __DIR__ . '/partials/menu.php'; ?>

    <main class="caregiver-confirmation-page">
        <div class="confirmation-container">
            <h1 class="confirmation-title">Confirmación de cuidador</h1>
            
            <div class="profile-placeholder">
                <div class="profile-icon"><span class="patient-icon"></span></div>
            </div>
            <?php if (empty($caregiverRequests)): ?>
                <div class="confirmation-message">
                    <h2 class="caregiver-name">No tienes solicitudes pendientes</h2>
                </div>
            <?php else: ?>
                <?php foreach ($caregiverRequests as $request): ?>
                    <div class="confirmation-message">
                        <h2 class="caregiver-name"><?php echo htmlspecialchars($request['nombre_usuario']); ?> quiere ser tu cuidador</h2>
                        <p class="caregiver-description">
                            Esto le permite ayudarte a administrar y configurar tu pastillero, 
                            además de asegurarse que estés tomando tus pastillas a horario
                        </p>
                    </div>
                    <div class="actions-container">
                        <button class="btn-confirm" onclick="confirmarCuidador('<?php echo isset($request['id']) ? htmlspecialchars($request['id']) : ''; ?>', '<?php echo htmlspecialchars($request['nombre_usuario']); ?>')">
                            Aceptar
                        </button>
                        <button class="btn-reject" onclick="rechazarCuidador('<?php echo isset($request['id']) ? htmlspecialchars($request['id']) : ''; ?>', '<?php echo htmlspecialchars($request['nombre_usuario']); ?>')">
                            Rechazar
                        </button>
                        <a href="notifications.php" class="btn-cancel">
                            Cancelar
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        // Función para alternar el menú móvil
        function toggleMobileMenu(event) {
            event.stopPropagation();
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
        }

        // Función para confirmar cuidador
        function confirmarCuidador(requestId, nombreCuidador) {
            console.log('DEBUG confirmarCuidador: requestId=', requestId, 'nombreCuidador=', nombreCuidador);
            if (!requestId || requestId === 'undefined' || requestId === '') {
                alert('Error: El ID de la solicitud es inválido o vacío. No se puede aceptar la solicitud.');
                return;
            }
            
            showConfirmationModal(
                'Aceptar cuidador',
                '¿Estás seguro de que quieres aceptar a ' + nombreCuidador + ' como tu cuidador?',
                function() {
                // Enviar petición al servidor
                fetch('confirmar_cuidador_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'confirmar'
                    })
                })
                .then(async response => {
                    let data;
                    try { data = await response.json(); } catch (e) { data = null; }
                    if (data && data.success) {
                        alert('Cuidador aceptado exitosamente');
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Error: ' + ((data && data.error) || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al aceptar cuidador');
                });
                }
            );
        }

        // Función para rechazar cuidador
        function rechazarCuidador(requestId, nombreCuidador) {
            if (!requestId || requestId === 'undefined' || requestId === '') {
                alert('Error: El ID de la solicitud es inválido o vacío.');
                return;
            }
            
            showConfirmationModal(
                'Rechazar cuidador',
                '¿Quieres rechazar a ' + nombreCuidador + ' como tu cuidador?',
                function() {
                fetch('confirmar_cuidador_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ request_id: requestId, action: 'rechazar' })
                })
                .then(async r => { try { return await r.json(); } catch { return null; } })
                .then(data => {
                    if (data && data.success) {
                        alert('Solicitud rechazada');
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Error: ' + ((data && data.error) || 'No se pudo rechazar'));
                    }
                })
                .catch(() => alert('Error de red al rechazar'));
                }
            );
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
    </script>
</body>
</html> 