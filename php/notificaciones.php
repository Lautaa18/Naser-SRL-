<?php
// Notificaciones del usuario (compras, aprobaciones, rechazos y vencimientos)
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
verify_csrf();
$uid = (int)$_SESSION['usuario_id'];

// Abrir una notificacion: se marca como leida y se va al link
if (isset($_GET['abrir'])) {
    $st = $pdo->prepare('SELECT url FROM notificaciones WHERE id = ? AND usuario_id = ?');
    $st->execute([(int)$_GET['abrir'], $uid]);
    $url = $st->fetchColumn();
    $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?')->execute([(int)$_GET['abrir'], $uid]);
    header('Location: ' . ($url ? app_url($url) : app_url('/php/notificaciones.php')));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'leer_todas') $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ?')->execute([$uid]);
    if (($_POST['accion'] ?? '') === 'borrar_leidas') $pdo->prepare('DELETE FROM notificaciones WHERE usuario_id = ? AND leida = 1')->execute([$uid]);
    header('Location: ' . app_url('/php/notificaciones.php'));
    exit;
}
$st = $pdo->prepare('SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY leida ASC, id DESC LIMIT 200');
$st->execute([$uid]);
$items = $st->fetchAll();
$iconos = ['aprobacion' => '📝', 'aprobado' => '✅', 'rechazado' => '⚠️', 'vencimiento' => '⏰', 'info' => 'ℹ️', 'bienvenida' => '👋', 'compra' => '🛒'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notificaciones | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head>
<body><div class="app"><?php sidebar($pdo, 'notificaciones'); ?><main class="content">
<header class="topbar"><div><p class="eyebrow">AVISOS</p><h1>Notificaciones</h1><p>Compras, formularios para aprobar, aprobaciones, rechazos y vencimientos. Los avisos también pueden llegar por mail.</p></div>
<div class="top-actions">
  <form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="leer_todas"><button class="btn secondary">Marcar todas como leídas</button></form>
  <form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="borrar_leidas"><button class="btn secondary">Borrar leídas</button></form>
</div></header>
<div class="notif-list">
<?php if (!$items): ?><div class="empty">No tenés notificaciones.</div><?php endif; ?>
<?php foreach ($items as $n): ?>
  <a class="notif-item <?= $n['leida'] ? '' : 'nueva' ?>" href="<?=h(app_url('/php/notificaciones.php') . '?abrir=' . (int)$n['id'])?>">
    <span class="notif-icon"><?=$iconos[$n['tipo']] ?? 'ℹ️'?></span>
    <div><h3><?=h($n['titulo'])?></h3><p><?=h($n['mensaje'])?></p></div>
    <time><?=h(date('d/m/Y H:i', strtotime($n['creado_en'])))?></time>
  </a>
<?php endforeach; ?>
</div>
</main></div></body></html>
