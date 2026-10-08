<?php
require __DIR__.'/config/auth.php'; requireLogin();
require __DIR__.'/config/db.php'; require __DIR__.'/config/layout.php';
$sectores = sectoresVisibles($pdo);
$editables = sectoresEditables($pdo);
$campos = ['fecha'=>'Fecha', 'equipo'=>'Equipo', 'operador'=>'Operador', 'pozo'=>'Pozo', 'tareas'=>'Tareas', 'servicios_adicionales'=>'Servicios adicionales', 'ecp'=>'ECP', 'equipo_izaje'=>'Equipo de izaje', 'estado_operacion'=>'Estado de operación', 'herramientas_pesca'=>'Herramientas en pesca', 'observaciones'=>'Observaciones'];
$estados = ['Pendiente', 'En curso', 'Finalizada', 'Suspendida'];
$registro = array_fill_keys(array_keys($campos), '');
$registro += ['id'=>0, 'sector_id'=>$editables[0]['id'] ?? 0, 'nombre'=>'', 'descripcion'=>''];
$error = '';
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM operaciones WHERE id = ?');
    $st->execute([(int)$_GET['editar']]);
    $encontrado = $st->fetch();
    if (!$encontrado || !puedeEditarSector($pdo, (int)$encontrado['sector_id'])) { http_response_code(403); exit('No tenés permisos para editar esta operación.'); }
    $registro = $encontrado;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $anterior = null;
    if ($id) {
        $st = $pdo->prepare('SELECT * FROM operaciones WHERE id = ?'); $st->execute([$id]); $anterior = $st->fetch();
        if (!$anterior || !puedeEditarSector($pdo, (int)$anterior['sector_id'])) { http_response_code(403); exit('No tenés permisos para gestionar esta operación.'); }
    }
    if ($accion === 'eliminar' && $anterior) {
        $pdo->prepare('DELETE FROM operaciones WHERE id = ?')->execute([$id]);
        registrarActividad($pdo, 'Eliminar operación', $anterior['nombre']);
        header('Location: '.app_url('/php/operaciones.php').'?ok=eliminada'); exit;
    }
    if ($accion !== 'guardar') { http_response_code(400); exit('Acción inválida.'); }
    $registro['id'] = $id;
    $registro['sector_id'] = (int)($_POST['sector_id'] ?? 0);
    if (!puedeEditarSector($pdo, $registro['sector_id']) || !isset(sectoresInfo($pdo)[$registro['sector_id']])) { http_response_code(403); exit('No tenés permisos para gestionar este sector.'); }
    foreach ($campos as $campo=>$titulo) {
        $valor = $_POST[$campo] ?? '';
        $registro[$campo] = is_string($valor) ? trim($valor) : '';
        if (mb_strlen($registro[$campo]) > 4000) $error = 'Los campos no pueden superar los 4000 caracteres.';
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $registro['fecha']);
    if (!$fecha || $fecha->format('Y-m-d') !== $registro['fecha']) $error = 'Ingresá una fecha válida.';
    if (!$registro['equipo'] || !$registro['operador'] || !$registro['pozo'] || !$registro['tareas']) $error = 'Completá fecha, equipo, operador, pozo y tareas.';
    if (!in_array($registro['estado_operacion'], $estados, true)) $error = 'Seleccioná un estado válido.';
    if (!$error) {
        $columnas = array_keys($campos);
        $valores = array_map(fn($c)=>$registro[$c], $columnas);
        $nombre = mb_substr($registro['equipo'].' · '.$registro['pozo'], 0, 160);
        $estado = in_array($registro['estado_operacion'], ['Finalizada','Suspendida'], true) ? 'inactiva' : 'activa';
        if ($id) {
            $sql = 'UPDATE operaciones SET sector_id=?, nombre=?, descripcion=?, estado=?, '.implode(', ', array_map(fn($c)=>"$c=?", $columnas)).' WHERE id=?';
            $pdo->prepare($sql)->execute(array_merge([$registro['sector_id'], $nombre, $registro['tareas'], $estado], $valores, [$id]));
        } else {
            $sql = 'INSERT INTO operaciones (sector_id,nombre,descripcion,estado,'.implode(',', $columnas).') VALUES ('.implode(',', array_fill(0, 4+count($columnas), '?')).')';
            $pdo->prepare($sql)->execute(array_merge([$registro['sector_id'], $nombre, $registro['tareas'], $estado], $valores));
        }
        registrarActividad($pdo, $id ? 'Editar operación' : 'Agregar operación', $nombre);
        header('Location: '.app_url('/php/operaciones.php').'?ok=guardada'); exit;
    }
}
$ids = array_map(fn($s)=>(int)$s['id'], $sectores);
$ops = [];
if ($ids) {
    $st = $pdo->prepare('SELECT o.*,s.nombre sector FROM operaciones o JOIN sectores s ON s.id=o.sector_id WHERE o.sector_id IN ('.implode(',', array_fill(0,count($ids),'?')).') ORDER BY o.fecha DESC,o.id DESC');
    $st->execute($ids); $ops = $st->fetchAll();
}
$mostrarFormulario = isset($_GET['nuevo']) || isset($_GET['editar']) || $error;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Listado de operaciones | NASER</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head><body><div class="app"><?php sidebar($pdo,'operaciones'); ?>
<main class="content operations-page"><header class="section-top"><div><p class="eyebrow">GESTIÓN OPERATIVA</p><h1>Listado de operaciones</h1><p>Registrá y consultá las actividades de campo.</p></div><?php if ($editables): ?><a class="btn primary" href="<?=app_url('/php/operaciones.php')?>?nuevo=1#operation-form">+ Agregar operación</a><?php endif; ?></header>
<?php if (in_array($_GET['ok'] ?? '', ['guardada','eliminada'], true)): ?><div class="alert success" role="status">Operación <?=($_GET['ok']==='eliminada'?'eliminada':'guardada')?> correctamente.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error" role="alert"><?=h($error)?></div><?php endif; ?>
<?php if ($mostrarFormulario && $editables): ?><section class="operations-editor" id="operation-form"><h2><?=$registro['id']?'Editar operación':'Nueva operación'?></h2><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?=(int)$registro['id']?>"><div class="operations-fields"><label>Sector<select name="sector_id" required><?php foreach ($editables as $s): ?><option value="<?=(int)$s['id']?>" <?=(int)$registro['sector_id']===(int)$s['id']?'selected':''?>><?=h($s['nombre'])?></option><?php endforeach; ?></select></label>
<?php foreach ($campos as $campo=>$titulo): $requerido=in_array($campo,['fecha','equipo','operador','pozo','tareas','estado_operacion'],true); ?><label class="<?=in_array($campo,['tareas','observaciones','servicios_adicionales'],true)?'operations-wide':''?>"><?=h($titulo)?><?=$requerido?' *':''?>
<?php if ($campo==='estado_operacion'): ?><select name="<?=$campo?>" required><option value="">Seleccionar estado</option><?php foreach ($estados as $estado): ?><option <?=$registro[$campo]===$estado?'selected':''?>><?=h($estado)?></option><?php endforeach; ?></select>
<?php elseif (in_array($campo,['tareas','observaciones','servicios_adicionales'],true)): ?><textarea name="<?=$campo?>" rows="3" maxlength="4000" <?=$requerido?'required':''?>><?=h($registro[$campo] ?? '')?></textarea>
<?php else: ?><input type="<?=$campo==='fecha'?'date':'text'?>" name="<?=$campo?>" value="<?=h($registro[$campo] ?? '')?>" maxlength="4000" <?=$requerido?'required':''?>><?php endif; ?></label><?php endforeach; ?></div><div class="operations-actions"><button class="btn primary" type="submit">Guardar operación</button><a class="btn secondary" href="<?=app_url('/php/operaciones.php')?>">Cancelar</a></div></form></section><?php endif; ?>
<section class="operations-sheet"><div class="operations-sheet-head"><img src="<?=app_url('/img/logo-naser.png')?>" alt="Naser División Petróleo"><div><h2>Listado de operaciones</h2><span><?=count($ops)?> registros · Sectores autorizados</span></div><div class="operations-reference"><strong>POSN08-F2</strong><span>Rev.00 Sep. 25</span></div></div><div class="operations-scroll" role="region" aria-label="Listado de operaciones" tabindex="0"><table class="operations-table"><thead><tr><?php foreach($campos as $titulo): ?><th scope="col"><?=h($titulo)?></th><?php endforeach; ?><th scope="col">Acciones</th></tr></thead><tbody>
<?php if (!$ops): ?><tr><td colspan="12" class="operations-empty">Todavía no hay operaciones cargadas. Agregá una operación para comenzar.</td></tr><?php endif; ?>
<?php foreach ($ops as $o): ?><tr><?php foreach ($campos as $campo=>$titulo): $valor=$o[$campo] ?? ''; if ($campo==='tareas' && !$valor) $valor=$o['descripcion'] ?: $o['nombre']; if ($campo==='estado_operacion' && !$valor) $valor=$o['estado']==='activa'?'En curso':'Finalizada'; ?><td><?php if ($campo==='fecha' && $valor): ?><?=h(date('d/m/Y',strtotime($valor)))?><?php elseif ($campo==='estado_operacion'): ?><span class="operations-state"><?=h($valor)?></span><?php else: ?><?=nl2br(h($valor ?: '—'))?><?php endif; ?></td><?php endforeach; ?><td><small class="muted"><?=h($o['sector'])?></small><?php if(puedeEditarSector($pdo,(int)$o['sector_id'])): ?><div class="operations-row-actions"><a class="btn secondary" aria-label="Editar <?=h($o['nombre'])?>" href="<?=app_url('/php/operaciones.php')?>?editar=<?=(int)$o['id']?>#operation-form">Editar</a><form method="post" class="operation-delete"><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><button class="btn danger" aria-label="Eliminar <?=h($o['nombre'])?>">Eliminar</button></form></div><?php else: ?><small>Solo lectura</small><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section></main></div><script>document.querySelectorAll('.operation-delete').forEach(form=>form.addEventListener('submit',event=>{if(!confirm('¿Eliminar esta operación? Esta acción no se puede deshacer.'))event.preventDefault();}));</script></body></html>
