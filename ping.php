<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
if (function_exists('ob_get_length') && ob_get_length() > 0) { @ob_clean(); }
echo json_encode([
    'ok' => true,
    'ts' => date('c'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'path' => $_SERVER['SCRIPT_NAME'] ?? '',
]);
