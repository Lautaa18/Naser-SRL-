<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';
if (!empty($_SESSION['usuario_id'])) { header('Location: '.app_url('/php/dashboard.php')); exit; }
$error='';
// Bloqueo temporal: 5 intentos fallidos en 15 minutos (por correo o por IP)
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_MINUTOS_BLOQUEO = 15;
function loginBloqueado(PDO $pdo, string $email, string $ip): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM login_intentos WHERE (email=? OR ip=?) AND creado_en > (NOW() - INTERVAL '.LOGIN_MINUTOS_BLOQUEO.' MINUTE)');
    $st->execute([$email,$ip]);
    return (int)$st->fetchColumn() >= LOGIN_MAX_INTENTOS;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    $ip=substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45);
    if (loginBloqueado($pdo,$email,$ip)) {
        $error='Demasiados intentos fallidos. Esperá '.LOGIN_MINUTOS_BLOQUEO.' minutos e intentá de nuevo.';
    } else {
        $st=$pdo->prepare('SELECT id,nombre,email,password,rol,activo,debe_cambiar_password FROM usuarios WHERE LOWER(email)=? LIMIT 1');
        $st->execute([$email]); $u=$st->fetch();
        if ($u && (int)$u['activo']===1 && password_verify($password,$u['password'])) {
            $pdo->prepare('DELETE FROM login_intentos WHERE email=?')->execute([$email]);
            session_regenerate_id(true);
            $_SESSION['usuario_id']=(int)$u['id']; $_SESSION['nombre']=$u['nombre']; $_SESSION['email']=$u['email']; $_SESSION['rol']=$u['rol'];
            if ((int)$u['debe_cambiar_password'] === 1) $_SESSION['debe_cambiar_password'] = true;
            $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([(int)$u['id']]);
            registrarActividad($pdo,'login','Inicio de sesión');
            header('Location: '.app_url('/php/dashboard.php')); exit;
        }
        $pdo->prepare('INSERT INTO login_intentos(email,ip) VALUES(?,?)')->execute([$email,$ip]);
        $pdo->exec('DELETE FROM login_intentos WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
        $error='Correo o contraseña incorrectos.';
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingreso | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>"></head><body class="login-page">
<div class="login-shell"><section class="login-brand"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER"><div><p class="eyebrow">DIVISIÓN PETRÓLEO</p><h1>Sistema de Gestión Integrado</h1><p>Documentación, operaciones y control de accesos en un único entorno.</p></div></section><section class="login-card"><p class="eyebrow">ACCESO INTERNO</p><h2>Ingresar</h2><p class="muted">Utilizá el correo y contraseña asignados.</p><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><form method="post" class="form-grid"><?=csrf_field()?><label>Correo<input type="email" name="email" required autocomplete="email"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary" type="submit">Ingresar</button></form><?php if(app_is_dev()):?><div class="demo-note"><strong>Cuenta demo:</strong> admin@naser.test · Admin123!</div><?php endif;?></section></div>
</body></html>
