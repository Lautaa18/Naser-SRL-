<?php
function app_base(): string {
    $base = trim((string)(getenv('APP_BASE') ?: ''));
    if ($base === '' || $base === '/') return '';
    return '/' . trim($base, '/');
}
function app_url(string $path = ''): string {
    return app_base() . '/' . ltrim($path, '/');
}
function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
