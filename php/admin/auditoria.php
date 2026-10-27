<?php
require __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/layout.php';
$editables=sectoresEditables($pdo);$ids=array_map(fn($s)=>(int)$s['id'],$editables);
if(!esAdmin()&&!$ids){http_response_code(403);exit('No tenés permiso para consultar el historial de cambios.');}
$scope=esAdmin()?'1=1':'a.sector_id IN ('.implode(',',$ids).')';
$vista=esAdmin()&&($_GET['vista']??'')==='actividad'?'actividad':'cambios';
$where=[$scope];$params=[];$tabla=trim((string)($_GET['tabla']??''));$usuario=max(0,(int)($_GET['usuario']??0));$desde=(string)($_GET['desde']??'');$hasta=(string)($_GET['hasta']??'');
function auditoriaFecha(string $valor):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$valor);return $d&&$d->format('Y-m-d')===$valor;}
if($usuario){$where[]='a.usuario_id=?';$params[]=$usuario;}
$campoFecha=$vista==='actividad'?'a.fecha':'a.creado_en';
if(auditoriaFecha($desde)){$where[]="$campoFecha>=?";$params[]=$desde.' 00:00:00';}else$desde='';
if(auditoriaFecha($hasta)){$where[]="$campoFecha<?";$params[]=(new DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d');}else$hasta='';
$tablas=[];$usuarios=[];
if($vista==='cambios'){
 $tablas=$pdo->query("SELECT DISTINCT a.tabla FROM auditoria_cambios a WHERE $scope ORDER BY a.tabla")->fetchAll(PDO::FETCH_COLUMN);
 if($tabla!==''&&in_array($tabla,$tablas,true)){$where[]='a.tabla=?';$params[]=$tabla;}else$tabla='';
 $usuarios=$pdo->query("SELECT DISTINCT u.id,u.nombre FROM auditoria_cambios a JOIN usuarios u ON u.id=a.usuario_id WHERE $scope ORDER BY u.nombre")->fetchAll();$from='auditoria_cambios a';
}else{
 $where[0]='1=1';$from='actividad a';$usuarios=$pdo->query('SELECT DISTINCT u.id,u.nombre FROM actividad a JOIN usuarios u ON u.id=a.usuario_id ORDER BY u.nombre')->fetchAll();
}
$whereSql=implode(' AND ',$where);$st=$pdo->prepare("SELECT COUNT(*) FROM $from WHERE $whereSql");$st->execute($params);$total=(int)$st->fetchColumn();
$porPagina=30;$paginas=max(1,(int)ceil($total/$porPagina));$pagina=min($paginas,max(1,(int)($_GET['pagina']??1)));$offset=($pagina-1)*$porPagina;
$st=$pdo->prepare("SELECT a.*,u.nombre AS usuario FROM $from LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE $whereSql ORDER BY a.id DESC LIMIT $porPagina OFFSET $offset");$st->execute($params);$rows=$st->fetchAll();
$acciones=['INSERT'=>'Creado','UPDATE'=>'Modificado','DELETE'=>'Eliminado'];
$nombres=['documentos'=>'Documentos','carpetas'=>'Carpetas','formularios_registros'=>'Formularios','empleados'=>'Personal','empleados_habilitaciones'=>'Habilitaciones','finanzas_trabajadores'=>'Personal de Finanzas','finanzas_checklist'=>'Checklist de Finanzas','finanzas_indicadores'=>'Indicadores de Finanzas','rrhh_vencimientos'=>'Vencimientos de RRHH','usuarios'=>'Usuarios','usuario_sector'=>'Permisos por sector'];
function auditoriaCampo(string $campo):string{$etiquetas=['datos_json'=>'Respuestas del formulario','storage_json'=>'Listas del formulario','revision'=>'Versión de edición','eliminado_lote'=>'Estado en papelera','sector_id'=>'Sector','usuario_id'=>'Persona','creado_por'=>'Creado por','actualizado_por'=>'Modificado por','carpeta_padre_id'=>'Carpeta superior','carpeta_id'=>'Carpeta','archivo'=>'Archivo','nombre_original'=>'Nombre del archivo','rol_sector'=>'Rol en el sector','puede_editar'=>'Permiso para gestionar','puede_aprobar'=>'Permiso para aprobar','puede_descargar'=>'Permiso para descargar'];return $etiquetas[$campo]??ucfirst(str_replace('_',' ',ltrim($campo,'#@~')));}
function auditoriaValor($valor):string{if($valor===null)return 'Sin valor';if(is_bool($valor))return $valor?'Sí':'No';$texto=is_scalar($valor)?(string)$valor:(string)json_encode($valor,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);return mb_strlen($texto)>3000?mb_substr($texto,0,3000).'… (valor extenso)':$texto;}
function auditoriaDiferencias(array $row):array{
 $antes=json_decode((string)$row['antes'],true);$despues=json_decode((string)$row['despues'],true);$antes=is_array($antes)?$antes:[];$despues=is_array($despues)?$despues:[];$out=[];
 foreach(array_unique(array_merge(array_keys($antes),array_keys($despues))) as $campo){
  if(preg_match('/password|contrase|csrf|token|secret/i',$campo))continue;$a=$antes[$campo]??null;$d=$despues[$campo]??null;if($a===$d)continue;
  if(in_array($campo,['datos_json','storage_json'],true)){
   $ja=json_decode((string)$a,true);$jd=json_decode((string)$d,true);
   if(is_array($ja)||is_array($jd)){
    $ja=is_array($ja)?$ja:[];$jd=is_array($jd)?$jd:[];
    foreach(array_unique(array_merge(array_keys($ja),array_keys($jd))) as $k){if(preg_match('/password|contrase|csrf|token|secret/i',(string)$k))continue;$va=$ja[$k]??null;$vd=$jd[$k]??null;if($va!==$vd)$out[]=['campo'=>auditoriaCampo($campo).' · '.auditoriaCampo((string)$k),'antes'=>$va,'despues'=>$vd];}
    continue;
   }
  }
  if($campo==='eliminado_lote'){$a=$a?'En papelera':'Disponible';$d=$d?'En papelera':'Disponible';}
  $out[]=['campo'=>auditoriaCampo($campo),'antes'=>$a,'despues'=>$d];
 }return $out;
}
function auditoriaPagina(int $pagina):string{$filtros=array_intersect_key($_GET,array_flip(['vista','tabla','usuario','desde','hasta']));$filtros['pagina']=$pagina;return app_url('/php/admin/auditoria.php').'?'.http_build_query($filtros);}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Historial de cambios | NASER</title><link rel="stylesheet" href="<?=asset('/style.css')?>"><link rel="stylesheet" href="<?=asset('/css/mejoras.css')?>"></head><body><div class="app"><?php sidebar($pdo,'auditoria');?><main class="content">
<header class="section-top"><div><p class="eyebrow">TRAZABILIDAD</p><h1>Historial de cambios</h1><p>Quién modificó cada registro, cuándo y qué valores cambiaron.</p></div><a class="btn secondary" href="<?=app_url('/php/admin/papelera.php')?>">Ver papelera</a></header>
<?php if(esAdmin()):?><nav class="module-tabs" aria-label="Tipo de historial"><a href="<?=app_url('/php/admin/auditoria.php')?>" <?=$vista==='cambios'?'aria-current="page"':''?>>Cambios detallados</a><a href="<?=app_url('/php/admin/auditoria.php?vista=actividad')?>" <?=$vista==='actividad'?'aria-current="page"':''?>>Actividad general</a></nav><?php endif;?>
<form method="get" class="mej-filters form-panel"><input type="hidden" name="vista" value="<?=h($vista)?>">
<?php if($vista==='cambios'):?><label>Sección<select name="tabla"><option value="">Todas las habilitadas</option><?php foreach($tablas as $t):?><option value="<?=h($t)?>" <?=$tabla===$t?'selected':''?>><?=h($nombres[$t]??$t)?></option><?php endforeach;?></select></label><?php endif;?>
<label>Persona<select name="usuario"><option value="">Todas</option><?php foreach($usuarios as $u):?><option value="<?=(int)$u['id']?>" <?=$usuario===(int)$u['id']?'selected':''?>><?=h($u['nombre'])?></option><?php endforeach;?></select></label><label>Desde<input type="date" name="desde" value="<?=h($desde)?>"></label><label>Hasta<input type="date" name="hasta" value="<?=h($hasta)?>"></label><div class="mej-filter-actions"><button class="btn primary">Filtrar</button><a class="btn secondary" href="<?=h(app_url('/php/admin/auditoria.php').'?vista='.$vista)?>">Limpiar</a></div></form>
<p class="muted"><?=$total?> movimiento(s) · Página <?=$pagina?> de <?=$paginas?>. El detalle se registra desde la activación de esta mejora.</p>
<?php if(!$rows):?><section class="table-panel"><p class="empty">No hay cambios que coincidan con los filtros.</p></section><?php endif;?>
<?php foreach($rows as $r):?><article class="table-panel mej-audit-entry"><header class="mej-entry-head"><div><strong><?=h($r['usuario']??'Sistema')?></strong> <span class="muted"><?=h($r[$vista==='actividad'?'fecha':'creado_en'])?></span></div><span class="count-pill"><?=h($acciones[$r['accion']]??$r['accion'])?></span></header>
<?php if($vista==='actividad'):?><p><?=h($r['detalle'])?></p><?php else:$cambios=auditoriaDiferencias($r);?><h2 class="mej-entry-title"><?=h($nombres[$r['tabla']]??$r['tabla'])?> · Registro #<?=(int)$r['registro_id']?></h2><details><summary>Ver <?=count($cambios)?> campo(s) modificado(s)</summary><div class="table-wrap"><table class="mej-diff-table"><thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody><?php foreach($cambios as $c):?><tr><th scope="row"><?=h($c['campo'])?></th><td><pre><?=h(auditoriaValor($c['antes']))?></pre></td><td><pre><?=h(auditoriaValor($c['despues']))?></pre></td></tr><?php endforeach;?></tbody></table></div></details><?php endif;?></article><?php endforeach;?>
<nav class="mej-pagination" aria-label="Páginas del historial"><?php if($pagina>1):?><a class="btn secondary" href="<?=h(auditoriaPagina($pagina-1))?>">← Anterior</a><?php endif;?><span>Página <?=$pagina?> de <?=$paginas?></span><?php if($pagina<$paginas):?><a class="btn secondary" href="<?=h(auditoriaPagina($pagina+1))?>">Siguiente →</a><?php endif;?></nav></main></div></body></html>
