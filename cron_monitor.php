<?php
/**
 * CRON MONITOR - Ejecutor automático de monitor_alarmas.php
 * 
 * Este script se auto-ejecuta periódicamente usando un mecanismo de "poor man's cron"
 * basado en requests HTTP normales. No requiere configuración de servidor.
 * 
 * CÓMO FUNCIONA:
 * 1. Este script se incluye en los dashboards
 * 2. Cada vez que alguien carga una página, verifica si pasó más de 1 minuto desde la última ejecución
 * 3. Si pasó el tiempo, ejecuta monitor_alarmas.php en segundo plano
 * 4. Usa un archivo de lock para evitar ejecuciones concurrentes
 * 
 * VENTAJAS:
 * - No requiere configuración de servidor Windows
 * - No requiere tareas programadas
 * - Funciona automáticamente con tráfico normal del sitio
 * - Se ejecuta en segundo plano sin afectar la carga de la página
 */

// Configuración
define('CRON_INTERVAL', 60); // Ejecutar cada 60 segundos
define('LOCK_FILE', __DIR__ . '/logs/cron_monitor.lock');
define('LOG_FILE', __DIR__ . '/logs/cron_monitor_exec.log');

/**
 * Verifica si es momento de ejecutar el monitor
 */
function shouldRun() {
    // Si no existe el archivo de lock, ejecutar
    if (!file_exists(LOCK_FILE)) {
        return true;
    }
    
    // Verificar cuánto tiempo pasó desde la última ejecución
    $lastRun = (int)@file_get_contents(LOCK_FILE);
    $elapsed = time() - $lastRun;
    
    return $elapsed >= CRON_INTERVAL;
}

/**
 * Marca que el cron se está ejecutando
 */
function markRunning() {
    @file_put_contents(LOCK_FILE, time());
}

/**
 * Log de ejecución
 */
function logExecution($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Ejecuta monitor_alarmas.php de forma asíncrona (no bloquea la página)
 */
function executeMonitorAsync() {
    // Si se ejecuta desde CLI, ejecutar directamente sin cURL
    if (php_sapi_name() === 'cli') {
        ob_start();
        include __DIR__ . '/monitor_alarmas.php';
        $output = ob_get_clean();
        $data = @json_decode($output, true);
        return ['success' => ($data && isset($data['success']) && $data['success']), 'code' => 200];
    }
    
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
           . dirname($_SERVER['SCRIPT_NAME']) . '/monitor_alarmas.php';
    
    // Usar cURL en modo no-bloqueante
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Timeout de 1 segundo para no esperar
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    
    // Ejecutar en segundo plano sin esperar respuesta completa
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    
    $result = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['success' => ($httpCode == 200), 'code' => $httpCode];
}

// ===== EJECUCIÓN PRINCIPAL =====

// Solo ejecutar si se incluye desde otro script (no directamente)
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // Si se llama directamente, ejecutar inmediatamente
    markRunning();
    logExecution("Ejecución manual directa");
    
    $result = executeMonitorAsync();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Monitor ejecutado manualmente',
        'result' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Ejecución automática (cuando se incluye desde otro archivo)
if (shouldRun()) {
    markRunning();
    logExecution("Auto-ejecución desde " . basename($_SERVER['PHP_SELF']));
    
    // Ejecutar de forma asíncrona (no bloquea)
    executeMonitorAsync();
}

// No mostrar nada - este script es silencioso cuando se incluye
?>
