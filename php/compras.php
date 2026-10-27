<?php
require __DIR__ . '/config/auth.php';
requireLogin();
verify_csrf(); // protege todos los formularios POST de esta pagina
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require __DIR__ . '/config/modulo.php';
require_once __DIR__ . '/config/formularios_catalogo.php';

$slug = 'compras';
[$sector, $sid, $canEdit] = cargarModulo($pdo, $slug);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg = ''; $err = '';

// Las tablas de Compras se crean en config/schema_modulos.php



$etapaNombre = [
    1 => '1. Pedido (documento)',
    2 => '2. Solicitud de Presupuesto',
    3 => '3. Orden de Compra',
    4 => '4. Factura',
    5 => '5. Pago',
    6 => '6. Entrega / Recepción'
];
$tiposDocumentoPorEtapa = [
    1 => ['Pedido de compra'],
    2 => ['Solicitud de presupuesto', 'Cotización recibida'],
    3 => ['Orden de compra'],
    4 => ['Factura'],
    5 => ['Comprobante de pago'],
    6 => ['Remito / comprobante de entrega']
];
$documentoPrincipalPorEtapa = [
    1 => 'Pedido de compra',
    2 => 'Solicitud de presupuesto',
    3 => 'Orden de compra',
    4 => 'Factura',
    5 => 'Comprobante de pago',
    6 => 'Remito / comprobante de entrega'
];
$estadosLogisticos = [
    'Pedido cargado', 'Presupuesto solicitado', 'Esperando presupuesto', 'Presupuesto recibido',
    'Orden de compra emitida', 'Factura recibida', 'Pago registrado',
    'En preparación', 'Despachado', 'En tránsito', 'Entrega pendiente de evaluación',
    'Con demora de entrega', 'Incidencia logística'
];
$estadosCompraFinales = ['Recibido y evaluado', 'Recibido con observaciones', 'Recibido con no conformidad'];
$estadoPorEtapa = [
    1 => 'Pedido cargado',
    2 => 'Presupuesto solicitado',
    3 => 'Orden de compra emitida',
    4 => 'Factura recibida',
    5 => 'Pago registrado',
    6 => 'Entrega pendiente de evaluación'
];

function guardarDocumentoCompra(PDO $pdo, int $compraId, int $etapa, string $tipo, int $usuarioId, string $uploadDir, array $archivo): int
{
    $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El archivo supera el tamaño máximo permitido de 10 MB.');
        throw new RuntimeException('Seleccioná un archivo válido para adjuntar.');
    }
    $tmp = (string)($archivo['tmp_name'] ?? '');
    $size = (int)($archivo['size'] ?? 0);
    if ($size < 1 || $size > 10 * 1024 * 1024) throw new RuntimeException('El archivo debe pesar menos de 10 MB.');

    $nombreOriginal = str_replace('\\', '/', (string)($archivo['name'] ?? ''));
    $nombreOriginal = basename($nombreOriginal);
    $nombreOriginal = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $nombreOriginal) ?: 'documento';
    $nombreOriginal = mb_substr($nombreOriginal, 0, 255);
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $mimesPorExtension = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']
    ];
    if (!isset($mimesPorExtension[$ext])) throw new RuntimeException('Formato no permitido. Adjuntá PDF, Word, Excel o imagen JPG/PNG.');
    if (function_exists('finfo_open') && is_file($tmp)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmp) : false;
        if ($finfo) finfo_close($finfo);
        if ($mime && !in_array($mime, $mimesPorExtension[$ext], true)) throw new RuntimeException('El contenido del archivo no coincide con su extensión.');
    }

    $seguro = 'compra-' . $compraId . '-etapa-' . $etapa . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $uploadDir . '/' . $seguro)) throw new RuntimeException('No se pudo guardar el archivo adjunto.');
    try {
        $st = $pdo->prepare('INSERT INTO compras_documentos(compra_id, etapa, nombre_archivo, ruta_archivo, tipo_documento, creado_por) VALUES(?,?,?,?,?,?)');
        $st->execute([$compraId, $etapa, $nombreOriginal, 'compras/' . $seguro, $tipo, $usuarioId]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($uploadDir . '/' . $seguro);
        throw $e;
    }
}

function notificarCompra(PDO $pdo, int $sectorId, int $creadorId, int $actorId, string $codigo, int $compraId, string $titulo, string $mensaje, string $evento): void
{
    if (!function_exists('notificar')) return;
    try {
        $responsables = array_column(usuariosConRol($pdo, $sectorId, 'responsable'), 'id');
        $destinatarios = array_values(array_unique(array_map('intval', array_merge([$creadorId, $actorId], $responsables))));
        $clave = 'compra:' . $compraId . ':' . $evento . ':' . bin2hex(random_bytes(6));
        notificar($pdo, $destinatarios, $titulo, $mensaje, '/php/compras.php#seguimiento', 'compra', $clave);
    } catch (Throwable $e) {
        error_log('[NASER] Notificación de compra ' . $codigo . ': ' . $e->getMessage());
    }
}

$uploadDir = dirname(__DIR__) . '/uploads/compras';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

// --- PROCESAMIENTO DE FORMULARIOS DE COMPRAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirEdicion($canEdit);
    try {
        $a = $_POST['accion'] ?? '';

        if ($a === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $desc = trim((string)($_POST['descripcion'] ?? ''));
            $cant = (int)($_POST['cantidad'] ?? 1);
            $prio = (string)($_POST['prioridad'] ?? 'Normal');
            $prov = trim((string)($_POST['proveedor'] ?? 'Pendiente')) ?: 'Pendiente';
            $monto = (float)($_POST['monto'] ?? 0);
            $moneda = (string)($_POST['moneda'] ?? 'ARS');
            if ($desc === '' || mb_strlen($desc) > 10000) throw new RuntimeException('Ingresá una descripción válida para el pedido.');
            if ($cant < 1 || !in_array($prio, ['Normal', 'Urgente', 'Critico'], true)) throw new RuntimeException('Revisá la cantidad y prioridad del pedido.');
            if ($monto < 0 || !in_array($moneda, ['ARS', 'USD'], true)) throw new RuntimeException('Revisá el monto y la moneda.');

            if ($id) {
                $st = $pdo->prepare('SELECT codigo, etapa, estado_logistico, creado_por FROM compras WHERE id=? AND sector_id=?');
                $st->execute([$id, $sid]);
                $anterior = $st->fetch();
                if (!$anterior) throw new RuntimeException('Pedido de compra inválido.');
                $estado = trim((string)($_POST['estado_logistico'] ?? $anterior['estado_logistico']));
                if (in_array($anterior['estado_logistico'], $estadosCompraFinales, true) && $estado !== $anterior['estado_logistico']) throw new RuntimeException('La recepción evaluada cierra el ciclo de compra y no se puede reabrir desde este formulario.');
                if (!in_array($estado, $estadosLogisticos, true) && $estado !== $anterior['estado_logistico']) throw new RuntimeException('Seleccioná un estado logístico válido.');
                $pdo->prepare('UPDATE compras SET descripcion=?, cantidad=?, prioridad=?, proveedor=?, monto=?, moneda=?, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                    ->execute([$desc, $cant, $prio, mb_substr($prov, 0, 150), $monto, $moneda, $estado, $uid, $id, $sid]);
                $compraId = $id;
                $codigo = (string)$anterior['codigo'];
                $creadorId = (int)$anterior['creado_por'];
                if ($estado !== $anterior['estado_logistico']) {
                    notificarCompra($pdo, $sid, $creadorId, $uid, $codigo, $id, 'Actualización de compra ' . $codigo, 'El estado logístico cambió a: ' . $estado . '.', 'estado');
                }
                $msg = 'Pedido actualizado con éxito. La etapa documental se avanza desde los adjuntos.';
            } else {
                $anio = date('Y');
                $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED)) FROM compras WHERE codigo LIKE ?");
                $st->execute(['CMP-' . $anio . '-%']);
                $secuencia = (int)$st->fetchColumn() + 1;
                do {
                    $codigo = 'CMP-' . $anio . '-' . str_pad((string)$secuencia++, 4, '0', STR_PAD_LEFT);
                    $st = $pdo->prepare('SELECT COUNT(*) FROM compras WHERE codigo=?');
                    $st->execute([$codigo]);
                } while ((int)$st->fetchColumn() > 0);
                $estado = 'Pedido cargado';
                $pdo->prepare('INSERT INTO compras(sector_id, codigo, sector, descripcion, cantidad, prioridad, proveedor, monto, moneda, etapa, estado_logistico, creado_por, actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,1,?,?,?)')
                    ->execute([$sid, $codigo, $sector['nombre'], $desc, $cant, $prio, mb_substr($prov, 0, 150), $monto, $moneda, $estado, $uid, $uid]);
                $compraId = (int)$pdo->lastInsertId();
                $creadorId = $uid;
                $msg = "Pedido $codigo creado correctamente.";
            }

            if (!empty($_FILES['archivo']['name']) || (int)($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    guardarDocumentoCompra($pdo, $compraId, 1, 'Pedido de compra', $uid, $uploadDir, $_FILES['archivo']);
                } catch (Throwable $errorArchivo) {
                    if (!$id) $pdo->prepare('DELETE FROM compras WHERE id=? AND sector_id=?')->execute([$compraId, $sid]);
                    throw $errorArchivo;
                }
            }
            auditModulo($pdo, $uid, 'compras_guardar', "Compra #$compraId");
            if (!$id) notificarCompra($pdo, $sid, $creadorId, $uid, $codigo, $compraId, 'Nuevo pedido de compra ' . $codigo, 'Se cargó un pedido de compra: ' . $desc . '.', 'creada');
        }

        if ($a === 'documento_etapa') {
            $id = (int)($_POST['compra_id'] ?? 0);
            $et = (int)($_POST['etapa_doc'] ?? 0);
            $tipoDoc = trim((string)($_POST['tipo_documento'] ?? ''));
            if (!isset($etapaNombre[$et]) || !in_array($tipoDoc, $tiposDocumentoPorEtapa[$et], true)) throw new RuntimeException('Seleccioná una etapa y tipo de documento válidos.');

            $st = $pdo->prepare('SELECT id, codigo, etapa, estado_logistico, creado_por FROM compras WHERE id=? AND sector_id=?');
            $st->execute([$id, $sid]);
            $compra = $st->fetch();
            if (!$compra) throw new RuntimeException('Pedido inválido.');
            $etapaActual = (int)$compra['etapa'];
            if ($et > $etapaActual + 1) throw new RuntimeException('El ciclo se completa en orden. Adjuntá primero el documento de la etapa pendiente.');
            if ($et === $etapaActual + 1) {
                if ($tipoDoc !== $documentoPrincipalPorEtapa[$et]) throw new RuntimeException('Para avanzar, adjuntá el documento principal requerido: ' . $documentoPrincipalPorEtapa[$et] . '.');
                $st = $pdo->prepare('SELECT COUNT(*) FROM compras_documentos WHERE compra_id=? AND etapa=?');
                $st->execute([$id, $etapaActual]);
                if ((int)$st->fetchColumn() === 0) throw new RuntimeException('Adjuntá el documento de la etapa actual antes de avanzar.');
            }

            guardarDocumentoCompra($pdo, $id, $et, $tipoDoc, $uid, $uploadDir, $_FILES['archivo_etapa'] ?? []);
            $avanza = $et === $etapaActual + 1;
            if ($avanza) {
                $estado = $estadoPorEtapa[$et];
                $pdo->prepare('UPDATE compras SET etapa=?, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                    ->execute([$et, $estado, $uid, $id, $sid]);
                $msg = 'Documento subido. La compra avanzó a: ' . $etapaNombre[$et] . '.';
            } else {
                $estado = (string)$compra['estado_logistico'];
                if ($etapaActual === 2 && $et === 2 && $tipoDoc === 'Cotización recibida') {
                    $estado = 'Presupuesto recibido';
                    $pdo->prepare('UPDATE compras SET estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$estado, $uid, $id, $sid]);
                } else {
                    $pdo->prepare('UPDATE compras SET actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$uid, $id, $sid]);
                }
                $msg = 'Documento agregado a la compra.';
            }
            auditModulo($pdo, $uid, 'compras_documento', "Documento $tipoDoc en compra #$id, etapa $et");
            notificarCompra($pdo, $sid, (int)$compra['creado_por'], $uid, (string)$compra['codigo'], $id, 'Documento agregado a compra ' . $compra['codigo'], $tipoDoc . ' cargado en ' . $etapaNombre[$et] . '. Estado logístico: ' . $estado . '.', 'documento');
        }

        if ($a === 'encuesta') {
            $id = (int)($_POST['compra_id'] ?? 0);
            $st = $pdo->prepare('SELECT id, codigo, etapa, creado_por FROM compras WHERE id=? AND sector_id=?');
            $st->execute([$id, $sid]);
            $compra = $st->fetch();
            if (!$compra) throw new RuntimeException('Pedido de compra inválido.');
            if ((int)$compra['etapa'] < 6) throw new RuntimeException('La recepción se registra después de completar los cinco pasos anteriores y adjuntar el documento de entrega.');
            $st = $pdo->prepare('SELECT COUNT(*) FROM compras_encuesta WHERE compra_id=?');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) throw new RuntimeException('Esta compra ya tiene una evaluación de recepción registrada.');

            $calidad = (string)($_POST['calidad'] ?? '');
            $estadoFisico = (string)($_POST['estado_fisico'] ?? '');
            $entrega = (string)($_POST['entrega'] ?? '');
            $modalidad = (string)($_POST['modalidad_entrega'] ?? '');
            $obs = trim((string)($_POST['observaciones'] ?? ''));
            if (!in_array($calidad, ['Conforme', 'Observada', 'No Conforme'], true)
                || !in_array($estadoFisico, ['Bueno', 'Regular', 'Malo'], true)
                || !in_array($entrega, ['En término', 'Demorada'], true)
                || !in_array($modalidad, ['Transporte del proveedor', 'Retiro por NASER', 'Correo / transporte contratado', 'Entrega directa en base / depósito', 'Otro'], true)) {
                throw new RuntimeException('Completá todas las respuestas de recepción con una opción válida.');
            }
            if (mb_strlen($obs) > 5000) throw new RuntimeException('Las observaciones no pueden superar los 5.000 caracteres.');

            $pdo->prepare('INSERT INTO compras_encuesta(compra_id, calidad, estado_fisico, cumplimiento_entrega, modalidad_entrega, observaciones, creado_por) VALUES(?,?,?,?,?,?,?)')
                ->execute([$id, $calidad, $estadoFisico, $entrega, $modalidad, $obs, $uid]);
            $estadoFinal = ($calidad === 'No Conforme' || $estadoFisico === 'Malo') ? 'Recibido con no conformidad'
                : (($calidad === 'Observada' || $estadoFisico === 'Regular' || $entrega === 'Demorada') ? 'Recibido con observaciones' : 'Recibido y evaluado');
            $pdo->prepare('UPDATE compras SET etapa=6, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                ->execute([$estadoFinal, $uid, $id, $sid]);
            auditModulo($pdo, $uid, 'compras_encuesta', "Recepción registrada para Compra #$id");
            notificarCompra($pdo, $sid, (int)$compra['creado_por'], $uid, (string)$compra['codigo'], $id, 'Recepción registrada para compra ' . $compra['codigo'], 'Estado final: ' . $estadoFinal . '. Modalidad de entrega: ' . $modalidad . '.', 'recepcion');
            $msg = 'Recepción y evaluación registradas. Estado final: ' . $estadoFinal . '.';
        }

        if ($a === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Seleccioná un pedido de compra válido.');

            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT id FROM compras WHERE id=? AND sector_id=? FOR UPDATE');
                $st->execute([$id, $sid]);
                if (!$st->fetchColumn()) throw new RuntimeException('Pedido de compra inválido.');

                $st = $pdo->prepare('SELECT ruta_archivo FROM compras_documentos WHERE compra_id=?');
                $st->execute([$id]);
                $rutasDocumentos = $st->fetchAll(PDO::FETCH_COLUMN);

                $pdo->prepare('DELETE FROM compras_documentos WHERE compra_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM compras_encuesta WHERE compra_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM compras WHERE id=? AND sector_id=?')->execute([$id, $sid]);
                $pdo->commit();
            } catch (Throwable $errorEliminar) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $errorEliminar;
            }

            // Los archivos se eliminan después de confirmar la transacción para no
            // perderlos si la base de datos rechaza el borrado.
            foreach ($rutasDocumentos as $rutaDocumento) {
                $rutaDocumento = str_replace('\\', '/', (string)$rutaDocumento);
                if (!preg_match('#^compras/[^/]+$#', $rutaDocumento)) continue;
                $archivoDocumento = $uploadDir . DIRECTORY_SEPARATOR . basename($rutaDocumento);
                if (is_file($archivoDocumento)) @unlink($archivoDocumento);
            }
            auditModulo($pdo, $uid, 'compras_eliminar', "Compra #$id");
            $msg = 'Registro de compra eliminado.';
        }
    } catch (Throwable $e) {
        $err = mensaje_error($e);
    }
}

// Carga de Datos
$edit = null;
if ($canEdit && !empty($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM compras WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar'], $sid]);
    $edit = $st->fetch();
}

$st = $pdo->prepare('SELECT c.*, u.nombre AS actualizado_nombre,
    (SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id) AS total_docs,
    (SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id AND d.etapa=c.etapa) AS docs_etapa_actual
    FROM compras c LEFT JOIN usuarios u ON u.id=c.actualizado_por WHERE c.sector_id=? ORDER BY c.fecha_creacion DESC');
$st->execute([$sid]);
$rows = $st->fetchAll();
$presupuestoCompraId = (int)($_GET['presupuesto'] ?? 0);
$presupuestoCompraCodigo = '';
foreach ($rows as $rowCompra) {
    if ((int)$rowCompra['id'] === $presupuestoCompraId && (int)$rowCompra['etapa'] >= 2) {
        $presupuestoCompraCodigo = (string)$rowCompra['codigo'];
        break;
    }
}
if ($presupuestoCompraCodigo === '') $presupuestoCompraId = 0;

$docsCompra = $pdo->prepare('SELECT d.*, c.codigo, u.nombre AS subido_por FROM compras_documentos d JOIN compras c ON c.id=d.compra_id LEFT JOIN usuarios u ON u.id=d.creado_por WHERE c.sector_id=? ORDER BY d.id DESC');
$docsCompra->execute([$sid]);
$docsCompra = $docsCompra->fetchAll();
$docsPorEtapa = [];
foreach ($docsCompra as $d) { $docsPorEtapa[(int)$d['etapa']][] = $d; }
$pasosFlujo = [1 => 'Carga de Pedido', 2 => 'Solicitud Presupuesto', 3 => 'Orden de Compra', 4 => 'Facturación', 5 => 'Registro de Pago', 6 => 'Entrega y Recepción'];

$campoFechaEncuesta = hasColumn($pdo, 'compras_encuesta', 'fecha_inspeccion')
    ? 'COALESCE(e.fecha_inspeccion, e.fecha_registro)'
    : 'e.fecha_registro';
$encuestas = $pdo->prepare("SELECT e.*, $campoFechaEncuesta AS fecha_evaluacion, c.codigo, u.nombre AS usuario_evaluador FROM compras_encuesta e JOIN compras c ON c.id=e.compra_id LEFT JOIN usuarios u ON u.id=e.creado_por WHERE c.sector_id=? ORDER BY e.id DESC");
$encuestas->execute([$sid]);
$encuestas = $encuestas->fetchAll();
$encuestadasIds = array_fill_keys(array_map(static fn($e) => (int)$e['compra_id'], $encuestas), true);
$recepcionesPendientes = array_values(array_filter($rows, static fn($r) => (int)$r['etapa'] >= 6 && !isset($encuestadasIds[(int)$r['id']])));

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión Integral de Compras | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>">
<link rel="stylesheet" href="<?=asset('/css/checklists.css')?>">
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
button.flow-step{font-family:inherit;cursor:pointer;line-height:1.25}
button.flow-step:hover,button.flow-step:focus-visible{background:var(--ns);border-color:var(--ng);box-shadow:0 4px 12px rgba(8,120,62,.15);transform:translateY(-1px);outline:none}
button.flow-step small{display:block;margin-top:5px;font-size:10px;font-weight:700;color:var(--ng)}
.etapa-dialog{border:0;border-radius:18px;padding:22px 24px;width:min(680px,94vw);max-height:90vh;box-shadow:0 20px 50px rgba(0,0,0,.28)}
.etapa-dialog::backdrop{background:rgba(15,40,25,.55)}
.etapa-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}.etapa-head h3{margin:0;font-size:19px;color:var(--nd)}
.etapa-dialog h4{margin:20px 0 8px;font-size:14px;color:#374151}
.etapa-dialog .module-table{min-width:0}

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
    <a href="#formularios">📋 Formularios / Checklists</a>
    <a href="#gestion">⚙ Modificar / Registrar Pedido</a>
    <a href="#seguimiento">📊 Seguimiento</a>
    <a href="#documentos">📎 Adjuntos por Etapa</a>
    <a href="#encuesta">📋 Encuesta de Recepción</a>
</div>

<div class="module-permission">
    🔐 <?php if($canEdit): ?>Modo edición: Tenés permisos para gestionar y subir documentación.<?php else: ?>Modo lectura: Solo consulta de estados y documentos.<?php endif; ?>
</div>

<div class="flow" id="documentos">
<?php foreach($pasosFlujo as $n => $titulo):?>
    <button type="button" class="flow-step" data-etapa="<?=$n?>"><b><?=$n?></b> <?=h($titulo)?><small><?=$canEdit ? '📎 Subir documento' : '📄 Ver documentos'?></small></button>
<?php endforeach;?>
</div>

<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>


<!-- SECCIÓN: FORMULARIOS DIGITALES (se guardan en la base de datos) -->
<?php formulariosPanel($pdo, 'compras'); ?>

<?php if($canEdit):?>
<section class="module-card" id="gestion">
    <h3><?=$edit ? '✏ Editar Pedido de Compra: '.h($edit['codigo']) : '➕ Cargar Nuevo Pedido de Compra'?></h3>
    <form method="post" enctype="multipart/form-data" class="module-form"><?=csrf_field()?>
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

        <?php if($edit):?>
        <label>Etapa documental
            <input value="<?=h($etapaNombre[(int)$edit['etapa']]??'Pedido cargado')?>" readonly>
        </label>
        <?php else:?>
        <label>Etapa documental
            <input value="1. Pedido (documento)" readonly>
        </label>
        <?php endif;?>

        <label>Monto Estimado / Real
            <input type="number" step="0.01" name="monto" value="<?=h($edit['monto']??'0.00')?>">
        </label>

        <label>Moneda
            <select name="moneda">
                <option value="ARS" <?=($edit['moneda']??'ARS')==='ARS'?'selected':''?>>ARS ($)</option>
                <option value="USD" <?=($edit['moneda']??'ARS')==='USD'?'selected':''?>>USD ($)</option>
            </select>
        </label>

        <?php if($edit):?>
        <label class="full">Estado logístico
            <?php if(in_array($edit['estado_logistico'], $estadosCompraFinales, true)):?>
            <input value="<?=h($edit['estado_logistico'])?>" readonly>
            <?php else:?>
            <select name="estado_logistico">
                <?php if(!in_array($edit['estado_logistico'], $estadosLogisticos, true)):?><option value="<?=h($edit['estado_logistico'])?>" selected><?=h($edit['estado_logistico'])?> (actual)</option><?php endif;?>
                <?php foreach($estadosLogisticos as $estado):?><option value="<?=h($estado)?>" <?=($edit['estado_logistico']??'')===$estado?'selected':''?>><?=h($estado)?></option><?php endforeach;?>
            </select>
            <?php endif;?>
        </label>
        <?php else:?>
        <label class="full">Estado logístico inicial
            <input value="Pedido cargado" readonly>
        </label>
        <?php endif;?>

        <label class="full">Adjuntar Pedido de compra (opcional · máximo 10 MB)
            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
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
                        <?php if((int)$r['etapa'] >= 2):?>
                            <a class="btn secondary" href="<?=app_url('/php/compras.php?presupuesto='.(int)$r['id'].'#documentos')?>">Adjuntar presupuesto</a>
                        <?php endif;?>
                        <a class="btn secondary" href="?editar=<?=(int)$r['id']?>">Editar</a>
                        <form method="post" onsubmit="return confirm('¿Confirma eliminar este pedido de compra?')"><?=csrf_field()?>
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
                <th>Fecha / Responsable</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!$docsCompra):?>
            <tr><td colspan="5" style="text-align:center;padding:15px;color:#6b7280">No hay documentos adjuntos.</td></tr>
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
                <td><?=h($d['subido_por']??'Sistema')?><br><small style="color:#9ca3af"><?=h(date('d/m/Y H:i', strtotime($d['fecha_subida'])))?></small></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

<?php if($canEdit && $rows):?>
<section class="module-card" style="margin-top:25px" id="encuesta">
    <h3>📋 Encuesta de Control de Calidad y Recepción de Producto</h3>
    <p>Al adjuntar el remito en la etapa 6, registrá cómo llegó el producto, su calidad y estado. Cada compra admite una evaluación de recepción.</p>
    <?php if(!$recepcionesPendientes):?><p class="module-permission">No hay entregas pendientes de evaluación. La compra debe llegar a la etapa 6 y tener su remito adjunto.</p><?php else:?>
    <form method="post" class="module-form"><?=csrf_field()?>
        <input type="hidden" name="accion" value="encuesta">
        
        <label class="full">Compra A Evaluar
            <select name="compra_id" required>
                <?php foreach($recepcionesPendientes as $r):?>
                    <option value="<?=(int)$r['id']?>"><?=h($r['codigo'].' - '.$r['descripcion'].' ('.$r['proveedor'].')')?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label>Calidad del Producto / Servicio
            <select name="calidad" required>
                <option value="" selected disabled>Seleccionar calidad</option>
                <option value="Conforme">Conforme (Cumple especificaciones)</option>
                <option value="Observada">Observada (Detalles menores)</option>
                <option value="No Conforme">No Conforme (Rechazado)</option>
            </select>
        </label>

        <label>Estado Físico del Envío
            <select name="estado_fisico" required>
                <option value="" selected disabled>Seleccionar estado</option>
                <option value="Bueno">Bueno / Excelente</option>
                <option value="Regular">Regular / Embalaje Dañado</option>
                <option value="Malo">Malo / Insumo Con Faltantes o Roturas</option>
            </select>
        </label>

        <label class="full">¿Cómo se entregó el producto?
            <select name="modalidad_entrega" required>
                <option value="" selected disabled>Seleccionar modalidad</option>
                <option>Transporte del proveedor</option>
                <option>Retiro por NASER</option>
                <option>Correo / transporte contratado</option>
                <option>Entrega directa en base / depósito</option>
                <option>Otro</option>
            </select>
        </label>

        <label class="full">Cumplimiento del Plazo de Entrega
            <select name="entrega" required>
                <option value="" selected disabled>Seleccionar cumplimiento</option>
                <option value="En término">En término (Dentro del plazo acordado)</option>
                <option value="Demorada">Demorada (Entregado fuera de fecha)</option>
            </select>
        </label>

        <label class="full">Observaciones Finales / Comentarios de Recepción
            <textarea name="observaciones" maxlength="5000" placeholder="Indicá cómo llegó el producto: embalaje, transporte, faltantes, daños u otras observaciones..."></textarea>
        </label>

        <div class="full">
            <button class="btn primary">Registrar recepción y evaluación</button>
        </div>
    </form>
    <?php endif;?>
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
                <th>Modalidad de entrega</th>
                <th>Cumplimiento</th>
                <th>Observaciones</th>
                <th>Registrado Por</th>
                <th>Fecha</th>
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
                <td><?=h($e['modalidad_entrega']??'—')?></td>
                <td><?=h($e['cumplimiento_entrega'])?></td>
                <td><?=h($e['observaciones']?:'Sin observaciones')?></td>
                <td><?=h($e['usuario_evaluador']??'Sistema')?></td>
                <td><?=h(date('d/m/Y H:i', strtotime($e['fecha_evaluacion'] ?? 'now')))?></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>
<?php endif;?>

</main>
</div>

<dialog id="dlgEtapa" class="etapa-dialog">
    <div class="etapa-head">
        <h3 id="dlgTitulo">Paso</h3>
        <button type="button" class="btn secondary" id="dlgCerrar" aria-label="Cerrar">✕</button>
    </div>

    <?php if($canEdit):?>
    <form method="post" enctype="multipart/form-data" class="module-form" id="dlgForm"><?=csrf_field()?>
        <input type="hidden" name="accion" value="documento_etapa">
        <input type="hidden" name="etapa_doc" id="dlgEtapaInput" value="1">

        <label class="full">Pedido de compra
            <select name="compra_id" id="dlgCompra" required>
                <?php foreach($rows as $r):?>
                    <option value="<?=(int)$r['id']?>" data-etapa="<?=(int)$r['etapa']?>" data-doc-actual="<?=(int)$r['docs_etapa_actual']?>"><?=h($r['codigo'].' - '.mb_substr((string)$r['descripcion'], 0, 70))?></option>
                <?php endforeach;?>
            </select>
        </label>

        <label class="full">Tipo de documento
            <select name="tipo_documento" id="dlgTipo" required>
                <?php foreach($tiposDocumentoPorEtapa as $num=>$tipos):foreach($tipos as $tipo):?>
                    <option value="<?=h($tipo)?>" data-etapa="<?=$num?>" data-principal="<?=$tipo===$documentoPrincipalPorEtapa[$num]?'1':'0'?>"><?=h($tipo === 'Cotización recibida' ? 'Presupuesto / cotización recibida del proveedor' : $tipo)?></option>
                <?php endforeach;endforeach;?>
            </select>
        </label>

        <label class="full">Archivo (PDF, Word, Excel o imagen · máximo 10 MB)
            <input type="file" name="archivo_etapa" id="dlgArchivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
        </label>

        <p class="module-permission full" id="dlgVacio" hidden></p>

        <div class="full module-actions">
            <button class="btn primary" id="dlgSubir">Subir documento</button>
            <a class="btn secondary" id="dlgNuevo" href="#gestion" hidden>➕ Crear nuevo pedido</a>
        </div>
    </form>
    <?php endif;?>

    <h4>Documentos cargados en este paso</h4>
    <div id="dlgLista"></div>
</dialog>

<?php foreach($pasosFlujo as $n => $titulo):?>
<template id="docs-etapa-<?=$n?>">
    <?php if(empty($docsPorEtapa[$n])):?>
        <p class="module-permission">Todavía no hay documentos cargados en este paso.</p>
    <?php else:?>
    <div class="table-wrapper">
        <table class="module-table">
            <thead><tr><th>Pedido</th><th>Tipo</th><th>Archivo</th><th>Fecha / Responsable</th></tr></thead>
            <tbody>
            <?php foreach($docsPorEtapa[$n] as $d):?>
                <tr>
                    <td><strong><?=h($d['codigo'])?></strong></td>
                    <td><?=h($d['tipo_documento']??'Adjunto')?></td>
                    <td><a href="<?=app_url('/uploads/'.h($d['ruta_archivo']))?>" target="_blank" rel="noopener" style="color:var(--ng);font-weight:bold;text-decoration:underline">📄 <?=h($d['nombre_archivo'])?></a></td>
                    <td><?=h($d['subido_por']??'Sistema')?><br><small style="color:#9ca3af"><?=h(date('d/m/Y H:i', strtotime($d['fecha_subida'])))?></small></td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <?php endif;?>
</template>
<?php endforeach;?>

<script>
(() => {
    const dlg = document.getElementById('dlgEtapa');
    if (!dlg) return;
    const titulos = <?=json_encode($pasosFlujo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)?>;
    const canEdit = <?=$canEdit ? 'true' : 'false'?>;
    const compra = document.getElementById('dlgCompra');
    const tipo = document.getElementById('dlgTipo');
    const vacio = document.getElementById('dlgVacio');
    const subir = document.getElementById('dlgSubir');
    const nuevo = document.getElementById('dlgNuevo');
    const archivo = document.getElementById('dlgArchivo');
    const etapaInput = document.getElementById('dlgEtapaInput');
    const todasCompras = compra ? [...compra.options].map(o => o.cloneNode(true)) : [];
    let paso = 1;

    // Un pedido puede recibir el documento de un paso si ese paso ya está
    // alcanzado, o si es el siguiente y la etapa actual ya tiene su documento.
    const elegible = (o) => {
        const etapa = Number(o.dataset.etapa), actual = Number(o.dataset.docActual);
        return paso <= etapa || (paso === etapa + 1 && actual > 0);
    };
    const ajustarTipos = () => {
        const sel = compra.selectedOptions[0];
        const avanza = sel ? paso === Number(sel.dataset.etapa) + 1 : false;
        let primero = null;
        [...tipo.options].forEach(o => {
            const ok = Number(o.dataset.etapa) === paso && (!avanza || o.dataset.principal === '1');
            o.hidden = !ok; o.disabled = !ok;
            if (ok && !primero) primero = o;
        });
        if (primero && (!tipo.selectedOptions.length || tipo.selectedOptions[0].disabled)) tipo.value = primero.value;
    };
    const armarFormulario = () => {
        if (!canEdit || !compra) return;
        etapaInput.value = String(paso);
        compra.innerHTML = '';
        todasCompras.filter(elegible).forEach(o => compra.appendChild(o.cloneNode(true)));
        const hay = compra.options.length > 0;
        compra.disabled = tipo.disabled = archivo.disabled = subir.disabled = !hay;
        vacio.hidden = hay;
        if (!hay) {
            vacio.textContent = todasCompras.length === 0
                ? 'Todavía no hay pedidos. Creá uno primero.'
                : 'Ningún pedido está listo para este paso. Cargá primero el documento del paso anterior.';
        }
        nuevo.hidden = !(paso === 1);
        ajustarTipos();
    };
    const abrir = (n) => {
        paso = n;
        document.getElementById('dlgTitulo').textContent = n + '. ' + titulos[n];
        const plantilla = document.getElementById('docs-etapa-' + n);
        document.getElementById('dlgLista').innerHTML = plantilla ? plantilla.innerHTML : '';
        if (archivo) archivo.value = '';
        armarFormulario();
        dlg.showModal();
    };
    document.querySelectorAll('button.flow-step').forEach(b => b.addEventListener('click', () => abrir(Number(b.dataset.etapa))));
    document.getElementById('dlgCerrar').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    if (nuevo) nuevo.addEventListener('click', () => dlg.close());
    if (compra) compra.addEventListener('change', ajustarTipos);

    // Enlace "Adjuntar presupuesto" de la tabla: abre el paso 2 con el pedido elegido.
    const presupuestoId = <?=(int)$presupuestoCompraId?>;
    if (presupuestoId && canEdit) {
        abrir(2);
        if ([...compra.options].some(o => Number(o.value) === presupuestoId)) {
            compra.value = String(presupuestoId);
            ajustarTipos();
            tipo.value = 'Cotización recibida';
        }
    }
})();
</script>
</body>
</html>
