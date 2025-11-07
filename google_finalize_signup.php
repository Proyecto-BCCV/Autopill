<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json; charset=utf-8');

require_once 'session_init.php';
require_once 'conexion.php';

// Asegurar que la tabla autenticacion_google existe
$conn->query("CREATE TABLE IF NOT EXISTS autenticacion_google (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario VARCHAR(6) NOT NULL,
  google_id VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  UNIQUE KEY unique_user_google (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Debe existir pendiente de Google en sesión
$pending = $_SESSION['google_pending'] ?? null;
if (!$pending || empty($pending['sub']) || empty($pending['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sesión de Google no encontrada o expirada']);
    exit;
}

// Leer role del body JSON
$raw = file_get_contents('php://input');
$json = json_decode($raw, true) ?: [];
$role = $json['role'] ?? '';
if (!in_array($role, ['paciente','cuidador'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rol inválido']);
    exit;
}

// Si el sub ya está asociado, iniciar sesión directo
$sub = $pending['sub'];
$email = $pending['email'];
$emailVerified = !empty($pending['email_verified']);
$name = $pending['name'] ?? '';

if ($stmt = $conn->prepare("SELECT ag.id_usuario, u.nombre_usuario, u.rol, u.email_usuario FROM autenticacion_google ag INNER JOIN usuarios u ON ag.id_usuario = u.id_usuario WHERE ag.google_id = ? LIMIT 1")) {
    $stmt->bind_param('s', $sub);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // Clean pending and login
        unset($_SESSION['google_pending']);
        $_SESSION['usuario'] = $row['nombre_usuario'];
        $_SESSION['user_id'] = $row['id_usuario'];
        $_SESSION['email'] = $row['email_usuario'];
        $_SESSION['rol'] = $row['rol'] ?? 'paciente';
        $_SESSION['last_activity'] = time();
        asociarEspSiNoExiste($row['id_usuario'], $row['nombre_usuario']);
        if ($_SESSION['rol'] !== 'cuidador') {
            $espRow = obtenerEspAsignado($row['id_usuario'], true);
            if (!$espRow) {
                $_SESSION['needs_esp32'] = true;
                $_SESSION['esp_info'] = null;
                error_log('[google_finalize_signup][existing-sub] Usuario ' . $row['id_usuario'] . ' sin ESP -> vincular_esp');
            } else {
                $_SESSION['needs_esp32'] = false;
                error_log('[google_finalize_signup][existing-sub] Usuario ' . $row['id_usuario'] . ' con ESP=' . ($espRow['nombre_esp'] ?? 'N/A'));
            }
        } else {
            $_SESSION['needs_esp32'] = false;
        }
        $redirect = ($row['rol'] === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php';
        if (!empty($_SESSION['needs_esp32'])) {
            $_SESSION['post_link_redirect'] = $redirect;
            $redirect = 'vincular_esp.php';
        }
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        exit;
    }
}

// Crear usuario nuevo
function generarIdUsuarioUnico($conn) {
  do {
      $id = str_pad(strval(rand(0, 999999)), 6, '0', STR_PAD_LEFT);
      $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ?");
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $result = $stmt->get_result();
  } while ($result->num_rows > 0);
  return $id;
}

// Si ya existe un usuario con este email, vincular Google y loguear
if ($stmt = $conn->prepare("SELECT id_usuario, nombre_usuario, rol, email_usuario FROM usuarios WHERE email_usuario = ? LIMIT 1")) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($exist = $res->fetch_assoc()) {
        // Vincular mapeo y loguear con el usuario existente (respetando su rol actual)
        $conn->begin_transaction();
        try {
            $stmt2 = $conn->prepare("INSERT INTO autenticacion_google (id_usuario, google_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE google_id = VALUES(google_id)");
            $stmt2->bind_param('ss', $exist['id_usuario'], $sub);
            $stmt2->execute();
            error_log('[google_finalize_signup] Vinculando Google a usuario existente: user_id=' . $exist['id_usuario'] . ' google_id=' . $sub);
            // Opcional: marcar email verificado si viene así de Google
            if (!empty($emailVerified)) {
                if ($stmt3 = $conn->prepare("UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = ?")) {
                    $stmt3->bind_param('s', $exist['id_usuario']);
                    $stmt3->execute();
                }
            }
            $conn->commit();

            unset($_SESSION['google_pending']);
            $_SESSION['usuario'] = $exist['nombre_usuario'];
            $_SESSION['user_id'] = $exist['id_usuario'];
            $_SESSION['email'] = $exist['email_usuario'];
            $_SESSION['rol'] = $exist['rol'] ?: 'paciente';
            $_SESSION['last_activity'] = time();
            asociarEspSiNoExiste($exist['id_usuario'], $exist['nombre_usuario']);
            if ($_SESSION['rol'] !== 'cuidador') {
                $espRow = obtenerEspAsignado($exist['id_usuario'], true);
                if (!$espRow) {
                    $_SESSION['needs_esp32'] = true;
                    $_SESSION['esp_info'] = null;
                    error_log('[google_finalize_signup][link-existing] Usuario ' . $exist['id_usuario'] . ' sin ESP -> vincular_esp');
                } else {
                    $_SESSION['needs_esp32'] = false;
                    error_log('[google_finalize_signup][link-existing] Usuario ' . $exist['id_usuario'] . ' con ESP=' . ($espRow['nombre_esp'] ?? 'N/A'));
                }
            } else {
                $_SESSION['needs_esp32'] = false;
            }
            $redirect = ($exist['rol'] === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php';
            if (!empty($_SESSION['needs_esp32'])) {
                $_SESSION['post_link_redirect'] = $redirect;
                $redirect = 'vincular_esp.php';
            }
            echo json_encode(['success' => true, 'redirect' => $redirect]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log('[google_finalize_signup] link-existing error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error vinculando cuenta existente']);
            exit;
        }
    }
}

$conn->begin_transaction();
try {
    $idUsuario = generarIdUsuarioUnico($conn);
    $nombreUsuario = substr(preg_replace('/[^A-Za-z0-9_]/', '', ($name ?: explode('@', $email)[0])), 0, 20);
    if ($nombreUsuario === '') { $nombreUsuario = 'user' . substr($idUsuario, -3); }

    // Crear usuario
    $stmt = $conn->prepare("INSERT INTO usuarios (id_usuario, email_usuario, nombre_usuario, email_verificado, rol, first_login) VALUES (?, ?, ?, ?, ?, 0)");
    $emailVer = ($emailVerified ? 1 : 0);
    $stmt->bind_param('sssis', $idUsuario, $email, $nombreUsuario, $emailVer, $role);
    $stmt->execute();

    // Config por defecto
    $stmt = $conn->prepare("INSERT INTO configuracion_usuario (id_usuario, formato_hora_config, modo_oscuro_config, cuidador_flag_config, notificaciones_config) VALUES (?, 0, 0, 0, 1)");
    $stmt->bind_param('s', $idUsuario);
    $stmt->execute();

    // Mapeo Google
    $stmt = $conn->prepare("INSERT INTO autenticacion_google (id_usuario, google_id) VALUES (?, ?)");
    $stmt->bind_param('ss', $idUsuario, $sub);
    $stmt->execute();
    error_log('[google_finalize_signup] Mapeo Google creado: user_id=' . $idUsuario . ' google_id=' . $sub);

    $conn->commit();

    unset($_SESSION['google_pending']);
    // Iniciar sesión
    $_SESSION['usuario'] = $nombreUsuario;
    $_SESSION['user_id'] = $idUsuario;
    $_SESSION['email'] = $email;
    $_SESSION['rol'] = $role;
    $_SESSION['last_activity'] = time();
    asociarEspSiNoExiste($idUsuario, $nombreUsuario);
    if ($_SESSION['rol'] !== 'cuidador') {
        $espRow = obtenerEspAsignado($idUsuario, true);
        if (!$espRow) {
            $_SESSION['needs_esp32'] = true;
            $_SESSION['esp_info'] = null;
            error_log('[google_finalize_signup][new-user] Usuario ' . $idUsuario . ' sin ESP -> vincular_esp');
        } else {
            $_SESSION['needs_esp32'] = false;
            error_log('[google_finalize_signup][new-user] Usuario ' . $idUsuario . ' con ESP=' . ($espRow['nombre_esp'] ?? 'N/A'));
        }
    } else {
        $_SESSION['needs_esp32'] = false;
    }
    $redirect = ($role === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php';
    if (!empty($_SESSION['needs_esp32'])) {
        $_SESSION['post_link_redirect'] = $redirect;
        $redirect = 'vincular_esp.php';
    }

    echo json_encode(['success' => true, 'redirect' => $redirect]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('[google_finalize_signup] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error creando el usuario']);
}

?>