<?php
require __DIR__ . '/../config/auth.php';
requireLogin();

// Verificar que solo los administradores accedan
if (($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

require __DIR__ . '/../config/db.php';

$mensaje = '';
$error = '';

// FUNCION PARA CONVERTIR NOMBRE A SLUG
function crearSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// PROCESAR FORMULARIO (GUARDAR O EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $orden = (int)($_POST['orden'] ?? 0);
    $sectorId = (int)($_POST['id'] ?? 0);

    if (empty($nombre)) {
        $error = 'El nombre del sector es obligatorio.';
    } else {
        $slug = crearSlug($nombre);

        if ($action === 'crear') {
            $stmt = $pdo->prepare('INSERT INTO sectores (nombre, slug, orden) VALUES (?, ?, ?)');
            try {
                $stmt->execute([$nombre, $slug, $orden]);
                $mensaje = 'Sector creado correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al crear el sector. Es posible que el nombre ya exista.';
            }
        } elseif ($action === 'editar' && $sectorId > 0) {
            $stmt = $pdo->prepare('UPDATE sectores SET nombre = ?, slug = ?, orden = ? WHERE id = ?');
            try {
                $stmt->execute([$nombre, $slug, $orden, $sectorId]);
                $mensaje = 'Sector actualizado correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al actualizar el sector.';
            }
        }
    }
}

// OBTENER TODOS LOS SECTORES PARA EL LISTADO
$stmt = $pdo->query('SELECT * FROM sectores ORDER BY orden, nombre');
$sectores = $stmt->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Sectores | NASER SGI</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMenu()"></div>

<div class="app-shell">

    <div class="mobile-header-bar">
        <div class="brand" style="font-size: 1.1rem; font-weight: bold; color: var(--verde-fuerte);">
            NASER <span style="font-size: 0.8rem; color: var(--texto-secundario);">SRL</span>
        </div>
        <button type="button" class="btn-hamburger" onclick="toggleMenu()">☰</button>
    </div>

    <aside id="mainSidebar" class="php-sidebar">
        <div class="logo-box">
            <div class="brand">NASER <span>SRL</span></div>
            <small>Sistema de Gestión Integrado</small>
        </div>

        <a class="nav-link" href="../dashboard.php">Volver al Inicio</a>
        <div class="nav-separator"></div>
        <a class="nav-link" href="usuarios.php">Usuarios</a>
        <a class="nav-link" href="documentos.php">Administrar documentos</a>
        <a class="nav-link active" href="sectores.php">Administrar sectores</a>

        <div class="php-sidebar-user">
            <strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></strong>
            <small><?= htmlspecialchars(ucfirst($_SESSION['rol'] ?? '')) ?></small>
            <a href="../logout.php">Cerrar sesión</a>
        </div>
    </aside>

    <main class="php-content">

        <div class="section-heading spaced">
            <div>
                <p class="eyebrow">ADMINISTRACIÓN</p>
                <h1>Gestión de Sectores</h1>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div style="padding: 10px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 15px;">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 15px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO DE NUEVO SECTOR -->
        <article class="document-card" style="margin-bottom: 2rem;">
            <h3>Nuevo Sector</h3>
            <form method="POST" action="sectores.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-top: 10px;">
                <input type="hidden" name="action" value="crear">
                
                <div style="flex: 2; min-width: 200px;">
                    <label style="display: block; font-size: 0.85rem;">Nombre del Sector</label>
                    <input type="text" name="nombre" placeholder="Ej: Recursos Humanos" required style="width: 100%; padding: 8px;">
                </div>

                <div style="flex: 1; min-width: 100px;">
                    <label style="display: block; font-size: 0.85rem;">Orden</label>
                    <input type="number" name="orden" value="0" style="width: 100%; padding: 8px;">
                </div>

                <button type="submit" class="btn primary" style="height: 38px;">Guardar Sector</button>
            </form>
        </article>

        <!-- LISTADO DE SECTORES EXISTENTES -->
        <article class="document-card">
            <h3>Sectores Registrados</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="border-bottom: 2px solid #ccc; text-align: left;">
                        <th style="padding: 8px;">Orden</th>
                        <th style="padding: 8px;">Nombre</th>
                        <th style="padding: 8px;">Slug (URL)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectores as $sec): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px;"><?= htmlspecialchars($sec['orden']) ?></td>
                            <td style="padding: 8px;"><strong><?= htmlspecialchars($sec['nombre']) ?></strong></td>
                            <td style="padding: 8px; color: var(--texto-secundario);"><?= htmlspecialchars($sec['slug']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>

    </main>

</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}
</script>

</body>
</html>