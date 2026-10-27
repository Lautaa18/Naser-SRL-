<?php
// Cambios transaccionales con control de versión; los borradores nuevos son idempotentes.
require __DIR__ . '/_init.php';
require_once __DIR__ . '/../config/mejoras.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
class FormApiError extends RuntimeException {
    public function __construct(string $mensaje, public int $status=400, public array $detalle=[]) { parent::__construct($mensaje); }
}
function responder(array $data,int $code=200):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
function fallar(string $msg,int $code=400,array $detalle=[]):never{throw new FormApiError($msg,$code,$detalle);}
function jsonFormulario($valor):string{
    if(!is_array($valor)&&!is_object($valor))fallar('Los campos del formulario tienen un formato inválido.');
    $json=json_encode($valor,JSON_UNESCAPED_UNICODE);
    if($json===false||strlen($json)>8*1024*1024)fallar('El formulario es demasiado grande o contiene datos inválidos.');return $json;
}
function contenidoIgual(string $a,string $b):bool{
    $normalizar=function($v)use(&$normalizar){if(is_array($v)){if(!array_is_list($v))ksort($v);foreach($v as &$x)$x=$normalizar($x);}return $v;};
    return $normalizar(json_decode($a,true))===$normalizar(json_decode($b,true));
}
$avisos=[];
try{
 if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['sesion']))responder(['ok'=>true,'usuario_id'=>(int)$_SESSION['usuario_id'],'csrf'=>csrf_token()]);
 if($_SERVER['REQUEST_METHOD']!=='POST')fallar('Método no permitido.',405);
 verify_csrf();
 $in=json_decode((string)file_get_contents('php://input'),true);if(!is_array($in))fallar('Datos inválidos.');
 $accion=is_string($in['accion']??null)?$in['accion']:'';
 if(!in_array($accion,['guardar','enviar','aprobar','rechazar','reabrir','duplicar','guardar_copia','eliminar'],true))fallar('Acción desconocida.');
 $id=max(0,(int)($in['id']??0));$uid=(int)$_SESSION['usuario_id'];$yo=(string)($_SESSION['nombre']??'Un usuario');
 $referencia=mb_substr(trim(is_string($in['referencia']??null)?$in['referencia']:''),0,200);
 $comentario=mb_substr(trim(is_string($in['comentario']??null)?$in['comentario']:''),0,2000);
 $necesitaDatos=in_array($accion,['guardar','enviar','guardar_copia'],true);
 $datos=$necesitaDatos?jsonFormulario($in['datos']??new stdClass()):null;$storage=$necesitaDatos?jsonFormulario($in['storage']??new stdClass()):null;
 $crea=!$id||in_array($accion,['guardar_copia','duplicar'],true);
 $token=null;
 if($crea){$raw=$in['client_token']??'';if(!is_string($raw)||!preg_match('/^[A-Za-z0-9_-]{20,64}$/',$raw))fallar('Falta el identificador de guardado. Recargá el formulario antes de continuar.');$token=hash('sha256',$uid.':'.$raw);}
 $urlRegistro=fn(int $rid)=>'/php/formularios/llenar.php?id='.$rid;
 $pdo->beginTransaction();
 $reg=$id?cargarRegistro($pdo,$id,true):null;
 if($id&&!$reg)fallar('El registro ya no está disponible. Puede estar en la papelera.',404);
 if($reg){
  if(!puedeVerSector($pdo,(int)$reg['sector_id']))fallar('No tenés acceso a este sector.',403);
  if($reg['estado']==='borrador'&&(int)$reg['creado_por']!==$uid&&!puedeEditarSector($pdo,(int)$reg['sector_id']))fallar('Este borrador pertenece a otra persona.',403);
  // Guardar una copia conserva los cambios locales y nunca modifica el registro original.
  if($accion!=='guardar_copia'&&(!isset($in['revision'])||!is_int($in['revision'])||$in['revision']<1||$in['revision']!==(int)$reg['revision']))fallar('El registro cambió desde que lo abriste. Conservá tus cambios como copia o abrí la versión actual.',409,['conflicto'=>true,'id'=>$id,'revision'=>(int)$reg['revision']]);
 }
 $form=$reg?formularioPorCodigo($reg['formulario']):formularioPorCodigo(is_string($in['formulario']??null)?$in['formulario']:'');
 if(!$form)fallar('Formulario desconocido.');
 $sector=sectorPorSlug($pdo,$form['sector']);if(!$sector||!puedeVerSector($pdo,(int)$sector['id']))fallar('No tenés acceso a este sector.',403);
 $sid=(int)$sector['id'];if($reg&&(int)$reg['sector_id']!==$sid)fallar('El formulario pertenece a otro sector.',409,['conflicto'=>true]);
 $nuevo=false;$repetido=false;$mensaje='';$redirect=null;$origenId=$id;
 if($crea){
  if(!in_array($accion,['guardar','enviar','guardar_copia','duplicar'],true))fallar('Falta el registro.');
  if(!puedeCompletarSector($pdo,$sid))fallar('No tenés permiso para completar formularios de este sector.',403);
  if($accion==='duplicar'&&!$reg)fallar('Falta el formulario a duplicar.');
  if($accion==='duplicar'){$datos=(string)($reg['datos_json']??'{}');$storage=(string)($reg['storage_json']??'{}');$referencia=mb_substr('Copia de '.($reg['referencia']?:'#'.$id),0,200);}
  elseif($accion==='guardar_copia')$referencia=mb_substr('Copia de '.($referencia?:($reg['referencia']??$form['titulo'])),0,200);
  $st=$pdo->prepare('SELECT * FROM formularios_registros WHERE client_token=? FOR UPDATE');$st->execute([$token]);$anterior=$st->fetch();
  $estadoInicial=$accion==='enviar'?'enviado':'borrador';
  if($anterior){
   $mismo=(int)$anterior['creado_por']===$uid&&$anterior['formulario']===$form['codigo']&&!$anterior['eliminado_lote']&&(int)$anterior['revision']===1&&$anterior['estado']===$estadoInicial&&($anterior['referencia']??'')===$referencia&&contenidoIgual((string)$anterior['datos_json'],$datos)&&contenidoIgual((string)$anterior['storage_json'],$storage);
   if(!$mismo)fallar('Este borrador ya se guardó y luego cambió. Conservá tus cambios como una copia para evitar reemplazarlo.',409,['conflicto'=>true,'id'=>(int)$anterior['id'],'revision'=>(int)$anterior['revision']]);
   $id=(int)$anterior['id'];$repetido=true;
  }else{
   $pdo->prepare("INSERT INTO formularios_registros(formulario,sector_id,referencia,estado,datos_json,storage_json,creado_por,actualizado_por,client_token,enviado_por,enviado_en) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$form['codigo'],$sid,$referencia?:null,$estadoInicial,$datos,$storage,$uid,$uid,$token,$accion==='enviar'?$uid:null,$accion==='enviar'?date('Y-m-d H:i:s'):null]);
   $id=(int)$pdo->lastInsertId();$nuevo=true;
   historialFormulario($pdo,$id,'creado',$origenId?'Copia del registro #'.$origenId:null);registrarActividad($pdo,'formulario_creado',$form['titulo'].' #'.$id);
  }
  $reg=cargarRegistro($pdo,$id);
 }else{
  $perm=permisosRegistro($pdo,$reg);
  if($accion==='guardar'||$accion==='enviar'){
   if(!$perm['editar'])fallar('El formulario ya no admite cambios o no tenés permiso para editarlo.',403);
   if($accion==='enviar'&&!$perm['enviar'])fallar('No podés enviar este formulario.',403);
   $cambio=!contenidoIgual((string)($reg['datos_json']??'{}'),$datos)||!contenidoIgual((string)($reg['storage_json']??'{}'),$storage)||($reg['referencia']??'')!==$referencia;
   if($cambio||$accion==='enviar'){
    $extra=$accion==='enviar'?",estado='enviado',enviado_por=$uid,enviado_en=NOW(),revisado_por=NULL,revisado_en=NULL,comentario_revision=NULL":'';
    $pdo->prepare('UPDATE formularios_registros SET datos_json=?,storage_json=?,referencia=?,actualizado_por=?,revision=revision+1'.$extra.' WHERE id=?')->execute([$datos,$storage,$referencia?:null,$uid,$id]);
    $st=$pdo->prepare("SELECT COUNT(*) FROM formularios_historial WHERE registro_id=? AND usuario_id=? AND accion IN ('guardado','creado') AND creado_en>NOW()-INTERVAL 10 MINUTE");$st->execute([$id,$uid]);if(!(int)$st->fetchColumn())historialFormulario($pdo,$id,'guardado');
   }
  }elseif($accion==='aprobar'||$accion==='rechazar'){
   if(!$perm['aprobar'])fallar('No tenés permiso para aprobar o rechazar este formulario.',403);
   if($accion==='rechazar'&&$comentario==='')fallar('Escribí el motivo del rechazo.');
   $estado=$accion==='aprobar'?'aprobado':'rechazado';$pdo->prepare('UPDATE formularios_registros SET estado=?,revisado_por=?,revisado_en=NOW(),comentario_revision=?,actualizado_por=?,revision=revision+1 WHERE id=?')->execute([$estado,$uid,$comentario?:null,$uid,$id]);
   historialFormulario($pdo,$id,$estado,$comentario?:null);registrarActividad($pdo,'formulario_'.$estado,$form['titulo'].' #'.$id);
  }elseif($accion==='reabrir'){
   if(!$perm['reabrir'])fallar('No tenés permiso para reabrir este formulario.',403);
   $pdo->prepare("UPDATE formularios_registros SET estado='borrador',revisado_por=NULL,revisado_en=NULL,comentario_revision=NULL,actualizado_por=?,revision=revision+1 WHERE id=?")->execute([$uid,$id]);historialFormulario($pdo,$id,'reabierto',$comentario?:null);
  }elseif($accion==='eliminar'){
   if(!$perm['eliminar'])fallar('No tenés permiso para eliminar este formulario.',403);
   $lote=enviarPapelera($pdo,'formulario',$id,$sid,$form['titulo'].($reg['referencia']?' — '.$reg['referencia']:''));$pdo->prepare('UPDATE formularios_registros SET eliminado_lote=?,actualizado_por=?,revision=revision+1 WHERE id=?')->execute([$lote,$uid,$id]);historialFormulario($pdo,$id,'papelera');registrarActividad($pdo,'formulario_papelera',$form['titulo'].' #'.$id);
   $redirect=app_url('/php/formularios/index.php').'?sector='.urlencode($sector['slug']);$mensaje='Formulario enviado a la papelera. Se puede recuperar.';
  }
 }
 if(!$repetido){
  $actual=$accion==='eliminar'?$reg:cargarRegistro($pdo,$id);$dest=[];$titulo='';$texto='';$tipo='info';
  if($accion==='enviar'){
   historialFormulario($pdo,$id,'enviado',$comentario?:null);registrarActividad($pdo,'formulario_enviado',$form['titulo'].' #'.$id);
   $st=$pdo->prepare("SELECT u.id FROM usuario_sector us JOIN usuarios u ON u.id=us.usuario_id WHERE us.sector_id=? AND us.rol_sector='responsable' AND us.puede_aprobar=1 AND u.activo=1");$st->execute([$sid]);$dest=$st->fetchAll(PDO::FETCH_COLUMN);if(!$dest)$dest=$pdo->query("SELECT id FROM usuarios WHERE rol='admin' AND activo=1")->fetchAll(PDO::FETCH_COLUMN);
   $titulo='Para aprobar: '.$form['titulo'];$texto=$yo.' completó el formulario de '.$sector['nombre'].' y lo envió para tu aprobación.';$tipo='aprobacion';$mensaje='Enviado para aprobación.';
  }elseif($accion==='aprobar'||$accion==='rechazar'){
   $dest=array_unique([(int)$reg['creado_por'],(int)$reg['enviado_por']]);$tipo=$accion==='aprobar'?'aprobado':'rechazado';$titulo=($accion==='aprobar'?'Aprobado: ':'Rechazado: ').$form['titulo'];$texto=$yo.($accion==='aprobar'?' aprobó el formulario.':' rechazó el formulario.').($comentario?' Comentario: '.$comentario:'');$mensaje=$accion==='aprobar'?'Formulario aprobado.':'Formulario rechazado. Se avisó a quien lo cargó.';
  }elseif($accion==='reabrir'){$dest=[(int)$reg['creado_por']];$titulo='Formulario reabierto para edición';$texto=$yo.' reabrió el formulario #'.$id.'.';$mensaje='El formulario volvió a borrador y se puede editar.';}
  $dest=array_values(array_diff(array_filter(array_map('intval',$dest)),[$uid]));
  if($dest){$clave='form:'.$id.':r'.$actual['revision'].':'.$tipo;notificar($pdo,$dest,$titulo,$texto,$urlRegistro($id),$tipo,$clave,false);$avisos=[$dest,$titulo,$texto,$urlRegistro($id),$tipo,$clave,true,true];}
 }
 if($mensaje==='')$mensaje=$accion==='duplicar'||$accion==='guardar_copia'?'Se creó una copia en borrador.':'Guardado en el sistema.';
 if($accion==='duplicar'||$accion==='guardar_copia')$redirect=app_url($urlRegistro($id));
 $r=$accion==='eliminar'?null:cargarRegistro($pdo,$id);
 $respuesta=['ok'=>true,'id'=>$id,'revision'=>$r?(int)$r['revision']:(int)$reg['revision']+1,'estado'=>$r['estado']??'papelera','estado_label'=>$r?estadoFormularioLabel($r['estado']):'En papelera','permisos'=>$r?permisosRegistro($pdo,$r):[],'mensaje'=>$mensaje,'repetido'=>$repetido];if($redirect)$respuesta['redirect']=$redirect;
 $pdo->commit();
 // Los correos se envían después de liberar los bloqueos; un fallo queda pendiente de reintento.
 if($avisos){try{notificar($pdo,...$avisos);}catch(Throwable $e){error_log('[NASER] aviso formulario pendiente: '.$e->getMessage());}}
 responder($respuesta);
}catch(FormApiError $e){if($pdo->inTransaction())$pdo->rollBack();responder(['ok'=>false,'error'=>$e->getMessage()]+$e->detalle,$e->status);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[NASER] API formularios: '.$e->getMessage());$transitorio=$e instanceof PDOException&&in_array((int)($e->errorInfo[1]??0),[1205,1213],true);responder(['ok'=>false,'error'=>$transitorio?'El registro está ocupado. Reintentá en unos segundos.':'No se pudo guardar. Tus cambios siguen en esta pantalla; intentá nuevamente.'],$transitorio?503:500);}
