<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

$slug = 'rrhh';
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
<title>Gestión de Recursos Humanos | NASER SGI</title><link rel="stylesheet" href="<?=app_url('/style.css')?>">
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

.calendar-wrap{background:#fff;border:1px solid var(--nl);border-radius:17px;padding:18px;margin:18px 0}
.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:7px}.cal-head{text-align:center;font-size:11px;font-weight:900;color:#52705b;padding:7px}
.cal-day{min-height:82px;border:1px solid #e6ece7;border-radius:10px;padding:7px;background:#fbfdfb}.cal-day.muted{opacity:.35}.cal-num{font-weight:900;font-size:12px}.cal-event{display:block;margin-top:5px;padding:4px 5px;border-radius:6px;background:#e9f6ee;color:#176337;font-size:9px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cal-event.overdue{background:#fff0ef;color:#a62b22}.alert-list{display:grid;gap:9px}.alert-item{display:flex;justify-content:space-between;gap:12px;padding:11px;border-radius:10px;background:#f8faf8;border:1px solid #e5ebe6;font-size:12px}
</style>
</head>
<body><div class="app"><?php sidebar($pdo,$slug); ?><main class="content">
<section class="module-hero"><p class="eyebrow">SERVICIOS NASER SRL · RECURSOS HUMANOS</p><h1>Gestión de Recursos Humanos</h1><p>Licencias, vencimientos, capacitaciones y seguimiento del personal.</p></section>
<div class="module-tabs"><a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación</a><a href="#gestion">⚙ Gestión</a><a href="#seguimiento">📊 Seguimiento</a></div>
<div class="module-permission">🔐 <?php if($canEdit): ?>Podés consultar y modificar este sector.<?php else: ?>Modo consulta: solo el responsable del sector y el administrador pueden modificar.<?php endif; ?></div>

<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
    exigirEdicion($canEdit);
    try{
        $a=$_POST['accion']??'';
        if($a==='guardar'){
            $id=(int)($_POST['id']??0); $empleado=trim($_POST['empleado_nombre']??''); $tipo=trim($_POST['tipo']??'');
            $inicio=$_POST['fecha_inicio']?:null; $vence=$_POST['fecha_vencimiento']??''; $obs=trim($_POST['observaciones']??'');
            if($empleado===''||$tipo===''||$vence==='') throw new RuntimeException('Completá empleado, tipo y vencimiento.');
            if($id){
                $st=$pdo->prepare('UPDATE rrhh_vencimientos SET empleado_nombre=?,tipo=?,fecha_inicio=?,fecha_vencimiento=?,observaciones=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?');
                $st->execute([$empleado,$tipo,$inicio,$vence,$obs,$uid,$id,$sid]); $msg='Registro actualizado.';
                auditModulo($pdo,$uid,'rrhh_editar',"RRHH #$id - $empleado");
            }else{
                $st=$pdo->prepare('INSERT INTO rrhh_vencimientos(sector_id,empleado_nombre,tipo,fecha_inicio,fecha_vencimiento,observaciones,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$empleado,$tipo,$inicio,$vence,$obs,$uid,$uid]); $msg='Registro creado.';
                auditModulo($pdo,$uid,'rrhh_crear',"RRHH - $empleado");
            }
        }
        if($a==='eliminar'){
            $id=(int)($_POST['id']??0);
            $pdo->prepare('DELETE FROM rrhh_vencimientos WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            auditModulo($pdo,$uid,'rrhh_eliminar',"RRHH #$id"); $msg='Registro eliminado.';
        }
    }catch(Throwable $e){$err=$e->getMessage();}
}
$edit=null; if($canEdit && !empty($_GET['editar'])){$st=$pdo->prepare('SELECT * FROM rrhh_vencimientos WHERE id=? AND sector_id=?');$st->execute([(int)$_GET['editar'],$sid]);$edit=$st->fetch();}
$rows=$pdo->prepare('SELECT r.*,u.nombre creado_nombre,ua.nombre actualizado_nombre FROM rrhh_vencimientos r LEFT JOIN usuarios u ON u.id=r.creado_por LEFT JOIN usuarios ua ON ua.id=r.actualizado_por WHERE r.sector_id=? ORDER BY r.fecha_vencimiento,r.empleado_nombre');
$rows->execute([$sid]); $rows=$rows->fetchAll();
$hoy=date('Y-m-d'); $lim=date('Y-m-d',strtotime('+30 days')); $vencidos=0;$proximos=0;foreach($rows as $r){if($r['fecha_vencimiento']<$hoy)$vencidos++;elseif($r['fecha_vencimiento']<=$lim)$proximos++;}
$alertasRRHH=array_values(array_filter($rows,fn($r)=>$r['fecha_vencimiento'] <= $lim));
$mes=(int)($_GET['mes']??date('n')); $anio=(int)($_GET['anio']??date('Y'));
if($mes<1||$mes>12)$mes=(int)date('n'); if($anio<2020||$anio>2100)$anio=(int)date('Y');
$primerDia=sprintf('%04d-%02d-01',$anio,$mes); $diasMes=(int)date('t',strtotime($primerDia)); $inicioSemana=(int)date('N',strtotime($primerDia));
$eventosPorDia=[]; foreach($rows as $r){if(substr($r['fecha_vencimiento'],0,7)===sprintf('%04d-%02d',$anio,$mes)){$d=(int)substr($r['fecha_vencimiento'],8,2);$eventosPorDia[$d][]=$r;}}

?>
<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>
<section class="module-grid"><div class="module-card"><h3>Total registros</h3><div class="big"><?=count($rows)?></div></div><div class="module-card"><h3>Próximos 30 días</h3><div class="big"><?=$proximos?></div></div><div class="module-card"><h3>Vencidos</h3><div class="big"><?=$vencidos?></div></div></section>
<?php if($canEdit):?><section class="module-card" id="gestion"><h3><?=$edit?'Modificar registro':'Nueva licencia / vencimiento'?></h3>
<form method="post" class="module-form"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
<label>Empleado<input name="empleado_nombre" required value="<?=h($edit['empleado_nombre']??'')?>"></label>
<label>Tipo<select name="tipo" required><?php foreach(['Licencia Medica','Licencia Vacaciones','Examen Periodico','Carnet de Conducir','Capacitacion','Otro'] as $t):?><option <?=($edit['tipo']??'')===$t?'selected':''?>><?=h($t)?></option><?php endforeach;?></select></label>
<label>Fecha inicio<input type="date" name="fecha_inicio" value="<?=h($edit['fecha_inicio']??'')?>"></label>
<label>Fecha vencimiento<input type="date" name="fecha_vencimiento" required value="<?=h($edit['fecha_vencimiento']??'')?>"></label>
<label class="full">Observaciones<textarea name="observaciones"><?=h($edit['observaciones']??'')?></textarea></label><div class="full"><button class="btn primary">Guardar</button><?php if($edit):?> <a class="btn secondary" href="<?=app_url('/php/rrhh.php')?>">Cancelar</a><?php endif;?></div></form></section><?php endif;?>

<div class="module-toolbar"><h2>Alertas de vencimiento</h2><span class="count-pill"><?=count($alertasRRHH)?> alerta(s)</span></div>
<section class="module-card"><div class="alert-list"><?php if(!$alertasRRHH):?><div class="indicator-empty">No hay vencimientos próximos o vencidos.</div><?php endif;?><?php foreach($alertasRRHH as $a):?><div class="alert-item"><div><strong><?=h($a['empleado_nombre'])?></strong><br><?=h($a['tipo'])?></div><div><strong><?=h($a['fecha_vencimiento'])?></strong><br><?=($a['fecha_vencimiento']<$hoy?'VENCIDO':'PRÓXIMO')?></div></div><?php endforeach;?></div></section>
<div class="module-toolbar"><h2>Calendario de vencimientos</h2><div><a class="btn secondary" href="?mes=<?=($mes===1?12:$mes-1)?>&anio=<?=($mes===1?$anio-1:$anio)?>">‹</a> <strong><?=h(date('m/Y',strtotime($primerDia)))?></strong> <a class="btn secondary" href="?mes=<?=($mes===12?1:$mes+1)?>&anio=<?=($mes===12?$anio+1:$anio)?>">›</a></div></div>
<section class="calendar-wrap"><div class="calendar-grid"><?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d):?><div class="cal-head"><?=$d?></div><?php endforeach;?><?php for($x=1;$x<$inicioSemana;$x++):?><div class="cal-day muted"></div><?php endfor;?><?php for($d=1;$d<=$diasMes;$d++):?><div class="cal-day"><span class="cal-num"><?=$d?></span><?php foreach($eventosPorDia[$d]??[] as $e):?><span class="cal-event <?=$e['fecha_vencimiento']<$hoy?'overdue':''?>" title="<?=h($e['empleado_nombre'].' - '.$e['tipo'])?>"><?=h($e['empleado_nombre'].' · '.$e['tipo'])?></span><?php endforeach;?></div><?php endfor;?></div></section>
<div class="module-toolbar" id="seguimiento"><h2>Seguimiento RRHH</h2><span class="count-pill"><?=count($rows)?> registro(s)</span></div>
<div class="table-wrapper"><table class="module-table"><thead><tr><th>Empleado</th><th>Tipo</th><th>Vencimiento</th><th>Observaciones</th><th>Última modificación</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><strong><?=h($r['empleado_nombre'])?></strong></td><td><?=h($r['tipo'])?></td><td><?=h($r['fecha_vencimiento'])?></td><td><?=h($r['observaciones']??'')?></td><td><?=h($r['actualizado_nombre']?:$r['creado_nombre']?:'Sistema')?><br><small><?=h($r['actualizado_en']??$r['creado_en']??'')?></small></td>
<?php if($canEdit):?><td class="module-actions"><a class="btn secondary" href="?editar=<?=(int)$r['id']?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar este registro?')"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn secondary">Eliminar</button></form></td><?php endif;?></tr><?php endforeach;?>
</tbody>
</table>
</div>

</main>
</div>
</body>
</html>

