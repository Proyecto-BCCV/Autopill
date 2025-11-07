<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que el usuario sea cuidador
if (!isCuidador()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo los cuidadores pueden acceder.']);
    exit;
}

try {
    $pacienteId = $_GET['paciente_id'] ?? null;
    $ultimaActualizacion = $_GET['ultima_actualizacion'] ?? null;

    if (!$pacienteId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de paciente requerido']);
        exit;
    }

    // Verificar acceso
    $cuidadorId = getUserId();
    $sqlAcceso = "SELECT 1 FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = 'activo' LIMIT 1";
    if (!$stmtAcceso = $conn->prepare($sqlAcceso)) {
        throw new Exception('Error preparando verificación acceso: ' . $conn->error);
    }
    $stmtAcceso->bind_param('ss', $cuidadorId, $pacienteId);
    if (!$stmtAcceso->execute()) {
        throw new Exception('Error ejecutando verificación acceso: ' . $stmtAcceso->error);
    }
    $resAcceso = $stmtAcceso->get_result();
    if ($resAcceso->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes acceso a este paciente']);
        exit;
    }

    // Obtener módulos activos (normalizar a 5 módulos como otros endpoints)
    $modulos = [];
    for ($i=1;$i<=5;$i++) {
        $modulos[$i] = [
            'numero_modulo' => $i,
            'nombre_medicamento' => 'Sin programar',
            'hora_toma' => null,
            'dias_semana' => null,
            'activo' => 1
        ];
    }
    $sqlMod = "SELECT numero_modulo, nombre_medicamento, hora_toma, dias_semana, activo FROM modulos WHERE id_usuario = ? AND activo = 1";
    if (!$stmtMod = $conn->prepare($sqlMod)) {
        throw new Exception('Error preparando módulos: ' . $conn->error);
    }
    $stmtMod->bind_param('s', $pacienteId);
    if (!$stmtMod->execute()) {
        throw new Exception('Error ejecutando módulos: ' . $stmtMod->error);
    }
    $resMod = $stmtMod->get_result();
    while ($row = $resMod->fetch_assoc()) {
        $idx = (int)$row['numero_modulo'];
        if ($idx>=1 && $idx<=5) {
            $modulos[$idx] = $row;
        }
    }
    // Reindex numeric keys
    $modulos = array_values($modulos);

    // Alarmas por módulo (usando join por id_usuario del paciente)
    $alarmasPorModulo = [];
    $sqlAl = "SELECT a.* FROM alarmas a INNER JOIN codigos_esp c ON c.id_esp = a.id_esp_alarma WHERE c.id_usuario = ? AND a.nombre_alarma = ? ORDER BY a.hora_alarma";
    if (!$stmtAl = $conn->prepare($sqlAl)) {
        throw new Exception('Error preparando alarmas: ' . $conn->error);
    }
    foreach ($modulos as $m) {
        $nombreModulo = 'Módulo ' . $m['numero_modulo'];
        $stmtAl->bind_param('ss', $pacienteId, $nombreModulo);
        if (!$stmtAl->execute()) {
            throw new Exception('Error ejecutando alarmas módulo ' . $m['numero_modulo'] . ': ' . $stmtAl->error);
        }
        $resAl = $stmtAl->get_result();
        $arr = [];
        while ($r = $resAl->fetch_assoc()) { $arr[] = $r; }
        $alarmasPorModulo[$m['numero_modulo']] = $arr;
    }

    // Información básica del paciente
    $paciente = null;
    if ($stmtP = $conn->prepare('SELECT nombre_usuario, email_usuario FROM usuarios WHERE id_usuario = ? LIMIT 1')) {
        $stmtP->bind_param('s', $pacienteId);
        if ($stmtP->execute()) {
            $paciente = $stmtP->get_result()->fetch_assoc();
        }
    }

    // Estrategia simplificada: siempre considerar que hay cambios si no se proporcionó última marca.
    // (Se puede mejorar guardando hashes o timestamps en el futuro.)
    $hayCambios = true;
    if ($ultimaActualizacion) {
        // Placeholder: podríamos comparar counts básicos para minimizar refrescos
        $hayCambios = true; // Forzar por ahora para mantener sincronía
    }

    echo json_encode([
        'success' => true,
        'hay_cambios' => $hayCambios,
        'paciente' => $paciente,
        'modulos' => $modulos,
        'alarmas_por_modulo' => $alarmasPorModulo,
        'timestamp_actualizacion' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor', 'detail' => $e->getMessage()]);
}
?>
