<?php

require __DIR__ . '/config/auth.php';
requireLogin();

require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require __DIR__ . '/config/sector_modules.php';


/* =========================================================
   DATOS RECIBIDOS
   ========================================================= */

$slug = trim($_GET['sector'] ?? '');

$cid = !empty($_GET['carpeta'])
    ? (int) $_GET['carpeta']
    : null;

$q = trim($_GET['q'] ?? '');

$tipo = trim($_GET['tipo'] ?? '');


/* =========================================================
   BUSCAR SECTOR
   ========================================================= */

$st = $pdo->prepare(
    'SELECT id, nombre, slug, orden
     FROM sectores
     WHERE slug = ?
     LIMIT 1'
);

$st->execute([$slug]);

$s = $st->fetch();

if (!$s) {
    http_response_code(404);
    exit('Sector no encontrado.');
}


/* =========================================================
   PERMISOS
   ========================================================= */

if (!puedeVerSector($pdo, (int) $s['id'])) {
    http_response_code(403);
    exit('No tenés acceso a este sector.');
}

$canEdit = puedeEditarSector($pdo, (int) $s['id']);


/* =========================================================
   CARPETA ACTUAL
   ========================================================= */

$parent = null;
$breadcrumbs = [];

if ($cid) {

    $st = $pdo->prepare(
        'SELECT *
         FROM carpetas
         WHERE id = ?
         AND sector_id = ?
         AND activa = 1
         LIMIT 1'
    );

    $st->execute([
        $cid,
        $s['id']
    ]);

    $parent = $st->fetch();

    if (!$parent) {
        $cid = null;
    }
}


/* =========================================================
   BREADCRUMBS
   ========================================================= */

if ($cid) {

    $current = $parent;

    while ($current) {

        array_unshift(
            $breadcrumbs,
            $current
        );

        if (!$current['carpeta_padre_id']) {
            break;
        }

        $st = $pdo->prepare(
            'SELECT *
             FROM carpetas
             WHERE id = ?
             AND sector_id = ?
             LIMIT 1'
        );

        $st->execute([
            $current['carpeta_padre_id'],
            $s['id']
        ]);

        $current = $st->fetch();
    }
}


/* =========================================================
   SUBCARPETAS
   ========================================================= */

if ($cid) {

    $st = $pdo->prepare(
        'SELECT *
         FROM carpetas
         WHERE sector_id = ?
         AND carpeta_padre_id = ?
         AND activa = 1
         ORDER BY orden, nombre'
    );

    $st->execute([
        $s['id'],
        $cid
    ]);

} else {

    $st = $pdo->prepare(
        'SELECT *
         FROM carpetas
         WHERE sector_id = ?
         AND carpeta_padre_id IS NULL
         AND activa = 1
         ORDER BY orden, nombre'
    );

    $st->execute([
        $s['id']
    ]);
}

$folders = $st->fetchAll();


/* =========================================================
   DOCUMENTOS
   ========================================================= */

$sql = '
    SELECT d.*
    FROM documentos d
    WHERE d.sector_id = ?
    AND d.activo = 1
';

$params = [
    (int) $s['id']
];


if ($cid) {

    $sql .= ' AND d.carpeta_id = ?';

    $params[] = $cid;

} else {

    $sql .= ' AND d.carpeta_id IS NULL';
}


/* =========================================================
   BUSCADOR
   ========================================================= */

if ($q !== '') {

    $sql .= '
        AND (
            d.titulo LIKE ?
            OR d.descripcion LIKE ?
            OR d.nombre_original LIKE ?
        )
    ';

    $like = '%' . $q . '%';

    array_push(
        $params,
        $like,
        $like,
        $like
    );
}


/* =========================================================
   FILTRO POR TIPO
   ========================================================= */

$tiposPermitidos = [
    'documentacion',
    'procedimiento',
    'formulario',
    'registro',
    'checklist',
    'certificado',
    'otro'
];

if (in_array($tipo, $tiposPermitidos, true)) {

    $sql .= ' AND d.tipo = ?';

    $params[] = $tipo;
}


$sql .= '
    ORDER BY
        d.fecha_actualizacion DESC,
        d.titulo
';


$st = $pdo->prepare($sql);

$st->execute($params);

$docs = $st->fetchAll();


/* =========================================================
   FUNCIÓN PARA URL DE DOCUMENTOS

   IMPORTANTE:
   codificamos cada parte de la ruta por separado.
   Esto evita el problema anterior de %2F.
   ========================================================= */

function documentUrl(string $archivo): string
{
    $archivo = str_replace('\\', '/', $archivo);

    $partes = explode('/', $archivo);

    $partes = array_map(
        'rawurlencode',
        $partes
    );

    return app_url(
        '/uploads/' . implode('/', $partes)
    );
}

?>
<!doctype html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= h($s['nombre']) ?> | NASER SGI
    </title>

    <link
        rel="stylesheet"
        href="<?= app_url('/style.css') ?>"
    >

</head>


<body>

<div class="app">

    <?php sidebar($pdo, $s['slug']); ?>


    <main class="content">


        <!-- =================================================
             ENCABEZADO DEL SECTOR
             ================================================= -->

        <header class="section-top">

            <div>

                <p class="eyebrow">
                    SECTOR
                </p>

                <h1>
                    <?= h($s['nombre']) ?>
                </h1>

                <p>
                    Carpetas, documentos y procedimientos disponibles.
                </p>

            </div>


            <!-- =============================================
                 ACCIONES DEL SECTOR
                 ============================================= -->

            <div class="top-actions">


                <!--
                    BOTÓN DEL MÓDULO ESPECIAL

                    RRHH     -> Gestión RRHH
                    Finanzas -> Gestión financiera
                    Compras  -> Gestión de compras

                    Los demás sectores no muestran nada.
                -->

                <?php botonModuloSector($slug); ?>


                <!--
                    SOLO RESPONSABLE DEL SECTOR O ADMIN
                    pueden administrar carpetas/documentos.
                -->

                <?php if ($canEdit): ?>


                    <a
                        class="btn secondary"
                        href="<?= app_url(
                            '/php/admin/carpetas.php?sector_id=' .
                            (int) $s['id']
                        ) ?>"
                    >
                        Carpetas
                    </a>


                    <a
                        class="btn primary"
                        href="<?= app_url(
                            '/php/admin/documentos.php?sector_id=' .
                            (int) $s['id']
                        ) ?>"
                    >
                        Cargar documentos
                    </a>


                <?php endif; ?>


            </div>

        </header>


        <!-- =================================================
             NAVEGACIÓN DE CARPETAS
             ================================================= -->

        <nav class="breadcrumbs">


            <a
                href="<?= app_url(
                    '/php/sector.php?sector=' .
                    urlencode($slug)
                ) ?>"
            >
                <?= h($s['nombre']) ?>
            </a>


            <?php foreach ($breadcrumbs as $b): ?>


                <span>›</span>


                <a
                    href="<?= app_url(
                        '/php/sector.php?sector=' .
                        urlencode($slug) .
                        '&carpeta=' .
                        (int) $b['id']
                    ) ?>"
                >
                    <?= h($b['nombre']) ?>
                </a>


            <?php endforeach; ?>


        </nav>


        <!-- =================================================
             BUSCADOR
             ================================================= -->

        <form
            method="get"
            class="search-bar"
        >


            <input
                type="hidden"
                name="sector"
                value="<?= h($slug) ?>"
            >


            <?php if ($cid): ?>

                <input
                    type="hidden"
                    name="carpeta"
                    value="<?= (int) $cid ?>"
                >

            <?php endif; ?>


            <input
                name="q"
                value="<?= h($q) ?>"
                placeholder="Buscar por nombre o descripción..."
            >


            <select name="tipo">


                <option value="">
                    Todos los tipos
                </option>


                <?php

                $tipos = [

                    'documentacion'
                        => 'Documentación',

                    'procedimiento'
                        => 'Procedimiento',

                    'formulario'
                        => 'Formulario',

                    'registro'
                        => 'Registro',

                    'checklist'
                        => 'Checklist',

                    'certificado'
                        => 'Certificado',

                    'otro'
                        => 'Otro'

                ];

                ?>


                <?php foreach ($tipos as $valor => $nombre): ?>


                    <option
                        value="<?= h($valor) ?>"
                        <?= $tipo === $valor
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= h($nombre) ?>

                    </option>


                <?php endforeach; ?>


            </select>


            <button
                class="btn primary"
                type="submit"
            >
                Buscar
            </button>


        </form>


        <!-- =================================================
             CARPETAS
             ================================================= -->

        <div class="section-head">

            <div>

                <p class="eyebrow">
                    CARPETAS
                </p>

                <h2>
                    Explorar
                </h2>

            </div>

        </div>


        <section class="folder-grid">


            <?php if (!$folders): ?>


                <div class="empty">

                    No hay subcarpetas en esta ubicación.

                </div>


            <?php endif; ?>


            <?php foreach ($folders as $f): ?>


                <a
                    class="folder-card"
                    href="<?= app_url(
                        '/php/sector.php?sector=' .
                        urlencode($slug) .
                        '&carpeta=' .
                        (int) $f['id']
                    ) ?>"
                >


                    <span class="folder-icon">
                        📁
                    </span>


                    <div>

                        <h3>
                            <?= h($f['nombre']) ?>
                        </h3>

                        <p>
                            Abrir carpeta
                        </p>

                    </div>


                    <b>
                        ›
                    </b>


                </a>


            <?php endforeach; ?>


        </section>


        <!-- =================================================
             DOCUMENTOS
             ================================================= -->

        <div class="section-head">

            <div>

                <p class="eyebrow">
                    ARCHIVOS
                </p>

                <h2>
                    Documentos
                </h2>

            </div>


            <span class="count-pill">

                <?= count($docs) ?> archivo(s)

            </span>

        </div>


        <section class="document-grid">


            <?php if (!$docs): ?>


                <div class="empty">

                    No hay archivos cargados en esta ubicación.

                </div>


            <?php endif; ?>


            <?php foreach ($docs as $d): ?>


                <?php

                $archivo = $d['archivo'] ?? '';

                $ext = strtolower(
                    pathinfo(
                        $archivo,
                        PATHINFO_EXTENSION
                    )
                );

                $url = documentUrl($archivo);

                ?>


                <article class="document-card">


                    <div class="doc-meta">


                        <span class="type-badge">

                            <?= h(
                                ucfirst(
                                    $d['tipo']
                                )
                            ) ?>

                        </span>


                        <span
                            class="status-pill <?= h(
                                $d['estado'] ?? 'aprobado'
                            ) ?>"
                        >

                            <?= h(
                                $d['estado'] ?? 'aprobado'
                            ) ?>

                        </span>


                    </div>


                    <h3>

                        <?= h($d['titulo']) ?>

                    </h3>


                    <p>

                        <?= h(
                            $d['descripcion']
                            ?: 'Sin descripción adicional.'
                        ) ?>

                    </p>


                    <small>

                        Versión
                        <?= h(
                            $d['version'] ?? '1.0'
                        ) ?>

                        · Actualizado
                        <?= h(
                            $d['fecha_actualizacion']
                        ) ?>


                        <?php if (!empty($d['fecha_vencimiento'])): ?>

                            · Vence
                            <?= h(
                                $d['fecha_vencimiento']
                            ) ?>

                        <?php endif; ?>

                    </small>


                    <?php if ($archivo !== ''): ?>


                        <div class="doc-actions">


                            <?php if (
                                in_array(
                                    $ext,
                                    [
                                        'pdf',
                                        'jpg',
                                        'jpeg',
                                        'png'
                                    ],
                                    true
                                )
                            ): ?>


                                <button
                                    class="btn secondary"
                                    type="button"
                                    onclick='previewDoc(
                                        <?= json_encode($url) ?>,
                                        <?= json_encode($d["titulo"]) ?>
                                    )'
                                >
                                    Vista previa
                                </button>


                            <?php endif; ?>


                            <a
                                class="btn primary"
                                href="<?= h($url) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Abrir
                            </a>


                        </div>


                    <?php endif; ?>


                </article>


            <?php endforeach; ?>


        </section>


    </main>

</div>


<!-- =========================================================
     VISTA PREVIA
     ========================================================= -->

<div
    id="previewModal"
    class="modal"
>

    <div class="modal-box preview-box">


        <div class="modal-head">


            <h3 id="previewTitle">

                Vista previa

            </h3>


            <button
                type="button"
                onclick="closePreview()"
            >
                ×
            </button>


        </div>


        <iframe
            id="previewFrame"
            title="Vista previa del documento"
        ></iframe>


    </div>

</div>


<script>

function previewDoc(url, titulo) {

    document.getElementById(
        'previewFrame'
    ).src = url;

    document.getElementById(
        'previewTitle'
    ).textContent = titulo;

    document.getElementById(
        'previewModal'
    ).classList.add('open');

}


function closePreview() {

    document.getElementById(
        'previewModal'
    ).classList.remove('open');

    document.getElementById(
        'previewFrame'
    ).src = '';

}


document
    .getElementById('previewModal')
    .addEventListener(
        'click',
        function (event) {

            if (
                event.target.id
                === 'previewModal'
            ) {

                closePreview();

            }

        }
    );


document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape'
        ) {

            closePreview();

        }

    }
);

</script>


</body>

</html>