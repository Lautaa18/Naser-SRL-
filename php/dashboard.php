<?php
require __DIR__.'/config/auth.php'; requireLogin();
require __DIR__.'/config/db.php'; require __DIR__.'/config/layout.php'; require_once __DIR__.'/config/formularios_catalogo.php';
$sectores=sectoresVisibles($pdo); $rol=$_SESSION['rol']??'';
$ids=array_map(fn($s)=>(int)$s['id'],$sectores);
$totalDocs=0; $totalCarpetas=0;
if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT COUNT(*) FROM documentos WHERE activo=1 AND sector_id IN ($ph)");$st->execute($ids);$totalDocs=(int)$st->fetchColumn();$st=$pdo->prepare("SELECT COUNT(*) FROM carpetas WHERE activa=1 AND sector_id IN ($ph)");$st->execute($ids);$totalCarpetas=(int)$st->fetchColumn();}
// ---------- Mi trabajo: formularios y vencimientos ----------
$uidD=(int)$_SESSION['usuario_id'];
$aprobables=array_values(array_filter($ids,fn($i)=>puedeAprobarSector($pdo,$i)));
$pendAprobar=[];
if($aprobables){$st=$pdo->query("SELECT r.id,r.formulario,r.referencia,r.enviado_en,u.nombre autor FROM formularios_registros r LEFT JOIN usuarios u ON u.id=r.enviado_por WHERE r.estado='enviado' AND r.sector_id IN (".implode(',',$aprobables).") ORDER BY r.enviado_en LIMIT 8");$pendAprobar=$st->fetchAll();}
$misPendientes=[];
if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT id,formulario,referencia,estado,actualizado_en,comentario_revision FROM formularios_registros WHERE creado_por=? AND sector_id IN ($ph) AND estado IN ('borrador','rechazado') ORDER BY estado='rechazado' DESC, actualizado_en DESC LIMIT 8");$st->execute([$uidD,...$ids]);$misPendientes=$st->fetchAll();}
$verHab=puedeVerHabilitaciones($pdo);
$vencs=array_slice(array_values(array_filter(vencimientosProximos($pdo,30,$ids),fn($v)=>$v['origen']!=='emp'||$verHab)),0,10);
$porSector=[];
if($ids){$st=$pdo->query("SELECT sector_id,COUNT(*) c FROM formularios_registros WHERE creado_en>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND sector_id IN (".implode(',',$ids).") GROUP BY sector_id");foreach($st->fetchAll() as $r)$porSector[(int)$r['sector_id']]=(int)$r['c'];}
$maxSector=max([1]+array_values($porSector));
$totAprobados=0;if($ids){$totAprobados=(int)$pdo->query("SELECT COUNT(*) FROM formularios_registros WHERE estado='aprobado' AND sector_id IN (".implode(',',$ids).")")->fetchColumn();}
$catD=formulariosCatalogo(); $infoD=sectoresInfo($pdo);
$galeria=[
 ['Trabajador','trabajador-slickline.jpeg','formularios/llenar.php?f=operaciones-control-slickline','Control Operativo Slickline','Completar checklist'],
 ['Camión Slickline','Camion-Naser.png','formularios/llenar.php?f=operaciones-control-slickline','Control Operativo Slickline','Completar checklist'],
 ['Unidad Liviana','Unidad-liviana.png','formularios/llenar.php?f=mantenimiento-checklist-vehicular','Checklist Vehicular · Unidad Liviana','Completar checklist'],
 ['Hidrogrúa','Hidrogrua.jpeg','formularios/llenar.php?f=mantenimiento-checklist-hidrogrua','Checklist de Hidrogrúa','Completar checklist']
];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panel principal | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head><body><div class="app"><?php sidebar($pdo,'inicio');?><main class="content">
<header class="topbar"><div><p class="eyebrow">SERVICIOS NASER SRL</p><h1>Panel principal</h1><p>Gestión centralizada de documentación, sectores y operaciones.</p></div><div class="top-actions"><span class="status"><i></i>Sistema activo</span><span class="user-chip"><?=h($_SESSION['nombre'])?></span></div></header>
<section class="dashboard-banner"><img src="<?=app_url('/img/Trabajo.jpeg')?>" alt="Operación NASER"><div class="dashboard-banner-overlay"></div><div class="dashboard-banner-copy"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER"><div><span>DIVISIÓN PETRÓLEO</span><strong>Compromiso · Seguridad · Operación</strong></div></div></section>
<section class="stats"><article><span>ROL ACTUAL</span><strong style="font-size:16px"><?=h(etiquetaRolUsuario($pdo))?></strong><small>Nivel de acceso asignado</small></article><article><span>SECTORES</span><strong><?=count($sectores)?></strong><small>Sectores visibles</small></article><article><span>DOCUMENTOS</span><strong><?=$totalDocs?></strong><small>Archivos disponibles</small></article><article><span>CARPETAS</span><strong><?=$totalCarpetas?></strong><small>Organización documental</small></article></section>
<section class="panel-grid">
  <div class="table-panel">
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">APROBACIONES</p><h2>Pendientes de mi aprobación <?php if($pendAprobar):?><span class="count-pill fx-pill-warn"><?=count($pendAprobar)?></span><?php endif;?></h2></div><a class="btn secondary small" href="<?=app_url('/php/formularios/index.php')?>?vista=aprobar">Ver todos</a></div>
    <?php if(!$pendAprobar):?><p class="muted">No tenés formularios para aprobar. ✔</p><?php else:?><ul class="mini-list"><?php foreach($pendAprobar as $r):?><li><a href="<?=h(app_url('/php/formularios/llenar.php').'?id='.(int)$r['id'])?>"><?=h(($catD[$r['formulario']]['titulo']??$r['formulario']).($r['referencia']?' — '.$r['referencia']:''))?></a><span class="muted"><?=h($r['autor']??'')?> · <?=h(date('d/m',strtotime($r['enviado_en'])))?></span></li><?php endforeach;?></ul><?php endif;?>
  </div>
  <div class="table-panel">
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">MIS FORMULARIOS</p><h2>Borradores y para corregir</h2></div><a class="btn secondary small" href="<?=app_url('/php/formularios/index.php')?>?vista=mios">Ver todos</a></div>
    <?php if(!$misPendientes):?><p class="muted">No tenés formularios sin terminar.</p><?php else:?><ul class="mini-list"><?php foreach($misPendientes as $r):?><li><a href="<?=h(app_url('/php/formularios/llenar.php').'?id='.(int)$r['id'])?>"><?=h(($catD[$r['formulario']]['titulo']??$r['formulario']).($r['referencia']?' — '.$r['referencia']:''))?></a><span class="fx-estado fx-<?=h($r['estado'])?>"><?=h($r['estado']==='rechazado'?'Corregir':'Borrador')?></span></li><?php endforeach;?></ul><?php endif;?>
  </div>
  <div class="table-panel">
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">PRÓXIMOS 30 DÍAS</p><h2>Vencimientos</h2></div></div>
    <?php if(!$vencs):?><p class="muted">No hay vencimientos próximos.</p><?php else:?><ul class="mini-list"><?php foreach($vencs as $v): $cls=$v['dias']<0?'vencido':($v['dias']<=7?'pronto':'ok');?><li><a href="<?=h(app_url($v['url']))?>"><?=h($v['titulo'].' · '.$v['detalle'])?></a><span class="dias-pill <?=$cls?>"><?= $v['dias']<0?'Vencido':($v['dias']===0?'Hoy':'en '.$v['dias'].' d')?> · <?=h(date('d/m/Y',strtotime($v['fecha'])))?></span></li><?php endforeach;?></ul><?php endif;?>
  </div>
  <div class="table-panel">
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">ESTE MES</p><h2>Formularios cargados por sector</h2></div><span class="count-pill"><?=$totAprobados?> aprobados en total</span></div>
    <div class="bar-chart"><?php foreach($sectores as $s): if(!formulariosDeSector($s['slug'])) continue; $c=$porSector[(int)$s['id']]??0;?><div class="bar-row"><span><?=h($s['nombre'])?></span><div class="bar-track"><div class="bar-fill" style="width:<?=round($c/$maxSector*100)?>%"></div></div><strong><?=$c?></strong></div><?php endforeach;?></div>
  </div>
</section>

<section class="fleet-panel"><div class="section-head"><div><p class="eyebrow">ACCESOS DIRECTOS</p><h2>Personal, flota y equipamiento</h2><p>Seleccioná una imagen para abrir su control o checklist correspondiente.</p></div></div><div class="fleet-images"><?php foreach($galeria as [$titulo,$img,$url,$desc,$accion]):?><a class="fleet-card" href="<?=h(app_url('/php/'.$url))?>"><div class="fleet-image"><img src="<?=app_url('/img/'.$img)?>" alt="<?=h($titulo)?>" loading="lazy"></div><div class="fleet-card-body"><h3><?=h($titulo)?></h3><p><?=h($desc)?></p><span><?=h($accion)?> →</span></div></a><?php endforeach;?></div></section>
<section class="policy-panel"><p class="eyebrow">INFORMACIÓN GENERAL</p><h2>Política de Calidad, Ambiente, Seguridad y Salud</h2><p>El sistema concentra la información necesaria para acompañar las operaciones de Slickline, Well Testing y Flow Back, con foco en seguridad, calidad, ambiente y mejora continua.</p><div class="policy-grid"><span>✓ Mejora continua</span><span>✓ Cumplimiento legal y normativo</span><span>✓ Prevención de incidentes</span><span>✓ Trabajo seguro y saludable</span></div></section>
<div class="section-head"><div><p class="eyebrow">GESTIÓN CENTRALIZADA</p><h2>Sectores habilitados</h2></div></div><section class="sector-grid"><?php foreach($sectores as $s):?><article class="sector-card"><div class="sector-code"><?=h(strtoupper(substr($s['nombre'],0,2)))?></div><h3><?=h($s['nombre'])?></h3><p>Carpetas, documentación y procedimientos del sector.</p><div class="card-links"><a href="<?=app_url('/php/sector.php?sector='.urlencode($s['slug']))?>">Abrir sector →</a></div></article><?php endforeach;?></section>
</main></div></body></html>
