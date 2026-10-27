<?php
require __DIR__.'/config/auth.php'; requireLogin();
require_once __DIR__.'/config/db.php'; require __DIR__.'/config/layout.php'; require_once __DIR__.'/config/formularios_catalogo.php';
$sectores=sectoresVisibles($pdo); $rol=$_SESSION['rol']??'';
$ids=array_map(fn($s)=>(int)$s['id'],$sectores);   // incluye el SGI (para contar su documentacion)
$sectoresReales=array_values(array_filter($sectores,fn($s)=>$s['slug']!=='sgi'));   // el SGI es un apartado, no un sector
// ---------- Mi trabajo: formularios y vencimientos ----------
$uidD=(int)$_SESSION['usuario_id'];
$aprobables=array_values(array_filter($ids,fn($i)=>puedeAprobarSector($pdo,$i)));
$pendAprobar=[];
if($aprobables){$st=$pdo->query("SELECT r.id,r.formulario,r.referencia,r.enviado_en,u.nombre autor FROM formularios_registros r LEFT JOIN usuarios u ON u.id=r.enviado_por WHERE r.eliminado_lote IS NULL AND r.estado='enviado' AND r.sector_id IN (".implode(',',$aprobables).") ORDER BY r.enviado_en LIMIT 8");$pendAprobar=$st->fetchAll();}
$misPendientes=[];
if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT id,formulario,referencia,estado,actualizado_en,comentario_revision FROM formularios_registros WHERE eliminado_lote IS NULL AND creado_por=? AND sector_id IN ($ph) AND estado IN ('borrador','rechazado') ORDER BY estado='rechazado' DESC, actualizado_en DESC LIMIT 8");$st->execute([$uidD,...$ids]);$misPendientes=$st->fetchAll();}
$verHab=puedeVerHabilitaciones($pdo);
$vencimientosVisibles=array_values(array_filter(vencimientosProximos($pdo,30,$ids),fn($v)=>$v['origen']!=='emp'||$verHab||(bool)array_intersect($v['sectores_finanzas']??[],$ids)));
$vencs=array_slice($vencimientosVisibles,0,10);
$totalVencidos=count(array_filter($vencimientosVisibles,fn($v)=>$v['dias']<0));
$totalProximos=count(array_filter($vencimientosVisibles,fn($v)=>$v['dias']>=0&&$v['dias']<=7));
$misBorradores=0;$misRechazados=0;$totalParaAprobar=0;
if($ids){$st=$pdo->prepare("SELECT estado,COUNT(*) cantidad FROM formularios_registros WHERE eliminado_lote IS NULL AND creado_por=? AND sector_id IN (".implode(',',$ids).") AND estado IN ('borrador','rechazado') GROUP BY estado");$st->execute([$uidD]);foreach($st->fetchAll() as $n){if($n['estado']==='borrador')$misBorradores=(int)$n['cantidad'];else$misRechazados=(int)$n['cantidad'];}}
if($aprobables)$totalParaAprobar=(int)$pdo->query("SELECT COUNT(*) FROM formularios_registros WHERE eliminado_lote IS NULL AND estado='enviado' AND sector_id IN (".implode(',',$aprobables).")")->fetchColumn();
$misAvisos=notificacionesSinLeer($pdo,$uidD);
$primero=explode(' ',trim((string)($_SESSION['nombre']??'')))[0]??'';
$proteccionResumen=null;
if(esAdmin()){
 if(is_file(__DIR__.'/config/proteccion.php')){require_once __DIR__.'/config/proteccion.php';$proteccionResumen=estadoProteccion($pdo)['resumen'];}
 else $proteccionResumen=['nivel'=>'aviso','texto'=>'Comprobá el estado de los respaldos y las alertas.'];
}
$porSector=[];
if($ids){$st=$pdo->query("SELECT sector_id,COUNT(*) c FROM formularios_registros WHERE eliminado_lote IS NULL AND creado_en>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND sector_id IN (".implode(',',$ids).") GROUP BY sector_id");foreach($st->fetchAll() as $r)$porSector[(int)$r['sector_id']]=(int)$r['c'];}
$maxSector=max(array_merge([1],array_values($porSector)));
$totAprobados=0;if($ids){$totAprobados=(int)$pdo->query("SELECT COUNT(*) FROM formularios_registros WHERE eliminado_lote IS NULL AND estado='aprobado' AND sector_id IN (".implode(',',$ids).")")->fetchColumn();}
$catD=formulariosCatalogo(); $infoD=sectoresInfo($pdo);
$paginasEquipo=['Trabajador'=>'equipos/trabajador.php','Camión Slickline'=>'equipos/camion-slickline.php','Unidad Liviana'=>'equipos/unidad-liviana.php','Hidrogrúa'=>'equipos/hidrogrua.php'];
$galeria=[
 ['Trabajador','trabajador-slickline.jpeg','formularios/llenar.php?f=operaciones-inspeccion-equipo','Inspección y preparación de equipo','Completar checklist'],
 ['Camión Slickline','Camion-Naser.png','formularios/llenar.php?f=operaciones-control-slickline','Control Operativo Slickline','Completar checklist'],
 ['Unidad Liviana','Unidad-liviana.png','formularios/llenar.php?f=mantenimiento-checklist-vehicular','Checklist Vehicular · Unidad Liviana','Completar checklist'],
 ['Hidrogrúa','Hidrogrua.jpeg','formularios/llenar.php?f=mantenimiento-checklist-hidrogrua','Checklist de Hidrogrúa','Completar checklist']
];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panel principal | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"><link rel="stylesheet" href="<?=asset('/css/mejoras.css')?>"></head><body><div class="app"><?php sidebar($pdo,'inicio');?><main class="content">
<header class="topbar mej-home-head"><div><p class="eyebrow">MI TRABAJO · NASER</p><h1>Hola<?= $primero!==''?', '.h($primero):'' ?></h1><p>Estas son tus prioridades y accesos según los permisos de tu cuenta.</p></div><div class="top-actions"><span class="user-chip"><?=h(etiquetaRolUsuario($pdo))?></span></div></header>
<section class="mej-work-summary" aria-label="Mis prioridades">
<?php if($aprobables):?><a class="mej-work-card <?=$totalParaAprobar?'mej-attention':''?>" href="<?=app_url('/php/formularios/index.php?vista=aprobar')?>"><strong><?=$totalParaAprobar?></strong><span>Para aprobar</span><small>Formularios enviados a los sectores que podés aprobar.</small></a><?php endif;?>
<a class="mej-work-card <?=$misRechazados?'mej-danger':''?>" href="<?=app_url('/php/formularios/index.php?vista=mios&estado=rechazado')?>"><strong><?=$misRechazados?></strong><span>Para corregir</span><small>Revisá los comentarios y volvé a enviar tus formularios.</small></a>
<a class="mej-work-card" href="<?=app_url('/php/formularios/index.php?vista=mios&estado=borrador')?>"><strong><?=$misBorradores?></strong><span>Mis borradores</span><small>Retomá lo que dejaste pendiente de completar.</small></a>
<a class="mej-work-card <?=$totalVencidos?'mej-danger':($totalProximos?'mej-attention':'')?>" href="#mis-vencimientos"><strong><?=$totalVencidos?></strong><span>Vencimientos pendientes</span><small><?=$totalProximos?> próximo(s) en los siguientes 7 días.</small></a>
<a class="mej-work-card" href="<?=app_url('/php/notificaciones.php')?>"><strong><?=$misAvisos?></strong><span>Avisos sin leer</span><small>Consultá novedades y notificaciones de tu trabajo.</small></a>
<?php if($proteccionResumen):?><a class="mej-work-card mej-admin <?=$proteccionResumen['nivel']==='error'?'mej-danger':($proteccionResumen['nivel']==='aviso'?'mej-attention':'')?>" href="<?=app_url('/php/admin/proteccion.php')?>"><span>Respaldos y alertas</span><small><?=h($proteccionResumen['texto'])?></small><span>Revisar estado →</span></a><?php endif;?>
</section>
<nav class="mej-quick-links" aria-label="Accesos a mi trabajo"><a class="btn secondary" href="<?=app_url('/php/formularios/index.php?vista=mios')?>">Mis formularios</a><?php foreach($sectoresReales as $sectorAcceso) botonHerramientaSector($pdo,$sectorAcceso['slug']);?><?php if(puedeVerHabilitaciones($pdo)):?><a class="btn secondary" href="<?=app_url('/php/personal.php')?>">Personal y habilitaciones</a><?php endif;?><?php if(sectoresEditables($pdo)):?><a class="btn secondary" href="<?=app_url('/php/admin/papelera.php')?>">Recuperar un elemento</a><?php endif;?></nav>
<section class="dashboard-banner"><img src="<?=app_url('/img/Trabajo.jpeg')?>" alt="Operación NASER"><div class="dashboard-banner-overlay"></div><div class="dashboard-banner-copy"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER"><div><span>DIVISIÓN PETRÓLEO</span><strong>Compromiso · Seguridad · Operación</strong></div></div></section>
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
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">VENCIDOS Y PRÓXIMOS 30 DÍAS</p><h2 id="mis-vencimientos">Mis vencimientos visibles</h2></div></div>
    <?php if(!$vencs):?><p class="muted">No hay vencimientos próximos.</p><?php else:?><ul class="mini-list"><?php foreach($vencs as $v): $cls=$v['dias']<0?'vencido':($v['dias']<=7?'pronto':'ok');?><li><a href="<?=h(app_url($v['url']))?>"><?=h($v['titulo'].' · '.$v['detalle'])?></a><span class="dias-pill <?=$cls?>"><?= $v['dias']<0?'Vencido':($v['dias']===0?'Hoy':'en '.$v['dias'].' d')?> · <?=h(date('d/m/Y',strtotime($v['fecha'])))?></span></li><?php endforeach;?></ul><?php endif;?>
  </div>
  <div class="table-panel">
    <div class="section-head" style="margin-top:0"><div><p class="eyebrow">ESTE MES</p><h2>Formularios cargados por sector</h2></div><span class="count-pill"><?=$totAprobados?> aprobados en total</span></div>
    <div class="bar-chart"><?php foreach($sectores as $s): if(!formulariosDeSector($s['slug'])) continue; $c=$porSector[(int)$s['id']]??0;?><div class="bar-row"><span><?=h($s['nombre'])?></span><div class="bar-track"><div class="bar-fill" style="width:<?=round($c/$maxSector*100)?>%"></div></div><strong><?=$c?></strong></div><?php endforeach;?></div>
  </div>
</section>

<section class="fleet-panel"><div class="section-head"><div><p class="eyebrow">ACCESOS DIRECTOS</p><h2>Personal, flota y equipamiento</h2><p>Seleccioná una imagen para abrir su control o checklist correspondiente.</p></div></div><div class="fleet-images"><?php foreach($galeria as [$titulo,$img,$url,$desc,$accion]):
  // Si la persona no puede completar ese formulario (otro sector), la tarjeta lleva a la ficha del equipo, no a un error
  $fCod=formularioPorCodigo(substr(strrchr($url,'='),1)); $fSec=$fCod?sectorPorSlug($pdo,$fCod['sector']):null;
  if(!($fSec&&$fCod&&puedeCompletarFormulario($pdo,$fCod['codigo'],(int)$fSec['id']))){ $url=$paginasEquipo[$titulo]; $accion='Ver equipo y registros'; }
?><a class="fleet-card" href="<?=h(app_url('/php/'.$url))?>"><div class="fleet-image"><img src="<?=app_url('/img/'.$img)?>" alt="<?=h($titulo)?>" loading="lazy"></div><div class="fleet-card-body"><h3><?=h($titulo)?></h3><p><?=h($desc)?></p><span><?=h($accion)?> →</span></div></a><?php endforeach;?></div></section>
<section class="policy-panel"><p class="eyebrow">INFORMACIÓN GENERAL</p><h2>Política de Calidad, Ambiente, Seguridad y Salud</h2><p>El sistema concentra la información necesaria para acompañar las operaciones de Slickline, Well Testing y Flow Back, con foco en seguridad, calidad, ambiente y mejora continua.</p><div class="policy-grid"><span>✓ Mejora continua</span><span>✓ Cumplimiento legal y normativo</span><span>✓ Prevención de incidentes</span><span>✓ Trabajo seguro y saludable</span></div></section>
<div class="section-head"><div><p class="eyebrow">GESTIÓN CENTRALIZADA</p><h2>Sectores habilitados</h2></div></div><section class="sector-grid"><?php foreach($sectoresReales as $s):?><article class="sector-card"><div class="sector-code"><?=h(strtoupper(substr($s['nombre'],0,2)))?></div><h3><?=h($s['nombre'])?></h3><p>Carpetas, documentación y procedimientos del sector.</p><div class="card-links"><a href="<?=app_url('/php/sector.php?sector='.urlencode($s['slug']))?>">Abrir sector →</a></div></article><?php endforeach;?></section>
<details class="developers-section">
  <summary class="developers-toggle">
    <span class="developers-icon" aria-hidden="true">&lt;/&gt;</span>
    <span class="developers-toggle-copy"><strong>Desarrolladores</strong><span>Conocé al equipo detrás de este proyecto</span></span>
    <svg class="developers-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </summary>
  <div class="developers-content">
    <figure class="developers-photo"><img src="<?=h(asset('/img/Desarrolladores.jpeg'))?>" alt="Foto grupal compartida por el equipo del proyecto NASER" width="1600" height="1200" loading="lazy"></figure>
    <div class="developers-info">
      <p class="eyebrow">PERSONAS QUE HACEN POSIBLE EL PROYECTO</p>
      <h2>Desarrolladores de este proyecto</h2>
      <p class="developers-description">Un proyecto construido en equipo.</p>
      <ul class="developers-names" aria-label="Desarrolladores del proyecto">
        <li><span aria-hidden="true">GV</span><strong>Gonzalo Vilche</strong></li>
        <li><span aria-hidden="true">RW</span><strong>Robert Weisser</strong></li>
        <li><span aria-hidden="true">LZ</span><strong>Lautaro Zeballos</strong></li>
      </ul>
      <p class="developers-signature">NASER <span>·</span> Sistema de Gestión Integrado</p>
    </div>
  </div>
</details>
</main></div></body></html>
