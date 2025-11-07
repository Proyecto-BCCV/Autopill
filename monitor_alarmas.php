<?php
// Desactivar COMPLETAMENTE la salida de errores a pantalla
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0); // Desactivar reporte de errores deprecados
ini_set('log_errors', '1');

// Configurar zona horaria Argentina (UTC-3)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Capturar TODO el output
ob_start();

$conexion_error = null;
try {
    require_once 'conexion.php';
    require_once 'notificaciones_dispensado_utils.php';
} catch (Exception $e) {
    $conexion_error = $e->getMessage();
}

// Limpiar TODO el buffer (incluyendo warnings)
$buffer_output = ob_get_clean();

// NOW set headers - DESPUÉS de limpiar el buffer
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Si hubo error en conexión, reportar
if ($conexion_error !== null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en conexion.php: ' . $conexion_error,
        'buffer' => $buffer_output
    ]);
    exit;
}

function logM($m) { 
    $logFile = __DIR__ . '/logs/monitor_alarmas.log';
    $timestamp = date('Y-m-d H:i:s');
    @error_log("[$timestamp] [monitor] $m\n", 3, $logFile);
}
function detectMod($n) { return preg_match('/M[óo]dulo\s*(\d+)/ui', $n, $x) ? (int)$x[1] : 1; }
function isDayOk($d) { 
    if (empty($d)) return true;
    // Convertir date('w') [0=Dom,1=Lun...6=Sáb] a índice del sistema [0=Lun,1=Mar...6=Dom]
    $phpDay = (int)date('w'); // 0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb
    $sysDay = ($phpDay + 6) % 7; // Convertir: 0=Lun, 1=Mar, 2=Mié, 3=Jue, 4=Vie, 5=Sáb, 6=Dom
    return isset($d[$sysDay]) && $d[$sysDay] === '1';
}
function inWindow($t) { 
    return isWithinAlarmWindow($t, 270); // Ventana de 4.5 minutos - unificada con otros componentes
}

// ELIMINADAS: notifExists() y createNotif() - ahora usamos las funciones compartidas de notificaciones_dispensado_utils.php

try {
    logM("=== INICIO ===");
    $conn=obtenerConexion(); if(!$conn) throw new Exception("NoDB");
    $st=['r'=>0,'d'=>0,'c'=>0,'e'=>[]]; $h=date('H:i:s');
    $sql="SELECT a.id_alarma,a.nombre_alarma,a.hora_alarma,a.dias_semana,e.id_esp,u.id_usuario FROM alarmas a INNER JOIN codigos_esp e ON a.id_esp_alarma=e.id_esp INNER JOIN usuarios u ON e.id_usuario=u.id_usuario WHERE e.id_usuario IS NOT NULL ORDER BY a.hora_alarma";
    $res=$conn->query($sql); if(!$res) throw new Exception("Query:".$conn->error);
    logM("Alarmas:".$res->num_rows);
    while($a=$res->fetch_assoc()){ 
        $st['r']++; 
        logM("Revisando alarma ".$a['id_alarma']." - ".$a['nombre_alarma']." - Hora:".$a['hora_alarma']." - Días:".$a['dias_semana']);
        
        if(!isDayOk($a['dias_semana'])) {
            logM("  SKIP: Día no activo");
            continue;
        }
        
        if(!inWindow($a['hora_alarma'])) {
            $diff = abs(time() - strtotime(date('Y-m-d') . ' ' . $a['hora_alarma']));
            logM("  SKIP: Fuera de ventana (diff: ".$diff." seg)");
            continue;
        }
        
        $st['d']++; 
        $mod=detectMod($a['nombre_alarma']); 
        
        logM("  Alarma en ventana - Verificando si crear notificación");
        logM("  Usuario: ".$a['id_usuario']." | Alarma ID: ".$a['id_alarma']." | Módulo: $mod");
        
        // Usar la función compartida para crear notificación
        try{ 
            $created = createPillDispensedNotification($conn, $a['id_esp'], $a['id_alarma'], $mod); 
            if ($created) {
                $st['c']++; 
                logM("  ✅ OK: Notificación creada para ".$a['nombre_alarma']); 
            } else {
                logM("  ⏭️  SKIP: Notificación ya existe (duplicado detectado) para ".$a['nombre_alarma']); 
            }
        } catch(Exception $e){ 
            $err="Err ".$a['id_alarma'].":".$e->getMessage(); 
            logM("  ❌ ".$err); 
            $st['e'][]=$err; 
        }
    }
    logM("=== FIN ===");
    echo json_encode(['success'=>true,'timestamp'=>date('Y-m-d H:i:s'),'hora_actual'=>$h,'estadisticas'=>['alarmas_revisadas'=>$st['r'],'alarmas_disparadas'=>$st['d'],'notificaciones_creadas'=>$st['c'],'errores'=>count($st['e'])],'errores'=>$st['e'],'mensaje'=>$st['c']>0?$st['c']." notificación(es) creada(s)":"Sin alarmas pendientes"],JSON_UNESCAPED_UNICODE);
} catch(Exception $e) {
    logM("ERR:".$e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>$e->getMessage(),
        'timestamp'=>date('Y-m-d H:i:s'),
        'file'=>basename(__FILE__),
        'line'=>$e->getLine(),
        'trace'=>$e->getTraceAsString()
    ],JSON_UNESCAPED_UNICODE);
} finally {
    if(isset($conn)&&$conn) $conn->close();
}
?>
