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
require_once __DIR__ . '/config/db.php';

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

// La referencia activa manda sobre la ruta: un archivo en papelera no se entrega.
$st=$pdo->prepare('SELECT d.archivo,d.sector_id,d.activo,d.eliminado_lote FROM documentos d WHERE d.archivo IS NOT NULL UNION ALL SELECT v.archivo,d.sector_id,d.activo,d.eliminado_lote FROM documento_versiones v JOIN documentos d ON d.id=v.documento_id');
$st->execute(); $referencias=[];
while($r=$st->fetch()) if(realpath($root.'/'.$r['archivo'])===$path) $referencias[]=$r;
$autorizado=false;
foreach($referencias as $r) if ((int)$r['activo'] && $r['eliminado_lote']===null && puedeDescargarSector($pdo,(int)$r['sector_id'])) $autorizado=true;
if ($referencias && !$autorizado) descargaDenegada(403,'El archivo está en papelera o no tenés permiso de descarga.');
if (!$referencias) {
    $sectorId=null;
    $primero=strtolower(explode('/',$rel)[0]);
    foreach(sectoresInfo($pdo) as $sid=>$sector) if(str_contains($rel,'/') && strtolower($sector['slug'])===$primero) $sectorId=(int)$sid;
    if ($sectorId===null ? !esAdmin() : !puedeDescargarSector($pdo,$sectorId)) descargaDenegada(403,'No tenés permiso para descargar este archivo.');
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
