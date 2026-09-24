<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

$visible = sectoresVisibles($pdo);
$ids = array_map(fn($s) => (int)$s['id'], $visible);

$total = 0;
$vig = 0;
$rev = 0;
$obs = 0;
$recent = [];

if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));

    // 1. Unificación de contadores en una sola consulta
    $sqlStats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN COALESCE(estado, 'aprobado') = 'aprobado' THEN 1 ELSE 0 END) as vig,
                    SUM(CASE WHEN estado = 'revision' THEN 1 ELSE 0 END) as rev,
                    SUM(CASE WHEN estado = 'obsoleto' THEN 1 ELSE 0 END) as obs
                 FROM documentos 
                 WHERE activo = 1 AND sector_id IN ($ph)";
    
    $st = $pdo->prepare($sqlStats);
    $st->execute($ids);
    $stats = $st->fetch();

    if ($stats) {
        $total = (int)$stats['total'];
        $vig   = (int)$stats['vig'];
        $rev   = (int)$stats['rev'];
        $obs   = (int)$stats['obs'];
    }

    // 2. Obtención de últimos documentos
    $sqlRecent = "SELECT d.*, s.nombre AS sector, c.nombre AS carpeta 
                  FROM documentos d 
                  JOIN sectores s ON s.id = d.sector_id 
                  LEFT JOIN carpetas c ON c.id = d.carpeta_id 
                  WHERE d.activo = 1 AND d.sector_id IN ($ph) 
                  ORDER BY d.id DESC LIMIT 10";
                  
    $stRecent = $pdo->prepare($sqlRecent);
    $stRecent->execute($ids);
    $recent = $stRecent->fetchAll();
}

// 3. Obtención de carpetas del sector SGI
$sgi = $pdo->query("SELECT id FROM sectores WHERE slug = 'sgi' LIMIT 1")->fetchColumn();
$folders = [];

if ($sgi) {
    $stFolders = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM documentos d WHERE d.carpeta_id = c.id AND d.activo = 1) AS cantidad FROM carpetas c WHERE c.sector_id = ? AND c.carpeta_padre_id IS NULL AND c.activa = 1 ORDER BY c.orden, c.nombre');
    $stFolders->execute([$sgi]);
    $folders = $stFolders->fetchAll();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGI | NASER</title>
    <link rel="stylesheet" href="<?= app_url('/style.css') ?>">
</head>
<body>
<div class="app">
    <?php sidebar($pdo, 'sgi'); ?>
    
    <main class="content">
        <header class="section-top">
            <div>
                <p class="eyebrow">SISTEMA DE GESTIÓN INTEGRADO</p>
                <h1>Centro documental</h1>
                <p>Vista general de la documentación a la que tenés acceso.</p>
            </div>
            <?php if (puedeGestionarDocumentos($pdo)): ?>
                <div class="top-actions">
                    <a class="btn secondary" href="<?= app_url('/php/admin/carpetas.php') ?>">Carpetas</a>
                    <a class="btn primary" href="<?= app_url('/php/admin/documentos.php') ?>">Carga masiva</a>
                </div>
            <?php endif; ?>
        </header>

        <section class="sgi-stats">
            <div><strong><?= $total ?></strong><span>Documentos</span></div>
            <div><strong><?= $vig ?></strong><span>Aprobados</span></div>
            <div><strong><?= $rev ?></strong><span>En revisión</span></div>
            <div><strong><?= $obs ?></strong><span>Obsoletos</span></div>
        </section>

        <div class="section-head">
            <div>
                <p class="eyebrow">SGI</p>
                <h2>Carpetas principales</h2>
            </div>
        </div>

        <section class="folder-grid">
            <?php foreach ($folders as $f): ?>
                <a class="folder-card" href="<?= app_url('/php/sector.php?sector=sgi&carpeta=' . (int)$f['id']) ?>">
                    <span class="folder-icon">📁</span>
                    <div>
                        <h3><?= h($f['nombre']) ?></h3>
                        <p><?= $f['cantidad'] ?> documento(s)</p>
                    </div>
                    <b>›</b>
                </a>
            <?php endforeach; ?>

            <?php if (!$folders): ?>
                <div class="empty">Todavía no hay carpetas SGI.</div>
            <?php endif; ?>
        </section>

        <div class="sgi-bottom">
            <section class="table-panel">
                <div class="section-head">
                    <div>
                        <p class="eyebrow">ÚLTIMAS CARGAS</p>
                        <h2>Documentos recientes</h2>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Sector</th>
                                <th>Carpeta</th>
                                <th>Versión</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recent): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No hay documentos recientes.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($recent as $d): ?>
                                <tr>
                                    <td><strong><?= h($d['titulo']) ?></strong></td>
                                    <td><?= h($d['sector']) ?></td>
                                    <td><?= h($d['carpeta'] ?? 'Sin carpeta') ?></td>
                                    <td><?= h($d['version'] ?? '1.0') ?></td>
                                    <td>
                                        <span class="status-pill <?= h($d['estado'] ?? 'aprobado') ?>">
                                            <?= h($d['estado'] ?? 'aprobado') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="quick-panel">
                <p class="eyebrow">ACCESOS RÁPIDOS</p>
                <h2>Herramientas</h2>
                <a href="<?= app_url('/php/buscar.php') ?>">🔎 Buscar documentos</a>
                <?php if (puedeGestionarDocumentos($pdo)): ?>
                    <a href="<?= app_url('/php/admin/documentos.php') ?>">⬆ Carga masiva / ZIP</a>
                    <a href="<?= app_url('/php/admin/carpetas.php') ?>">📁 Administrar carpetas</a>
                <?php endif; ?>
                <a href="<?= app_url('/php/operaciones.php') ?>">⚙ Gestión operativa</a>
            </aside>
        </div>
    </main>
</div>
</body>
</html>