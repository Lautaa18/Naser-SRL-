<?php
require __DIR__.'/config/auth.php';requireLogin();
require __DIR__.'/config/db.php';require __DIR__.'/config/layout.php';
$sectores=sectoresVisibles($pdo);
$ids=array_map(fn($s)=>(int)$s['id'],$sectores);
$ops=[];if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));
$st=$pdo->prepare("SELECT o.*,s.nombre sector FROM operaciones o JOIN sectores s ON s.id=o.sector_id WHERE o.sector_id IN ($ph) ORDER BY s.orden,o.orden,o.nombre");
$st->execute($ids);$ops=$st->fetchAll();}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Operaciones | NASER</title><link rel="stylesheet" href="<?=app_url('/style.css')?>"></head><body><div class="app"><?php sidebar($pdo,'operaciones');
?><main class="content"><header class="section-top"><div><p class="eyebrow">GESTIÓN OPERATIVA</p><h1>Operaciones</h1><p>Visualización de actividades según los permisos del usuario.</p></div></header><section class="operation-grid"><?php if(!$ops):?><div class="empty">No hay operaciones cargadas.</div><?php endif;?><?php foreach($ops as $o):?><article class="operation-card"><div class="op-head"><span class="type-badge"><?=h($o['sector'])?></span><span class="<?=$o['estado']==='activa'?'state-on':'state-off'?>"><?=h(ucfirst($o['estado']))?></span></div><h3><?=h($o['nombre'])?></h3><p><?=h($o['descripcion']?:'Sin descripción cargada.')?></p></article><?php endforeach;?></section></main></div></body></html>
