<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';
if (!empty($_SESSION['usuario_id'])) { header('Location: '.app_url('/php/dashboard.php')); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    $st=$pdo->prepare('SELECT id,nombre,email,password,rol,activo FROM usuarios WHERE LOWER(email)=? LIMIT 1');
    $st->execute([$email]); $u=$st->fetch();
    if ($u && (int)$u['activo']===1 && password_verify($password,$u['password'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id']=(int)$u['id']; $_SESSION['nombre']=$u['nombre']; $_SESSION['email']=$u['email']; $_SESSION['rol']=$u['rol'];
        registrarActividad($pdo,'login','Inicio de sesión');
        header('Location: '.app_url('/php/dashboard.php')); exit;
    }
    $error='Correo o contraseña incorrectos.';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingreso | NASER SGI</title><link rel="stylesheet" href="<?=app_url('/style.css')?>"></head><body class="login-page">
<div class="login-shell"><section class="login-brand"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER"><div><p class="eyebrow">DIVISIÓN PETRÓLEO</p><h1>Sistema de Gestión Integrado</h1><p>Documentación, operaciones y control de accesos en un único entorno.</p></div></section><section class="login-card"><p class="eyebrow">ACCESO INTERNO</p><h2>Ingresar</h2><p class="muted">Utilizá el correo y contraseña asignados.</p><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><form method="post" class="form-grid"><label>Correo<input type="email" name="email" required autocomplete="email"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary" type="submit">Ingresar</button></form><div class="demo-note"><strong>Cuenta demo:</strong> admin@naser.test · Admin123!</div></section></div>
</body></html>
