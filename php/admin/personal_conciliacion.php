<?php
require __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/personal_maestro.php';
if (!puedeConciliarPersonal($pdo)) { http_response_code(403); exit('Solo el responsable de RRHH puede revisar estas diferencias.'); }
verify_csrf();
$error=''; $mensaje=$_SESSION['conciliacion_msg']??''; unset($_SESSION['conciliacion_msg']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (($_POST['accion']??'')!=='resolver') throw new RuntimeException('Acción no permitida.');
        resolverConciliacionPersonal($pdo,(int)($_POST['id']??0),(array)($_POST['decisiones']??[]));
        $_SESSION['conciliacion_msg']='Revisión resuelta. Se conservaron o adoptaron únicamente los valores que elegiste.';
        header('Location: '.app_url('/php/admin/personal_conciliacion.php'));exit;
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
$pagina=max(1,(int)($_GET['pagina']??1)); $offset=($pagina-1)*25;
$total=(int)$pdo->query('SELECT COUNT(*) FROM personal_conciliacion WHERE resuelto_en IS NULL')->fetchColumn();
$filas=$pdo->query('SELECT * FROM personal_conciliacion WHERE resuelto_en IS NULL ORDER BY id LIMIT 25 OFFSET '.$offset)->fetchAll();
$etiquetas=['apellido_nombre'=>'Apellido y nombre','dni'=>'DNI','email_corporativo'=>'Correo corporativo','telefono'=>'Teléfono',
    'licencia_conducir'=>'Vencimiento de licencia de conducir','manejo_defensivo'=>'Vencimiento de manejo defensivo'];
$camposFinanzas=['apellido_nombre'=>'nombre_completo','dni'=>'dni','email_corporativo'=>'email','telefono'=>'telefono',
    'licencia_conducir'=>'vencimiento_carnet','manejo_defensivo'=>'vencimiento_defensivo'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Revisión de fichas de Personal | NASER</title><link rel="stylesheet" href="<?=asset('/style.css')?>">
<style>.conciliacion-card{margin:18px 0;padding:20px;border:1px solid #dfe7e1;background:#fff;border-radius:14px}.conciliacion-table{width:100%;border-collapse:collapse}.conciliacion-table th,.conciliacion-table td{padding:12px;text-align:left;border-bottom:1px solid #e5ebe7;vertical-align:top}.conciliacion-table select{min-width:170px;max-width:100%}.conciliacion-scroll{overflow-x:auto}.conciliacion-value{white-space:pre-wrap;overflow-wrap:anywhere;max-width:280px}.conciliacion-warn{display:block;color:#9a5c00;font-size:12px;margin-top:6px}</style>
</head><body><div class="app"><?php sidebar($pdo,'personal');?><main class="content">
<header class="section-top"><div><p class="eyebrow">RRHH · FICHA CENTRAL</p><h1>Revisar diferencias de Personal</h1><p>La ficha central es la fuente que utilizan los sectores. Las diferencias encontradas al vincular los legajos se conservan acá para que puedas decidir cada cambio.</p></div><a class="btn secondary" href="<?=app_url('/php/personal.php')?>">Abrir Personal</a></header>
<?php if($mensaje):?><div class="alert success"><?=h($mensaje)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<p class="muted"><?=$total?> revisión(es) pendiente(s). Ningún valor de la ficha central se reemplaza sin una elección explícita.</p>
<?php if(!$filas):?><section class="table-panel"><p>No hay diferencias pendientes de revisión.</p></section><?php endif;?>
<?php foreach($filas as $p):
    $empleado=empleadoParaFinanzas($pdo,(int)$p['empleado_id']);
    $st=$pdo->prepare('SELECT * FROM finanzas_trabajadores WHERE id=?');$st->execute([$p['finanzas_id']]);$finanzas=$st->fetch();
    $diferencias=json_decode($p['diferencias_json'],true)?:[];
    $vinculoValido=$empleado && $finanzas && (int)$finanzas['empleado_id']===(int)$p['empleado_id'];
?>
<section class="conciliacion-card"><h2><?=h(($empleado['legajo']??'Sin legajo').' · '.($empleado['apellido_nombre']??'Ficha no disponible'))?></h2>
<?php if(!$vinculoValido):?><div class="alert error">La ficha o el seguimiento cambió de vinculación. Revisá el legajo en Personal antes de continuar.</div><?php else:?>
<form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?=(int)$p['id']?>">
<div class="conciliacion-scroll"><table class="conciliacion-table"><thead><tr><th>Dato</th><th>Ficha central actual</th><th>Valor registrado en Finanzas</th><th>Decisión</th></tr></thead><tbody>
<?php foreach($diferencias as $campo=>$valores):
    if(!isset($etiquetas[$campo]))continue;
    $actual=match($campo){'licencia_conducir'=>$empleado['vencimiento_carnet'],'manejo_defensivo'=>$empleado['vencimiento_defensivo'],default=>$empleado[$campo]??null};
    $legadoActual=$finanzas[$camposFinanzas[$campo]]??null;
    $vigente=(string)$actual===(string)($valores['maestro']??null) && (string)$legadoActual===(string)($valores['finanzas']??null);
?>
<tr><th><?=h($etiquetas[$campo])?></th><td><div class="conciliacion-value"><?=h($actual===null || $actual===''?'Sin dato':(string)$actual)?></div></td><td><div class="conciliacion-value"><?=h(($valores['finanzas']??null)===null || $valores['finanzas']===''?'Sin dato':(string)$valores['finanzas'])?></div><?php if(!$vigente):?><span class="conciliacion-warn">Hubo cambios posteriores. Podés conservar la ficha actual y, si hace falta, corregirla desde Personal.</span><?php endif;?></td><td><select name="decisiones[<?=h($campo)?>]" required><option value="mantener">Conservar ficha actual</option><?php if($vigente):?><option value="adoptar">Usar valor de Finanzas</option><?php endif;?></select></td></tr>
<?php endforeach;?>
</tbody></table></div><div class="table-actions" style="margin-top:16px"><button class="btn primary">Confirmar revisión</button><a class="btn secondary" href="<?=app_url('/php/personal.php?id='.(int)$p['empleado_id'])?>">Ver ficha central</a></div>
</form><?php endif;?></section>
<?php endforeach;?>
<div class="table-actions"><?php if($pagina>1):?><a class="btn secondary" href="<?=app_url('/php/admin/personal_conciliacion.php?pagina='.($pagina-1))?>">Anterior</a><?php endif;?><?php if($offset+25<$total):?><a class="btn secondary" href="<?=app_url('/php/admin/personal_conciliacion.php?pagina='.($pagina+1))?>">Siguiente</a><?php endif;?></div>
</main></div></body></html>
