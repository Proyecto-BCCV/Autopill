<?php
require_once 'conexion.php';

echo "\n=== ALARMAS CONFIGURADAS ===\n";
echo "Hora actual: " . date('H:i:s') . " (Día de la semana: " . date('w') . " - " . date('l') . ")\n\n";

$conn = obtenerConexion();
if (!$conn) {
    die("❌ Error de conexión\n");
}

$sql = "SELECT 
            a.id_alarma,
            a.nombre_alarma,
            a.hora_alarma,
            a.dias_semana,
            e.id_esp,
            u.id_usuario,
            u.nombre_usuario
        FROM alarmas a 
        INNER JOIN codigos_esp e ON a.id_esp_alarma = e.id_esp 
        INNER JOIN usuarios u ON e.id_usuario = u.id_usuario 
        WHERE e.id_usuario IS NOT NULL 
        ORDER BY a.hora_alarma";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Total de alarmas: " . $result->num_rows . "\n\n";
    
    $now = time();
    $phpDay = (int)date('w'); // 0=Dom, 1=Lun, 2=Mar...
    $sysDay = ($phpDay + 6) % 7; // Convertir a 0=Lun, 1=Mar, 2=Mié...6=Dom
    
    while ($row = $result->fetch_assoc()) {
        echo "─────────────────────────────────────\n";
        echo "ID: " . $row['id_alarma'] . "\n";
        echo "Nombre: " . $row['nombre_alarma'] . "\n";
        echo "Hora configurada: " . $row['hora_alarma'] . "\n";
        echo "Usuario: " . $row['nombre_usuario'] . " (ID: " . $row['id_usuario'] . ")\n";
        echo "ESP ID: " . $row['id_esp'] . "\n";
        
        // Días de la semana
        if (!empty($row['dias_semana'])) {
            echo "Días activos: ";
            $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']; // Sistema usa 0=Lun
            $diasConfig = str_split($row['dias_semana']);
            $activos = [];
            foreach ($diasConfig as $i => $val) {
                if ($val === '1') {
                    $activos[] = $dias[$i];
                }
            }
            echo implode(', ', $activos) . "\n";
            
            // Verificar si HOY está activo (usar $sysDay en lugar de $currentDay)
            if (isset($diasConfig[$sysDay]) && $diasConfig[$sysDay] === '1') {
                echo "✅ HOY está activo\n";
            } else {
                echo "❌ HOY NO está activo (hoy es " . $dias[$sysDay] . ")\n";
            }
        } else {
            echo "Días: Todos los días\n";
            echo "✅ HOY está activo\n";
        }
        
        // Verificar ventana de tiempo
        $alarmTime = strtotime(date('Y-m-d') . ' ' . $row['hora_alarma']);
        $diff = $now - $alarmTime; // Positivo si la alarma ya pasó, negativo si es en el futuro
        $diffAbs = abs($diff);
        
        echo "Diferencia temporal: ";
        if ($diff > 0) {
            echo "Pasó hace " . $diffAbs . " segundos\n";
        } else {
            echo "Falta " . $diffAbs . " segundos para que suene\n";
        }
        
        if ($diffAbs <= 30) {
            echo "🔔 ¡ESTÁ DENTRO DE LA VENTANA DE ±30 SEGUNDOS!\n";
            echo "⚠️  DEBERÍA CREAR NOTIFICACIÓN AHORA\n";
        } else {
            echo "⏰ Fuera de ventana (necesita estar dentro de ±30 seg)\n";
        }
        
        echo "\n";
    }
} else {
    echo "⚠️  No hay alarmas configuradas\n";
}

$conn->close();
echo "\n=== FIN ===\n";
?>
