<?php
require_once 'conexion.php';

$conn = obtenerConexion();

echo "=== ÚLTIMAS NOTIFICACIONES CREADAS ===\n\n";

$sql = "SELECT 
            n.id_notificacion,
            n.id_usuario_destinatario,
            n.tipo_notificacion,
            n.mensaje,
            n.detalles_json,
            n.fecha_creacion,
            u.nombre_usuario
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.id_usuario_destinatario = u.id_usuario
        WHERE n.tipo_notificacion = 'pastilla_dispensada'
        ORDER BY n.fecha_creacion DESC
        LIMIT 5";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Total encontradas: " . $result->num_rows . "\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "─────────────────────────────────────\n";
        echo "ID: " . $row['id_notificacion'] . "\n";
        echo "Destinatario: " . $row['nombre_usuario'] . " (ID: " . $row['id_usuario_destinatario'] . ")\n";
        echo "Mensaje: " . $row['mensaje'] . "\n";
        echo "Fecha: " . $row['fecha_creacion'] . "\n";
        
        if ($row['detalles_json']) {
            $detalles = json_decode($row['detalles_json'], true);
            echo "Detalles:\n";
            echo "  - Módulo: " . ($detalles['modulo'] ?? 'N/A') . "\n";
            echo "  - Alarma: " . ($detalles['alarma_nombre'] ?? 'N/A') . "\n";
            if (isset($detalles['paciente_nombre'])) {
                echo "  - Paciente: " . $detalles['paciente_nombre'] . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "No hay notificaciones de tipo 'pastilla_dispensada'\n";
}

$conn->close();
?>
