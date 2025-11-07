<?php
// Logger simple con timestamp a php_error.log en el raíz de AutoPill
function app_log($message, array $context = []) {
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'php_error.log';
    $ts = date('Y-m-d H:i:s');
    if (!empty($context)) {
        $ctx = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } else {
        $ctx = '';
    }
    $line = "[$ts] $message$ctx" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
