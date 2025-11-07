<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Método no permitido']);
  exit;
}

$userId = $_SESSION['pwd_reset_verified_user'] ?? null;
if (!$userId) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'No autorizado']);
  exit;
}

$password = trim($_POST['password'] ?? '');
$confirm = trim($_POST['confirm'] ?? '');

if ($password === '' || $password !== $confirm) {
  echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden']);
  exit;
}

// Reglas: 8+ chars, 1 número, 1 especial, 1 mayúscula
$hasLen = strlen($password) >= 8;
$hasNum = preg_match('/\d/', $password);
$hasSpec = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);
$hasUpper = preg_match('/[A-Z]/', $password);
if (!($hasLen && $hasNum && $hasSpec && $hasUpper)) {
  echo json_encode(['success' => false, 'error' => 'Contraseña no cumple requisitos']);
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Actualizar contraseña
$stmt = $conn->prepare('UPDATE autenticacion_local SET contrasena_usuario = ? WHERE id_usuario = ?');
$stmt->bind_param('ss', $hash, $userId);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la contraseña']);
  exit;
}

// Si no existe registro local (p.ej. cuenta de Google), crear uno
if ($stmt->affected_rows === 0) {
  $stmtIns = $conn->prepare('INSERT INTO autenticacion_local (id_usuario, contrasena_usuario) VALUES (?, ?)');
  $stmtIns->bind_param('ss', $userId, $hash);
  if (!$stmtIns->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo establecer la contraseña']);
    exit;
  }
}

// Invalida tokens/reset previos relacionados
@$conn->query("UPDATE password_reset_tokens SET used = 1 WHERE user_id = '" . $conn->real_escape_string($userId) . "'");
@$conn->query("UPDATE verification_codes SET used = 1 WHERE user_id = '" . $conn->real_escape_string($userId) . "'");

// Limpiar flags de sesión de recuperación
unset($_SESSION['pwd_reset_verified_user']);
unset($_SESSION['pwd_reset_token']); // Limpiar token guardado
unset($_SESSION['pwd_reset_email']);
unset($_SESSION['last_pwd_reset_request']);

echo json_encode(['success' => true]);
?>