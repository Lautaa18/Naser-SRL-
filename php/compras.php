<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

$slug = 'compras';
$st = $pdo->prepare('SELECT id,nombre,slug FROM sectores WHERE slug=? LIMIT 1');
$st->execute([$slug]);
$sector = $st->fetch();
if (!$sector) { http_response_code(404); exit('Sector no encontrado.'); }
$sid = (int)$sector['id'];
if (!puedeVerSector($pdo,$sid)) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$canEdit = puedeEditarSector($pdo,$sid);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg=''; $err='';

function auditModulo(PDO $pdo,int $uid,string $accion,string $detalle): void {
    try {
        $st=$pdo->prepare('INSERT INTO actividad(usuario_id,accion,detalle) VALUES(?,?,?)');
        $st->execute([$uid,$accion,$detalle]);
    } catch(Throwable $e) {}
}
function exigirEdicion(bool $canEdit): void {
    if(!$canEdit) { http_response_code(403); exit('No tenés permiso para modificar este sector.'); }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión de Compras y Suministros | NASER SGI</title><link rel="stylesheet" href="<?=app_url('/style.css')?>">
<style>
:root{--ng:#08783e;--nd:#164c2d;--ns:#edf7f1;--nl:#dfe7e1}
.module-hero{position:relative;overflow:hidden;background:linear-gradient(125deg,#123f28,#08783e);color:#fff;border-radius:20px;padding:26px 28px;margin:0 0 20px;box-shadow:0 12px 30px rgba(20,70,40,.12)}
.module-hero:after{content:"";position:absolute;width:220px;height:220px;border-radius:50%;right:-70px;top:-100px;background:rgba(255,255,255,.08)}
.module-hero .eyebrow{color:#d8f3e2}.module-hero h1{margin:4px 0 7px;font-size:30px}.module-hero p{margin:0;color:#e9f7ee}
.module-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}.module-tabs a{padding:9px 14px;border:1px solid var(--nl);border-radius:999px;background:#fff;color:var(--nd);font-size:13px;font-weight:800;text-decoration:none}
.module-permission{padding:11px 14px;border:1px solid var(--nl);background:#f7faf8;border-radius:12px;margin-bottom:18px;font-size:13px}
.module-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px;margin:18px 0 24px}
.module-card{background:#fff;border:1px solid var(--nl);border-radius:17px;padding:19px;box-shadow:0 5px 18px rgba(31,41,55,.045)}
.module-card h3{margin:0 0 8px}.module-card .big{font-size:32px;line-height:1;font-weight:900;color:var(--ng);margin-top:9px}
.module-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:25px 0 12px}.module-toolbar h2{margin:0}
.module-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.module-form .full{grid-column:1/-1}.module-form label{display:flex;flex-direction:column;gap:7px;font-size:12px;font-weight:800;color:#374151}
.module-form input,.module-form select,.module-form textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d6dfd8;border-radius:10px;background:#fbfdfb}.module-form textarea{min-height:82px}
.table-wrapper{overflow:auto;border:1px solid var(--nl);border-radius:16px;background:#fff}.module-table{width:100%;border-collapse:collapse;min-width:760px}.module-table th,.module-table td{padding:12px 13px;border-bottom:1px solid #edf1ee;text-align:left;font-size:13px}.module-table th{background:#f1f7f3;color:#27583a;font-size:11px;text-transform:uppercase}.module-table tbody tr:hover{background:#fbfdfb}
.module-actions{display:flex;gap:6px;flex-wrap:wrap}.module-note,.module-error{padding:13px 15px;border-radius:11px;margin:12px 0;font-weight:700;font-size:13px}.module-note{background:#eaf7ef;color:#176337}.module-error{background:#fff1f0;color:#9b231b}
.flow{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin:15px 0 22px}.flow-step{background:#f2f7f3;border:1px solid #dce8df;border-radius:12px;padding:12px 8px;text-align:center;font-size:11px;font-weight:800;color:#315a3e}.flow-step b{display:block;font-size:17px;color:var(--ng);margin-bottom:4px}
@media(max-width:900px){.module-grid{grid-template-columns:1fr 1fr}.flow{grid-template-columns:repeat(3,1fr)}}@media(max-width:650px){.module-grid,.module-form{grid-template-columns:1fr}.module-form .full{grid-column:auto}.flow{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body><div class="app"><?php sidebar($pdo,$slug); ?><main class="content">
<section class="module-hero"><p class="eyebrow">SERVICIOS NASER SRL · COMPRAS Y SUMINISTROS</p><h1>Gestión de Compras y Suministros</h1><p>Pedidos, proveedores, documentación, seguimiento y recepción.</p></section>
<div class="module-tabs"><a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación</a><a href="#gestion">⚙ Gestión</a><a href="#seguimiento">📊 Seguimiento</a></div>
<div class="module-permission">🔐 <?php if($canEdit): ?>Podés consultar y modificar este sector.<?php else: ?>Modo consulta: solo el responsable del sector y el administrador pueden modificar.<?php endif; ?></div>

<?php
$uploadDir=dirname(__DIR__).'/uploads/compras'; if(!is_dir($uploadDir))@mkdir($uploadDir,0775,true);
if($_SERVER['REQUEST_METHOD']==='POST'){
 exigirEdicion($canEdit);
 try{
  $a=$_POST['accion']??'';
  if($a==='guardar'){
   $id=(int)($_POST['id']??0);$desc=trim($_POST['descripcion']??'');$cant=max(1,(int)($_POST['cantidad']??1));$prio=$_POST['prioridad']??'Normal';$prov=trim($_POST['proveedor']??'Pendiente');$et=max(1,min(6,(int)($_POST['etapa']??1)));$estado=trim($_POST['estado_logistico']??'Pedido cargado');
   if($desc==='')throw new RuntimeException('Ingresá la descripción.');
   if($id){$pdo->prepare('UPDATE compras SET descripcion=?,cantidad=?,prioridad=?,proveedor=?,etapa=?,estado_logistico=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$desc,$cant,$prio,$prov,$et,$estado,$uid,$id,$sid]);$compraId=$id;$msg='Compra actualizada.';}
   else{$codigo='CMP-'.date('Y').'-'.str_pad((string)((int)$pdo->query('SELECT COUNT(*)+1 FROM compras')->fetchColumn()),4,'0',STR_PAD_LEFT);$pdo->prepare('INSERT INTO compras(sector_id,codigo,sector,descripcion,cantidad,prioridad,proveedor,etapa,estado_logistico,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$sid,$codigo,$sector['nombre'],$desc,$cant,$prio,$prov,$et,$estado,$uid,$uid]);$compraId=(int)$pdo->lastInsertId();$msg="Pedido $codigo creado.";}
   if(!empty($_FILES['archivo']['name'])&&($_FILES['archivo']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){$orig=$_FILES['archivo']['name'];$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,['pdf','doc','docx','xls','xlsx'],true))throw new RuntimeException('Adjunto no permitido.');$safe='compra-'.$compraId.'-'.time().'.'.$ext;if(!move_uploaded_file($_FILES['archivo']['tmp_name'],$uploadDir.'/'.$safe))throw new RuntimeException('No se pudo guardar el adjunto.');$pdo->prepare('INSERT INTO compras_documentos(compra_id,etapa,nombre_archivo,ruta_archivo,creado_por) VALUES(?,?,?,?,?)')->execute([$compraId,$et,$orig,'compras/'.$safe,$uid]);}
   auditModulo($pdo,$uid,'compras_guardar',"Compra #$compraId");
  }
  
  if($a==='documento_etapa'){
    $id=(int)($_POST['compra_id']??0);$et=max(1,min(6,(int)($_POST['etapa_doc']??1)));
    $st=$pdo->prepare('SELECT id FROM compras WHERE id=? AND sector_id=?');$st->execute([$id,$sid]);if(!$st->fetchColumn())throw new RuntimeException('Compra inválida.');
    if(empty($_FILES['archivo_etapa']['name'])||($_FILES['archivo_etapa']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Seleccioná un archivo.');
    $orig=$_FILES['archivo_etapa']['name'];$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,['pdf','doc','docx','xls','xlsx'],true))throw new RuntimeException('Formato no permitido.');
    $safe='compra-'.$id.'-etapa-'.$et.'-'.time().'.'.$ext;if(!move_uploaded_file($_FILES['archivo_etapa']['tmp_name'],$uploadDir.'/'.$safe))throw new RuntimeException('No se pudo guardar.');
    $pdo->prepare('INSERT INTO compras_documentos(compra_id,etapa,nombre_archivo,ruta_archivo,creado_por) VALUES(?,?,?,?,?)')->execute([$id,$et,$orig,'compras/'.$safe,$uid]);
    $pdo->prepare('UPDATE compras SET etapa=GREATEST(etapa,?),actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$et,$uid,$id,$sid]);
    auditModulo($pdo,$uid,'compras_documento',"Compra #$id etapa $et");$msg='Documento de la etapa cargado.';
  }
  if($a==='eliminar'){$id=(int)($_POST['id']??0);$pdo->prepare('DELETE FROM compras WHERE id=? AND sector_id=?')->execute([$id,$sid]);auditModulo($pdo,$uid,'compras_eliminar',"Compra #$id");$msg='Compra eliminada.';}
  if($a==='encuesta'){$id=(int)($_POST['compra_id']??0);$st=$pdo->prepare('SELECT id FROM compras WHERE id=? AND sector_id=?');$st->execute([$id,$sid]);if(!$st->fetchColumn())throw new RuntimeException('Compra inválida.');$pdo->prepare('INSERT INTO compras_encuesta(compra_id,calidad,estado_fisico,cumplimiento_entrega,observaciones,creado_por) VALUES(?,?,?,?,?,?)')->execute([$id,$_POST['calidad']??'',$_POST['estado_fisico']??'',$_POST['entrega']??'',trim($_POST['observaciones']??''),$uid]);$pdo->prepare('UPDATE compras SET etapa=6,estado_logistico=?,actualizado_por=?,actualizado_en=NOW() WHERE id=?')->execute(['Recepción evaluada',$uid,$id]);auditModulo($pdo,$uid,'compras_encuesta',"Compra #$id");$msg='Recepción registrada.';}
 }catch(Throwable $e){$err=$e->getMessage();}
}
$edit=null;if($canEdit&&!empty($_GET['editar'])){$st=$pdo->prepare('SELECT * FROM compras WHERE id=? AND sector_id=?');$st->execute([(int)$_GET['editar'],$sid]);$edit=$st->fetch();}
$st=$pdo->prepare('SELECT c.*,u.nombre actualizado_nombre,(SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id) documentos FROM compras c LEFT JOIN usuarios u ON u.id=c.actualizado_por WHERE c.sector_id=? ORDER BY c.fecha_creacion DESC');$st->execute([$sid]);$rows=$st->fetchAll();
$docsCompra=$pdo->prepare('SELECT d.*,c.codigo FROM compras_documentos d JOIN compras c ON c.id=d.compra_id WHERE c.sector_id=? ORDER BY d.fecha_subida DESC');$docsCompra->execute([$sid]);$docsCompra=$docsCompra->fetchAll();
$etapaNombre=[1=>'Pedido',2=>'Solicitud de presupuesto',3=>'Orden de compra',4=>'Factura',5=>'Pago',6=>'Entrega / recepción'];

?>
<div class="flow"><div class="flow-step"><b>1</b>Pedido</div><div class="flow-step"><b>2</b>Solicitud presupuesto</div><div class="flow-step"><b>3</b>Orden de compra</div><div class="flow-step"><b>4</b>Factura</div><div class="flow-step"><b>5</b>Pago</div><div class="flow-step"><b>6</b>Entrega / recepción</div></div><?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>
<section class="module-grid"><div class="module-card"><h3>Pedidos</h3><div class="big"><?=count($rows)?></div></div><div class="module-card"><h3>En proceso</h3><div class="big"><?=count(array_filter($rows,fn($r)=>(int)$r['etapa']<6))?></div></div><div class="module-card"><h3>Finalizados</h3><div class="big"><?=count(array_filter($rows,fn($r)=>(int)$r['etapa']===6))?></div></div></section>
<?php if($canEdit):?><section class="module-card" id="gestion"><h3><?=$edit?'Modificar pedido':'Nuevo pedido'?></h3><form method="post" enctype="multipart/form-data" class="module-form"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><label class="full">Descripción<textarea name="descripcion" required><?=h($edit['descripcion']??'')?></textarea></label><label>Cantidad<input type="number" min="1" name="cantidad" value="<?=h($edit['cantidad']??1)?>"></label><label>Prioridad<select name="prioridad"><?php foreach(['Normal','Urgente','Critico'] as $p):?><option <?=($edit['prioridad']??'Normal')===$p?'selected':''?>><?=h($p)?></option><?php endforeach;?></select></label><label>Proveedor<input name="proveedor" value="<?=h($edit['proveedor']??'Pendiente')?>"></label><label>Etapa<input type="number" min="1" max="6" name="etapa" value="<?=h($edit['etapa']??1)?>"></label><label class="full">Estado logístico<input name="estado_logistico" value="<?=h($edit['estado_logistico']??'Pedido cargado')?>"></label><label class="full">Adjunto (PDF/Office)<input type="file" name="archivo"></label><div class="full"><button class="btn primary">Guardar</button><?php if($edit):?> <a class="btn secondary" href="<?=app_url('/php/compras.php')?>">Cancelar</a><?php endif;?></div></form></section><?php endif;?>
<div class="module-toolbar" id="seguimiento"><h2>Seguimiento de compras</h2><span class="count-pill"><?=count($rows)?> pedido(s)</span></div><div class="table-wrapper"><table class="module-table"><thead><tr><th>Código</th><th>Descripción</th><th>Prioridad</th><th>Proveedor</th><th>Etapa</th><th>Estado</th><th>Responsable</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><strong><?=h($r['codigo'])?></strong></td><td><?=h($r['descripcion'])?><br><small><?=(int)$r['documentos']?> adjunto(s)</small></td><td><?=h($r['prioridad'])?></td><td><?=h($r['proveedor'])?></td><td><?=(int)$r['etapa']?>/6</td><td><?=h($r['estado_logistico'])?></td><td><?=h($r['actualizado_nombre']??'Sistema')?></td><?php if($canEdit):?><td class="module-actions"><a class="btn secondary" href="?editar=<?=(int)$r['id']?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar este pedido?')"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>

<?php if($canEdit && $rows):?><section class="module-card" style="margin-top:18px" id="documentos-compra"><h3>Documentos por etapa de compra</h3><p>Subí el PDF o archivo correspondiente a Pedido, Presupuesto, Orden, Factura, Pago o Entrega.</p><form method="post" enctype="multipart/form-data" class="module-form"><input type="hidden" name="accion" value="documento_etapa"><label>Compra<select name="compra_id"><?php foreach($rows as $r):?><option value="<?=(int)$r['id']?>"><?=h($r['codigo'].' - '.$r['descripcion'])?></option><?php endforeach;?></select></label><label>Etapa<select name="etapa_doc"><?php foreach($etapaNombre as $n=>$et):?><option value="<?=$n?>"><?=$n?> - <?=h($et)?></option><?php endforeach;?></select></label><label class="full">Archivo<input type="file" name="archivo_etapa" required></label><div><button class="btn primary">Subir y actualizar etapa</button></div></form></section><?php endif;?>
<div class="module-toolbar"><h2>Documentos del ciclo</h2><span class="count-pill"><?=count($docsCompra)?> archivo(s)</span></div><div class="table-wrapper"><table class="module-table"><thead><tr><th>Compra</th><th>Etapa</th><th>Archivo</th><th>Fecha</th></tr></thead><tbody><?php foreach($docsCompra as $d):?><tr><td><?=h($d['codigo'])?></td><td><?=h($etapaNombre[(int)$d['etapa']]??('Etapa '.$d['etapa']))?></td><td><?=h($d['nombre_archivo'])?></td><td><?=h($d['fecha_subida'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($canEdit && $rows):?><section class="module-card" style="margin-top:18px"><h3>Encuesta de recepción</h3><form method="post" class="module-form"><input type="hidden" name="accion" value="encuesta"><label>Compra<select name="compra_id"><?php foreach($rows as $r):?><option value="<?=(int)$r['id']?>"><?=h($r['codigo'].' - '.$r['descripcion'])?></option><?php endforeach;?></select></label><label>Calidad<select name="calidad"><option>Conforme</option><option>Observada</option><option>No conforme</option></select></label><label>Estado físico<select name="estado_fisico"><option>Bueno</option><option>Regular</option><option>Malo</option></select></label><label>Entrega<select name="entrega"><option>En término</option><option>Demorada</option></select></label><label class="full">Observaciones<textarea name="observaciones"></textarea></label><div><button class="btn primary">Guardar recepción</button></div></form></section><?php endif;?>

</main></div></body></html>
