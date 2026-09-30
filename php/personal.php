<?php
// PERSONAL: listado de empleados, legajo, habilitaciones con vencimiento e importacion del Excel
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require_once __DIR__ . '/config/empleados.php';
verify_csrf();

$verDatos = puedeVerPersonal($pdo);
if (!$verDatos && !puedeVerHabilitaciones($pdo)) { http_response_code(403); exit('No tenés acceso al listado de personal.'); }
$editar = puedeEditarPersonal($pdo);
$tipos = tiposHabilitacion();
$msg = $_SESSION['flash_msg'] ?? ''; $err = '';
unset($_SESSION['flash_msg']);

function volverPersonal(string $msg, string $qs = ''): never {
    $_SESSION['flash_msg'] = $msg;
    header('Location: ' . app_url('/php/personal.php') . $qs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$editar) throw new RuntimeException('Solo los responsables de RRHH pueden modificar el personal.');
        $a = $_POST['accion'] ?? '';
        if ($a === 'importar') {
            $f = $_FILES['archivo'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Elegí el archivo Excel del listado de empleados.');
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new RuntimeException('El archivo tiene que ser .xlsx');
            $r = importarEmpleadosXlsx($pdo, $f['tmp_name'], isset($_POST['marcar_bajas']));
            registrarActividad($pdo, 'importar_personal', "{$r['nuevos']} nuevos, {$r['actualizados']} actualizados");
            volverPersonal("Listado importado: {$r['nuevos']} empleado(s) nuevo(s), {$r['actualizados']} actualizado(s), {$r['habilitaciones']} habilitaciones con fecha" . ($r['bajas'] ? ", {$r['bajas']} pasado(s) a baja" : '') . '.');
        }
        if ($a === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $campos = ['legajo', 'apellido_nombre', 'dni', 'cuil', 'servicio', 'cargo', 'fecha_ingreso', 'fecha_nacimiento', 'convenio', 'encuadre', 'categoria', 'telefono', 'domicilio', 'localidad', 'provincia', 'email_corporativo', 'email_personal', 'observaciones'];
            $v = [];
            foreach ($campos as $c) { $x = trim((string)($_POST[$c] ?? '')); $v[$c] = $x === '' ? null : $x; }
            if (!$v['legajo'] || !$v['apellido_nombre']) throw new RuntimeException('Legajo y nombre son obligatorios.');
            foreach (['fecha_ingreso', 'fecha_nacimiento'] as $c) if ($v[$c] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v[$c])) $v[$c] = null;
            $sector = (int)($_POST['sector_id'] ?? 0) ?: null;
            if ($id) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", $campos));
                $pdo->prepare("UPDATE empleados SET $set, sector_id = ? WHERE id = ?")->execute([...array_values($v), $sector, $id]);
            } else {
                $pdo->prepare('INSERT INTO empleados (' . implode(',', $campos) . ', sector_id) VALUES (' . implode(',', array_fill(0, count($campos) + 1, '?')) . ')')->execute([...array_values($v), $sector]);
                $id = (int)$pdo->lastInsertId();
            }
            // Habilitaciones
            foreach ($tipos as $t => $label) {
                $venc = (string)($_POST['hab'][$t]['venc'] ?? '');
                $real = (string)($_POST['hab'][$t]['real'] ?? '');
                $det = trim((string)($_POST['hab'][$t]['det'] ?? ''));
                $okV = preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc); $okR = preg_match('/^\d{4}-\d{2}-\d{2}$/', $real);
                if (!$okV && !$okR) { $pdo->prepare('DELETE FROM empleados_habilitaciones WHERE empleado_id = ? AND tipo = ?')->execute([$id, $t]); continue; }
                $pdo->prepare('INSERT INTO empleados_habilitaciones (empleado_id, tipo, detalle, fecha_realizacion, fecha_vencimiento) VALUES (?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE detalle = VALUES(detalle), fecha_realizacion = VALUES(fecha_realizacion), fecha_vencimiento = VALUES(fecha_vencimiento)')
                    ->execute([$id, $t, $det ?: null, $okR ? $real : null, $okV ? $venc : null]);
            }
            registrarActividad($pdo, 'editar_empleado', "Legajo {$v['legajo']}");
            volverPersonal('Datos guardados.', '?id=' . $id);
        }
        if ($a === 'baja' || $a === 'alta') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE empleados SET activo = ?, fecha_baja = ? WHERE id = ?')->execute([$a === 'alta' ? 1 : 0, $a === 'alta' ? null : date('Y-m-d'), $id]);
            registrarActividad($pdo, 'empleado_' . $a, "Empleado $id");
            volverPersonal($a === 'baja' ? 'Empleado dado de baja.' : 'Empleado reactivado.', '?id=' . $id);
        }
    } catch (Throwable $e) {
        $err = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un empleado con ese legajo.' : $e->getMessage();
    }
}

$sectores = sectoresInfo($pdo);
$id = (int)($_GET['id'] ?? 0);
$nuevo = isset($_GET['nuevo']) && $editar;

// ---------- FICHA ----------
if ($id || $nuevo) {
    $emp = ['id' => 0, 'activo' => 1];
    $habs = [];
    if ($id) {
        $st = $pdo->prepare('SELECT e.*, u.nombre AS usuario_nombre FROM empleados e LEFT JOIN usuarios u ON u.id = e.usuario_id WHERE e.id = ?');
        $st->execute([$id]);
        $emp = $st->fetch();
        if (!$emp) { http_response_code(404); exit('Empleado no encontrado.'); }
        $st = $pdo->prepare('SELECT * FROM empleados_habilitaciones WHERE empleado_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll() as $hb) $habs[$hb['tipo']] = $hb;
    }
    $ro = $editar ? '' : 'disabled';
    $val = fn(string $c) => h((string)($emp[$c] ?? ''));
}
// ---------- LISTADO ----------
else {
    $q = trim((string)($_GET['q'] ?? ''));
    $filtro = (string)($_GET['filtro'] ?? 'activos');
    $servicio = (string)($_GET['servicio'] ?? '');
    $where = [$filtro === 'bajas' ? 'e.activo = 0' : 'e.activo = 1'];
    $params = [];
    if ($q !== '') { $where[] = '(e.apellido_nombre LIKE ? OR e.legajo = ? OR e.dni LIKE ? OR e.cargo LIKE ?)'; array_push($params, "%$q%", $q, "%$q%", "%$q%"); }
    if ($servicio !== '') { $where[] = 'e.servicio = ?'; $params[] = $servicio; }
    if (!$verDatos) { $where[] = 'e.sector_id IS NOT NULL'; } // responsables operativos: solo personal operativo
    $st = $pdo->prepare('SELECT e.* FROM empleados e WHERE ' . implode(' AND ', $where) . ' ORDER BY e.apellido_nombre');
    $st->execute($params);
    $emps = $st->fetchAll();
    $habPorEmp = [];
    if ($emps) {
        $ids = implode(',', array_map(fn($e) => (int)$e['id'], $emps));
        foreach ($pdo->query("SELECT * FROM empleados_habilitaciones WHERE empleado_id IN ($ids)")->fetchAll() as $hb) $habPorEmp[(int)$hb['empleado_id']][$hb['tipo']] = $hb;
    }
    if ($filtro === 'vencimientos') {
        $emps = array_values(array_filter($emps, function ($e) use ($habPorEmp) {
            foreach ($habPorEmp[(int)$e['id']] ?? [] as $hb) if (in_array(estadoVencimiento($hb['fecha_vencimiento'])[0], ['vencido', 'pronto'], true)) return true;
            return false;
        }));
    }
    $servicios = $pdo->query("SELECT DISTINCT servicio FROM empleados WHERE servicio IS NOT NULL AND activo = 1 ORDER BY servicio")->fetchAll(PDO::FETCH_COLUMN);
    $totActivos = (int)$pdo->query('SELECT COUNT(*) FROM empleados WHERE activo = 1')->fetchColumn();
    $vencidos = (int)$pdo->query('SELECT COUNT(*) FROM empleados_habilitaciones h JOIN empleados e ON e.id = h.empleado_id WHERE e.activo = 1 AND h.fecha_vencimiento < CURDATE()')->fetchColumn();
    $proximos = (int)$pdo->query('SELECT COUNT(*) FROM empleados_habilitaciones h JOIN empleados e ON e.id = h.empleado_id WHERE e.activo = 1 AND h.fecha_vencimiento BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY')->fetchColumn();
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Personal | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>"><link rel="stylesheet" href="<?=asset('/css/modules.css')?>">
</head><body><div class="app"><?php sidebar($pdo, 'personal'); ?><main class="content">

<?php if ($msg): ?><div class="alert success"><?=h($msg)?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?=h($err)?></div><?php endif; ?>

<?php if ($id || $nuevo): ?>
  <nav class="breadcrumbs"><a href="<?=h(app_url('/php/personal.php'))?>">Personal</a><span>›</span><span><?= $nuevo ? 'Nuevo empleado' : h($emp['apellido_nombre']) ?></span></nav>
  <header class="topbar"><div>
    <p class="eyebrow">LEGAJO <?= $nuevo ? 'NUEVO' : h($emp['legajo']) ?></p>
    <h1><?= $nuevo ? 'Nuevo empleado' : h($emp['apellido_nombre']) ?> <?php if (!$nuevo && !(int)$emp['activo']): ?><span class="state-off">Baja <?=h($emp['fecha_baja'] ? date('d/m/Y', strtotime($emp['fecha_baja'])) : '')?></span><?php endif; ?></h1>
    <?php if (!$nuevo): ?><p><?=h($emp['cargo'] ?? '')?><?= $emp['servicio'] ? ' · ' . h($emp['servicio']) : '' ?><?= $emp['fecha_ingreso'] ? ' · Ingreso ' . h(date('d/m/Y', strtotime($emp['fecha_ingreso']))) : '' ?><?= $emp['usuario_nombre'] ? ' · Usuario del sistema: ' . h($emp['usuario_nombre']) : '' ?></p><?php endif; ?>
  </div>
  <?php if ($editar && !$nuevo): ?><div class="top-actions">
    <form method="post" onsubmit="return confirm('¿Confirmás?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$emp['id']?>"><input type="hidden" name="accion" value="<?= (int)$emp['activo'] ? 'baja' : 'alta' ?>"><button class="btn <?= (int)$emp['activo'] ? 'danger' : 'secondary' ?>"><?= (int)$emp['activo'] ? 'Dar de baja' : 'Reactivar' ?></button></form>
  </div><?php endif; ?></header>

  <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?=(int)$emp['id']?>">
  <?php if ($verDatos): ?>
  <section class="form-panel">
    <h2>Datos del legajo</h2>
    <div class="module-form">
      <label>Legajo<input name="legajo" value="<?=$val('legajo')?>" required <?=$ro?>></label>
      <label>Apellido y nombre<input name="apellido_nombre" value="<?=$val('apellido_nombre')?>" required <?=$ro?>></label>
      <label>DNI<input name="dni" value="<?=$val('dni')?>" <?=$ro?>></label>
      <label>CUIL<input name="cuil" value="<?=$val('cuil')?>" <?=$ro?>></label>
      <label>Servicio<input name="servicio" value="<?=$val('servicio')?>" list="lista-servicios" <?=$ro?>></label>
      <label>Sector del sistema<select name="sector_id" <?=$ro?>><option value="">—</option><?php foreach ($sectores as $s): ?><option value="<?=(int)$s['id']?>" <?= (int)($emp['sector_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?=h($s['nombre'])?></option><?php endforeach; ?></select></label>
      <label>Cargo / función<input name="cargo" value="<?=$val('cargo')?>" <?=$ro?>></label>
      <label>Fecha de ingreso<input type="date" name="fecha_ingreso" value="<?=$val('fecha_ingreso')?>" <?=$ro?>></label>
      <label>Fecha de nacimiento <?php if (!empty($emp['fecha_nacimiento'])): ?><small class="muted">(<?=edadDesde($emp['fecha_nacimiento'])?> años)</small><?php endif; ?><input type="date" name="fecha_nacimiento" value="<?=$val('fecha_nacimiento')?>" <?=$ro?>></label>
      <label>Convenio<input name="convenio" value="<?=$val('convenio')?>" <?=$ro?>></label>
      <label>Encuadre<input name="encuadre" value="<?=$val('encuadre')?>" <?=$ro?>></label>
      <label>Categoría<input name="categoria" value="<?=$val('categoria')?>" <?=$ro?>></label>
      <label>Teléfono<input name="telefono" value="<?=$val('telefono')?>" <?=$ro?>></label>
      <label>Mail corporativo<input type="email" name="email_corporativo" value="<?=$val('email_corporativo')?>" <?=$ro?>></label>
      <label>Mail personal<input type="email" name="email_personal" value="<?=$val('email_personal')?>" <?=$ro?>></label>
      <label>Domicilio<input name="domicilio" value="<?=$val('domicilio')?>" <?=$ro?>></label>
      <label>Localidad<input name="localidad" value="<?=$val('localidad')?>" <?=$ro?>></label>
      <label>Provincia<input name="provincia" value="<?=$val('provincia')?>" <?=$ro?>></label>
      <label class="full">Observaciones<textarea name="observaciones" <?=$ro?>><?=$val('observaciones')?></textarea></label>
    </div>
  </section>
  <?php else: ?>
    <?php foreach (['legajo', 'apellido_nombre'] as $c): ?><input type="hidden" name="<?=$c?>" value="<?=$val($c)?>"><?php endforeach; ?>
  <?php endif; ?>

  <section class="form-panel">
    <h2>Licencias, cursos y exámenes</h2>
    <p class="muted">Los vencimientos se avisan solos a los responsables de RRHH (15 días antes, 3 días antes y el día que vence).</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Habilitación</th><th>Detalle / categoría</th><th>Realizado</th><th>Vence</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($tipos as $t => $label): $hb = $habs[$t] ?? []; [$cls, $txt] = estadoVencimiento($hb['fecha_vencimiento'] ?? null); ?>
        <tr><td><strong><?=h($label)?></strong></td>
          <td><input name="hab[<?=$t?>][det]" value="<?=h($hb['detalle'] ?? '')?>" <?=$ro?>></td>
          <td><input type="date" name="hab[<?=$t?>][real]" value="<?=h($hb['fecha_realizacion'] ?? '')?>" <?=$ro?>></td>
          <td><input type="date" name="hab[<?=$t?>][venc]" value="<?=h($hb['fecha_vencimiento'] ?? '')?>" <?=$ro?>></td>
          <td><?php if ($hb): ?><span class="dias-pill <?= $cls === 'ok' ? 'ok' : ($cls === 'pronto' ? 'pronto' : 'vencido') ?>"><?=h($txt)?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </section>
  <?php if ($editar): ?><div><button class="btn primary">Guardar</button> <a class="btn secondary" href="<?=h(app_url('/php/personal.php'))?>">Volver</a></div><?php endif; ?>
  </form>
  <datalist id="lista-servicios"><option>ADMINISTRACION</option><option>OPERADOR</option><option>SUPERVISOR</option><option>SLICK LINE</option><option>SALON</option></datalist>

<?php else: ?>
  <header class="topbar"><div>
    <p class="eyebrow">RECURSOS HUMANOS</p><h1>Personal</h1>
    <p><?= $verDatos ? 'Legajos, licencias, cursos y exámenes médicos del personal.' : 'Habilitaciones y vencimientos del personal operativo.' ?></p>
  </div>
  <?php if ($editar): ?><div class="top-actions"><a class="btn primary" href="?nuevo=1">+ Nuevo empleado</a></div><?php endif; ?></header>

  <section class="module-grid">
    <div class="module-card"><h3>Personal activo</h3><div class="big"><?=$totActivos?></div></div>
    <a class="module-card" style="text-decoration:none;color:inherit" href="?filtro=vencimientos"><h3>Vencen en 30 días</h3><div class="big" style="color:#b45309"><?=$proximos?></div></a>
    <a class="module-card" style="text-decoration:none;color:inherit" href="?filtro=vencimientos"><h3>Habilitaciones vencidas</h3><div class="big" style="color:#b42318"><?=$vencidos?></div></a>
  </section>

  <?php if ($editar): ?>
  <details class="form-panel" style="margin-bottom:18px" <?= $totActivos ? '' : 'open' ?>>
    <summary style="cursor:pointer;font-weight:800">⬆ Importar / actualizar desde el Excel "Listado de Empleados"</summary>
    <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:12px" onsubmit="this.querySelector('button').textContent='Importando…'"><?=csrf_field()?><input type="hidden" name="accion" value="importar">
      <p class="muted" style="margin:0">Se lee la hoja <strong>PERSONAL</strong> (datos, licencias, cursos y exámenes) y la hoja <strong>MAILS</strong>. Se actualiza por número de legajo; no se borra nada.</p>
      <input type="file" name="archivo" accept=".xlsx" required>
      <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="marcar_bajas" style="width:auto"> Pasar a "baja" a quienes ya no figuran en el Excel</label>
      <button class="btn primary">Importar</button>
    </form>
  </details>
  <?php endif; ?>

  <form class="search-bar" method="get">
    <label>Buscar<input name="q" value="<?=h($q)?>" placeholder="Nombre, legajo, DNI o cargo"></label>
    <label>Servicio<select name="servicio"><option value="">Todos</option><?php foreach ($servicios as $s): ?><option <?= $servicio === $s ? 'selected' : '' ?>><?=h($s)?></option><?php endforeach; ?></select></label>
    <label>Ver<select name="filtro"><option value="activos">Activos</option><option value="vencimientos" <?= $filtro === 'vencimientos' ? 'selected' : '' ?>>Con vencimientos</option><option value="bajas" <?= $filtro === 'bajas' ? 'selected' : '' ?>>Bajas</option></select></label>
    <button class="btn primary">Filtrar</button>
  </form>

  <div class="table-wrapper"><table class="module-table">
    <thead><tr><th>Legajo</th><th>Apellido y nombre</th><th>Cargo</th><th>Servicio</th><?php if ($verDatos): ?><th>Teléfono</th><?php endif; ?><th>Habilitaciones</th><th></th></tr></thead>
    <tbody>
    <?php if (!$emps): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:22px"><?= $totActivos ? 'No hay empleados con estos filtros.' : 'Todavía no se cargó el personal. Usá "Importar desde el Excel".' ?></td></tr><?php endif; ?>
    <?php foreach ($emps as $e): $hbs = $habPorEmp[(int)$e['id']] ?? []; $nv = 0; $np = 0;
      foreach ($hbs as $hb) { $s = estadoVencimiento($hb['fecha_vencimiento'])[0]; if ($s === 'vencido') $nv++; elseif ($s === 'pronto') $np++; } ?>
      <tr>
        <td><strong><?=h($e['legajo'])?></strong></td>
        <td><?=h($e['apellido_nombre'])?><?php if ($e['email_corporativo']): ?><small><?=h($e['email_corporativo'])?></small><?php endif; ?></td>
        <td><?=h($e['cargo'] ?? '—')?></td>
        <td><?=h($e['servicio'] ?? '—')?></td>
        <?php if ($verDatos): ?><td><?=h($e['telefono'] ?? '')?></td><?php endif; ?>
        <td><?php if ($nv): ?><span class="dias-pill vencido"><?=$nv?> vencida(s)</span> <?php endif; ?><?php if ($np): ?><span class="dias-pill pronto"><?=$np?> por vencer</span> <?php endif; ?><?php if (!$nv && !$np): ?><span class="muted"><?= $hbs ? count($hbs) . ' al día' : '—' ?></span><?php endif; ?></td>
        <td><a class="btn secondary small" href="?id=<?=(int)$e['id']?>">Ver legajo</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
</main></div></body></html>
