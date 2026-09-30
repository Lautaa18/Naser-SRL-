<?php
// ==========================================
// Entorno: "dev" (desarrollo) o "production"
// Se define con APP_ENV en el archivo .env
// ==========================================
function app_env(): string {
    return strtolower(trim((string)(getenv('APP_ENV') ?: 'production')));
}
function app_is_dev(): bool {
    return in_array(app_env(), ['dev', 'development', 'local'], true);
}

// En desarrollo se muestran los errores; en produccion se guardan en logs/php-errors.log
if (!defined('NASER_ERRORS_CONFIGURED')) {
    define('NASER_ERRORS_CONFIGURED', true);
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    $logDir = dirname(__DIR__, 2) . '/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php-errors.log');
    }
    if (app_is_dev()) {
        ini_set('display_errors', '1');
    } else {
        ini_set('display_errors', '0');
        set_exception_handler(function (Throwable $e): void {
            error_log('[NASER] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Error | NASER SGI</title>'
               . '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:80px auto;padding:24px;border:1px solid #dfe7e1;border-radius:14px">'
               . '<h2 style="margin-top:0;color:#164c2d">Ocurrió un error</h2>'
               . '<p>No pudimos completar la operación. Volvé atrás e intentá de nuevo. Si el problema sigue, avisale al administrador.</p></div>';
        });
    }
}

function app_base(): string {
    $base = trim((string)(getenv('APP_BASE') ?: ''));
    if ($base === '' || $base === '/') return '';
    return '/' . trim($base, '/');
}
function app_url(string $path = ''): string {
    return app_base() . '/' . ltrim($path, '/');
}
// URL de un archivo estatico (CSS/JS/imagen) con version automatica:
// cuando el archivo cambia, cambia la ?v= y el navegador descarga la version nueva.
function asset(string $path): string {
    $file = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : '1';
    return app_url($path) . '?v=' . $v;
}
function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
