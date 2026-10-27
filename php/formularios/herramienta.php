<?php
// Herramientas de trabajo de un sector: Diagrama de trabajo (RRHH) y Stock de herramientas (Mantenimiento).
// No son formularios: no hay borrador, envío ni aprobación. Es una única pantalla compartida por sector
// que se guarda sola cada vez que alguien con permiso la modifica.
require __DIR__ . '/_init.php';

$slug = (string)($_GET['sector'] ?? '');
$herr = herramientaDeSector($slug);
if (!$herr) { http_response_code(404); exit('Herramienta no encontrada.'); }
$sector = sectorPorSlug($pdo, $slug);
if (!$sector || !puedeVerSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$sid = (int)$sector['id'];
$puedeEditar = puedeCompletarFormulario($pdo, $herr['codigo'], $sid);

// Registro compartido: el último guardado de esta herramienta en el sector.
$st = $pdo->prepare('SELECT r.id, r.actualizado_en, u.nombre AS modificado_por
                     FROM formularios_registros r LEFT JOIN usuarios u ON u.id = r.actualizado_por
                     WHERE r.formulario = ? AND r.sector_id = ? ORDER BY r.actualizado_en DESC, r.id DESC LIMIT 1');
$st->execute([$herr['codigo'], $sid]);
$reg = $st->fetch() ?: null;
$id = $reg ? (int)$reg['id'] : 0;

$volver = app_url('/php/sector.php?sector=' . urlencode($slug));
$srcIframe = app_url('/php/formularios/render.php') . ($id ? '?id=' . $id : '?f=' . urlencode($herr['codigo']));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($herr['titulo'])?> | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
</head><body><div class="app"><?php sidebar($pdo, $slug); ?><main class="content">

<nav class="breadcrumbs"><a href="<?=h($volver)?>"><?=h($sector['nombre'])?></a><span>›</span><span><?=h($herr['titulo'])?></span></nav>

<section class="fx-head">
  <div class="fx-head-info">
    <p class="eyebrow"><?=h($sector['nombre'])?></p>
    <h1><?=h($herr['icono'])?> <?=h($herr['titulo'])?></h1>
    <div class="fx-meta">
      <?php if ($reg): ?>
        <span>Última modificación: <strong><?=h($reg['modificado_por'] ?? '—')?></strong>, <?=h(date('d/m/Y H:i', strtotime($reg['actualizado_en'])))?></span>
      <?php endif; ?>
      <span id="hxEstado" class="fx-meta-estado"><?= $puedeEditar ? 'Los cambios se guardan solos.' : 'Solo lectura: no tenés permiso para modificar esta pantalla.' ?></span>
    </div>
  </div>
  <div class="top-actions">
    <button type="button" class="btn secondary" id="hxImprimir">🖨 Imprimir / PDF</button>
    <a class="btn secondary" href="<?=h($volver)?>">← Volver a <?=h($sector['nombre'])?></a>
  </div>
</section>

<div id="hxMsg" class="fx-msg" role="status" aria-live="polite"></div>

<div class="fx-frame-wrap"><iframe id="hxFrame" src="<?=h($srcIframe)?>" title="<?=h($herr['titulo'])?>"></iframe></div>

</main></div>
<script>
(function(){
  var API = <?=json_encode(app_url('/php/formularios/api.php'))?>;
  var CSRF = <?=json_encode(csrf_token())?>;
  var FORM = <?=json_encode($herr['codigo'])?>;
  var id = <?=$id ?: 'null'?>;
  var version = <?=json_encode($reg ? (string)$reg['actualizado_en'] : '')?>;
  var puedeEditar = <?=$puedeEditar ? 'true' : 'false'?>;
  var frame = document.getElementById('hxFrame');
  var estado = document.getElementById('hxEstado');
  var msg = document.getElementById('hxMsg');
  var sucio = false, ocupado = false, pendiente = false, detenido = false, reqs = {}, reqN = 0;

  function ponerEstado(texto, color){ estado.textContent = texto; estado.style.color = color || ''; }
  function aviso(texto, tipo){ msg.textContent = texto; msg.className = 'fx-msg ' + (tipo || 'ok'); }

  window.addEventListener('message', function(e){
    if (e.origin !== window.location.origin || e.source !== frame.contentWindow || !e.data) return;
    var d = e.data;
    if (d.type === 'naser:height' && Number.isFinite(d.height)) frame.style.height = Math.max(300, d.height + 16) + 'px';
    else if (d.type === 'naser:dirty' && puedeEditar && !detenido) { sucio = true; ponerEstado('Guardando cambios…', '#b45309'); }
    else if (d.type === 'naser:collected' && reqs[d.reqId]) { reqs[d.reqId](d); delete reqs[d.reqId]; }
    else if (d.type === 'naser:save-request' && puedeEditar) guardar();
  });
  window.addEventListener('beforeunload', function(e){ if (sucio || ocupado) { e.preventDefault(); e.returnValue = ''; } });
  document.getElementById('hxImprimir').addEventListener('click', function(){
    frame.contentWindow.postMessage({ type: 'naser:print' }, window.location.origin);
  });

  function recolectar(){
    return new Promise(function(ok, mal){
      var n = ++reqN; reqs[n] = ok;
      frame.contentWindow.postMessage({ type: 'naser:collect', reqId: n, validate: false }, window.location.origin);
      setTimeout(function(){ if (reqs[n]) { delete reqs[n]; mal(new Error('La pantalla no respondió. Recargá la página.')); } }, 5000);
    });
  }

  async function guardar(){
    if (!puedeEditar || detenido) return;
    if (ocupado) { pendiente = true; return; }
    ocupado = true;
    try {
      var d = await recolectar();
      if (d.valid === false) { ponerEstado('Revisá los datos indicados: todavía no se guardó.', '#9a251d'); return; }
      var r = await fetch(API, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ accion: 'herramienta_guardar', formulario: FORM, id: id, base: version, datos: d.fields, storage: d.storage })
      });
      var j = await r.json().catch(function(){ throw new Error('Error del servidor (' + r.status + ').'); });
      if (!j.ok) {
        if (r.status === 409) detenido = true;
        throw new Error(j.error || 'No se pudo guardar.');
      }
      id = j.id; version = j.version;
      if (!pendiente) sucio = false;
      var h = new Date();
      ponerEstado('✔ Guardado a las ' + ('0' + h.getHours()).slice(-2) + ':' + ('0' + h.getMinutes()).slice(-2), '#17683c');
      msg.className = 'fx-msg';
    } catch (err) {
      ponerEstado('No se pudo guardar.', '#9a251d');
      aviso(err.message + (detenido ? ' Tus últimos cambios no se guardaron.' : ''), 'err');
      if (detenido) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn secondary'; b.style.marginLeft = '12px'; b.textContent = 'Recargar';
        b.addEventListener('click', function(){ sucio = false; window.location.reload(); });
        msg.appendChild(b);
      }
    } finally {
      ocupado = false;
      if (pendiente && !detenido) { pendiente = false; setTimeout(guardar, 50); }
    }
  }
})();
</script>
</body></html>
