<?php
// Completar / ver / aprobar un formulario digital
require __DIR__ . '/_init.php';

$id = (int)($_GET['id'] ?? 0);
$reg = null;
if ($id) {
    $reg = cargarRegistro($pdo, $id);
    if (!$reg) { http_response_code(404); exit('Registro no encontrado.'); }
    $form = formularioPorCodigo($reg['formulario']);
} else {
    $form = formularioPorCodigo((string)($_GET['f'] ?? ''));
}
if (!$form) { http_response_code(404); exit('Formulario no encontrado.'); }
$sector = sectorPorSlug($pdo, $form['sector']);
if (!$sector || !puedeVerSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$sid = (int)$sector['id'];

if ($reg && $reg['estado'] === 'borrador' && (int)$reg['creado_por'] !== (int)$_SESSION['usuario_id'] && !puedeEditarSector($pdo, (int)$reg['sector_id'])) {
    http_response_code(403); exit('Este formulario es un borrador de otra persona.');
}
if ($reg) {
    $perm = permisosRegistro($pdo, $reg);
} else {
    if (!puedeCompletarSector($pdo, $sid)) { http_response_code(403); exit('No tenés permiso para completar formularios de este sector.'); }
    $perm = ['ver' => true, 'editar' => true, 'enviar' => true, 'aprobar' => false, 'reabrir' => false, 'eliminar' => false];
}
$estado = $reg['estado'] ?? 'nuevo';
$historial = [];
if ($reg) {
    $st = $pdo->prepare('SELECT h.*, u.nombre FROM formularios_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.registro_id = ? ORDER BY h.id DESC');
    $st->execute([$id]);
    $historial = $st->fetchAll();
}
$srcIframe = app_url('/php/formularios/render.php') . ($reg ? '?id=' . $id : '?f=' . urlencode($form['codigo']));
$hub = app_url('/php/formularios/index.php') . '?sector=' . urlencode($sector['slug']);
$accionesHist = ['creado' => 'Creó el formulario', 'guardado' => 'Guardó cambios', 'enviado' => 'Envió para aprobación', 'aprobado' => 'Aprobó', 'rechazado' => 'Rechazó', 'reabierto' => 'Reabrió para edición'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($form['titulo'])?> | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
</head><body><div class="app"><?php sidebar($pdo, $sector['slug']); ?><main class="content">

<nav class="breadcrumbs"><a href="<?=h($hub)?>">Formularios · <?=h($sector['nombre'])?></a><span>›</span><span><?=h($form['titulo'])?></span></nav>

<section class="fx-head">
  <div class="fx-head-info">
    <p class="eyebrow"><?=h($sector['nombre'])?><?= $form['codigo_sgi'] ? ' · ' . h($form['codigo_sgi']) : '' ?></p>
    <h1><?=h($form['titulo'])?> <?php if ($reg): ?><small>#<?=$id?></small><?php endif; ?></h1>
    <div class="fx-meta">
      <span class="fx-estado fx-<?=h($estado)?>" id="fxEstado"><?=h($reg ? estadoFormularioLabel($estado) : 'Nuevo (sin guardar)')?></span>
      <?php if ($reg): ?>
        <span>Cargado por <strong><?=h($reg['autor'] ?? '—')?></strong> el <?=h(date('d/m/Y H:i', strtotime($reg['creado_en'])))?></span>
        <?php if ($reg['revisor']): ?><span><?= $estado === 'aprobado' ? 'Aprobado' : 'Revisado' ?> por <strong><?=h($reg['revisor'])?></strong> el <?=h(date('d/m/Y H:i', strtotime($reg['revisado_en'])))?></span><?php endif; ?>
      <?php endif; ?>
      <span id="fxDirty" class="fx-dirty" hidden>● Cambios sin guardar</span>
    </div>
  </div>
  <label class="fx-ref">Referencia <small>(trabajador, proveedor, pozo…)</small>
    <input id="fxReferencia" maxlength="200" value="<?=h($reg['referencia'] ?? '')?>" placeholder="Se completa sola al guardar" <?= $perm['editar'] ? '' : 'disabled' ?>>
  </label>
</section>

<?php if ($reg && $reg['estado'] === 'rechazado' && $reg['comentario_revision']): ?>
  <div class="alert error"><strong>Rechazado por <?=h($reg['revisor'] ?? 'el responsable')?>:</strong> <?=h($reg['comentario_revision'])?> — Corregí el formulario y volvé a enviarlo.</div>
<?php elseif ($reg && $reg['estado'] === 'aprobado' && $reg['comentario_revision']): ?>
  <div class="alert success"><strong>Comentario de aprobación:</strong> <?=h($reg['comentario_revision'])?></div>
<?php elseif ($reg && $reg['estado'] === 'enviado'): ?>
  <div class="alert fx-alert-info">Este formulario está <strong>pendiente de aprobación</strong><?= $perm['aprobar'] ? '. Revisalo y usá los botones <strong>Aprobar</strong> o <strong>Rechazar</strong>.' : ' por el responsable del área. No se puede modificar mientras tanto.' ?></div>
<?php endif; ?>

<div class="fx-toolbar" id="fxToolbar">
  <div class="fx-toolbar-main">
    <?php if ($perm['editar']): ?>
      <button class="btn secondary" data-accion="guardar">💾 Guardar borrador</button>
      <button class="btn primary" data-accion="enviar">📤 Guardar y enviar a aprobación</button>
    <?php endif; ?>
    <?php if ($perm['aprobar']): ?>
      <button class="btn primary" data-accion="aprobar">✔ Aprobar</button>
      <button class="btn danger" data-accion="rechazar">✖ Rechazar</button>
    <?php endif; ?>
    <?php if ($perm['reabrir']): ?><button class="btn secondary" data-accion="reabrir">↺ Reabrir para edición</button><?php endif; ?>
  </div>
  <div class="fx-toolbar-side">
    <button class="btn secondary" data-accion="imprimir">🖨 Imprimir / PDF</button>
    <?php if ($reg && puedeCompletarSector($pdo, $sid)): ?><button class="btn secondary" data-accion="duplicar">⧉ Duplicar</button><?php endif; ?>
    <?php if ($perm['eliminar']): ?><button class="btn danger" data-accion="eliminar">🗑 Eliminar</button><?php endif; ?>
  </div>
</div>
<div id="fxMsg" class="fx-msg" role="status" aria-live="polite"></div>

<div class="fx-frame-wrap"><iframe id="fxFrame" src="<?=h($srcIframe)?>" title="<?=h($form['titulo'])?>"></iframe></div>

<?php if ($historial): ?>
<section class="table-panel fx-historial">
  <h2>Historial</h2>
  <ul>
    <?php foreach ($historial as $hh): ?>
      <li><span><?=h(date('d/m/Y H:i', strtotime($hh['creado_en'])))?></span> <strong><?=h($hh['nombre'] ?? 'Sistema')?></strong> — <?=h($accionesHist[$hh['accion']] ?? $hh['accion'])?><?php if ($hh['comentario']): ?>: <em><?=h($hh['comentario'])?></em><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<dialog id="fxDialog" class="fx-dialog">
  <form method="dialog">
    <h3 id="fxDialogTitulo">Comentario</h3>
    <p id="fxDialogTexto" class="muted"></p>
    <textarea id="fxDialogComentario" rows="4" placeholder="Escribí un comentario…"></textarea>
    <div class="fx-dialog-actions"><button value="cancel" class="btn secondary">Cancelar</button><button value="ok" class="btn primary" id="fxDialogOk">Confirmar</button></div>
  </form>
</dialog>

</main></div>
<script>
(function(){
  var API = <?=json_encode(app_url('/php/formularios/api.php'))?>;
  var CSRF = <?=json_encode(csrf_token())?>;
  var FORM = <?=json_encode($form['codigo'])?>;
  var id = <?=$id ?: 'null'?>;
  var puedeEditar = <?=$perm['editar'] ? 'true' : 'false'?>;
  var frame = document.getElementById('fxFrame');
  var msg = document.getElementById('fxMsg');
  var dirty = document.getElementById('fxDirty');
  var refInput = document.getElementById('fxReferencia');
  var ocupado = false, reqs = {}, reqN = 0, revision = 0;

  function aviso(texto, tipo){ msg.textContent = texto; msg.className = 'fx-msg ' + (tipo || 'ok'); if (tipo !== 'err') setTimeout(function(){ if (msg.textContent === texto) msg.className = 'fx-msg'; }, 5000); }

  window.addEventListener('message', function(e){
    if (e.origin !== window.location.origin || e.source !== frame.contentWindow || !e.data) return;
    var d = e.data;
    if (d.type === 'naser:height' && Number.isFinite(d.height)) frame.style.height = Math.max(300, d.height + 16) + 'px';
    else if (d.type === 'naser:dirty' && puedeEditar) { revision++; dirty.hidden = false; }
    else if (d.type === 'naser:collected' && reqs[d.reqId]) { reqs[d.reqId](d); delete reqs[d.reqId]; }
    else if (d.type === 'naser:save-request' && puedeEditar) accion('guardar');
  });
  refInput.addEventListener('input', function(){ if (puedeEditar) { revision++; dirty.hidden = false; } });
  window.addEventListener('beforeunload', function(e){ if (!dirty.hidden) { e.preventDefault(); e.returnValue = ''; } });

  function recolectar(validar){
    return new Promise(function(ok, mal){
      var n = ++reqN; reqs[n] = ok;
      frame.contentWindow.postMessage({ type: 'naser:collect', reqId: n, validate: !!validar }, window.location.origin);
      setTimeout(function(){ if (reqs[n]) { delete reqs[n]; mal(new Error('El formulario no respondió. Recargá la página.')); } }, 5000);
    });
  }

  var dlg = document.getElementById('fxDialog');
  function pedirComentario(titulo, texto, obligatorio){
    return new Promise(function(ok){
      document.getElementById('fxDialogTitulo').textContent = titulo;
      document.getElementById('fxDialogTexto').textContent = texto || '';
      var ta = document.getElementById('fxDialogComentario'); ta.value = ''; ta.required = !!obligatorio;
      dlg.returnValue = '';
      dlg.onclose = function(){ ok(dlg.returnValue === 'ok' ? ta.value.trim() : null); };
      dlg.showModal(); ta.focus();
    });
  }

  function enviarApi(payload){
    payload.id = id; payload.formulario = FORM;
    return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(payload), credentials: 'same-origin' })
      .then(function(r){ return r.json().catch(function(){ throw new Error('Error del servidor (' + r.status + ').'); }); })
      .then(function(j){ if (!j.ok) throw new Error(j.error || 'No se pudo completar la acción.'); return j; });
  }

  var guardarPendiente = false;
  async function accion(nombre){
    if (ocupado) { if (nombre === 'guardar') guardarPendiente = true; return; }
    if (nombre === 'imprimir') { frame.contentWindow.postMessage({ type: 'naser:print' }, window.location.origin); return; }
    var payload = { accion: nombre };
    try {
      if (nombre === 'enviar' && !confirm('¿Enviar el formulario al responsable del área para su aprobación? Mientras esté pendiente no se podrá modificar.')) return;
      if (nombre === 'aprobar') { var c = await pedirComentario('Aprobar formulario', 'Podés dejar un comentario (opcional).', false); if (c === null) return; payload.comentario = c; }
      if (nombre === 'rechazar') { var c2 = await pedirComentario('Rechazar formulario', 'Indicá qué hay que corregir. Se le avisará a quien lo cargó.', true); if (c2 === null) return; if (!c2) { aviso('Escribí el motivo del rechazo.', 'err'); return; } payload.comentario = c2; }
      if (nombre === 'reabrir' && !confirm('¿Reabrir el formulario? Vuelve a borrador y se puede modificar.')) return;
      if (nombre === 'eliminar' && !confirm('¿Eliminar este formulario? No se puede deshacer.')) return;
      ocupado = true; document.body.classList.add('fx-busy');
      var revisionGuardada = revision;
      if (nombre === 'guardar' || nombre === 'enviar') {
        var d = await recolectar(nombre === 'enviar');
        if (d.valid === false) { aviso('Completá los campos obligatorios y corregí los datos indicados antes de enviar.', 'err'); return; }
        payload.datos = d.fields; payload.storage = d.storage;
        if (!refInput.value.trim() && d.sugerencia) refInput.value = d.sugerencia;
        payload.referencia = refInput.value.trim();
      }
      var j = await enviarApi(payload);
      dirty.hidden = revision !== revisionGuardada;
      if (j.redirect) { window.location.href = j.redirect; return; }
      var nuevo = !id; id = j.id;
      if (nombre === 'guardar') {
        history.replaceState(null, '', location.pathname + '?id=' + id);
        var estadoEl = document.getElementById('fxEstado');
        estadoEl.textContent = j.estado_label; estadoEl.className = 'fx-estado fx-' + j.estado;
        if (nuevo) {
          var numero = document.createElement('small'); numero.textContent = ' #' + id;
          document.querySelector('.fx-head h1').appendChild(numero);
          ['duplicar', 'eliminar'].forEach(function(a){
            if (a === 'eliminar' && !j.permisos.eliminar) return;
            var btn = document.createElement('button'); btn.type = 'button';
            btn.className = 'btn ' + (a === 'eliminar' ? 'danger' : 'secondary');
            btn.dataset.accion = a; btn.textContent = a === 'eliminar' ? 'Eliminar' : 'Duplicar';
            document.querySelector('.fx-toolbar-side').appendChild(btn);
          });
        }
        aviso('✔ ' + j.mensaje); return;
      }
      aviso('✔ ' + j.mensaje);
      setTimeout(function(){ window.location.href = <?=json_encode(app_url('/php/formularios/llenar.php'))?> + '?id=' + id + '&ok=' + encodeURIComponent(j.mensaje); }, nuevo && nombre === 'guardar' ? 200 : 600);
    } catch (err) {
      aviso(err.message, 'err');
    } finally {
      ocupado = false; document.body.classList.remove('fx-busy');
      if (guardarPendiente) { guardarPendiente = false; setTimeout(function(){ accion('guardar'); }, 50); }
    }
  }

  document.getElementById('fxToolbar').addEventListener('click', function(e){
    var b = e.target.closest('[data-accion]'); if (b) accion(b.getAttribute('data-accion'));
  });
  var params = new URLSearchParams(location.search);
  if (params.get('ok')) { aviso('✔ ' + params.get('ok')); history.replaceState(null, '', location.pathname + '?id=' + id); }
})();
</script>
</body></html>
