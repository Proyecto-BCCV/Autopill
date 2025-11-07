<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'notificaciones_utils.php';

/**
 * Notifica a todos los cuidadores activos de un paciente cuando se modifica algo
 */
function notificarCuidadorCambio($pacienteId, $tipoCambio, $detalles = []) {
    global $conn;
    
    try {
        // Obtener todos los cuidadores activos del paciente
        $sql = "SELECT cuidador_id FROM cuidadores WHERE paciente_id = ? AND estado = 'activo'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $pacienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cuidadoresNotificados = 0;
        
        while ($row = $result->fetch_assoc()) {
            $cuidadorId = $row['cuidador_id'];
            // Crear mensaje y detalles
            $mensaje = generarMensajeNotificacion($tipoCambio, $detalles);
            $payload = [
                'tipo' => 'cambio_paciente',
                'origen' => 'paciente',
                'actor_id' => $pacienteId,
                'actor_nombre' => $detalles['actor_nombre'] ?? null,
                'paciente_id' => $detalles['paciente_id'] ?? $pacienteId,
                'paciente_nombre' => $detalles['paciente_nombre'] ?? null,
                'detalles' => $detalles,
            ];
            // Usar util para agregar a notificación consolidada de dashboard
            crearNotificacion($cuidadorId, $pacienteId, 'cambio_paciente', $mensaje, $payload);
            $cuidadoresNotificados++;
        }
        
        return [
            'success' => true,
            'cuidadores_notificados' => $cuidadoresNotificados,
            'mensaje' => "Se notificó a $cuidadoresNotificados cuidador(es)"
        ];
        
    } catch (Exception $e) {
        error_log("Error al notificar cuidadores: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al notificar cuidadores: ' . $e->getMessage()
        ];
    }
}

/**
 * Genera el mensaje de notificación según el tipo de cambio
 */
function generarMensajeNotificacion($tipoCambio, $detalles) {
    $pacienteNombre = $detalles['paciente_nombre'] ?? 'tu paciente';
    $actorNombre = $detalles['actor_nombre'] ?? null;
    $origen = $detalles['origen'] ?? null; // 'paciente' o 'cuidador'
    
    // Función helper para generar icono responsive a modo oscuro
    $getIcon = function($name) {
        return "<img src='/icons/lightmode/{$name}.png' class='light-mode-icon' style='width:14px;height:14px;vertical-align:middle;margin-right:4px'>" .
               "<img src='/icons/darkmode/{$name}.png' class='dark-mode-icon' style='width:14px;height:14px;vertical-align:middle;margin-right:4px;display:none'>";
    };
    
    switch ($tipoCambio) {
        case 'modulo_creado':
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('new') . " $actorNombre realizó cambios en el módulo de $pacienteNombre"
                : $getIcon('new') . " $pacienteNombre ha creado un nuevo módulo de medicamento";
            
        case 'modulo_modificado':
            $modulo = $detalles['modulo'] ?? '';
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('edit') . " $actorNombre realizó cambios en el módulo $modulo de $pacienteNombre"
                : $getIcon('edit') . " $pacienteNombre ha modificado el módulo $modulo";
            
        case 'modulo_eliminado':
            $modulo = $detalles['modulo'] ?? '';
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('trash') . " $actorNombre eliminó el módulo $modulo de $pacienteNombre"
                : $getIcon('trash') . " $pacienteNombre ha eliminado el módulo $modulo";
            
        case 'alarma_creada':
            $modulo = $detalles['modulo'] ?? '';
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('alarm') . " $actorNombre programó una nueva alarma en el módulo $modulo de $pacienteNombre"
                : $getIcon('alarm') . " $pacienteNombre ha programado una nueva alarma en el módulo $modulo";
            
        case 'alarma_modificada':
            $modulo = $detalles['modulo'] ?? '';
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('refresh') . " $actorNombre modificó una alarma en el módulo $modulo de $pacienteNombre"
                : $getIcon('refresh') . " $pacienteNombre ha modificado una alarma en el módulo $modulo";
            
        case 'alarma_eliminada':
            $modulo = $detalles['modulo'] ?? '';
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('incorrect') . " $actorNombre eliminó una alarma del módulo $modulo de $pacienteNombre"
                : $getIcon('incorrect') . " $pacienteNombre ha eliminado una alarma del módulo $modulo";
            
        case 'alarmas_eliminadas_multiple':
            $cantidad = $detalles['cantidad'] ?? 0;
            $modulos = $detalles['modulos'] ?? '';
            $textoAlarmas = $cantidad > 1 ? "$cantidad alarmas" : "una alarma";
            $textoModulos = $modulos ? " de los módulos $modulos" : "";
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('incorrect') . " $actorNombre eliminó $textoAlarmas$textoModulos de $pacienteNombre"
                : $getIcon('incorrect') . " $pacienteNombre ha eliminado $textoAlarmas$textoModulos";
            
        default:
            return $origen === 'cuidador' && $actorNombre
                ? $getIcon('clipboard') . " $actorNombre realizó cambios en el pastillero de $pacienteNombre"
                : $getIcon('clipboard') . " $pacienteNombre ha realizado cambios en su pastillero";
    }
}

/**
 * Función para obtener el nombre del paciente
 */
function obtenerNombrePaciente($pacienteId) {
    global $conn;
    
    $sql = "SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pacienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['nombre_usuario'];
    }
    
    return 'paciente';
}

// Si se llama directamente, devolver información de uso
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo json_encode([
        'info' => 'Este archivo contiene funciones para notificar automáticamente a los cuidadores',
        'uso' => 'Incluir este archivo y llamar a notificarCuidadorCambio()',
        'ejemplo' => 'notificarCuidadorCambio($pacienteId, "modulo_creado", ["paciente_nombre" => "Juan"])'
    ]);
}
?>
