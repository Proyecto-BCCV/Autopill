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

// Obtener el ID del paciente desde la URL
$pacienteId = $_GET['paciente_id'] ?? null;

if (!$pacienteId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente requerido']);
    exit;
}

// Verificar que el cuidador tenga acceso a este paciente
$cuidadorId = getUserId();
$sql = "SELECT * FROM cuidadores WHERE cuidador_id = ? AND paciente_id = ? AND estado = 'activo'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $cuidadorId, $pacienteId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes acceso a este paciente']);
    exit;
}

try {
    // Verificar conexión de base de datos
    if (!isset($conn) || !$conn) {
        throw new Exception("No hay conexión a la base de datos");
    }
    
    // Test básico de conectividad
    $testQuery = $conn->query("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Error de conectividad a la base de datos: " . $conn->error);
    }
    
    // Inicializar 5 módulos base para paridad con obtener_modulos.php
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

    // Intentar consulta simple primero para verificar si la tabla existe
    $testModulos = $conn->query("SELECT COUNT(*) as total FROM modulos LIMIT 1");
    if (!$testModulos) {
        throw new Exception("La tabla 'modulos' no existe o no es accesible: " . $conn->error);
    }
    
    // Obtener módulos activos reales del paciente
    $sql = "SELECT numero_modulo, nombre_medicamento, hora_toma, dias_semana, activo FROM modulos WHERE id_usuario = ? AND activo = 1 ORDER BY numero_modulo";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta módulos: " . $conn->error);
    }
    
    $stmt->bind_param("s", $pacienteId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta módulos: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $idx = (int)$row['numero_modulo'] - 1;
        if ($idx >=0 && $idx < 5) {
            $numeroModulo = (int)$row['numero_modulo'];
            
            // Verificar si existen alarmas activas para este módulo
            // La tabla alarmas usa id_esp_alarma y modificado_por, no id_usuario
            // Primero necesitamos obtener el id_esp del paciente desde codigos_esp
            try {
                $sqlEsp = "SELECT id_esp FROM codigos_esp WHERE id_usuario = ? LIMIT 1";
                $stmtEsp = $conn->prepare($sqlEsp);
                $countAlarmas = 0;
                
                if ($stmtEsp && $stmtEsp->bind_param("s", $pacienteId) && $stmtEsp->execute()) {
                    $resultEsp = $stmtEsp->get_result();
                    if ($rowEsp = $resultEsp->fetch_assoc()) {
                        $idEsp = $rowEsp['id_esp'];
                        
                        // Ahora buscar alarmas para este ESP y módulo
                        // La tabla alarmas no tiene columna 'activo', solo buscamos por ESP y nombre de módulo
                        $sqlAlarmas = "SELECT COUNT(*) as count FROM alarmas WHERE id_esp_alarma = ? AND nombre_alarma LIKE ?";
                        $stmtAlarmas = $conn->prepare($sqlAlarmas);
                        
                        if ($stmtAlarmas) {
                            $nombreBusqueda = "%Módulo " . $numeroModulo . "%";
                            $stmtAlarmas->bind_param("ss", $idEsp, $nombreBusqueda);
                            
                            if ($stmtAlarmas->execute()) {
                                $resultAlarmas = $stmtAlarmas->get_result();
                                $countAlarmas = $resultAlarmas->fetch_assoc()['count'];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Exception en consulta de alarmas: " . $e->getMessage());
                $countAlarmas = 0; // Valor por defecto si hay excepción
            }
            
            // Solo mostrar el nombre del medicamento si hay alarmas activas
            // Si no hay alarmas, mostrar "Sin programar"
            $nombreMedicamento = ($countAlarmas > 0 && $row['nombre_medicamento']) 
                ? $row['nombre_medicamento'] 
                : 'Sin programar';
            
            // Formatear hora a HH:MM si existe y hay alarmas
            $hora12 = null;
            if ($row['hora_toma'] && $countAlarmas > 0) {
                $hora = new DateTime($row['hora_toma']);
                $hora12 = $hora->format('H:i');
            }
            
            // Días legibles si existen y hay alarmas
            $diasLegibles = null;
            if ($row['dias_semana'] && $countAlarmas > 0) {
                $dias = $row['dias_semana'];
                $diasArray = [];
                $diasNombres = ['L','M','X','J','V','S','D'];
                for ($i2=0; $i2<7; $i2++) {
                    if (isset($dias[$i2]) && $dias[$i2] === '1') $diasArray[] = $diasNombres[$i2];
                }
                $diasLegibles = implode(' ', $diasArray);
            }
            
            $modulos[$idx] = [
                'numero_modulo' => $numeroModulo,
                'nombre_medicamento' => $nombreMedicamento,
                'hora_toma' => $hora12,
                'dias_semana' => $diasLegibles,
                'activo' => (int)$row['activo']
            ];
        }
    }

    echo json_encode(['success' => true, 'modulos' => $modulos]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
