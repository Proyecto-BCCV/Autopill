<?php
// Configuración de OAuth de Google
// 1) Crea un proyecto en Google Cloud Console
// 2) Habilita "Google Identity Services" (OAuth 2.0)
// 3) Crea credenciales OAuth (tipo "Aplicación web")
// 4) Configura URIs de redirección autorizadas. En producción, por ejemplo:
//    https://pastillero.webhop.net/google_callback.php
// 5) Coloca aquí tu CLIENT_ID y CLIENT_SECRET

// IMPORTANTE: No subas tus credenciales a un repositorio público.
// En producción, usa variables de entorno o un vault seguro.
// 0) Si existe un archivo local con secretos, cárgalo primero para overrides seguros
//    Crea un archivo google_oauth_secrets.php con defines de CLIENT_ID/SECRET/REDIRECT_URI.
//    Este archivo NO debe subirse a control de versiones.
@include_once __DIR__ . '/google_oauth_secrets.php';

// 1) Valores por entorno o placeholders si aún no están definidos por secrets.php
if (!defined('GOOGLE_CLIENT_ID')) {
	define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID.apps.googleusercontent.com');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
	define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_CLIENT_SECRET');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
	/* =============================================================
	 OPCION MANUAL DE REDIRECCION (elige UNA y descomenta):

	  A) LOCAL (desarrollo):
	     define('GOOGLE_REDIRECT_URI', 'http://localhost/proyecto/google_callback.php');

	  B) PRODUCCION (webhop):
	     define('GOOGLE_REDIRECT_URI', 'https://pastillero.webhop.net/google_callback.php');

	  Si dejas ambas comentadas, se usará:
	    1. Variable de entorno GOOGLE_REDIRECT_URI (si existe)
	    2. Autodetección dinámica según host actual

	  NOTA: Un archivo opcional google_oauth_secrets.php puede definir
	        GOOGLE_REDIRECT_URI y tendrá prioridad sobre este bloque.
	============================================================= */

	// define('GOOGLE_REDIRECT_URI', 'http://localhost/proyecto/google_callback.php'); // <- LOCAL (comentado)
	define('GOOGLE_REDIRECT_URI', 'https://pastillero.webhop.net/google_callback.php'); // <- PRODUCCIÓN (activado)

	if (!defined('GOOGLE_REDIRECT_URI')) {
		// Redirección dinámica: si hay variable de entorno úsala; si no, autodetectar host actual.
		$envRedirect = getenv('GOOGLE_REDIRECT_URI');
		if ($envRedirect) {
			define('GOOGLE_REDIRECT_URI', $envRedirect);
		} else {
			// Autodetectar (local vs producción).
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
			// Intentar localizar la raíz del proyecto (buscar google_callback.php en la ruta actual)
			$callbackPath = '/google_callback.php';
			if (!file_exists(__DIR__ . '/google_callback.php')) {
				// fallback: asume que estamos en subcarpeta del proyecto
				$callbackPath = '/proyecto/google_callback.php';
			}
			$dynamic = $scheme . '://' . $host . $callbackPath;
			define('GOOGLE_REDIRECT_URI', $dynamic);
			if (isset($_GET['debug_oauth'])) {
				error_log('[google_oauth_config] redirect autodetectado=' . $dynamic);
			}
		}
	}
}

// Scopes mínimos para login (OpenID Connect)
if (!defined('GOOGLE_OAUTH_SCOPE')) {
	define('GOOGLE_OAUTH_SCOPE', 'openid email profile');
}

// Emisor válido para tokens de Google
if (!defined('GOOGLE_ISSUER_1')) define('GOOGLE_ISSUER_1', 'https://accounts.google.com');
if (!defined('GOOGLE_ISSUER_2')) define('GOOGLE_ISSUER_2', 'accounts.google.com');

// Opcional: forzar prompt
if (!defined('GOOGLE_OAUTH_PROMPT')) define('GOOGLE_OAUTH_PROMPT', 'select_account'); // o 'consent' si deseas forzar consentimiento cada vez

// Utilidad: validación rápida de configuración (sin exponer secretos)
if (!function_exists('google_oauth_config_check')) {
	function google_oauth_config_check(): array {
		$id = GOOGLE_CLIENT_ID;
		$secret = GOOGLE_CLIENT_SECRET;
		$redirect = GOOGLE_REDIRECT_URI;
		$issues = [];
		if (!$id || $id === 'TU_CLIENT_ID') $issues[] = 'CLIENT_ID no configurado';
	if (!$secret || in_array($secret, ['TU_CLIENT_SECRET','REEMPLAZAR_CLIENT_SECRET'], true)) $issues[] = 'CLIENT_SECRET no configurado';
		if (!$redirect) $issues[] = 'REDIRECT_URI vacío';
		// Validación básica de esquema/host
		$p = @parse_url($redirect);
		if (!$p || !isset($p['scheme']) || !isset($p['host'])) {
			$issues[] = 'REDIRECT_URI inválido';
		}
		return [
			'ok' => empty($issues),
			'issues' => $issues,
			'client_id_masked' => $id ? substr($id, 0, 6) . '…' : null,
			'redirect_uri' => $redirect,
		];
	}
}

?>
