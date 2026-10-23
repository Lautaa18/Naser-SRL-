<?php
// Cierre de sesion. Solo se cierra con un envio (POST) con token: asi nadie puede
// cerrarle la sesion a otra persona con un link o una imagen escondida.
require __DIR__ . '/config/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    naser_cerrar_sesion();
    header('Location: ' . app_url('/php/login.php'));
    exit;
}
if (empty($_SESSION['usuario_id'])) { header('Location: ' . app_url('/php/login.php')); exit; }
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cerrar sesión | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head>
<body class="login-page"><div class="login-shell"><section class="login-card" style="margin:auto">
<p class="eyebrow">NASER SGI</p><h2>¿Cerrar sesión?</h2><p class="muted">Vas a salir del sistema en este equipo.</p>
<form method="post" class="form-grid"><?=csrf_field()?><button class="btn primary" type="submit">Cerrar sesión</button><a class="btn secondary" href="<?=h(app_url('/php/dashboard.php'))?>">Volver</a></form>
</section></div></body></html>
