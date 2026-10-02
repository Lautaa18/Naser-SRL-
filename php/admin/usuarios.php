<?php
// Usuarios y permisos (solo super usuario)
require __DIR__ . '/../config/auth.php';
requireAdmin();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/estructura_naser.php';
verify_csrf();

$yoId = (int)$_SESSION['usuario_id'];
$msg = $_SESSION['flash_msg'] ?? ''; $err = '';
$credenciales = $_SESSION['flash_credenciales'] ?? [];
unset($_SESSION['flash_msg'], $_SESSION['flash_credenciales']);
$sectores = array_values(sectoresInfo($pdo));
$ROLES = ['' => '— Sin acceso —', 'observador' => 'Observador (ve)', 'operador' => 'Operador (ve y completa)', 'responsable' => 'Responsable (edita y aprueba)'];

function volver(string $msg, array $cred = [], string $ancla = ''): never {
    $_SESSION['flash_msg'] = $msg;
    if ($cred) $_SESSION['flash_credenciales'] = $cred;
    header('Location: ' . app_url('/php/admin/usuarios.php') . ($ancla ? '#' . $ancla : ''));
    exit;
}
function guardarRoles(PDO $pdo, int $uid, array $roles): void {
    foreach (sectoresInfo($pdo) as $s) {
        $r = (string)($roles[(int)$s['id']] ?? '');
        asignarRolSector($pdo, $uid, (int)$s['id'], $r === '' ? null : $r);
    }
}
function adminsActivos(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin' AND activo=1")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $a = $_POST['accion'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($a === 'aplicar_estructura') {
            $inf = aplicarEstructuraNaser($pdo, isset($_POST['enviar_mails']), isset($_POST['desactivar_prueba']), isset($_POST['operadores_personal']));
            registrarActividad($pdo, 'aplicar_estructura', count($inf['creados']) . ' creados, ' . $inf['actualizados'] . ' actualizados');
            $txt = 'Estructura aplicada: ' . count($inf['creados']) . ' usuario(s) creado(s) y ' . $inf['actualizados'] . ' actualizado(s).';
            if ($inf['avisos']) $txt .= ' ' . implode(' ', $inf['avisos']);
            $txt .= $inf['cambios']
                ? ' Cambios de permisos: ' . implode(' | ', $inf['cambios']) . '.'
                : ' Los permisos ya estaban como indica la estructura (no hubo cambios).';
            volver($txt, $inf['creados']);
        }

        if ($a === 'crear' || $a === 'editar') {
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $super = ($_POST['super'] ?? '') === '1';
            $pass = (string)($_POST['password'] ?? '');
            if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Completá el nombre y un correo válido.');
            if ($pass !== '' && strlen($pass) < 8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            $cred = [];
            if ($a === 'crear') {
                $temporal = $pass === '';
                if ($temporal) $pass = passwordTemporal();
                $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol, activo, debe_cambiar_password) VALUES (?,?,?,?,1,1)')
                    ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT), $super ? 'admin' : 'usuario']);
                $id = (int)$pdo->lastInsertId();
                if ($temporal) $cred[] = ['nombre' => $nombre, 'email' => $email, 'password' => $pass];
                registrarActividad($pdo, 'crear_usuario', $email);
            } else {
                if ($id === $yoId && !$super) throw new RuntimeException('No podés quitarte a vos mismo el rol de super usuario.');
                $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?')->execute([$nombre, $email, $super ? 'admin' : 'usuario', $id]);
                if ($pass !== '') $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 1 WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                registrarActividad($pdo, 'editar_usuario', $email);
            }
            guardarRoles($pdo, $id, (array)($_POST['roles'] ?? []));
            volver($a === 'crear' ? "Usuario $nombre creado." : "Usuario $nombre actualizado.", $cred, 'u' . $id);
        }

        if ($a === 'reset_password') {
            $st = $pdo->prepare('SELECT nombre, email FROM usuarios WHERE id = ?'); $st->execute([$id]); $u = $st->fetch();
            if (!$u) throw new RuntimeException('Usuario inexistente.');
            $pass = passwordTemporal();
            $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 1 WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            $pdo->prepare('DELETE FROM login_intentos WHERE email = ?')->execute([strtolower($u['email'])]);
            registrarActividad($pdo, 'reset_password', $u['email']);
            $c = ['nombre' => $u['nombre'], 'email' => $u['email'], 'password' => $pass];
            if (isset($_POST['enviar_mail'])) {
                require_once __DIR__ . '/../config/mailer.php';
                $c['mail'] = enviar_mail($u['email'], 'Nueva contraseña de NASER SGI', mail_plantilla('Nueva contraseña temporal', '<p>Tu contraseña temporal es: <strong>' . htmlspecialchars($pass) . '</strong></p><p>Al ingresar te va a pedir que la cambies.</p>', app_absolute_url('/php/login.php'), 'Ingresar'));
            }
            volver('Se generó una contraseña temporal para ' . $u['nombre'] . '.', [$c], 'u' . $id);
        }

        if ($a === 'estado') {
            if ($id === $yoId) throw new RuntimeException('No podés desactivar tu propia cuenta.');
            $st = $pdo->prepare('SELECT rol, activo FROM usuarios WHERE id = ?'); $st->execute([$id]); $u = $st->fetch();
            if ($u && $u['rol'] === 'admin' && (int)$u['activo'] === 1 && adminsActivos($pdo) <= 1) throw new RuntimeException('Tiene que quedar al menos un super usuario activo.');
            $pdo->prepare('UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = ?')->execute([$id]);
            registrarActividad($pdo, 'cambiar_estado_usuario', "Usuario $id");
            volver('Estado actualizado.', [], 'u' . $id);
        }

        if ($a === 'eliminar') {
            if ($id === $yoId) throw new RuntimeException('No podés eliminar tu propia cuenta.');
            $st = $pdo->prepare('SELECT nombre, email, rol, activo FROM usuarios WHERE id = ?'); $st->execute([$id]); $u = $st->fetch();
            if (!$u) throw new RuntimeException('Usuario inexistente.');
            if ($u['rol'] === 'admin' && (int)$u['activo'] === 1 && adminsActivos($pdo) <= 1) throw new RuntimeException('No se puede eliminar el último super usuario.');
            $pdo->prepare('DELETE FROM notificaciones WHERE usuario_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            registrarActividad($pdo, 'eliminar_usuario', $u['email']);
            volver('Usuario ' . $u['nombre'] . ' eliminado. Los formularios que cargó se conservan.');
        }

        if ($a === 'sectores') {
            foreach ((array)($_POST['burbuja'] ?? []) as $sid => $bid) {
                $pdo->prepare('UPDATE sectores SET burbuja_id = ?, restringido = ? WHERE id = ?')
                    ->execute([(int)$bid ?: null, isset($_POST['restringido'][$sid]) ? 1 : 0, (int)$sid]);
            }
            foreach ((array)($_POST['burbuja_nombre'] ?? []) as $bid => $nom) {
                $nom = trim((string)$nom);
                if ($nom === '') continue;
                $pdo->prepare('UPDATE burbujas SET nombre = ?, colaborativa = ? WHERE id = ?')->execute([$nom, isset($_POST['burbuja_colab'][$bid]) ? 1 : 0, (int)$bid]);
            }
            if (($nueva = trim((string)($_POST['nueva_burbuja'] ?? ''))) !== '') {
                $pdo->prepare('INSERT INTO burbujas (nombre, colaborativa) VALUES (?, ?)')->execute([$nueva, isset($_POST['nueva_colab']) ? 1 : 0]);
            }
            registrarActividad($pdo, 'config_sectores', 'Burbujas y sectores restringidos');
            volver('Sectores y burbujas actualizados.', [], 'sectores');
        }
    } catch (Throwable $e) {
        $err = str_contains($e->getMessage(), 'Duplicate') ? 'Ese correo ya está registrado.' : $e->getMessage();
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY activo DESC, FIELD(rol,'admin') DESC, nombre")->fetchAll();
$rolesPorUsuario = [];
foreach ($pdo->query('SELECT usuario_id, sector_id, rol_sector FROM usuario_sector')->fetchAll() as $r) $rolesPorUsuario[(int)$r['usuario_id']][(int)$r['sector_id']] = $r['rol_sector'];
$burbujas = $pdo->query('SELECT * FROM burbujas ORDER BY nombre')->fetchAll();
$est = estructuraNaser();

function matrizRoles(array $sectores, array $roles, array $ROLES): void { ?>
  <table class="perm-matrix"><thead><tr><th>Sector</th><th>Rol</th></tr></thead><tbody>
  <?php foreach ($sectores as $s): $sid = (int)$s['id']; $r = $roles[$sid] ?? ''; ?>
    <tr><td><?=h($s['nombre'])?><?= (int)$s['restringido'] ? ' <small class="muted">🔒 restringido</small>' : '' ?></td>
    <td><select name="roles[<?=$sid?>]"><?php foreach ($ROLES as $k => $v): ?><option value="<?=h($k)?>" <?= $r === $k ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select></td></tr>
  <?php endforeach; ?>
  </tbody></table>
<?php }
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios y permisos | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head>
<body><div class="app"><?php sidebar($pdo, 'usuarios'); ?><main class="content">
<header class="topbar"><div><p class="eyebrow">SUPER USUARIO</p><h1>Usuarios y permisos</h1><p>Cada sector tiene responsables (editan y aprueban), operadores (ven y completan formularios) y observadores (solo ven).</p></div></header>

<?php if ($msg): ?><div class="alert success"><?=h($msg)?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?=h($err)?></div><?php endif; ?>
<?php if ($credenciales): ?>
<div class="cred-box">
  <strong>⚠ Contraseñas temporales — copialas ahora, no se vuelven a mostrar.</strong>
  <p class="muted" style="margin:6px 0 10px">Cada persona tiene que cambiarla la primera vez que ingresa.</p>
  <div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Usuario (correo)</th><th>Contraseña temporal</th><th>Mail</th></tr></thead><tbody>
  <?php foreach ($credenciales as $c): ?><tr><td><?=h($c['nombre'])?></td><td><?=h($c['email'])?></td><td><code><?=h($c['password'])?></code></td><td><?= isset($c['mail']) ? ($c['mail'] ? 'Enviado' : 'No se pudo enviar') : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<section class="form-panel" style="margin-bottom:18px">
  <h2>Estructura de NASER</h2>
  <p class="muted">Crea los usuarios con su mail corporativo (<code>nombre.apellido@<?=h(NASER_DOMINIO_MAIL)?></code>) y asigna responsables, observadores, burbujas y sectores restringidos según <code>php/config/estructura_naser.php</code>. Se puede aplicar varias veces.</p>
  <details><summary style="cursor:pointer;font-weight:800;margin:8px 0">Ver qué se va a aplicar</summary>
    <div class="table-wrap"><table><thead><tr><th>Sector</th><th>Responsables</th><th>Observadores</th></tr></thead><tbody>
    <?php foreach ($sectores as $s): $sl = $s['slug']; $p = $est['personas']; ?>
      <tr><td><?=h($s['nombre'])?><?= in_array($sl, $est['restringidos'], true) ? ' 🔒' : '' ?></td>
      <td><?=h(implode(', ', array_map(fn($k) => $p[$k][0] ?? $k, $est['responsables'][$sl] ?? [])))?></td>
      <td><?=h(implode(', ', array_map(fn($k) => $p[$k][0] ?? $k, $est['observadores'][$sl] ?? [])))?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="muted">Super usuario: <?=h(implode(', ', array_map(fn($x) => $x[0], array_filter($est['personas'], fn($x) => $x[1]))))?>. 🔒 = solo lo ven sus integrantes.</p>
  </details>
  <form method="post" style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-top:10px" onsubmit="return confirm('¿Aplicar la estructura de NASER? Se crean los usuarios que falten y se actualizan sus permisos.')"><?=csrf_field()?>
    <input type="hidden" name="accion" value="aplicar_estructura">
    <label style="display:flex;gap:6px;align-items:center;font-weight:600"><input type="checkbox" name="enviar_mails" style="width:auto"> Enviar a cada uno su usuario y contraseña por mail</label>
    <label style="display:flex;gap:6px;align-items:center;font-weight:600"><input type="checkbox" name="desactivar_prueba" style="width:auto"> Desactivar cuentas de prueba (@naser.test)</label>
    <label style="display:flex;gap:6px;align-items:center;font-weight:600" title="Crea un usuario operador para cada empleado activo de Operaciones/Mantenimiento/HSEQ con mail @gruponaser.com.ar (cargados en Personal)"><input type="checkbox" name="operadores_personal" style="width:auto"> Crear operadores desde el Personal (<?= (int)$pdo->query("SELECT COUNT(*) FROM empleados WHERE activo=1 AND sector_id IS NOT NULL AND email_corporativo LIKE '%@gruponaser.com.ar'")->fetchColumn() ?> con mail corporativo)</label>
    <button class="btn primary">Aplicar estructura NASER</button>
  </form>
</section>

<div class="admin-layout">
  <section class="form-panel">
    <h2>Nuevo usuario</h2>
    <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="crear">
      <label>Nombre y apellido<input name="nombre" required></label>
      <label>Correo<input type="email" name="email" required placeholder="nombre.apellido@<?=h(NASER_DOMINIO_MAIL)?>"></label>
      <label>Contraseña <small class="muted">(vacío = se genera una temporal)</small><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
      <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="super" value="1" style="width:auto"> Super usuario (ve y edita todo, administra usuarios)</label>
      <?php matrizRoles($sectores, [], $ROLES); ?>
      <button class="btn primary">Crear usuario</button>
    </form>
  </section>

  <section>
    <div class="search-bar"><input id="buscarUsuario" placeholder="Buscar por nombre o correo…"></div>
    <div class="user-list" id="listaUsuarios">
    <?php foreach ($usuarios as $u): $uid = (int)$u['id']; $roles = $rolesPorUsuario[$uid] ?? []; ?>
      <details class="user-item" id="u<?=$uid?>" data-buscar="<?=h(mb_strtolower($u['nombre'] . ' ' . $u['email']))?>">
        <summary>
          <div><?=h($u['nombre'])?> <?= (int)$u['activo'] ? '' : '<span class="state-off">Inactivo</span>' ?>
            <small><?=h($u['email'])?><?= $u['ultimo_acceso'] ? ' · último acceso ' . h(date('d/m/Y', strtotime($u['ultimo_acceso']))) : ' · nunca ingresó' ?></small>
            <div><?php if ($u['rol'] === 'admin'): ?><span class="tag admin">SUPER USUARIO</span><?php endif; ?>
            <?php foreach ($sectores as $s): $r = $roles[(int)$s['id']] ?? null; if (!$r || $s['slug'] === 'sgi' && $r === 'observador') continue; ?><span class="tag <?=h($r)?>"><?=h($s['nombre'])?> · <?=h(nombreRolSector($r))?></span><?php endforeach; ?></div>
          </div>
          <span class="count-pill">Editar</span>
        </summary>
        <form method="post" class="user-edit form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?=$uid?>">
          <div class="form-row"><label>Nombre y apellido<input name="nombre" value="<?=h($u['nombre'])?>" required></label><label>Correo<input type="email" name="email" value="<?=h($u['email'])?>" required></label></div>
          <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="super" value="1" style="width:auto" <?= $u['rol'] === 'admin' ? 'checked' : '' ?>> Super usuario</label>
          <label>Nueva contraseña <small class="muted">(dejar vacío para no cambiarla)</small><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
          <?php matrizRoles($sectores, $roles, $ROLES); ?>
          <button class="btn primary">Guardar cambios</button>
        </form>
        <div class="user-actions">
          <form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="reset_password"><input type="hidden" name="id" value="<?=$uid?>"><label style="display:inline-flex;gap:5px;align-items:center;font-size:11px"><input type="checkbox" name="enviar_mail" style="width:auto"> por mail</label> <button class="btn secondary small">Generar contraseña temporal</button></form>
          <?php if ($uid !== $yoId): ?>
          <form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?=$uid?>"><button class="btn secondary small"><?= (int)$u['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
          <form method="post" onsubmit="return confirm('¿Eliminar a <?=h(addslashes($u['nombre']))?>? No se puede deshacer. (Si solo querés quitarle el acceso, usá Desactivar.)')"><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$uid?>"><button class="btn danger small">Eliminar usuario</button></form>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="form-panel" id="sectores" style="margin-top:22px">
  <h2>Sectores y burbujas</h2>
  <p class="muted">🔒 Restringido: solo lo ven sus integrantes. Burbuja colaborativa: los responsables de sus sectores pueden editar y completar en todos ellos (aprueba el responsable del área).</p>
  <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="sectores">
    <div class="table-wrap"><table><thead><tr><th>Sector</th><th>Burbuja</th><th>🔒 Restringido</th></tr></thead><tbody>
    <?php foreach ($sectores as $s): ?>
      <tr><td><?=h($s['nombre'])?></td>
      <td><select name="burbuja[<?=(int)$s['id']?>]"><option value="0">— Ninguna —</option><?php foreach ($burbujas as $b): ?><option value="<?=(int)$b['id']?>" <?= (int)$s['burbuja_id'] === (int)$b['id'] ? 'selected' : '' ?>><?=h($b['nombre'])?></option><?php endforeach; ?></select></td>
      <td><input type="checkbox" name="restringido[<?=(int)$s['id']?>]" style="width:auto" <?= (int)$s['restringido'] ? 'checked' : '' ?>></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($burbujas): ?>
    <div class="table-wrap"><table><thead><tr><th>Burbuja</th><th>Colaborativa</th></tr></thead><tbody>
      <?php foreach ($burbujas as $b): ?><tr><td><input name="burbuja_nombre[<?=(int)$b['id']?>]" value="<?=h($b['nombre'])?>"></td><td><input type="checkbox" name="burbuja_colab[<?=(int)$b['id']?>]" style="width:auto" <?= (int)$b['colaborativa'] ? 'checked' : '' ?>></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <div class="form-row"><label>Nueva burbuja<input name="nueva_burbuja" placeholder="Nombre"></label><label style="display:flex;gap:8px;align-items:center;margin-top:22px"><input type="checkbox" name="nueva_colab" style="width:auto"> Colaborativa</label></div>
    <button class="btn primary">Guardar sectores y burbujas</button>
  </form>
</section>

</main></div>
<script>
document.getElementById('buscarUsuario').addEventListener('input', function(){
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('#listaUsuarios .user-item').forEach(function(el){ el.style.display = !q || el.dataset.buscar.indexOf(q) >= 0 ? '' : 'none'; });
});
if (location.hash && document.querySelector(location.hash + '.user-item')) document.querySelector(location.hash).open = true;
</script>
</body></html>
