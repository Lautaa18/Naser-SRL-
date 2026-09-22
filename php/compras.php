<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';

$slug = 'compras';
$st = $pdo->prepare('SELECT id,nombre,slug FROM sectores WHERE slug=? LIMIT 1');
$st->execute([$slug]);
$sector = $st->fetch();
if (!$sector) { http_response_code(404); exit('Sector no encontrado.'); }
$sid = (int)$sector['id'];
if (!puedeVerSector($pdo,$sid)) { http_response_code(403); exit('No tenés acceso a este sector.'); }
$canEdit = puedeEditarSector($pdo,$sid);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg = ''; $err = '';

// --- CREACIÓN Y MIGRACIÓN AUTOMÁTICA DE TABLAS ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        codigo VARCHAR(30) NOT NULL,
        sector VARCHAR(100),
        descripcion TEXT NOT NULL,
        cantidad INT DEFAULT 1,
        prioridad ENUM('Normal','Urgente','Critico') DEFAULT 'Normal',
        proveedor VARCHAR(150) DEFAULT 'Pendiente',
        monto DECIMAL(12,2) DEFAULT 0.00,
        moneda VARCHAR(5) DEFAULT 'ARS',
        etapa INT DEFAULT 1,
        estado_logistico VARCHAR(100) DEFAULT 'Pedido cargado',
        creado_por INT,
        actualizado_por INT,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        etapa INT NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        ruta_archivo VARCHAR(255) NOT NULL,
        tipo_documento VARCHAR(100) DEFAULT 'Adjunto',
        creado_por INT,
        fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_encuesta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        calidad VARCHAR(50) NOT NULL,
        estado_fisico VARCHAR(50) NOT NULL,
        cumplimiento_entrega VARCHAR(50) NOT NULL,
        observaciones TEXT,
        creado_por INT,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Intenta agregar columnas faltantes en tablas existentes si no fueron creadas recién
    @$pdo->exec("ALTER TABLE compras ADD COLUMN monto DECIMAL(12,2) DEFAULT 0.00 AFTER proveedor;");
    @$pdo->exec("ALTER TABLE compras ADD COLUMN moneda VARCHAR(5) DEFAULT 'ARS' AFTER monto;");
    @$pdo->exec("ALTER TABLE compras_encuesta ADD COLUMN fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP;");
} catch (Throwable $e) {
    // Continuar si las columnas o tablas ya existen
}

function auditModulo(PDO $pdo, int $uid, string $accion, string $detalle): void {
    try {
        $st = $pdo->prepare('INSERT INTO actividad(usuario_id,accion,detalle) VALUES(?,?,?)');
        $st->execute([$uid, $accion, $detalle]);
    } catch(Throwable $e) {}
}

function exigirEdicion(bool $canEdit): void {
    if (!$canEdit) { http_response_code(403); exit('No tenés permiso para modificar este sector.'); }
}

$etapaNombre = [
    1 => '1. Pedido Cargado',
    2 => '2. Solicitud de Presupuesto',
    3 => '3. Orden de Compra',
    4 => '4. Facturación',
    5 => '5. Pago',
    6 => '6. Entrega / Recepción'
];

$uploadDir = dirname(__DIR__) . '/uploads/compras';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

// --- PROCESAMIENTO DE FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirEdicion($canEdit);
    try {
        $a = $_POST['accion'] ?? '';

        // 1. Crear / Guardar Pedido
        if ($a === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $desc = trim($_POST['descripcion'] ?? '');
            $cant = max(1, (int)($_POST['cantidad'] ?? 1));
            $prio = $_POST['prioridad'] ?? 'Normal';
            $prov = trim($_POST['proveedor'] ?? 'Pendiente');
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = $_POST['moneda'] ?? 'ARS';
            $et = max(1, min(6, (int)($_POST['etapa'] ?? 1)));
            $estado = trim($_POST['estado_logistico'] ?? 'Pedido cargado');

            if ($desc === '') throw new RuntimeException('Ingresá una descripción para el pedido.');

            if ($id) {
                $pdo->prepare('UPDATE compras SET descripcion=?, cantidad=?, prioridad=?, proveedor=?, monto=?, moneda=?, etapa=?, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                    ->execute([$desc, $cant, $prio, $prov, $monto, $moneda, $et, $estado, $uid, $id, $sid]);
                $compraId = $id;
                $msg = 'Pedido actualizado con éxito.';
            } else {
                $codigo = 'CMP-' . date('Y') . '-' . str_pad((string)((int)$pdo->query('SELECT COUNT(*)+1 FROM compras')->fetchColumn()), 4, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO compras(sector_id, codigo, sector, descripcion, cantidad, prioridad, proveedor, monto, moneda, etapa, estado_logistico, creado_por, actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$sid, $codigo, $sector['nombre'], $desc, $cant, $prio, $prov, $monto, $moneda, $et, $estado, $uid, $uid]);
                $compraId = (int)$pdo->lastInsertId();
                $msg = "Pedido $codigo creado correctamente.";
            }

            if (!empty($_FILES['archivo']['name']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $orig = $_FILES['archivo']['name'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'], true)) throw new RuntimeException('Formato de archivo no permitido.');
                $safe = 'compra-' . $compraId . '-e' . $et . '-' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $uploadDir . '/' . $safe)) throw new RuntimeException('Error al guardar el archivo adjunto.');
                $pdo->prepare('INSERT INTO compras_documentos(compra_id, etapa, nombre_archivo, ruta_archivo, tipo_documento, creado_por) VALUES(?,?,?,?,?,?)')
                    ->execute([$compraId, $et, $orig, 'compras/' . $safe, 'Documento de Inicio', $uid]);
            }
            auditModulo($pdo, $uid, 'compras_guardar', "Compra #$compraId");
        }

        // 2. Subir Documento por Etapa
        if ($a === 'documento_etapa') {
            $id = (int)($_POST['compra_id'] ?? 0);
            $et = max(1, min(6, (int)($_POST['etapa_doc'] ?? 1)));
            $tipoDoc = trim($_POST['tipo_documento'] ?? 'Documento General');

            $st = $pdo->prepare('SELECT id FROM compras WHERE id=? AND sector_id=?');
            $st->execute([$id, $sid]);
            if (!$st->fetchColumn()) throw new RuntimeException('Pedido inválido.');

            if (empty($_FILES['archivo_etapa']['name']) || ($_FILES['archivo_etapa']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Seleccioná un archivo válido.');
            }

            $orig = $_FILES['archivo_etapa']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'], true)) throw new RuntimeException('Formato no permitido (Use PDF, Office o imágenes).');
            
            $safe = 'compra-' . $id . '-etapa-' . $et . '-' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['archivo_etapa']['tmp_name'], $uploadDir . '/' . $safe)) throw new RuntimeException('No se pudo guardar el archivo.');

            $pdo->prepare('INSERT INTO compras_documentos(compra_id, etapa, nombre_archivo, ruta_archivo, tipo_documento, creado_por) VALUES(?,?,?,?,?,?)')
                ->execute([$id, $et, $orig, 'compras/' . $safe, $tipoDoc, $uid]);

            $pdo->prepare('UPDATE compras SET etapa=GREATEST(etapa,?), actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                ->execute([$et, $uid, $id, $sid]);

            auditModulo($pdo, $uid, 'compras_documento', "Documento subido a la compra #$id etapa $et");
            $msg = 'Documento subido y estado actualizado correctamente.';
        }

        // 3. Registrar Encuesta de Recepción
        if ($a === 'encuesta') {
            $id = (int)($_POST['compra_id'] ?? 0);
            $st = $pdo->prepare('SELECT id FROM compras WHERE id=? AND sector_id=?');
            $st->execute([$id, $sid]);
            if (!$st->fetchColumn()) throw new RuntimeException('Pedido de compra inválido.');

            $calidad = $_POST['calidad'] ?? 'Conforme';
            $estadoFisico = $_POST['estado_fisico'] ?? 'Bueno';
            $entrega = $_POST['entrega'] ?? 'En término';
            $obs = trim($_POST['observaciones'] ?? '');

            $pdo->prepare('INSERT INTO compras_encuesta(compra_id, calidad, estado_fisico, cumplimiento_entrega, observaciones, creado_por) VALUES(?,?,?,?,?,?)')
                ->execute([$id, $calidad, $estadoFisico, $entrega, $obs, $uid]);

            $pdo->prepare('UPDATE compras SET etapa=6, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=?')
                ->execute(['Producto Recibido y Evaluado', $uid, $id]);

            auditModulo($pdo, $uid, 'compras_encuesta', "Encuesta registrada para Compra #$id");
            $msg = 'Recepción y evaluación registrada con éxito.';
        }

        // 4. Eliminar Pedido
        if ($a === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM compras WHERE id=? AND sector_id=?')->execute([$id, $sid]);
            auditModulo($pdo, $uid, 'compras_eliminar', "Compra #$id");
            $msg = 'Registro de compra eliminado.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// Carga de Datos
$edit = null;
if ($canEdit && !empty($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM compras WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar'], $sid]);
    $edit = $st->fetch();
}

$st = $pdo->prepare('SELECT c.*, u.nombre AS actualizado_nombre, (SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id) AS total_docs FROM compras c LEFT JOIN usuarios u ON u.id=c.actualizado_por WHERE c.sector_id=? ORDER BY c.fecha_creacion DESC');
$st->execute([$sid]);
$rows = $st->fetchAll();

$docsCompra = $pdo->prepare('SELECT d.*, c.codigo FROM compras_documentos d JOIN compras c ON c.id=d.compra_id WHERE c.sector_id=? ORDER BY d.id DESC');
$docsCompra->execute([$sid]);
$docsCompra = $docsCompra->fetchAll();

$encuestas = $pdo->prepare('SELECT e.*, c.codigo, u.nombre AS usuario_evaluador FROM compras_encuesta e JOIN compras c ON c.id=e.compra_id LEFT JOIN usuarios u ON u.id=e.creado_por WHERE c.sector_id=? ORDER BY e.id DESC');
$encuestas->execute([$sid]);
$encuestas = $encuestas->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión Integral de Compras | NASER SGI</title>
<link rel="stylesheet" href="<?=app_url('/style.css')?>">
<style>
:root{--ng:#08783e;--nd:#164c2d;--ns:#edf7f1;--nl:#dfe7e1;--warn:#d97706;--danger:#dc2626;--info:#2563eb}
.module-hero{position:relative;overflow:hidden;background:linear-gradient(125deg,#123f28,#08783e);color:#fff;border-radius:20px;padding:26px 28px;margin:0 0 20px;box-shadow:0 12px 30px rgba(20,70,40,.12)}
.module-hero .eyebrow{color:#d8f3e2;font-size:12px;font-weight:700;letter-spacing:1px}.module-hero h1{margin:4px 0 7px;font-size:30px}.module-hero p{margin:0;color:#e9f7ee}
.module-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}.module-tabs a{padding:9px 14px;border:1px solid var(--nl);border-radius:999px;background:#fff;color:var(--nd);font-size:13px;font-weight:800;text-decoration:none;transition:all .2s}
.module-tabs a:hover{background:var(--ns);border-color:var(--ng)}
.module-permission{padding:11px 14px;border:1px solid var(--nl);background:#f7faf8;border-radius:12px;margin-bottom:18px;font-size:13px}
.module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin:18px 0 24px}
.module-card{background:#fff;border:1px solid var(--nl);border-radius:17px;padding:19px;box-shadow:0 5px 18px rgba(31,41,55,.045)}
.module-card h3{margin:0 0 8px;font-size:15px;color:#374151}.module-card .big{font-size:30px;line-height:1;font-weight:900;color:var(--ng);margin-top:6px}
.module-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:25px 0 12px}.module-toolbar h2{margin:0;font-size:20px}
.module-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.module-form .full{grid-column:1/-1}.module-form label{display:flex;flex-direction:column;gap:7px;font-size:12px;font-weight:800;color:#374151}
.module-form input,.module-form select,.module-form textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d6dfd8;border-radius:10px;background:#fbfdfb;font-family:inherit}
.module-form textarea{min-height:80px;resize:vertical}
.table-wrapper{overflow:auto;border:1px solid var(--nl);border-radius:16px;background:#fff}.module-table{width:100%;border-collapse:collapse;min-width:850px}.module-table th,.module-table td{padding:12px 13px;border-bottom:1px solid #edf1ee;text-align:left;font-size:13px}.module-table th{background:#f1f7f3;color:#27583a;font-size:11px;text-transform:uppercase;letter-spacing:.5px}.module-table tbody tr:hover{background:#fbfdfb}
.module-actions{display:flex;gap:6px;flex-wrap:wrap}.module-note,.module-error{padding:13px 15px;border-radius:11px;margin:12px 0;font-weight:700;font-size:13px}.module-note{background:#eaf7ef;color:#176337;border:1px solid #b7e4c7}.module-error{background:#fff1f0;color:#9b231b;border:1px solid #fca5a5}
.flow{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin:15px 0 22px}
.flow-step{background:#f2f7f3;border:1px solid #dce8df;border-radius:12px;padding:12px 8px;text-align:center;font-size:11px;font-weight:800;color:#315a3e;transition:all .2s}
.flow-step b{display:block;font-size:16px;color:var(--ng);margin-bottom:3px}
.badge{padding:4px 8px;border-radius:6px;font-size:11px;font-weight:800;display:inline-block}
.badge-prio-normal{background:#e0f2fe;color:#0369a1}.badge-prio-urgente{background:#fef3c7;color:#b45309}.badge-prio-critico{background:#fee2e2;color:#b91c1c}
.badge-etapa{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
.badge-calidad{background:#dcfce7;color:#15803d;padding:3px 6px;border-radius:4px;font-weight:bold;font-size:11px}
.badge-calidad-obs{background:#fef3c7;color:#b45309}
.badge-calidad-bad{background:#fee2e2;color:#b91c1c}
@media(max-width:900px){.module-grid{grid-template-columns:1fr 1fr}.flow{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.module-grid,.module-form{grid-template-columns:1fr}.module-form .full{grid-column:auto}.flow{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="app">
<?php sidebar($pdo,$slug); ?>
<main class="content">

<section class="module-hero">
    <p class="eyebrow">SERVICIOS NASER SRL · COMPRAS Y SUMINISTROS</p>
    <h1>Gestión de Compras y Suministros</h1>
    <p>Ciclo integral: Pedidos, Presupuestos, Orden de Compra, Facturación, Logística y Evaluación.</p>
</section>

<div class="module-tabs">
    <a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación del Sector</a>
    <a href="#gestion">⚙ Modificar / Registrar Pedido</a>
    <a href="#seguimiento">📊 Seguimiento</a>
    <a href="#documentos">📎 Adjuntos por Etapa</a>
    <a href="#encuesta">📋 Encuesta de Recepción</a>
</div>

<div class="module-permission">
    🔐 <?php if($canEdit): ?>Modo edición: Tenés permisos para gestionar y subir documentación.<?php else: ?>Modo lectura: Solo consulta de estados y documentos.<?php endif; ?>
</div>

<div class="flow">
    <div class="flow-step"><b>1</b> Carga de Pedido</div>
    <div class="flow-step"><b>2</b> Solicitud Presupuesto</div>
    <div class="flow-step"><b>3</b> Orden de Compra</div>
    <div class="flow-step"><b>4</b> Facturación</div>
    <div class="flow-step"><b>5</b> Registro de Pago</div>
    <div class="flow-step"><b>6</b> Entrega y Recepción</div>
</div>

<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>

<section class="module-grid">
    <div class="module-card">
        <h3>Total Compras</h3>
        <div class="big"><?=count($rows)?></div>
    </div>
    <div class="module-card">
        <h3>En Cotización / OC</h3>
        <div class="big"><?=count(array_filter($rows,fn($r)=>(int)$r['etapa']>=1 && (int)$r['etapa']<=3))?></div>
    </div>
    <div class="module-card">
        <h3>Pendientes de Entrega</h3>
        <div class="big"><?=count(array_filter($rows,fn($r)=>(int)$r['etapa']>=4 && (int)$r['etapa']<6))?></div>
    </div>
    <div class="module-card">
        <h3>Completados</h3>
        <div class="big"><?=count(array_filter($rows,fn($r)=>(int)$r['etapa']===6))?></div>
    </div>
</section>

<?php if($canEdit):?>
<section class="module-card" id="gestion">
    <h3><?=$edit ? '✏ Editar Pedido de Compra: '.h($edit['codigo']) : '➕ Cargar Nuevo Pedido de Compra'?></h3>
    <form method="post" enctype="multipart/form-data" class="module-form">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
        
        <label class="full">Descripción del Producto / Servicio
            <textarea name="descripcion" placeholder="Detalle ítems, especificaciones técnicas o insumos requeridos..." required><?=h($edit['descripcion']??'')?></textarea>
        </label>

        <label>Cantidad
            <input type="number" min="1" name="cantidad" value="<?=h($edit['cantidad']??1)?>" required>
        </label>

        <label>Prioridad
            <select name="prioridad">
                <?php foreach(['Normal','Urgente','Critico'] as $p):?>
                    <option value="<?=$p?>" <?=($edit['prioridad']??'Normal')===$p?'selected':''?>><?=h($p)?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label>Proveedor Asignado / Sugerido
            <input name="proveedor" value="<?=h($edit['proveedor']??'Pendiente')?>" placeholder="Razón social o proveedor">
        </label>

        <label>Etapa Actual
            <select name="etapa">
                <?php foreach($etapaNombre as $num=>$nombre):?>
                    <option value="<?=$num?>" <?=((int)($edit['etapa']??1))===$num?'selected':''?>><?=h($nombre)?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label>Monto Estimado / Real
            <input type="number" step="0.01" name="monto" value="<?=h($edit['monto']??'0.00')?>">
        </label>

        <label>Moneda
            <select name="moneda">
                <option value="ARS" <?=($edit['moneda']??'ARS')==='ARS'?'selected':''?>>ARS ($)</option>
                <option value="USD" <?=($edit['moneda']??'ARS')==='USD'?'selected':''?>>USD ($)</option>
            </select>
        </label>

        <label class="full">Estado Logístico / Seguimiento
            <input name="estado_logistico" value="<?=h($edit['estado_logistico']??'Pedido cargado')?>" placeholder="Ej: En preparación, Despachado, En depósito...">
        </label>

        <label class="full">Adjuntar Archivo Inicial (PDF / Presupuesto)
            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
        </label>

        <div class="full">
            <button class="btn primary"><?=$edit?'Actualizar Pedido':'Crear Pedido'?></button>
            <?php if($edit):?> 
                <a class="btn secondary" href="<?=app_url('/php/compras.php')?>">Cancelar</a>
            <?php endif;?>
        </div>
    </form>
</section>
<?php endif;?>

<div class="module-toolbar" id="seguimiento">
    <h2>Seguimiento Logístico y de Compras</h2>
    <span class="count-pill"><?=count($rows)?> Registro(s)</span>
</div>

<div class="table-wrapper">
    <table class="module-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción / Detalles</th>
                <th>Prioridad</th>
                <th>Proveedor</th>
                <th>Monto</th>
                <th>Etapa</th>
                <th>Estado Logístico</th>
                <th>Últ. Actualización</th>
                <?php if($canEdit):?><th>Acciones</th><?php endif;?>
            </tr>
        </thead>
        <tbody>
        <?php if(!$rows):?>
            <tr><td colspan="<?=$canEdit?9:8?>" style="text-align:center;padding:20px;color:#6b7280">No hay pedidos registrados en el sistema.</td></tr>
        <?php endif;?>
        <?php foreach($rows as $r):?>
            <tr>
                <td><strong><?=h($r['codigo'])?></strong></td>
                <td>
                    <?=h($r['descripcion'])?><br>
                    <small style="color:#6b7280">Cant: <b><?=(int)$r['cantidad']?></b> | 📎 <?=(int)$r['total_docs']?> documento(s)</small>
                </td>
                <td>
                    <?php 
                        $pClass = $r['prioridad']==='Critico'?'badge-prio-critico':($r['prioridad']==='Urgente'?'badge-prio-urgente':'badge-prio-normal'); 
                    ?>
                    <span class="badge <?=$pClass?>"><?=h($r['prioridad'])?></span>
                </td>
                <td><?=h($r['proveedor'])?></td>
                <td><strong><?=$r['moneda']==='USD'?'US$':'$'?> <?=number_format((float)($r['monto']??0),2,',','.')?></strong></td>
                <td><span class="badge badge-etapa"><?=(int)$r['etapa']?>/6 - <?=h($etapaNombre[(int)$r['etapa']]??'')?></span></td>
                <td><strong style="color:var(--ng)"><?=h($r['estado_logistico'])?></strong></td>
                <td><?=h($r['actualizado_nombre']??'Sistema')?><br><small style="color:#9ca3af"><?=date('d/m/Y H:i',strtotime($r['actualizado_en']))?></small></td>
                <?php if($canEdit):?>
                    <td class="module-actions">
                        <a class="btn secondary" href="?editar=<?=(int)$r['id']?>">Editar</a>
                        <form method="post" onsubmit="return confirm('¿Confirma eliminar este pedido de compra?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                            <button class="btn secondary" style="color:var(--danger)">Eliminar</button>
                        </form>
                    </td>
                <?php endif;?>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

<?php if($canEdit && $rows):?>
<section class="module-card" style="margin-top:25px" id="documentos">
    <h3>📎 Gestor de Documentación y Adjuntos por Etapa</h3>
    <p>Subí archivos en PDF, Excel o Imagen para respaldar el flujo (Presupuestos, Facturas, Comprobantes de Pago, Remitos de Entrega).</p>
    <form method="post" enctype="multipart/form-data" class="module-form">
        <input type="hidden" name="accion" value="documento_etapa">
        
        <label>Seleccionar Compra
            <select name="compra_id" required>
                <?php foreach($rows as $r):?>
                    <option value="<?=(int)$r['id']?>"><?=h($r['codigo'].' - '.$r['descripcion'])?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label>Etapa Correspondiente
            <select name="etapa_doc" required>
                <?php foreach($etapaNombre as $n=>$et):?>
                    <option value="<?=$n?>"><?=h($et)?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label class="full">Tipo de Documento
            <input name="tipo_documento" placeholder="Ej: Solicitud Presupuesto, Orden de Compra #123, Factura A, Remito" required>
        </label>

        <label class="full">Archivo (PDF / Imagen / Office)
            <input type="file" name="archivo_etapa" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" required>
        </label>

        <div class="full">
            <button class="btn primary">Subir Documento y Avanzar Etapa</button>
        </div>
    </form>
</section>
<?php endif;?>

<div class="module-toolbar">
    <h2>Repositorio de Documentos Adjuntos</h2>
    <span class="count-pill"><?=count($docsCompra)?> Archivo(s)</span>
</div>
<div class="table-wrapper">
    <table class="module-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Etapa</th>
                <th>Tipo / Descripción</th>
                <th>Archivo</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!$docsCompra):?>
            <tr><td colspan="4" style="text-align:center;padding:15px;color:#6b7280">No hay documentos adjuntos.</td></tr>
        <?php endif;?>
        <?php foreach($docsCompra as $d):?>
            <tr>
                <td><strong><?=h($d['codigo'])?></strong></td>
                <td><?=h($etapaNombre[(int)$d['etapa']]??('Etapa '.$d['etapa']))?></td>
                <td><?=h($d['tipo_documento']??'Adjunto')?></td>
                <td>
                    <a href="<?=app_url('/uploads/'.h($d['ruta_archivo']))?>" target="_blank" style="color:var(--ng);font-weight:bold;text-decoration:underline">
                        📄 <?=h($d['nombre_archivo'])?>
                    </a>
                </td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

<?php if($canEdit && $rows):?>
<section class="module-card" style="margin-top:25px" id="encuesta">
    <h3>📋 Encuesta de Control de Calidad y Recepción de Producto</h3>
    <p>Completa este formulario una vez recibido el producto o servicio para evaluar al proveedor y cerrar el ciclo.</p>
    <form method="post" class="module-form">
        <input type="hidden" name="accion" value="encuesta">
        
        <label class="full">Compra A Evaluar
            <select name="compra_id" required>
                <?php foreach($rows as $r):?>
                    <option value="<?=(int)$r['id']?>"><?=h($r['codigo'].' - '.$r['descripcion'].' ('.$r['proveedor'].')')?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label>Calidad del Producto / Servicio
            <select name="calidad">
                <option value="Conforme">Conforme (Cumple especificaciones)</option>
                <option value="Observada">Observada (Detalles menores)</option>
                <option value="No Conforme">No Conforme (Rechazado)</option>
            </select>
        </label>

        <label>Estado Físico del Envío
            <select name="estado_fisico">
                <option value="Bueno">Bueno / Excelente</option>
                <option value="Regular">Regular / Embalaje Dañado</option>
                <option value="Malo">Malo / Insumo Con Faltantes o Roturas</option>
            </select>
        </label>

        <label class="full">Cumplimiento del Plazo de Entrega
            <select name="entrega">
                <option value="En término">En término (Dentro del plazo acordado)</option>
                <option value="Demorada">Demorada (Entregado fuera de fecha)</option>
                <option value="No Entregado">No Entregado</option>
            </select>
        </label>

        <label class="full">Observaciones Finales / Comentarios de Recepción
            <textarea name="observaciones" placeholder="Escribe detalles adicionales sobre el estado de la entrega..."></textarea>
        </label>

        <div class="full">
            <button class="btn primary">Registrar Evaluación y Finalizar Compra</button>
        </div>
    </form>
</section>
<?php endif;?>

<?php if($encuestas):?>
<div class="module-toolbar">
    <h2>Historial de Recepciones y Evaluaciones de Calidad</h2>
</div>
<div class="table-wrapper">
    <table class="module-table">
        <thead>
            <tr>
                <th>Compra</th>
                <th>Calidad</th>
                <th>Estado Físico</th>
                <th>Cumplimiento</th>
                <th>Observaciones</th>
                <th>Registrado Por</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($encuestas as $e):?>
            <tr>
                <td><strong><?=h($e['codigo'])?></strong></td>
                <td>
                    <?php 
                        $qClass = $e['calidad']==='Conforme'?'badge-calidad':($e['calidad']==='Observada'?'badge-calidad-obs':'badge-calidad-bad'); 
                    ?>
                    <span class="<?=$qClass?>"><?=h($e['calidad'])?></span>
                </td>
                <td><?=h($e['estado_fisico'])?></td>
                <td><?=h($e['cumplimiento_entrega'])?></td>
                <td><?=h($e['observaciones']?:'Sin observaciones')?></td>
                <td><?=h($e['usuario_evaluador']??'Sistema')?></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>
<?php endif;?>

</main>
</div>
</body>
</html>