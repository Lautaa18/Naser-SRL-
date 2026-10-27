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
$msg = (string)($_SESSION['compras_flash_msg'] ?? '');
unset($_SESSION['compras_flash_msg']);
$err = '';

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
// Documento que habilita el paso siguiente. La cotización recibida es necesaria
// para emitir la orden de compra; la solicitud por sí sola no alcanza.
$documentoNecesarioParaAvanzar = [
    1 => 'Pedido de compra',
    2 => 'Cotización recibida',
    3 => 'Orden de compra',
    4 => 'Factura',
    5 => 'Comprobante de pago',
    6 => 'Remito / comprobante de entrega'
];
$estadosCompraFinales = ['Recibido y evaluado', 'Recibido con observaciones', 'Recibido con no conformidad'];
$estadosEditablesPorEtapa = [
    1 => ['Pedido cargado'],
    2 => ['Presupuesto solicitado', 'Esperando presupuesto', 'Presupuesto recibido'],
    3 => ['Orden de compra emitida', 'En preparación', 'Despachado', 'En tránsito', 'Con demora de entrega', 'Incidencia logística'],
    4 => ['Factura recibida', 'En preparación', 'Despachado', 'En tránsito', 'Con demora de entrega', 'Incidencia logística'],
    5 => ['Pago registrado', 'En preparación', 'Despachado', 'En tránsito', 'Con demora de entrega', 'Incidencia logística'],
    6 => ['Entrega pendiente de evaluación', 'En preparación', 'Despachado', 'En tránsito', 'Con demora de entrega', 'Incidencia logística']
];
$estadoPorEtapa = [
    1 => 'Pedido cargado',
    2 => 'Presupuesto solicitado',
    3 => 'Orden de compra emitida',
    4 => 'Factura recibida',
    5 => 'Pago registrado',
    6 => 'Entrega pendiente de evaluación'
];
$camposDocumentoCompra = [
    'Solicitud de presupuesto' => [
        ['name' => 'fecha_solicitud', 'label' => 'Fecha de solicitud', 'type' => 'date', 'required' => true],
        ['name' => 'proveedores_solicitados', 'label' => 'Proveedores consultados', 'type' => 'textarea', 'required' => true],
        ['name' => 'fecha_limite', 'label' => 'Fecha límite para recibir presupuestos', 'type' => 'date'],
        ['name' => 'plazo_requerido', 'label' => 'Plazo de entrega requerido', 'type' => 'text'],
        ['name' => 'observaciones', 'label' => 'Especificaciones y condiciones solicitadas', 'type' => 'textarea']
    ],
    'Cotización recibida' => [
        ['name' => 'numero_cotizacion', 'label' => 'Número de presupuesto / cotización', 'type' => 'text', 'required' => true],
        ['name' => 'fecha_cotizacion', 'label' => 'Fecha del presupuesto', 'type' => 'date', 'required' => true],
        ['name' => 'proveedor', 'label' => 'Proveedor que cotiza', 'type' => 'text', 'required' => true],
        ['name' => 'monto_cotizado', 'label' => 'Importe total cotizado', 'type' => 'number', 'required' => true, 'min' => 0.01],
        ['name' => 'moneda', 'label' => 'Moneda', 'type' => 'select', 'required' => true, 'options' => ['ARS' => 'ARS ($)', 'USD' => 'USD (US$)']],
        ['name' => 'vigencia_oferta', 'label' => 'Vigencia del presupuesto', 'type' => 'text'],
        ['name' => 'plazo_entrega', 'label' => 'Plazo de entrega ofrecido', 'type' => 'text'],
        ['name' => 'condiciones_pago', 'label' => 'Condiciones de pago', 'type' => 'textarea'],
        ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea']
    ],
    'Orden de compra' => [
        ['name' => 'numero_orden', 'label' => 'Número de orden de compra', 'type' => 'text', 'required' => true],
        ['name' => 'fecha_orden', 'label' => 'Fecha de emisión', 'type' => 'date', 'required' => true],
        ['name' => 'proveedor', 'label' => 'Proveedor seleccionado', 'type' => 'text', 'required' => true],
        ['name' => 'monto_orden', 'label' => 'Importe total de la orden', 'type' => 'number', 'required' => true, 'min' => 0.01],
        ['name' => 'moneda', 'label' => 'Moneda', 'type' => 'select', 'required' => true, 'options' => ['ARS' => 'ARS ($)', 'USD' => 'USD (US$)']],
        ['name' => 'fecha_entrega_comprometida', 'label' => 'Fecha de entrega comprometida', 'type' => 'date'],
        ['name' => 'condiciones_pago', 'label' => 'Condiciones de pago', 'type' => 'textarea'],
        ['name' => 'autorizado_por', 'label' => 'Autorizado por', 'type' => 'text', 'required' => true],
        ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea']
    ],
    'Factura' => [
        ['name' => 'numero_factura', 'label' => 'Número de factura', 'type' => 'text', 'required' => true],
        ['name' => 'fecha_factura', 'label' => 'Fecha de factura', 'type' => 'date', 'required' => true],
        ['name' => 'fecha_vencimiento', 'label' => 'Fecha de vencimiento', 'type' => 'date'],
        ['name' => 'monto_factura', 'label' => 'Importe total facturado', 'type' => 'number', 'required' => true, 'min' => 0.01],
        ['name' => 'moneda', 'label' => 'Moneda', 'type' => 'select', 'required' => true, 'options' => ['ARS' => 'ARS ($)', 'USD' => 'USD (US$)']],
        ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea']
    ],
    'Comprobante de pago' => [
        ['name' => 'fecha_pago', 'label' => 'Fecha de pago', 'type' => 'date', 'required' => true],
        ['name' => 'monto_pago', 'label' => 'Importe pagado', 'type' => 'number', 'required' => true, 'min' => 0.01],
        ['name' => 'moneda', 'label' => 'Moneda', 'type' => 'select', 'required' => true, 'options' => ['ARS' => 'ARS ($)', 'USD' => 'USD (US$)']],
        ['name' => 'medio_pago', 'label' => 'Medio de pago', 'type' => 'select', 'required' => true, 'options' => ['Transferencia bancaria' => 'Transferencia bancaria', 'Cheque' => 'Cheque', 'Efectivo' => 'Efectivo', 'Tarjeta' => 'Tarjeta', 'Otro' => 'Otro']],
        ['name' => 'referencia_pago', 'label' => 'Referencia / número de operación', 'type' => 'text', 'required' => true],
        ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea']
    ],
    'Remito / comprobante de entrega' => [
        ['name' => 'fecha_recepcion', 'label' => 'Fecha de recepción', 'type' => 'date', 'required' => true],
        ['name' => 'numero_remito', 'label' => 'Número de remito', 'type' => 'text', 'required' => true],
        ['name' => 'cantidad_recibida', 'label' => 'Cantidad recibida', 'type' => 'number', 'required' => true, 'min' => 0.01],
        ['name' => 'recibido_por', 'label' => 'Recibido por', 'type' => 'text', 'required' => true],
        ['name' => 'diferencias', 'label' => 'Faltantes, daños o diferencias', 'type' => 'textarea']
    ]
];
$etiquetasDatosDocumentoCompra = [];
foreach ($camposDocumentoCompra as $camposTipo) {
    foreach ($camposTipo as $campoTipo) $etiquetasDatosDocumentoCompra[$campoTipo['name']] = $campoTipo['label'];
}

function siguienteCodigoCompra(PDO $pdo): string
{
    $anio = date('Y');
    $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED)) FROM compras WHERE codigo LIKE ?");
    $st->execute(['CMP-' . $anio . '-%']);
    $secuencia = (int)$st->fetchColumn() + 1;
    do {
        $codigo = 'CMP-' . $anio . '-' . str_pad((string)$secuencia++, 4, '0', STR_PAD_LEFT);
        $st = $pdo->prepare('SELECT COUNT(*) FROM compras WHERE codigo=?');
        $st->execute([$codigo]);
    } while ((int)$st->fetchColumn() > 0);
    return $codigo;
}

function datosPedidoDigitalCompra(array $registro): array
{
    $campos = json_decode((string)($registro['datos_json'] ?? ''), true);
    if (!is_array($campos)) $campos = [];
    $storage = json_decode((string)($registro['storage_json'] ?? ''), true);
    if (!is_array($storage)) $storage = [];
    $nativo = json_decode((string)($storage['NASER_PGSN03_F1'] ?? ''), true);
    if (!is_array($nativo)) $nativo = [];
    $obtener = static function (string $clave) use ($campos, $nativo): string {
        $valor = $nativo[$clave] ?? $campos['#' . $clave] ?? '';
        return is_scalar($valor) ? trim((string)$valor) : '';
    };
    $filas = [];
    $cantidadTotal = 0;
    $filasNativas = is_array($nativo['filas'] ?? null) ? $nativo['filas'] : [];
    foreach ($filasNativas as $fila) {
        if (!is_array($fila)) continue;
        $descripcion = trim((string)($fila['descripcion'] ?? ''));
        if ($descripcion === '') continue;
        $cantidad = is_numeric($fila['cantidad'] ?? null) ? max(0, (float)$fila['cantidad']) : 0;
        $unidad = trim((string)($fila['unidad'] ?? ''));
        $destino = trim((string)($fila['destino'] ?? ''));
        $plazo = trim((string)($fila['plazo'] ?? ''));
        $texto = '• ' . ($cantidad > 0 ? rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.') . ' ' . $unidad . ' — ' : '') . $descripcion;
        if ($destino !== '') $texto .= ' | Destino: ' . $destino;
        if ($plazo !== '') $texto .= ' | Plazo: ' . $plazo;
        $filas[] = $texto;
        $cantidadTotal += $cantidad;
    }
    $referencia = $obtener('numeroPedido') ?: trim((string)($registro['referencia'] ?? ''));
    $solicitante = $obtener('solicitante');
    $fecha = $obtener('fecha');
    $proveedores = trim((string)($nativo['proveedores'] ?? $campos['#proveedores'] ?? ''));
    $observaciones = trim((string)($nativo['observaciones'] ?? $campos['#observaciones'] ?? ''));
    $partes = ['Pedido de Materiales y Servicios digital #' . ((int)($registro['id'] ?? 0)) . ($referencia !== '' ? ' · Pedido ' . $referencia : '')];
    if ($solicitante !== '') $partes[] = 'Solicitante: ' . $solicitante;
    if ($fecha !== '') $partes[] = 'Fecha: ' . $fecha;
    if ($filas) $partes[] = "Materiales / servicios:\n" . implode("\n", $filas);
    else $partes[] = 'Detalle pendiente de completar en el formulario digital.';
    if ($proveedores !== '') $partes[] = 'Proveedores sugeridos: ' . $proveedores;
    if ($observaciones !== '') $partes[] = 'Observaciones: ' . $observaciones;
    $resumen = $filas ? implode('; ', array_slice($filas, 0, 2)) : 'Detalle pendiente de completar';
    if (count($filas) > 2) $resumen .= ' (+' . (count($filas) - 2) . ' ítems)';
    $primeroProveedor = preg_split('/[\r\n,;]+/', $proveedores)[0] ?? '';
    return [
        'descripcion' => mb_substr(implode("\n", $partes), 0, 10000),
        'cantidad' => max(1, (int)round($cantidadTotal)),
        'proveedor' => mb_substr(trim($primeroProveedor) ?: 'Pendiente', 0, 150),
        'referencia' => $referencia,
        'solicitante' => $solicitante,
        'resumen' => mb_substr($resumen, 0, 500)
    ];
}

function validarDatosDocumentoCompra(string $tipo, $entrada, array $configuracion): array
{
    if (!is_array($entrada)) $entrada = [];
    $resultado = [];
    foreach ($configuracion[$tipo] ?? [] as $campo) {
        $valor = $entrada[$campo['name']] ?? '';
        if (!is_scalar($valor)) throw new RuntimeException('Revisá el campo «' . $campo['label'] . '».');
        $valor = trim((string)$valor);
        if (!empty($campo['required']) && $valor === '') throw new RuntimeException('Completá el campo obligatorio: ' . $campo['label'] . '.');
        if ($valor !== '' && ($campo['type'] ?? '') === 'date') {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            if (!$fecha || $fecha->format('Y-m-d') !== $valor) throw new RuntimeException('Ingresá una fecha válida en «' . $campo['label'] . '».');
        }
        if ($valor !== '' && ($campo['type'] ?? '') === 'number') {
            if (!is_numeric($valor) || (float)$valor < (float)($campo['min'] ?? 0)) throw new RuntimeException('Ingresá un importe o cantidad válido en «' . $campo['label'] . '».');
        }
        if ($valor !== '' && ($campo['type'] ?? '') === 'select' && !array_key_exists($valor, $campo['options'] ?? [])) {
            throw new RuntimeException('Seleccioná una opción válida en «' . $campo['label'] . '».');
        }
        if (mb_strlen($valor) > (($campo['type'] ?? '') === 'textarea' ? 5000 : 300)) throw new RuntimeException('El campo «' . $campo['label'] . '» es demasiado extenso.');
        $resultado[$campo['name']] = $valor;
    }
    return $resultado;
}

function mostrarDatosDocumentoCompra(?string $json, array $etiquetas, bool $abierto = false): string
{
    $datos = json_decode((string)$json, true);
    if (!is_array($datos) || !$datos) return '';
    $html = '<details class="doc-details"' . ($abierto ? ' open' : '') . '><summary>Ver datos cargados</summary><ul>';
    foreach ($datos as $clave => $valor) {
        if (!is_scalar($valor) || trim((string)$valor) === '') continue;
        $html .= '<li><strong>' . h($etiquetas[$clave] ?? ucfirst(str_replace('_', ' ', (string)$clave))) . ':</strong> ' . h((string)$valor) . '</li>';
    }
    return $html . '</ul></details>';
}

function subirArchivoCompra(int $compraId, int $etapa, string $uploadDir, array $archivo): array
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
    $rutaFisica = $uploadDir . '/' . $seguro;
    if (!move_uploaded_file($tmp, $rutaFisica)) {
        throw new RuntimeException('No se pudo guardar el archivo adjunto.');
    }
    return ['nombre' => $nombreOriginal, 'ruta' => 'compras/' . $seguro, 'fisica' => $rutaFisica];
}

function guardarDocumentoCompra(PDO $pdo, int $compraId, int $etapa, string $tipo, int $usuarioId, string $uploadDir, array $archivo, array $datos = [], ?string &$rutaFisica = null): int
{
    $rutaFisica = null;
    $subido = subirArchivoCompra($compraId, $etapa, $uploadDir, $archivo);
    $rutaFisica = $subido['fisica'];
    try {
        $jsonDatos = $datos ? json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
        $st = $pdo->prepare('INSERT INTO compras_documentos(compra_id, etapa, nombre_archivo, ruta_archivo, tipo_documento, datos_json, creado_por) VALUES(?,?,?,?,?,?,?)');
        $st->execute([$compraId, $etapa, $subido['nombre'], $subido['ruta'], $tipo, $jsonDatos, $usuarioId]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($rutaFisica);
        $rutaFisica = null;
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
                $estadosPermitidos = $estadosEditablesPorEtapa[(int)$anterior['etapa']] ?? [];
                if (!in_array($estado, $estadosPermitidos, true) && $estado !== $anterior['estado_logistico']) throw new RuntimeException('Seleccioná un estado logístico válido para la etapa actual.');
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
                $codigo = siguienteCodigoCompra($pdo);
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

        if ($a === 'importar_pedido_digital') {
            $formularioId = (int)($_POST['formulario_registro_id'] ?? 0);
            if ($formularioId < 1) throw new RuntimeException('Seleccioná un pedido digital válido.');
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("SELECT id, sector_id, referencia, estado, datos_json, storage_json FROM formularios_registros WHERE id=? AND sector_id=? AND formulario='compras-pedido-materiales' FOR UPDATE");
                $st->execute([$formularioId, $sid]);
                $pedidoDigitalRegistro = $st->fetch();
                if (!$pedidoDigitalRegistro) throw new RuntimeException('El pedido digital no existe o no pertenece al sector de Compras.');
                $st = $pdo->prepare('SELECT codigo FROM compras WHERE formulario_registro_id=? LIMIT 1');
                $st->execute([$formularioId]);
                $compraExistente = $st->fetchColumn();
                if ($compraExistente) throw new RuntimeException('Este pedido digital ya está vinculado a la compra ' . $compraExistente . '.');

                $pedidoDigital = datosPedidoDigitalCompra($pedidoDigitalRegistro);
                $codigo = siguienteCodigoCompra($pdo);
                $pdo->prepare("INSERT INTO compras(sector_id, codigo, sector, descripcion, cantidad, prioridad, proveedor, monto, moneda, formulario_registro_id, etapa, estado_logistico, creado_por, actualizado_por) VALUES(?,?,?,?,?,'Normal',?,0,'ARS',?,1,'Pedido cargado',?,?)")
                    ->execute([$sid, $codigo, $sector['nombre'], $pedidoDigital['descripcion'], $pedidoDigital['cantidad'], $pedidoDigital['proveedor'], $formularioId, $uid, $uid]);
                $compraId = (int)$pdo->lastInsertId();
                $pdo->commit();
            } catch (Throwable $errorImportacion) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $errorImportacion;
            }
            auditModulo($pdo, $uid, 'compras_importar_pedido_digital', "Pedido digital #$formularioId vinculado a compra #$compraId");
            notificarCompra($pdo, $sid, $uid, $uid, $codigo, $compraId, 'Pedido digital incorporado ' . $codigo, 'El pedido de Materiales y Servicios #' . $formularioId . ' se incorporó al seguimiento de Compras.', 'creada_desde_formulario');
            $msg = 'Pedido digital vinculado. La compra ' . $codigo . ' ya aparece en seguimiento y puede avanzar a solicitud de presupuesto.';
        }

        if ($a === 'documento_etapa') {
            $id = (int)($_POST['compra_id'] ?? 0);
            $et = (int)($_POST['etapa_doc'] ?? 0);
            $tipoDoc = trim((string)($_POST['tipo_documento'] ?? ''));
            if (!isset($etapaNombre[$et]) || !in_array($tipoDoc, $tiposDocumentoPorEtapa[$et], true)) throw new RuntimeException('Seleccioná una etapa y tipo de documento válidos.');

            $rutaFisicaDocumento = null;
            $pdo->beginTransaction();
            try {
                // Bloquea el pedido mientras se valida y se actualiza su etapa para
                // evitar dos cargas simultáneas que salteen o pisen el estado.
                $st = $pdo->prepare('SELECT id, codigo, etapa, estado_logistico, creado_por, formulario_registro_id FROM compras WHERE id=? AND sector_id=? FOR UPDATE');
                $st->execute([$id, $sid]);
                $compra = $st->fetch();
                if (!$compra) throw new RuntimeException('Pedido inválido.');
                $etapaActual = (int)$compra['etapa'];
                if ($et > $etapaActual + 1) throw new RuntimeException('El ciclo se completa en orden. Adjuntá primero el documento de la etapa pendiente.');
                if ($et === $etapaActual + 1) {
                    if ($tipoDoc !== $documentoPrincipalPorEtapa[$et]) throw new RuntimeException('Para avanzar, adjuntá el documento principal requerido: ' . $documentoPrincipalPorEtapa[$et] . '.');
                    $tienePedidoDigital = false;
                    if ($etapaActual === 1 && (int)$compra['formulario_registro_id'] > 0) {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM formularios_registros WHERE id=? AND sector_id=? AND formulario='compras-pedido-materiales'");
                        $st->execute([(int)$compra['formulario_registro_id'], $sid]);
                        $tienePedidoDigital = (int)$st->fetchColumn() > 0;
                    }
                    if (!$tienePedidoDigital) {
                        $st = $pdo->prepare('SELECT COUNT(*) FROM compras_documentos WHERE compra_id=? AND etapa=?');
                        $st->execute([$id, $etapaActual]);
                        if ((int)$st->fetchColumn() === 0) throw new RuntimeException('Adjuntá el documento de la etapa actual antes de avanzar.');
                        $tipoRequerido = $documentoNecesarioParaAvanzar[$etapaActual] ?? '';
                        $st = $pdo->prepare('SELECT COUNT(*) FROM compras_documentos WHERE compra_id=? AND etapa=? AND tipo_documento=?');
                        $st->execute([$id, $etapaActual, $tipoRequerido]);
                        if ((int)$st->fetchColumn() === 0) {
                            $instruccion = $etapaActual === 2
                                ? 'Adjuntá primero la cotización o presupuesto recibido del proveedor en el paso 2.'
                                : 'Adjuntá primero el documento requerido de la etapa actual: ' . $tipoRequerido . '.';
                            throw new RuntimeException($instruccion);
                        }
                    }
                }

                $datosDocumento = validarDatosDocumentoCompra($tipoDoc, $_POST['datos'] ?? [], $camposDocumentoCompra);
                guardarDocumentoCompra($pdo, $id, $et, $tipoDoc, $uid, $uploadDir, $_FILES['archivo_etapa'] ?? [], $datosDocumento, $rutaFisicaDocumento);
                $avanza = $et === $etapaActual + 1;
                if ($avanza) {
                    if ($et === 3) {
                        $pdo->prepare('UPDATE compras SET proveedor=?, monto=?, moneda=? WHERE id=? AND sector_id=?')
                            ->execute([$datosDocumento['proveedor'], $datosDocumento['monto_orden'], $datosDocumento['moneda'], $id, $sid]);
                    }
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
                $pdo->commit();
            } catch (Throwable $errorDocumento) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($rutaFisicaDocumento !== null && is_file($rutaFisicaDocumento)) @unlink($rutaFisicaDocumento);
                throw $errorDocumento;
            }
            auditModulo($pdo, $uid, 'compras_documento', "Documento $tipoDoc en compra #$id, etapa $et");
            notificarCompra($pdo, $sid, (int)$compra['creado_por'], $uid, (string)$compra['codigo'], $id, 'Documento agregado a compra ' . $compra['codigo'], $tipoDoc . ' cargado en ' . $etapaNombre[$et] . '. Estado logístico: ' . $estado . '.', 'documento');
        }

        if ($a === 'actualizar_documento') {
            $documentoId = (int)($_POST['documento_id'] ?? 0);
            if ($documentoId < 1) throw new RuntimeException('Seleccioná un documento válido para editar.');
            $pdo->beginTransaction();
            $archivoNuevo = null;
            $rutaAnterior = '';
            try {
                $st = $pdo->prepare('SELECT d.id, d.compra_id, d.etapa, d.tipo_documento, d.ruta_archivo, c.codigo, c.creado_por FROM compras_documentos d JOIN compras c ON c.id=d.compra_id WHERE d.id=? AND c.sector_id=? FOR UPDATE');
                $st->execute([$documentoId, $sid]);
                $documentoAnterior = $st->fetch();
                if (!$documentoAnterior) throw new RuntimeException('El documento ya no existe o no pertenece a Compras.');
                $tipo = (string)$documentoAnterior['tipo_documento'];
                $etapa = (int)$documentoAnterior['etapa'];
                $compraId = (int)$documentoAnterior['compra_id'];
                if (!in_array($tipo, $tiposDocumentoPorEtapa[$etapa] ?? [], true)) throw new RuntimeException('El tipo de documento no se puede editar.');
                $datos = validarDatosDocumentoCompra($tipo, $_POST['datos'] ?? [], $camposDocumentoCompra);
                $jsonDatos = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $subirReemplazo = isset($_FILES['archivo_reemplazo'])
                    && (int)($_FILES['archivo_reemplazo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($subirReemplazo) {
                    $archivoNuevo = subirArchivoCompra($compraId, $etapa, $uploadDir, $_FILES['archivo_reemplazo']);
                    $rutaAnterior = (string)$documentoAnterior['ruta_archivo'];
                    $pdo->prepare('UPDATE compras_documentos SET nombre_archivo=?, ruta_archivo=?, datos_json=?, creado_por=?, fecha_subida=NOW() WHERE id=?')
                        ->execute([$archivoNuevo['nombre'], $archivoNuevo['ruta'], $jsonDatos, $uid, $documentoId]);
                } else {
                    $pdo->prepare('UPDATE compras_documentos SET datos_json=? WHERE id=?')->execute([$jsonDatos, $documentoId]);
                }
                if ($tipo === 'Orden de compra') {
                    $pdo->prepare('UPDATE compras SET proveedor=?, monto=?, moneda=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                        ->execute([$datos['proveedor'], $datos['monto_orden'], $datos['moneda'], $uid, $compraId, $sid]);
                } else {
                    $pdo->prepare('UPDATE compras SET actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$uid, $compraId, $sid]);
                }
                $pdo->commit();
            } catch (Throwable $errorEdicionDocumento) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($archivoNuevo !== null && is_file($archivoNuevo['fisica'])) @unlink($archivoNuevo['fisica']);
                throw $errorEdicionDocumento;
            }
            if ($archivoNuevo !== null && preg_match('#^compras/[^/]+$#', str_replace('\\', '/', $rutaAnterior))) {
                $anteriorFisico = $uploadDir . DIRECTORY_SEPARATOR . basename($rutaAnterior);
                if (is_file($anteriorFisico)) @unlink($anteriorFisico);
            }
            auditModulo($pdo, $uid, 'compras_documento_actualizado', "Documento #$documentoId editado en Compra #$compraId");
            notificarCompra($pdo, $sid, (int)$documentoAnterior['creado_por'], $uid, (string)$documentoAnterior['codigo'], $compraId, 'Datos actualizados en compra ' . $documentoAnterior['codigo'], 'Se actualizaron los datos del documento ' . $tipo . '.', 'documento_editado');
            $msg = 'Datos del documento actualizados.' . ($archivoNuevo !== null ? ' Se reemplazó también el archivo.' : '');
        }

        if (in_array($a, ['encuesta', 'actualizar_encuesta'], true)) {
            $id = (int)($_POST['compra_id'] ?? 0);
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

            $estadoFinal = ($calidad === 'No Conforme' || $estadoFisico === 'Malo') ? 'Recibido con no conformidad'
                : (($calidad === 'Observada' || $estadoFisico === 'Regular' || $entrega === 'Demorada') ? 'Recibido con observaciones' : 'Recibido y evaluado');

            // Serializa dos envíos simultáneos para no guardar dos encuestas de recepción.
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT id, codigo, etapa, creado_por FROM compras WHERE id=? AND sector_id=? FOR UPDATE');
                $st->execute([$id, $sid]);
                $compra = $st->fetch();
                if (!$compra) throw new RuntimeException('Pedido de compra inválido.');
                if ((int)$compra['etapa'] < 6) throw new RuntimeException('La recepción se registra después de completar los cinco pasos anteriores y adjuntar el documento de entrega.');
                $st = $pdo->prepare('SELECT COUNT(*) FROM compras_documentos WHERE compra_id=? AND etapa=6 AND tipo_documento=?');
                $st->execute([$id, $documentoNecesarioParaAvanzar[6]]);
                if ((int)$st->fetchColumn() === 0) throw new RuntimeException('Adjuntá el remito o comprobante de entrega en el paso 6 antes de completar la encuesta.');
                $st = $pdo->prepare('SELECT id FROM compras_encuesta WHERE compra_id=? FOR UPDATE');
                $st->execute([$id]);
                $encuestaId = (int)$st->fetchColumn();
                if ($a === 'encuesta') {
                    if ($encuestaId > 0) throw new RuntimeException('Esta compra ya tiene una evaluación de recepción registrada.');
                    $pdo->prepare('INSERT INTO compras_encuesta(compra_id, calidad, estado_fisico, cumplimiento_entrega, modalidad_entrega, observaciones, creado_por) VALUES(?,?,?,?,?,?,?)')
                        ->execute([$id, $calidad, $estadoFisico, $entrega, $modalidad, $obs, $uid]);
                } else {
                    if ($encuestaId < 1) throw new RuntimeException('La encuesta de recepción ya no existe.');
                    $pdo->prepare('UPDATE compras_encuesta SET calidad=?, estado_fisico=?, cumplimiento_entrega=?, modalidad_entrega=?, observaciones=? WHERE id=?')
                        ->execute([$calidad, $estadoFisico, $entrega, $modalidad, $obs, $encuestaId]);
                }
                $pdo->prepare('UPDATE compras SET etapa=6, estado_logistico=?, actualizado_por=?, actualizado_en=NOW() WHERE id=? AND sector_id=?')
                    ->execute([$estadoFinal, $uid, $id, $sid]);
                $pdo->commit();
            } catch (Throwable $errorEncuesta) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $errorEncuesta;
            }
            $esEdicionEncuesta = $a === 'actualizar_encuesta';
            auditModulo($pdo, $uid, $esEdicionEncuesta ? 'compras_encuesta_actualizada' : 'compras_encuesta', ($esEdicionEncuesta ? 'Evaluación actualizada' : 'Recepción registrada') . " para Compra #$id");
            notificarCompra($pdo, $sid, (int)$compra['creado_por'], $uid, (string)$compra['codigo'], $id, ($esEdicionEncuesta ? 'Evaluación actualizada' : 'Recepción registrada') . ' para compra ' . $compra['codigo'], 'Estado final: ' . $estadoFinal . '. Modalidad de entrega: ' . $modalidad . '.', $esEdicionEncuesta ? 'recepcion_editada' : 'recepcion');
            $msg = ($esEdicionEncuesta ? 'Recepción y evaluación actualizadas.' : 'Recepción y evaluación registradas.') . ' Estado final: ' . $estadoFinal . '.';
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

        if (!in_array($a, ['guardar', 'importar_pedido_digital', 'documento_etapa', 'actualizar_documento', 'encuesta', 'actualizar_encuesta', 'eliminar'], true)) {
            throw new RuntimeException('La acción solicitada no es válida.');
        }
    } catch (Throwable $e) {
        $err = mensaje_error($e);
    }
    if ($err === '' && $msg !== '') {
        $_SESSION['compras_flash_msg'] = $msg;
        $destino = (($a === 'actualizar_documento' && !empty($compraId)) || $a === 'actualizar_encuesta')
            ? '/php/compras.php?ver=' . (int)($a === 'actualizar_encuesta' ? $id : $compraId) . '#detalleCompra'
            : '/php/compras.php#seguimiento';
        header('Location: ' . app_url($destino), true, 303);
        exit;
    }
}

// Carga de Datos
$edit = null;
if ($canEdit && !empty($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM compras WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar'], $sid]);
    $edit = $st->fetch();
}

$st = $pdo->prepare("SELECT c.*, u.nombre AS actualizado_nombre,
    (SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id) AS total_docs,
    CASE WHEN c.etapa=1 AND EXISTS (
        SELECT 1 FROM formularios_registros fr WHERE fr.id=c.formulario_registro_id
            AND fr.sector_id=c.sector_id AND fr.formulario='compras-pedido-materiales'
    ) THEN 1 ELSE (SELECT COUNT(*) FROM compras_documentos d WHERE d.compra_id=c.id AND d.etapa=c.etapa
        AND d.tipo_documento=CASE c.etapa
            WHEN 1 THEN 'Pedido de compra'
            WHEN 2 THEN 'Cotización recibida'
            WHEN 3 THEN 'Orden de compra'
            WHEN 4 THEN 'Factura'
            WHEN 5 THEN 'Comprobante de pago'
            WHEN 6 THEN 'Remito / comprobante de entrega'
            ELSE '' END) END AS docs_etapa_actual
    FROM compras c LEFT JOIN usuarios u ON u.id=c.actualizado_por WHERE c.sector_id=? ORDER BY c.fecha_creacion DESC");
$st->execute([$sid]);
$rows = $st->fetchAll();
$pedidosDigitalesSt = $pdo->prepare("SELECT f.*, u.nombre AS autor FROM formularios_registros f
    LEFT JOIN usuarios u ON u.id=f.creado_por
    WHERE f.sector_id=? AND f.formulario='compras-pedido-materiales'
      AND NOT EXISTS (SELECT 1 FROM compras c WHERE c.formulario_registro_id=f.id)
    ORDER BY f.actualizado_en DESC, f.id DESC");
$pedidosDigitalesSt->execute([$sid]);
$pedidosDigitales = $pedidosDigitalesSt->fetchAll();
foreach ($pedidosDigitales as &$pedidoDigitalPendiente) {
    $pedidoDigitalPendiente['datos_pedido'] = datosPedidoDigitalCompra($pedidoDigitalPendiente);
}
unset($pedidoDigitalPendiente);
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
$remitosSt = $pdo->prepare('SELECT DISTINCT compra_id FROM compras_documentos WHERE etapa=6 AND tipo_documento=?');
$remitosSt->execute([$documentoNecesarioParaAvanzar[6]]);
$comprasConRemito = array_fill_keys(array_map('intval', $remitosSt->fetchAll(PDO::FETCH_COLUMN)), true);
$recepcionesPendientes = array_values(array_filter($rows, static fn($r) => (int)$r['etapa'] >= 6
    && isset($comprasConRemito[(int)$r['id']])
    && !isset($encuestadasIds[(int)$r['id']])));
$verCompraId = (int)($_GET['ver'] ?? $_GET['editar_encuesta'] ?? 0);
$documentoEdicion = null;
if (!empty($_GET['editar_documento'])) {
    foreach ($docsCompra as $documentoCandidato) {
        if ((int)$documentoCandidato['id'] === (int)$_GET['editar_documento']) {
            $documentoEdicion = $documentoCandidato;
            $verCompraId = (int)$documentoCandidato['compra_id'];
            break;
        }
    }
}
$compraDetalle = null;
foreach ($rows as $compraCandidata) {
    if ((int)$compraCandidata['id'] === $verCompraId) {
        $compraDetalle = $compraCandidata;
        break;
    }
}
if (!$compraDetalle) $documentoEdicion = null;
$datosDocumentoEdicion = $documentoEdicion ? (json_decode((string)($documentoEdicion['datos_json'] ?? ''), true) ?: []) : [];
$encuestaDetalle = null;
foreach ($encuestas as $encuestaCandidata) {
    if ((int)$encuestaCandidata['compra_id'] === $verCompraId) {
        $encuestaDetalle = $encuestaCandidata;
        break;
    }
}
$encuestaCompraSeleccionada = (int)($_GET['evaluar'] ?? 0);
if ($encuestaCompraSeleccionada > 0 && !in_array($encuestaCompraSeleccionada, array_map(static fn($r) => (int)$r['id'], $recepcionesPendientes), true)) {
    $encuestaCompraSeleccionada = 0;
}

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
.module-card{background:#fff;border:1px solid var(--nl);border-radius:17px;padding:19px;box-shadow:0 5px 18px rgba(31,41,55,.045)}
.purchase-detail{margin:20px 0;scroll-margin-top:20px}.purchase-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 20px}.purchase-detail-grid>div{padding:12px;border:1px solid var(--nl);border-radius:11px;background:#fbfdfb}.purchase-detail-grid .wide{grid-column:1/-1}.purchase-detail dt{font-size:11px;font-weight:800;color:#66736a;text-transform:uppercase}.purchase-detail dd{margin:5px 0 0;color:#22352a;white-space:pre-wrap;overflow-wrap:anywhere}.purchase-detail .module-actions{margin:12px 0}.purchase-detail h4{margin:20px 0 8px}.document-edit{padding:16px;margin-top:15px;border:1px solid var(--nl);border-radius:13px;background:#f7faf8;scroll-margin-top:20px}
.module-card h3{margin:0 0 8px;font-size:15px;color:#374151}.module-card .big{font-size:30px;line-height:1;font-weight:900;color:var(--ng);margin-top:6px}
.module-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:25px 0 12px}.module-toolbar h2{margin:0;font-size:20px}
.module-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.module-form .full{grid-column:1/-1}.module-form label{display:flex;flex-direction:column;gap:7px;font-size:12px;font-weight:800;color:#374151}
.module-form input,.module-form select,.module-form textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d6dfd8;border-radius:10px;background:#fbfdfb;font-family:inherit}
.module-form textarea{min-height:80px;resize:vertical}
.table-wrapper{overflow:auto;border:1px solid var(--nl);border-radius:16px;background:#fff}.module-table{width:100%;border-collapse:collapse;min-width:850px}.module-table th,.module-table td{padding:12px 13px;border-bottom:1px solid #edf1ee;text-align:left;font-size:13px}.module-table th{background:#f1f7f3;color:#27583a;font-size:11px;text-transform:uppercase;letter-spacing:.5px}.module-table tbody tr:hover{background:#fbfdfb}
.module-actions{display:flex;gap:6px;flex-wrap:wrap}.module-note,.module-error{padding:13px 15px;border-radius:11px;margin:12px 0;font-weight:700;font-size:13px}.module-note{background:#eaf7ef;color:#176337;border:1px solid #b7e4c7}.module-error{background:#fff1f0;color:#9b231b;border:1px solid #fca5a5}
.digital-requests{margin:16px 0;padding:16px;border:1px solid var(--nl);border-radius:13px;background:#f7faf8}.digital-requests h4{margin:0 0 6px;color:var(--nd)}.digital-requests>p{margin:0 0 12px;color:#526157;font-size:13px}.digital-requests .module-table{min-width:900px}.digital-requests .module-actions form{margin:0}.digital-requests .module-actions .btn{white-space:nowrap}.doc-details{margin-top:5px;font-size:11px;color:#4b5563}.doc-details summary{cursor:pointer;color:var(--nd);font-weight:700}.doc-details ul{padding-left:18px;margin:5px 0}.doc-details li{margin:3px 0;white-space:normal}
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
@media(max-width:900px){.flow{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.module-form,.purchase-detail-grid{grid-template-columns:1fr}.module-form .full,.purchase-detail-grid .wide{grid-column:auto}.flow{grid-template-columns:repeat(2,1fr)}}
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
    <button type="button" class="flow-step" data-etapa="<?=$n?>"><b><?=$n?></b> <?=h($titulo)?><small><?=$canEdit ? '📝 Completar etapa' : '📄 Ver documentos'?></small></button>
<?php endforeach;?>
</div>

<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>

<!-- SECCIÓN: FORMULARIOS DIGITALES (se guardan en la base de datos) -->
<?php formulariosPanel($pdo, 'compras'); ?>

<?php if($canEdit):?>
<section class="module-card" id="gestion">
    <h3><?=$edit ? '✏ Editar Pedido de Compra: '.h($edit['codigo']) : '➕ Cargar Nuevo Pedido de Compra'?></h3>
    <?php if(!$edit):?>
    <div class="digital-requests">
        <h4>Usar un pedido de Materiales y Servicios ya cargado</h4>
        <p>Vinculá un formulario digital al seguimiento para continuar con presupuesto, orden de compra, factura, pago y recepción sin volver a cargar el pedido.</p>
        <?php if($pedidosDigitales):?>
        <div class="table-wrapper">
            <table class="module-table">
                <thead><tr><th>Formulario</th><th>Solicitante / Pedido</th><th>Detalle</th><th>Estado</th><th>Cargado por</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach($pedidosDigitales as $pedidoPendiente): $infoPedido=$pedidoPendiente['datos_pedido']; ?>
                    <tr>
                        <td><strong>#<?=(int)$pedidoPendiente['id']?></strong><br><small><?=h(date('d/m/Y H:i', strtotime($pedidoPendiente['actualizado_en'])))?></small></td>
                        <td><?=h($infoPedido['solicitante'] ?: 'Sin solicitante')?><br><small>Pedido <?=h($infoPedido['referencia'] ?: 'sin número')?></small></td>
                        <td><?=h($infoPedido['resumen'])?><br><small>Cantidad total: <?=(int)$infoPedido['cantidad']?></small></td>
                        <td><span class="badge badge-etapa"><?=h(estadoFormularioLabel((string)$pedidoPendiente['estado']))?></span></td>
                        <td><?=h($pedidoPendiente['autor'] ?? 'Sistema')?></td>
                        <td class="module-actions">
                            <a class="btn secondary" target="_blank" rel="noopener" href="<?=h(app_url('/php/formularios/llenar.php?id='.(int)$pedidoPendiente['id']))?>">Ver formulario</a>
                            <form method="post" onsubmit="return confirm('¿Iniciar el seguimiento de este pedido digital?')"><?=csrf_field()?>
                                <input type="hidden" name="accion" value="importar_pedido_digital">
                                <input type="hidden" name="formulario_registro_id" value="<?=(int)$pedidoPendiente['id']?>">
                                <button class="btn primary">Usar como pedido</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <?php else:?>
        <p class="module-permission">No hay pedidos digitales pendientes de vincular. Los formularios ya usados aparecen como compras en seguimiento.</p>
        <?php endif;?>
    </div>
    <h4>O cargar un pedido manualmente</h4>
    <?php endif;?>
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
        <label>Monto registrado
            <input type="number" step="0.01" name="monto" value="<?=h($edit['monto']??'0.00')?>">
        </label>

        <label>Moneda
            <select name="moneda">
                <option value="ARS" <?=($edit['moneda']??'ARS')==='ARS'?'selected':''?>>ARS ($)</option>
                <option value="USD" <?=($edit['moneda']??'ARS')==='USD'?'selected':''?>>USD ($)</option>
            </select>
        </label>

        <label class="full">Estado logístico
            <?php if(in_array($edit['estado_logistico'], $estadosCompraFinales, true)):?>
            <input value="<?=h($edit['estado_logistico'])?>" readonly>
            <?php else:?>
            <select name="estado_logistico">
                <?php $estadosDisponibles = $estadosEditablesPorEtapa[(int)$edit['etapa']] ?? [];?>
                <?php if(!in_array($edit['estado_logistico'], $estadosDisponibles, true)):?><option value="<?=h($edit['estado_logistico'])?>" selected><?=h($edit['estado_logistico'])?> (actual)</option><?php endif;?>
                <?php foreach($estadosDisponibles as $estado):?><option value="<?=h($estado)?>" <?=($edit['estado_logistico']??'')===$estado?'selected':''?>><?=h($estado)?></option><?php endforeach;?>
            </select>
            <?php endif;?>
        </label>
        <?php endif;?>

        <label class="full">Adjuntar solicitud o especificación (opcional · máximo 10 MB)
            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
        </label>

        <div class="full">
            <button class="btn primary"><?=$edit?'Actualizar Pedido':'Crear Pedido'?></button>
            <?php if($edit):?>
                <a class="btn secondary" href="<?=app_url('/php/compras.php?ver='.(int)$edit['id'].'#detalleCompra')?>">Ver detalle</a>
                <a class="btn secondary" href="<?=app_url('/php/compras.php#gestion')?>">Cancelar</a>
            <?php endif;?>
        </div>
    </form>
</section>
<?php endif;?>

<div class="module-toolbar" id="seguimiento">
    <h2>Seguimiento Logístico y de Compras</h2>
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
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!$rows):?>
            <tr><td colspan="9" style="text-align:center;padding:20px;color:#6b7280">No hay pedidos registrados en el sistema.</td></tr>
        <?php endif;?>
        <?php foreach($rows as $r):?>
            <tr>
                <td><strong><?=h($r['codigo'])?></strong></td>
                <td>
                    <?=h(mb_substr((string)$r['descripcion'], 0, 180))?><?=mb_strlen((string)$r['descripcion']) > 180 ? '…' : ''?><br>
                    <small style="color:#6b7280">Cant: <b><?=(int)$r['cantidad']?></b> | 📎 <?=(int)$r['total_docs']?> archivo(s)</small>
                    <?php if(!empty($r['formulario_registro_id'])):?><br><small>Origen: <a target="_blank" rel="noopener" href="<?=h(app_url('/php/formularios/llenar.php?id='.(int)$r['formulario_registro_id']))?>">Pedido digital #<?=(int)$r['formulario_registro_id']?></a></small><?php endif;?>
                </td>
                <td>
                    <?php 
                        $pClass = $r['prioridad']==='Critico'?'badge-prio-critico':($r['prioridad']==='Urgente'?'badge-prio-urgente':'badge-prio-normal'); 
                    ?>
                    <span class="badge <?=$pClass?>"><?=h($r['prioridad'])?></span>
                </td>
                <td><?=h($r['proveedor'])?></td>
                <td><strong><?=(float)($r['monto'] ?? 0) > 0 ? h(($r['moneda']==='USD'?'US$':'$').' '.number_format((float)$r['monto'],2,',','.')) : 'Pendiente'?></strong></td>
                <td><span class="badge badge-etapa"><?=(int)$r['etapa']?>/6 - <?=h($etapaNombre[(int)$r['etapa']]??'')?></span></td>
                <td><strong style="color:var(--ng)"><?=h($r['estado_logistico'])?></strong></td>
                <td><?=h($r['actualizado_nombre']??'Sistema')?><br><small style="color:#9ca3af"><?=date('d/m/Y H:i',strtotime($r['actualizado_en']))?></small></td>
                    <td class="module-actions">
                        <a class="btn primary" href="<?=app_url('/php/compras.php?ver='.(int)$r['id'].'#detalleCompra')?>">Ver</a>
                        <?php if($canEdit):?>
                        <?php if((int)$r['etapa'] >= 6 && isset($comprasConRemito[(int)$r['id']]) && !isset($encuestadasIds[(int)$r['id']])):?>
                            <a class="btn primary" href="<?=app_url('/php/compras.php?evaluar='.(int)$r['id'].'#encuesta')?>">Completar recepción</a>
                        <?php endif;?>
                        <?php if((int)$r['etapa'] >= 2):?>
                            <a class="btn secondary" href="<?=app_url('/php/compras.php?presupuesto='.(int)$r['id'].'#documentos')?>">Adjuntar presupuesto</a>
                        <?php endif;?>
                        <a class="btn secondary" href="<?=app_url('/php/compras.php?editar='.(int)$r['id'].'#gestion')?>">Editar</a>
                        <form method="post" onsubmit="return confirm('¿Confirma eliminar este pedido de compra?')"><?=csrf_field()?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                            <button class="btn secondary" style="color:var(--danger)">Eliminar</button>
                        </form>
                        <?php endif;?>
                    </td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

<?php if($compraDetalle):?>
<section class="module-card purchase-detail" id="detalleCompra">
    <div class="module-toolbar" style="margin-top:0">
        <h2>Detalle completo de <?=h($compraDetalle['codigo'])?></h2>
        <div class="module-actions">
            <?php if($canEdit):?><a class="btn secondary" href="<?=app_url('/php/compras.php?editar='.(int)$compraDetalle['id'].'#gestion')?>">Editar pedido</a><?php endif;?>
            <a class="btn secondary" href="<?=app_url('/php/compras.php#seguimiento')?>">Volver al seguimiento</a>
        </div>
    </div>
    <dl class="purchase-detail-grid">
        <div><dt>Código</dt><dd><?=h($compraDetalle['codigo'])?></dd></div>
        <div><dt>Etapa</dt><dd><?=h($etapaNombre[(int)$compraDetalle['etapa']] ?? 'Pedido')?></dd></div>
        <div><dt>Estado logístico</dt><dd><?=h($compraDetalle['estado_logistico'])?></dd></div>
        <div><dt>Cantidad</dt><dd><?=(int)$compraDetalle['cantidad']?></dd></div>
        <div><dt>Prioridad</dt><dd><?=h($compraDetalle['prioridad'])?></dd></div>
        <div><dt>Proveedor</dt><dd><?=h($compraDetalle['proveedor'] ?: 'Pendiente')?></dd></div>
        <div><dt>Monto registrado</dt><dd><?=(float)($compraDetalle['monto'] ?? 0) > 0 ? h(($compraDetalle['moneda'] === 'USD' ? 'US$' : '$') . ' ' . number_format((float)$compraDetalle['monto'], 2, ',', '.')) : 'Pendiente de la orden de compra'?></dd></div>
        <div><dt>Fecha de creación</dt><dd><?=h(date('d/m/Y H:i', strtotime($compraDetalle['fecha_creacion'])))?></dd></div>
        <div><dt>Última modificación</dt><dd><?=h(date('d/m/Y H:i', strtotime($compraDetalle['actualizado_en'])))?> · <?=h($compraDetalle['actualizado_nombre'] ?? 'Sistema')?></dd></div>
        <div class="wide"><dt>Descripción completa</dt><dd><?=h($compraDetalle['descripcion'])?></dd></div>
        <?php if(!empty($compraDetalle['formulario_registro_id'])):?>
        <div class="wide"><dt>Pedido digital de origen</dt><dd><a target="_blank" rel="noopener" href="<?=h(app_url('/php/formularios/llenar.php?id='.(int)$compraDetalle['formulario_registro_id']))?>">Abrir formulario de origen #<?=(int)$compraDetalle['formulario_registro_id']?></a></dd></div>
        <?php endif;?>
    </dl>

    <h4>Documentos y datos de cada etapa</h4>
    <div class="table-wrapper">
        <table class="module-table">
            <thead><tr><th>Etapa</th><th>Tipo</th><th>Archivo y datos guardados</th><th>Fecha / responsable</th><?php if($canEdit):?><th>Acción</th><?php endif;?></tr></thead>
            <tbody>
            <?php $hayDocumentoDetalle = false; foreach($docsCompra as $d): if((int)$d['compra_id'] !== (int)$compraDetalle['id']) continue; $hayDocumentoDetalle = true; ?>
                <tr>
                    <td><?=h($etapaNombre[(int)$d['etapa']] ?? ('Etapa '.$d['etapa']))?></td>
                    <td><?=h($d['tipo_documento'] ?? 'Adjunto')?></td>
                    <td><a target="_blank" rel="noopener" href="<?=h(app_url('/uploads/'.(string)$d['ruta_archivo']))?>">📄 <?=h($d['nombre_archivo'])?></a><?=mostrarDatosDocumentoCompra($d['datos_json'] ?? null, $etiquetasDatosDocumentoCompra, true)?></td>
                    <td><?=h($d['subido_por'] ?? 'Sistema')?><br><small><?=h(date('d/m/Y H:i', strtotime($d['fecha_subida'])))?></small></td>
                    <?php if($canEdit):?><td><a class="btn secondary" href="<?=app_url('/php/compras.php?editar_documento='.(int)$d['id'].'#editarDocumento')?>">Editar datos</a></td><?php endif;?>
                </tr>
            <?php endforeach;?>
            <?php if(!$hayDocumentoDetalle && empty($compraDetalle['formulario_registro_id'])):?><tr><td colspan="<?= $canEdit ? 5 : 4 ?>" style="text-align:center">Todavía no hay archivos adjuntos para esta compra.</td></tr><?php endif;?>
            <?php if(!empty($compraDetalle['formulario_registro_id'])):?>
                <tr><td><?=h($etapaNombre[1])?></td><td>Pedido digital</td><td colspan="<?= $canEdit ? 2 : 1 ?>"><a target="_blank" rel="noopener" href="<?=h(app_url('/php/formularios/llenar.php?id='.(int)$compraDetalle['formulario_registro_id']))?>">📋 Ver formulario de origen #<?=(int)$compraDetalle['formulario_registro_id']?></a></td><td>Formulario vinculado</td></tr>
            <?php endif;?>
            </tbody>
        </table>
    </div>

    <h4>Recepción y encuesta</h4>
    <?php if($encuestaDetalle):?>
    <?php if($canEdit && (int)($_GET['editar_encuesta'] ?? 0) === (int)$compraDetalle['id']):?>
    <div class="document-edit">
        <h4>Editar evaluación de recepción</h4>
        <form method="post" class="module-form"><?=csrf_field()?>
            <input type="hidden" name="accion" value="actualizar_encuesta">
            <input type="hidden" name="compra_id" value="<?=(int)$compraDetalle['id']?>">
            <label>Calidad del producto
                <select name="calidad" required>
                    <?php foreach(['Conforme'=>'Conforme (Cumple especificaciones)','Observada'=>'Observada (Detalles menores)','No Conforme'=>'No Conforme (Rechazado)'] as $valor=>$etiqueta):?><option value="<?=h($valor)?>" <?=$encuestaDetalle['calidad']===$valor?'selected':''?>><?=h($etiqueta)?></option><?php endforeach;?>
                </select>
            </label>
            <label>Estado físico
                <select name="estado_fisico" required>
                    <?php foreach(['Bueno'=>'Bueno / Excelente','Regular'=>'Regular / Embalaje dañado','Malo'=>'Malo / Con faltantes o roturas'] as $valor=>$etiqueta):?><option value="<?=h($valor)?>" <?=$encuestaDetalle['estado_fisico']===$valor?'selected':''?>><?=h($etiqueta)?></option><?php endforeach;?>
                </select>
            </label>
            <label>Modalidad de entrega
                <select name="modalidad_entrega" required>
                    <?php foreach(['Transporte del proveedor','Retiro por NASER','Correo / transporte contratado','Entrega directa en base / depósito','Otro'] as $valor):?><option value="<?=h($valor)?>" <?=$encuestaDetalle['modalidad_entrega']===$valor?'selected':''?>><?=h($valor)?></option><?php endforeach;?>
                </select>
            </label>
            <label>Cumplimiento del plazo
                <select name="entrega" required>
                    <?php foreach(['En término','Demorada'] as $valor):?><option value="<?=h($valor)?>" <?=$encuestaDetalle['cumplimiento_entrega']===$valor?'selected':''?>><?=h($valor)?></option><?php endforeach;?>
                </select>
            </label>
            <label class="full">Observaciones
                <textarea name="observaciones" maxlength="5000" rows="3"><?=h($encuestaDetalle['observaciones'] ?? '')?></textarea>
            </label>
            <div class="full module-actions"><button class="btn primary">Guardar evaluación</button><a class="btn secondary" href="<?=app_url('/php/compras.php?ver='.(int)$compraDetalle['id'].'#detalleCompra')?>">Cancelar</a></div>
        </form>
    </div>
    <?php else:?>
    <dl class="purchase-detail-grid">
        <div><dt>Calidad</dt><dd><?=h($encuestaDetalle['calidad'])?></dd></div>
        <div><dt>Estado físico</dt><dd><?=h($encuestaDetalle['estado_fisico'])?></dd></div>
        <div><dt>Entrega</dt><dd><?=h($encuestaDetalle['cumplimiento_entrega'])?></dd></div>
        <div><dt>Modalidad de entrega</dt><dd><?=h($encuestaDetalle['modalidad_entrega'] ?? '—')?></dd></div>
        <div class="wide"><dt>Observaciones</dt><dd><?=h($encuestaDetalle['observaciones'] ?: 'Sin observaciones')?></dd></div>
    </dl>
    <?php if($canEdit):?><a class="btn secondary" href="<?=app_url('/php/compras.php?ver='.(int)$compraDetalle['id'].'&editar_encuesta='.(int)$compraDetalle['id'].'#detalleCompra')?>">Editar evaluación</a><?php endif;?>
    <?php endif;?>
    <?php else:?>
    <p class="module-permission">Todavía no hay una encuesta de recepción registrada.</p>
    <?php endif;?>

    <?php if($documentoEdicion && $canEdit && (int)$documentoEdicion['compra_id'] === (int)$compraDetalle['id']):?>
    <?php $camposEdicionDocumento = $camposDocumentoCompra[(string)$documentoEdicion['tipo_documento']] ?? []; ?>
    <div class="document-edit" id="editarDocumento">
        <h4>Editar datos: <?=h($documentoEdicion['tipo_documento'])?></h4>
        <p>Podés corregir los datos guardados o elegir un archivo nuevo para reemplazar el adjunto actual.</p>
        <form method="post" enctype="multipart/form-data" class="module-form"><?=csrf_field()?>
            <input type="hidden" name="accion" value="actualizar_documento">
            <input type="hidden" name="documento_id" value="<?=(int)$documentoEdicion['id']?>">
            <?php foreach($camposEdicionDocumento as $campo): $valorCampo=(string)($datosDocumentoEdicion[$campo['name']] ?? ''); ?>
                <label class="<?=($campo['type']==='textarea'?'full':'')?>"><?=h($campo['label'])?><?=!empty($campo['required'])?' *':''?>
                    <?php if($campo['type']==='textarea'):?>
                        <textarea name="datos[<?=h($campo['name'])?>]" maxlength="5000" rows="3" <?=!empty($campo['required'])?'required':''?>><?=h($valorCampo)?></textarea>
                    <?php elseif($campo['type']==='select'):?>
                        <select name="datos[<?=h($campo['name'])?>]" <?=!empty($campo['required'])?'required':''?>>
                            <option value="">Seleccionar opción</option>
                            <?php foreach(($campo['options'] ?? []) as $opcionValor=>$opcionTexto):?><option value="<?=h((string)$opcionValor)?>" <?=$valorCampo===(string)$opcionValor?'selected':''?>><?=h((string)$opcionTexto)?></option><?php endforeach;?>
                        </select>
                    <?php else:?>
                        <input type="<?=h($campo['type'])?>" name="datos[<?=h($campo['name'])?>]" value="<?=h($valorCampo)?>" <?=!empty($campo['required'])?'required':''?> <?=$campo['type']==='number'?'step="0.01" min="'.h((string)($campo['min'] ?? 0)).'"':''?>>
                    <?php endif;?>
                </label>
            <?php endforeach;?>
            <label class="full">Reemplazar archivo (opcional · máximo 10 MB)
                <input type="file" name="archivo_reemplazo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
            </label>
            <div class="full module-actions">
                <button class="btn primary">Guardar cambios</button>
                <a class="btn secondary" href="<?=app_url('/php/compras.php?ver='.(int)$compraDetalle['id'].'#detalleCompra')?>">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif;?>
</section>
<?php endif;?>


<div class="module-toolbar">
    <h2>Repositorio de Documentos Adjuntos</h2>
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
                    <?=mostrarDatosDocumentoCompra($d['datos_json'] ?? null, $etiquetasDatosDocumentoCompra)?>
                </td>
                <td><?=h($d['subido_por']??'Sistema')?><br><small style="color:#9ca3af"><?=h(date('d/m/Y H:i', strtotime($d['fecha_subida'])))?></small></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

<section class="module-card" style="margin-top:25px" id="encuesta">
    <h3>📋 Encuesta de Control de Calidad y Recepción de Producto</h3>
    <?php if(!$canEdit):?>
        <p class="module-permission">Modo consulta: las evaluaciones registradas aparecen en el historial. Para completar una encuesta necesitás permisos de edición en Compras.</p>
    <?php elseif(!$rows):?>
        <p class="module-permission">Todavía no hay compras en seguimiento. Iniciá el ciclo con un pedido manual o vinculá un pedido de Materiales y Servicios. La encuesta se habilita al completar la etapa 6 y adjuntar el remito.</p>
    <?php elseif(!$recepcionesPendientes):?>
        <p class="module-permission">No hay entregas pendientes de evaluación. La encuesta se habilita cuando una compra llega a la etapa 6 y tiene cargado el remito. Revisá el <a href="#seguimiento">seguimiento de compras</a>.</p>
    <?php else:?>
    <p>Al adjuntar el remito en la etapa 6, registrá cómo llegó el producto, su calidad y estado. Cada compra admite una evaluación de recepción.</p>
    <form method="post" class="module-form"><?=csrf_field()?>
        <input type="hidden" name="accion" value="encuesta">
        
        <label class="full">Compra A Evaluar
            <select name="compra_id" required>
                <?php foreach($recepcionesPendientes as $r):?>
                    <option value="<?=(int)$r['id']?>" <?=((int)$r['id']===$encuestaCompraSeleccionada?'selected':'')?>><?=h($r['codigo'].' - '.$r['descripcion'].' ('.$r['proveedor'].')')?></option>
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

        <p class="module-permission full" id="dlgAyuda">Completá los datos del paso y adjuntá el documento de respaldo.</p>
        <div class="module-form full" id="dlgCamposDocumento"></div>

        <label class="full">Archivo (PDF, Word, Excel o imagen · máximo 10 MB)
            <input type="file" name="archivo_etapa" id="dlgArchivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
        </label>

        <p class="module-permission full" id="dlgVacio" hidden></p>

        <div class="full module-actions">
            <button class="btn primary" id="dlgSubir">Subir documento</button>
            <a class="btn secondary" id="dlgNuevo" href="#gestion" hidden>➕ Crear o vincular pedido</a>
        </div>
    </form>
    <?php endif;?>

    <h4>Documentos cargados en este paso</h4>
    <div id="dlgLista"></div>
</dialog>

<?php foreach($pasosFlujo as $n => $titulo):?>
<template id="docs-etapa-<?=$n?>">
    <?php $docsPaso = $docsPorEtapa[$n] ?? []; $pedidosOrigenPaso = $n === 1 ? array_values(array_filter($rows, static fn($r) => !empty($r['formulario_registro_id']))) : []; ?>
    <?php if(!$docsPaso && !$pedidosOrigenPaso):?>
        <p class="module-permission" data-sin-documentos>Todavía no hay documentos cargados para este pedido en este paso.</p>
    <?php else:?>
    <div class="table-wrapper">
        <table class="module-table">
            <thead><tr><th>Pedido</th><th>Tipo</th><th>Archivo / Datos</th><th>Fecha / Responsable</th></tr></thead>
            <tbody>
            <?php foreach($pedidosOrigenPaso as $pedidoOrigen):?>
                <tr data-compra-id="<?=(int)$pedidoOrigen['id']?>">
                    <td><strong><?=h($pedidoOrigen['codigo'])?></strong></td>
                    <td>Pedido digital vinculado</td>
                    <td><a href="<?=h(app_url('/php/formularios/llenar.php?id='.(int)$pedidoOrigen['formulario_registro_id']))?>" target="_blank" rel="noopener" style="color:var(--ng);font-weight:bold;text-decoration:underline">📋 Ver Pedido de Materiales y Servicios #<?=(int)$pedidoOrigen['formulario_registro_id']?></a></td>
                    <td>Formulario digital</td>
                </tr>
            <?php endforeach;?>
            <?php foreach($docsPaso as $d):?>
                <tr data-compra-id="<?=(int)$d['compra_id']?>">
                    <td><strong><?=h($d['codigo'])?></strong></td>
                    <td><?=h($d['tipo_documento']??'Adjunto')?></td>
                    <td><a href="<?=app_url('/uploads/'.h($d['ruta_archivo']))?>" target="_blank" rel="noopener" style="color:var(--ng);font-weight:bold;text-decoration:underline">📄 <?=h($d['nombre_archivo'])?></a><?=mostrarDatosDocumentoCompra($d['datos_json'] ?? null, $etiquetasDatosDocumentoCompra)?></td>
                    <td><?=h($d['subido_por']??'Sistema')?><br><small style="color:#9ca3af"><?=h(date('d/m/Y H:i', strtotime($d['fecha_subida'])))?></small></td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <p class="module-permission" data-sin-documentos hidden>Todavía no hay documentos para este pedido en este paso.</p>
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
    const lista = document.getElementById('dlgLista');
    const camposContenedor = document.getElementById('dlgCamposDocumento');
    const ayuda = document.getElementById('dlgAyuda');
    const camposPorTipo = <?=json_encode($camposDocumentoCompra, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
    const todasCompras = compra ? [...compra.options].map(o => o.cloneNode(true)) : [];
    let paso = 1;

    // Un pedido puede recibir el documento de un paso si ese paso ya está
    // alcanzado, o si es el siguiente y la etapa actual ya tiene su documento.
    const elegible = (o) => {
        const etapa = Number(o.dataset.etapa), actual = Number(o.dataset.docActual);
        return paso <= etapa || (paso === etapa + 1 && actual > 0);
    };
    const filtrarDocumentos = () => {
        if (!lista) return;
        const compraId = compra?.value || '';
        const filas = [...lista.querySelectorAll('[data-compra-id]')];
        let visibles = 0;
        filas.forEach(fila => {
            const mostrar = compraId !== '' && fila.dataset.compraId === compraId;
            fila.hidden = !mostrar;
            if (mostrar) visibles++;
        });
        lista.querySelectorAll('[data-sin-documentos]').forEach(mensaje => {
            mensaje.hidden = filas.length > 0 && visibles > 0;
        });
    };
    const ajustarTipos = () => {
        const sel = compra.selectedOptions[0];
        const avanza = sel ? paso === Number(sel.dataset.etapa) + 1 : false;
        let forzarPresupuesto = false;
        let primero = null;
        [...tipo.options].forEach(o => {
            const ok = Number(o.dataset.etapa) === paso && (!avanza || o.dataset.principal === '1');
            o.hidden = !ok; o.disabled = !ok;
            if (ok && !primero) primero = o;
        });
        if (sel && paso === 2 && Number(sel.dataset.etapa) === 2) {
            const presupuesto = [...tipo.options].find(o => o.value === 'Cotización recibida' && !o.disabled);
            if (presupuesto) { primero = presupuesto; forzarPresupuesto = true; }
        }
        if (primero && (forzarPresupuesto || !tipo.selectedOptions.length || tipo.selectedOptions[0].disabled)) tipo.value = primero.value;
        filtrarDocumentos();
        renderizarCamposDocumento();
    };
    const renderizarCamposDocumento = () => {
        if (!camposContenedor || !tipo) return;
        camposContenedor.replaceChildren();
        const campos = camposPorTipo[tipo.value] || [];
        ayuda.textContent = campos.length
            ? 'Completá los datos obligatorios de este paso y adjuntá el documento de respaldo.'
            : 'Adjuntá el pedido de compra o usá el formulario digital vinculado como respaldo de la solicitud.';
        campos.forEach(campo => {
            const label = document.createElement('label');
            label.className = (campo.type === 'textarea' || campo.name === 'proveedores_solicitados') ? 'full' : '';
            label.textContent = campo.label + (campo.required ? ' *' : '');
            let control;
            if (campo.type === 'textarea') {
                control = document.createElement('textarea');
                control.rows = 3;
            } else if (campo.type === 'select') {
                control = document.createElement('select');
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Seleccionar opción';
                control.appendChild(placeholder);
                Object.entries(campo.options || {}).forEach(([valor, texto]) => {
                    const opcion = document.createElement('option');
                    opcion.value = valor;
                    opcion.textContent = texto;
                    control.appendChild(opcion);
                });
            } else {
                control = document.createElement('input');
                control.type = campo.type || 'text';
                if (campo.type === 'number') { control.step = '0.01'; control.min = String(campo.min || 0); }
            }
            control.name = 'datos[' + campo.name + ']';
            control.required = !!campo.required;
            if (campo.type === 'textarea') control.maxLength = 5000;
            else if (campo.type !== 'select') control.maxLength = 300;
            label.appendChild(control);
            camposContenedor.appendChild(label);
        });
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
            const faltaPresupuesto = paso === 3 && todasCompras.some(o =>
                Number(o.dataset.etapa) === 2 && Number(o.dataset.docActual) === 0);
            vacio.textContent = todasCompras.length === 0
                ? 'Todavía no hay pedidos. Creá uno primero.'
                : (faltaPresupuesto
                    ? 'Para emitir la orden de compra, cargá primero el presupuesto o cotización recibida en el paso 2.'
                    : 'Ningún pedido está listo para este paso. Cargá primero el documento requerido del paso anterior.');
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
    if (tipo) tipo.addEventListener('change', renderizarCamposDocumento);

    // Enlace "Adjuntar presupuesto" de la tabla: abre el paso 2 con el pedido elegido.
    const presupuestoId = <?=(int)$presupuestoCompraId?>;
    if (presupuestoId && canEdit) {
        abrir(2);
        if ([...compra.options].some(o => Number(o.value) === presupuestoId)) {
            compra.value = String(presupuestoId);
            ajustarTipos();
            tipo.value = 'Cotización recibida';
            renderizarCamposDocumento();
        }
    }
})();
</script>
</body>
</html>
