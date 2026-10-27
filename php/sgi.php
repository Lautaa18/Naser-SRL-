<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';
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
                 WHERE activo = 1 AND eliminado_lote IS NULL AND sector_id IN ($ph)";

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
                  WHERE d.activo = 1 AND d.eliminado_lote IS NULL AND d.sector_id IN ($ph)
                  ORDER BY d.id DESC LIMIT 20";

    $stRecent = $pdo->prepare($sqlRecent);
    $stRecent->execute($ids);
    $recent = $stRecent->fetchAll();
}

// 2b. Archivos cargados en los demás módulos (Compras, Finanzas, Ventas), de los sectores que el usuario puede ver
$archivosModulos = [];
$etapasCompra = [1 => 'Pedido', 2 => 'Solicitud de presupuesto', 3 => 'Orden de compra', 4 => 'Factura', 5 => 'Pago', 6 => 'Entrega / Recepción'];
if ($ids) {
    $ph2 = implode(',', array_fill(0, count($ids), '?'));
    $consultas = [
        ['Compras', "SELECT CONCAT('Pedido ', c.codigo, ' · ', COALESCE(d.tipo_documento, 'Adjunto')) AS titulo, d.ruta_archivo AS ruta, d.nombre_archivo AS nombre, d.fecha_subida AS fecha, s.nombre AS sector, c.sector_id
                     FROM compras_documentos d JOIN compras c ON c.id = d.compra_id JOIN sectores s ON s.id = c.sector_id
                     WHERE c.sector_id IN ($ph2) ORDER BY d.id DESC LIMIT 30"],
        ['Finanzas', "SELECT f.requisito AS titulo, f.archivo_adjunto AS ruta, f.archivo_adjunto AS nombre, f.actualizado_en AS fecha, s.nombre AS sector, f.sector_id
                      FROM finanzas_checklist f JOIN sectores s ON s.id = f.sector_id
                      WHERE f.sector_id IN ($ph2) AND f.archivo_adjunto IS NOT NULL AND f.archivo_adjunto <> '' ORDER BY f.id DESC LIMIT 30"],
        ['Ventas', "SELECT v.titulo AS titulo, v.archivo_path AS ruta, v.archivo_path AS nombre, v.creado_en AS fecha, s.nombre AS sector, v.sector_id
                    FROM ventas_presentaciones v JOIN sectores s ON s.id = v.sector_id
                    WHERE v.sector_id IN ($ph2) ORDER BY v.id DESC LIMIT 30"],
    ];
    foreach ($consultas as [$modulo, $sqlM]) {
        try {
            $stM = $pdo->prepare($sqlM);
            $stM->execute($ids);
            foreach ($stM->fetchAll() as $r) { $r['modulo'] = $modulo; $archivosModulos[] = $r; }
        } catch (Throwable $e) { /* la tabla de ese módulo todavía no existe */ }
    }
    usort($archivosModulos, fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
    $archivosModulos = array_slice($archivosModulos, 0, 30);
}
function sgiUrlArchivo(string $ruta): string {
    $partes = array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $ruta), '/')));
    return app_url('/uploads/' . implode('/', $partes));
}

// 3. Obtención de carpetas del sector SGI
$sgi = $pdo->query("SELECT id FROM sectores WHERE slug = 'sgi' LIMIT 1")->fetchColumn();
$folders = [];

if ($sgi && in_array((int)$sgi, $ids, true)) {
    $stFolders = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM documentos d WHERE d.carpeta_id = c.id AND d.sector_id = c.sector_id AND d.activo = 1 AND d.eliminado_lote IS NULL) AS cantidad FROM carpetas c WHERE c.sector_id = ? AND c.carpeta_padre_id IS NULL AND c.activa = 1 AND c.eliminado_lote IS NULL ORDER BY c.orden, c.nombre');
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
    <link rel="stylesheet" href="<?= asset('/style.css') ?>">
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

        <?php if ($archivosModulos): ?>
        <section class="table-panel" style="margin-bottom:24px">
            <div class="section-head">
                <div>
                    <p class="eyebrow">OTROS SECTORES</p>
                    <h2>Archivos cargados en Compras, Finanzas y Ventas</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Documento</th><th>Módulo</th><th>Sector</th><th>Archivo</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($archivosModulos as $d): ?>
                        <tr>
                            <td><strong><?= h($d['titulo']) ?></strong></td>
                            <td><?= h($d['modulo']) ?></td>
                            <td><?= h($d['sector']) ?></td>
                            <td><?php if (puedeDescargarSector($pdo, (int)$d['sector_id'])): ?><a href="<?= h(sgiUrlArchivo((string)$d['ruta'])) ?>" target="_blank" rel="noopener">📄 <?= h(basename((string)$d['nombre'])) ?></a><?php else: ?><?= h(basename((string)$d['nombre'])) ?><?php endif; ?></td>
                            <td><?= $d['fecha'] ? h(date('d/m/Y H:i', strtotime((string)$d['fecha']))) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

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
                                    <td><?php if (!empty($d['archivo']) && puedeDescargarSector($pdo, (int)$d['sector_id'])): ?><a href="<?= h(sgiUrlArchivo((string)$d['archivo'])) ?>" target="_blank" rel="noopener"><strong><?= h($d['titulo']) ?></strong></a><?php else: ?><strong><?= h($d['titulo']) ?></strong><?php endif; ?></td>
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
                <p class="eyebrow">ACCESOS RAPIDOS</p>
                <h2>Herramientas</h2>
                <a href="<?= app_url('/php/buscar.php') ?>">🔎 Buscar documentos</a>
                <?php if (puedeGestionarDocumentos($pdo)): ?>
                    <a href="<?= app_url('/php/admin/documentos.php') ?>">⬆ Carga masiva / ZIP</a>
                    <a href="<?= app_url('/php/admin/carpetas.php') ?>">📁 Administrar carpetas</a>
                <?php endif; ?>
                <?php $opId = sectorIdPorSlug($pdo, 'operaciones'); if ($opId && puedeVerSector($pdo, $opId)): ?><a href="<?= app_url('/php/operaciones.php') ?>">⚙ Gestión operativa</a><?php endif; ?>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
