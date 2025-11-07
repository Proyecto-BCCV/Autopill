<?php
require_once 'session_init.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once 'google_oauth_config.php';
// Verificación rápida de configuración para evitar errores 'invalid_client'.
$cfg = function_exists('google_oauth_config_check') ? google_oauth_config_check() : ['ok' => true];
if (!$cfg['ok']) {
  error_log('[google_login] Config OAuth inválida: ' . json_encode($cfg['issues'], JSON_UNESCAPED_UNICODE));
  header('Location: login.php?error=oauth_config');
  exit;
}

// Generar state y nonce seguros para mitigar CSRF y replay
function random_token($len = 32){
  try { return bin2hex(random_bytes($len)); } catch(Exception $e){ return bin2hex(openssl_random_pseudo_bytes($len)); }
}

$state = random_token(16);
$nonce = random_token(16);
$_SESSION['oauth2_state'] = $state;
$_SESSION['oauth2_nonce'] = $nonce;

$params = [
  'client_id' => GOOGLE_CLIENT_ID,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'response_type' => 'code',
  'scope' => GOOGLE_OAUTH_SCOPE,
  'state' => $state,
  'nonce' => $nonce,
  'prompt' => GOOGLE_OAUTH_PROMPT,
  'access_type' => 'offline',
  'include_granted_scopes' => 'true'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
?>