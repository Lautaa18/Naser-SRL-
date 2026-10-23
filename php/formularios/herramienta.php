<?php
// Entrada directa a las herramientas de trabajo de un sector
// (Diagrama de trabajo en RRHH, Stock de herramientas en Mantenimiento).
// Abre el último registro guardado; si todavía no hay ninguno, abre uno nuevo.
require __DIR__ . '/_init.php';

$slug = (string)($_GET['sector'] ?? '');
$herr = herramientaDeSector($slug);
if (!$herr) { http_response_code(404); exit('Herramienta no encontrada.'); }
$sector = sectorPorSlug($pdo, $slug);
if (!$sector || !puedeVerSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$sid = (int)$sector['id'];
$llenar = app_url('/php/formularios/llenar.php');

$where = 'formulario = ? AND sector_id = ?';
$params = [$herr['codigo'], $sid];
if (!esAdmin() && !puedeEditarSector($pdo, $sid)) {
    // Los borradores de otras personas no se ven (igual que en el resto de los formularios).
    $where .= " AND (estado <> 'borrador' OR creado_por = ?)";
    $params[] = (int)($_SESSION['usuario_id'] ?? 0);
}
$st = $pdo->prepare("SELECT id FROM formularios_registros WHERE $where ORDER BY actualizado_en DESC, id DESC LIMIT 1");
$st->execute($params);
$id = (int)$st->fetchColumn();

if ($id) { header('Location: ' . $llenar . '?id=' . $id); exit; }
if (puedeCompletarFormulario($pdo, $herr['codigo'], $sid)) { header('Location: ' . $llenar . '?f=' . urlencode($herr['codigo'])); exit; }

$volver = app_url('/php/sector.php?sector=' . urlencode($slug));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($herr['titulo'])?> | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
</head><body><div class="app"><?php sidebar($pdo, $slug); ?><main class="content">
<nav class="breadcrumbs"><a href="<?=h($volver)?>"><?=h($sector['nombre'])?></a><span>›</span><span><?=h($herr['titulo'])?></span></nav>
<section class="module-card">
  <h1><?=h($herr['titulo'])?></h1>
  <p class="muted">Todavía no se cargó nada y tu usuario no tiene permiso para crear el primer registro.</p>
  <a class="btn secondary" href="<?=h($volver)?>">Volver a <?=h($sector['nombre'])?></a>
</section>
</main></div></body></html>
