<?php
// Mi perfil: cambiar contraseña, preferencias de mail y ver mis permisos
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
verify_csrf();
$uid = (int)$_SESSION['usuario_id'];
$msg = ''; $err = '';
$primerIngreso = !empty($_SESSION['debe_cambiar_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'password') {
        $actual = (string)($_POST['actual'] ?? '');
        $nueva = (string)($_POST['nueva'] ?? '');
        $repetir = (string)($_POST['repetir'] ?? '');
        $st = $pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
        $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();
        if (!password_verify($actual, $hash)) $err = 'La contraseña actual no es correcta.';
        elseif (strlen($nueva) < 8) $err = 'La nueva contraseña debe tener al menos 8 caracteres.';
        elseif (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) $err = 'La nueva contraseña debe tener letras y números.';
        elseif ($nueva !== $repetir) $err = 'Las contraseñas nuevas no coinciden.';
        elseif (password_verify($nueva, $hash)) $err = 'La nueva contraseña tiene que ser distinta de la actual.';
        else {
            $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 0 WHERE id = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $uid]);
            unset($_SESSION['debe_cambiar_password']);
            session_regenerate_id(true);
            registrarActividad($pdo, 'cambio_password', 'Cambió su contraseña');
            if ($primerIngreso) { header('Location: ' . app_url('/php/dashboard.php')); exit; }
            $msg = 'Contraseña actualizada.';
        }
    }
    if ($accion === 'preferencias') {
        $pdo->prepare('UPDATE usuarios SET recibe_mails = ? WHERE id = ?')->execute([isset($_POST['recibe_mails']) ? 1 : 0, $uid]);
        $msg = 'Preferencias guardadas.';
    }
}
$st = $pdo->prepare('SELECT nombre, email, rol, recibe_mails, ultimo_acceso, creado_en FROM usuarios WHERE id = ?');
$st->execute([$uid]);
$yo = $st->fetch();
$roles = misRolesSector($pdo);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi perfil | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head>
<body><div class="app"><?php sidebar($pdo, 'perfil'); ?><main class="content">
<header class="topbar"><div><p class="eyebrow">MI CUENTA</p><h1><?=h($yo['nombre'])?></h1><p><?=h($yo['email'])?> · <?=h(etiquetaRolUsuario($pdo))?></p></div></header>
<?php if ($primerIngreso): ?><div class="alert error">Por seguridad, antes de seguir tenés que cambiar la contraseña que te dieron.</div><?php endif; ?>
<?php if ($msg): ?><div class="alert success"><?=h($msg)?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?=h($err)?></div><?php endif; ?>
<div class="admin-layout">
  <section class="form-panel">
    <h2>Cambiar contraseña</h2>
    <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="accion" value="password">
      <label>Contraseña actual<input type="password" name="actual" required autocomplete="current-password"></label>
      <label>Nueva contraseña<input type="password" name="nueva" required minlength="8" autocomplete="new-password"></label>
      <label>Repetir nueva contraseña<input type="password" name="repetir" required minlength="8" autocomplete="new-password"></label>
      <p class="muted" style="margin:0;font-size:11px">Mínimo 8 caracteres, con letras y números.</p>
      <button class="btn primary">Guardar contraseña</button>
    </form>
  </section>
  <section class="form-panel">
    <h2>Mis permisos</h2>
    <?php if (esAdmin()): ?><p><span class="tag admin">SUPER USUARIO</span> Podés ver, editar y aprobar en todos los sectores y administrar usuarios.</p><?php endif; ?>
    <table class="perm-matrix"><thead><tr><th>Sector</th><th>Mi rol</th><th>Puedo</th></tr></thead><tbody>
    <?php foreach (sectoresInfo($pdo) as $s): $sid = (int)$s['id']; if (!puedeVerSector($pdo, $sid)) continue; $r = $roles[$sid] ?? null; ?>
      <tr><td><?=h($s['nombre'])?><?= $s['burbuja'] ? ' <small class="muted">· ' . h($s['burbuja']) . '</small>' : '' ?></td>
      <td><?= $r ? '<span class="tag ' . h($r) . '">' . h(nombreRolSector($r)) . '</span>' : '<span class="muted">—</span>' ?></td>
      <td><?= implode(' · ', array_filter(['Ver', puedeCompletarSector($pdo, $sid) ? 'Completar' : null, puedeEditarSector($pdo, $sid) ? 'Editar' : null, puedeAprobarSector($pdo, $sid) ? 'Aprobar' : null])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <h2 style="margin-top:22px">Avisos por mail</h2>
    <form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="preferencias">
      <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="recibe_mails" style="width:auto" <?= (int)$yo['recibe_mails'] ? 'checked' : '' ?>> Recibir las notificaciones también por mail</label>
      <button class="btn secondary" style="margin-top:10px">Guardar preferencia</button>
    </form>
  </section>
</div>
</main></div></body></html>
