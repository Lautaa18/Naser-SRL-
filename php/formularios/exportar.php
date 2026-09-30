<?php
// Exporta los formularios a CSV (se abre directo con Excel)
require __DIR__ . '/_init.php';

$slug = (string)($_GET['sector'] ?? '');
$sector = $slug !== '' ? sectorPorSlug($pdo, $slug) : null;
if ($slug !== '' && (!$sector || !puedeVerSector($pdo, (int)$sector['id']))) { http_response_code(403); exit('Sin acceso.'); }
$visiblesIds = array_map(fn($s) => (int)$s['id'], sectoresVisibles($pdo));
$uid = (int)$_SESSION['usuario_id'];

$where = []; $params = [];
if ($sector) { $where[] = 'r.sector_id = ?'; $params[] = (int)$sector['id']; }
else { $where[] = $visiblesIds ? 'r.sector_id IN (' . implode(',', $visiblesIds) . ')' : '0'; }
if (($f = (string)($_GET['f'] ?? '')) !== '') { $where[] = 'r.formulario = ?'; $params[] = $f; }
if (in_array($e = (string)($_GET['estado'] ?? ''), ['borrador', 'enviado', 'aprobado', 'rechazado'], true)) { $where[] = 'r.estado = ?'; $params[] = $e; }
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $where[] = 'r.referencia LIKE ?'; $params[] = "%$q%"; }
if (($_GET['vista'] ?? '') === 'mios') { $where[] = 'r.creado_por = ?'; $params[] = $uid; }
if (!esAdmin()) {
    $gest = array_values(array_filter($visiblesIds, fn($id) => puedeEditarSector($pdo, $id)));
    $where[] = "(r.estado <> 'borrador' OR r.creado_por = $uid" . ($gest ? ' OR r.sector_id IN (' . implode(',', $gest) . ')' : '') . ')';
}
$st = $pdo->prepare('SELECT r.*, a.nombre AS autor, v.nombre AS revisor FROM formularios_registros r
                     LEFT JOIN usuarios a ON a.id = r.creado_por LEFT JOIN usuarios v ON v.id = r.revisado_por
                     WHERE ' . implode(' AND ', $where) . ' ORDER BY r.formulario, r.id');
$st->execute($params);
$rows = $st->fetchAll();
$cat = formulariosCatalogo();
$info = sectoresInfo($pdo);

// Columnas de campos: union de todas las claves (se quita el # del id)
$campos = [];
foreach ($rows as &$r) {
    $r['_d'] = json_decode((string)$r['datos_json'], true) ?: [];
    foreach ($r['_d'] as $k => $v) $campos[$k] = true;
}
unset($r);
// Los campos de cada formulario solo se exportan cuando se filtra por un formulario
// (si se mezclan formularios distintos, se exportan solo los datos generales)
$campos = ($_GET['f'] ?? '') !== '' ? array_keys($campos) : [];

registrarActividad($pdo, 'exportar_formularios', count($rows) . ' registros');
$nombre = 'formularios_' . ($slug ?: 'todos') . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
$sep = ';';
$cab = ['N°', 'Formulario', 'Código SGI', 'Sector', 'Referencia', 'Estado', 'Cargado por', 'Creado', 'Enviado', 'Revisado por', 'Revisado', 'Comentario revisión'];
foreach ($campos as $c) { $l = ltrim(preg_replace('/^(radio:|@|~)/', '', $c), '#'); $cab[] = ctype_digit(explode('|', $l)[0]) ? 'campo ' . $l : $l; }
fputcsv($out, $cab, $sep, '"', '\\');
foreach ($rows as $r) {
    $f = $cat[$r['formulario']] ?? null;
    $fila = [$r['id'], $f['titulo'] ?? $r['formulario'], $f['codigo_sgi'] ?? '', $info[(int)$r['sector_id']]['nombre'] ?? '', $r['referencia'], estadoFormularioLabel($r['estado']),
             $r['autor'], $r['creado_en'], $r['enviado_en'], $r['revisor'], $r['revisado_en'], $r['comentario_revision']];
    foreach ($campos as $c) {
        $v = $r['_d'][$c] ?? '';
        if (is_bool($v)) $v = $v ? 'Sí' : '';
        elseif (is_array($v)) $v = implode(', ', $v);
        $fila[] = $v;
    }
    // Evita que Excel interprete textos como formulas (=, +, -, @)
    $fila = array_map(fn($x) => (is_string($x) && $x !== '' && strpbrk($x[0], '=+-@') !== false) ? "'" . $x : $x, $fila);
    fputcsv($out, $fila, $sep, '"', '\\');
}
fclose($out);
