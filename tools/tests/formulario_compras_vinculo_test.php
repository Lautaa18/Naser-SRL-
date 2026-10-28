<?php
/** Integration test. Run: php tools/tests/formulario_compras_vinculo_test.php --qa-only
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

$prefix='qa-form-link-'.bin2hex(random_bytes(5));
$email=$prefix.'@example.test';$pass='QA-link-only-'.bin2hex(random_bytes(12));
$pdo->prepare("INSERT INTO usuarios(nombre,email,password,rol,activo,recibe_mails,debe_cambiar_password) VALUES ('QA Linked Form',?,?,'admin',1,0,0)")->execute([$email,password_hash($pass,PASSWORD_DEFAULT)]);
$uid=(int)$pdo->lastInsertId();$id=0;
try {
 $r=req('/php/login.php');preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);$GLOBALS['csrf']=$m[1]??'';
 ck($r['status']===200&&$GLOBALS['csrf']!=='','QA login has CSRF');
 $r=req('/php/login.php',['csrf'=>$GLOBALS['csrf'],'email'=>$email,'password'=>$pass]);ck($r['status']===302,'QA linked-form admin authenticated');
 $p=['accion'=>'guardar','formulario'=>'compras-pedido-materiales','client_token'=>bin2hex(random_bytes(20)),
  'referencia'=>$prefix,'datos'=>['#solicitante'=>$prefix,'#numeroPedido'=>$prefix],'storage'=>[]];
 $r=req('/php/formularios/api.php',$p,true);ck($r['status']===200&&($r['json']['ok']??false),'Create materials form');$id=(int)$r['json']['id'];
 ck($r['json']['revision']===1,'Materials form starts at revision one');
 $r=req('/php/compras.php',['csrf'=>$GLOBALS['csrf'],'accion'=>'importar_pedido_digital','formulario_registro_id'=>$id]);ck($r['status']===303,'Purchases links digital form');
 $st=$pdo->prepare('SELECT id,codigo FROM compras WHERE formulario_registro_id=?');$st->execute([$id]);$compra=$st->fetch();ck((bool)$compra,'Purchases stores source link');
 $before=$pdo->query('SELECT * FROM formularios_registros WHERE id='.$id)->fetch();
 $hist=(int)$pdo->query('SELECT COUNT(*) FROM formularios_historial WHERE registro_id='.$id)->fetchColumn();
 $r=req('/php/formularios/api.php',['accion'=>'eliminar','id'=>$id,'revision'=>1],true);
 ck($r['status']===409&&!($r['json']['ok']??true)&&str_contains($r['json']['error']??'',(string)$compra['codigo']),'Linked form deletion returns 409 and purchase code');
 ck(str_contains($r['json']['error'],'seguimiento de Compras'),'Linked form error explains how to unlink');
 $after=$pdo->query('SELECT * FROM formularios_registros WHERE id='.$id)->fetch();
 ck($after===$before,'Rejected deletion preserves the entire source row and revision');
 ck((int)$pdo->query('SELECT COUNT(*) FROM formularios_historial WHERE registro_id='.$id)->fetchColumn()===$hist,'Rejected deletion preserves history');
 ck((int)$pdo->query("SELECT COUNT(*) FROM papelera WHERE tipo='formulario' AND registro_id=".$id)->fetchColumn()===0,'Rejected deletion does not create a trash entry');
 ck((int)$pdo->query('SELECT formulario_registro_id FROM compras WHERE id='.(int)$compra['id'])->fetchColumn()===$id,'Rejected deletion preserves purchase link');
 $r=req('/php/formularios/llenar.php?id='.$id);ck($r['status']===200,'Linked form remains available');
 $r=req('/php/compras.php',['csrf'=>$GLOBALS['csrf'],'accion'=>'eliminar','id'=>(int)$compra['id']]);ck($r['status']===303,'Remove purchase tracking');
 $st=$pdo->prepare('SELECT COUNT(*) FROM compras WHERE formulario_registro_id=?');$st->execute([$id]);ck((int)$st->fetchColumn()===0,'Purchase link is removed');
 $r=req('/php/formularios/api.php',['accion'=>'eliminar','id'=>$id,'revision'=>1],true);ck($r['status']===200&&$r['json']['estado']==='papelera','Unlinked form uses recoverable trash');
 $after=$pdo->query('SELECT * FROM formularios_registros WHERE id='.$id)->fetch();ck($after['eliminado_lote']!==null&&(int)$after['revision']===2,'Unlinked deletion preserves row and advances revision');
 ck((int)$pdo->query('SELECT COUNT(*) FROM formularios_historial WHERE registro_id='.$id)->fetchColumn()===$hist+1,'Unlinked deletion records trash history');
 $r=req('/php/formularios/llenar.php?id='.$id);ck($r['status']===404,'Trashed source is unavailable in editor');
 echo 'TOTAL: '.$n.' passed. QA marker: '.$prefix.PHP_EOL;
} finally {
 // The QA database and all rows below are scoped to the random test user.
 $st=$pdo->prepare('SELECT id FROM compras WHERE creado_por=?');$st->execute([$uid]);foreach($st->fetchAll(PDO::FETCH_COLUMN) as $cid){
  $pdo->prepare('DELETE FROM compras_documentos WHERE compra_id=?')->execute([$cid]);$pdo->prepare('DELETE FROM compras_encuesta WHERE compra_id=?')->execute([$cid]);$pdo->prepare('DELETE FROM compras WHERE id=? AND creado_por=?')->execute([$cid,$uid]);
 }
 $st=$pdo->prepare('SELECT id FROM formularios_registros WHERE creado_por=?');$st->execute([$uid]);foreach($st->fetchAll(PDO::FETCH_COLUMN) as $rid){
  $pdo->prepare("DELETE FROM papelera WHERE tipo='formulario' AND registro_id=? AND usuario_id=?")->execute([$rid,$uid]);
  $pdo->prepare('DELETE FROM formularios_historial WHERE registro_id=?')->execute([$rid]);$pdo->prepare('DELETE FROM formularios_registros WHERE id=? AND creado_por=?')->execute([$rid,$uid]);
 }
 $pdo->prepare('UPDATE usuarios SET activo=0 WHERE id=?')->execute([$uid]);
}
