<?php
// Solo fixtures propios en el servidor y esquema locales de QA de la integración.
$base='http://127.0.0.1:18079';
$pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname=naser_qa_mejoras;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='naser_qa_mejoras')throw new RuntimeException('Solo QA.');
$prefix='qa-auth-merge-'.bin2hex(random_bytes(5));$total=0;$jars=[];$users=[];$messageIds=[];
$pass='QA-inicial-128946';$ops=(int)$pdo->query("SELECT id FROM sectores WHERE slug='operaciones'")->fetchColumn();
function check(bool $ok,string $label):void{global$total;if(!$ok)throw new RuntimeException('FAIL: '.$label);$total++;echo 'OK: '.$label.PHP_EOL;}
function requestQa(array $ses,string $path,?array $post=null):array{
 global$base;$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$ses['jar'],CURLOPT_COOKIEFILE=>$ses['jar'],CURLOPT_TIMEOUT=>25]);
 if(str_contains($path,'/api.php'))curl_setopt($c,CURLOPT_HTTPHEADER,['Accept: application/json']);
 if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$post);}
 $r=curl_exec($c);if($r===false)throw new RuntimeException(curl_error($c));$n=curl_getinfo($c,CURLINFO_HEADER_SIZE);$out=['code'=>curl_getinfo($c,CURLINFO_RESPONSE_CODE),'head'=>substr($r,0,$n),'body'=>substr($r,$n)];unset($c);return$out;
}
function loginQa(array $u,string $password):array{
 global$jars;$jar=tempnam(__DIR__.'/../../logs','naser_qa_auth_');$jars[]=$jar;$ses=['jar'=>$jar];$r=requestQa($ses,'/php/login.php');preg_match('/name="csrf"[^>]*value="([^"]+)"/',$r['body'],$m);$ses['csrf']=html_entity_decode($m[1]??'',ENT_QUOTES,'UTF-8');
 $r=requestQa($ses,'/php/login.php',['csrf'=>$ses['csrf'],'email'=>$u['email'],'password'=>$password]);check($r['code']===302&&str_contains($r['head'],'dashboard.php'),'Inicio de fixture '.$u['name'].' HTTP'.$r['code'].' '.(preg_match('/class="alert error"[^>]*>(.*?)<\/div>/s',$r['body'],$qaErr)?strip_tags($qaErr[1]):'').' csrf '.($ses['csrf']!==''?'presente':'ausente'));return$ses;
}
function makeQa(string $name,bool $admin=false):array{
 global$pdo,$users,$prefix,$pass,$ops;$email=$prefix.'-'.$name.'@example.test';$pdo->prepare('INSERT INTO usuarios(nombre,email,password,rol,activo,recibe_mails,debe_cambiar_password) VALUES(?,?,?,?,1,0,0)')->execute(['QA '.$name,$email,password_hash($pass,PASSWORD_DEFAULT),$admin?'admin':'usuario']);$id=(int)$pdo->lastInsertId();$users[]=$id;
 $pdo->prepare("INSERT INTO usuario_sector(usuario_id,sector_id,rol_sector,puede_ver,puede_editar,puede_aprobar,puede_descargar) VALUES(?,?,'operador',1,0,0,1)")->execute([$id,$ops]);return['id'=>$id,'email'=>$email,'name'=>$name];
}
function latestMsg(int $author):int{global$pdo;$q=$pdo->prepare('SELECT MAX(id) FROM mensajes WHERE de_usuario_id=?');$q->execute([$author]);return(int)$q->fetchColumn();}
function msgCount(int $root):int{global$pdo;$q=$pdo->prepare('SELECT COUNT(*) FROM mensajes WHERE id=? OR respuesta_a=?');$q->execute([$root,$root]);return(int)$q->fetchColumn();}
try{
 $ua=makeQa('A');$ub=makeQa('B');$uc=makeQa('Admin',true);$ud=makeQa('Ajeno');
 $a=loginQa($ua,$pass);$b=loginQa($ub,$pass);$c=loginQa($uc,$pass);$d=loginQa($ud,$pass);
 $secret=$prefix.' contenido privado';$r=requestQa($a,'/php/mensajes.php',['csrf'=>$a['csrf'],'accion'=>'enviar','para'=>'u:'.$ub['id'],'de_sector'=>99999,'asunto'=>$prefix,'cuerpo'=>$secret]);$root=latestMsg($ua['id']);$messageIds[]=$root;check($r['code']===302&&$root>0,'Mensaje privado se envía a la persona elegida');
 $m=$pdo->query('SELECT * FROM mensajes WHERE id='.$root)->fetch();check((int)$m['para_usuario_id']===$ub['id']&&$m['para_sector_id']===null&&(int)$m['de_sector_id']===$ops,'Destinatario personal y firma sectorial se controlan en servidor');
 foreach([['ses'=>$d,'name'=>'Tercero del mismo sector'],['ses'=>$c,'name'=>'Super usuario ajeno']]as$x){$r=requestQa($x['ses'],'/php/mensajes.php?ver='.$root);check($r['code']===200&&!str_contains($r['body'],$secret)&&str_contains($r['body'],'Conversación no encontrada'),$x['name'].' no puede leer la conversación');}
 $r=requestQa($a,'/php/mensajes.php',['csrf'=>$a['csrf'],'accion'=>'responder','raiz'=>$root,'cuerpo'=>'No debe guardarse']);check($r['code']===200&&str_contains($r['body'],'No podés responder')&&msgCount($root)===1,'Emisor no se puede autoresponder');
 $r=requestQa($b,'/php/mensajes.php?ver='.$root);check($r['code']===200&&str_contains($r['body'],$secret),'Destinatario puede leer el mensaje');$q=$pdo->prepare('SELECT COUNT(*) FROM mensajes_leidos WHERE mensaje_id=? AND usuario_id=?');$q->execute([$root,$ub['id']]);check((int)$q->fetchColumn()===1,'Lectura se registra solo para el destinatario fixture');
 $r=requestQa($b,'/php/mensajes.php',['csrf'=>$b['csrf'],'accion'=>'responder','raiz'=>$root,'cuerpo'=>'Respuesta B']);check($r['code']===302&&msgCount($root)===2,'Destinatario responde dentro del hilo');
 $r=requestQa($b,'/php/mensajes.php',['csrf'=>$b['csrf'],'accion'=>'responder','raiz'=>$root,'cuerpo'=>'Segunda respuesta B']);check($r['code']===200&&str_contains($r['body'],'No podés responder')&&msgCount($root)===2,'Último autor no puede volver a responder');
 $r=requestQa($a,'/php/mensajes.php',['csrf'=>$a['csrf'],'accion'=>'responder','raiz'=>$root,'cuerpo'=>'Respuesta A']);check($r['code']===302&&msgCount($root)===3,'La otra persona puede continuar la conversación');
 $q=$pdo->prepare('SELECT id FROM mensajes WHERE respuesta_a=?');$q->execute([$root]);$messageIds=array_merge($messageIds,array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)));
 $r=requestQa($a,'/php/mensajes.php',['csrf'=>$a['csrf'],'accion'=>'enviar','para'=>'u:'.$ua['id'],'asunto'=>'A sí mismo','cuerpo'=>'Texto a conservar']);check($r['code']===200&&str_contains($r['body'],'esa persona no está disponible')&&str_contains($r['body'],'Texto a conservar'),'No se puede crear mensaje a uno mismo y el texto se conserva');
 $r=requestQa($a,'/php/mensajes.php',['csrf'=>$a['csrf'],'accion'=>'enviar','para'=>'s:'.$ops,'asunto'=>'Sector','cuerpo'=>'No sector']);check($r['code']===200&&str_contains($r['body'],'Elegí a quién le escribís'),'No permite nuevos mensajes a sectores');
 $r=requestQa($c,'/php/admin/usuarios.php');check($r['code']===200,'Super usuario fixture accede al panel');$pdo->prepare("UPDATE usuarios SET rol='usuario' WHERE id=?")->execute([$uc['id']]);$r=requestQa($c,'/php/admin/usuarios.php');check($r['code']===403,'Cambio de rol se aplica en la siguiente petición');
 $second=loginQa($ua,$pass);$newPass='QA-propia-975312';$r=requestQa($a,'/php/perfil.php',['csrf'=>$a['csrf'],'accion'=>'password','actual'=>$pass,'nueva'=>$newPass,'repetir'=>$newPass]);check($r['code']===200&&str_contains($r['body'],'Contraseña actualizada'),'Cambiar clave propia se guarda');
 $r=requestQa($a,'/php/formularios/api.php?sesion=1');$j=json_decode($r['body'],true);check($r['code']===200&&($j['usuario_id']??0)===$ua['id'],'Sesión que cambia su clave sigue vigente');$r=requestQa($second,'/php/formularios/api.php?sesion=1');check($r['code']===401&&is_array(json_decode($r['body'],true)),'Cambio propio revoca otras sesiones con respuesta JSON');
 $tempPass='QA-reset-369852';$pdo->prepare('UPDATE usuarios SET password=?,debe_cambiar_password=1 WHERE id=?')->execute([password_hash($tempPass,PASSWORD_DEFAULT),$ub['id']]);$r=requestQa($b,'/php/formularios/api.php?sesion=1');check($r['code']===401,'Reset de contraseña revoca la sesión anterior');
 $b=loginQa($ub,$tempPass);$r=requestQa($b,'/php/formularios/api.php?sesion=1');$j=json_decode($r['body'],true);check($r['code']===403&&!empty($j['cambiar_password']),'Clave temporal exige cambio antes de usar API');
 $pdo->prepare('UPDATE usuarios SET activo=0 WHERE id=?')->execute([$uc['id']]);$r=requestQa($c,'/php/formularios/api.php?sesion=1');check($r['code']===401&&is_array(json_decode($r['body'],true)),'Desactivar cuenta corta acceso API de inmediato');
 $jar=file_get_contents($d['jar']);preg_match('/\tPHPSESSID\t([^\r\n]+)/',$jar,$sidMatch);$sid=trim($sidMatch[1]??'');if(!preg_match('/^[a-zA-Z0-9,-]+$/',$sid))throw new RuntimeException('SID QA no encontrado.');
 $sessionFile=dirname(__DIR__,3).'/test-sessions/sess_'.$sid;$serialized=file_get_contents($sessionFile);if(!str_contains($serialized,'usuario_id|i:'.$ud['id'].';'))throw new RuntimeException('La sesión no corresponde al fixture QA.');
 $expired=preg_replace('/ultima_actividad\|i:\d+;/','ultima_actividad|i:'.(time()-28801).';',$serialized,1,$changed);if($changed!==1)throw new RuntimeException('Sesión fixture sin actividad.');file_put_contents($sessionFile,$expired,LOCK_EX);
 $r=requestQa($d,'/php/formularios/api.php?sesion=1');check($r['code']===401&&is_array(json_decode($r['body'],true)),'Ocho horas de inactividad vencen sesión HTTP y devuelven JSON');
 echo 'TOTAL: '.$total.' comprobaciones HTTP de auth y mensajes. Marcador '.$prefix.PHP_EOL;
}finally{
 if($users){$ids=implode(',',array_map('intval',$users));$pdo->exec('DELETE FROM mensajes_leidos WHERE usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM mensajes WHERE respuesta_a IS NOT NULL AND de_usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM mensajes WHERE de_usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM notificaciones WHERE usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM actividad WHERE usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM usuarios WHERE id IN ('.$ids.')');}
 foreach($jars as$jar)if(is_file($jar))unlink($jar);
}
