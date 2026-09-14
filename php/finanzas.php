<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

$slug = 'finanzas';
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
<title>Gestión de Finanzas | NASER SGI</title><link rel="stylesheet" href="<?=app_url('/style.css')?>">
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
<div class="module-tabs"><a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación</a><a href="#gestion">⚙ Gestión</a><a href="#seguimiento">📊 Seguimiento</a></div>
<div class="module-permission">🔐 <?php if($canEdit): ?>Podés consultar y modificar este sector.<?php else: ?>Modo consulta: solo el responsable del sector y el administrador pueden modificar.<?php endif; ?></div>

<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
 exigirEdicion($canEdit);
 try{
  $a=$_POST['accion']??'';
  if($a==='trabajador_guardar'){
   $id=(int)($_POST['id']??0);$leg=trim($_POST['legajo']??'');$nom=trim($_POST['nombre']??'');$dni=trim($_POST['dni']??'');$cat=trim($_POST['categoria']??'');$vc=$_POST['venc_carnet']??'';$curso=trim($_POST['curso']??'');$vd=$_POST['venc_defensivo']??'';
   if($leg===''||$nom===''||$dni===''||$vc===''||$vd==='')throw new RuntimeException('Completá los datos obligatorios.');
   if($id){$pdo->prepare('UPDATE finanzas_trabajadores SET legajo=?,nombre_completo=?,dni=?,categoria_carnet=?,vencimiento_carnet=?,curso_defensivo=?,vencimiento_defensivo=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$leg,$nom,$dni,$cat,$vc,$curso,$vd,$uid,$id,$sid]);$msg='Trabajador actualizado.';}
   else{$pdo->prepare('INSERT INTO finanzas_trabajadores(sector_id,legajo,nombre_completo,dni,sector,categoria_carnet,vencimiento_carnet,curso_defensivo,vencimiento_defensivo,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$sid,$leg,$nom,$dni,$sector['nombre'],$cat,$vc,$curso,$vd,$uid,$uid]);$msg='Trabajador registrado.';}
   auditModulo($pdo,$uid,'finanzas_trabajador',$nom);
  }
  
  if($a==='alerta_guardar'){
    $trabajador=trim($_POST['trabajador_nombre']??'');$email=trim($_POST['email']??'');$telefono=trim($_POST['telefono']??'');$canal=trim($_POST['canal']??'Correo');
    if($trabajador==='') throw new RuntimeException('Indicá a quién corresponde la alerta.');
    $pdo->prepare('INSERT INTO finanzas_alertas(sector_id,trabajador_nombre,email,telefono,canal,creado_por,estado,fecha_vencimiento) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$trabajador,$email,$telefono,$canal,$uid,'pendiente',$_POST['fecha_vencimiento']?:null]);
    auditModulo($pdo,$uid,'finanzas_alerta',$trabajador);$msg='Alerta registrada. Queda disponible para seguimiento y posterior envío.';
  }
  if($a==='indicador_guardar'){$nom=trim($_POST['nombre_indicador']??'');$val=max(0,min(100,(int)($_POST['valor']??0)));if($nom==='')throw new RuntimeException('Ingresá el indicador.');$pdo->prepare('INSERT INTO finanzas_indicadores(sector_id,nombre_indicador,valor_porcentaje,creado_por,actualizado_por) VALUES(?,?,?,?,?)')->execute([$sid,$nom,$val,$uid,$uid]);auditModulo($pdo,$uid,'finanzas_indicador',$nom);$msg='Indicador guardado.';}
  if($a==='check_guardar'){$req=trim($_POST['requisito']??'');if($req==='')throw new RuntimeException('Ingresá el requisito.');$pdo->prepare('INSERT INTO finanzas_checklist(sector_id,requisito,categoria,frecuencia,completado,estado_auditoria,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$req,trim($_POST['categoria']??''),trim($_POST['frecuencia']??''),!empty($_POST['completado'])?1:0,trim($_POST['estado']??'APROBADO'),$uid,$uid]);auditModulo($pdo,$uid,'finanzas_checklist',$req);$msg='Checklist guardado.';}
  if($a==='eliminar'){ $tabla=$_POST['tabla']??'';$id=(int)($_POST['id']??0);$permitidas=['finanzas_trabajadores','finanzas_indicadores','finanzas_checklist'];if(!in_array($tabla,$permitidas,true))throw new RuntimeException('Operación inválida.');$pdo->prepare("DELETE FROM $tabla WHERE id=? AND sector_id=?")->execute([$id,$sid]);auditModulo($pdo,$uid,'finanzas_eliminar',"$tabla #$id");$msg='Registro eliminado.';}
 }catch(Throwable $e){$err=$e->getMessage();}
}
$trab=$pdo->prepare('SELECT f.*,u.nombre actualizado_nombre FROM finanzas_trabajadores f LEFT JOIN usuarios u ON u.id=f.actualizado_por WHERE f.sector_id=? ORDER BY f.nombre_completo');$trab->execute([$sid]);$trab=$trab->fetchAll();
$inds=$pdo->prepare('SELECT * FROM finanzas_indicadores WHERE sector_id=? ORDER BY fecha_actualizacion DESC');$inds->execute([$sid]);$inds=$inds->fetchAll();
$checks=$pdo->prepare('SELECT * FROM finanzas_checklist WHERE sector_id=? ORDER BY id DESC');$checks->execute([$sid]);$checks=$checks->fetchAll();
$alertas=$pdo->prepare('SELECT * FROM finanzas_alertas WHERE sector_id=? ORDER BY fecha_envio DESC,id DESC');$alertas->execute([$sid]);$alertas=$alertas->fetchAll();

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
<?php if($canEdit):?><section class="module-card" id="gestion"><h3>Registrar trabajador / vencimientos</h3><form method="post" class="module-form"><input type="hidden" name="accion" value="trabajador_guardar"><label>Legajo<input name="legajo" required></label><label>Nombre<input name="nombre" required></label><label>DNI<input name="dni" required></label><label>Categoría carnet<input name="categoria"></label><label>Venc. carnet<input type="date" name="venc_carnet" required></label><label>Curso defensivo<input name="curso"></label><label>Venc. curso<input type="date" name="venc_defensivo" required></label><div><button class="btn primary">Guardar</button></div></form></section><?php endif;?>
<div class="module-toolbar" id="seguimiento"><h2>Personal y vencimientos</h2></div><div class="table-wrapper"><table class="module-table"><thead><tr><th>Legajo</th><th>Nombre</th><th>DNI</th><th>Carnet</th><th>Curso</th><th>Responsable</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead><tbody><?php foreach($trab as $r):?><tr><td><?=h($r['legajo'])?></td><td><strong><?=h($r['nombre_completo'])?></strong></td><td><?=h($r['dni'])?></td><td><?=h($r['vencimiento_carnet'])?></td><td><?=h($r['vencimiento_defensivo'])?></td><td><?=h($r['actualizado_nombre']??'Sistema')?></td><?php if($canEdit):?><td><form method="post" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tabla" value="finanzas_trabajadores"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>
<?php if($canEdit):?><section class="module-grid"><div class="module-card"><h3>Nuevo indicador</h3><form method="post" class="module-form"><input type="hidden" name="accion" value="indicador_guardar"><label>Indicador<input name="nombre_indicador" required></label><label>Porcentaje<input type="number" min="0" max="100" name="valor" required></label><div><button class="btn primary">Guardar</button></div></form></div><div class="module-card"><h3>Nuevo checklist</h3><form method="post" class="module-form"><input type="hidden" name="accion" value="check_guardar"><label class="full">Requisito<input name="requisito" required></label><label>Categoría<input name="categoria"></label><label>Frecuencia<input name="frecuencia"></label><label>Estado<select name="estado"><option>APROBADO</option><option>PENDIENTE</option><option>OBSERVADO</option></select></label><label><span>Completado</span><input type="checkbox" name="completado" value="1"></label><div><button class="btn primary">Guardar</button></div></form></div></section><?php endif;?>


<div class="module-toolbar"><h2>Alertas y avisos de vencimiento</h2><span class="count-pill"><?=count($alertas)?> aviso(s)</span></div>
<section class="module-grid">
<div class="module-card"><h3>Listado de trabajadores</h3><p>Control de carnet de conducir y curso de manejo/defensa de conducir.</p><div class="big"><?=count($trab)?></div></div>
<div class="module-card"><h3>Documentación / Actas</h3><p>La documentación y los Excel de checklist se administran desde la documentación del sector.</p><a class="btn secondary" href="<?=app_url('/php/sector.php?sector=finanzas')?>">Abrir documentación</a></div>
<?php if($canEdit):?><div class="module-card"><h3>Nueva alerta</h3><form method="post" class="module-form"><input type="hidden" name="accion" value="alerta_guardar"><label class="full">Persona / destinatario<input name="trabajador_nombre" required placeholder="Trabajador o Karina"></label><label>Correo<input type="email" name="email"></label><label>Teléfono<input name="telefono" placeholder="+54..."></label><label>Vencimiento<input type="date" name="fecha_vencimiento"></label><label>Vía<select name="canal"><option>Correo</option><option>WhatsApp</option><option>Correo y WhatsApp</option><option>Aviso interno</option></select></label><div class="full"><button class="btn primary">Registrar alerta</button></div></form></div><?php endif;?>
</section>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Destinatario</th><th>Correo</th><th>Teléfono</th><th>Canal</th><th>Vencimiento</th><th>Estado</th></tr></thead><tbody><?php foreach($alertas as $a):?><tr><td><?=h($a['trabajador_nombre'])?></td><td><?=h($a['email']??'')?></td><td><?=h($a['telefono']??'')?></td><td><?=h($a['canal']??'')?></td><td><?=h($a['fecha_vencimiento']??'')?></td><td><?=h($a['estado']??'pendiente')?></td></tr><?php endforeach;?></tbody></table></div>
<div class="module-toolbar"><h2>Panel gráfico de indicadores</h2><span class="count-pill">Datos en tiempo real de MariaDB</span></div>
<section class="chart-grid">
    <div class="chart-card">
        <h3>Indicadores del sector</h3>
        <p>El gráfico se genera automáticamente con los valores cargados por el responsable de Finanzas.</p>
        <?php if(!$inds): ?>
            <div class="indicator-empty">Todavía no hay indicadores cargados. Al registrar el primero, aparecerá automáticamente acá.</div>
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

<div class="module-toolbar" id="indicadores"><h2>Detalle de indicadores</h2></div><div class="table-wrapper"><table class="module-table"><thead><tr><th>Indicador</th><th>Valor</th><th>Actualizado</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead><tbody><?php foreach($inds as $r):?><tr><td><?=h($r['nombre_indicador'])?></td><td><strong><?=(int)$r['valor_porcentaje']?>%</strong></td><td><?=h($r['fecha_actualizacion'])?></td><?php if($canEdit):?><td><form method="post"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tabla" value="finanzas_indicadores"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>

</main></div></body></html>
