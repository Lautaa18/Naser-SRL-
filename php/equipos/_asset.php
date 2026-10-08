<?php
require __DIR__ . '/../config/auth.php';
requireLogin();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/formularios_catalogo.php';
require_once __DIR__ . '/../config/storage.php';

$title = $title ?? 'Activo';
$subtitle = $subtitle ?? '';
$image = $image ?? 'logo-naser.png';
$sectorSlugs = $sectorSlugs ?? [];
$formCodes = $formCodes ?? [];
$formNote = $formNote ?? '';

$visible = sectoresVisibles($pdo);
$visibleMap = array_column($visible, 'id', 'slug');
$sectorNames = array_column($visible, 'nombre', 'slug');
$allowedIds = [];
foreach ($sectorSlugs as $slug) {
    if (isset($visibleMap[$slug])) $allowedIds[] = (int)$visibleMap[$slug];
}

// Mostrar únicamente formularios existentes de sectores autorizados.
$forms = [];
foreach ($formCodes as $code) {
    $form = formularioPorCodigo($code);
    if (!$form || !isset($visibleMap[$form['sector']]) || !is_file(rutaFormulario($form))) continue;
    $form['sector_id'] = (int)$visibleMap[$form['sector']];
    $forms[] = $form;
}

$docs = [];
if ($allowedIds) {
    $ph = implode(',', array_fill(0, count($allowedIds), '?'));
    $st = $pdo->prepare("SELECT d.*, s.nombre sector FROM documentos d JOIN sectores s ON s.id=d.sector_id WHERE d.activo=1 AND d.sector_id IN ($ph) ORDER BY d.fecha_actualizacion DESC LIMIT 12");
    $st->execute($allowedIds);
    $docs = $st->fetchAll();
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=h($title)?> | NASER</title>
  <link rel="stylesheet" href="<?=asset('/style.css')?>">
  <link rel="stylesheet" href="<?=asset('/css/modules.css')?>">
  <link rel="stylesheet" href="<?=asset('/css/checklists.css')?>">
</head>
<body>
<div class="app">
<?php sidebar($pdo, 'inicio'); ?>
<main class="content resource-page">
  <header class="section-top">
    <div><p class="eyebrow">GESTIÓN DE RECURSOS</p><h1><?=h($title)?></h1><p><?=h($subtitle)?></p></div>
    <a class="btn secondary" href="<?=app_url('/php/dashboard.php')?>">Volver a Inicio</a>
  </header>
  <section class="asset-hero">
    <img src="<?=app_url('/img/' . $image)?>" alt="<?=h($title)?>">
    <div>
      <p class="eyebrow">FICHA OPERATIVA</p><h2><?=h($title)?></h2>
      <p>Completá los formularios relacionados con este recurso o consultá sus registros y documentación.</p>
      <div class="asset-links">
        <?php if ($forms): ?><a class="btn primary" href="#formularios">Ver formularios</a><?php endif; ?>
        <a class="btn secondary" href="<?=h(app_url('/php/buscar.php') . '?q=' . urlencode($title))?>">Buscar documentación</a>
        <?php if (isset($visibleMap['operaciones'])): ?><a class="btn secondary" href="<?=app_url('/php/operaciones.php')?>">Ver operaciones</a><?php endif; ?>
      </div>
    </div>
  </section>

  <section id="formularios" aria-labelledby="asset-forms-title">
    <div class="section-head">
      <div><p class="eyebrow">ACCESOS A FORMULARIOS</p><h2 id="asset-forms-title">Formularios de <?=h($title)?></h2><p>Elegí el formulario para completar una nueva carga o consultar registros anteriores.</p></div>
      <span class="count-pill"><?=count($forms)?> disponibles</span>
    </div>
    <?php if ($formNote !== ''): ?><p class="muted"><?=h($formNote)?></p><?php endif; ?>
    <?php if (!$forms): ?>
      <div class="empty">No hay formularios relacionados disponibles para tus sectores habilitados.</div>
    <?php else: ?>
      <div class="grid-forms">
        <?php foreach ($forms as $form):
            $hub = app_url('/php/formularios/index.php') . '?' . http_build_query(['sector' => $form['sector'], 'f' => $form['codigo']]);
            $fill = app_url('/php/formularios/llenar.php') . '?f=' . urlencode($form['codigo']);
        ?>
          <article class="card-form">
            <div>
              <span class="type-badge"><?=h($sectorNames[$form['sector']] ?? $form['sector'])?></span>
              <h3><?=h($form['titulo'])?></h3>
              <?php if ($form['codigo_sgi'] !== ''): ?><span class="fx-code"><?=h($form['codigo_sgi'])?></span><?php endif; ?>
            </div>
            <div class="fx-card-actions">
              <?php if (puedeCompletarSector($pdo, $form['sector_id'])): ?><a class="btn primary" href="<?=h($fill)?>">Completar nuevo</a><?php endif; ?>
              <a class="btn secondary" href="<?=h($hub)?>">Ver registros</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="section-head"><div><p class="eyebrow">DOCUMENTACIÓN RELACIONADA</p><h2>Últimos documentos de sectores asociados</h2></div></div>
  <section class="document-grid">
    <?php if (!$docs): ?><div class="empty">Todavía no hay documentación cargada en los sectores relacionados.</div><?php endif; ?>
    <?php foreach ($docs as $doc): ?>
      <article class="document-card">
        <span class="type-badge"><?=h($doc['sector'])?></span><h3><?=h($doc['titulo'])?></h3>
        <p><?=h($doc['descripcion'] ?: 'Documento relacionado.')?></p>
        <?php if ($doc['archivo']): ?><div class="doc-actions"><a class="btn primary" target="_blank" rel="noopener" href="<?=h(naser_upload_url($doc['archivo']))?>">Abrir documento</a></div><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
</main>
</div>
</body>
</html>
