<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

// Verificación de acceso al sector HSEQ
$rolActual = $_SESSION['rol'] ?? '';

$stmtHseq = $pdo->query("SELECT id FROM sectores WHERE slug = 'hseq' LIMIT 1");
$sectorHseq = $stmtHseq->fetch();

if ($sectorHseq && !puedeVerSector($pdo, (int)$sectorHseq['id'])) {
    http_response_code(403);
    exit('No tienes permiso para acceder al sector HSEQ.');
}

// Consultas de datos básicos
$documentos = [];
if ($sectorHseq) {
    $stDocs = $pdo->prepare("SELECT * FROM documentos WHERE sector_id = ? AND activo = 1 ORDER BY fecha_actualizacion DESC");
    $stDocs->execute([$sectorHseq['id']]);
    $documentos = $stDocs->fetchAll();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HSEQ | NASER SGI</title>
    <link rel="stylesheet" href="<?= app_url('/style.css') ?>">
</head>
<body>
<div class="app">
    <?php sidebar($pdo, 'hseq'); ?>

    <main class="content">
        <header class="section-top">
            <div>
                <p class="eyebrow">SALUD, SEGURIDAD, MEDIO AMBIENTE Y CALIDAD</p>
                <h1>Gestión HSEQ</h1>
                <p>Módulo de control de procesos, prevención y calidad.</p>
            </div>
            <?php if (puedeGestionarDocumentos($pdo)): ?>
                <div class="top-actions">
                    <a class="btn primary" href="<?= app_url('/php/admin/documentos.php') ?>">Cargar documento HSEQ</a>
                </div>
            <?php endif; ?>
        </header>

        <!-- PUNTOS DEL PIZARRÓN -->
        <div class="sector-grid" style="margin-bottom: 2rem;">
            
            <article class="sector-card">
                <h3>1. Informes</h3>
                <p>Reportes y documentación técnica de HSEQ.</p>
                <div class="quick-links">
                    <a href="<?= app_url('/php/sector.php?sector=hseq&tipo=documentacion') ?>">Ver Informes <span>→</span></a>
                </div>
            </article>

            <article class="sector-card">
                <h3>2. Formularios a completar</h3>
                <p>Plantillas e instructivos operativos.</p>
                <div class="quick-links">
                    <a href="<?= app_url('/php/sector.php?sector=hseq&tipo=procedimiento') ?>">Ver Formularios <span>→</span></a>
                </div>
            </article>

            <article class="sector-card">
                <h3>3. Notificaciones de accidentes</h3>
                <p>Registro y aviso de incidentes en planta/campo.</p>
                <div class="quick-links">
                    <a href="#">Registrar / Ver avisos <span>→</span></a>
                </div>
            </article>

            <article class="sector-card">
                <h3>4. Notificaciones de vencimientos</h3>
                <p>Control de fechas, EPP, certificaciones y capacitaciones.</p>
                <div class="quick-links">
                    <a href="#">Panel de Alertas <span>→</span></a>
                </div>
            </article>

            <article class="sector-card">
                <h3>5. Calidad</h3>
                <p>Auditoría e Indicadores de Satisfacción del Cliente (encuestas y gráficos).</p>
                <div class="quick-links">
                    <a href="#">Ver Indicadores <span>→</span></a>
                </div>
            </article>

            <article class="sector-card">
                <h3>6. Gráfico de accidentes</h3>
                <p>Estadísticas y comparativas por diversas áreas.</p>
                <div class="quick-links">
                    <a href="#">Ver Estadísticas <span>→</span></a>
                </div>
            </article>

            <article class="sector-card" style="grid-column: span 2;">
                <h3>7. Observaciones preventivas</h3>
                <p>Módulo de implementación de mejoras y prevención de riesgos.</p>
                <div class="quick-links">
                    <a href="#">Ver / Implementar <span>→</span></a>
                </div>
            </article>

        </div>

        <!-- LISTA GENERAL DE ARCHIVOS DE HSEQ -->
        <div class="table-panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">DOCUMENTACIÓN</p>
                    <h2>Archivos de HSEQ</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$documentos): ?>
                            <tr><td colspan="5" style="text-align:center;">No hay documentos cargados en HSEQ.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td><strong><?= h($doc['titulo']) ?></strong></td>
                                <td><?= h(ucfirst($doc['tipo'])) ?></td>
                                <td>
                                    <span class="status-pill <?= h($doc['estado'] ?? 'aprobado') ?>">
                                        <?= h($doc['estado'] ?? 'aprobado') ?>
                                    </span>
                                </td>
                                <td><?= h($doc['fecha_actualizacion']) ?></td>
                                <td>
                                    <?php if (!empty($doc['archivo'])): ?>
                                        <a class="btn-open" href="<?= app_url('/uploads/' . rawurlencode($doc['archivo'])) ?>" target="_blank">↗ Abrir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>