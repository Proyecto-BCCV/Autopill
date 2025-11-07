<?php
require_once 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$conn = obtenerConexion();
if (!$conn) die("Error de conexión\n");

echo "=== ALARMAS ACTIVAS (con días configurados) ===\n\n";

$sql = "SELECT a.id_alarma, a.nombre_alarma, a.hora_alarma, a.dias_semana, 
               a.ultima_notificacion, a.modificado_por
        FROM alarmas a 
        INNER JOIN codigos_esp e ON a.id_esp_alarma = e.id_esp
        WHERE a.dias_semana LIKE '%1%' 
        ORDER BY a.hora_alarma";

$res = $conn->query($sql);

if (!$res) {
    die("Error en query: " . $conn->error . "\n");
}

echo "Total alarmas: " . $res->num_rows . "\n\n";
echo str_pad("ID", 6) . " | " . str_pad("Nombre", 40) . " | " . str_pad("Hora", 10) . " | " . str_pad("Días", 10) . " | Última Notif\n";
echo str_repeat("-", 120) . "\n";

while ($r = $res->fetch_assoc()) {
    echo str_pad($r['id_alarma'], 6) . " | ";
    echo str_pad(substr($r['nombre_alarma'], 0, 40), 40) . " | ";
    echo str_pad($r['hora_alarma'], 10) . " | ";
    echo str_pad($r['dias_semana'], 10) . " | ";
    echo ($r['ultima_notificacion'] ?: 'NUNCA') . "\n";
}

echo "\n=== DETECTAR POSIBLES DUPLICADOS (misma hora) ===\n\n";

$sql2 = "SELECT hora_alarma, COUNT(*) as total, GROUP_CONCAT(nombre_alarma SEPARATOR ' | ') as nombres
         FROM alarmas a
         INNER JOIN codigos_esp e ON a.id_esp_alarma = e.id_esp
         WHERE a.dias_semana LIKE '%1%'
         GROUP BY hora_alarma
         HAVING total > 1
         ORDER BY hora_alarma";

$res2 = $conn->query($sql2);

if ($res2->num_rows > 0) {
    echo "⚠️  ADVERTENCIA: Se encontraron horas con múltiples alarmas:\n\n";
    while ($r = $res2->fetch_assoc()) {
        echo "Hora: " . $r['hora_alarma'] . " - " . $r['total'] . " alarmas\n";
        echo "  Nombres: " . $r['nombres'] . "\n\n";
    }
} else {
    echo "✅ No se encontraron duplicados de hora\n";
}

$conn->close();
?>
