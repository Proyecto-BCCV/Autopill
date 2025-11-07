<?php
require_once 'session_init.php';
require_once 'conexion.php';
require_once 'logger.php';

// Configurar respuesta como JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';
$role = $_POST['role'] ?? 'paciente';

// Generar nombre de usuario basado en el email
$name = explode('@', $email)[0]; // Tomar la parte antes del @
$name = preg_replace('/[^a-zA-Z0-9]/', '', $name); // Solo letras y números
$name = substr($name, 0, 20); // Limitar a 20 caracteres

// Validaciones básicas
if (empty($email) || empty($password) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son requeridos']);
    exit;
}

// Validar que sea un email válido
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Debes ingresar un email válido']);
    exit;
}

// Validar que las contraseñas coincidan
if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'Las contraseñas no coinciden']);
    exit;
}

// Validar contraseña (mínimo 8 caracteres, al menos un número y un carácter especial)
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

if (!preg_match('/\d/', $password)) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe contener al menos un número']);
    exit;
}

if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe contener al menos un carácter especial']);
    exit;
}

// Validar rol
if (!in_array($role, ['paciente', 'cuidador'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Rol no válido']);
    exit;
}

// Validar que el nombre generado sea válido
if (strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'El email debe tener al menos 2 caracteres antes del @']);
    exit;
}

// Agregar función para generar un id de 6 dígitos único
function generarIdUsuarioUnico($conn) {
    do {
        $id = str_pad(strval(rand(0, 999999)), 6, '0', STR_PAD_LEFT);
        $sql = "SELECT id_usuario FROM usuarios WHERE id_usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
    } while ($result->num_rows > 0);
    return $id;
}

try {
    // Log de base seleccionada y parámetros de entrada (parciales)
    $dbNameRes = $conn->query('SELECT DATABASE() as db');
    $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : ['db' => null];
    app_log('[Register] Inicio', ['db' => $dbNameRow['db'] ?? null, 'email' => $email, 'role' => $role]);

    // Verificar si el email ya existe
    $sql = "SELECT id_usuario FROM usuarios WHERE email_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) { throw new Exception('Prepare email existente: ' . $conn->error); }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) { throw new Exception('Execute email existente: ' . $stmt->error); }
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'El email ya está registrado']);
        exit;
    }
    
    // Verificar si el nombre de usuario ya existe y generar uno único si es necesario
$originalName = $name;
$counter = 1;
do {
    $sql = "SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?";
    if (!$stmt = $conn->prepare($sql)) { throw new Exception('Prepare nombre existente: ' . $conn->error); }
    $stmt->bind_param("s", $name);
    if (!$stmt->execute()) { throw new Exception('Execute nombre existente: ' . $stmt->error); }
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $name = $originalName . $counter;
        $counter++;
    } else {
        break;
    }
} while ($counter <= 100); // Límite para evitar bucle infinito
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Generar un id de usuario único
    $idUsuario = generarIdUsuarioUnico($conn);

    // Insertar usuario en la tabla usuarios
    $sql = "INSERT INTO usuarios (id_usuario, email_usuario, nombre_usuario, email_verificado, rol) VALUES (?, ?, ?, 0, ?)";
    if (!$stmt = $conn->prepare($sql)) { throw new Exception('Prepare insert usuarios: ' . $conn->error); }
    $stmt->bind_param("ssss", $idUsuario, $email, $name, $role);
    if (!$stmt->execute()) { throw new Exception('Execute insert usuarios: ' . $stmt->error); }
    
    // Hash de la contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar contraseña en la tabla autenticacion_local
    $sql = "INSERT INTO autenticacion_local (id_usuario, contrasena_usuario) VALUES (?, ?)";
    if (!$stmt = $conn->prepare($sql)) { throw new Exception('Prepare insert auth_local: ' . $conn->error); }
    $stmt->bind_param("ss", $idUsuario, $hashedPassword);
    if (!$stmt->execute()) { throw new Exception('Execute insert auth_local: ' . $stmt->error); }
    
    // Insertar configuración por defecto
    $sql = "INSERT INTO configuracion_usuario (id_usuario, formato_hora_config, modo_oscuro_config, cuidador_flag_config, notificaciones_config) VALUES (?, 0, 0, 0, 1)";
    if (!$stmt = $conn->prepare($sql)) { throw new Exception('Prepare insert config: ' . $conn->error); }
    $stmt->bind_param("s", $idUsuario);
    if (!$stmt->execute()) { throw new Exception('Execute insert config: ' . $stmt->error); }
    
    // NO crear ESP automáticamente - el usuario debe vincular manualmente en vincular_esp.php
    app_log('[Register] Usuario registrado sin ESP automático - deberá vincular manualmente', ['user_id' => $idUsuario, 'role' => $role]);

    // Confirmar transacción
    $conn->commit();
    app_log('[Register] OK', ['user_id' => $idUsuario, 'email' => $email]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'redirect' => 'login.php',
        'user_id' => $idUsuario
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor. Por favor intenta de nuevo.']);
    
    // Log del error (en producción, usar un sistema de logging apropiado)
    app_log('Error en registro', ['message' => $e->getMessage()]);
}
?> 