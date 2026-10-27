<?php
// Test aislado: solo usa MariaDB local de QA; nunca importa db.php ni la configuración real.
require __DIR__.'/../../php/config/personal_maestro.php';
$servidor=new PDO('mysql:host=127.0.0.1;port=13379;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$base='naser_qa_personal_'.bin2hex(random_bytes(4));
if(!preg_match('/^naser_qa_personal_[a-f0-9]{8}$/',$base))throw new RuntimeException('Nombre de QA inválido.');
$servidor->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname='.$base.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$tablas=[
'CREATE TABLE sectores(id INT PRIMARY KEY,nombre VARCHAR(100),slug VARCHAR(100))',
'CREATE TABLE usuarios(id INT PRIMARY KEY,nombre VARCHAR(100))',
'CREATE TABLE empleados(id INT AUTO_INCREMENT PRIMARY KEY,legajo VARCHAR(50) UNIQUE,apellido_nombre VARCHAR(160),dni VARCHAR(20),email_corporativo VARCHAR(160),email_personal VARCHAR(160),telefono VARCHAR(60),activo TINYINT DEFAULT 1)',
'CREATE TABLE empleados_habilitaciones(id INT AUTO_INCREMENT PRIMARY KEY,empleado_id INT,tipo VARCHAR(40),detalle VARCHAR(120),fecha_vencimiento DATE,UNIQUE KEY uk_hab(empleado_id,tipo))',
'CREATE TABLE finanzas_trabajadores(id INT AUTO_INCREMENT PRIMARY KEY,empleado_id INT,sector_id INT,legajo VARCHAR(50) UNIQUE,nombre_completo VARCHAR(160),dni VARCHAR(20),sector VARCHAR(100),email VARCHAR(160),telefono VARCHAR(60),categoria_carnet VARCHAR(50),vencimiento_carnet DATE,curso_defensivo VARCHAR(100),vencimiento_defensivo DATE,creado_por INT,actualizado_por INT,actualizado_en DATETIME)',
'CREATE TABLE personal_conciliacion(id INT AUTO_INCREMENT PRIMARY KEY,finanzas_id INT UNIQUE,empleado_id INT,diferencias_json LONGTEXT,resuelto_en DATETIME,resuelto_por INT)'
];foreach($tablas as $sql)$pdo->exec($sql);
$pdo->exec("INSERT INTO sectores VALUES(1,'Finanzas','finanzas'),(2,'RRHH','rrhh');INSERT INTO usuarios VALUES(1,'QA')");
$GLOBALS['qaAdmin']=true;$GLOBALS['qaEditar']=true;$GLOBALS['qaRoles']=[];$_SESSION=['usuario_id'=>1];
function esAdmin():bool{return $GLOBALS['qaAdmin'];}
function puedeEditarSector(PDO $pdo,int $sid):bool{return $GLOBALS['qaEditar'];}
function rolEnSector(PDO $pdo,int $sid):?string{return $GLOBALS['qaRoles'][$sid]??null;}
$total=0;function check(bool $ok,string $nombre):void{global $total;if(!$ok)throw new RuntimeException('FAIL: '.$nombre);$total++;echo 'OK: '.$nombre.PHP_EOL;}
function rechaza(callable $f,string $nombre):void{try{$f();}catch(RuntimeException $e){check(true,$nombre);return;}check(false,$nombre);}
function empleado(PDO $pdo,string $legajo,string $nombre):int{$pdo->prepare('INSERT INTO empleados(legajo,apellido_nombre,dni,email_corporativo,email_personal,telefono) VALUES(?,?,?,?,?,?)')->execute([$legajo,$nombre,'12345678','qa@example.test','personal@example.test','+54 11111111']);return(int)$pdo->lastInsertId();}
function datos(PDO $pdo,int $eid,?int $id=null):array{$d=['id'=>$id??0,'empleado_id'=>$eid,'categoria'=>'B1','venc_carnet'=>'2027-04-01','curso'=>'Defensivo','venc_defensivo'=>'2027-05-01','ficha_version'=>versionEmpleadoFinanzas(empleadoParaFinanzas($pdo,$eid))];if($id)$d['registro_version']=versionTrabajadorFinanzas(trabajadorFinanzas($pdo,$id,1));return$d;}
$e=empleado($pdo,'QA-00000000000000000000000001','Persona de Maestro');
$in=datos($pdo,$e);$in['nombre']='Nombre adulterado';$in['email']='otro@example.test';$f=guardarTrabajadorFinanzas($pdo,1,1,$in);$fid=$f['id'];
check($f['nombre']==='Persona de Maestro','El alta usa nombre maestro e ignora identidad enviada');
check((int)$pdo->query('SELECT empleado_id FROM finanzas_trabajadores WHERE id='.$fid)->fetchColumn()===$e,'El seguimiento queda vinculado al empleado');
check($pdo->query('SELECT email FROM finanzas_trabajadores WHERE id='.$fid)->fetchColumn()==='qa@example.test','El alta toma el contacto del maestro');
check($pdo->query("SELECT fecha_vencimiento FROM empleados_habilitaciones WHERE empleado_id=$e AND tipo='licencia_conducir'")->fetchColumn()==='2027-04-01','El alta actualiza la licencia central');
check($pdo->query('SELECT legajo FROM finanzas_trabajadores WHERE id='.$fid)->fetchColumn()==='QA-00000000000000000000000001','Legajos largos se conservan completos');
$pdo->exec("UPDATE empleados SET apellido_nombre='Nombre actualizado',email_corporativo=NULL WHERE id=$e");$vista=trabajadorFinanzas($pdo,$fid,1);
check($vista['nombre_completo']==='Nombre actualizado','Finanzas refleja cambios de nombre del maestro');check($vista['email']==='personal@example.test','Se usa correo personal cuando no hay corporativo');
$pdo->exec("DELETE FROM empleados_habilitaciones WHERE empleado_id=$e AND tipo='licencia_conducir'");check(trabajadorFinanzas($pdo,$fid,1)['vencimiento_carnet']===null,'Eliminar habilitación no revive una fecha legacy');
rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,datos($pdo,$e)),'No se duplica seguimiento del mismo empleado');
$in=datos($pdo,$e,$fid);$in['venc_carnet']='2027-02-31';rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$in),'Fechas imposibles rechazadas');
$in=datos($pdo,$e,$fid);$pdo->exec("UPDATE empleados SET activo=0 WHERE id=$e");rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$in),'No se modifica seguimiento de empleado inactivo');$pdo->exec("UPDATE empleados SET activo=1 WHERE id=$e");
$in=datos($pdo,$e,$fid);rechaza(fn()=>guardarTrabajadorFinanzas($pdo,2,1,$in),'Se rechaza un sector que no es Finanzas');
$in=datos($pdo,$e,$fid);$pdo->exec("UPDATE empleados SET telefono='+54 22222222' WHERE id=$e");rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$in),'Edición antigua no sobrescribe ficha modificada');
$in=datos($pdo,$e,$fid);$in['ficha_version']='obsoleta';rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$in),'Se verifica la versión central además del registro');
$e2=empleado($pdo,'QA-2','Otra persona');$in=datos($pdo,$e2,$fid);rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$in),'Registro vinculado no puede reasignarse a otra persona');
$in=datos($pdo,$e,$fid);$in['nombre']='Ataque nombre';$in['telefono']='ataque';$res=guardarTrabajadorFinanzas($pdo,1,1,$in);
check($pdo->query('SELECT apellido_nombre FROM empleados WHERE id='.$e)->fetchColumn()==='Nombre actualizado','Guardar Finanzas no cambia la identidad central');check($pdo->query('SELECT telefono FROM empleados WHERE id='.$e)->fetchColumn()==='+54 22222222','Guardar Finanzas no cambia contacto central');
check($pdo->query('SELECT nombre_completo FROM finanzas_trabajadores WHERE id='.$fid)->fetchColumn()==='Persona de Maestro','La fila histórica se conserva al editar');
$in=datos($pdo,$e,$fid);$segundo=$in;$in['venc_carnet']='2028-01-01';guardarTrabajadorFinanzas($pdo,1,1,$in);$segundo['venc_carnet']='2029-01-01';rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,$segundo),'Dos editores no sobrescriben el primer cambio');check(trabajadorFinanzas($pdo,$fid,1)['vencimiento_carnet']==='2028-01-01','El cambio confirmado conserva su fecha');
$GLOBALS['qaEditar']=false;rechaza(fn()=>guardarTrabajadorFinanzas($pdo,1,1,datos($pdo,$e,$fid)),'Se comprueba permiso de edición en servidor');$GLOBALS['qaEditar']=true;
$f2=guardarTrabajadorFinanzas($pdo,1,1,datos($pdo,$e2));$pdo->exec('DELETE FROM empleados WHERE id='.$e2);$huella=trabajadorFinanzas($pdo,$f2['id'],1);check($huella['nombre_completo']===null&&$huella['email']===null,'Ficha central ausente no filtra la identidad legacy');
// Conciliación de valores históricos.
function revision(PDO $pdo,int $fid,int $eid,array $diff):int{$pdo->prepare('INSERT INTO personal_conciliacion(finanzas_id,empleado_id,diferencias_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE diferencias_json=VALUES(diferencias_json),resuelto_en=NULL')->execute([$fid,$eid,json_encode($diff)]);return(int)$pdo->query('SELECT id FROM personal_conciliacion WHERE finanzas_id='.$fid)->fetchColumn();}
$pdo->exec("UPDATE empleados SET apellido_nombre='Maestro vigente' WHERE id=$e");$cid=revision($pdo,$fid,$e,['apellido_nombre'=>['maestro'=>'Maestro vigente','finanzas'=>'Persona de Maestro']]);resolverConciliacionPersonal($pdo,$cid,['apellido_nombre'=>'mantener']);check($pdo->query('SELECT apellido_nombre FROM empleados WHERE id='.$e)->fetchColumn()==='Maestro vigente','Conservar maestro no cambia datos');check((bool)$pdo->query('SELECT resuelto_en FROM personal_conciliacion WHERE id='.$cid)->fetchColumn(),'Se registra la revisión resuelta');
$cid=revision($pdo,$fid,$e,['apellido_nombre'=>['maestro'=>'Maestro vigente','finanzas'=>'Persona de Maestro']]);resolverConciliacionPersonal($pdo,$cid,['apellido_nombre'=>'adoptar']);check($pdo->query('SELECT apellido_nombre FROM empleados WHERE id='.$e)->fetchColumn()==='Persona de Maestro','Solo la adopción explícita reemplaza un valor');
$cid=revision($pdo,$fid,$e,['apellido_nombre'=>['maestro'=>'Persona de Maestro','finanzas'=>'Persona de Maestro']]);$pdo->exec("UPDATE empleados SET apellido_nombre='Cambio posterior de RRHH' WHERE id=$e");rechaza(fn()=>resolverConciliacionPersonal($pdo,$cid,['apellido_nombre'=>'adoptar']),'Una revisión obsoleta no reemplaza cambios de RRHH');check($pdo->query('SELECT resuelto_en FROM personal_conciliacion WHERE id='.$cid)->fetchColumn()===null,'Una revisión fallida queda pendiente');
$GLOBALS['qaAdmin']=false;$GLOBALS['qaRoles']=[1=>'responsable',2=>'observador'];rechaza(fn()=>resolverConciliacionPersonal($pdo,$cid,['apellido_nombre'=>'mantener']),'Responsable de otro sector no concilia Personal');$GLOBALS['qaRoles'][2]='responsable';resolverConciliacionPersonal($pdo,$cid,['apellido_nombre'=>'mantener']);check(true,'Responsable directo RRHH puede resolver conservando datos');$GLOBALS['qaAdmin']=true;
$pdo->exec("UPDATE finanzas_trabajadores SET email='correo-invalido' WHERE id=$fid");$cid=revision($pdo,$fid,$e,['email_corporativo'=>['maestro'=>null,'finanzas'=>'correo-invalido']]);rechaza(fn()=>resolverConciliacionPersonal($pdo,$cid,['email_corporativo'=>'adoptar']),'No se adopta un correo histórico inválido');
$cid=revision($pdo,$fid,$e,['telefono'=>['maestro'=>'+54 22222222','finanzas'=>'+54 11111111']]);rechaza(fn()=>resolverConciliacionPersonal($pdo,$cid,[]),'Cada diferencia exige una decisión');$pdo->exec("UPDATE finanzas_trabajadores SET telefono='Otro dato posterior' WHERE id=$fid");rechaza(fn()=>resolverConciliacionPersonal($pdo,$cid,['telefono'=>'adoptar']),'Un legado cambiado invalida la adopción antigua');
check(!$pdo->inTransaction(),'Errores dejan la conexión sin transacciones abiertas');
echo 'TOTAL: '.$total.' comprobaciones aprobadas. Base aislada: '.$base.PHP_EOL;
