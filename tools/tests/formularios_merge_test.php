<?php
/** Integration test. Run: php tools/tests/formularios_merge_test.php --qa-only
 * Writes only to 127.0.0.1:18079 and naser_qa_mejoras on 127.0.0.1:13379. */
if (PHP_SAPI !== 'cli' || !in_array('--qa-only', $argv ?? [], true)) {
 if (PHP_SAPI !== 'cli') http_response_code(404);
 exit("Solo CLI y con --qa-only: esta prueba crea registros QA.\n");
}
if (!extension_loaded('curl') || !extension_loaded('pdo_mysql')) throw new RuntimeException('Se requieren curl y pdo_mysql.');

$pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname=naser_qa_mejoras;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'naser_qa_mejoras') throw new RuntimeException('Base no autorizada: solo QA.');
$n=0;function ck(bool $ok,string $msg){global $n;if(!$ok)throw new RuntimeException('FAIL: '.$msg);$n++;echo 'OK: '.$msg.PHP_EOL;}
$base='http://127.0.0.1:18079';$jar=tempnam(__DIR__.'/../../logs','naser_qa_forms_');register_shutdown_function(static function()use($jar){if(is_file($jar))unlink($jar);});
function req(string $path,$data=null,bool $json=false):array{global $base,$jar;$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>20]);if($data!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$json?json_encode($data):http_build_query($data));if($json)curl_setopt($c,CURLOPT_HTTPHEADER,['Content-Type: application/json','X-CSRF-Token: '.($GLOBALS['csrf']??'')]);}$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));$hs=curl_getinfo($c,CURLINFO_HEADER_SIZE);$body=substr($raw,$hs);$respuesta=['status'=>curl_getinfo($c,CURLINFO_RESPONSE_CODE),'body'=>$body,'json'=>json_decode($body,true)];unset($c);return $respuesta;}

$prefix='qa-merge-'.bin2hex(random_bytes(5));$email=$prefix.'@example.test';$pass='QA-merge-only-'.bin2hex(random_bytes(10));
$pdo->prepare("INSERT INTO usuarios(nombre,email,password,rol,activo,recibe_mails,debe_cambiar_password) VALUES ('QA Merge',?,?,'admin',1,0,0)")->execute([$email,password_hash($pass,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
try {
$r=req('/php/login.php');preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);$GLOBALS['csrf']=$m[1]??'';$r=req('/php/login.php',['csrf'=>$GLOBALS['csrf'],'email'=>$email,'password'=>$pass]);ck($r['status']===302,'QA merge login');
$rrhh=(int)$pdo->query("SELECT id FROM sectores WHERE slug='rrhh'")->fetchColumn();$compras=(int)$pdo->query("SELECT id FROM sectores WHERE slug='compras'")->fetchColumn();
$tool='operaciones-diagrama-trabajo';$st=$pdo->prepare('SELECT COUNT(*) FROM formularios_registros WHERE formulario=? AND eliminado_lote IS NULL');$st->execute([$tool]);if((int)$st->fetchColumn())throw new RuntimeException('La herramienta QA debe estar vacía para esta prueba.');
$p=['accion'=>'herramienta_guardar','formulario'=>$tool,'datos'=>['#nombre'=>$prefix],'storage'=>['lista'=>'[1]']];
$r=req('/php/formularios/api.php',$p,true);ck($r['status']===200&&$r['json']['estado']==='aprobado'&&$r['json']['revision']===1,'Shared tool starts saved without approval');$tid=(int)$r['json']['id'];ck(!$r['json']['permisos']['enviar']&&!$r['json']['permisos']['aprobar']&&!$r['json']['permisos']['reabrir']&&!$r['json']['permisos']['eliminar'],'Tools have no workflow permissions');
$r=req('/php/formularios/api.php',$p,true);ck($r['status']===200&&(int)$r['json']['id']===$tid&&$r['json']['repetido'],'Lost initial tool response retries same row');
$p['id']=$tid;$p['revision']=1;$p['datos']['#nombre']=$prefix.'-edit';$r=req('/php/formularios/api.php',$p,true);ck($r['status']===200&&$r['json']['revision']===2,'Tool increments integer revision');
$p['datos']['#nombre']='stale';$r=req('/php/formularios/api.php',$p,true);ck($r['status']===409&&$r['json']['conflicto'],'Stale tool save conflicts');ck(json_decode($pdo->query('SELECT datos_json FROM formularios_registros WHERE id='.$tid)->fetchColumn(),true)['#nombre']===$prefix.'-edit','Stale tool cannot overwrite current data');
foreach(['guardar','enviar','duplicar','eliminar','aprobar','reabrir','guardar_copia'] as $a){$q=$p;$q['accion']=$a;$q['revision']=2;$q['client_token']=bin2hex(random_bytes(20));$r=req('/php/formularios/api.php',$q,true);ck($r['status']===400,'Tool rejects workflow action '.$a);}
$r=req('/php/formularios/herramienta.php?sector=rrhh');ck($r['status']===200&&str_contains($r['body'],'"directo":true')&&!str_contains($r['body'],'data-accion="enviar"'),'Tool page uses shared autosave with no submit button');
$r=req('/php/formularios/index.php?sector=rrhh');ck($r['status']===200&&!str_contains($r['body'],'<small>#'.$tid.'</small>')&&!str_contains($r['body'],'Diagrama de trabajo — Cronograma mensual'),'Tool excluded from forms catalog');
$off=['naser_offline'=>1,'offline_id'=>'qa-offline-'.bin2hex(random_bytes(12)),'formulario'=>'hseq-minuta-reunion','referencia'=>$prefix,'datos'=>['#nombre'=>'Campo offline'],'storage'=>['nativo'=>'[1]']];
$r=req('/php/formularios/api.php',['accion'=>'importar','archivo'=>$off],true);ck($r['status']===200&&$r['json']['estado']==='borrador'&&isset($r['json']['url'],$r['json']['titulo']),'Offline import creates draft with URL');$oid=(int)$r['json']['id'];
$r=req('/php/formularios/api.php',['accion'=>'importar','archivo'=>$off],true);ck($r['status']===200&&(int)$r['json']['id']===$oid&&$r['json']['repetido'],'Same offline import is idempotent');
$off['datos']['#nombre']='changed export';$r=req('/php/formularios/api.php',['accion'=>'importar','archivo'=>$off],true);ck($r['status']===409,'Changed offline file cannot replace imported draft');
$off['formulario']=$tool;$r=req('/php/formularios/api.php',['accion'=>'importar','archivo'=>$off],true);ck($r['status']===400,'Offline import cannot create shared tools');
$r=req('/php/formularios/offline.php?f=hseq-minuta-reunion');ck($r['status']===200&&str_contains($r['body'],'__NASER_OFFLINE_SAVE__')&&str_contains($r['body'],'naserOffNuevo'),'Offline download includes isolated persistence and new form control');
$pdo->prepare("UPDATE usuarios SET rol='usuario' WHERE id=?")->execute([$uid]);
$pdo->prepare("INSERT INTO usuario_sector(usuario_id,sector_id,rol_sector,puede_ver,puede_editar,puede_aprobar,puede_descargar) VALUES (?,?,'responsable',1,1,0,0),(?,?,'observador',1,0,0,0)")->execute([$uid,$rrhh,$uid,$compras]);
$pedido=['accion'=>'guardar','formulario'=>'compras-pedido-materiales','client_token'=>bin2hex(random_bytes(20)),'datos'=>['#nombre'=>$prefix],'storage'=>[]];$r=req('/php/formularios/api.php',$pedido,true);ck($r['status']===200,'Responsible from another sector can create materials request');$pid=(int)$r['json']['id'];
$pedido['id']=$pid;$pedido['revision']=1;$pedido['datos']['#nombre']='Updated request';$r=req('/php/formularios/api.php',$pedido,true);ck($r['status']===200&&$r['json']['revision']===2,'External responsible edits own materials request');
$pedido['accion']='duplicar';$pedido['revision']=2;$pedido['client_token']=bin2hex(random_bytes(20));$r=req('/php/formularios/api.php',$pedido,true);ck($r['status']===200&&$r['json']['id']!==$pid,'External responsible duplicates own materials request');
$pedido['accion']='enviar';$r=req('/php/formularios/api.php',$pedido,true);ck($r['status']===200&&$r['json']['estado']==='enviado','External responsible submits materials request to owner sector');
$r=req('/php/formularios/api.php',['accion'=>'aprobar','id'=>$pid,'revision'=>3],true);ck($r['status']===403,'External responsible cannot approve owner sector request');
$other=['accion'=>'guardar','formulario'=>'compras-alta-proveedores','client_token'=>bin2hex(random_bytes(20)),'datos'=>['#nombre'=>'Forbidden'],'storage'=>[]];$r=req('/php/formularios/api.php',$other,true);ck($r['status']===403,'Materials exception does not grant other purchases forms');
$r=req('/php/formularios/offline.php?f=compras-pedido-materiales');ck($r['status']===403,'Offline download requires separate download permission');
$pdo->prepare('UPDATE usuario_sector SET puede_descargar=1 WHERE usuario_id=? AND sector_id=?')->execute([$uid,$compras]);$r=req('/php/formularios/offline.php?f=compras-pedido-materiales');ck($r['status']===200,'Authorized external responsible downloads materials template');
$off['formulario']='compras-pedido-materiales';$off['offline_id']='qa-pedido-'.bin2hex(random_bytes(12));$r=req('/php/formularios/api.php',['accion'=>'importar','archivo'=>$off],true);ck($r['status']===200,'External responsible imports materials file');
echo 'TOTAL: '.$n.' passed. QA marker: '.$prefix.PHP_EOL;
} finally {
$st=$pdo->prepare('SELECT id FROM formularios_registros WHERE creado_por=?');$st->execute([$uid]);foreach($st->fetchAll(PDO::FETCH_COLUMN) as $rid){$pdo->prepare('DELETE FROM formularios_historial WHERE registro_id=?')->execute([$rid]);$pdo->prepare('DELETE FROM formularios_registros WHERE id=? AND creado_por=?')->execute([$rid,$uid]);}
// Se conserva el usuario QA para mantener referencias de auditoría.
$pdo->prepare('UPDATE usuarios SET activo=0 WHERE id=?')->execute([$uid]);
}
