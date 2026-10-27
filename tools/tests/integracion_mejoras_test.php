<?php
// Solo QA local: crea y elimina una base con nombre aleatorio, nunca usa db.php.
ob_start(); putenv('APP_ENV=dev');
$sesiones=sys_get_temp_dir().'/naser-qa-sesiones';if(!is_dir($sesiones))mkdir($sesiones,0700,true);session_save_path($sesiones);
require __DIR__.'/../../php/config/auth.php';
require __DIR__.'/../../php/config/bootstrap.php';
require __DIR__.'/../../php/config/documentos_storage.php';
$op=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$servidor=new PDO('mysql:host=127.0.0.1;port=13379;charset=utf8mb4','root','',$op);
$base='naser_qa_integracion_'.bin2hex(random_bytes(5));if(!preg_match('/^naser_qa_integracion_[a-f0-9]{10}$/',$base))throw new RuntimeException('Base insegura');
$servidor->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$total=0;
function comprobar(bool $ok,string $nombre):void{global $total;if(!$ok)throw new RuntimeException('FALLÓ: '.$nombre);$total++;echo 'OK: '.$nombre.PHP_EOL;}
try{
 $pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname='.$base.';charset=utf8mb4','root','',$op);configurarZonaHorariaDB($pdo);naser_bootstrap($pdo);
 comprobar($pdo->query("SELECT valor FROM sistema_config WHERE clave='schema_version'")->fetchColumn()===NASER_SCHEMA_VERSION,'Instalación completa');
 $sid=(int)$pdo->query("SELECT id FROM sectores WHERE slug='rrhh'")->fetchColumn();$admin=(int)$pdo->query("SELECT id FROM usuarios WHERE rol='admin' LIMIT 1")->fetchColumn();$_SESSION=['usuario_id'=>$admin,'rol'=>'admin'];$pdo->exec('SET @naser_actor='.$admin);
 $pdo->exec("INSERT INTO empleados(legajo,apellido_nombre,dni) VALUES('QA-CENTRAL','Maestro Original','111')");$empleado=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO empleados_habilitaciones(empleado_id,tipo,fecha_vencimiento) VALUES(?,'licencia_conducir','2029-01-01')")->execute([$empleado]);
 $pdo->exec("INSERT INTO finanzas_trabajadores(legajo,nombre_completo,dni,sector,vencimiento_carnet,vencimiento_defensivo) VALUES('QA-CENTRAL','Legado Financiero','222','Finanzas','2030-01-01','2031-01-01')");$finanzas=(int)$pdo->lastInsertId();
 naser_consolidar_personal($pdo);
 comprobar((int)$pdo->query('SELECT empleado_id FROM finanzas_trabajadores WHERE id='.$finanzas)->fetchColumn()===$empleado,'Legajo vinculado al maestro existente');
 comprobar($pdo->query('SELECT apellido_nombre FROM empleados WHERE id='.$empleado)->fetchColumn()==='Maestro Original','No sobrescribe nombre maestro');
 comprobar($pdo->query("SELECT fecha_vencimiento FROM empleados_habilitaciones WHERE empleado_id=$empleado AND tipo='licencia_conducir'")->fetchColumn()==='2029-01-01','Conserva licencia maestra');
 comprobar($pdo->query("SELECT fecha_vencimiento FROM empleados_habilitaciones WHERE empleado_id=$empleado AND tipo='manejo_defensivo'")->fetchColumn()==='2031-01-01','Integra licencia antes inexistente');
 $d=json_decode($pdo->query('SELECT diferencias_json FROM personal_conciliacion WHERE finanzas_id='.$finanzas)->fetchColumn(),true);
 comprobar(isset($d['apellido_nombre'],$d['dni'],$d['licencia_conducir']),'Diferencias listas para conciliación explícita');
 naser_consolidar_personal($pdo);comprobar((int)$pdo->query("SELECT COUNT(*) FROM empleados WHERE legajo='QA-CENTRAL'")->fetchColumn()===1,'Consolidación repetida sin duplicados');
 $antes=(int)$pdo->query("SELECT COUNT(*) FROM auditoria_cambios WHERE tabla='empleados' AND registro_id=$empleado")->fetchColumn();
 $pdo->exec("UPDATE empleados SET apellido_nombre='maestro original' WHERE id=$empleado");
 $a=$pdo->query("SELECT * FROM auditoria_cambios WHERE tabla='empleados' AND registro_id=$empleado ORDER BY id DESC LIMIT 1")->fetch();
 comprobar((int)$pdo->query("SELECT COUNT(*) FROM auditoria_cambios WHERE tabla='empleados' AND registro_id=$empleado")->fetchColumn()===$antes+1,'Auditoría distingue mayúsculas');
 comprobar((int)$a['usuario_id']===$admin&&json_decode($a['antes'],true)['apellido_nombre']==='Maestro Original'&&json_decode($a['despues'],true)['apellido_nombre']==='maestro original','Auditoría conserva actor y antes/después');
 $pdo->prepare('INSERT INTO carpetas(sector_id,nombre) VALUES(?,?)')->execute([$sid,'QA padre']);$padre=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO carpetas(sector_id,nombre,carpeta_padre_id,activa) VALUES(?,?,?,0)')->execute([$sid,'QA inactiva',$padre]);$inactiva=(int)$pdo->lastInsertId();
 try{validarCarpetaSector($pdo,$sid,$inactiva);comprobar(false,'Inactiva rechazada');}catch(RuntimeException $e){comprobar(true,'No permite cargar en carpeta inactiva');}
 $pdo->prepare('INSERT INTO carpetas(sector_id,nombre,carpeta_padre_id) VALUES(?,?,?)')->execute([$sid,'QA hija',$padre]);$hija=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO documentos(sector_id,carpeta_id,titulo,fecha_actualizacion) VALUES(?,?,'QA documento',CURDATE())")->execute([$sid,$hija]);$doc=(int)$pdo->lastInsertId();
 $pdo->beginTransaction();$lote=enviarPapelera($pdo,'carpeta',$padre,$sid,'QA padre');
 $pdo->prepare('UPDATE carpetas SET activa=0,eliminado_lote=? WHERE id IN (?,?)')->execute([$lote,$padre,$hija]);
 $pdo->prepare('UPDATE documentos SET activo=0,eliminado_lote=? WHERE id=?')->execute([$lote,$doc]);$pdo->commit();
 $trash=(int)$pdo->query('SELECT MAX(id) FROM papelera')->fetchColumn();restaurarPapelera($pdo,$trash);
 comprobar((int)$pdo->query('SELECT activa FROM carpetas WHERE id='.$padre)->fetchColumn()===1&&(int)$pdo->query('SELECT activa FROM carpetas WHERE id='.$hija)->fetchColumn()===1,'Restauración recupera estructura activa');
 comprobar((int)$pdo->query('SELECT activa FROM carpetas WHERE id='.$inactiva)->fetchColumn()===0,'Restauración no revive inactivas anteriores');
 comprobar((int)$pdo->query('SELECT activo FROM documentos WHERE id='.$doc)->fetchColumn()===1,'Restauración recupera documentos');
 $pdo->beginTransaction();$loteDoc=enviarPapelera($pdo,'documento',$doc,$sid,'QA separado');$pdo->prepare('UPDATE documentos SET activo=0,eliminado_lote=? WHERE id=?')->execute([$loteDoc,$doc]);$pdo->commit();$trashDoc=(int)$pdo->query('SELECT MAX(id) FROM papelera')->fetchColumn();
 $pdo->exec('UPDATE carpetas SET activa=0 WHERE id='.$hija);restaurarPapelera($pdo,$trashDoc);
 comprobar($pdo->query('SELECT carpeta_id FROM documentos WHERE id='.$doc)->fetchColumn()===null,'Documento con carpeta inactiva se recupera en raíz');
 comprobar((int)$pdo->query("SELECT IS_FREE_LOCK('naser_schema_".substr(hash('sha256',$base),0,32)."')")->fetchColumn()===1,'Migración libera su bloqueo');
 echo 'TOTAL: '.$total.' pruebas aprobadas'.PHP_EOL;
}finally{ $pdo=null; $servidor->exec("DROP DATABASE `$base`"); }
