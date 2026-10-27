<?php
// Regresión de PR14: solo usuarios y cambios temporales del esquema local de QA.
$base='http://127.0.0.1:18079';
$pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname=naser_qa_mejoras;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='naser_qa_mejoras')throw new RuntimeException('Solo se permite el esquema QA.');
$prefix='qa-pr14-'.bin2hex(random_bytes(5));$total=0;$users=[];$jars=[];
$pass='QA-pr14-clave-985632';$sectorIds=[];
foreach($pdo->query('SELECT id,slug FROM sectores')->fetchAll()as$row)$sectorIds[$row['slug']]=(int)$row['id'];
$hseqId=$sectorIds['hseq'];$hseqRestringido=(int)$pdo->query('SELECT restringido FROM sectores WHERE id='.$hseqId)->fetchColumn();
function ok14(bool $ok,string $label):void{global$total;if(!$ok)throw new RuntimeException('FAIL: '.$label);$total++;echo 'OK: '.$label.PHP_EOL;}
function req14(array $s,string $path,?array $post=null):array{
 global$base;$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$s['jar'],CURLOPT_COOKIEFILE=>$s['jar'],CURLOPT_TIMEOUT=>25]);
 if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$post);}
 $r=curl_exec($c);if($r===false)throw new RuntimeException(curl_error($c));$n=curl_getinfo($c,CURLINFO_HEADER_SIZE);$out=['code'=>curl_getinfo($c,CURLINFO_RESPONSE_CODE),'head'=>substr($r,0,$n),'body'=>substr($r,$n)];unset($c);return$out;
}
function user14(string $name,array $roles,bool $admin=false):array{
 global$pdo,$users,$jars,$prefix,$pass,$sectorIds;$email=$prefix.'-'.strtolower($name).'@example.test';
 $pdo->prepare('INSERT INTO usuarios(nombre,email,password,rol,activo,recibe_mails,debe_cambiar_password) VALUES(?,?,?,?,1,0,0)')->execute(['QA '.$name,$email,password_hash($pass,PASSWORD_DEFAULT),$admin?'admin':'usuario']);$id=(int)$pdo->lastInsertId();$users[]=$id;
 foreach($roles as$slug=>$role)$pdo->prepare('INSERT INTO usuario_sector(usuario_id,sector_id,rol_sector,puede_ver,puede_editar,puede_aprobar,puede_descargar) VALUES(?,?,?,1,?,?,1)')->execute([$id,$sectorIds[$slug],$role,$role==='responsable'?1:0,$role==='responsable'?1:0]);
 $jar=tempnam(__DIR__.'/../../logs','naser_qa_pr14_');$jars[]=$jar;$s=['jar'=>$jar,'id'=>$id];$r=req14($s,'/php/login.php');preg_match('/name="csrf"[^>]*value="([^"]+)"/',$r['body'],$m);$s['csrf']=html_entity_decode($m[1]??'',ENT_QUOTES,'UTF-8');
 $r=req14($s,'/php/login.php',['csrf'=>$s['csrf'],'email'=>$email,'password'=>$pass]);ok14($r['code']===302&&str_contains($r['head'],'dashboard.php'),'Inicio '.$name.' HTTP'.$r['code'].' '.(preg_match('/class="alert error"[^>]*>(.*?)<\/div>/s',$r['body'],$err)?strip_tags($err[1]):''));return$s;
}
function visible14(array $s,string $slug,bool $allowed,string $label):array{
 $r=req14($s,'/php/sector.php?sector='.urlencode($slug));ok14($r['code']===($allowed?200:403),$label.' HTTP'.$r['code']);
 if($allowed)ok14(!preg_match('/Fatal error|Warning:|Uncaught|Parse error/',$r['body']),$label.' sin errores PHP');return$r;
}
function hasLink14(string $html,string $path):bool{
 $dom=new DOMDocument();@$dom->loadHTML($html);foreach($dom->getElementsByTagName('a')as$a)if(parse_url($a->getAttribute('href'),PHP_URL_PATH)===$path)return true;return false;
}
try{
 // Solo QA: dejar público HSEQ durante la primera parte y restaurar el valor original al terminar.
 $pdo->exec('UPDATE sectores SET restringido=0 WHERE id='.$hseqId);
 $op=user14('operador',['operaciones'=>'operador','rrhh'=>'observador','finanzas'=>'observador','compras'=>'observador']);
 $opHr=user14('operadorrrhh',['rrhh'=>'operador']);
 $observer=user14('observador',['rrhh'=>'observador','finanzas'=>'observador','compras'=>'observador','ventas'=>'observador','hseq'=>'observador']);
 $mixed=user14('mixto',['operaciones'=>'operador','ventas'=>'responsable']);
 $hseq=user14('responsablehseq',['hseq'=>'responsable']);
 $opsResp=user14('responsableops',['operaciones'=>'responsable']);
 $admin=user14('admin',[],true);
 foreach(['operaciones','mantenimiento','hseq','sgi']as$slug)visible14($op,$slug,true,'Operador puro ve '.$slug);
 foreach(['rrhh','finanzas','compras','ventas','gerencia']as$slug)visible14($op,$slug,false,'Operador puro no ve '.$slug.' aunque tenga observación asignada');
 $home=req14($op,'/php/dashboard.php');ok14($home['code']===200&&!str_contains($home['body'],'sector=rrhh')&&!str_contains($home['body'],'sector=finanzas')&&!str_contains($home['body'],'sector=compras'),'Menú y panel del operador respetan filtro de tres sectores');
 $r=visible14($op,'hseq',true,'Documentación HSEQ permanece accesible al operador');ok14(!hasLink14($r['body'],'/php/hseq.php'),'Operador no ve botón de gestión HSEQ');
 ok14(req14($op,'/php/hseq.php')['code']===403,'URL de gestión HSEQ denegada a operador');
 ok14(req14($op,'/php/formularios/index.php?sector=hseq')['code']===200,'Restricción de gestión no bloquea catálogo de formularios visible');
 foreach(['/php/rrhh.php','/php/finanzas.php','/php/compras.php','/php/ventas.php','/php/hseq.php']as$path)ok14(req14($observer,$path)['code']===403,'Observador no accede a gestión '.$path);
 foreach(['rrhh','finanzas','compras','ventas','hseq']as$slug)visible14($observer,$slug,true,'Observador conserva consulta documental '.$slug);
 $r=visible14($observer,'ventas',true,'Observador consulta Ventas');ok14(!hasLink14($r['body'],'/php/ventas.php'),'Consulta Ventas no muestra botón comercial sin gestión');
 visible14($mixed,'compras',true,'Operador con responsabilidad en otro sector sale del filtro de tres');
 visible14($mixed,'ventas',true,'Responsable mixto consulta su sector');
 foreach(['rrhh','finanzas']as$slug)visible14($mixed,$slug,false,'Responsable mixto sigue sin acceso privado a '.$slug);
 ok14(req14($mixed,'/php/ventas.php')['code']===200,'Responsable mixto puede gestionar su sector autorizado');
 $pdo->prepare('UPDATE usuario_sector SET puede_editar=0 WHERE usuario_id=? AND sector_id=?')->execute([$hseq['id'],$hseqId]);
 $r=visible14($hseq,'hseq',true,'Responsable sin capacidad de gestionar conserva consulta');ok14(!hasLink14($r['body'],'/php/hseq.php'),'Capacidad de gestión revocada oculta botón');
 ok14(req14($hseq,'/php/hseq.php')['code']===403,'Rol Responsable no evade capacidad de gestionar revocada');
 $pdo->prepare('UPDATE usuario_sector SET puede_editar=1 WHERE usuario_id=? AND sector_id=?')->execute([$hseq['id'],$hseqId]);
 ok14(req14($hseq,'/php/hseq.php')['code']===200,'Cambio de capacidad habilita gestión en la siguiente petición');
 // La pertenencia a uno de los tres sectores no debe saltarse el indicador restringido.
 $pdo->exec('UPDATE sectores SET restringido=1 WHERE id='.$hseqId);
 visible14($op,'hseq',false,'Operador rechaza HSEQ restringido sin pertenencia');
 visible14($mixed,'hseq',false,'Responsable de otro sector respeta HSEQ restringido');
 visible14($admin,'hseq',true,'Super usuario conserva acceso a restringidos');
 $pdo->prepare("INSERT INTO usuario_sector(usuario_id,sector_id,rol_sector,puede_ver,puede_editar,puede_aprobar,puede_descargar) VALUES(?,?,'operador',1,0,0,1)")->execute([$op['id'],$hseqId]);
 visible14($op,'hseq',true,'Operador asignado accede a HSEQ restringido');
 ok14(req14($op,'/php/hseq.php')['code']===403,'Pertenencia a restringido no concede gestión');
 ok14(req14($op,'/php/personal.php')['code']===403,'Operador de Operaciones con observación RRHH no accede a Personal');
 ok14(req14($opHr,'/php/personal.php')['code']===403,'Operador puro de RRHH tampoco evade el filtro al abrir Personal');
 $r=req14($observer,'/php/personal.php');ok14($r['code']===200&&str_contains($r['body'],'Legajos, licencias, cursos y exámenes médicos del personal.'),'Observador RRHH sin rol operador conserva consulta de Personal');
 $r=req14($opsResp,'/php/personal.php');ok14($r['code']===200&&str_contains($r['body'],'Habilitaciones y vencimientos del personal operativo.')&&!str_contains($r['body'],'<th>Teléfono</th>'),'Responsable operativo conserva habilitaciones sin datos personales');
 foreach(['rrhh','finanzas']as$slug)visible14($admin,$slug,true,'Super usuario mantiene acceso privado '.$slug);
 echo 'TOTAL: '.$total.' comprobaciones HTTP de PR14. Marcador '.$prefix.PHP_EOL;
}finally{
 $pdo->prepare('UPDATE sectores SET restringido=? WHERE id=?')->execute([$hseqRestringido,$hseqId]);
 if($users){$ids=implode(',',array_map('intval',$users));$pdo->exec('DELETE FROM notificaciones WHERE usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM actividad WHERE usuario_id IN ('.$ids.')');$pdo->exec('DELETE FROM usuarios WHERE id IN ('.$ids.')');}
 foreach($jars as$jar)if(is_file($jar))unlink($jar);
}
