<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notificaciones.php';

function sidebar(PDO $pdo, string $active=''): void {
    $sectores = sectoresVisibles($pdo);
    $canDocs = puedeGestionarDocumentos($pdo);
    tareasDiarias($pdo); // avisos de vencimientos (una vez por dia)
    $sinLeer = notificacionesSinLeer($pdo, (int)($_SESSION['usuario_id'] ?? 0));
    require_once __DIR__ . '/mensajes.php';
    $msgSinLeer = mensajesSinLeer($pdo, (int)($_SESSION['usuario_id'] ?? 0));
    $urlNotif = app_url('/php/notificaciones.php');
    $campana = '<a class="notif-bell" href="' . h($urlNotif) . '" title="Notificaciones" aria-label="Notificaciones' . ($sinLeer ? " ($sinLeer sin leer)" : '') . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.4-2h-4.8A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5-6.7V3.5a2 2 0 1 0-4 0v.8A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z" fill="currentColor"/></svg>' . ($sinLeer ? '<b>' . ($sinLeer > 99 ? '99+' : $sinLeer) . '</b>' : '') . '</a>';
?>
<header class="mobile-bar">
  <a class="mobile-brand" href="<?=app_url('/php/dashboard.php')?>"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER - Ir al inicio"></a>
  <?=$campana?>
  <button type="button" class="menu-toggle" aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Menú</span>
  </button>
</header>
<div class="menu-backdrop" hidden></div>
<aside class="sidebar" id="menu-principal">
  <button type="button" class="menu-close" aria-label="Cerrar menú">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  </button>
  <a class="brand-panel" href="<?=app_url('/php/dashboard.php')?>"><img src="<?=app_url('/img/logo-naser.png')?>" alt="NASER División Petróleo"></a>
  <nav class="side-nav">
    <a class="<?=$active==='inicio'?'active':''?>" href="<?=app_url('/php/dashboard.php')?>"><span>⌂</span> Inicio</a>
    <div class="nav-label">SISTEMA DE GESTIÓN</div>
    <a class="<?=$active==='sgi'?'active':''?>" href="<?=app_url('/php/sgi.php')?>"><span>▦</span> SGI</a>
    <a class="<?=$active==='buscar'?'active':''?>" href="<?=app_url('/php/buscar.php')?>"><span>⌕</span> Buscar documentación</a>
    <a class="<?=$active==='formularios'?'active':''?>" href="<?=app_url('/php/formularios/index.php')?>"><span>✎</span> Formularios</a>
    <a class="<?=$active==='mensajes'?'active':''?>" href="<?=app_url('/php/mensajes.php')?>"><span>✉</span> Mensajes<?= $msgSinLeer ? ' (' . (int)$msgSinLeer . ')' : '' ?></a>
    <?php if(puedeVerHabilitaciones($pdo)): ?><a class="<?=$active==='personal'?'active':''?>" href="<?=app_url('/php/personal.php')?>"><span>☺</span> Personal</a><?php endif; ?>
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
      <div class="nav-label">SUPER USUARIO</div>
      <a class="<?=$active==='usuarios'?'active':''?>" href="<?=app_url('/php/admin/usuarios.php')?>">Usuarios y permisos</a>
      <a class="<?=$active==='reportes'?'active':''?>" href="<?=app_url('/php/admin/reportes.php')?>">Reportes</a>
      <a class="<?=$active==='auditoria'?'active':''?>" href="<?=app_url('/php/admin/auditoria.php')?>">Auditoría</a>
      <a href="<?=app_url('/php/tools/health.php')?>">Diagnóstico</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="sidebar-user-top"><strong><?=h($_SESSION['nombre'] ?? '')?></strong><?=$campana?></div>
    <span><?=h(etiquetaRolUsuario($pdo))?></span>
    <div class="sidebar-user-links"><a href="<?=app_url('/php/perfil.php')?>">Mi perfil</a><a href="<?=app_url('/php/logout.php')?>">Cerrar sesión</a></div>
  </div>
</aside>
<script>
(function(){
  var body=document.body, btn=document.querySelector('.menu-toggle'),
      closeBtn=document.querySelector('.menu-close'), backdrop=document.querySelector('.menu-backdrop'),
      menu=document.getElementById('menu-principal');
  if(!btn||!menu) return;
  function abrir(){ body.classList.add('menu-abierto'); backdrop.hidden=false; btn.setAttribute('aria-expanded','true'); closeBtn.focus(); }
  function cerrar(){ body.classList.remove('menu-abierto'); backdrop.hidden=true; btn.setAttribute('aria-expanded','false'); }
  btn.addEventListener('click', abrir);
  closeBtn.addEventListener('click', function(){ cerrar(); btn.focus(); });
  backdrop.addEventListener('click', cerrar);
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && body.classList.contains('menu-abierto')){ cerrar(); btn.focus(); } });
  menu.addEventListener('click', function(e){ if(e.target.closest('a')) cerrar(); });
  window.addEventListener('resize', function(){ if(window.innerWidth>900) cerrar(); });
})();
</script>
<?php }