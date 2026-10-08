<?php
require __DIR__ . '/config/auth.php';
requireLogin();
verify_csrf(); // protege todos los formularios POST de esta pagina
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require __DIR__ . '/config/modulo.php';
require_once __DIR__ . '/config/mailer.php';

$slug = 'finanzas';
[$sector, $sid, $canEdit] = cargarModulo($pdo, $slug);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg=''; $err='';

function finanzasEstadoVencimiento(?string $fecha): array {
    if (!$fecha) return ['Sin fecha', 'sin-fecha', null];
    $dias = (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($fecha))->format('%r%a');
    if ($dias < 0) return ['Vencido hace ' . abs($dias) . ' día(s)', 'vencido', $dias];
    if ($dias === 0) return ['Vence hoy', 'hoy', 0];
    if ($dias <= 30) return ['Vence en ' . $dias . ' día(s)', 'proximo', $dias];
    return ['Vigente', 'vigente', $dias];
}

function finanzasRutaAdjunto(?string $ruta): ?string {
    if (!$ruta || !preg_match('~^finanzas/[A-Za-z0-9._-]+$~', $ruta)) return null;
    return dirname(__DIR__) . '/uploads/' . $ruta;
}

function finanzasWhatsappUrl(?string $telefono, string $mensaje): ?string {
    $numero = preg_replace('/\D+/', '', (string)$telefono);
    if (strlen($numero) < 8) return null;
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
}

function finanzasGuardarAdjunto(array $archivo, string $directorio): ?string {
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo. Volvé a intentarlo.');
    if (($archivo['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('El archivo no puede superar los 10 MB.');
    $nombreOriginal = (string)($archivo['name'] ?? '');
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extensiones = ['xlsx', 'xls', 'pdf', 'docx'];
    if (!in_array($extension, $extensiones, true)) throw new RuntimeException('Adjuntá un Excel (.xlsx, .xls), PDF o Word (.docx).');
    $tmp = (string)($archivo['tmp_name'] ?? '');
    if (!$tmp || !is_uploaded_file($tmp)) throw new RuntimeException('El archivo recibido no es válido.');
    $mime = '';
    if (class_exists('finfo')) { $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = (string)$finfo->file($tmp); }
    $mimes = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];
    if ($mime !== '' && !in_array($mime, $mimes[$extension], true)) throw new RuntimeException('El contenido del archivo no coincide con su extensión.');
    if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) throw new RuntimeException('No se pudo preparar la carpeta de archivos de Finanzas.');
    $baseNombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
    $baseNombre = trim(substr((string)$baseNombre, 0, 60), '._-') ?: 'adjunto';
    $nombre = date('YmdHis') . '_' . $baseNombre . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($tmp, $directorio . DIRECTORY_SEPARATOR . $nombre)) throw new RuntimeException('No se pudo guardar el archivo adjunto.');
    return 'finanzas/' . $nombre;
}

function finanzasEstadoHtml(?string $fecha): string {
    [$texto, $clase] = finanzasEstadoVencimiento($fecha);
    return '<span class="due-badge due-' . h($clase) . '">' . h($texto) . '</span>';
}

$uploadDirFinanzas = dirname(__DIR__) . '/uploads/finanzas';
$subidaChecklistFullPath = null;

if (isset($_GET['descargar_checklist'])) {
    $idDescarga = (int)$_GET['descargar_checklist'];
    $st = $pdo->prepare('SELECT requisito,archivo_adjunto FROM finanzas_checklist WHERE id=? AND sector_id=? LIMIT 1');
    $st->execute([$idDescarga, $sid]);
    $archivo = $st->fetch(PDO::FETCH_ASSOC);
    $ruta = finanzasRutaAdjunto($archivo['archivo_adjunto'] ?? null);
    if (!$archivo || !$ruta || !is_file($ruta)) { http_response_code(404); exit('El adjunto no está disponible.'); }
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mime = ['xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xls'=>'application/vnd.ms-excel','pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$ext] ?? 'application/octet-stream';
    session_write_close();
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    readfile($ruta);
    exit;
}

?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión de Finanzas | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>">
<link rel="stylesheet" href="<?=asset('/css/modules.css')?>">
<style>
.due-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}.due-vigente{background:#e8f5ec;color:#176337}.due-proximo,.due-hoy{background:#fff4d6;color:#865700}.due-vencido{background:#fde8e7;color:#a32d2a}.due-sin-fecha{background:#eef1f3;color:#5c6670}.finance-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.finance-actions form{margin:0}.finance-contact,.checklist-hint{font-size:12px;color:#6b7280}.checklist-hint{margin:0 0 12px}.module-table td{vertical-align:middle}
.chart-grid{display:grid;grid-template-columns:2fr 1fr;gap:15px;margin:18px 0 24px}
.chart-card{background:#fff;border:1px solid var(--nl);border-radius:17px;padding:19px;box-shadow:0 5px 18px rgba(31,41,55,.045)}
.chart-card h3{margin:0 0 4px}.chart-card p{margin:0 0 18px;color:#6b7280;font-size:12px}
.chart-bars{display:flex;flex-direction:column;gap:14px}
.chart-row{display:grid;grid-template-columns:minmax(130px,1fr) 3fr 52px;gap:10px;align-items:center}
.chart-label{font-size:12px;font-weight:800;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chart-track{height:15px;background:#edf1ee;border-radius:999px;overflow:hidden}
.chart-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#1f6e3d,#16a05a);min-width:2px}
.chart-value{text-align:right;font-size:12px;font-weight:900;color:#176337}
.donut-wrap{display:flex;justify-content:center;align-items:center;min-height:210px}
.donut{--p:0;width:180px;height:180px;border-radius:50%;background:conic-gradient(#08783e calc(var(--p)*1%),#e8efea 0);display:grid;place-items:center}
.donut:after{content:"";width:125px;height:125px;border-radius:50%;background:#fff;grid-area:1/1}
.donut-text{grid-area:1/1;z-index:1;text-align:center}.donut-text strong{display:block;font-size:30px;color:#08783e}.donut-text span{font-size:11px;color:#6b7280;font-weight:800}
.indicator-empty{padding:24px;text-align:center;color:#6b7280;background:#f8faf8;border-radius:12px}
@media(max-width:850px){.chart-grid{grid-template-columns:1fr}.chart-row{grid-template-columns:110px 1fr 45px}}
</style>
</head>
<body><div class="app"><?php sidebar($pdo,$slug); ?><main class="content">
<section class="module-hero"><p class="eyebrow">SERVICIOS NASER SRL · FINANZAS</p><h1>Gestión de Finanzas</h1><p>Control de vencimientos, indicadores y checklist del sector.</p></section>
<div class="module-tabs"><a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación</a><a href="#gestion">⚙ Gestión</a><a href="#seguimiento">📊 Seguimiento</a><a href="#checklist">☑ Checklist</a><a href="#alertas">🔔 Alertas</a><a href="#indicadores">📈 Indicadores</a></div>
<div class="module-permission">🔐 <?php if($canEdit): ?>Podés consultar y modificar este sector.<?php else: ?>Modo consulta: solo el responsable del sector y el administrador pueden modificar.<?php endif; ?></div>

<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
 exigirEdicion($canEdit);
 try{
  $a=$_POST['accion']??'';
  if($a==='trabajador_guardar'){
   $id=(int)($_POST['id']??0);$leg=trim($_POST['legajo']??'');$nom=trim($_POST['nombre']??'');$dni=trim($_POST['dni']??'');$cat=trim($_POST['categoria']??'');$vc=$_POST['venc_carnet']??'';$curso=trim($_POST['curso']??'');$vd=$_POST['venc_defensivo']??'';$email=trim($_POST['email']??'');$telefono=trim($_POST['telefono']??'');
   if($leg===''||$nom===''||$dni===''||$vc===''||$vd==='')throw new RuntimeException('Completá los datos obligatorios.');
   if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('El correo del trabajador no es válido.');
   if($id){$pdo->prepare('UPDATE finanzas_trabajadores SET legajo=?,nombre_completo=?,dni=?,email=?,telefono=?,categoria_carnet=?,vencimiento_carnet=?,curso_defensivo=?,vencimiento_defensivo=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$leg,$nom,$dni,$email?:null,$telefono?:null,$cat,$vc,$curso,$vd,$uid,$id,$sid]);$msg='Trabajador actualizado.';}
   else{$pdo->prepare('INSERT INTO finanzas_trabajadores(sector_id,legajo,nombre_completo,dni,sector,email,telefono,categoria_carnet,vencimiento_carnet,curso_defensivo,vencimiento_defensivo,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$sid,$leg,$nom,$dni,$sector['nombre'],$email?:null,$telefono?:null,$cat,$vc,$curso,$vd,$uid,$uid]);$msg='Trabajador registrado.';}
   auditModulo($pdo,$uid,'finanzas_trabajador',$nom);
  }

  if($a==='trabajador_aviso_mail'){
    $id=(int)($_POST['id']??0);$st=$pdo->prepare('SELECT * FROM finanzas_trabajadores WHERE id=? AND sector_id=? LIMIT 1');$st->execute([$id,$sid]);$persona=$st->fetch(PDO::FETCH_ASSOC);
    if(!$persona||!filter_var((string)$persona['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('El trabajador no tiene un correo válido cargado.');
    $estadoCarnet=finanzasEstadoVencimiento($persona['vencimiento_carnet']??null);$estadoCurso=finanzasEstadoVencimiento($persona['vencimiento_defensivo']??null);
    $cuerpo='<p>Hola '.h($persona['nombre_completo']).',</p><p>Este es el estado de tus vencimientos registrados:</p><ul><li>Carnet de conducir ('.h($persona['categoria_carnet']??'sin categoría').'): <strong>'.h($estadoCarnet[0]).'</strong> — '.h($persona['vencimiento_carnet']??'').'</li><li>Curso defensivo ('.h($persona['curso_defensivo']??'').'): <strong>'.h($estadoCurso[0]).'</strong> — '.h($persona['vencimiento_defensivo']??'').'</li></ul><p>Por favor, coordiná la renovación si alguno está próximo a vencer o vencido.</p>';
    $enviado=enviar_mail((string)$persona['email'],'Estado de vencimientos de conducción',mail_plantilla('Aviso de vencimientos',$cuerpo,app_absolute_url('/php/finanzas.php')));
    if(!$enviado)throw new RuntimeException('No se pudo enviar el correo. Revisá la configuración de correo del sistema.');
    auditModulo($pdo,$uid,'finanzas_aviso_trabajador',$persona['nombre_completo']);
    $msg=mail_config()['transport']==='log'?'Aviso registrado en el sistema de correo de prueba; no se envió al destinatario.':'Aviso enviado a '.$persona['email'].'.';
  }

  if($a==='alerta_guardar'){
    $trabajador=trim($_POST['trabajador_nombre']??'');$email=trim($_POST['email']??'');$telefono=trim($_POST['telefono']??'');$canal=trim($_POST['canal']??'Correo');
    if($trabajador==='') throw new RuntimeException('Indicá a quién corresponde la alerta.');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('El correo del destinatario no es válido.');
    if(!in_array($canal,['Correo','WhatsApp','Correo y WhatsApp','Aviso interno'],true))throw new RuntimeException('Seleccioná una vía de aviso válida.');
    if(in_array($canal,['Correo','Correo y WhatsApp'],true)&&$email==='')throw new RuntimeException('Ingresá un correo para la vía seleccionada.');
    if(in_array($canal,['WhatsApp','Correo y WhatsApp'],true)&&!finanzasWhatsappUrl($telefono,'Aviso de vencimiento'))throw new RuntimeException('Ingresá un teléfono válido con código de país para WhatsApp.');
    $pdo->prepare('INSERT INTO finanzas_alertas(sector_id,trabajador_nombre,email,telefono,canal,creado_por,estado,fecha_vencimiento) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$trabajador,$email,$telefono,$canal,$uid,'pendiente',$_POST['fecha_vencimiento']?:null]);
    auditModulo($pdo,$uid,'finanzas_alerta',$trabajador);$msg='Alerta registrada. Queda disponible para seguimiento y posterior envío.';
  }
  if($a==='alerta_enviar_mail'){
    $id=(int)($_POST['id']??0);$st=$pdo->prepare('SELECT * FROM finanzas_alertas WHERE id=? AND sector_id=? LIMIT 1');$st->execute([$id,$sid]);$alerta=$st->fetch(PDO::FETCH_ASSOC);
    if(!$alerta||!filter_var((string)$alerta['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('La alerta no tiene un correo válido cargado.');
    [$estado]=finanzasEstadoVencimiento($alerta['fecha_vencimiento']??null);
    $cuerpo='<p>Hola '.h($alerta['trabajador_nombre']).',</p><p>Te avisamos que el vencimiento registrado para vos figura como <strong>'.h($estado).'</strong>'.($alerta['fecha_vencimiento']?' ('.h($alerta['fecha_vencimiento']).')':'').'.</p><p>Por favor, coordiná las acciones necesarias con tu responsable.</p>';
    $enviado=enviar_mail((string)$alerta['email'],'Aviso de vencimiento - Finanzas',mail_plantilla('Aviso de vencimiento',$cuerpo,app_absolute_url('/php/finanzas.php')));
    if(!$enviado)throw new RuntimeException('No se pudo enviar el correo. Revisá la configuración de correo del sistema.');
    $estadoEnvio=mail_config()['transport']==='log'?'registrado':'notificado';$pdo->prepare('UPDATE finanzas_alertas SET estado=? WHERE id=? AND sector_id=?')->execute([$estadoEnvio,$id,$sid]);
    auditModulo($pdo,$uid,'finanzas_alerta_mail',$alerta['trabajador_nombre']);
    $msg=$estadoEnvio==='registrado'?'Aviso guardado en el registro de correo de prueba; el destinatario no recibió un correo.':'Aviso enviado a '.$alerta['email'].'.';
  }
  if($a==='alerta_marcar_notificada'){$id=(int)($_POST['id']??0);$pdo->prepare("UPDATE finanzas_alertas SET estado='notificado' WHERE id=? AND sector_id=?")->execute([$id,$sid]);auditModulo($pdo,$uid,'finanzas_alerta_estado',"Alerta #$id marcada como notificada");$msg='Alerta marcada como notificada.';}
  if($a==='indicador_guardar'){$id=(int)($_POST['id']??0);$nom=trim($_POST['nombre_indicador']??'');$valor=$_POST['valor']??'';if($nom==='')throw new RuntimeException('Ingresá el indicador.');if($valor===''||(int)$valor<0||(int)$valor>100)throw new RuntimeException('El porcentaje debe estar entre 0 y 100.');$val=(int)$valor;if($id){$pdo->prepare('UPDATE finanzas_indicadores SET nombre_indicador=?,valor_porcentaje=?,actualizado_por=? WHERE id=? AND sector_id=?')->execute([$nom,$val,$uid,$id,$sid]);$msg='Indicador actualizado.';}else{$pdo->prepare('INSERT INTO finanzas_indicadores(sector_id,nombre_indicador,valor_porcentaje,creado_por,actualizado_por) VALUES(?,?,?,?,?)')->execute([$sid,$nom,$val,$uid,$uid]);$msg='Indicador guardado.';}auditModulo($pdo,$uid,'finanzas_indicador',$nom);}
  if($a==='check_guardar'){$id=(int)($_POST['id']??0);$req=trim($_POST['requisito']??'');$categoria=trim($_POST['categoria']??'');$frecuencia=trim($_POST['frecuencia']??'');$estado=trim($_POST['estado']??'PENDIENTE');if($req==='')throw new RuntimeException('Ingresá el requisito.');if(!in_array($estado,['APROBADO','PENDIENTE','OBSERVADO'],true))throw new RuntimeException('Seleccioná un estado de checklist válido.');$existente=null;if($id){$st=$pdo->prepare('SELECT archivo_adjunto FROM finanzas_checklist WHERE id=? AND sector_id=? LIMIT 1');$st->execute([$id,$sid]);$existente=$st->fetch(PDO::FETCH_ASSOC);if(!$existente)throw new RuntimeException('El requisito del checklist ya no existe.');}$nuevoAdjunto=finanzasGuardarAdjunto($_FILES['archivo_adjunto']??[], $uploadDirFinanzas);$subidaChecklistFullPath=$nuevoAdjunto?finanzasRutaAdjunto($nuevoAdjunto):null;$adjunto=$nuevoAdjunto??($existente['archivo_adjunto']??null);if($id){$pdo->prepare('UPDATE finanzas_checklist SET requisito=?,categoria=?,frecuencia=?,completado=?,estado_auditoria=?,archivo_adjunto=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$req,$categoria,$frecuencia,!empty($_POST['completado'])?1:0,$estado,$adjunto,$uid,$id,$sid]);if($nuevoAdjunto&&($existente['archivo_adjunto']??null)){ $viejo=finanzasRutaAdjunto($existente['archivo_adjunto']);if($viejo&&is_file($viejo))@unlink($viejo); }$msg='Checklist actualizado.';}else{$pdo->prepare('INSERT INTO finanzas_checklist(sector_id,requisito,categoria,frecuencia,completado,estado_auditoria,archivo_adjunto,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$sid,$req,$categoria,$frecuencia,!empty($_POST['completado'])?1:0,$estado,$adjunto,$uid,$uid]);$msg='Checklist guardado.';}$subidaChecklistFullPath=null;auditModulo($pdo,$uid,'finanzas_checklist',$req);}
  if($a==='eliminar'){ $tabla=$_POST['tabla']??'';$id=(int)($_POST['id']??0);$permitidas=['finanzas_trabajadores','finanzas_indicadores','finanzas_checklist'];if(!in_array($tabla,$permitidas,true))throw new RuntimeException('Operación inválida.');$adjunto=null;if($tabla==='finanzas_checklist'){$st=$pdo->prepare('SELECT archivo_adjunto FROM finanzas_checklist WHERE id=? AND sector_id=?');$st->execute([$id,$sid]);$adjunto=$st->fetchColumn()?:null;}$pdo->prepare("DELETE FROM $tabla WHERE id=? AND sector_id=?")->execute([$id,$sid]);if($adjunto){$path=finanzasRutaAdjunto($adjunto);if($path&&is_file($path))@unlink($path);}auditModulo($pdo,$uid,'finanzas_eliminar',"$tabla #$id");$msg='Registro eliminado.';}
 }catch(Throwable $e){if($subidaChecklistFullPath&&is_file($subidaChecklistFullPath))@unlink($subidaChecklistFullPath);$err=$e->getMessage();}
}
$trab=$pdo->prepare('SELECT f.*,u.nombre actualizado_nombre FROM finanzas_trabajadores f LEFT JOIN usuarios u ON u.id=f.actualizado_por WHERE f.sector_id=? ORDER BY f.nombre_completo');$trab->execute([$sid]);$trab=$trab->fetchAll();
$inds=$pdo->prepare('SELECT * FROM finanzas_indicadores WHERE sector_id=? ORDER BY fecha_actualizacion DESC');$inds->execute([$sid]);$inds=$inds->fetchAll();
$checks=$pdo->prepare('SELECT * FROM finanzas_checklist WHERE sector_id=? ORDER BY id DESC');$checks->execute([$sid]);$checks=$checks->fetchAll();
$alertas=$pdo->prepare('SELECT * FROM finanzas_alertas WHERE sector_id=? ORDER BY fecha_envio DESC,id DESC');$alertas->execute([$sid]);$alertas=$alertas->fetchAll();
$editTrabajador=null;$editCheck=null;$editIndicador=null;
if($canEdit&&isset($_GET['editar_trabajador'])){foreach($trab as $row)if((int)$row['id']===(int)$_GET['editar_trabajador']){$editTrabajador=$row;break;}}
if($canEdit&&isset($_GET['editar_checklist'])){foreach($checks as $row)if((int)$row['id']===(int)$_GET['editar_checklist']){$editCheck=$row;break;}}
if($canEdit&&isset($_GET['editar_indicador'])){foreach($inds as $row)if((int)$row['id']===(int)$_GET['editar_indicador']){$editIndicador=$row;break;}}

$promedioIndicadores = 0;
$mejorIndicador = null;
if ($inds) {
    $sumaIndicadores = array_sum(array_map(fn($i)=>(int)$i['valor_porcentaje'],$inds));
    $promedioIndicadores = (int)round($sumaIndicadores / count($inds));
    $mejorIndicador = $inds[0];
    foreach ($inds as $ind) {
        if ((int)$ind['valor_porcentaje'] > (int)$mejorIndicador['valor_porcentaje']) $mejorIndicador = $ind;
        
    }
}

?>
<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>
<section class="module-grid">
<div class="module-card"><span class="metric-label">Personal controlado</span><div class="big"><?=count($trab)?></div><small>Registros con seguimiento</small></div>
<div class="module-card"><span class="metric-label">Cumplimiento promedio</span><div class="big"><?=$promedioIndicadores?>%</div><small><?=count($inds)?> indicador(es) cargado(s)</small></div>
<div class="module-card"><span class="metric-label">Mejor indicador</span><div class="big"><?=$mejorIndicador ? (int)$mejorIndicador['valor_porcentaje'].'%' : '—'?></div><small><?=h($mejorIndicador['nombre_indicador']??'Sin indicadores')?></small></div>
</section>
<?php if($canEdit):?>
<section class="module-card" id="gestion">
 <h3><?=$editTrabajador?'Editar trabajador / vencimientos':'Registrar trabajador / vencimientos'?></h3>
 <form method="post" class="module-form"><?=csrf_field()?>
  <input type="hidden" name="accion" value="trabajador_guardar"><input type="hidden" name="id" value="<?=(int)($editTrabajador['id']??0)?>">
  <label>Legajo<input name="legajo" required value="<?=h($editTrabajador['legajo']??'')?>"></label>
  <label>Nombre<input name="nombre" required value="<?=h($editTrabajador['nombre_completo']??'')?>"></label>
  <label>DNI<input name="dni" required value="<?=h($editTrabajador['dni']??'')?>"></label>
  <label>Correo de avisos<input type="email" name="email" value="<?=h($editTrabajador['email']??'')?>"></label>
  <label>Teléfono / WhatsApp<input name="telefono" placeholder="+54..." value="<?=h($editTrabajador['telefono']??'')?>"></label>
  <label>Categoría carnet<input name="categoria" value="<?=h($editTrabajador['categoria_carnet']??'')?>"></label>
  <label>Venc. carnet<input type="date" name="venc_carnet" required value="<?=h($editTrabajador['vencimiento_carnet']??'')?>"></label>
  <label>Curso defensivo<input name="curso" value="<?=h($editTrabajador['curso_defensivo']??'')?>"></label>
  <label>Venc. curso<input type="date" name="venc_defensivo" required value="<?=h($editTrabajador['vencimiento_defensivo']??'')?>"></label>
  <div class="finance-actions"><button class="btn primary"><?=$editTrabajador?'Guardar cambios':'Guardar trabajador'?></button><?php if($editTrabajador):?><a class="btn secondary" href="<?=app_url('/php/finanzas.php#gestion')?>">Cancelar</a><?php endif;?></div>
 </form>
</section>
<?php endif;?>
<div class="module-toolbar" id="seguimiento"><h2>Personal y vencimientos</h2><span class="count-pill">Alertas automáticas al responsable del sector</span></div>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Legajo</th><th>Nombre / contacto</th><th>DNI</th><th>Carnet de conducir</th><th>Curso defensivo</th><th>Responsable</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead><tbody>
<?php foreach($trab as $r): $waMensaje='Aviso NASER Finanzas para '.$r['nombre_completo'].': carnet de conducir '.finanzasEstadoVencimiento($r['vencimiento_carnet'])[0].' ('.$r['vencimiento_carnet'].'); curso defensivo '.finanzasEstadoVencimiento($r['vencimiento_defensivo'])[0].' ('.$r['vencimiento_defensivo'].').'; $waUrl=finanzasWhatsappUrl($r['telefono']??null,$waMensaje); ?>
<tr><td><?=h($r['legajo'])?></td><td><strong><?=h($r['nombre_completo'])?></strong><div class="finance-contact"><?=h($r['email']??'Sin correo')?> · <?=h($r['telefono']??'Sin teléfono')?></div></td><td><?=h($r['dni'])?></td><td><div><?=h($r['categoria_carnet']??'Carnet')?> · <?=h($r['vencimiento_carnet'])?></div><?=finanzasEstadoHtml($r['vencimiento_carnet'])?></td><td><div><?=h($r['curso_defensivo']?:'Curso defensivo')?> · <?=h($r['vencimiento_defensivo'])?></div><?=finanzasEstadoHtml($r['vencimiento_defensivo'])?></td><td><?=h($r['actualizado_nombre']??'Sistema')?></td>
<?php if($canEdit):?><td><div class="finance-actions"><a class="btn secondary" href="<?=app_url('/php/finanzas.php?editar_trabajador='.(int)$r['id'].'#gestion')?>">Editar</a><?php if(filter_var((string)$r['email'],FILTER_VALIDATE_EMAIL)):?><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="trabajador_aviso_mail"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Avisar por correo</button></form><?php endif;?><?php if($waUrl):?><a class="btn secondary" href="<?=h($waUrl)?>" target="_blank" rel="noopener">Avisar por WhatsApp</a><?php endif;?><form method="post" onsubmit="return confirm('¿Eliminar este trabajador?')"><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tabla" value="finanzas_trabajadores"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></div></td><?php endif;?></tr>
<?php endforeach;?><?php if(!$trab):?><tr><td colspan="<?=$canEdit?7:6?>">Todavía no hay trabajadores cargados.</td></tr><?php endif;?></tbody></table></div>

<?php if($canEdit):?><section class="module-grid">
 <div class="module-card"><h3><?=$editIndicador?'Editar indicador':'Nuevo indicador'?></h3><form method="post" class="module-form"><?=csrf_field()?>
  <input type="hidden" name="accion" value="indicador_guardar"><input type="hidden" name="id" value="<?=(int)($editIndicador['id']??0)?>"><label>Indicador<input name="nombre_indicador" required value="<?=h($editIndicador['nombre_indicador']??'')?>"></label><label>Porcentaje<input type="number" min="0" max="100" name="valor" required value="<?=h($editIndicador['valor_porcentaje']??'')?>"></label><div class="finance-actions"><button class="btn primary"><?=$editIndicador?'Guardar cambios':'Guardar indicador'?></button><?php if($editIndicador):?><a class="btn secondary" href="<?=app_url('/php/finanzas.php#indicadores')?>">Cancelar</a><?php endif;?></div>
 </form></div>
 <div class="module-card"><h3><?=$editCheck?'Editar checklist':'Nuevo requisito del checklist'?></h3><p class="checklist-hint">Adjuntá una planilla de Excel, un PDF o un Word para respaldar el requisito.</p><form method="post" enctype="multipart/form-data" class="module-form"><?=csrf_field()?>
  <input type="hidden" name="accion" value="check_guardar"><input type="hidden" name="id" value="<?=(int)($editCheck['id']??0)?>"><label class="full">Requisito<input name="requisito" required value="<?=h($editCheck['requisito']??'')?>"></label><label>Categoría<input name="categoria" value="<?=h($editCheck['categoria']??'')?>"></label><label>Frecuencia<input name="frecuencia" value="<?=h($editCheck['frecuencia']??'')?>"></label><label>Estado<select name="estado"><?php foreach(['PENDIENTE','APROBADO','OBSERVADO'] as $op):?><option <?=($editCheck['estado_auditoria']??'PENDIENTE')===$op?'selected':''?>><?=$op?></option><?php endforeach;?></select></label><label><span>Completado</span><input type="checkbox" name="completado" value="1" <?=!empty($editCheck['completado'])?'checked':''?>></label><label class="full">Excel / PDF / Word (máximo 10 MB)<input type="file" name="archivo_adjunto" accept=".xlsx,.xls,.pdf,.docx"></label>
  <?php if(!empty($editCheck['archivo_adjunto'])):?><div class="full">Adjunto actual: <a href="<?=app_url('/php/finanzas.php?descargar_checklist='.(int)$editCheck['id'])?>"><?=h(basename($editCheck['archivo_adjunto']))?></a></div><?php endif;?><div class="finance-actions"><button class="btn primary"><?=$editCheck?'Guardar cambios':'Guardar requisito'?></button><?php if($editCheck):?><a class="btn secondary" href="<?=app_url('/php/finanzas.php#gestion')?>">Cancelar</a><?php endif;?></div>
 </form></div>
</section><?php endif;?>
<div class="module-toolbar" id="checklist"><h2>Checklist de documentación y actas</h2><span class="count-pill"><?=count($checks)?> requisito(s)</span></div>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Requisito</th><th>Categoría</th><th>Frecuencia</th><th>Estado</th><th>Completado</th><th>Archivo</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead><tbody>
<?php foreach($checks as $c):?><tr><td><strong><?=h($c['requisito'])?></strong></td><td><?=h($c['categoria']??'')?></td><td><?=h($c['frecuencia']??'')?></td><td><?=h($c['estado_auditoria']??'PENDIENTE')?></td><td><?=!empty($c['completado'])?'Sí':'No'?></td><td><?php if(!empty($c['archivo_adjunto'])):?><a href="<?=app_url('/php/finanzas.php?descargar_checklist='.(int)$c['id'])?>">Descargar <?=h(strtoupper(pathinfo($c['archivo_adjunto'],PATHINFO_EXTENSION)))?></a><?php else:?>Sin adjunto<?php endif;?></td><?php if($canEdit):?><td><div class="finance-actions"><a class="btn secondary" href="<?=app_url('/php/finanzas.php?editar_checklist='.(int)$c['id'].'#gestion')?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar este requisito y su adjunto?')"><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tabla" value="finanzas_checklist"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="btn secondary">Eliminar</button></form></div></td><?php endif;?></tr><?php endforeach;?><?php if(!$checks):?><tr><td colspan="<?=$canEdit?7:6?>">No hay requisitos cargados todavía. Podés cargar el checklist del sector y adjuntar la planilla Excel.</td></tr><?php endif;?></tbody></table></div>

<div class="module-toolbar" id="alertas"><h2>Alertas y avisos de vencimiento</h2><span class="count-pill"><?=count($alertas)?> aviso(s)</span></div>
<section class="module-grid">
<div class="module-card"><h3>Listado de trabajadores</h3><p>Control de carnet de conducir y curso de manejo/defensa de conducir.</p><div class="big"><?=count($trab)?></div></div>
<div class="module-card"><h3>Documentación / Actas</h3><p>Consultá los documentos del sector y usá el checklist de esta página para registrar requisitos con adjuntos Excel, PDF o Word.</p><a class="btn secondary" href="<?=app_url('/php/sector.php?sector=finanzas')?>">Abrir documentación</a></div>
<?php if($canEdit):?><div class="module-card"><h3>Nueva alerta</h3><form method="post" class="module-form"><?=csrf_field()?><input type="hidden" name="accion" value="alerta_guardar"><label class="full">Persona / destinatario<input name="trabajador_nombre" required placeholder="Trabajador o responsable del sector"></label><label>Correo<input type="email" name="email"></label><label>Teléfono / WhatsApp<input name="telefono" placeholder="+54 con código de país"></label><label>Vencimiento<input type="date" name="fecha_vencimiento"></label><label>Vía<select name="canal"><option>Correo</option><option>WhatsApp</option><option>Correo y WhatsApp</option><option>Aviso interno</option></select></label><div class="full"><button class="btn primary">Registrar alerta</button></div></form></div><?php endif;?>
</section>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Destinatario</th><th>Correo</th><th>Teléfono</th><th>Canal</th><th>Vencimiento</th><th>Estado del vencimiento</th><th>Aviso / acciones</th></tr></thead><tbody>
<?php foreach($alertas as $a):$alertaEstado=finanzasEstadoVencimiento($a['fecha_vencimiento']??null);$alertaWa=finanzasWhatsappUrl($a['telefono']??null,'Aviso NASER Finanzas para '.$a['trabajador_nombre'].': el vencimiento registrado figura como '.$alertaEstado[0].($a['fecha_vencimiento']?' ('.$a['fecha_vencimiento'].')':'').'. Por favor, coordiná las acciones necesarias con tu responsable.');?><tr><td><?=h($a['trabajador_nombre'])?></td><td><?=h($a['email']??'')?></td><td><?=h($a['telefono']??'')?></td><td><?=h($a['canal']??'')?></td><td><?=h($a['fecha_vencimiento']??'Sin fecha')?></td><td><?=finanzasEstadoHtml($a['fecha_vencimiento']??null)?></td><td><div class="finance-actions"><span class="due-badge due-<?=h(($a['estado']??'pendiente')==='notificado'?'vigente':(($a['estado']??'pendiente')==='registrado'?'proximo':'sin-fecha'))?>"><?=h($a['estado']??'pendiente')?></span><?php if($canEdit&&in_array($a['canal'],['Correo','Correo y WhatsApp'],true)&&filter_var((string)$a['email'],FILTER_VALIDATE_EMAIL)):?><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="alerta_enviar_mail"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="btn secondary">Enviar correo</button></form><?php endif;?><?php if($canEdit&&in_array($a['canal'],['WhatsApp','Correo y WhatsApp'],true)&&$alertaWa):?><a class="btn secondary" href="<?=h($alertaWa)?>" target="_blank" rel="noopener">Abrir WhatsApp</a><?php endif;?><?php if($canEdit&&($a['estado']??'pendiente')!=='notificado'):?><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="alerta_marcar_notificada"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="btn secondary">Marcar notificada</button></form><?php endif;?></div></td></tr><?php endforeach;?><?php if(!$alertas):?><tr><td colspan="7">No hay alertas registradas.</td></tr><?php endif;?></tbody></table></div>
<div class="module-toolbar"><h2>Panel gráfico de indicadores</h2><span class="count-pill">Datos en tiempo real de MariaDB</span></div>
<section class="chart-grid">
    <div class="chart-card">
        <h3>Indicadores del sector</h3>
        <p>El grafico se genera automaticamente con los valores cargados por el responsable de Finanzas.</p>
        <?php if(!$inds): ?>
            <div class="indicator-empty">Todavia no hay indicadores cargados. Al registrar el primero, aparecerá automáticamente acá.</div>
        <?php else: ?>
            <div class="chart-bars">
                <?php foreach($inds as $ind): $v=max(0,min(100,(int)$ind['valor_porcentaje'])); ?>
                    <div class="chart-row" title="<?=h($ind['nombre_indicador'])?>: <?=$v?>%">
                        <div class="chart-label"><?=h($ind['nombre_indicador'])?></div>
                        <div class="chart-track"><div class="chart-fill" style="width:<?=$v?>%"></div></div>
                        <div class="chart-value"><?=$v?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3>Cumplimiento general</h3>
        <p>Promedio de todos los indicadores actualmente registrados.</p>
        <div class="donut-wrap">
            <div class="donut" style="--p:<?=$promedioIndicadores?>">
                <div class="donut-text"><strong><?=$promedioIndicadores?>%</strong><span>PROMEDIO GENERAL</span></div>
            </div>
        </div>
    </div>
</section>

<div class="module-toolbar" id="indicadores"><h2>Detalle de indicadores</h2></div>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Indicador</th>
<th>Valor</th><th>Actualizado</th><?php if($canEdit):?><th>Acciones</th><?php endif;
?></tr></thead>
<tbody>
    <?php 
    foreach($inds as $r):?><tr><td>
        <?=h($r['nombre_indicador'])
        ?></td><td><strong>
            <?=(int)$r['valor_porcentaje']?>%</strong></td>
            <td><?=h($r['fecha_actualizacion'])?>
        </td><?php if($canEdit):?><td><div class="finance-actions"><a class="btn secondary" href="<?=app_url('/php/finanzas.php?editar_indicador='.(int)$r['id'].'#gestion')?>">Editar</a><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tabla" value="finanzas_indicadores"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></div></td><?php endif;?></tr><?php endforeach;?><?php if(!$inds):?><tr><td colspan="<?=$canEdit?4:3?>">No hay indicadores cargados.</td></tr><?php endif;?></tbody></table></div>

</main></div></body></html>
