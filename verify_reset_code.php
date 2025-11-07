<?php
require_once 'session_init.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false]);
  exit;
}

$code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
if (strlen($code) !== 6) {
  echo json_encode(['success' => false]);
  exit;
}

// Necesitamos el email guardado previamente en la sesión
$email = $_SESSION['pwd_reset_email'] ?? null;
if (!$email) {
  echo json_encode(['success' => false]);
  exit;
}

// Buscar usuario
$stmtU = $conn->prepare('SELECT id_usuario FROM usuarios WHERE email_usuario = ? LIMIT 1');
$stmtU->bind_param('s', $email);
$stmtU->execute();
$resU = $stmtU->get_result();
if (!$rowU = $resU->fetch_assoc()) {
  // genérico
  echo json_encode(['success' => false]);
  exit;
}
$userId = $rowU['id_usuario'];

// Validar código vigente y no usado
$stmt = $conn->prepare('SELECT id FROM verification_codes WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
$stmt->bind_param('ss', $userId, $code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  // Marcar como usado
  $stmt2 = $conn->prepare('UPDATE verification_codes SET used = 1 WHERE id = ?');
  $stmt2->bind_param('s', $row['id']);
  $stmt2->execute();

  // Señalar sesión verificada para cambio de contraseña
  $_SESSION['pwd_reset_verified_user'] = $userId;
  echo json_encode(['success' => true]);
  exit;
}

// genérico
echo json_encode(['success' => false]);
?>