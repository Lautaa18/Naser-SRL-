<?php
// Entrada propia de Diagrama y Stock: comparten el guardado seguro, sin circuito de aprobación.
require_once __DIR__ . '/_init.php';
$slug = (string)($_GET['sector'] ?? '');
$herr = herramientaDeSector($slug);
$sector = $herr ? sectorPorSlug($pdo, $slug) : null;
if (!$herr || !$sector) { http_response_code(404); exit('Herramienta no encontrada.'); }
if (!puedeVerSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$st = $pdo->prepare('SELECT id FROM formularios_registros WHERE formulario=? AND sector_id=? AND eliminado_lote IS NULL ORDER BY actualizado_en DESC,id DESC LIMIT 1');
$st->execute([$herr['codigo'], (int)$sector['id']]);
$_GET['id'] = (int)($st->fetchColumn() ?: 0);
$_GET['f'] = $herr['codigo'];
define('NASER_HERRAMIENTA_DIRECTA', true);
require __DIR__ . '/llenar.php';
