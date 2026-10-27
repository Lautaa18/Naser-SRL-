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

$where = ['r.eliminado_lote IS NULL', sqlSinHerramientas()]; $params = [];
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
$puedeExportar = $sector ? puedeDescargarSector($pdo, (int)$sector['id']) : (bool)array_filter($visibles, fn($s) => puedeDescargarSector($pdo, (int)$s['id']));
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
  <?php if ($puedeExportar): ?><a class="btn secondary" href="<?=h(app_url('/php/formularios/exportar.php') . $qs([]))?>">⬇ Exportar a Excel (CSV)</a><?php endif; ?>
</div></header>

<nav class="module-tabs">
  <a href="?<?=h(http_build_query(['vista' => 'aprobar']))?>" class="<?= $vista === 'aprobar' ? 'fx-tab-on' : '' ?>">✔ Pendientes de mi aprobación</a>
  <a href="?<?=h(http_build_query(['vista' => 'mios']))?>" class="<?= $vista === 'mios' ? 'fx-tab-on' : '' ?>">👤 Cargados por mí</a>
  <a href="?" class="<?= !$sector && !$vista ? 'fx-tab-on' : '' ?>">Todos</a>
  <?php foreach ($visibles as $s): if (!formulariosDeSector($s['slug'])) continue; ?>
    <a href="?sector=<?=h(urlencode($s['slug']))?>" class="<?= $sector && $sector['slug'] === $s['slug'] && !$vista ? 'fx-tab-on' : '' ?>"><?=h($s['nombre'])?></a>
  <?php endforeach; ?>
</nav>

<section class="form-panel" style="margin:0 0 18px" id="fxImportar">
  <h2>Trabajo en el campo (sin conexión)</h2>
  <p class="muted">1) Entrá a un sector y tocá <strong>⬇ Sin conexión</strong> en el formulario que vayas a usar: se baja un archivo que se abre sin internet. 2) Completalo en el campo y tocá <strong>Guardar archivo para subir</strong>. 3) Cuando tengas wifi, subí acá los archivos guardados: quedan como borrador para revisar y enviar.</p>
  <label class="btn secondary" style="cursor:pointer;display:inline-block">⬆ Importar archivos del campo<input type="file" id="fxImpArchivos" accept=".json,application/json" multiple style="display:none"></label>
  <ul id="fxImpResultado" style="margin:12px 0 0;padding-left:18px"></ul>
</section>
<script>
(function(){
  var inp=document.getElementById('fxImpArchivos'), out=document.getElementById('fxImpResultado'), tok=<?=json_encode(csrf_token())?>, base=<?=json_encode(app_url('/php/formularios/api.php'))?>;
  function li(html,ok){var e=document.createElement('li');e.innerHTML=html;e.style.color=ok?'':'#a12626';out.appendChild(e);}
  function esc(t){var d=document.createElement('div');d.textContent=t==null?'':t;return d.innerHTML;}
  inp.addEventListener('change',function(){
    out.innerHTML='';
    Array.prototype.forEach.call(inp.files,function(f){
      var rd=new FileReader();
      rd.onload=function(){
        var arc; try{arc=JSON.parse(rd.result);}catch(e){li(esc(f.name)+': no se pudo leer el archivo.',false);return;}
        fetch(base,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':tok},body:JSON.stringify({accion:'importar',archivo:arc})})
          .then(function(r){return r.json().catch(function(){return {ok:false,error:'Respuesta inesperada (¿venció la sesión? Recargá e ingresá de nuevo).'};});})
          .then(function(j){
            if(!j.ok){li(esc(f.name)+': '+esc(j.error),false);return;}
            var link=j.url?j.url:(base.replace('api.php','llenar.php')+'?id='+j.id);
            li(esc(f.name)+' → '+esc(j.titulo)+(j.referencia?' ('+esc(j.referencia)+')':'')+': '+(j.repetido?'ya estaba importado. ':'importado como borrador. ')+'<a href="'+esc(link)+'">Abrir</a>',true);
          }).catch(function(){li(esc(f.name)+': no hay conexión con el sistema.',false);});
      };
      rd.readAsText(f);
    });
    inp.value='';
  });
})();
</script>

<?php if ($sector && $formsSector): ?>
  <div class="section-head"><div><h2>Completar un formulario</h2><p><?= $puedeCompletar ? 'Elegí el formulario que querés cargar.' : (array_filter($formsSector, fn($f) => puedeCompletarFormulario($pdo, $f['codigo'], (int)$sector['id'])) ? 'Podés completar los formularios que tienen el botón Completar; el resto solo se puede ver.' : 'Podés ver los registros, pero no completar formularios de este sector.') ?></p></div></div>
  <div class="grid-forms">
    <?php foreach ($formsSector as $f): ?>
      <div class="card-form">
        <div><?php if ($f['codigo_sgi']): ?><span class="fx-code"><?=h($f['codigo_sgi'])?></span><?php endif; ?><h3><?=h($f['titulo'])?></h3></div>
        <div class="fx-card-actions">
          <?php if (puedeCompletarFormulario($pdo, $f['codigo'], (int)$sector['id'])): ?><a class="btn primary small" href="<?=h(app_url('/php/formularios/llenar.php') . '?f=' . urlencode($f['codigo']))?>">Completar</a><?php endif; ?>
          <?php if (puedeCompletarFormulario($pdo, $f['codigo'], (int)$sector['id']) && puedeDescargarSector($pdo, (int)$sector['id'])): ?><a class="btn secondary small" title="Descargar para completar sin internet y subir después" href="<?=h(app_url('/php/formularios/offline.php') . '?f=' . urlencode($f['codigo']))?>">⬇ Sin conexión</a><?php endif; ?>
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
    <?php foreach ($cat as $c): if (esHerramientaDeTrabajo($c['codigo'])) continue; if ($sector && $c['sector'] !== $sector['slug']) continue; ?>
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
      <tr><td colspan="<?= $sector ? 8 : 9 ?>" class="muted" style="text-align:center;padding:22px">No hay formularios cargados con estos filtros.</td></tr>
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
