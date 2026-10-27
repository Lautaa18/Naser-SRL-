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
$puedeDescargar = puedeDescargarSector($pdo, $sid);

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
$accionesHist = ['creado' => 'Creó el formulario', 'guardado' => 'Guardó cambios', 'enviado' => 'Envió para aprobación', 'aprobado' => 'Aprobó', 'rechazado' => 'Rechazó', 'reabierto' => 'Reabrió para edición', 'papelera' => 'Envió a la papelera'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($form['titulo'])?> | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
<style>.fx-save-status{font-size:13px;color:#4b6354}.fx-save-status.pending,.fx-save-status.saving{color:#8b5b12}.fx-save-status.error{color:#a13324}.fx-save-status.saved{color:#16643b}.fx-conflict[hidden]{display:none}.fx-conflict h2{font-size:18px;margin-top:0}.fx-transition .fx-frame-wrap{position:relative}.fx-transition .fx-frame-wrap:after{content:'Procesando…';position:absolute;inset:0;background:#ffffffaa;display:flex;align-items:flex-start;justify-content:center;padding-top:50px;font-weight:700;z-index:5}</style>
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
      <span id="fxDirty" class="fx-dirty" hidden>● Cambios pendientes</span>
      <span id="fxSaveStatus" class="fx-save-status" role="status" aria-live="polite"></span>
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
    <?php if ($puedeDescargar): ?><button class="btn secondary" data-accion="imprimir">🖨 Imprimir / PDF</button><?php endif; ?>
    <?php if ($reg && puedeCompletarSector($pdo, $sid)): ?><button class="btn secondary" data-accion="duplicar">⧉ Duplicar</button><?php endif; ?>
    <?php if ($perm['eliminar']): ?><button class="btn danger" data-accion="eliminar">🗑 Eliminar</button><?php endif; ?>
  </div>
</div>
<div id="fxMsg" class="fx-msg" role="status" aria-live="polite"></div>

<section id="fxConflict" class="alert error fx-conflict" hidden aria-labelledby="fxConflictTitulo"><h2 id="fxConflictTitulo">Hay una versión más reciente</h2><p>Tus cambios siguen en esta pantalla. Podés conservarlos en un borrador separado, sin reemplazar la versión del servidor.</p><div class="top-actions"><button type="button" id="fxGuardarCopia" class="btn primary">Guardar mis cambios como copia</button><button type="button" id="fxAbrirActual" class="btn secondary">Abrir versión actual</button></div></section>
<section id="fxSession" class="alert error fx-conflict" hidden aria-labelledby="fxSessionTitulo"><h2 id="fxSessionTitulo">El guardado está detenido</h2><p>Revisá tu sesión y tus permisos. Tus cambios siguen en esta pantalla. Si necesitás ingresar de nuevo o cambiar la contraseña, hacelo en otra pestaña y después reanudá el guardado con la misma cuenta.</p><div class="top-actions"><a id="fxSessionLink" class="btn secondary" href="<?=h(app_url('/php/login.php'))?>" target="_blank" rel="noopener">Revisar mi sesión en otra pestaña</a><button type="button" id="fxReanudar" class="btn primary">Reanudar guardado</button><button type="button" id="fxDescargarBorrador" class="btn secondary">Descargar mis cambios</button></div><small>La descarga contiene los datos de este formulario.</small></section>
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
window.__NASER_EDITOR__ = <?=json_encode(['api'=>app_url('/php/formularios/api.php'),'csrf'=>csrf_token(),'formulario'=>$form['codigo'],'id'=>$id?:null,'revision'=>(int)($reg['revision']??0),'clientToken'=>bin2hex(random_bytes(20)),'editar'=>(bool)$perm['editar'],'descargar'=>$puedeDescargar,'usuarioId'=>(int)$_SESSION['usuario_id'],'editorUrl'=>app_url('/php/formularios/llenar.php')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;
</script>
<script src="<?=h(asset('/js/naser-autosave.js'))?>"></script>
</body></html>
