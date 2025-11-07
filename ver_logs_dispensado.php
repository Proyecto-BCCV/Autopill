<?php
/**
 * Visualizador de logs de report_alarm_execution.php
 */

echo "<h1>Logs de Report Alarm Execution</h1>";
echo "<p>Este script muestra los últimos logs relacionados con las ejecuciones de alarmas.</p>";

// Intentar leer el error_log de PHP
$possibleLogPaths = [
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    'C:/xampp/apache/logs/error.log',
    'C:/wamp64/logs/apache_error.log',
    '/Applications/XAMPP/logs/error_log',
    ini_get('error_log')
];

echo "<h2>Buscando archivos de log...</h2>";

$logFile = null;
foreach ($possibleLogPaths as $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        $logFile = $path;
        echo "✅ Log encontrado: <code>$path</code><br>";
        break;
    }
}

if (!$logFile) {
    echo "❌ No se pudo encontrar el archivo de log. Rutas probadas:<br>";
    echo "<ul>";
    foreach ($possibleLogPaths as $path) {
        echo "<li><code>$path</code></li>";
    }
    echo "</ul>";
    echo "<p>La configuración actual de error_log es: <code>" . ini_get('error_log') . "</code></p>";
} else {
    echo "<h2>Últimas 100 líneas del log (filtradas por 'report_alarm_execution')</h2>";
    
    // Leer las últimas líneas del log
    $command = "tail -n 500 " . escapeshellarg($logFile) . " | grep -i 'report_alarm_execution'";
    
    // En Windows, usar PowerShell
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = "powershell -Command \"Get-Content -Tail 500 " . escapeshellarg($logFile) . " | Select-String -Pattern 'report_alarm_execution' -CaseSensitive:false\"";
    }
    
    $output = [];
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && !empty($output)) {
        echo "<div style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 600px; overflow-y: auto;'>";
        foreach (array_reverse(array_slice($output, -100)) as $line) {
            // Colorear según el tipo de mensaje
            $color = '#000';
            if (stripos($line, 'error') !== false || stripos($line, '❌') !== false) {
                $color = '#d9534f';
            } elseif (stripos($line, 'warn') !== false) {
                $color = '#f0ad4e';
            } elseif (stripos($line, '✅') !== false || stripos($line, 'exitosa') !== false) {
                $color = '#5cb85c';
            } elseif (stripos($line, 'notificación') !== false) {
                $color = '#0275d8';
                $line = "<strong>$line</strong>";
            }
            
            echo "<div style='color: $color; margin-bottom: 3px;'>" . htmlspecialchars($line) . "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No se encontraron logs recientes de 'report_alarm_execution'.</p>";
        echo "<p>Código de retorno: $returnVar</p>";
    }
}

echo "<hr>";
echo "<h2>Alternativa: Verificar manualmente</h2>";
echo "<p>Si los logs no se muestran aquí, puedes verificar manualmente ejecutando en el servidor:</p>";
echo "<pre>tail -f " . (ini_get('error_log') ?: '/var/log/apache2/error.log') . " | grep report_alarm_execution</pre>";

echo "<hr>";
echo "<h2>Acciones de prueba</h2>";
echo "<ul>";
echo "<li><a href='test_notificacion_dispensado.php' target='_blank'>Crear notificación de prueba</a></li>";
echo "<li><a href='notifications.php' target='_blank'>Ver notificaciones del usuario</a></li>";
echo "</ul>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px;
    }
    h1 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }
    h2 {
        color: #555;
        margin-top: 30px;
    }
    code {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
    pre {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
</style>
