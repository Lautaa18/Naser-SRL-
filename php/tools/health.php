<?php
require __DIR__.'/../config/auth.php';requireAdmin();require __DIR__.'/../config/db.php';require __DIR__.'/../config/layout.php';
$uploads=dirname(__DIR__,2).'/uploads';$checks=[
 ['Base de datos',true,'Conexión PDO activa'],
 ['ZipArchive',class_exists('ZipArchive'),class_exists('ZipArchive')?'Disponible para importación ZIP':'No disponible'],
 ['Carpeta uploads',is_dir($uploads)&&is_writable($uploads),is_dir($uploads)&&is_writable($uploads)?'Existe y tiene permisos de escritura':'Revisar permisos'],
];
$counts=[];foreach(['usuarios','sectores','carpetas','documentos','operaciones','actividad'] as $t){try{$counts[$t]=(int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();}catch(Throwable $e){$counts[$t]=-1;}}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico | NASER</title><link rel="stylesheet" href="<?=app_url('/style.css')?>"></head><body><div class="app"><?php sidebar($pdo,'');?><main class="content"><header class="section-top"><div><p class="eyebrow">DIAGNÓSTICO</p><h1>Estado de la prueba funcional</h1><p>Chequeo rápido antes de una presentación.</p></div></header><section class="document-grid"><?php foreach($checks as [$n,$ok,$d]):?><article class="document-card"><span class="<?=$ok?'state-on':'state-off'?>"><?=$ok?'OK':'REVISAR'?></span><h3><?=h($n)?></h3><p><?=h($d)?></p></article><?php endforeach;?></section><div class="section-head"><div><h2>Contenido de la base</h2></div></div><section class="stats"><?php foreach($counts as $n=>$v):?><article><span><?=h(strtoupper($n))?></span><strong><?=$v?></strong><small>Registros</small></article><?php endforeach;?></section></main></div></body></html>
