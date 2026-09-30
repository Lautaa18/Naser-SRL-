<?php
// Listado de formularios: catalogo por sector + registros guardados con filtros
require __DIR__ . '/_init.php';

$visibles = sectoresVisibles($pdo);
$visiblesIds = array_map(fn($s) => (int)$s['id'], $visibles);
$slug = (string)($_GET['sector'] ?? '');
$sector = $slug !== '' ? sectorPorSlug($pdo, $slug) : null;
if ($slug !== '' && (!$sector || !puedeVerSector($pdo, (int)$sector['id']))) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$fFiltro = (string)($_GET['f'] ?? '');
$estado = (string)($_GET['estado'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$vista = (string)($_GET['vista'] ?? ''); // 'aprobar' | 'mios'
$uid = (int)$_SESSION['usuario_id'];

$where = []; $params = [];
if ($sector) { $where[] = 'r.sector_id = ?'; $params[] = (int)$sector['id']; }
else { $where[] = $visiblesIds ? 'r.sector_id IN (' . implode(',', $visiblesIds) . ')' : '0'; }
if ($fFiltro !== '') { $where[] = 'r.formulario = ?'; $params[] = $fFiltro; }
if (in_array($estado, ['borrador', 'enviado', 'aprobado', 'rechazado'], true)) { $where[] = 'r.estado = ?'; $params[] = $estado; }
if ($q !== '') { $where[] = '(r.referencia LIKE ? OR r.id = ?)'; $params[] = '%' . $q . '%'; $params[] = (int)$q; }
if ($vista === 'mios') { $where[] = 'r.creado_por = ?'; $params[] = $uid; }
if ($vista === 'aprobar') {
    $aprobables = array_values(array_filter($visiblesIds, fn($id) => puedeAprobarSector($pdo, $id)));
    $where[] = "r.estado = 'enviado'";
    $where[] = $aprobables ? 'r.sector_id IN (' . implode(',', $aprobables) . ')' : '0';
}
// Los borradores solo los ve su autor o quien puede gestionar el sector
if (!esAdmin()) {
    $gestionables = array_values(array_filter($visiblesIds, fn($id) => puedeEditarSector($pdo, $id)));
    $where[] = "(r.estado <> 'borrador' OR r.creado_por = " . $uid . ($gestionables ? ' OR r.sector_id IN (' . implode(',', $gestionables) . ')' : '') . ')';
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$st = $pdo->prepare("SELECT r.id, r.formulario, r.sector_id, r.referencia, r.estado, r.creado_en, r.actualizado_en, r.enviado_en, r.revisado_en,
                            a.nombre AS autor, v.nombre AS revisor
                     FROM formularios_registros r LEFT JOIN usuarios a ON a.id = r.creado_por LEFT JOIN usuarios v ON v.id = r.revisado_por
                     $sqlWhere ORDER BY r.actualizado_en DESC LIMIT 300");
$st->execute($params);
$registros = $st->fetchAll();
$cat = formulariosCatalogo();
$info = sectoresInfo($pdo);
$formsSector = $sector ? formulariosDeSector($sector['slug']) : [];
$puedeCompletar = $sector ? puedeCompletarSector($pdo, (int)$sector['id']) : false;
$qs = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(['sector' => $slug, 'f' => $fFiltro, 'estado' => $estado, 'q' => $q, 'vista' => $vista], $extra), fn($v) => $v !== '' && $v !== null));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Formularios<?= $sector ? ' ' . h($sector['nombre']) : '' ?> | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
<link rel="stylesheet" href="<?=asset('/css/modules.css')?>">
<link rel="stylesheet" href="<?=asset('/css/checklists.css')?>">
</head><body><div class="app"><?php sidebar($pdo, $sector ? $sector['slug'] : 'formularios'); ?><main class="content">

<header class="topbar"><div>
  <p class="eyebrow">FORMULARIOS DIGITALES</p>
  <h1><?= $sector ? h($sector['nombre']) : 'Todos los formularios' ?></h1>
  <p>Los formularios se guardan en la base de datos. Al enviarlos, el responsable del área recibe un aviso para aprobarlos.</p>
</div>
<div class="top-actions">
  <a class="btn secondary" href="<?=h(app_url('/php/formularios/exportar.php') . $qs([]))?>">⬇ Exportar a Excel (CSV)</a>
</div></header>

<nav class="module-tabs">
  <a href="?<?=h(http_build_query(['vista' => 'aprobar']))?>" class="<?= $vista === 'aprobar' ? 'fx-tab-on' : '' ?>">✔ Pendientes de mi aprobación</a>
  <a href="?<?=h(http_build_query(['vista' => 'mios']))?>" class="<?= $vista === 'mios' ? 'fx-tab-on' : '' ?>">👤 Cargados por mí</a>
  <a href="?" class="<?= !$sector && !$vista ? 'fx-tab-on' : '' ?>">Todos</a>
  <?php foreach ($visibles as $s): if (!formulariosDeSector($s['slug'])) continue; ?>
    <a href="?sector=<?=h(urlencode($s['slug']))?>" class="<?= $sector && $sector['slug'] === $s['slug'] && !$vista ? 'fx-tab-on' : '' ?>"><?=h($s['nombre'])?></a>
  <?php endforeach; ?>
</nav>

<?php if ($sector && $formsSector): ?>
  <div class="section-head"><div><h2>Completar un formulario</h2><p><?= $puedeCompletar ? 'Elegí el formulario que querés cargar.' : 'Podés ver los registros, pero no completar formularios de este sector.' ?></p></div></div>
  <div class="grid-forms">
    <?php foreach ($formsSector as $f): ?>
      <div class="card-form">
        <div><?php if ($f['codigo_sgi']): ?><span class="fx-code"><?=h($f['codigo_sgi'])?></span><?php endif; ?><h3><?=h($f['titulo'])?></h3></div>
        <div class="fx-card-actions">
          <?php if ($puedeCompletar): ?><a class="btn primary small" href="<?=h(app_url('/php/formularios/llenar.php') . '?f=' . urlencode($f['codigo']))?>">Completar</a><?php endif; ?>
          <a class="btn secondary small" href="<?=h($qs(['f' => $f['codigo']]))?>">Registros</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form class="search-bar" method="get">
  <?php if ($slug): ?><input type="hidden" name="sector" value="<?=h($slug)?>"><?php endif; ?>
  <?php if ($vista): ?><input type="hidden" name="vista" value="<?=h($vista)?>"><?php endif; ?>
  <label>Buscar<input name="q" value="<?=h($q)?>" placeholder="Referencia o número"></label>
  <label>Formulario<select name="f"><option value="">Todos</option>
    <?php foreach ($cat as $c): if ($sector && $c['sector'] !== $sector['slug']) continue; ?>
      <option value="<?=h($c['codigo'])?>" <?= $fFiltro === $c['codigo'] ? 'selected' : '' ?>><?=h($c['titulo'])?></option>
    <?php endforeach; ?></select></label>
  <label>Estado<select name="estado"><option value="">Todos</option>
    <?php foreach (['borrador', 'enviado', 'aprobado', 'rechazado'] as $e): ?><option value="<?=$e?>" <?= $estado === $e ? 'selected' : '' ?>><?=h(estadoFormularioLabel($e))?></option><?php endforeach; ?>
  </select></label>
  <button class="btn primary">Filtrar</button>
</form>

<div class="table-wrapper">
  <table class="module-table">
    <thead><tr><th>#</th><th>Formulario</th><?php if (!$sector): ?><th>Sector</th><?php endif; ?><th>Referencia</th><th>Estado</th><th>Cargado por</th><th>Actualizado</th><th>Revisión</th><th></th></tr></thead>
    <tbody>
    <?php if (!$registros): ?>
      <tr><td colspan="9" class="muted" style="text-align:center;padding:22px">No hay formularios cargados con estos filtros.</td></tr>
    <?php endif; ?>
    <?php foreach ($registros as $r): $f = $cat[$r['formulario']] ?? null; ?>
      <tr>
        <td><strong>#<?=(int)$r['id']?></strong></td>
        <td><?=h($f['titulo'] ?? $r['formulario'])?><?php if (!empty($f['codigo_sgi'])): ?><small><?=h($f['codigo_sgi'])?></small><?php endif; ?></td>
        <?php if (!$sector): ?><td><?=h($info[(int)$r['sector_id']]['nombre'] ?? '')?></td><?php endif; ?>
        <td><?=h($r['referencia'] ?: '—')?></td>
        <td><span class="fx-estado fx-<?=h($r['estado'])?>"><?=h(estadoFormularioLabel($r['estado']))?></span></td>
        <td><?=h($r['autor'] ?? '—')?></td>
        <td><?=h(date('d/m/Y H:i', strtotime($r['actualizado_en'])))?></td>
        <td><?= $r['revisor'] ? h($r['revisor']) . '<small>' . h(date('d/m/Y', strtotime($r['revisado_en']))) . '</small>' : '—' ?></td>
        <td><a class="btn secondary small" href="<?=h(app_url('/php/formularios/llenar.php') . '?id=' . (int)$r['id'])?>"><?= $r['estado'] === 'enviado' && puedeAprobarSector($pdo, (int)$r['sector_id']) ? 'Revisar' : 'Abrir' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</main></div></body></html>
