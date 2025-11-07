<?php
// Callback de Google OAuth 2.0
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

require_once 'session_init.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once 'conexion.php';
require_once 'google_oauth_config.php';

// Asegurar que la tabla autenticacion_google existe
$conn->query("CREATE TABLE IF NOT EXISTS autenticacion_google (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario VARCHAR(6) NOT NULL,
  google_id VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  UNIQUE KEY unique_user_google (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function json_redirect($url){
  if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
  echo "<script>window.location.href = '" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "';</script>";
  exit;
}

// Comprobar state anti-CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth2_state']) || hash_equals($_SESSION['oauth2_state'], $_GET['state']) === false){
  error_log('[google_callback] state inválido');
  json_redirect('login.php?error=oauth_state');
}
unset($_SESSION['oauth2_state']);

if (!isset($_GET['code'])) {
  json_redirect('login.php?error=oauth_code');
}

$code = $_GET['code'];

// Intercambiar code por tokens
$tokenEndpoint = 'https://oauth2.googleapis.com/token';
$post = http_build_query([
  'code' => $code,
  'client_id' => GOOGLE_CLIENT_ID,
  'client_secret' => GOOGLE_CLIENT_SECRET,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'grant_type' => 'authorization_code'
]);

// Función robusta para POST con cURL y fallback
function http_post_form($url, $body) {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_HTTPHEADER => [ 'Content-Type: application/x-www-form-urlencoded' ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_HEADER => false,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
      $err = curl_error($ch);
      $code = curl_errno($ch);
      $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      error_log('[google_callback] cURL error getting token (verify on): errno=' . $code . ' http=' . $http . ' msg=' . $err);
      // Intento inseguro (solo desarrollo local) si falla verificación SSL
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $resp2 = curl_exec($ch);
      if ($resp2 === false) {
        $err2 = curl_error($ch);
        $http2 = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        error_log('[google_callback] cURL error (verify off - local dev): http=' . $http2 . ' msg=' . $err2);
        curl_close($ch);
        return false;
      }
      curl_close($ch);
      return $resp2;
    }
    curl_close($ch);
    return $resp;
  }
  // Fallback: streams
  $opts = ['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => $body,
    'timeout' => 15
  ]];
  $context = stream_context_create($opts);
  $resp = @file_get_contents($url, false, $context);
  if ($resp === false) {
    $hdr = isset($http_response_header) ? implode(' | ', $http_response_header) : 'no headers';
    error_log('[google_callback] stream error getting token; headers=' . $hdr);
  }
  return $resp;
}

$resp = http_post_form($tokenEndpoint, $post);
if ($resp === false){
  error_log('[google_callback] fallo al obtener tokens');
  json_redirect('login.php?error=oauth_token');
}

$tokens = json_decode($resp, true);
// Log de errores devueltos por Google para diagnóstico (sin exponer secretos)
if (isset($tokens['error'])) {
  $e = $tokens['error'];
  $ed = $tokens['error_description'] ?? '';
  error_log('[google_callback] token error: ' . $e . ' desc=' . $ed);
  // Casos típicos: invalid_client, redirect_uri_mismatch, invalid_grant
  $map = [
    'invalid_client' => 'oauth_invalid_client',
    'redirect_uri_mismatch' => 'oauth_redirect_mismatch',
    'invalid_grant' => 'oauth_invalid_grant',
  ];
  $code = $map[$e] ?? 'oauth_token_error';
  json_redirect('login.php?error=' . $code);
}
if (!isset($tokens['id_token'])){
  error_log('[google_callback] id_token ausente');
  json_redirect('login.php?error=oauth_idtoken');
}

$idToken = $tokens['id_token'];

// Validar id_token (firmas y claims básicos). Para validación completa usar libs JWT.
// Aquí validamos payload y claims esenciales; para producción idealmente verifica firma con JWKS de Google.
$parts = explode('.', $idToken);
if (count($parts) !== 3){ json_redirect('login.php?error=jwt_format'); }
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
if (!$payload){ json_redirect('login.php?error=jwt_payload'); }

// Validaciones de claims
$audOk = (isset($payload['aud']) && ($payload['aud'] === GOOGLE_CLIENT_ID || (is_array($payload['aud']) && in_array(GOOGLE_CLIENT_ID, $payload['aud']))));
$issOk = (isset($payload['iss']) && in_array($payload['iss'], [GOOGLE_ISSUER_1, GOOGLE_ISSUER_2], true));
$expOk = (isset($payload['exp']) && time() < intval($payload['exp']));
$nonceOk = (isset($_SESSION['oauth2_nonce']) && isset($payload['nonce']) && hash_equals($_SESSION['oauth2_nonce'], $payload['nonce']));
unset($_SESSION['oauth2_nonce']);

if (!$audOk || !$issOk || !$expOk || !$nonceOk){
  error_log('[google_callback] claims inválidos aud=' . ($audOk?'1':'0') . ' iss=' . ($issOk?'1':'0') . ' exp=' . ($expOk?'1':'0') . ' nonce=' . ($nonceOk?'1':'0'));
  json_redirect('login.php?error=jwt_claims');
}

// Extraer datos de perfil mínimos
$googleSub = $payload['sub'] ?? null; // ID único de Google
$email = $payload['email'] ?? null;
$emailVerified = $payload['email_verified'] ?? false;
$name = $payload['name'] ?? ($payload['given_name'] ?? '');

if (!$googleSub || !$email){
  json_redirect('login.php?error=profile_incomplete');
}

// Buscar si ya hay mapeo en autenticacion_google
error_log('[google_callback] Buscando usuario existente con google_id: ' . $googleSub);
$stmt = $conn->prepare("SELECT ag.id_usuario, u.nombre_usuario, u.rol, u.email_usuario FROM autenticacion_google ag INNER JOIN usuarios u ON ag.id_usuario = u.id_usuario WHERE ag.google_id = ? LIMIT 1");
$stmt->bind_param('s', $googleSub);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  error_log('[google_callback] Usuario existente encontrado: id=' . $row['id_usuario'] . ' rol=' . $row['rol']);
  // Usuario existente -> iniciar sesión
  $_SESSION['usuario'] = $row['nombre_usuario'];
  $_SESSION['user_id'] = $row['id_usuario'];
  $_SESSION['email'] = $row['email_usuario'];
  $_SESSION['rol'] = $row['rol'] ?? 'paciente';
  $_SESSION['last_activity'] = time();
  asociarEspSiNoExiste($row['id_usuario'], $row['nombre_usuario']);
  // Refuerzo: forzar verificación explícita de ESP (sin cache) para evitar falsos positivos
  if ($_SESSION['rol'] !== 'cuidador') {
    $espRow = obtenerEspAsignado($row['id_usuario'], true); // forceRefresh
    if (!$espRow) {
      $_SESSION['needs_esp32'] = true;
      $_SESSION['esp_info'] = null; // asegurar limpieza
      error_log('[google_callback] Usuario ' . $row['id_usuario'] . ' sin ESP -> redirigir a vincular_esp');
    } else {
      $_SESSION['needs_esp32'] = false;
      error_log('[google_callback] Usuario ' . $row['id_usuario'] . ' tiene ESP=' . ($espRow['nombre_esp'] ?? 'N/A'));
    }
  } else {
    // Cuidadores nunca requieren ESP propio
    $_SESSION['needs_esp32'] = false;
  }
  $redirect = ($row['rol'] === 'cuidador') ? 'dashboard_cuidador.php' : 'dashboard.php';
  if (!empty($_SESSION['needs_esp32'])) {
    $_SESSION['post_link_redirect'] = $redirect;
    $redirect = 'vincular_esp.php';
  }
  json_redirect($redirect);
}

// Si no existe, guardar datos en sesión y redirigir a selección de rol.
error_log('[google_callback] Usuario NO encontrado con google_id: ' . $googleSub . ' email: ' . $email . ' - Redirigiendo a selección de rol');
$_SESSION['google_pending'] = [
  'sub' => $googleSub,
  'email' => $email,
  'email_verified' => (bool)$emailVerified,
  'name' => $name
];
json_redirect('google_role_select.php');
?>