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

try {
    $userId = $_SESSION['user_id'];
    
    // Obtener el id_esp del usuario desde codigos_esp
    $sqlEsp = "SELECT id_esp FROM codigos_esp WHERE id_usuario = ? LIMIT 1";
    $stmtEsp = $conn->prepare($sqlEsp);
    $stmtEsp->bind_param("s", $userId);
    $stmtEsp->execute();
    $resultEsp = $stmtEsp->get_result();
    $idEsp = null;
    if ($rowEsp = $resultEsp->fetch_assoc()) {
        $idEsp = $rowEsp['id_esp'];
    }
    
    // Crear array con los 5 módulos por defecto
    $modulos = [];
    for ($i = 1; $i <= 5; $i++) {
        $modulos[] = [
            'numero_modulo' => $i,
            'nombre_medicamento' => 'Sin programar',
            'hora_toma' => null,
            'dias_semana' => null,
            'activo' => 1
        ];
    }
    
    // Obtener módulos existentes del usuario y sobrescribir los valores por defecto
    $sql = "SELECT numero_modulo, nombre_medicamento, hora_toma, dias_semana, activo 
            FROM modulos 
            WHERE id_usuario = ? AND activo = 1 
            ORDER BY numero_modulo";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $numeroModulo = $row['numero_modulo'];
        
        // Verificar si existen alarmas activas para este módulo
        // La tabla alarmas usa id_esp_alarma, no id_usuario
        $countAlarmas = 0;
        if ($idEsp !== null) {
            $nombreModulo = "Módulo " . $numeroModulo;
            $sqlAlarmas = "SELECT COUNT(*) as count FROM alarmas WHERE id_esp_alarma = ? AND nombre_alarma LIKE ?";
            $stmtAlarmas = $conn->prepare($sqlAlarmas);
            $stmtAlarmas->bind_param("is", $idEsp, $nombreModulo);
            $stmtAlarmas->execute();
            $resultAlarmas = $stmtAlarmas->get_result();
            $countAlarmas = $resultAlarmas->fetch_assoc()['count'];
        }
        
        // Solo mostrar el nombre del medicamento si hay alarmas activas
        // Si no hay alarmas, mostrar "Sin programar"
        $nombreMedicamento = ($countAlarmas > 0 && $row['nombre_medicamento']) 
            ? $row['nombre_medicamento'] 
            : 'Sin programar';
        
        // Convertir hora a formato 12 horas si existe y hay alarmas
        $hora12 = null;
        if ($row['hora_toma'] && $countAlarmas > 0) {
            $hora = new DateTime($row['hora_toma']);
            $hora12 = $hora->format('H:i');
        }
        
        // Convertir días de la semana a formato legible si existe y hay alarmas
        $diasLegibles = null;
        if ($row['dias_semana'] && $countAlarmas > 0) {
            $dias = $row['dias_semana'];
            $diasArray = [];
            $diasNombres = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
            
            for ($i = 0; $i < 7; $i++) {
                if ($dias[$i] === '1') {
                    $diasArray[] = $diasNombres[$i];
                }
            }
            $diasLegibles = implode(' ', $diasArray);
        }
        
        // Actualizar el módulo correspondiente
        $modulos[$numeroModulo - 1] = [
            'numero_modulo' => $numeroModulo,
            'nombre_medicamento' => $nombreMedicamento,
            'hora_toma' => $hora12,
            'dias_semana' => $diasLegibles,
            'activo' => $row['activo']
        ];
    }
    
    echo json_encode(['success' => true, 'modulos' => $modulos]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?> 