<?php
/**
 * Entrega protegida de archivos de /uploads.
 * Apache redirige aca todo pedido a /uploads/... (ver uploads/.htaccess).
 *  - Hay que estar logueado.
 *  - Documentos del SGI: los ve cualquier usuario logueado.
 *  - Resto de los sectores: solo quien puede ver ese sector (Operaciones es restringido).
 *  - Archivo que no se puede asociar a un sector: solo el super usuario.
 */
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';

function descargaDenegada(int $code, string $msg): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($msg);
}

$root = realpath(dirname(__DIR__) . '/uploads');
$rel  = str_replace('\\', '/', (string)($_GET['f'] ?? ''));
$rel  = ltrim($rel, '/');
if ($root === false || $rel === '' || str_contains($rel, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $rel)) {
    descargaDenegada(404, 'Archivo no encontrado.');
}
$path = realpath($root . '/' . $rel);
if ($path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
    descargaDenegada(404, 'Archivo no encontrado.');
}
$base = basename($path);
if ($base === '.htaccess' || preg_match('/\.(php|phtml|phar|cgi|pl|py|sh)$/i', $base)) {
    descargaDenegada(404, 'Archivo no encontrado.');
}

// ¿A que sector pertenece? Primero por la carpeta (uploads/<sector>/...), si no por la tabla documentos.
$sectorId = null;
$slugs = [];
foreach (sectoresInfo($pdo) as $id => $s) $slugs[strtolower((string)$s['slug'])] = (int)$id;
$primero = strtolower(explode('/', $rel)[0]);
if (str_contains($rel, '/') && isset($slugs[$primero])) {
    $sectorId = $slugs[$primero];
} else {
    $st = $pdo->prepare('SELECT sector_id FROM documentos WHERE archivo = ? OR archivo = ? LIMIT 1');
    $st->execute([$rel, $base]);
    $v = $st->fetchColumn();
    if ($v !== false && $v !== null) $sectorId = (int)$v;
}

if ($sectorId === null) {
    if (!esAdmin()) descargaDenegada(403, 'No tenés permiso para ver este archivo.');
} elseif (!puedeVerSector($pdo, $sectorId)) {   // SGI siempre visible para usuarios logueados
    descargaDenegada(403, 'No tenés permiso para ver este archivo.');
}

$mimes = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
];
$ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
$mime = $mimes[$ext] ?? 'application/octet-stream';
$inline = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true);

session_write_close();
while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($base));
readfile($path);
exit;
