<?php
/**
 * SERVICIO AUTO-EJECUTABLE DE MONITOR DE ALARMAS
 * 
 * Este script se ejecuta continuamente y revisa alarmas cada 60 segundos
 * de forma completamente independiente del tráfico del sitio.
 * 
 * EJECUTAR DESDE TERMINAL:
 * C:\xampp\php\php.exe auto_monitor_service.php
 * 
 * MANTENER EN EJECUCIÓN:
 * Este proceso debe quedar corriendo en segundo plano.
 * Si cierras la terminal, el proceso se detiene.
 */

// Evitar timeout
set_time_limit(0);
ini_set('max_execution_time', 0);

// Configuración
define('CHECK_INTERVAL', 60); // Revisar cada 60 segundos
define('LOG_FILE', __DIR__ . '/logs/auto_service.log');

/**
 * Log de servicio
 */
function logService($message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo $line; // También mostrar en consola
}

/**
 * Ejecuta monitor_alarmas.php
 */
function runMonitor() {
    ob_start();
    include __DIR__ . '/monitor_alarmas.php';
    $output = ob_get_clean();
    
    $data = @json_decode($output, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        $notifs = $data['notifications_created'] ?? 0;
        logService("✓ Monitor ejecutado OK - $notifs notificaciones creadas");
        return true;
    } else {
        logService("✗ Error al ejecutar monitor");
        return false;
    }
}

// ===== INICIO DEL SERVICIO =====

logService("========================================");
logService("SERVICIO AUTO-MONITOR INICIADO");
logService("Intervalo: " . CHECK_INTERVAL . " segundos");
logService("========================================");

$iteration = 0;

while (true) {
    $iteration++;
    logService("--- Iteración #$iteration ---");
    
    try {
        runMonitor();
    } catch (Exception $e) {
        logService("ERROR: " . $e->getMessage());
    }
    
    // Esperar antes de la siguiente ejecución
    logService("Esperando " . CHECK_INTERVAL . " segundos...");
    sleep(CHECK_INTERVAL);
}
