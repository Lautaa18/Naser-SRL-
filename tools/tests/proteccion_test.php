<?php
// Ejecutar: php tools/tests/proteccion_test.php. No usa una base ni archivos reales.
require __DIR__.'/../../php/config/proteccion.php';
class ProteccionStatement extends PDOStatement {
    public function __construct(private mixed $valor) {}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {return $this->valor;}
    public function fetchColumn(int $column=0): mixed {return $this->valor;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {return is_array($this->valor)?$this->valor:[];}
}
class ProteccionPDO extends PDO {
    public function __construct(public ?array $tarea=null,public int $pendientes=0,public int $fallidos=0) {}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {
        return new ProteccionStatement(str_contains($query,'sistema_tareas')?$this->tarea:(str_contains($query,'SELECT n.id')?[]:(str_contains($query,'mail_error IS NOT NULL')?$this->fallidos:$this->pendientes)));
    }
}
function mail_config(): array {return ['transport'=>'smtp'];}
function comprobar(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo 'OK: '.$msg.PHP_EOL;}
$tmp=sys_get_temp_dir().'/naser_estado_'.bin2hex(random_bytes(5));mkdir($tmp);
file_put_contents($tmp.'/backup-latido.json','{"hora":123}');
comprobar(leerEstadoProteccion('backup-latido.json',$tmp)['hora']===123,'Lee metadatos JSON');
comprobar(leerEstadoProteccion('../archivo.json',$tmp)===null,'Rechaza rutas fuera del directorio');
file_put_contents($tmp.'/backup-intento.json','no json');
comprobar(leerEstadoProteccion('backup-intento.json',$tmp)===null,'Tolera un estado incompleto');
file_put_contents($tmp.'/backup-exito.json',str_repeat('x',65537));
comprobar(leerEstadoProteccion('backup-exito.json',$tmp)===null,'Rechaza estados excesivos');
$e=estadoProteccion(new ProteccionPDO(['error'=>'Error simulado'],3),$tmp,$tmp);
comprobar($e['resumen']['nivel']==='error','Muestra un error del servicio de avisos');
comprobar($e['correo']['pendientes']===3,'Cuenta avisos sin envio confirmado');


comprobar(resultadoCorreoProteccion(['activo'=>1,'recibe_mails'=>1,'mail_enviado'=>1,'mail_transporte'=>'log'])==='Registrado en modo de prueba','No confunde log con correo entregado');
comprobar(resultadoCorreoProteccion(['activo'=>1,'recibe_mails'=>1,'mail_enviado'=>0,'mail_error'=>'Fallo'])==='Falló; pendiente de reintento','Distingue fallo de pendiente');

// Cada caso tiene archivos propios: no depende de un servicio ni de copias del proyecto.
function guardarEstadoPrueba(string $nombre,array $estado):void {global $tmp;file_put_contents($tmp.'/'.$nombre.'.json',json_encode($estado,JSON_THROW_ON_ERROR));}
$ahora=time(); $archivo='db_'.date('Y-m-d').'.sql.gz';
file_put_contents($tmp.'/'.$archivo,gzencode('CREATE TABLE usuarios(id INT);'));
$exito=['estado'=>'correcto','hora'=>$ahora,'archivo'=>$archivo];
guardarEstadoPrueba('backup-latido',['hora'=>$ahora,'retencion_dias'=>7]);
guardarEstadoPrueba('backup-intento',$exito); guardarEstadoPrueba('backup-exito',$exito);
guardarEstadoPrueba('verificador-latido',['hora'=>$ahora]);
$recuperada=$exito+['sha256'=>hash_file('sha256',$tmp.'/'.$archivo),'tablas'=>2];
guardarEstadoPrueba('verificacion-intento',$recuperada); guardarEstadoPrueba('verificacion-exito',$recuperada);
guardarEstadoPrueba('tareas-latido',['hora'=>$ahora,'error'=>null]);
$pdo=new ProteccionPDO(['completado_en'=>date('Y-m-d H:i:s',$ahora),'error'=>null]);
comprobar(estadoProteccion($pdo,$tmp,$tmp)['resumen']['nivel']==='ok','Solo confirma protección con copias, restauración y revisión reciente');
guardarEstadoPrueba('tareas-latido',['hora'=>$ahora,'error'=>'Conexión no disponible']);
comprobar(estadoProteccion($pdo,$tmp,$tmp)['resumen']['nivel']==='error','Un fallo de conexión no queda oculto por el último éxito en la base');
guardarEstadoPrueba('tareas-latido',['hora'=>$ahora,'error'=>null]);
$pdo->tarea['completado_en']=date('Y-m-d H:i:s',$ahora-3600);
comprobar(estadoProteccion($pdo,$tmp,$tmp)['resumen']['nivel']==='aviso','Un latido nuevo no reemplaza una revisión completa reciente');
$pdo->tarea['completado_en']=date('Y-m-d H:i:s',$ahora);
guardarEstadoPrueba('verificador-latido',['hora'=>$ahora+7200]);
comprobar(!estadoProteccion($pdo,$tmp,$tmp)['verificacion']['activo'],'Una señal con reloj futuro no se acepta como reciente');
guardarEstadoPrueba('verificador-latido',['hora'=>$ahora]);
guardarEstadoPrueba('verificacion-intento',['estado'=>'en_curso','hora'=>$ahora,'archivo'=>$archivo]);
comprobar(estadoProteccion($pdo,$tmp,$tmp)['resumen']['nivel']==='aviso','Una restauración en curso no figura como terminada');
guardarEstadoPrueba('verificacion-intento',$recuperada);
file_put_contents($tmp.'/'.$archivo,gzencode('CREATE TABLE otro(id INT);'));
touch($tmp.'/'.$archivo,$ahora);clearstatcache();
comprobar(!estadoProteccion($pdo,$tmp,$tmp)['verificacion']['corresponde_ultimo'],'Una copia reemplazada debe volver a restaurarse aunque tenga la misma fecha');
foreach(glob($tmp.'/*') as $f)unlink($f);rmdir($tmp);