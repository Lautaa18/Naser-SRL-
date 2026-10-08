<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require_once __DIR__ . '/config/formularios_catalogo.php';

$sectorHseq = sectorPorSlug($pdo, 'hseq');
if (!$sectorHseq) { http_response_code(404); exit('No se encontró el sector HSEQ.'); }
$sid = (int)$sectorHseq['id'];
if (!puedeVerSector($pdo, $sid)) { http_response_code(403); exit('No tenés permiso para acceder al sector HSEQ.'); }
$uid = (int)$_SESSION['usuario_id'];
$puedeGestionar = puedeEditarSector($pdo, $sid);
$puedeCompletar = puedeCompletarSector($pdo, $sid);
$mensaje = '';
$tipoMensaje = 'success';
if (isset($_SESSION['hseq_flash'])) {
    $mensaje = (string)$_SESSION['hseq_flash'];
    unset($_SESSION['hseq_flash']);
}

function hseqFechaValida(string $fecha, bool $opcional = false): bool {
    if ($fecha === '') return $opcional;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}
function hseqTexto(string $valor, int $max = 500): string {
    return mb_substr(trim($valor), 0, $max);
}
function hseqCampo(array $datos, string $clave): string {
    $valor = $datos['#' . $clave] ?? $datos[$clave] ?? '';
    return is_scalar($valor) ? trim((string)$valor) : '';
}
function hseqFechaLegible(?string $fecha): string {
    return $fecha && $fecha !== '0000-00-00' ? date('d/m/Y', strtotime($fecha)) : '—';
}
function hseqEstadoFormulario(string $estado): string {
    return match ($estado) {
        'enviado' => 'Pendiente de revisión', 'aprobado' => 'Revisado', 'rechazado' => 'Requiere corrección', default => ucfirst($estado)
    };
}
function hseqCsvSeguro(string $valor): string {
    return preg_match('/^[=+@\-]/u', $valor) ? "'" . $valor : $valor;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'observacion_guardar') {
            if (!$puedeCompletar) throw new RuntimeException('Tu usuario puede consultar HSEQ, pero no registrar observaciones.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id && !$puedeGestionar) throw new RuntimeException('Solo el responsable HSEQ puede modificar una observación existente.');
            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $lugar = hseqTexto((string)($_POST['lugar'] ?? ''), 180);
            $categoria = (string)($_POST['categoria'] ?? '');
            $descripcion = hseqTexto((string)($_POST['descripcion'] ?? ''), 5000);
            $accionPropuesta = hseqTexto((string)($_POST['accion_propuesta'] ?? ''), 5000);
            $responsable = hseqTexto((string)($_POST['responsable'] ?? ''), 150);
            $compromiso = trim((string)($_POST['fecha_compromiso'] ?? ''));
            $prioridad = (string)($_POST['prioridad'] ?? 'Media');
            if (!hseqFechaValida($fecha) || !$lugar || !$descripcion) throw new RuntimeException('Completá una fecha válida, el lugar y la descripción.');
            if (!in_array($categoria, ['Acto inseguro', 'Condición insegura', 'Mejora'], true)) throw new RuntimeException('Elegí una categoría válida.');
            if (!in_array($prioridad, ['Baja', 'Media', 'Alta', 'Crítica'], true)) throw new RuntimeException('Elegí una prioridad válida.');
            if (!hseqFechaValida($compromiso, true)) throw new RuntimeException('La fecha compromiso no es válida.');
            if ($id) {
                $st = $pdo->prepare('UPDATE hseq_observaciones SET fecha=?, lugar=?, categoria=?, descripcion=?, accion_propuesta=?, responsable=?, fecha_compromiso=?, prioridad=?, actualizado_por=? WHERE id=? AND sector_id=?');
                $st->execute([$fecha, $lugar, $categoria, $descripcion, $accionPropuesta ?: null, $responsable ?: null, $compromiso ?: null, $prioridad, $uid, $id, $sid]);
                registrarActividad($pdo, 'hseq_observacion_actualizada', "Observación preventiva #$id");
                $mensaje = 'Observación actualizada.';
            } else {
                $st = $pdo->prepare('INSERT INTO hseq_observaciones (sector_id, fecha, lugar, categoria, descripcion, accion_propuesta, responsable, fecha_compromiso, prioridad, creado_por, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$sid, $fecha, $lugar, $categoria, $descripcion, $accionPropuesta ?: null, $responsable ?: null, $compromiso ?: null, $prioridad, $uid, $uid]);
                $nuevoId = (int)$pdo->lastInsertId();
                registrarActividad($pdo, 'hseq_observacion_creada', "Observación preventiva #$nuevoId");
                $responsables = array_column(usuariosConRol($pdo, $sid, 'responsable'), 'id');
                notificar($pdo, array_diff($responsables, [$uid]), 'Nueva observación preventiva HSEQ', "Se registró la observación #$nuevoId en $lugar. Revisá la acción y su fecha compromiso.", '/php/hseq.php#observaciones', 'info', 'hseq-observacion:' . $nuevoId);
                $mensaje = 'Observación preventiva registrada.';
            }
            $_SESSION['hseq_flash'] = $mensaje;
            header('Location: ' . app_url('/php/hseq.php#observaciones'));
            exit;
        } elseif ($accion === 'observacion_estado') {
            if (!$puedeGestionar) throw new RuntimeException('Solo el responsable HSEQ puede actualizar el seguimiento.');
            $id = (int)($_POST['id'] ?? 0);
            $estado = (string)($_POST['estado'] ?? '');
            $cierre = hseqTexto((string)($_POST['cierre'] ?? ''), 2000);
            if (!$id || !in_array($estado, ['abierta', 'en_curso', 'cerrada'], true)) throw new RuntimeException('El estado indicado no es válido.');
            if ($estado === 'cerrada' && !$cierre) throw new RuntimeException('Dejá una nota de cierre antes de marcar la observación como cerrada.');
            $st = $pdo->prepare('UPDATE hseq_observaciones SET estado=?, cierre=?, actualizado_por=? WHERE id=? AND sector_id=?');
            $st->execute([$estado, $estado === 'cerrada' ? $cierre : null, $uid, $id, $sid]);
            registrarActividad($pdo, 'hseq_observacion_estado', "Observación preventiva #$id: $estado");
            $_SESSION['hseq_flash'] = 'Seguimiento de la observación actualizado.';
            header('Location: ' . app_url('/php/hseq.php#observaciones'));
            exit;
        } elseif ($accion === 'encuesta_guardar') {
            if (!$puedeGestionar) throw new RuntimeException('Solo el responsable HSEQ puede administrar encuestas de satisfacción.');
            $id = (int)($_POST['id'] ?? 0);
            $cliente = hseqTexto((string)($_POST['cliente'] ?? ''), 180);
            $servicio = hseqTexto((string)($_POST['servicio'] ?? ''), 120);
            $fecha = trim((string)($_POST['fecha_encuesta'] ?? ''));
            $puntaje = (int)($_POST['puntaje_general'] ?? 0);
            $calidad = (int)($_POST['calidad_servicio'] ?? 0);
            $cumplimiento = (int)($_POST['cumplimiento'] ?? 0);
            $comunicacion = (int)($_POST['comunicacion'] ?? 0);
            $seguridad = (int)($_POST['seguridad'] ?? 0);
            $comentarios = hseqTexto((string)($_POST['comentarios'] ?? ''), 5000);
            if (!$cliente || !hseqFechaValida($fecha) || $puntaje < 1 || $puntaje > 5) throw new RuntimeException('Completá el cliente, la fecha y una satisfacción general de 1 a 5.');
            foreach ([$calidad, $cumplimiento, $comunicacion, $seguridad] as $valor) if ($valor < 1 || $valor > 5) throw new RuntimeException('Las cuatro calificaciones deben estar entre 1 y 5.');
            if ($id) {
                $st = $pdo->prepare('UPDATE hseq_encuestas_cliente SET cliente=?, servicio=?, fecha_encuesta=?, puntaje_general=?, calidad_servicio=?, cumplimiento=?, comunicacion=?, seguridad=?, comentarios=?, actualizado_por=? WHERE id=? AND sector_id=?');
                $st->execute([$cliente, $servicio ?: null, $fecha, $puntaje, $calidad, $cumplimiento, $comunicacion, $seguridad, $comentarios ?: null, $uid, $id, $sid]);
                registrarActividad($pdo, 'hseq_encuesta_actualizada', "Encuesta de satisfacción #$id");
                $mensaje = 'Encuesta actualizada.';
            } else {
                $st = $pdo->prepare('INSERT INTO hseq_encuestas_cliente (sector_id, cliente, servicio, fecha_encuesta, puntaje_general, calidad_servicio, cumplimiento, comunicacion, seguridad, comentarios, creado_por, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$sid, $cliente, $servicio ?: null, $fecha, $puntaje, $calidad, $cumplimiento, $comunicacion, $seguridad, $comentarios ?: null, $uid, $uid]);
                registrarActividad($pdo, 'hseq_encuesta_creada', "Encuesta de satisfacción #" . $pdo->lastInsertId());
                $mensaje = 'Encuesta de satisfacción registrada.';
            }
            $_SESSION['hseq_flash'] = $mensaje;
            header('Location: ' . app_url('/php/hseq.php#calidad'));
            exit;
        } else {
            throw new RuntimeException('La acción solicitada no es válida.');
        }
    } catch (Throwable $e) {
        $mensaje = $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar la información. Revisá los datos e intentá nuevamente.';
        $tipoMensaje = 'error';
    }
}

$yearNow = (int)date('Y');
$anio = filter_var($_GET['anio'] ?? $yearNow, FILTER_VALIDATE_INT);
if (!$anio || $anio < 2000 || $anio > $yearNow + 1) $anio = $yearNow;
$inicioAnio = sprintf('%04d-01-01', $anio);
$finAnio = sprintf('%04d-12-31', $anio);
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$accidentesPorMes = array_fill(1, 12, 0);
$accidentesMesActualReal = 0;
$accidentesMesAnteriorReal = 0;
$fechaMesActual = new DateTimeImmutable('first day of this month');
$fechaMesAnterior = $fechaMesActual->modify('-1 month');
$auditoriasEstado = ['borrador' => 0, 'enviado' => 0, 'aprobado' => 0, 'rechazado' => 0];
$accidentes = [];
$auditorias = [];
$formulariosIncidente = ['hseq-declaracion-incidente', 'hseq-listado-incidentes'];
$codigosAuditoria = ['hseq-plan-anual-auditorias', 'hseq-programa-auditoria', 'hseq-informe-auditoria', 'hseq-revision-direccion'];
$placeholdersInc = implode(',', array_fill(0, count($formulariosIncidente), '?'));
$placeholdersAud = implode(',', array_fill(0, count($codigosAuditoria), '?'));
$visibilidadBorrador = $puedeGestionar ? '1=1' : '(r.estado <> \'borrador\' OR r.creado_por = ?)';
$paramsInc = array_merge([$sid], $formulariosIncidente);
$st = $pdo->prepare("SELECT r.id, r.formulario, r.referencia, r.estado, r.datos_json, r.storage_json, r.creado_en, r.enviado_en, r.actualizado_en, u.nombre AS autor
    FROM formularios_registros r LEFT JOIN usuarios u ON u.id=r.creado_por
    WHERE r.sector_id=? AND r.formulario IN ($placeholdersInc) AND r.estado <> 'borrador'
    ORDER BY COALESCE(r.enviado_en,r.creado_en) DESC");
$st->execute($paramsInc);
$eventosIncidente = [];
$clavesIncidente = [];
$claveIncidente = static function (string $fecha, string $persona, string $detalle): string {
    $normalizar = static fn(string $s): string => preg_replace('/\s+/u', ' ', mb_strtolower(trim($s))) ?? trim($s);
    return $fecha . '|' . ($persona !== '' ? 'persona:' . $normalizar($persona) : 'detalle:' . $normalizar($detalle));
};
$agregarIncidente = static function (array $evento, string $origen) use (&$eventosIncidente, &$clavesIncidente, $claveIncidente): void {
    $datos = $evento['datos_obj'];
    $fecha = (string)($evento['fecha_evento'] ?? '');
    $persona = hseqCampo($datos, 'nombreApellido');
    $detalle = hseqCampo($datos, 'descripcion');
    if ($detalle === '') $detalle = hseqCampo($datos, 'q6');
    if ($detalle === '') $detalle = trim(hseqCampo($datos, 'tipo') . ' ' . hseqCampo($datos, 'diasBaja') . ' ' . hseqCampo($datos, 'parteCuerpo'));
    $clave = $claveIncidente($fecha, $persona, $detalle);
    if (!isset($clavesIncidente[$clave])) {
        $clavesIncidente[$clave] = count($eventosIncidente);
        $eventosIncidente[] = $evento + ['origen_evento' => $origen, 'detalle_evento' => $detalle];
    } elseif ($origen === 'listado') {
        $indice = $clavesIncidente[$clave];
        $previo = $eventosIncidente[$indice];
        $datosPrevios = $previo['datos_obj'] ?? [];
        foreach ($datosPrevios as $campo => $valor) {
            if (trim((string)($datos[$campo] ?? '')) === '' && trim((string)$valor) !== '') $datos[$campo] = $valor;
        }
        $evento['datos_obj'] = $datos;
        if ($detalle === '') $detalle = hseqCampo($datos, 'q6');
        $evento['origen_evento'] = $origen;
        $evento['detalle_evento'] = $detalle;
        $eventosIncidente[$indice] = array_merge($previo, $evento);
    }
};
foreach ($st->fetchAll() as $r) {
    $datos = json_decode((string)$r['datos_json'], true);
    if (!is_array($datos)) $datos = [];
    $fechaFallback = substr((string)($r['enviado_en'] ?: $r['creado_en']), 0, 10);
    if ($r['formulario'] === 'hseq-listado-incidentes') {
        $storage = json_decode((string)($r['storage_json'] ?? ''), true);
        $listaGuardada = json_decode((string)($storage['NASER_PGSN10_F2'] ?? ''), true);
        $filas = is_array($listaGuardada['incidentes'] ?? null) ? $listaGuardada['incidentes'] : [];
        foreach ($filas as $fila) {
            if (!is_array($fila)) continue;
            $datosEvento = [
                'nombreApellido' => (string)($fila['personal'] ?? ''),
                'descripcion' => (string)($fila['incidente'] ?? ''),
                'tipo' => (string)($fila['tipo'] ?? ''),
                'diasBaja' => (string)($fila['diasBaja'] ?? ''),
                'lugarIncidente' => 'Registro de incidentes',
            ];
            if (!array_filter([$fila['fecha'] ?? '', $fila['incidente'] ?? '', $fila['tipo'] ?? '', $fila['personal'] ?? '', $fila['diasBaja'] ?? '', $fila['parteCuerpo'] ?? '', $fila['observaciones'] ?? ''], fn($v) => trim((string)$v) !== '')) continue;
            $fechaEvento = trim((string)($fila['fecha'] ?? ''));
            if (!hseqFechaValida($fechaEvento)) $fechaEvento = $fechaFallback;
            $evento = $r;
            $evento['datos_obj'] = $datosEvento;
            $evento['fecha_evento'] = $fechaEvento;
            $evento['referencia'] = ($fila['personal'] ?? '') ?: ($r['referencia'] ?? '');
            $agregarIncidente($evento, 'listado');
        }
    } else {
        $fechaEvento = hseqCampo($datos, 'fechaDeclaracion');
        if (!hseqFechaValida($fechaEvento)) $fechaEvento = $fechaFallback;
        $r['datos_obj'] = $datos;
        $r['fecha_evento'] = $fechaEvento;
        $r['detalle_evento'] = hseqCampo($datos, 'q6');
        $agregarIncidente($r, 'declaracion');
    }
}
foreach ($eventosIncidente as $evento) {
    $fechaEvento = (string)$evento['fecha_evento'];
    if (substr($fechaEvento, 0, 7) === $fechaMesActual->format('Y-m')) $accidentesMesActualReal++;
    if (substr($fechaEvento, 0, 7) === $fechaMesAnterior->format('Y-m')) $accidentesMesAnteriorReal++;
    if (substr($fechaEvento, 0, 4) === (string)$anio) {
        $mes = (int)substr($fechaEvento, 5, 2);
        if ($mes >= 1 && $mes <= 12) $accidentesPorMes[$mes]++;
        $accidentes[] = $evento;
    }
}
usort($accidentes, fn($a, $b) => strcmp((string)$b['fecha_evento'], (string)$a['fecha_evento']));
$accidentesAnio = count($accidentes);
$accidentesConBaja = count(array_filter($accidentes, fn($a) => (int)($a['datos_obj']['diasBaja'] ?? 0) > 0));

$paramsAud = array_merge([$sid], $codigosAuditoria, $puedeGestionar ? [] : [$uid]);
$st = $pdo->prepare("SELECT r.estado, COUNT(*) AS cantidad
    FROM formularios_registros r
    WHERE r.sector_id=? AND r.formulario IN ($placeholdersAud) AND $visibilidadBorrador
      AND r.creado_en >= ? AND r.creado_en < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY r.estado");
$st->execute(array_merge($paramsAud, [$inicioAnio, $finAnio]));
foreach ($st->fetchAll() as $fila) if (isset($auditoriasEstado[$fila['estado']])) $auditoriasEstado[$fila['estado']] = (int)$fila['cantidad'];
$st = $pdo->prepare("SELECT r.id, r.formulario, r.referencia, r.estado, r.creado_en, r.enviado_en, r.revisado_en, u.nombre AS autor
    FROM formularios_registros r LEFT JOIN usuarios u ON u.id=r.creado_por
    WHERE r.sector_id=? AND r.formulario IN ($placeholdersAud) AND $visibilidadBorrador
      AND r.creado_en >= ? AND r.creado_en < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY r.creado_en DESC LIMIT 100");
$st->execute(array_merge($paramsAud, [$inicioAnio, $finAnio]));
$auditorias = $st->fetchAll();

$st = $pdo->prepare('SELECT COUNT(*) FROM formularios_registros WHERE sector_id=? AND formulario IN (' . $placeholdersInc . ') AND estado=\'enviado\'');
$st->execute(array_merge([$sid], $formulariosIncidente));
$accidentesPendientes = (int)$st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM formularios_registros WHERE sector_id=? AND formulario IN (' . $placeholdersInc . ') AND estado=\'rechazado\'');
$st->execute(array_merge([$sid], $formulariosIncidente));
$accidentesCorregir = (int)$st->fetchColumn();
$accidentesMes = $accidentesMesActualReal;
$accidentesMesAnterior = $accidentesMesAnteriorReal;
$alertaTendencia = $accidentesMes >= 3 && ($accidentesMesAnterior === 0 || $accidentesMes >= $accidentesMesAnterior * 1.5);

$st = $pdo->prepare('SELECT MONTH(fecha_encuesta) AS mes, AVG(puntaje_general) AS promedio, COUNT(*) AS cantidad FROM hseq_encuestas_cliente WHERE sector_id=? AND fecha_encuesta BETWEEN ? AND ? GROUP BY MONTH(fecha_encuesta)');
$st->execute([$sid, $inicioAnio, $finAnio]);
$satisfaccionPorMes = array_fill(1, 12, ['suma' => 0, 'cantidad' => 0]);
foreach ($st->fetchAll() as $fila) $satisfaccionPorMes[(int)$fila['mes']] = ['suma' => (float)$fila['promedio'] * (int)$fila['cantidad'], 'cantidad' => (int)$fila['cantidad']];
$st = $pdo->prepare('SELECT COUNT(*), AVG(puntaje_general) FROM hseq_encuestas_cliente WHERE sector_id=? AND fecha_encuesta BETWEEN ? AND ?');
$st->execute([$sid, $inicioAnio, $finAnio]);
$encuestaResumen = $st->fetch(PDO::FETCH_NUM);
$encuestasTotal = (int)($encuestaResumen[0] ?? 0);
$satisfaccionPromedio = $encuestaResumen[1] !== null ? round((float)$encuestaResumen[1], 1) : 0;
$st = $pdo->prepare('SELECT id, cliente, servicio, fecha_encuesta, puntaje_general, calidad_servicio, cumplimiento, comunicacion, seguridad, comentarios FROM hseq_encuestas_cliente WHERE sector_id=? AND fecha_encuesta BETWEEN ? AND ? ORDER BY fecha_encuesta DESC, id DESC LIMIT 100');
$st->execute([$sid, $inicioAnio, $finAnio]);
$encuestas = $st->fetchAll();

$obsEdit = null;
if ($puedeGestionar && !empty($_GET['editar_obs'])) {
    $st = $pdo->prepare('SELECT * FROM hseq_observaciones WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar_obs'], $sid]);
    $obsEdit = $st->fetch() ?: null;
}
$encuestaEdit = null;
if ($puedeGestionar && !empty($_GET['editar_encuesta'])) {
    $st = $pdo->prepare('SELECT * FROM hseq_encuestas_cliente WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar_encuesta'], $sid]);
    $encuestaEdit = $st->fetch() ?: null;
}
$st = $pdo->prepare("SELECT COUNT(*) AS total,
    SUM(estado='abierta') AS abiertas,
    SUM(estado='en_curso') AS en_curso,
    SUM(estado='cerrada') AS cerradas,
    SUM(estado <> 'cerrada' AND fecha_compromiso IS NOT NULL AND fecha_compromiso < CURDATE()) AS vencidas
    FROM hseq_observaciones WHERE sector_id=?");
$st->execute([$sid]);
$observacionesStats = $st->fetch() ?: ['total'=>0,'abiertas'=>0,'en_curso'=>0,'cerradas'=>0,'vencidas'=>0];
$st = $pdo->prepare("SELECT COUNT(*) AS total,
    SUM(estado='abierta') AS abiertas,
    SUM(estado='en_curso') AS en_curso,
    SUM(estado='cerrada') AS cerradas,
    SUM(estado <> 'cerrada' AND fecha_compromiso IS NOT NULL AND fecha_compromiso < CURDATE()) AS vencidas
    FROM hseq_observaciones WHERE sector_id=? AND fecha BETWEEN ? AND ?");
$st->execute([$sid, $inicioAnio, $finAnio]);
$observacionesAnioStats = $st->fetch() ?: ['total'=>0,'abiertas'=>0,'en_curso'=>0,'cerradas'=>0,'vencidas'=>0];
$st = $pdo->prepare('SELECT o.*, u.nombre AS autor FROM hseq_observaciones o LEFT JOIN usuarios u ON u.id=o.creado_por WHERE o.sector_id=? ORDER BY FIELD(o.estado,\'abierta\',\'en_curso\',\'cerrada\'), o.fecha_compromiso IS NULL, o.fecha_compromiso ASC, o.fecha DESC LIMIT 100');
$st->execute([$sid]);
$observaciones = $st->fetchAll();
$vencimientos = vencimientosProximos($pdo, 45, [$sid]);
if (!puedeVerHabilitaciones($pdo)) $vencimientos = array_values(array_filter($vencimientos, fn($v) => ($v['origen'] ?? '') !== 'emp'));
$vencidos = count(array_filter($vencimientos, fn($v) => (int)$v['dias'] < 0));
$porVencer = count(array_filter($vencimientos, fn($v) => (int)$v['dias'] >= 0));

if (($_GET['exportar'] ?? '') === 'informe') {
    $nombreArchivo = 'informe-hseq-' . $anio . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['Indicador', 'Período', 'Valor', 'Detalle'], ';');
    foreach ($meses as $i => $etiqueta) {
        $mes = $i + 1;
        $encuestaMes = $satisfaccionPorMes[$mes];
        $prom = $encuestaMes['cantidad'] ? round($encuestaMes['suma'] / $encuestaMes['cantidad'], 1) : 'Sin respuestas';
        fputcsv($salida, ['Registros de incidentes', $etiqueta . ' ' . $anio, $accidentesPorMes[$mes], 'Declaraciones y filas del listado, sin duplicados por fecha y persona'], ';');
        fputcsv($salida, ['Satisfacción general promedio', $etiqueta . ' ' . $anio, $prom, $encuestaMes['cantidad'] . ' respuestas (escala 1 a 5)'], ';');
    }
    foreach ($auditoriasEstado as $estado => $cantidad) fputcsv($salida, ['Auditorías y revisión', $anio, $cantidad, hseqEstadoFormulario($estado)], ';');
    foreach (['abierta'=>'Abiertas','en_curso'=>'En curso','cerrada'=>'Cerradas'] as $estado => $etiqueta) fputcsv($salida, ['Observaciones preventivas', $anio, (int)($observacionesAnioStats[$estado === 'en_curso' ? 'en_curso' : ($estado === 'abierta' ? 'abiertas' : 'cerradas')] ?? 0), $etiqueta], ';');
    fputcsv($salida, ['Observaciones con plazo vencido', $anio, (int)$observacionesAnioStats['vencidas'], 'Requieren seguimiento'], ';');
    foreach ($vencimientos as $v) fputcsv($salida, ['Vencimiento', hseqFechaLegible($v['fecha']), $v['dias'] < 0 ? 'Vencido' : 'Próximo', hseqCsvSeguro($v['titulo'] . ' — ' . $v['detalle'])], ';');
    fclose($salida);
    exit;
}

$maxAccidentesMes = max(1, max($accidentesPorMes));
$maxAuditorias = max(1, max($auditoriasEstado));
$opcionesAnio = range(max(2020, $yearNow - 5), $yearNow + 1);
$docSt = $pdo->prepare("SELECT id,titulo,tipo,estado,fecha_actualizacion,fecha_vencimiento,archivo FROM documentos WHERE sector_id=? AND activo=1 ORDER BY fecha_actualizacion DESC LIMIT 80");
$docSt->execute([$sid]);
$documentos = $docSt->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HSEQ | NASER SGI</title>
    <link rel="stylesheet" href="<?= asset('/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/css/modules.css') ?>">
    <link rel="stylesheet" href="<?= asset('/css/checklists.css') ?>">
    <link rel="stylesheet" href="<?= asset('/css/hseq.css') ?>">
</head>
<body>
<div class="app">
    <?php sidebar($pdo, 'hseq'); ?>
    <main class="content hseq-page">
        <header class="section-top hseq-heading">
            <div>
                <p class="eyebrow">SALUD, SEGURIDAD, MEDIO AMBIENTE Y CALIDAD</p>
                <h1>Gestión HSEQ</h1>
                <p>Seguimiento de incidentes, vencimientos, auditorías, clientes y acciones preventivas.</p>
            </div>
            <div class="top-actions">
                <a class="btn secondary" href="<?=h(app_url('/php/hseq.php?exportar=informe&anio=' . $anio))?>">Descargar informe anual</a>
                <?php if (puedeGestionarDocumentos($pdo)): ?>
                    <a class="btn primary" href="<?= app_url('/php/admin/documentos.php') ?>">Cargar documento HSEQ</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($mensaje): ?><div class="alert <?=h($tipoMensaje)?>" role="status"><?=h($mensaje)?></div><?php endif; ?>

        <section class="hseq-shortcuts" aria-label="Secciones de Gestión HSEQ">
            <a class="hseq-shortcut" href="#informes"><span>01</span><strong>Informes</strong><small>Documentos y resumen de gestión</small></a>
            <a class="hseq-shortcut" href="#formularios"><span>02</span><strong>Formularios a completar</strong><small>Acceso a los formularios actuales</small></a>
            <a class="hseq-shortcut" href="#notificaciones-accidentes"><span>03</span><strong>Accidentes</strong><small>Notificaciones y seguimiento</small></a>
            <a class="hseq-shortcut" href="#vencimientos"><span>04</span><strong>Vencimientos</strong><small>Alertas y fechas próximas</small></a>
            <a class="hseq-shortcut" href="#calidad"><span>05</span><strong>Calidad</strong><small>Auditorías y satisfacción</small></a>
            <a class="hseq-shortcut" href="#grafico-accidentes"><span>06</span><strong>Gráfico de accidentes</strong><small>Tendencias y alertas</small></a>
            <a class="hseq-shortcut" href="#observaciones"><span>07</span><strong>Observaciones preventivas</strong><small>Acciones, responsables y plazos</small></a>
        </section>

        <form class="hseq-period" method="get">
            <label for="anio">Período de indicadores</label>
            <select id="anio" name="anio">
                <?php foreach ($opcionesAnio as $opcion): ?><option value="<?=$opcion?>" <?=$opcion === (int)$anio ? 'selected' : ''?>><?=$opcion?></option><?php endforeach; ?>
            </select>
            <button class="btn secondary">Actualizar gráficos</button>
            <span>Los informes descargados usan este mismo período.</span>
        </form>

        <section class="hseq-kpis" aria-label="Resumen HSEQ">
            <article><span>Accidentes y notificaciones · <?=$anio?></span><strong><?=$accidentesAnio?></strong><small><?=$accidentesPendientes?> pendientes de revisión</small></article>
            <article class="is-warn"><span>Vencimientos en seguimiento</span><strong><?=count($vencimientos)?></strong><small><?=$vencidos?> vencidos · <?=$porVencer?> próximos</small></article>
            <article><span>Satisfacción general · <?=$anio?></span><strong><?=$encuestasTotal ? h(number_format($satisfaccionPromedio, 1, ',', '.')) . ' / 5' : '—'?></strong><small><?=$encuestasTotal?> respuesta(s) registradas</small></article>
            <article class="<?=((int)$observacionesStats['vencidas'] > 0 ? 'is-danger' : '')?>"><span>Observaciones abiertas</span><strong><?= (int)$observacionesStats['abiertas'] + (int)$observacionesStats['en_curso'] ?></strong><small><?=(int)$observacionesStats['vencidas']?> con plazo vencido</small></article>
        </section>

        <section class="hseq-section" id="informes">
            <div class="section-head"><div><p class="eyebrow">01 · INFORMES</p><h2>Informes y documentación HSEQ</h2><p>Consultá los documentos vigentes y descargá un resumen anual con indicadores y alertas.</p></div><a class="btn secondary" href="<?=h(app_url('/php/sector.php?sector=hseq&tipo=documentacion'))?>">Ver todos los informes</a></div>
            <div class="table-panel"><div class="table-wrap"><table><thead><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Actualizado</th><th>Vencimiento</th><th></th></tr></thead><tbody>
                <?php if (!$documentos): ?><tr><td colspan="6" class="muted" style="text-align:center">Todavía no hay documentos cargados en HSEQ.</td></tr><?php endif; ?>
                <?php foreach ($documentos as $doc): ?><tr><td><strong><?=h($doc['titulo'])?></strong></td><td><?=h(ucfirst($doc['tipo']))?></td><td><span class="status-pill <?=h($doc['estado'] ?? 'aprobado')?>"><?=h($doc['estado'] ?? 'aprobado')?></span></td><td><?=hseqFechaLegible($doc['fecha_actualizacion'])?></td><td><?=hseqFechaLegible($doc['fecha_vencimiento'])?></td><td><?php if ($doc['archivo']): ?><a class="btn secondary small" href="<?=h(app_url('/uploads/' . rawurlencode($doc['archivo'])))?>" target="_blank" rel="noopener">Abrir</a><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>

        <section class="hseq-section" id="formularios-seccion">
            <div class="section-head"><div><p class="eyebrow">02 · FORMULARIOS A COMPLETAR</p><h2>Formularios HSEQ existentes</h2><p>Los formularios y su circuito de revisión siguen disponibles tal como están implementados.</p></div><a class="btn secondary" href="<?=h(app_url('/php/sector.php?sector=hseq&tipo=procedimiento'))?>">Ver procedimientos</a></div>
            <?php formulariosPanel($pdo, 'hseq'); ?>
        </section>

        <section class="hseq-section" id="notificaciones-accidentes">
            <div class="section-head"><div><p class="eyebrow">03 · NOTIFICACIONES DE ACCIDENTES</p><h2>Registro y revisión de incidentes</h2><p>Consolida las declaraciones enviadas y las filas del listado de incidentes, evitando duplicados por fecha y persona.</p></div>
                <?php if ($puedeCompletar): ?><a class="btn primary" href="<?=h(app_url('/php/formularios/llenar.php?f=hseq-declaracion-incidente'))?>">Registrar incidente</a><?php else: ?><a class="btn secondary" href="<?=h(app_url('/php/formularios/index.php?sector=hseq&f=hseq-declaracion-incidente'))?>">Ver declaraciones</a><?php endif; ?>
            </div>
            <div class="hseq-alert-list">
                <?php if ($accidentesPendientes): ?><div class="hseq-alert warn"><strong><?=$accidentesPendientes?> notificación(es) para revisar</strong><span>El responsable del sector HSEQ puede abrirlas y aprobarlas.</span><a href="<?=h(app_url('/php/formularios/index.php?sector=hseq&estado=enviado'))?>">Ir a pendientes →</a></div><?php endif; ?>
                <?php if ($accidentesCorregir): ?><div class="hseq-alert danger"><strong><?=$accidentesCorregir?> declaración(es) devuelta(s)</strong><span>Hay formularios con correcciones pendientes.</span><a href="<?=h(app_url('/php/formularios/index.php?sector=hseq&estado=rechazado'))?>">Ver para corregir →</a></div><?php endif; ?>
                <?php if (!$accidentesPendientes && !$accidentesCorregir): ?><div class="hseq-alert ok"><strong>Sin revisiones pendientes</strong><span>Las notificaciones no tienen revisiones pendientes en este momento.</span></div><?php endif; ?>
            </div>
            <div class="table-panel"><div class="table-wrap"><table><thead><tr><th>Fecha del hecho</th><th>Persona / referencia</th><th>Lugar y detalle</th><th>Origen</th><th>Estado</th><th>Registró</th><th></th></tr></thead><tbody>
                <?php if (!$accidentes): ?><tr><td colspan="7" class="muted" style="text-align:center">No hay notificaciones de accidentes para <?=$anio?>.</td></tr><?php endif; ?>
                <?php foreach (array_slice($accidentes, 0, 100) as $acc): $d=$acc['datos_obj']; $persona=hseqCampo($d,'nombreApellido') ?: ($acc['referencia'] ?: '—'); $lugar=hseqCampo($d,'lugarIncidente') ?: '—'; $diasBaja=(int)hseqCampo($d,'diasBaja'); $titulo=$acc['origen_evento']==='listado'?'Listado de incidentes':'Declaración ante incidente'; ?>
                    <tr><td><?=hseqFechaLegible($acc['fecha_evento'])?></td><td><strong><?=h($persona)?></strong><small>Registro #<?=(int)$acc['id']?><?=$diasBaja>0?' · '.$diasBaja.' días de baja':''?></small></td><td><strong><?=h($lugar)?></strong><?php if($acc['detalle_evento']):?><small><?=h($acc['detalle_evento'])?></small><?php endif;?></td><td><?=h($titulo)?></td><td><span class="fx-estado fx-<?=h($acc['estado'])?>"><?=h(hseqEstadoFormulario($acc['estado']))?></span></td><td><?=h($acc['autor'] ?? '—')?></td><td><a class="btn secondary small" href="<?=h(app_url('/php/formularios/llenar.php?id=' . (int)$acc['id']))?>">Abrir</a></td></tr>
                <?php endforeach; ?>
            </tbody></table></div></div>
        </section>

        <section class="hseq-section" id="vencimientos">
            <div class="section-head"><div><p class="eyebrow">04 · NOTIFICACIONES DE VENCIMIENTOS</p><h2>Vencidos y próximos a vencer</h2><p>Incluye vencimientos asignados a HSEQ. El sistema avisa a los responsables en los cortes configurados de 15 días, 3 días y el día del vencimiento.</p></div><?php if(puedeVerHabilitaciones($pdo)):?><a class="btn secondary" href="<?=h(app_url('/php/personal.php?filtro=vencimientos'))?>">Abrir habilitaciones</a><?php endif;?></div>
            <div class="hseq-alert-list"><div class="hseq-alert <?= $vencidos ? 'danger' : 'ok' ?>"><strong><?=$vencidos?> vencido(s)</strong><span><?= $vencidos ? 'Revisá las renovaciones para recuperar la vigencia.' : 'No hay elementos vencidos en el seguimiento HSEQ.' ?></span></div><div class="hseq-alert <?= $porVencer ? 'warn' : 'ok' ?>"><strong><?=$porVencer?> próximo(s) en 45 días</strong><span>Las fechas se ordenan desde la más urgente.</span></div></div>
            <div class="table-panel"><div class="table-wrap"><table><thead><tr><th>Estado</th><th>Vence</th><th>Elemento</th><th>Persona / detalle</th><th>Días</th><th></th></tr></thead><tbody>
                <?php if (!$vencimientos): ?><tr><td colspan="6" class="muted" style="text-align:center">No hay vencimientos vencidos o próximos para HSEQ.</td></tr><?php endif; ?>
                <?php foreach ($vencimientos as $v): $clase=$v['dias']<0?'danger':($v['dias']<=15?'warn':'ok'); $texto=$v['dias']<0?'Vencido':($v['dias']===0?'Vence hoy':'Próximo'); ?>
                    <tr><td><span class="hseq-tag <?=$clase?>"><?=h($texto)?></span></td><td><?=hseqFechaLegible($v['fecha'])?></td><td><strong><?=h($v['titulo'])?></strong></td><td><?=h($v['detalle'])?></td><td><?=$v['dias']<0?abs((int)$v['dias']).' d vencido':(int)$v['dias'].' d'?></td><td><a class="btn secondary small" href="<?=h(app_url($v['url']))?>">Abrir</a></td></tr>
                <?php endforeach; ?>
            </tbody></table></div></div>
        </section>

        <section class="hseq-section" id="calidad">
            <div class="section-head"><div><p class="eyebrow">05 · CALIDAD</p><h2>Auditorías y satisfacción del cliente</h2><p>Seguimiento de formularios de auditoría existentes y registro de evaluaciones de servicio.</p></div></div>
            <div class="hseq-quality-grid">
                <article class="hseq-panel"><div class="hseq-panel-head"><div><h3>Auditorías y revisión</h3><p>Estado de los formularios cargados durante <?=$anio?>.</p></div><a class="btn secondary small" href="<?=h(app_url('/php/formularios/index.php?sector=hseq&f=hseq-plan-anual-auditorias'))?>">Ver formularios</a></div>
                    <div class="hseq-bars">
                        <?php foreach (['enviado'=>'Pendiente de revisión','aprobado'=>'Aprobado','rechazado'=>'A corregir','borrador'=>'Borrador'] as $estado=>$etiqueta): $valor=(int)$auditoriasEstado[$estado]; $porcentaje=(int)round($valor*100/$maxAuditorias); ?>
                            <div class="hseq-bar-row"><span><?=h($etiqueta)?></span><div class="hseq-bar-track"><i class="audit-<?=h($estado)?>" style="width:<?=$porcentaje?>%"></i></div><strong><?=$valor?></strong></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hseq-audit-links"><a href="<?=h(app_url('/php/formularios/index.php?sector=hseq&f=hseq-plan-anual-auditorias'))?>">Plan anual</a><a href="<?=h(app_url('/php/formularios/index.php?sector=hseq&f=hseq-programa-auditoria'))?>">Programas</a><a href="<?=h(app_url('/php/formularios/index.php?sector=hseq&f=hseq-informe-auditoria'))?>">Informes</a></div>
                </article>
                <article class="hseq-panel"><div class="hseq-panel-head"><div><h3>Satisfacción del cliente</h3><p>Promedio general mensual, escala de 1 a 5.</p></div><strong class="hseq-score"><?=$encuestasTotal ? h(number_format($satisfaccionPromedio,1,',','.')) . '/5' : '—'?></strong></div>
                    <div class="hseq-bars hseq-score-bars">
                        <?php foreach ($meses as $i=>$nombre): $mes=$i+1; $d=$satisfaccionPorMes[$mes]; $prom=$d['cantidad']?round($d['suma']/$d['cantidad'],1):0; $ancho=(int)round($prom*20); ?>
                            <div class="hseq-bar-row"><span><?=$nombre?></span><div class="hseq-bar-track"><i class="score-bar" style="width:<?=$ancho?>%"></i></div><strong><?=$d['cantidad']?h(number_format($prom,1,',','.')):'—'?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
            <?php if ($puedeGestionar): ?>
                <div class="hseq-panel hseq-editor"><div class="section-head"><div><h3><?=$encuestaEdit?'Editar evaluación':'Registrar satisfacción del cliente'?></h3><p>Guardá una respuesta recibida por el servicio prestado.</p></div><?php if ($encuestaEdit): ?><a class="btn secondary small" href="<?=h(app_url('/php/hseq.php#calidad'))?>">Cancelar edición</a><?php endif; ?></div>
                    <form method="post" class="hseq-form"><?=csrf_field()?>
                        <input type="hidden" name="accion" value="encuesta_guardar"><input type="hidden" name="id" value="<?= (int)($encuestaEdit['id'] ?? 0) ?>">
                        <label>Cliente<input name="cliente" maxlength="180" required value="<?=h($encuestaEdit['cliente'] ?? '')?>"></label>
                        <label>Servicio<input name="servicio" maxlength="120" placeholder="Slickline, Well Testing…" value="<?=h($encuestaEdit['servicio'] ?? '')?>"></label>
                        <label>Fecha<input type="date" name="fecha_encuesta" required value="<?=h($encuestaEdit['fecha_encuesta'] ?? date('Y-m-d'))?>"></label>
                        <?php foreach (['puntaje_general'=>'Satisfacción general','calidad_servicio'=>'Calidad del servicio','cumplimiento'=>'Cumplimiento','comunicacion'=>'Comunicación','seguridad'=>'Seguridad'] as $campo=>$etiqueta): ?><label><?=$etiqueta?><select name="<?=$campo?>" required><option value="" disabled <?=empty($encuestaEdit)?'selected':''?>>Seleccionar</option><?php for($nota=5;$nota>=1;$nota--): ?><option value="<?=$nota?>" <?=isset($encuestaEdit[$campo]) && (int)$encuestaEdit[$campo]===$nota?'selected':''?>><?=$nota?><?= $nota===5?' · Muy bueno':($nota===1?' · Muy bajo':'') ?></option><?php endfor; ?></select></label><?php endforeach; ?>
                        <label class="wide">Comentarios<textarea name="comentarios" rows="3" maxlength="5000"><?=h($encuestaEdit['comentarios'] ?? '')?></textarea></label>
                        <div class="wide"><button class="btn primary"><?=$encuestaEdit?'Guardar cambios':'Registrar evaluación'?></button></div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="table-panel"><div class="section-head"><div><h3>Respuestas registradas · <?=$anio?></h3></div><span class="count-pill"><?=$encuestasTotal?> evaluación(es) · últimas 100</span></div><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Servicio</th><th>General</th><th>Calidad</th><th>Cumplimiento</th><th>Comunicación</th><th>Seguridad</th><?php if($puedeGestionar):?><th></th><?php endif;?></tr></thead><tbody>
                <?php if (!$encuestas): ?><tr><td colspan="<?=$puedeGestionar?9:8?>" class="muted" style="text-align:center">Todavía no hay evaluaciones registradas para <?=$anio?>.</td></tr><?php endif; ?>
                <?php foreach ($encuestas as $e): ?><tr><td><?=hseqFechaLegible($e['fecha_encuesta'])?></td><td><strong><?=h($e['cliente'])?></strong><?php if($e['comentarios']):?><small><?=h($e['comentarios'])?></small><?php endif;?></td><td><?=h($e['servicio'] ?? '—')?></td><td><b><?= (int)$e['puntaje_general'] ?>/5</b></td><td><?= (int)$e['calidad_servicio'] ?>/5</td><td><?= (int)$e['cumplimiento'] ?>/5</td><td><?= (int)$e['comunicacion'] ?>/5</td><td><?= (int)$e['seguridad'] ?>/5</td><?php if($puedeGestionar):?><td><a class="btn secondary small" href="<?=h(app_url('/php/hseq.php?anio=' . $anio . '&editar_encuesta=' . (int)$e['id'] . '#calidad'))?>">Editar</a></td><?php endif;?></tr><?php endforeach; ?>
            </tbody></table></div></div>
            <div class="table-panel"><div class="section-head"><div><h3>Formularios de auditoría recientes · <?=$anio?></h3></div><span class="count-pill"><?=count($auditorias)?> registro(s)</span></div><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Formulario</th><th>Referencia</th><th>Estado</th><th>Cargó</th><th></th></tr></thead><tbody>
                <?php if (!$auditorias): ?><tr><td colspan="6" class="muted" style="text-align:center">No hay formularios de auditoría cargados este año.</td></tr><?php endif; ?>
                <?php foreach ($auditorias as $a): $formulario=formularioPorCodigo($a['formulario']); ?><tr><td><?=hseqFechaLegible(substr($a['creado_en'],0,10))?></td><td><?=h($formulario['titulo'] ?? $a['formulario'])?></td><td><?=h($a['referencia'] ?: '—')?></td><td><span class="fx-estado fx-<?=h($a['estado'])?>"><?=h(hseqEstadoFormulario($a['estado']))?></span></td><td><?=h($a['autor'] ?? '—')?></td><td><a class="btn secondary small" href="<?=h(app_url('/php/formularios/llenar.php?id=' . (int)$a['id']))?>">Abrir</a></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>

        <section class="hseq-section" id="grafico-accidentes">
            <div class="section-head"><div><p class="eyebrow">06 · GRÁFICO DE ACCIDENTES</p><h2>Notificaciones por mes · <?=$anio?></h2><p>Cuenta las declaraciones existentes en el sistema por fecha del hecho. Las alertas se basan en revisiones y variación de volumen.</p></div></div>
            <div class="hseq-quality-grid">
                <article class="hseq-panel"><div class="hseq-panel-head"><div><h3>Distribución mensual</h3><p>Cada barra representa declaraciones de incidente cargadas.</p></div><span class="hseq-total"><?=$accidentesAnio?> total</span></div>
                    <div class="hseq-bars hseq-accident-bars">
                        <?php foreach ($meses as $i=>$nombre): $mes=$i+1; $valor=$accidentesPorMes[$mes]; $ancho=(int)round($valor*100/$maxAccidentesMes); ?>
                            <div class="hseq-bar-row"><span><?=$nombre?></span><div class="hseq-bar-track"><i class="accident-bar" style="width:<?=$ancho?>%"></i></div><strong><?=$valor?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <article class="hseq-panel"><div class="hseq-panel-head"><div><h3>Alertas de seguimiento</h3><p>Indicadores automáticos para actuar a tiempo.</p></div></div>
                    <div class="hseq-alert-list hseq-alert-stack">
                        <div class="hseq-alert <?= $accidentesPendientes ? 'warn':'ok' ?>"><strong><?=$accidentesPendientes?> por revisar</strong><span>Declaraciones enviadas sin aprobación registrada.</span></div>
                        <div class="hseq-alert <?= $accidentesCorregir ? 'danger':'ok' ?>"><strong><?=$accidentesCorregir?> requieren corrección</strong><span>Declaraciones devueltas por el responsable.</span></div>
                        <div class="hseq-alert <?= $accidentesConBaja ? 'danger':'ok' ?>"><strong><?=$accidentesConBaja?> registro(s) con días de baja</strong><span>Casos declarados con impacto de baja laboral durante <?=$anio?>.</span></div>
                        <div class="hseq-alert <?= $alertaTendencia ? 'danger':'ok' ?>"><strong><?=$alertaTendencia?'Aumentaron las notificaciones':'Sin aumento relevante este mes'?></strong><span>Este mes: <?=$accidentesMes?> · mes anterior: <?=$accidentesMesAnterior?>. Se alerta al llegar a 3 y aumentar al menos 50%.</span></div>
                    </div>
                    <p class="hseq-note">El formulario de declaración no clasifica la gravedad. Por eso el tablero no estima lesiones ni severidad; muestra datos de registro, revisión y tendencia.</p>
                </article>
            </div>
        </section>

        <section class="hseq-section" id="observaciones">
            <div class="section-head"><div><p class="eyebrow">07 · OBSERVACIONES PREVENTIVAS</p><h2>Condiciones, actos y mejoras</h2><p>Registrá una observación, proponé la acción y asigná una persona y plazo para su seguimiento.</p></div></div>
            <?php if ($puedeCompletar): ?>
                <div class="hseq-panel hseq-editor"><div class="section-head"><div><h3><?=$obsEdit?'Editar observación #'.(int)$obsEdit['id']:'Nueva observación preventiva'?></h3><p><?= $puedeGestionar ? 'El responsable HSEQ también puede editar y actualizar el estado.' : 'El responsable HSEQ podrá asignar y cerrar el seguimiento.' ?></p></div><?php if ($obsEdit): ?><a class="btn secondary small" href="<?=h(app_url('/php/hseq.php#observaciones'))?>">Cancelar edición</a><?php endif; ?></div>
                    <form method="post" class="hseq-form"><?=csrf_field()?>
                        <input type="hidden" name="accion" value="observacion_guardar"><input type="hidden" name="id" value="<?= (int)($obsEdit['id'] ?? 0) ?>">
                        <label>Fecha<input type="date" name="fecha" required value="<?=h($obsEdit['fecha'] ?? date('Y-m-d'))?>"></label>
                        <label>Lugar / equipo<input name="lugar" maxlength="180" required value="<?=h($obsEdit['lugar'] ?? '')?>" placeholder="Base, locación, unidad…"></label>
                        <label>Tipo<select name="categoria" required><?php foreach(['Condición insegura','Acto inseguro','Mejora'] as $v):?><option <?=$v===($obsEdit['categoria']??'Condición insegura')?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
                        <label>Prioridad<select name="prioridad" required><?php foreach(['Baja','Media','Alta','Crítica'] as $v):?><option <?=$v===($obsEdit['prioridad']??'Media')?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
                        <label class="wide">Descripción<textarea name="descripcion" rows="3" maxlength="5000" required><?=h($obsEdit['descripcion'] ?? '')?></textarea></label>
                        <label class="wide">Acción preventiva propuesta<textarea name="accion_propuesta" rows="2" maxlength="5000"><?=h($obsEdit['accion_propuesta'] ?? '')?></textarea></label>
                        <label>Responsable sugerido<input name="responsable" maxlength="150" value="<?=h($obsEdit['responsable'] ?? '')?>"></label>
                        <label>Fecha compromiso<input type="date" name="fecha_compromiso" value="<?=h($obsEdit['fecha_compromiso'] ?? '')?>"></label>
                        <div class="wide"><button class="btn primary"><?=$obsEdit?'Guardar cambios':'Registrar observación'?></button></div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="hseq-kpis hseq-observation-kpis"><article><span>Abiertas</span><strong><?=(int)$observacionesStats['abiertas']?></strong></article><article><span>En curso</span><strong><?=(int)$observacionesStats['en_curso']?></strong></article><article><span>Cerradas</span><strong><?=(int)$observacionesStats['cerradas']?></strong></article><article class="is-danger"><span>Fuera de plazo</span><strong><?=(int)$observacionesStats['vencidas']?></strong></article></div>
            <div class="table-panel"><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Lugar / tipo</th><th>Observación y medida</th><th>Prioridad</th><th>Responsable</th><th>Compromiso</th><th>Estado</th><?php if($puedeGestionar):?><th>Seguimiento</th><?php endif;?></tr></thead><tbody>
                <?php if (!$observaciones): ?><tr><td colspan="<?=$puedeGestionar?8:7?>" class="muted" style="text-align:center">Todavía no hay observaciones preventivas registradas.</td></tr><?php endif; ?>
                <?php foreach ($observaciones as $o): $plazoVencido=$o['estado']!=='cerrada' && $o['fecha_compromiso'] && $o['fecha_compromiso']<date('Y-m-d'); ?>
                    <tr><td><?=hseqFechaLegible($o['fecha'])?></td><td><strong><?=h($o['lugar'])?></strong><small><?=h($o['categoria'])?></small></td><td><?=h($o['descripcion'])?><?php if($o['accion_propuesta']):?><small><b>Acción:</b> <?=h($o['accion_propuesta'])?></small><?php endif;?><?php if($o['cierre']):?><small><b>Cierre:</b> <?=h($o['cierre'])?></small><?php endif;?></td><td><span class="hseq-tag <?=in_array($o['prioridad'],['Alta','Crítica'],true)?'danger':($o['prioridad']==='Media'?'warn':'ok')?>"><?=h($o['prioridad'])?></span></td><td><?=h($o['responsable'] ?: $o['autor'] ?: 'Sin asignar')?></td><td class="<?=$plazoVencido?'hseq-overdue':''?>"><?=hseqFechaLegible($o['fecha_compromiso'])?><?=$plazoVencido?'<small>Plazo vencido</small>':''?></td><td><span class="hseq-tag <?=h($o['estado'])?>"><?=h($o['estado']==='en_curso'?'En curso':ucfirst($o['estado']))?></span></td>
                    <?php if($puedeGestionar):?><td><div class="hseq-row-actions"><a class="btn secondary small" href="<?=h(app_url('/php/hseq.php?anio=' . $anio . '&editar_obs=' . (int)$o['id'] . '#observaciones'))?>">Editar</a><form method="post" class="hseq-state-form"><?=csrf_field()?><input type="hidden" name="accion" value="observacion_estado"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><select name="estado" aria-label="Estado de observación"><option value="abierta" <?=$o['estado']==='abierta'?'selected':''?>>Abierta</option><option value="en_curso" <?=$o['estado']==='en_curso'?'selected':''?>>En curso</option><option value="cerrada" <?=$o['estado']==='cerrada'?'selected':''?>>Cerrada</option></select><input name="cierre" placeholder="Nota si se cierra" value="<?=h($o['cierre'] ?? '')?>"><button class="btn primary small">Guardar</button></form></div></td><?php endif; ?></tr>
                <?php endforeach; ?>
            </tbody></table></div></div>
        </section>

    </main>
</div>
</body>
</html>
