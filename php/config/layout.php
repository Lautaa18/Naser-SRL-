<?php
require_once __DIR__ . '/auth.php';

function sidebar(PDO $pdo, string $active=''): void {
    $sectores = sectoresVisibles($pdo);
    $rol = $_SESSION['rol'] ?? '';
    $canDocs = puedeGestionarDocumentos($pdo);
?>
<aside class="sidebar">
  <a class="brand-panel" href="<?=app_url('/php/dashboard.php')?>"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER División Petróleo"></a>
  <nav class="side-nav">
    <a class="<?=$active==='inicio'?'active':''?>" href="<?=app_url('/php/dashboard.php')?>"><span>⌂</span> Inicio</a>
    <div class="nav-label">SISTEMA DE GESTIÓN</div>
    <a class="<?=$active==='sgi'?'active':''?>" href="<?=app_url('/php/sgi.php')?>"><span>▦</span> SGI</a>
    <a class="<?=$active==='buscar'?'active':''?>" href="<?=app_url('/php/buscar.php')?>"><span>⌕</span> Buscar documentación</a>
    <div class="nav-label">SECTORES</div>
    <?php foreach($sectores as $s): if($s['slug']==='sgi') continue; ?>
      <a class="<?=$active===$s['slug']?'active':''?>" href="<?=app_url('/php/sector.php?sector='.urlencode($s['slug']))?>"><?=h($s['nombre'])?></a>
    <?php endforeach; ?>
    <a class="<?=$active==='operaciones'?'active':''?>" href="<?=app_url('/php/operaciones.php')?>"><span>⚙</span> Gestión operativa</a>
    <?php if($canDocs): ?>
      <div class="nav-label">GESTIÓN DOCUMENTAL</div>
      <a class="<?=$active==='documentos'?'active':''?>" href="<?=app_url('/php/admin/documentos.php')?>">Cargar documentos</a>
      <a class="<?=$active==='carpetas'?'active':''?>" href="<?=app_url('/php/admin/carpetas.php')?>">Carpetas y subcarpetas</a>
    <?php endif; ?>
    <?php if(esAdmin()): ?>
      <div class="nav-label">ADMINISTRACIÓN</div>
      <a class="<?=$active==='usuarios'?'active':''?>" href="<?=app_url('/php/admin/usuarios.php')?>">Usuarios y permisos</a>
      <a class="<?=$active==='reportes'?'active':''?>" href="<?=app_url('/php/admin/reportes.php')?>">Reportes</a>
      <a class="<?=$active==='auditoria'?'active':''?>" href="<?=app_url('/php/admin/auditoria.php')?>">Auditoría</a>
      <a href="<?=app_url('/php/tools/health.php')?>">Diagnóstico</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user"><strong><?=h($_SESSION['nombre'] ?? '')?></strong><span><?=h($rol==='admin'?'Administrador':ucfirst($rol))?></span><a href="<?=app_url('/php/logout.php')?>">Cerrar sesión</a></div>
</aside>
<?php }