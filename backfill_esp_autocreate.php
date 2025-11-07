<?php
// Script puntual para crear un ESP por defecto a cada paciente que no tenga uno.
// Uso: abrir en navegador autenticado como admin o ejecutar por CLI (php backfill_esp_autocreate.php)
// Reglas: crea ESP32_<idUsuario> (sin ceros a la izquierda). Si existe, añade sufijo _n

require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/logger.php';

header('Content-Type: text/plain; charset=utf-8');

if (!$conn) {
    echo "Sin conexión a la base de datos.\n";
    exit;
}

$creados = 0; $saltados = 0; $errores = 0;

// Seleccionar pacientes sin dispositivo
$sql = "SELECT u.id_usuario FROM usuarios u
        LEFT JOIN codigos_esp c ON c.id_usuario = u.id_usuario
        WHERE u.rol='paciente' AND c.id_usuario IS NULL";
$res = $conn->query($sql);
if (!$res) {
    echo "Error consulta pacientes: " . $conn->error . "\n"; exit;
}

while ($row = $res->fetch_assoc()) {
    $id = $row['id_usuario'];
    $base = 'ESP32_' . ltrim($id, '0');
    if ($base === 'ESP32_') { $base = 'ESP32_0'; }
    $nombre = $base; $suf=1;
    while (true) {
        if ($chk = $conn->prepare('SELECT 1 FROM codigos_esp WHERE nombre_esp=? LIMIT 1')) {
            $chk->bind_param('s', $nombre);
            if ($chk->execute()) {
                $r = $chk->get_result();
                if ($r && $r->num_rows > 0) {
                    $nombre = $base . '_' . $suf;
                    $suf++;
                    if ($suf > 10) { $nombre = $base . '_' . uniqid(); break; }
                    continue;
                }
            }
        }
        break;
    }
    if ($ins = $conn->prepare('INSERT INTO codigos_esp (nombre_esp, id_usuario, modulos_conectados_esp) VALUES (?, ?, "00000")')) {
        $ins->bind_param('ss', $nombre, $id);
        if ($ins->execute()) {
            $creados++;
            echo "[OK] $id -> $nombre\n";
        } else {
            $errores++; echo "[ERR] $id fallo insert: " . $conn->error . "\n";
        }
    } else {
        $errores++; echo "[ERR] $id no preparó insert: " . $conn->error . "\n";
    }
}

echo "Resumen: creados=$creados errores=$errores\n";
?>