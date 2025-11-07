<?php
// Rellena con tus credenciales reales de Google OAuth.
// Conseguir en Google Cloud > APIs & Services > Credentials > OAuth 2.0 Client IDs (tipo: Web application)

define('GOOGLE_CLIENT_ID', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// Asegúrate de registrar esta URI EXACTA en Google Cloud (Authorized redirect URIs)
define('GOOGLE_REDIRECT_URI', 'https://pastillero.webhop.net/google_callback.php');

?>