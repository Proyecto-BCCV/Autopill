<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';

// Verificar autenticación
requireAuth();

// Obtener información del usuario
$userName = getUserName();
$userEmail = getUserEmail();
$userRole = getUserRole();

// Salvaguarda: si hay solicitudes pendientes sin notificaciones, reconstruirlas
try {
    $userIdCheck = $_SESSION['user_id'] ?? null;
    if ($userIdCheck && $conn) {
        $q1 = $conn->prepare("SELECT COUNT(*) AS cnt FROM cuidadores WHERE paciente_id = ? AND estado = 'pendiente'");
        $q1->bind_param('s', $userIdCheck);
        $q1->execute();
        $c1 = ($q1->get_result()->fetch_assoc()['cnt'] ?? 0);

        $q2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM notificaciones WHERE id_usuario_destinatario = ? AND tipo_notificacion = 'solicitud_cuidado'");
        $q2->bind_param('s', $userIdCheck);
        $q2->execute();
        $c2 = ($q2->get_result()->fetch_assoc()['cnt'] ?? 0);

        if (intval($c1) > 0 && intval($c2) === 0) {
            // Reconstruir para generar una por solicitud
            rebuildCaregiverRequestNotifications($userIdCheck);
        }
    }
} catch (Throwable $e) {
    // Ignorar errores silenciosamente para no romper la página
}
// Traer TODAS las notificaciones sin categorías
$allNotifications = [];
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
    
    // Traer todas las notificaciones del usuario, ordenadas por fecha
    $sql = "SELECT * FROM notificaciones WHERE id_usuario_destinatario = ? ORDER BY fecha_creacion DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allNotifications[] = $row;
    }
} catch (Exception $e) {
    $allNotifications = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
    <style>
        /* Estilos específicos para notificaciones */
        .notifications-page {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            min-height: 100vh;
        }

        .notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 10px;
        }

        .notifications-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .notifications-group {
            background: var(--element-bg, #ffffff);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .notifications-group-header {
            padding: 10px 12px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: var(--element-bg, #ffffff);
        }

        .notification-block {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: #f8f9fa; /* leído = gris claro */
            border: none;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            text-align: left;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .notification-block:hover {
            background: #e9ecef;
        }

        .notification-block:active {
            background: #dee2e6;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            margin-left: 8px;
            flex-shrink: 0;
        }

        .notification-icon img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        /* Mostrar/ocultar iconos según el modo */
        .notification-icon img.icon-light-theme {
            display: block !important;
        }

        .notification-icon img.icon-dark-theme {
            display: none !important;
        }

        body.dark-mode .notification-icon img.icon-light-theme {
            display: none !important;
        }

        body.dark-mode .notification-icon img.icon-dark-theme {
            display: block !important;
        }

        .notification-icon.request-icon {
            background-image: url('/icons/lightmode/notification.png');
            background-size: 24px 24px;
            background-repeat: no-repeat;
            background-position: center;
        }

        .notification-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .notification-primary {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            line-height: 1.3;
        }

        .notification-secondary {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.3;
        }

        .notification-time {
            font-size: 12px;
            color: #adb5bd;
            text-align: right;
            min-width: 60px;
            margin-left: 16px;
        }

        /* Separador entre notificaciones */
        .notification-block:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        /* Estado no leído */
        .notification-block.unread {
            background: #ffffff; /* no leído = blanco */
            box-shadow: 0 2px 8px rgba(193, 84, 193, 0.2);
        }

        .notification-block.unread:hover {
            background: #f8f9fa;
        }

        .notification-block.unread .notification-primary {
            color: #1a1a1a;
            font-weight: 700;
        }

        /* Estilos para checkboxes y botones de selección */
        .notification-item-wrapper {
            display: flex;
            align-items: center;
            gap: 0;
            position: relative;
        }
        
        .notification-item-wrapper .notification-block {
            flex: 1;
            margin: 0;
        }
        
        .notification-item-wrapper:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .notif-checkbox {
            width: 20px !important;
            height: 20px !important;
            cursor: pointer !important;
            flex-shrink: 0 !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            border: 2px solid #d1d5db !important;
            border-radius: 4px !important;
            background: white !important;
            position: relative !important;
            transition: all 0.2s !important;
            margin: 0 0 0 16px !important;
        }

        .notif-checkbox:hover {
            border-color: #C154C1 !important;
        }

        .notif-checkbox:checked {
            background: #C154C1 !important;
            border-color: #C154C1 !important;
        }

        .notif-checkbox:checked::after {
            content: '✓' !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            color: white !important;
            font-size: 14px !important;
            font-weight: bold !important;
        }

        /* Dark mode para checkboxes */
        body.dark-mode .notif-checkbox {
            background: #2d3748 !important;
            border-color: #4a5568 !important;
        }

        body.dark-mode .notif-checkbox:hover {
            border-color: #C154C1 !important;
        }

        body.dark-mode .notif-checkbox:checked {
            background: #C154C1 !important;
            border-color: #C154C1 !important;
        }

        .notification-item-wrapper .notification-block {
            flex: 1;
        }

        .btn-select-all, .btn-delete-selected, .btn-delete-all {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-select-all {
            background: #6c757d;
            color: white;
        }

        .btn-select-all:hover {
            background: #5a6268;
        }

        .btn-delete-selected {
            background: #dc3545;
            color: white;
        }

        .btn-delete-selected:hover {
            background: #c82333;
        }

        .btn-delete-all {
            background: #ff4444;
            color: white;
        }

        .btn-delete-all:hover {
            background: #cc0000;
        }

        .btn-delete-all-bottom {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
            background: #ff4444;
            color: white;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
            width: 100%;
            max-width: 400px;
        }

        .btn-delete-all-bottom:hover {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btn-delete-all-bottom:active {
            transform: translateY(0);
        }

        /* Dark mode para el botón eliminar todas */
        body.dark-mode .btn-delete-all-bottom {
            background: #ff4444;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.5);
        }

        body.dark-mode .btn-delete-all-bottom:hover {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.6);
        }

        .btn-delete-selected-bottom {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
            background: #ff4444;
            color: white;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
            width: 100%;
            max-width: 400px;
            opacity: 0.5;
        }

        .btn-delete-selected-bottom:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .btn-delete-selected-bottom.active {
            opacity: 1;
            cursor: pointer;
        }

        .btn-delete-selected-bottom.active:hover {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btn-delete-selected-bottom.active:active {
            transform: translateY(0);
        }

        /* Dark mode para el botón eliminar seleccionadas */
        body.dark-mode .btn-delete-selected-bottom {
            background: #ff4444;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.5);
        }

        body.dark-mode .btn-delete-selected-bottom.active:hover {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.6);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .notifications-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .notifications-header > div {
                width: 100%;
                justify-content: space-between;
            }

            .btn-select-all, .btn-delete-selected, .btn-delete-all {
                font-size: 13px;
                padding: 7px 12px;
            }
        }

        @media (max-width: 480px) {
            .notifications-page {
                padding: 15px;
            }

            .btn-select-all, .btn-delete-selected, .btn-delete-all {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .notification-block {
                padding: 14px 16px;
            }
            
            .notification-icon {
                width: 36px;
                height: 36px;
                font-size: 18px;
                margin-right: 12px;
            }
            
            .notification-primary {
                font-size: 15px;
            }
            
            .notification-secondary {
                font-size: 13px;
            }
        }

        /* Modo oscuro */
        .dark-mode .notifications-page {
            background: #1a1a1a;
        }

        .dark-mode .notifications-title {
            color: #ffffff;
        }

        .dark-mode .notifications-group {
            background: #2d2d2d;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .dark-mode .notifications-group-header {
            background: #2d2d2d;
            color: #888;
        }

        .dark-mode .notification-block {
            background: #2d2d2d;
            color: #e0e0e0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .dark-mode .notification-block:hover {
            background: #3a3a3a;
        }

        .dark-mode .notification-block:active {
            background: #404040;
        }

        .dark-mode .notification-icon.request-icon {
            background-image: url('/icons/darkmode/notification.png');
        }

        .dark-mode .notification-primary {
            color: #ffffff;
        }

        .dark-mode .notification-secondary {
            color: #b0b0b0;
        }

        .dark-mode .notification-time {
            color: #888888;
        }

        .dark-mode .notification-block.unread {
            background: #1e3a5f;
            box-shadow: 0 2px 8px rgba(193, 84, 193, 0.3);
        }

        .dark-mode .notification-block.unread:hover {
            background: #2a4a7a;
        }

        .dark-mode .notification-block.unread .notification-primary {
            color: #ffffff;
            font-weight: 700;
        }

        .dark-mode .notification-item-wrapper:not(:last-child)::after {
            background: #404040;
        }

        /* Dark mode - Modal de detalle de notificación */
        .dark-mode #notifModal > div {
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
        }

        .dark-mode #notifModal .close-btn {
            color: #e0e0e0 !important;
        }

        .dark-mode #notifModalTitle {
            color: #ffffff !important;
        }

        .dark-mode #notifModalBody {
            color: #e0e0e0 !important;
        }

        /* Dark mode - Botón de eliminar */
        .dark-mode #deleteNotifBtn {
            background: #dc3545 !important;
            color: #fff !important;
        }

        .dark-mode #deleteNotifBtn:hover {
            background: #c82333 !important;
        }

        /* Hover para el botón de eliminar en modo claro también */
        #deleteNotifBtn:hover {
            background: #c82333;
        }

        /* Modal de detalle de notificación */
        #notifModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        #notifModal > div {
            background: #fff;
            color: #333;
            max-width: 520px;
            width: 92%;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 20px;
            position: relative;
            overflow-y: auto;
        }

        #notifModal .close-btn {
            position: absolute;
            top: 8px;
            right: 10px;
            border: none;
            background: transparent;
            font-size: 20px;
            cursor: pointer;
            color: #333;
        }

        #notifModalTitle {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 1.25rem;
            padding-right: 30px;
        }

        #notifModalBody {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }

        #deleteNotifBtn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }

        /* Responsive para modal de notificación */
        @media (max-width: 768px) {
            #notifModal {
                padding: 16px;
            }

            #notifModal > div {
                width: 95%;
                max-height: 85vh;
                padding: 18px;
            }

            #notifModalTitle {
                font-size: 1.15rem;
            }

            #notifModalBody {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            #notifModal {
                padding: 12px;
            }

            #notifModal > div {
                width: calc(100% - 24px);
                max-height: 90vh;
                padding: 16px;
            }

            #notifModal .close-btn {
                top: 10px;
                right: 10px;
                font-size: 24px;
            }

            #notifModalTitle {
                font-size: 1.1rem;
                padding-right: 35px;
            }

            #notifModalBody {
                font-size: 13px;
            }

            #notifModalBody ul {
                padding-left: 16px;
            }

            .modal-actions {
                margin-top: 20px;
            }

            #deleteNotifBtn {
                width: 100%;
                padding: 12px 16px;
                font-size: 15px;
            }
        }

        @media (max-width: 360px) {
            #notifModal {
                padding: 8px;
            }

            #notifModal > div {
                width: calc(100% - 16px);
                padding: 14px;
            }

            #notifModalTitle {
                font-size: 1rem;
            }

            #notifModalBody {
                font-size: 12px;
            }

            #deleteNotifBtn {
                padding: 10px 14px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php $menuLogoHref = (function_exists('isCuidador') && isCuidador()) ? 'dashboard_cuidador.php' : 'dashboard.php'; ?>
    <?php include __DIR__ . '/partials/menu.php'; ?>

    <main class="notifications-page">
        <div class="notifications-header">
            <h1 class="notifications-title">Notificaciones</h1>
        </div>

    <div class="notifications-list" id="notificationsList">
            <?php if (empty($allNotifications)): ?>
                <div style="padding:20px;color:#666;text-align:center">No tienes notificaciones</div>
            <?php else: ?>
                <div class="notifications-group">
                    <div class="notifications-group-header">Notificaciones</div>
                    <?php foreach ($allNotifications as $notification): ?>
                        <?php 
                            $notifId = $notification['id_notificacion']; 
                            $tipo = $notification['tipo_notificacion'];
                            $det = $notification['detalles_json'] ? json_decode($notification['detalles_json'], true) : [];
                            $requestIdAttr = '';
                            if (is_array($det) && isset($det['request_id'])) { 
                                $requestIdAttr = ' data-request-id="' . htmlspecialchars($det['request_id']) . '"'; 
                            }
                            
                            // Determinar el icono según el tipo
                            $iconPath = '/icons/lightmode/notification.png';
                            $iconPathDark = '/icons/darkmode/notification.png';
                            if ($tipo === 'pastilla_dispensada' || $tipo === 'pastilla_dispensada_paciente') {
                                $iconPath = '/icons/lightmode/notification.png';
                                $iconPathDark = '/icons/darkmode/notification.png';
                            } elseif ($tipo === 'solicitud_cuidado') {
                                $iconPath = '/icons/lightmode/patient-plus.png';
                                $iconPathDark = '/icons/darkmode/patient-plus.png';
                            } elseif ($tipo === 'cambios_dashboard') {
                                $iconPath = '/icons/lightmode/clipboard.png';
                                $iconPathDark = '/icons/darkmode/clipboard.png';
                            }
                            
                            // Determinar el título
                            $titulo = htmlspecialchars($notification['mensaje'] ?? 'Notificación');
                            if ($tipo === 'solicitud_cuidado') {
                                $titulo = 'Solicitud de cuidado';
                            } elseif ($tipo === 'cambios_dashboard') {
                                $titulo = 'Cambios recientes en el dashboard';
                            }
                            
                            // Determinar el subtítulo
                            $subtitulo = '';
                            if ($tipo === 'pastilla_dispensada') {
                                $subtitulo = 'Ya puede tomar su pastilla';
                            } elseif ($tipo === 'cambios_dashboard') {
                                $subtitulo = htmlspecialchars($notification['mensaje'] ?? '');
                            } else {
                                $subtitulo = htmlspecialchars($notification['mensaje'] ?? '');
                            }
                        ?>
                        <div class="notification-item-wrapper <?php echo $notification['leida'] ? '' : 'unread'; ?>">
                            <button class="notification-block" 
                                data-notif-id="<?php echo $notifId; ?>"
                                data-notif-tipo="<?php echo htmlspecialchars($tipo); ?>"<?php echo $requestIdAttr; ?>
                                onclick="handleNotificationClick(this)">
                                <input type="checkbox" class="notif-checkbox" data-notif-id="<?php echo $notifId; ?>" onchange="updateDeleteButton()" onclick="event.stopPropagation()">
                                <div class="notification-icon">
                                    <img src="<?php echo $iconPath; ?>" class="icon-light-theme" alt="Icono">
                                    <img src="<?php echo $iconPathDark; ?>" class="icon-dark-theme" alt="Icono">
                                </div>
                                <div class="notification-content">
                                    <div class="notification-primary"><?php echo $titulo; ?></div>
                                    <?php if ($subtitulo): ?>
                                        <div class="notification-secondary"><?php echo $subtitulo; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-time time-display" data-time="<?php echo date('H:i:s', strtotime($notification['fecha_creacion'])); ?>">
                                    <?php 
                                        $horaNotif = new DateTime($notification['fecha_creacion']);
                                        echo ($formato24 === 1) ? $horaNotif->format('H:i') : $horaNotif->format('g:i A'); 
                                    ?>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Botón Eliminar todas al final -->
        <?php if (!empty($allNotifications)): ?>
        <div style="margin-top: 20px; text-align: center; padding: 0 20px; display: flex; flex-direction: column; gap: 12px; align-items: center;">
            <button class="btn-delete-all-bottom" onclick="deleteAllNotifications()" id="btnDeleteAllBottom">
                Eliminar todas las notificaciones
            </button>
            <button class="btn-delete-selected-bottom" onclick="deleteSelected()" id="btnDeleteSelectedBottom" disabled>
                Eliminar notificaciones seleccionadas
            </button>
        </div>
        <?php endif; ?>
    </main>

        <!-- Modal detalles -->
        <div id="notifModal">
            <div>
                <button class="close-btn" onclick="closeNotifModal()">×</button>
                <h3 id="notifModalTitle">Detalle de cambios</h3>
                <div id="notifModalBody"></div>
                <div class="modal-actions">
                    <button id="deleteNotifBtn" onclick="confirmarEliminarNotificacion()">Eliminar notificación</button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación de eliminación -->
        <div class="confirmation-modal-overlay" id="confirmDeleteNotifModal">
            <div class="confirmation-modal">
                <div class="confirmation-modal-header">Eliminar notificación</div>
                <div class="confirmation-modal-body">¿Estás seguro de que deseas eliminar esta notificación?</div>
                <div class="confirmation-modal-actions">
                    <button class="btn-cancel" onclick="closeConfirmDeleteNotifModal()">Cancelar</button>
                    <button class="btn-confirm" id="confirmDeleteNotifBtn">Eliminar</button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar todas las notificaciones -->
        <div class="confirmation-modal-overlay" id="confirmDeleteAllModal">
            <div class="confirmation-modal">
                <div class="confirmation-modal-header">Eliminar todas las notificaciones</div>
                <div class="confirmation-modal-body">¿Estás seguro de que deseas eliminar TODAS las notificaciones? Esta acción no se puede deshacer.</div>
                <div class="confirmation-modal-actions">
                    <button class="btn-cancel" onclick="closeConfirmDeleteAllModal()">Cancelar</button>
                    <button class="btn-confirm" id="confirmDeleteAllBtn" onclick="confirmDeleteAll()">Eliminar todas</button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar notificaciones seleccionadas -->
        <div class="confirmation-modal-overlay" id="confirmDeleteSelectedModal">
            <div class="confirmation-modal">
                <div class="confirmation-modal-header">Eliminar notificaciones seleccionadas</div>
                <div class="confirmation-modal-body" id="confirmDeleteSelectedBody">¿Estás seguro de que deseas eliminar las notificaciones seleccionadas? Esta acción no se puede deshacer.</div>
                <div class="confirmation-modal-actions">
                    <button class="btn-cancel" onclick="closeConfirmDeleteSelectedModal()">Cancelar</button>
                    <button class="btn-confirm" id="confirmDeleteSelectedBtn" onclick="confirmDeleteSelected()">Eliminar seleccionadas</button>
                </div>
            </div>
        </div>

    <script>
    // toggleMobileMenu y cierre fuera vienen del menú parcial

        // Función para manejar clics en notificaciones
        async function handleNotificationClick(elOrId) {
            const el = (typeof elOrId === 'object') ? elOrId : document.querySelector(`[data-notif-id="${elOrId}"]`);
            const notificationId = el ? el.getAttribute('data-notif-id') : String(elOrId);
            const tipo = (el && el.getAttribute('data-notif-tipo')) || '';
            // Si es solicitud de cuidador, dirigir a la página de confirmación
            if (tipo.toLowerCase() === 'solicitud_cuidado') {
                const rid = el ? (el.getAttribute('data-request-id') || '') : '';
                // Marcar como leída sin eliminar para que no desaparezca al cancelar
                markNotificationAsRead(notificationId);
                if (rid) {
                    window.location.href = 'confirmar_cuidador.php?id=' + encodeURIComponent(rid);
                } else {
                    window.location.href = 'confirmar_cuidador.php';
                }
                return;
            }
            try{
                const r = await fetch('notification_details.php?id=' + encodeURIComponent(notificationId) + '&_ts=' + Date.now());
                const ct = r.headers.get('content-type')||'';
                const txt = await r.text();
                let data = null;
                if (ct.includes('application/json')) data = JSON.parse(txt);
                if (!data || !data.success || !data.data) {
                    mostrarNotifModal({mensaje: 'No hay detalles disponibles para esta notificación.'});
                    markNotificationAsRead(notificationId);
                    return;
                }
                mostrarNotifModal(data.data);
                markNotificationAsRead(notificationId);
            }catch(e){
                mostrarNotifModal({mensaje: 'No hay detalles disponibles para esta notificación.'});
                markNotificationAsRead(notificationId);
            }
        }

        // Variable global para guardar el ID de la notificación actual
        let currentNotificationId = null;

        // Función para corregir rutas de iconos antiguos en mensajes
        function corregirRutasIconos(mensaje) {
            if (!mensaje) return mensaje;
            
            // Mapeo de iconos antiguos a nuevos
            const iconMap = {
                'icons/refresh.png': '/icons/lightmode/refresh.png',
                'icons/incorrect.png': '/icons/lightmode/incorrect.png',
                'icons/new.png': '/icons/lightmode/new.png',
                'icons/edit.png': '/icons/lightmode/edit.png',
                'icons/trash.png': '/icons/lightmode/trash.png',
                'icons/alarm.png': '/icons/lightmode/alarm.png',
                'icons/clipboard.png': '/icons/lightmode/clipboard.png'
            };
            
            let mensajeCorregido = mensaje;
            
            // Reemplazar rutas antiguas con nuevas (modo claro y oscuro)
            Object.keys(iconMap).forEach(oldPath => {
                const iconName = oldPath.split('/').pop().replace('.png', '');
                const newPattern = new RegExp(`src=['"]${oldPath}['"]`, 'g');
                const replacement = `src='/icons/lightmode/${iconName}.png' class='icon-light-theme' style='width:14px;height:14px;vertical-align:middle;margin-right:4px'><img src='/icons/darkmode/${iconName}.png' class='icon-dark-theme' style='width:14px;height:14px;vertical-align:middle;margin-right:4px;display:none'`;
                mensajeCorregido = mensajeCorregido.replace(newPattern, replacement);
            });
            
            return mensajeCorregido;
        }

        function mostrarNotifModal(payload){
            const modal = document.getElementById('notifModal');
            const title = document.getElementById('notifModalTitle');
            const body = document.getElementById('notifModalBody');
            
            // Guardar el ID de la notificación actual
            currentNotificationId = payload.id_notificacion || payload.id || null;
            
            title.textContent = 'Cambios realizados';
            const d = payload.detalles || {};
            const tipo = d.tipo || payload.tipo || payload.tipo_notificacion;
            const det = d.detalles || {};
            const actor = (payload.actor_nombre || d.actor_nombre || det.actor_nombre || payload.mensaje_actor) || 'Alguien';
            const paciente = d.paciente_nombre || det.paciente_nombre || payload.paciente_nombre || '';
            let accion = '';
            // Si es agregada con eventos, renderizar lista de eventos
            if ((payload.tipo === 'cambios_dashboard' || (payload.tipo_notificacion === 'cambios_dashboard')) && Array.isArray(d.eventos)) {
                title.textContent = 'Cambios recientes en el dashboard';
                let html = '';
                const eventos = d.eventos.slice(0, 10); // mostrar hasta 10 recientes
                if (d.resumen) html += `<p>${d.resumen}</p>`;
                if (eventos.length) {
                    html += '<ul style="padding-left:18px;margin:8px 0">' + eventos.map(ev => {
                        let hora = '';
                        if (ev.timestamp) {
                            const date = new Date(ev.timestamp * 1000);
                            const is24 = localStorage.getItem('formatoHora24') === '1';
                            if (is24) {
                                hora = date.toLocaleString('es-ES', { 
                                    year: 'numeric', month: '2-digit', day: '2-digit',
                                    hour: '2-digit', minute: '2-digit', hour12: false 
                                });
                            } else {
                                hora = date.toLocaleString('en-US', { 
                                    year: 'numeric', month: '2-digit', day: '2-digit',
                                    hour: 'numeric', minute: '2-digit', hour12: true 
                                });
                            }
                        }
                        const who = ev.actor_nombre || 'Alguien';
                        const pac = ev.paciente_nombre ? ` para ${ev.paciente_nombre}` : '';
                        let msg = ev.mensaje || 'Cambio realizado';
                        // Corregir rutas de iconos antiguas
                        msg = corregirRutasIconos(msg);
                        return `<li>${msg} — <i>${who}${pac}</i> <span style="color:#888">(${hora})</span></li>`;
                    }).join('') + '</ul>';
                } else {
                    html += '<p>No hay detalles disponibles.</p>';
                }
                body.innerHTML = html;
                modal.style.display = 'flex';
                modal.onclick = (e)=>{ if(e.target===modal) closeNotifModal(); };
                return;
            }
            // Determinar la acción principal
            if (tipo === 'modulo_creado') accion = 'Creación de módulo';
            else if (tipo === 'modulo_modificado') accion = 'Modificación de módulo';
            else if (tipo === 'modulo_eliminado') accion = 'Eliminación de módulo';
            else if (tipo === 'alarma_creada') accion = 'Creación de alarma';
            else if (tipo === 'alarma_modificada') accion = 'Modificación de alarma';
            else if (tipo === 'alarma_eliminada') accion = 'Eliminación de alarma';
            else if (tipo === 'pastilla_dispensada') accion = 'Pastilla dispensada';
            else if (tipo === 'Solicitud confirmada') accion = 'Solicitud aceptada';
            else if (tipo === 'Solicitud rechazada') accion = 'Solicitud rechazada';
            else accion = 'Cambio en el pastillero';

            let html = '';
            html += `<p><b>Acción:</b> ${accion}</p>`;
            html += `<p><b>Realizado por:</b> ${actor}</p>`;
            if (paciente) html += `<p><b>Paciente:</b> ${paciente}</p>`;
            
            // Para pastilla_dispensada, mostrar la hora programada de la alarma en lugar de la hora de notificación
            if (tipo === 'pastilla_dispensada' && payload.hora_alarma) {
                // Formatear hora_alarma (viene como HH:MM:SS)
                const horaFormateada = payload.hora_alarma.substring(0, 5); // Mostrar solo HH:MM
                const fechaActual = new Date().toLocaleDateString('es-AR');
                html += `<p><b>Fecha y hora:</b> ${fechaActual} ${horaFormateada}</p>`;
            } else if (payload.fecha) {
                html += `<p><b>Fecha y hora:</b> ${payload.fecha}</p>`;
            }

            // Detalles específicos y cambios concretos
            let cambios = [];
            if (tipo === 'pastilla_dispensada') {
                const modulo = d.modulo || 'desconocido';
                const nombreMedicamento = d.nombre_medicamento || d.alarma_nombre || 'medicamento';
                cambios.push(`Se dispensó la pastilla del <b>Módulo ${modulo}</b>`);
                cambios.push(`Medicamento: <b>${nombreMedicamento}</b>`);
                cambios.push(`Ya puede tomar su pastilla`);
                title.textContent = 'Se dispensó la pastilla del Módulo ' + modulo;
            } else {
                if (det.modulo) cambios.push(`Módulo afectado: <b>${det.modulo}</b>`);
                if (det.alarma_id) cambios.push(`ID de alarma: <b>${det.alarma_id}</b>`);
                if (det.hora) cambios.push(`Hora programada: <b>${formatearHora(det.hora)}</b>`);
                if (det.dias) cambios.push(`Días: <b>${formatearDias(det.dias)}</b>`);
                if (det.medicamento) cambios.push(`Medicamento: <b>${det.medicamento}</b>`);
                if (tipo === 'modulo_eliminado') cambios.push('Se eliminó un módulo.');
                if (tipo === 'alarma_eliminada') cambios.push('Se eliminó una alarma.');
                if (tipo === 'modulo_modificado' || tipo === 'alarma_modificada') cambios.push('Se modificaron datos existentes.');
                if (tipo === 'modulo_creado' || tipo === 'alarma_creada') cambios.push('Se creó un nuevo elemento.');
            }
            if (cambios.length) {
                html += '<ul style="padding-left:18px;margin:8px 0">' + cambios.map(c=>`<li>${c}</li>`).join('') + '</ul>';
            } else if (payload.mensaje) {
                html += `<p><b>Detalle:</b> ${payload.mensaje}</p>`;
            }
            body.innerHTML = html;
            modal.style.display = 'flex';
            modal.onclick = (e)=>{ if(e.target===modal) closeNotifModal(); };
        }

        function formatearHora(hora){
            if (!hora) return '';
            if (typeof hora === 'string' && hora.length >= 5) {
                // Obtener preferencia del usuario
                const is24 = localStorage.getItem('formatoHora24') === '1';
                const timeStr = hora.substring(0, 5); // HH:MM
                
                if (is24) {
                    return timeStr; // Ya está en formato 24h
                } else {
                    // Convertir a formato 12h
                    const [hours, minutes] = timeStr.split(':').map(Number);
                    const period = hours >= 12 ? 'PM' : 'AM';
                    const displayHours = hours % 12 || 12;
                    return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
                }
            }
            return String(hora);
        }

        function formatearDias(dias){
            const etiquetas = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
            if (Array.isArray(dias)) {
                return dias.length ? dias.join(', ') : '';
            }
            if (typeof dias === 'string') {
                const limpia = dias.trim();
                if (/^[01]{7}$/.test(limpia)) {
                    const activos = limpia.split('').map((val, idx) => val === '1' ? etiquetas[idx] : null).filter(Boolean);
                    return activos.length ? activos.join(', ') : 'Sin días configurados';
                }
                if (limpia.includes(',')) {
                    return limpia.split(',').map(p => p.trim()).filter(Boolean).join(', ');
                }
                if (limpia) return limpia;
            }
            return 'Sin días configurados';
        }

        function closeNotifModal(){
            const modal = document.getElementById('notifModal');
            modal.style.display = 'none';
        }

        // Funciones para eliminar notificación
        function confirmarEliminarNotificacion() {
            if (!currentNotificationId) {
                alert('No se puede eliminar esta notificación');
                return;
            }
            
            // Mostrar modal de confirmación
            const confirmModal = document.getElementById('confirmDeleteNotifModal');
            confirmModal.classList.add('show');
            
            // Configurar el botón de confirmar
            const confirmBtn = document.getElementById('confirmDeleteNotifBtn');
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            newConfirmBtn.addEventListener('click', function() {
                eliminarNotificacion(currentNotificationId);
            });
            
            // Cerrar modal al hacer clic fuera
            confirmModal.addEventListener('click', function(e) {
                if (e.target === confirmModal) {
                    closeConfirmDeleteNotifModal();
                }
            });
        }

        function closeConfirmDeleteNotifModal() {
            const modal = document.getElementById('confirmDeleteNotifModal');
            modal.classList.remove('show');
        }

        function eliminarNotificacion(notificationId) {
            closeConfirmDeleteNotifModal();
            
            fetch('delete_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    // Cerrar el modal de detalles
                    closeNotifModal();
                    
                    // Eliminar la notificación de la lista visualmente
                    const notification = document.querySelector(`[data-notif-id="${notificationId}"]`);
                    if (notification) {
                        notification.remove();
                    }
                    
                    // Actualizar el badge
                    actualizarBadge();
                    
                    // Recargar la página después de un breve delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('Error al eliminar la notificación: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la notificación');
            });
        }

        // Función para marcar notificación como leída
        function markNotificationAsRead(notificationId) {
            const notification = document.querySelector(`[data-notif-id="${notificationId}"]`);
            if (notification) {
                // Marcar como leída pero NO eliminar
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId, delete_after: false })
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.success) {
                        notification.classList.remove('unread');
                        actualizarBadge();
                    }
                })
                .catch(() => {});
            }
        }

        // Borrar notificaciones leídas
        function borrarLeidas(){
            fetch('delete_notifications.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ onlyRead: true })
            })
            .then(r=>r.json()).then(d=>{
                if (d && d.success){
                    // Remover del DOM las que no tienen clase 'unread'
                    document.querySelectorAll('.notification-block:not(.unread)')
                        .forEach(el => el.parentNode && el.parentNode.removeChild(el));
                    actualizarBadge();
                }
            }).catch(()=>{});
        }

        // Actualiza el badge con la cuenta de no leídas
        function actualizarBadge(){
            const badge = document.getElementById('notifBadge');
            const badgeHeader = document.getElementById('notifBadgeHeader');
            fetch('notifications_count.php?_ts=' + Date.now())
                .then(r=>r.json()).then(d=>{
                    const count = d && d.success ? (d.count||0) : 0;
                    
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
                }).catch(()=>{});
        }

        // Funciones para modal de eliminar todas
        function updateDeleteButton() {
            const checkboxes = document.querySelectorAll('.notif-checkbox:checked');
            const btnDeleteSelected = document.getElementById('btnDeleteSelectedBottom');
            
            if (checkboxes.length > 0) {
                btnDeleteSelected.classList.add('active');
                btnDeleteSelected.disabled = false;
            } else {
                btnDeleteSelected.classList.remove('active');
                btnDeleteSelected.disabled = true;
            }
        }

        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.notif-checkbox:checked');
            const notifIds = Array.from(checkboxes).map(cb => cb.getAttribute('data-notif-id'));
            
            if (notifIds.length === 0) {
                alert('No hay notificaciones seleccionadas');
                return;
            }
            
            // Actualizar el mensaje del modal con la cantidad
            const modalBody = document.getElementById('confirmDeleteSelectedBody');
            modalBody.textContent = `¿Estás seguro de que deseas eliminar ${notifIds.length} notificación(es)? Esta acción no se puede deshacer.`;
            
            // Mostrar modal de confirmación
            document.getElementById('confirmDeleteSelectedModal').classList.add('show');
        }

        function closeConfirmDeleteSelectedModal() {
            document.getElementById('confirmDeleteSelectedModal').classList.remove('show');
        }

        function confirmDeleteSelected() {
            const checkboxes = document.querySelectorAll('.notif-checkbox:checked');
            const notifIds = Array.from(checkboxes).map(cb => cb.getAttribute('data-notif-id'));
            
            fetch('delete_notifications.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ ids: notifIds })
            })
            .then(r => r.json())
            .then(d => {
                closeConfirmDeleteSelectedModal();
                if (d && d.success) {
                    // Recargar la página para actualizar la lista
                    window.location.reload();
                } else {
                    alert('Error al eliminar notificaciones: ' + (d.error || 'Error desconocido'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                closeConfirmDeleteSelectedModal();
                alert('Error al eliminar notificaciones');
            });
        }

        function deleteAllNotifications() {
            // Mostrar modal de confirmación
            document.getElementById('confirmDeleteAllModal').classList.add('show');
        }

        function closeConfirmDeleteAllModal() {
            document.getElementById('confirmDeleteAllModal').classList.remove('show');
        }

        function confirmDeleteAll() {
            // Obtener todos los IDs de las checkboxes de notificaciones
            const allCheckboxes = document.querySelectorAll('.notif-checkbox[data-notif-id]');
            const allNotifIds = Array.from(allCheckboxes).map(checkbox => checkbox.getAttribute('data-notif-id'));
            
            if (allNotifIds.length === 0) {
                closeConfirmDeleteAllModal();
                alert('No hay notificaciones para eliminar');
                return;
            }
            
            fetch('delete_notifications.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ ids: allNotifIds })
            })
            .then(r => r.json())
            .then(d => {
                closeConfirmDeleteAllModal();
                if (d && d.success) {
                    // Recargar la página para actualizar la lista
                    window.location.reload();
                } else {
                    alert('Error al eliminar notificaciones: ' + (d.error || 'Error desconocido'));
                }
            })
            .catch(err => {
                closeConfirmDeleteAllModal();
                console.error('Error:', err);
                alert('Error al eliminar notificaciones');
            });
        }

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

            // Cerrar modales al hacer clic fuera
            const confirmDeleteAllModal = document.getElementById('confirmDeleteAllModal');
            if (confirmDeleteAllModal) {
                confirmDeleteAllModal.addEventListener('click', function(e) {
                    if (e.target === confirmDeleteAllModal) {
                        closeConfirmDeleteAllModal();
                    }
                });
            }

            const confirmDeleteSelectedModal = document.getElementById('confirmDeleteSelectedModal');
            if (confirmDeleteSelectedModal) {
                confirmDeleteSelectedModal.addEventListener('click', function(e) {
                    if (e.target === confirmDeleteSelectedModal) {
                        closeConfirmDeleteSelectedModal();
                    }
                });
            }

            // Cierre fuera manejado globalmente por el parcial

            // Inicializar badge en esta página
            actualizarBadge();
        });
    </script>
</body>
</html> 