<?php
require __DIR__ . '/config/auth.php';
requireLogin();
verify_csrf(); // protege todos los formularios POST de esta pagina
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require __DIR__ . '/config/modulo.php';
require_once __DIR__ . '/config/formularios_catalogo.php';

$slug = 'rrhh';
[$sector, $sid, $canEdit] = cargarModulo($pdo, $slug);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg = ''; $err = '';

/* ============================================================
   CONSTANTES Y HELPERS
   ============================================================ */
const RRHH_TIPOS_VENC   = ['Licencia Medica','Licencia Vacaciones','Examen Periodico','Carnet de Conducir','Capacitacion','Otro'];
const RRHH_TIPOS_EVENTO = ['Alerta','Notificación','Recordatorio','Reunión','Capacitación','Examen médico','Trámite','Otro'];
const RRHH_ESTADOS      = ['Pendiente','En proceso','Resuelto','Cancelado'];
const RRHH_PRIORIDADES  = ['Baja','Media','Alta'];

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function rrhhFechaValida(?string $f): bool {
    if (!$f || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}

function rrhhDias(string $fecha, string $hoy): int {
    return (int)(new DateTime($hoy))->diff(new DateTime($fecha))->format('%r%a');
}

function rrhhEstadoVenc(string $venc, string $hoy, string $lim): string {
    return $venc < $hoy ? 'vencido' : ($venc <= $lim ? 'proximo' : 'vigente');
}

/** Crea las tablas nuevas si no existen (calendario de alertas + comentarios). */
function rrhhAsegurarTablas(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rrhh_eventos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        fecha DATE NOT NULL,
        titulo VARCHAR(160) NOT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'Alerta',
        estado VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
        prioridad VARCHAR(10) NOT NULL DEFAULT 'Media',
        empleado_nombre VARCHAR(150) NULL,
        detalle TEXT NULL,
        dias_aviso SMALLINT NOT NULL DEFAULT 0,
        creado_por INT NULL,
        actualizado_por INT NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME NULL,
        KEY idx_sector_fecha (sector_id, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS rrhh_comentarios (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        ref_tipo VARCHAR(12) NOT NULL,
        ref_id INT UNSIGNED NOT NULL,
        usuario_id INT NULL,
        comentario TEXT NOT NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ref (ref_tipo, ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Datos del calendario para un mes + alertas activas + resumen. */
function rrhhDatos(PDO $pdo, int $sid, int $anio, int $mes): array {
    $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
    $offsetDomingo = (int)$primerDia->format('w');
    $semanas = (int)ceil(($offsetDomingo + (int)$primerDia->format('t')) / 7);
    $inicioCalendario = $primerDia->modify('-' . $offsetDomingo . ' days');
    $finCalendario = $inicioCalendario->modify('+' . ($semanas * 7 - 1) . ' days');
    $ini = $inicioCalendario->format('Y-m-d');
    $fin = $finCalendario->format('Y-m-d');
    $hoy = date('Y-m-d');
    $lim = date('Y-m-d', strtotime('+30 days'));

    $st = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM rrhh_comentarios c WHERE c.ref_tipo='evento' AND c.ref_id=e.id) AS ncom
        FROM rrhh_eventos e WHERE e.sector_id=? AND e.fecha BETWEEN ? AND ? ORDER BY e.fecha, e.id");
    $st->execute([$sid, $ini, $fin]);
    $eventos = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT r.id, r.empleado_nombre, r.tipo, r.fecha_inicio, r.fecha_vencimiento, r.observaciones,
        (SELECT COUNT(*) FROM rrhh_comentarios c WHERE c.ref_tipo='venc' AND c.ref_id=r.id) AS ncom
        FROM rrhh_vencimientos r
        WHERE r.sector_id=? AND (r.fecha_vencimiento BETWEEN ? AND ? OR r.fecha_inicio BETWEEN ? AND ?)
        ORDER BY r.fecha_vencimiento, r.empleado_nombre");
    $st->execute([$sid, $ini, $fin, $ini, $fin]);
    $venc = $st->fetchAll(PDO::FETCH_ASSOC);

    // Alertas activas: vencimientos hasta +30 días (incluye vencidos) y alertas pendientes cercanas
    $alertas = [];
    $st = $pdo->prepare("SELECT id, empleado_nombre, tipo, fecha_vencimiento FROM rrhh_vencimientos WHERE sector_id=? AND fecha_vencimiento<=? ORDER BY fecha_vencimiento");
    $st->execute([$sid, $lim]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $alertas[] = ['kind'=>'venc','id'=>(int)$r['id'],'fecha'=>$r['fecha_vencimiento'],'titulo'=>$r['empleado_nombre'],'sub'=>$r['tipo']];
    }
    $st = $pdo->prepare("SELECT id, titulo, tipo, estado, prioridad, empleado_nombre, fecha FROM rrhh_eventos
        WHERE sector_id=? AND estado IN ('Pendiente','En proceso') AND fecha<=DATE_ADD(?, INTERVAL GREATEST(dias_aviso,7) DAY) ORDER BY fecha");
    $st->execute([$sid, $hoy]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $alertas[] = ['kind'=>'ev','id'=>(int)$r['id'],'fecha'=>$r['fecha'],'titulo'=>$r['titulo'],
                      'sub'=>$r['tipo'].($r['empleado_nombre'] ? ' · '.$r['empleado_nombre'] : ''),'estado'=>$r['estado'],'prioridad'=>$r['prioridad']];
    }
    usort($alertas, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));

    $st = $pdo->prepare("SELECT
            SUM(estado IN ('Pendiente','En proceso')) p,
            SUM(estado IN ('Pendiente','En proceso') AND fecha=?) h,
            SUM(estado IN ('Pendiente','En proceso') AND fecha<?) v
        FROM rrhh_eventos WHERE sector_id=?");
    $st->execute([$hoy, $hoy, $sid]);
    $rs = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'hoy' => $hoy, 'anio' => $anio, 'mes' => $mes,
        'eventos' => $eventos, 'vencimientos' => $venc, 'alertas' => $alertas,
        'resumen' => ['pendientes'=>(int)($rs['p'] ?? 0), 'hoy'=>(int)($rs['h'] ?? 0), 'atrasadas'=>(int)($rs['v'] ?? 0)],
    ];
}

try { rrhhAsegurarTablas($pdo); }
catch (Throwable $e) {
    error_log('RRHH tablas: ' . $e->getMessage());
    $err = 'No se pudieron crear las tablas del calendario. Ejecutá el archivo rrhh_calendario.sql en la base de datos.';
}

/* ============================================================
   ENDPOINTS AJAX (calendario, alertas y comentarios)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    try {
        $ajax = $_GET['ajax'];
        if ($ajax === 'mes') {
            $m = (int)($_GET['mes'] ?? date('n')); $y = (int)($_GET['anio'] ?? date('Y'));
            if ($m < 1 || $m > 12) $m = (int)date('n');
            if ($y < 2020 || $y > 2100) $y = (int)date('Y');
            jsonOut(['ok'=>true] + rrhhDatos($pdo, (int)$sid, $y, $m));
        }
        if ($ajax === 'comentarios') {
            $tipo = ($_GET['tipo'] ?? '') === 'venc' ? 'venc' : 'evento';
            $st = $pdo->prepare("SELECT c.id, c.comentario, c.creado_en, c.usuario_id, u.nombre autor
                FROM rrhh_comentarios c LEFT JOIN usuarios u ON u.id=c.usuario_id
                WHERE c.sector_id=? AND c.ref_tipo=? AND c.ref_id=? ORDER BY c.id");
            $st->execute([$sid, $tipo, (int)($_GET['id'] ?? 0)]);
            jsonOut(['ok'=>true, 'comentarios'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        }
        jsonOut(['ok'=>false, 'error'=>'Acción no válida.'], 400);
    } catch (Throwable $e) {
        error_log('RRHH ajax GET: ' . $e->getMessage());
        jsonOut(['ok'=>false, 'error'=>'Error interno.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (!$canEdit) jsonOut(['ok'=>false, 'error'=>'No tenés permiso para modificar este sector.'], 403);
    try {
        $a = $_POST['accion'] ?? '';

        if ($a === 'ev_guardar') {
            $id      = (int)($_POST['id'] ?? 0);
            $titulo  = mb_substr(trim($_POST['titulo'] ?? ''), 0, 160);
            $tipo    = $_POST['tipo'] ?? '';
            $estado  = $_POST['estado'] ?? '';
            $prio    = $_POST['prioridad'] ?? 'Media';
            $fecha   = $_POST['fecha'] ?? '';
            $emp     = mb_substr(trim($_POST['empleado_nombre'] ?? ''), 0, 150);
            $detalle = trim($_POST['detalle'] ?? '');
            $aviso   = max(0, min(60, (int)($_POST['dias_aviso'] ?? 0)));
            if ($titulo === '') throw new RuntimeException('Escribí un título.');
            if (!in_array($tipo, RRHH_TIPOS_EVENTO, true)) throw new RuntimeException('Tipo no válido.');
            if (!in_array($estado, RRHH_ESTADOS, true)) throw new RuntimeException('Estado no válido.');
            if (!in_array($prio, RRHH_PRIORIDADES, true)) $prio = 'Media';
            if (!rrhhFechaValida($fecha)) throw new RuntimeException('Fecha no válida.');
            if ($id) {
                $st = $pdo->prepare('UPDATE rrhh_eventos SET fecha=?,titulo=?,tipo=?,estado=?,prioridad=?,empleado_nombre=?,detalle=?,dias_aviso=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?');
                $st->execute([$fecha,$titulo,$tipo,$estado,$prio,$emp ?: null,$detalle ?: null,$aviso,$uid,$id,$sid]);
                auditModulo($pdo, $uid, 'rrhh_alerta_editar', "Alerta #$id - $titulo");
            } else {
                $st = $pdo->prepare('INSERT INTO rrhh_eventos(sector_id,fecha,titulo,tipo,estado,prioridad,empleado_nombre,detalle,dias_aviso,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$fecha,$titulo,$tipo,$estado,$prio,$emp ?: null,$detalle ?: null,$aviso,$uid,$uid]);
                $id = (int)$pdo->lastInsertId();
                auditModulo($pdo, $uid, 'rrhh_alerta_crear', "Alerta #$id - $titulo");
            }
            jsonOut(['ok'=>true, 'id'=>$id]);
        }

        if ($a === 'ev_estado') {
            $estado = $_POST['estado'] ?? '';
            if (!in_array($estado, RRHH_ESTADOS, true)) throw new RuntimeException('Estado no válido.');
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE rrhh_eventos SET estado=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$estado,$uid,$id,$sid]);
            auditModulo($pdo, $uid, 'rrhh_alerta_estado', "Alerta #$id → $estado");
            jsonOut(['ok'=>true]);
        }

        if ($a === 'ev_mover') {
            $fecha = $_POST['fecha'] ?? '';
            if (!rrhhFechaValida($fecha)) throw new RuntimeException('Fecha no válida.');
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE rrhh_eventos SET fecha=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')->execute([$fecha,$uid,$id,$sid]);
            auditModulo($pdo, $uid, 'rrhh_alerta_mover', "Alerta #$id → $fecha");
            jsonOut(['ok'=>true]);
        }

        if ($a === 'ev_eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM rrhh_comentarios WHERE sector_id=? AND ref_tipo='evento' AND ref_id=?")->execute([$sid,$id]);
            $pdo->prepare('DELETE FROM rrhh_eventos WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            auditModulo($pdo, $uid, 'rrhh_alerta_eliminar', "Alerta #$id");
            jsonOut(['ok'=>true]);
        }

        if ($a === 'com_agregar') {
            $tipo = ($_POST['tipo'] ?? '') === 'venc' ? 'venc' : 'evento';
            $rid  = (int)($_POST['id'] ?? 0);
            $txt  = trim($_POST['comentario'] ?? '');
            if ($txt === '') throw new RuntimeException('Escribí un comentario.');
            $tabla = $tipo === 'venc' ? 'rrhh_vencimientos' : 'rrhh_eventos';
            $chk = $pdo->prepare("SELECT 1 FROM $tabla WHERE id=? AND sector_id=?");
            $chk->execute([$rid, $sid]);
            if (!$chk->fetchColumn()) throw new RuntimeException('El registro ya no existe.');
            $pdo->prepare('INSERT INTO rrhh_comentarios(sector_id,ref_tipo,ref_id,usuario_id,comentario) VALUES(?,?,?,?,?)')
                ->execute([$sid, $tipo, $rid, $uid, mb_substr($txt, 0, 2000)]);
            jsonOut(['ok'=>true]);
        }

        if ($a === 'com_eliminar') {
            $pdo->prepare('DELETE FROM rrhh_comentarios WHERE id=? AND sector_id=?')->execute([(int)($_POST['id'] ?? 0), $sid]);
            jsonOut(['ok'=>true]);
        }

        jsonOut(['ok'=>false, 'error'=>'Acción no válida.'], 400);
    } catch (RuntimeException $e) {
        jsonOut(['ok'=>false, 'error'=>$e->getMessage()], 422);
    } catch (Throwable $e) {
        error_log('RRHH ajax POST: ' . $e->getMessage());
        jsonOut(['ok'=>false, 'error'=>'Error interno. Intentá de nuevo.'], 500);
    }
}

/* ============================================================
   FORMULARIO CLÁSICO: licencias / vencimientos (con redirección PRG)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirEdicion($canEdit);
    $a = $_POST['accion'] ?? '';
    if (in_array($a, ['guardar', 'eliminar'], true)) {
        $flash = ['msg'=>'', 'err'=>''];
        try {
            if ($a === 'guardar') {
                $id       = (int)($_POST['id'] ?? 0);
                $empleado = mb_substr(trim($_POST['empleado_nombre'] ?? ''), 0, 150);
                $tipo     = trim($_POST['tipo'] ?? '');
                $inicio   = trim($_POST['fecha_inicio'] ?? '') ?: null;
                $vence    = trim($_POST['fecha_vencimiento'] ?? '');
                $obs      = trim($_POST['observaciones'] ?? '');
                if ($empleado === '' || $tipo === '' || $vence === '') throw new RuntimeException('Completá empleado, tipo y vencimiento.');
                if (!in_array($tipo, RRHH_TIPOS_VENC, true)) throw new RuntimeException('Tipo no válido.');
                if (!rrhhFechaValida($vence)) throw new RuntimeException('La fecha de vencimiento no es válida.');
                if ($inicio !== null && !rrhhFechaValida($inicio)) throw new RuntimeException('La fecha de inicio no es válida.');
                if ($inicio !== null && $inicio > $vence) throw new RuntimeException('La fecha de inicio no puede ser posterior al vencimiento.');
                if ($id) {
                    $st = $pdo->prepare('UPDATE rrhh_vencimientos SET empleado_nombre=?,tipo=?,fecha_inicio=?,fecha_vencimiento=?,observaciones=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?');
                    $st->execute([$empleado,$tipo,$inicio,$vence,$obs,$uid,$id,$sid]);
                    $flash['msg'] = 'Registro actualizado.';
                    auditModulo($pdo, $uid, 'rrhh_editar', "RRHH #$id - $empleado");
                } else {
                    $st = $pdo->prepare('INSERT INTO rrhh_vencimientos(sector_id,empleado_nombre,tipo,fecha_inicio,fecha_vencimiento,observaciones,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?)');
                    $st->execute([$sid,$empleado,$tipo,$inicio,$vence,$obs,$uid,$uid]);
                    $flash['msg'] = 'Registro creado.';
                    auditModulo($pdo, $uid, 'rrhh_crear', "RRHH - $empleado");
                }
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare("DELETE FROM rrhh_comentarios WHERE sector_id=? AND ref_tipo='venc' AND ref_id=?")->execute([$sid, $id]);
                $pdo->prepare('DELETE FROM rrhh_vencimientos WHERE id=? AND sector_id=?')->execute([$id, $sid]);
                auditModulo($pdo, $uid, 'rrhh_eliminar', "RRHH #$id");
                $flash['msg'] = 'Registro eliminado.';
            }
        } catch (RuntimeException $e) {
            $flash['err'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('RRHH guardar: ' . $e->getMessage());
            $flash['err'] = 'No se pudo guardar el registro.';
        }
        $_SESSION['rrhh_flash'] = $flash;
        header('Location: ' . app_url('/php/rrhh.php') . ($flash['err'] && $a === 'guardar' ? '' : '#seguimiento'));
        exit;
    }
}

if (!empty($_SESSION['rrhh_flash'])) {
    $msg = $_SESSION['rrhh_flash']['msg'] ?? '';
    $err = $_SESSION['rrhh_flash']['err'] ?? ($err ?: '');
    unset($_SESSION['rrhh_flash']);
}

/* ============================================================
   CONSULTAS PARA RENDERIZAR
   ============================================================ */
$edit = null;
if ($canEdit && !empty($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM rrhh_vencimientos WHERE id=? AND sector_id=?');
    $st->execute([(int)$_GET['editar'], $sid]);
    $edit = $st->fetch();
}
$st = $pdo->prepare('SELECT r.*,u.nombre creado_nombre,ua.nombre actualizado_nombre,
    (SELECT COUNT(*) FROM rrhh_comentarios c WHERE c.ref_tipo=\'venc\' AND c.ref_id=r.id) ncom
    FROM rrhh_vencimientos r LEFT JOIN usuarios u ON u.id=r.creado_por LEFT JOIN usuarios ua ON ua.id=r.actualizado_por
    WHERE r.sector_id=? ORDER BY r.fecha_vencimiento,r.empleado_nombre');
$st->execute([$sid]);
$rows = $st->fetchAll();

$hoy = date('Y-m-d'); $lim = date('Y-m-d', strtotime('+30 days'));
$vencidos = 0; $proximos = 0; $vigentes = 0;
foreach ($rows as $r) {
    $e = rrhhEstadoVenc($r['fecha_vencimiento'], $hoy, $lim);
    if ($e === 'vencido') $vencidos++; elseif ($e === 'proximo') $proximos++; else $vigentes++;
}

$empleados = [];
foreach ($rows as $r) $empleados[$r['empleado_nombre']] = true;
$empleados = array_keys($empleados); sort($empleados);

$mes  = (int)($_GET['mes'] ?? date('n'));  $anio = (int)($_GET['anio'] ?? date('Y'));
if ($mes < 1 || $mes > 12) $mes = (int)date('n');
if ($anio < 2020 || $anio > 2100) $anio = (int)date('Y');
try { $datosIni = rrhhDatos($pdo, (int)$sid, $anio, $mes); }
catch (Throwable $e) {
    error_log('RRHH datos: ' . $e->getMessage());
    $datosIni = ['hoy'=>$hoy,'anio'=>$anio,'mes'=>$mes,'eventos'=>[],'vencimientos'=>[],'alertas'=>[],'resumen'=>['pendientes'=>0,'hoy'=>0,'atrasadas'=>0]];
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión de Recursos Humanos | NASER SGI</title><link rel="stylesheet" href="<?=asset('/style.css')?>">
<link rel="stylesheet" href="<?=asset('/css/modules.css')?>">
<link rel="stylesheet" href="<?=asset('/css/checklists.css')?>">
<style>
:root{--rh-green:#176337;--rh-green-soft:#e9f6ee;--rh-border:#dfe8e1;--rh-red:#a62b22;--rh-red-soft:#fff0ef;--rh-amber:#8a5a00;--rh-amber-soft:#fff6dd;--rh-blue:#1d4f91;--rh-blue-soft:#e8f0fb;--rh-gray:#6b7a70;--rh-gray-soft:#eef1ee}
.rh-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:14px 0}
.rh-kpi{background:#fff;border:1px solid var(--rh-border);border-radius:14px;padding:14px 16px}
.rh-kpi small{display:block;color:var(--rh-gray);font-weight:700;font-size:12px}
.rh-kpi b{display:block;font-size:28px;line-height:1.15;margin-top:2px}
.rh-kpi.red b{color:var(--rh-red)}.rh-kpi.amber b{color:var(--rh-amber)}.rh-kpi.green b{color:var(--rh-green)}.rh-kpi.blue b{color:var(--rh-blue)}

.rh-cal{background:#fff;border:1px solid var(--rh-border);border-radius:15px;padding:14px;margin:10px 0 18px}
.rh-cal-bar{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;margin-bottom:14px;background:#f8faf8;padding:10px 12px;border:1px solid var(--rh-border);border-radius:12px}
.rh-cal-bar .spacer{flex:1}
.rh-cal-bar h2{margin:0 6px;font-size:18px;color:var(--rh-green);text-transform:capitalize;text-align:center}
.rh-cal-bar select,.rh-cal-bar button{font:inherit;font-size:13px;padding:7px 11px;border:1px solid var(--rh-border);border-radius:9px;background:#fff;cursor:pointer}
.rh-cal-bar button:hover{background:var(--rh-green-soft)}
.rh-legend{display:flex;flex-wrap:wrap;gap:12px;font-size:11px;color:var(--rh-gray);margin-bottom:10px}
.rh-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
.rh-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
.rh-head{text-align:center;font-size:11px;font-weight:800;color:#52705b;padding:6px 0}
.rh-day{min-height:95px;border:1px solid var(--rh-border);border-radius:10px;padding:8px;background:#fff;cursor:pointer;position:relative;display:flex;flex-direction:column;justify-content:space-between;transition:background .12s,border-color .12s;overflow:hidden}
.rh-day:hover{background:var(--rh-green-soft);border-color:#b9d8c4}
.rh-day:focus-visible{outline:2px solid var(--rh-green);outline-offset:1px}
.rh-day.empty{background:transparent;border-color:transparent;cursor:default;pointer-events:none}
.rh-day.other{background:#f6f8f7;border-color:#edf1ee}
.rh-day.other .rh-num>span:first-child{color:#a9b6af;font-weight:600}
.rh-day.other .rh-chip{opacity:.75}
.rh-day.today{border:2px solid var(--rh-green);background:#f2fbf5}
.rh-day.drop{background:#d9f0e1;border-color:var(--rh-green)}
.rh-num{font-weight:800;font-size:12px;display:flex;justify-content:space-between;align-items:center}
.rh-day.today .rh-num span:first-child{background:var(--rh-green);color:#fff;border-radius:999px;padding:1px 7px}
.rh-add{opacity:0;font-size:14px;line-height:1;color:var(--rh-green);font-weight:900;transition:opacity .12s}
.rh-day:hover .rh-add{opacity:1}
.rh-chip{display:block;margin-top:4px;padding:3px 6px;border-radius:6px;font-size:10px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-left:3px solid transparent}
.rh-chip.v-vencido{background:var(--rh-red-soft);color:var(--rh-red);border-left-color:var(--rh-red)}
.rh-chip.v-proximo{background:var(--rh-amber-soft);color:var(--rh-amber);border-left-color:#e0a100}
.rh-chip.v-vigente{background:var(--rh-green-soft);color:var(--rh-green);border-left-color:var(--rh-green)}
.rh-chip.v-inicio{background:var(--rh-gray-soft);color:var(--rh-gray);border-left-color:#9aa8a0}
.rh-chip.ev{background:var(--rh-blue-soft);color:var(--rh-blue);border-left-color:var(--rh-blue)}
.rh-chip.ev.p-alta{border-left-color:var(--rh-red)}
.rh-chip.ev.done{background:var(--rh-gray-soft);color:var(--rh-gray);text-decoration:line-through;border-left-color:#b6c0b9}
.rh-chip[draggable=true]{cursor:grab}
.rh-more{display:block;margin-top:4px;font-size:10px;font-weight:800;color:var(--rh-gray)}

.rh-alertas{display:grid;gap:8px}
.rh-alert{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;border:1px solid #e5ebe6;background:#f8faf8;font-size:13px;cursor:pointer;text-align:left;width:100%;font-family:inherit}
.rh-alert:hover{border-color:#b9d8c4;background:var(--rh-green-soft)}
.rh-alert small{display:block;color:var(--rh-gray)}
.rh-alert.late{background:var(--rh-red-soft);border-color:#f3c9c5}
.rh-alert.soon{background:var(--rh-amber-soft);border-color:#f0dc9b}
.rh-pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
.rh-pill.red{background:var(--rh-red-soft);color:var(--rh-red)}.rh-pill.amber{background:var(--rh-amber-soft);color:var(--rh-amber)}
.rh-pill.green{background:var(--rh-green-soft);color:var(--rh-green)}.rh-pill.blue{background:var(--rh-blue-soft);color:var(--rh-blue)}.rh-pill.gray{background:var(--rh-gray-soft);color:var(--rh-gray)}
.rh-empty{padding:18px;text-align:center;color:var(--rh-gray);font-size:13px;border:1px dashed var(--rh-border);border-radius:10px}

/* Diálogo del día */
.rh-dialog{border:none;border-radius:18px;padding:0;width:min(640px,94vw);max-height:90vh;box-shadow:0 20px 60px rgba(0,0,0,.28)}
.rh-dialog::backdrop{background:rgba(15,30,20,.45)}
.rh-dlg-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:16px 20px;border-bottom:1px solid var(--rh-border);position:sticky;top:0;background:#fff;z-index:1}
.rh-dlg-head small{color:var(--rh-gray);font-weight:700}
.rh-dlg-head h3{margin:2px 0 0;font-size:19px;text-transform:capitalize}
.rh-x{border:none;background:var(--rh-gray-soft);border-radius:999px;width:32px;height:32px;font-size:15px;cursor:pointer}
.rh-dlg-body{padding:16px 20px;display:grid;gap:12px;overflow:auto;max-height:calc(90vh - 150px)}
.rh-dlg-foot{padding:12px 20px 16px;border-top:1px solid var(--rh-border);display:flex;gap:8px;justify-content:flex-end}
.rh-item{border:1px solid var(--rh-border);border-radius:12px;padding:12px 14px;background:#fff;border-left:4px solid var(--rh-blue)}
.rh-item.venc{border-left-color:var(--rh-green)}.rh-item.venc.s-vencido{border-left-color:var(--rh-red)}.rh-item.venc.s-proximo{border-left-color:#e0a100}
.rh-item.inicio{border-left-color:#9aa8a0}
.rh-item.ev.p-alta{border-left-color:var(--rh-red)}
.rh-item.done{opacity:.7}.rh-item.done h4{text-decoration:line-through}
.rh-item h4{margin:6px 0 2px;font-size:15px}
.rh-item p{margin:6px 0 0;font-size:13px;color:#33433a;white-space:pre-wrap}
.rh-item .meta{font-size:12px;color:var(--rh-gray)}
.rh-badges{display:flex;flex-wrap:wrap;gap:6px}
.rh-ctrl{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:10px}
.rh-ctrl select{font:inherit;font-size:12px;padding:5px 8px;border:1px solid var(--rh-border);border-radius:8px}
.rh-btn{font:inherit;font-size:12px;font-weight:700;padding:6px 11px;border-radius:8px;border:1px solid var(--rh-border);background:#fff;cursor:pointer;text-decoration:none;color:inherit;display:inline-block}
.rh-btn:hover{background:var(--rh-gray-soft)}
.rh-btn.primary{background:var(--rh-green);border-color:var(--rh-green);color:#fff}.rh-btn.primary:hover{background:#0f4d29}
.rh-btn.danger{color:var(--rh-red)}.rh-btn.danger:hover{background:var(--rh-red-soft)}
.rh-com{margin-top:10px;padding-top:10px;border-top:1px dashed var(--rh-border)}
.rh-com-item{background:#f6f9f7;border-radius:9px;padding:8px 10px;margin-bottom:6px;font-size:13px}
.rh-com-item .who{display:flex;justify-content:space-between;gap:8px;font-size:11px;color:var(--rh-gray);margin-bottom:2px}
.rh-com-item .who button{border:none;background:none;color:var(--rh-red);cursor:pointer;font-size:11px}
.rh-com-item div:last-child{white-space:pre-wrap}
.rh-com form{display:flex;gap:6px;margin-top:6px}
.rh-com textarea{flex:1;min-height:38px;font:inherit;font-size:13px;padding:7px 9px;border:1px solid var(--rh-border);border-radius:8px;resize:vertical}
.rh-form{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.rh-form label{display:grid;gap:4px;font-size:12px;font-weight:700;color:#33433a}
.rh-form .full{grid-column:1/-1}
.rh-form input,.rh-form select,.rh-form textarea{font:inherit;font-size:14px;font-weight:400;padding:8px 10px;border:1px solid var(--rh-border);border-radius:9px;width:100%;box-sizing:border-box}
.rh-form textarea{min-height:90px;resize:vertical}
.rh-form .actions{grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end}
.rh-toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);background:#1c3326;color:#fff;padding:10px 18px;border-radius:999px;font-size:13px;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.25)}
.rh-toast.err{background:var(--rh-red)}

/* Seguimiento */
.rh-filtros{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}
.rh-filtros input,.rh-filtros select{font:inherit;font-size:13px;padding:8px 11px;border:1px solid var(--rh-border);border-radius:9px;background:#fff}
.rh-filtros input{flex:1;min-width:200px}
.module-table tr.s-vencido td:first-child{box-shadow:inset 4px 0 0 var(--rh-red)}
.module-table tr.s-proximo td:first-child{box-shadow:inset 4px 0 0 #e0a100}
.module-table tr.s-vigente td:first-child{box-shadow:inset 4px 0 0 var(--rh-green)}
.module-table tr.rh-hide{display:none}
@media(max-width:720px){.rh-day{min-height:58px;padding:5px;border-radius:8px}.rh-chip{font-size:10px;padding:3px 4px}.rh-form{grid-template-columns:1fr}.rh-head{font-size:11px;padding:6px 0}.rh-grid{gap:4px}.rh-cal-bar .spacer{display:none}}
@media(max-width:460px){.rh-cal{padding:10px}.rh-cal-bar{gap:6px}.rh-cal-bar h2{order:-1;flex-basis:100%;font-size:17px}.rh-cal-bar select{flex:1;min-width:0}.rh-grid{gap:3px}.rh-day{min-height:58px;padding:4px 3px;border-radius:7px}.rh-head{font-size:10px;padding:5px 0}.rh-chip{font-size:9px;padding:2px 3px;border-radius:4px}.rh-more{font-size:9px}}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body><div class="app"><?php sidebar($pdo,$slug); ?><main class="content">
<section class="module-hero"><p class="eyebrow">SERVICIOS NASER SRL · RECURSOS HUMANOS</p><h1>Gestión de Recursos Humanos</h1><p>Licencias, vencimientos, capacitaciones y seguimiento del personal.</p></section>
<div class="module-tabs">
  <a href="<?=app_url('/php/sector.php?sector='.$slug)?>">📁 Documentación</a>
  <a href="#formularios">📋 Formularios / Checklists</a>
  <a href="#gestion">⚙ Gestión</a>
  <a href="#calendario">📅 Calendario</a>
  <a href="#seguimiento">📊 Seguimiento</a>
</div>
<div class="module-permission">🔐 <?php if($canEdit): ?>Podés consultar y modificar este sector.<?php else: ?>Modo consulta: solo el responsable del sector y el administrador pueden modificar.<?php endif; ?></div>

<?php if($msg):?><div class="module-note"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="module-error"><?=h($err)?></div><?php endif;?>

<!-- RESUMEN -->
<section class="rh-kpis">
  <div class="rh-kpi"><small>Total registros</small><b><?=count($rows)?></b></div>
  <div class="rh-kpi green"><small>Vigentes</small><b><?=$vigentes?></b></div>
  <div class="rh-kpi amber"><small>Vencen en 30 días</small><b><?=$proximos?></b></div>
  <div class="rh-kpi red"><small>Vencidos</small><b><?=$vencidos?></b></div>
  <div class="rh-kpi blue"><small>Alertas pendientes</small><b id="kpiPend"><?=$datosIni['resumen']['pendientes']?></b></div>
  <div class="rh-kpi red"><small>Alertas atrasadas</small><b id="kpiAtras"><?=$datosIni['resumen']['atrasadas']?></b></div>
</section>

<!-- SECCIÓN: FORMULARIOS DIGITALES (se guardan en la base de datos) -->
<?php formulariosPanel($pdo, 'rrhh'); ?>

<!-- SECCIÓN: GESTIÓN DE VENCIMIENTOS Y LICENCIAS -->
<?php if($canEdit):?>
<section class="module-card" id="gestion">
  <h3><?=$edit?'Modificar registro':'Nueva licencia / vencimiento'?></h3>
  <form method="post" class="module-form"><?=csrf_field()?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
    <label>Empleado<input name="empleado_nombre" required maxlength="150" list="rhEmpleados" autocomplete="off" value="<?=h($edit['empleado_nombre']??'')?>"></label>
    <label>Tipo
      <select name="tipo" required>
        <?php foreach(RRHH_TIPOS_VENC as $t):?>
          <option <?=($edit['tipo']??'')===$t?'selected':''?>><?=h($t)?></option>
        <?php endforeach;?>
      </select>
    </label>
    <label>Fecha inicio<input type="date" name="fecha_inicio" value="<?=h($edit['fecha_inicio']??'')?>"></label>
    <label>Fecha vencimiento<input type="date" name="fecha_vencimiento" required value="<?=h($edit['fecha_vencimiento']??'')?>"></label>
    <label class="full">Observaciones<textarea name="observaciones"><?=h($edit['observaciones']??'')?></textarea></label>
    <div class="full">
      <button class="btn primary">Guardar</button>
      <?php if($edit):?> <a class="btn secondary" href="<?=app_url('/php/rrhh.php')?>">Cancelar</a><?php endif;?>
    </div>
  </form>
</section>
<?php endif;?>
<datalist id="rhEmpleados"><?php foreach($empleados as $n):?><option value="<?=h($n)?>"><?php endforeach;?></datalist>

<!-- ALERTAS ACTIVAS -->
<div class="module-toolbar"><h2>Alertas activas</h2><span class="count-pill" id="rhAlertCount">0 alerta(s)</span></div>
<section class="module-card"><div class="rh-alertas" id="rhAlertas"></div></section>

<!-- CALENDARIO INTERACTIVO -->
<div class="module-toolbar" id="calendario"><h2>Calendario</h2>
  <span class="meta" style="font-size:12px;color:#6b7a70"><?=$canEdit?'Hacé clic en un día para ver o agregar alertas. Arrastrá una alerta a otro día para reprogramarla.':'Hacé clic en un día para ver sus alertas y vencimientos.'?></span>
</div>
<section class="rh-cal">
  <div class="rh-cal-bar">
    <button type="button" id="calPrev" aria-label="Mes anterior">◀ Mes anterior</button>
    <h2 id="calTitulo"></h2>
    <button type="button" id="calNext" aria-label="Mes siguiente">Mes siguiente ▶</button>
    <span class="spacer"></span>
    <select id="calMes" aria-label="Mes"></select>
    <select id="calAnio" aria-label="Año"></select>
    <button type="button" id="calHoy">Hoy</button>
    <?php if($canEdit):?><button type="button" id="calNueva" class="rh-btn primary">＋ Nueva alerta</button><?php endif;?>
  </div>
  <div class="rh-legend">
    <span><i style="background:#e0a100"></i>Vence en 30 días</span>
    <span><i style="background:var(--rh-red)"></i>Vencido</span>
    <span><i style="background:var(--rh-green)"></i>Vigente</span>
    <span><i style="background:#9aa8a0"></i>Inicio de licencia</span>
    <span><i style="background:var(--rh-blue)"></i>Alerta / notificación</span>
  </div>
  <div class="rh-grid" id="calGrid"></div>
</section>

<!-- SEGUIMIENTO -->
<div class="module-toolbar" id="seguimiento"><h2>Seguimiento RRHH</h2><span class="count-pill" id="segCount"><?=count($rows)?> registro(s)</span></div>
<div class="rh-filtros">
  <input type="search" id="fBuscar" placeholder="Buscar por empleado, tipo u observaciones…">
  <select id="fTipo"><option value="">Todos los tipos</option><?php foreach(RRHH_TIPOS_VENC as $t):?><option><?=h($t)?></option><?php endforeach;?></select>
  <select id="fEstado"><option value="">Todos los estados</option><option value="vencido">Vencidos</option><option value="proximo">Vencen en 30 días</option><option value="vigente">Vigentes</option></select>
  <button type="button" class="rh-btn" id="fCsv">⬇ Exportar CSV</button>
</div>
<div class="table-wrapper">
  <table class="module-table" id="segTabla">
    <thead>
      <tr>
        <th>Empleado</th>
        <th>Tipo</th>
        <th>Inicio</th>
        <th>Vencimiento</th>
        <th>Estado</th>
        <th>Observaciones</th>
        <th>Última modificación</th>
        <?php if($canEdit):?><th>Acciones</th><?php endif;?>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r):
        $est = rrhhEstadoVenc($r['fecha_vencimiento'], $hoy, $lim);
        $dias = rrhhDias($r['fecha_vencimiento'], $hoy);
        $txt = $est==='vencido' ? 'Venció hace '.abs($dias).' d' : ($dias===0 ? 'Vence hoy' : 'En '.$dias.' d');
        $cls = ['vencido'=>'red','proximo'=>'amber','vigente'=>'green'][$est];
        $busq = mb_strtolower($r['empleado_nombre'].' '.$r['tipo'].' '.($r['observaciones']??''));
      ?>
      <tr class="s-<?=$est?>" data-q="<?=h($busq)?>" data-tipo="<?=h($r['tipo'])?>" data-estado="<?=$est?>">
        <td><strong><?=h($r['empleado_nombre'])?></strong></td>
        <td><?=h($r['tipo'])?></td>
        <td><?=h($r['fecha_inicio']??'—')?></td>
        <td><?=h($r['fecha_vencimiento'])?></td>
        <td><span class="rh-pill <?=$cls?>"><?=h($txt)?></span></td>
        <td><?=h($r['observaciones']??'')?><?php if((int)$r['ncom']>0):?> <span class="rh-pill gray">💬 <?=(int)$r['ncom']?></span><?php endif;?></td>
        <td><?=h($r['actualizado_nombre']?:$r['creado_nombre']?:'Sistema')?><br><small><?=h($r['actualizado_en']??$r['creado_en']??'')?></small></td>
        <?php if($canEdit):?>
        <td class="module-actions">
          <a class="btn secondary" href="?editar=<?=(int)$r['id']?>#gestion">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar este registro y sus comentarios?')"><?=csrf_field()?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
            <button class="btn secondary">Eliminar</button>
          </form>
        </td>
        <?php endif;?>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
  <?php if(!$rows):?><div class="rh-empty" style="margin-top:10px">Todavía no hay licencias ni vencimientos cargados.</div><?php endif;?>
</div>

<!-- Diálogo del día -->
<dialog id="rhDia" class="rh-dialog">
  <div class="rh-dlg-head">
    <div><small id="rhDiaSub"></small><h3 id="rhDiaTitulo"></h3></div>
    <button type="button" class="rh-x" id="rhCerrar" aria-label="Cerrar">✕</button>
  </div>
  <div class="rh-dlg-body" id="rhDiaBody"></div>
  <div class="rh-dlg-foot" id="rhDiaFoot"></div>
</dialog>

<div id="rhCsrf" hidden><?=csrf_field()?></div>

</main>
</div>

<script>
(function(){
'use strict';
var URL_BASE = <?=json_encode(app_url('/php/rrhh.php'), $jsonFlags)?>;
var CAN_EDIT = <?=json_encode((bool)$canEdit)?>;
var TIPOS    = <?=json_encode(RRHH_TIPOS_EVENTO, $jsonFlags)?>;
var ESTADOS  = <?=json_encode(RRHH_ESTADOS, $jsonFlags)?>;
var PRIOS    = <?=json_encode(RRHH_PRIORIDADES, $jsonFlags)?>;
var MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
var S = { data: <?=json_encode($datosIni, $jsonFlags)?>, anio: <?=$anio?>, mes: <?=$mes?>, dia: null, vista: 'lista', edit: null };

var $ = function(s){ return document.querySelector(s); };
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function pad(n){ return (n < 10 ? '0' : '') + n; }
function ymd(y,m,d){ return y + '-' + pad(m) + '-' + pad(d); }
function parse(f){ var p = f.split('-').map(Number); return new Date(p[0], p[1]-1, p[2]); }
function diff(f){ return Math.round((parse(f) - parse(S.data.hoy)) / 86400000); }
function fmtFecha(f){ return parse(f).toLocaleDateString('es-AR', {weekday:'long', day:'numeric', month:'long', year:'numeric'}); }
function slug(s){ return String(s||'').toLowerCase(); }
function rel(f){
  var d = diff(f);
  if (d < 0) return 'Vencido hace ' + Math.abs(d) + ' d';
  if (d === 0) return 'Hoy';
  if (d === 1) return 'Mañana';
  return 'En ' + d + ' d';
}
function estVenc(f){ var d = diff(f); return d < 0 ? 'vencido' : (d <= 30 ? 'proximo' : 'vigente'); }

function toast(msg, isErr){
  var t = document.createElement('div');
  t.className = 'rh-toast' + (isErr ? ' err' : '');
  t.textContent = msg; document.body.appendChild(t);
  setTimeout(function(){ t.remove(); }, 2600);
}

/* ---------- red ---------- */
function getJson(url){
  return fetch(url, {credentials:'same-origin'}).then(function(r){ return r.json(); });
}
function post(data){
  var fd = new FormData();
  Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
  fd.append('ajax', '1');
  var tok = document.querySelector('#rhCsrf input');
  if (tok) fd.append(tok.name, tok.value);
  return fetch(URL_BASE, {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .catch(function(){ return {ok:false, error:'No se pudo conectar con el servidor.'}; });
}
function reload(){
  return getJson(URL_BASE + '?ajax=mes&anio=' + S.anio + '&mes=' + S.mes).then(function(d){
    if (d.ok) { S.data = d; renderTodo(); }
    return d;
  });
}

/* ---------- datos por día ---------- */
function itemsDelDia(f){
  var out = [];
  S.data.vencimientos.forEach(function(v){
    if (v.fecha_vencimiento === f) out.push({k:'venc', v:v});
    else if (v.fecha_inicio === f)  out.push({k:'inicio', v:v});
  });
  S.data.eventos.forEach(function(e){ if (e.fecha === f) out.push({k:'ev', e:e}); });
  return out;
}

/* ---------- calendario ---------- */
function renderBar(){
  $('#calTitulo').textContent = MESES[S.mes-1] + ' ' + S.anio;
  var sm = $('#calMes'), sa = $('#calAnio');
  if (!sm.options.length) MESES.forEach(function(m,i){ var o = document.createElement('option'); o.value = i+1; o.textContent = m.charAt(0).toUpperCase()+m.slice(1); sm.appendChild(o); });
  sm.value = S.mes;
  var y0 = new Date().getFullYear();
  sa.innerHTML = '';
  for (var y = Math.min(y0-5, S.anio); y <= Math.max(y0+5, S.anio); y++){ var o = document.createElement('option'); o.value = y; o.textContent = y; sa.appendChild(o); }
  sa.value = S.anio;
}

function renderCal(){
  var g = $('#calGrid'), html = '';
  ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].forEach(function(d){ html += '<div class="rh-head">' + d + '</div>'; });
  var primero = new Date(S.anio, S.mes-1, 1);
  var offset = primero.getDay();
  var dias = new Date(S.anio, S.mes, 0).getDate();
  var totalCeldas = Math.ceil((offset + dias) / 7) * 7;
  for (var i = 0; i < totalCeldas; i++){
    var fechaCelda = new Date(S.anio, S.mes-1, 1 - offset + i);
    var anioCelda = fechaCelda.getFullYear(), mesCelda = fechaCelda.getMonth() + 1, d = fechaCelda.getDate();
    var f = ymd(anioCelda, mesCelda, d);
    var otroMes = mesCelda !== S.mes || anioCelda !== S.anio;
    var items = itemsDelDia(f), chips = '';
    items.slice(0, 3).forEach(function(it){
      if (it.k === 'ev'){
        var e = it.e, done = (e.estado === 'Resuelto' || e.estado === 'Cancelado');
        chips += '<span class="rh-chip ev p-' + slug(e.prioridad) + (done ? ' done' : '') + '"' + (CAN_EDIT ? ' draggable="true"' : '') +
                 ' data-ev="' + e.id + '" title="' + esc(e.titulo + ' · ' + e.estado) + '">' + esc(e.titulo) + '</span>';
      } else if (it.k === 'venc'){
        chips += '<span class="rh-chip v-' + estVenc(it.v.fecha_vencimiento) + '" title="' + esc(it.v.empleado_nombre + ' - ' + it.v.tipo) + '">' + esc(it.v.empleado_nombre + ' · ' + it.v.tipo) + '</span>';
      } else {
        chips += '<span class="rh-chip v-inicio" title="Inicio: ' + esc(it.v.empleado_nombre + ' - ' + it.v.tipo) + '">▶ ' + esc(it.v.empleado_nombre + ' · ' + it.v.tipo) + '</span>';
      }
    });
    if (items.length > 3) chips += '<span class="rh-more">+' + (items.length - 3) + ' más</span>';
    html += '<div class="rh-day' + (f === S.data.hoy ? ' today' : '') + (otroMes ? ' other' : '') + '" tabindex="0" role="button"' + (f === S.data.hoy ? ' aria-current="date"' : '') + ' data-fecha="' + f + '" aria-label="' + esc(fmtFecha(f)) + ', ' + items.length + ' elemento(s)">' +
            '<div class="rh-num"><span>' + d + '</span>' + (f === S.data.hoy ? ' <small style="color:var(--rh-green);font-weight:bold">(Hoy)</small>' : '') + (CAN_EDIT ? '<span class="rh-add" title="Nueva alerta">＋</span>' : '') + '</div>' + chips + '</div>';
  }
  g.innerHTML = html;
}

function renderAlertas(){
  var box = $('#rhAlertas'), list = S.data.alertas || [];
  $('#rhAlertCount').textContent = list.length + ' alerta(s)';
  if (!list.length){ box.innerHTML = '<div class="rh-empty">No hay vencimientos ni alertas próximas. ¡Todo al día!</div>'; return; }
  box.innerHTML = list.map(function(a){
    var d = diff(a.fecha), cls = d < 0 ? 'late' : (d <= 7 ? 'soon' : '');
    var pill = d < 0 ? 'red' : (d <= 7 ? 'amber' : 'blue');
    var tag = a.kind === 'venc' ? '<span class="rh-pill gray">Vencimiento</span>' : '<span class="rh-pill blue">' + esc(a.estado) + '</span>';
    return '<button type="button" class="rh-alert ' + cls + '" data-abrir="' + a.fecha + '">' +
      '<div><strong>' + esc(a.titulo) + '</strong><small>' + esc(a.sub) + '</small></div>' +
      '<div style="text-align:right">' + tag + ' <span class="rh-pill ' + pill + '">' + esc(rel(a.fecha)) + '</span><small>' + esc(a.fecha) + '</small></div></button>';
  }).join('');
}

function renderKpis(){
  $('#kpiPend').textContent = S.data.resumen.pendientes;
  $('#kpiAtras').textContent = S.data.resumen.atrasadas;
}

function renderTodo(){
  renderBar(); renderCal(); renderAlertas(); renderKpis();
  if ($('#rhDia').open && S.vista === 'lista') renderDia();
}

/* ---------- diálogo del día ---------- */
function badge(txt, cls){ return '<span class="rh-pill ' + cls + '">' + esc(txt) + '</span>'; }
function prioCls(p){ return p === 'Alta' ? 'red' : (p === 'Media' ? 'amber' : 'gray'); }
function estCls(e){ return e === 'Resuelto' ? 'green' : (e === 'Cancelado' ? 'gray' : (e === 'En proceso' ? 'blue' : 'amber')); }

function comBtn(tipo, id, n){ return '<button type="button" class="rh-btn" data-act="com">💬 Comentarios (<b>' + (n||0) + '</b>)</button>'; }

function evHtml(e){
  var done = (e.estado === 'Resuelto' || e.estado === 'Cancelado');
  var h = '<article class="rh-item ev p-' + slug(e.prioridad) + (done ? ' done' : '') + '" data-tipo="evento" data-id="' + e.id + '">';
  h += '<div class="rh-badges">' + badge(e.tipo, 'blue') + badge('Prioridad ' + e.prioridad.toLowerCase(), prioCls(e.prioridad)) + (CAN_EDIT ? '' : badge(e.estado, estCls(e.estado))) + '</div>';
  h += '<h4>' + esc(e.titulo) + '</h4>';
  if (e.empleado_nombre) h += '<div class="meta">👤 ' + esc(e.empleado_nombre) + '</div>';
  if (+e.dias_aviso > 0) h += '<div class="meta">🔔 Avisar ' + (+e.dias_aviso) + ' día(s) antes</div>';
  if (e.detalle) h += '<p>' + esc(e.detalle) + '</p>';
  h += '<div class="rh-ctrl">';
  if (CAN_EDIT){
    h += '<select data-act="estado" aria-label="Estado">' + ESTADOS.map(function(s){ return '<option' + (s === e.estado ? ' selected' : '') + '>' + esc(s) + '</option>'; }).join('') + '</select>';
    h += '<button type="button" class="rh-btn" data-act="editar">✏️ Editar</button>';
    h += '<button type="button" class="rh-btn danger" data-act="eliminar">🗑 Eliminar</button>';
  }
  h += comBtn('evento', e.id, e.ncom) + '</div><div class="rh-com" hidden></div></article>';
  return h;
}

function vencHtml(v, inicio){
  var est = estVenc(v.fecha_vencimiento);
  var cls = {vencido:'red', proximo:'amber', vigente:'green'}[est];
  var h = '<article class="rh-item ' + (inicio ? 'inicio' : 'venc s-' + est) + '" data-tipo="venc" data-id="' + v.id + '">';
  h += '<div class="rh-badges">' + badge(inicio ? 'Inicio' : 'Vencimiento', 'gray') + badge(v.tipo, 'blue') + (inicio ? '' : badge(rel(v.fecha_vencimiento), cls)) + '</div>';
  h += '<h4>' + esc(v.empleado_nombre) + '</h4>';
  h += '<div class="meta">' + (v.fecha_inicio ? 'Desde ' + esc(v.fecha_inicio) + ' · ' : '') + 'Vence ' + esc(v.fecha_vencimiento) + '</div>';
  if (v.observaciones) h += '<p>' + esc(v.observaciones) + '</p>';
  h += '<div class="rh-ctrl">';
  if (CAN_EDIT) h += '<a class="rh-btn" href="?editar=' + v.id + '#gestion">✏️ Editar registro</a>';
  h += comBtn('venc', v.id, v.ncom) + '</div><div class="rh-com" hidden></div></article>';
  return h;
}

function opts(arr, sel){ return arr.map(function(x){ return '<option' + (x === sel ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join(''); }

function formHtml(e, fecha){
  e = e || {};
  return '<form class="rh-form" id="rhForm" autocomplete="off">' +
    '<input type="hidden" name="id" value="' + esc(e.id || '') + '">' +
    '<label class="full">Título<input name="titulo" required maxlength="160" placeholder="Ej: Renovar carnet de conducir de Pérez" value="' + esc(e.titulo || '') + '"></label>' +
    '<label>Tipo<select name="tipo">' + opts(TIPOS, e.tipo || 'Alerta') + '</select></label>' +
    '<label>Estado<select name="estado">' + opts(ESTADOS, e.estado || 'Pendiente') + '</select></label>' +
    '<label>Fecha<input type="date" name="fecha" required value="' + esc(e.fecha || fecha) + '"></label>' +
    '<label>Prioridad<select name="prioridad">' + opts(PRIOS, e.prioridad || 'Media') + '</select></label>' +
    '<label>Empleado (opcional)<input name="empleado_nombre" list="rhEmpleados" maxlength="150" value="' + esc(e.empleado_nombre || '') + '"></label>' +
    '<label>Avisar con anticipación (días)<input type="number" name="dias_aviso" min="0" max="60" value="' + esc(e.dias_aviso || 0) + '"></label>' +
    '<label class="full">Detalles / observaciones<textarea name="detalle" placeholder="Qué hay que hacer, a quién avisar, documentación necesaria…">' + esc(e.detalle || '') + '</textarea></label>' +
    '<div class="actions"><button type="button" class="rh-btn" data-act="cancelar">Cancelar</button><button class="rh-btn primary">' + (e.id ? 'Guardar cambios' : 'Crear alerta') + '</button></div></form>';
}

function renderDia(){
  var f = S.dia, dlg = $('#rhDia');
  $('#rhDiaSub').textContent = f === S.data.hoy ? 'Hoy' : rel(f);
  $('#rhDiaTitulo').textContent = fmtFecha(f);
  var body = $('#rhDiaBody'), foot = $('#rhDiaFoot');
  if (S.vista === 'form'){
    body.innerHTML = formHtml(S.edit, f); foot.innerHTML = '';
    var inp = body.querySelector('input[name=titulo]'); if (inp) inp.focus();
    return;
  }
  var items = itemsDelDia(f);
  body.innerHTML = items.length ? items.map(function(it){
    return it.k === 'ev' ? evHtml(it.e) : vencHtml(it.v, it.k === 'inicio');
  }).join('') : '<div class="rh-empty">No hay nada programado para este día.' + (CAN_EDIT ? '<br>Usá “Nueva alerta” para agregar una.' : '') + '</div>';
  foot.innerHTML = CAN_EDIT ? '<button type="button" class="rh-btn primary" data-act="nueva">＋ Nueva alerta</button>' : '';
}

function abrirDia(f, vista, ev){
  S.dia = f; S.vista = vista || 'lista'; S.edit = ev || null;
  var p = f.split('-').map(Number);
  var abrir = function(){ renderDia(); var d = $('#rhDia'); if (!d.open) d.showModal(); };
  if (p[0] !== S.anio || p[1] !== S.mes){ S.anio = p[0]; S.mes = p[1]; reload().then(abrir); }
  else abrir();
}

/* ---------- comentarios ---------- */
function cargarCom(item){
  var box = item.querySelector('.rh-com');
  return getJson(URL_BASE + '?ajax=comentarios&tipo=' + item.dataset.tipo + '&id=' + item.dataset.id).then(function(d){
    var list = d.ok ? d.comentarios : [];
    var h = list.length ? list.map(function(c){
      return '<div class="rh-com-item"><div class="who"><span><strong>' + esc(c.autor || 'Sistema') + '</strong> · ' + esc(c.creado_en) + '</span>' +
        (CAN_EDIT ? '<button type="button" data-act="delcom" data-cid="' + c.id + '">Borrar</button>' : '') + '</div><div>' + esc(c.comentario) + '</div></div>';
    }).join('') : '<div class="meta" style="font-size:12px;color:#6b7a70">Sin comentarios todavía.</div>';
    if (CAN_EDIT) h += '<form data-form="com"><textarea name="comentario" placeholder="Escribí un comentario…" required></textarea><button class="rh-btn primary">Enviar</button></form>';
    box.innerHTML = h;
    var b = item.querySelector('[data-act=com] b'); if (b) b.textContent = list.length;
  });
}

/* ---------- eventos de la interfaz ---------- */
$('#calGrid').addEventListener('click', function(e){
  var day = e.target.closest('.rh-day[data-fecha]'); if (!day) return;
  var f = day.dataset.fecha;
  if (CAN_EDIT && e.target.closest('.rh-add')) abrirDia(f, 'form');
  else abrirDia(f, 'lista');
});
$('#calGrid').addEventListener('keydown', function(e){
  if (e.key !== 'Enter' && e.key !== ' ') return;
  var day = e.target.closest('.rh-day[data-fecha]'); if (!day) return;
  e.preventDefault(); abrirDia(day.dataset.fecha, 'lista');
});

/* arrastrar y soltar para reprogramar */
$('#calGrid').addEventListener('dragstart', function(e){
  var c = e.target.closest('[data-ev]'); if (!c) return;
  e.dataTransfer.setData('text/plain', c.dataset.ev); e.dataTransfer.effectAllowed = 'move';
});
$('#calGrid').addEventListener('dragover', function(e){
  var d = e.target.closest('.rh-day[data-fecha]'); if (!d) return;
  e.preventDefault();
  document.querySelectorAll('.rh-day.drop').forEach(function(x){ if (x !== d) x.classList.remove('drop'); });
  d.classList.add('drop');
});
$('#calGrid').addEventListener('dragleave', function(e){ if (e.target.classList) e.target.classList.remove('drop'); });
$('#calGrid').addEventListener('drop', function(e){
  var d = e.target.closest('.rh-day[data-fecha]'); if (!d) return;
  e.preventDefault();
  document.querySelectorAll('.rh-day.drop').forEach(function(x){ x.classList.remove('drop'); });
  var id = e.dataTransfer.getData('text/plain'); if (!id) return;
  post({accion:'ev_mover', id:id, fecha:d.dataset.fecha}).then(function(r){
    if (r.ok){ toast('Alerta reprogramada al ' + d.dataset.fecha); reload(); } else toast(r.error, true);
  });
});

$('#rhAlertas').addEventListener('click', function(e){
  var b = e.target.closest('[data-abrir]'); if (b) abrirDia(b.dataset.abrir, 'lista');
});

function irMes(delta){
  var d = new Date(S.anio, S.mes - 1 + delta, 1);
  S.anio = d.getFullYear(); S.mes = d.getMonth() + 1; reload();
}
$('#calPrev').addEventListener('click', function(){ irMes(-1); });
$('#calNext').addEventListener('click', function(){ irMes(1); });
$('#calHoy').addEventListener('click', function(){ var h = parse(S.data.hoy); S.anio = h.getFullYear(); S.mes = h.getMonth() + 1; reload(); });
$('#calMes').addEventListener('change', function(){ S.mes = +this.value; reload(); });
$('#calAnio').addEventListener('change', function(){ S.anio = +this.value; reload(); });
if ($('#calNueva')) $('#calNueva').addEventListener('click', function(){
  var h = S.data.hoy, p = h.split('-').map(Number);
  abrirDia((p[0] === S.anio && p[1] === S.mes) ? h : ymd(S.anio, S.mes, 1), 'form');
});
$('#rhCerrar').addEventListener('click', function(){ $('#rhDia').close(); });
$('#rhDia').addEventListener('click', function(e){ if (e.target === this) this.close(); });

/* acciones dentro del diálogo */
$('#rhDia').addEventListener('click', function(e){
  var btn = e.target.closest('[data-act]'); if (!btn) return;
  var act = btn.dataset.act, item = btn.closest('.rh-item');
  if (act === 'nueva'){ S.vista = 'form'; S.edit = null; renderDia(); }
  else if (act === 'cancelar'){ S.vista = 'lista'; S.edit = null; renderDia(); }
  else if (act === 'editar'){
    var ev = S.data.eventos.find(function(x){ return String(x.id) === item.dataset.id; });
    S.vista = 'form'; S.edit = ev; renderDia();
  }
  else if (act === 'eliminar'){
    if (!confirm('¿Eliminar esta alerta y sus comentarios?')) return;
    post({accion:'ev_eliminar', id:item.dataset.id}).then(function(r){
      if (r.ok){ toast('Alerta eliminada'); reload(); } else toast(r.error, true);
    });
  }
  else if (act === 'com'){
    var box = item.querySelector('.rh-com');
    if (box.hidden){ box.hidden = false; cargarCom(item); } else box.hidden = true;
  }
  else if (act === 'delcom'){
    if (!confirm('¿Borrar este comentario?')) return;
    post({accion:'com_eliminar', id:btn.dataset.cid}).then(function(r){
      if (r.ok){ cargarCom(item); reload(); } else toast(r.error, true);
    });
  }
});

$('#rhDia').addEventListener('change', function(e){
  if (e.target.dataset.act !== 'estado') return;
  var item = e.target.closest('.rh-item');
  post({accion:'ev_estado', id:item.dataset.id, estado:e.target.value}).then(function(r){
    if (r.ok){ toast('Estado actualizado'); reload(); } else toast(r.error, true);
  });
});

$('#rhDia').addEventListener('submit', function(e){
  e.preventDefault();
  var form = e.target;
  if (form.id === 'rhForm'){
    var data = {accion:'ev_guardar'};
    new FormData(form).forEach(function(v, k){ data[k] = v; });
    post(data).then(function(r){
      if (!r.ok){ toast(r.error, true); return; }
      toast(data.id ? 'Alerta actualizada' : 'Alerta creada');
      S.vista = 'lista'; S.edit = null; S.dia = data.fecha;
      var p = data.fecha.split('-').map(Number);
      S.anio = p[0]; S.mes = p[1];
      reload().then(renderDia);
    });
  } else if (form.dataset.form === 'com'){
    var item = form.closest('.rh-item');
    var txt = form.comentario.value.trim(); if (!txt) return;
    post({accion:'com_agregar', tipo:item.dataset.tipo, id:item.dataset.id, comentario:txt}).then(function(r){
      if (r.ok){ cargarCom(item); reload(); } else toast(r.error, true);
    });
  }
});

/* ---------- tabla de seguimiento: filtros + CSV ---------- */
var tabla = $('#segTabla');
function filtrar(){
  var q = $('#fBuscar').value.trim().toLowerCase(), t = $('#fTipo').value, s = $('#fEstado').value, n = 0;
  tabla.querySelectorAll('tbody tr').forEach(function(tr){
    var ok = (!q || tr.dataset.q.indexOf(q) > -1) && (!t || tr.dataset.tipo === t) && (!s || tr.dataset.estado === s);
    tr.classList.toggle('rh-hide', !ok); if (ok) n++;
  });
  $('#segCount').textContent = n + ' registro(s)';
}
['fBuscar','fTipo','fEstado'].forEach(function(id){ $('#' + id).addEventListener('input', filtrar); });
$('#fCsv').addEventListener('click', function(){
  var rows = [['Empleado','Tipo','Inicio','Vencimiento','Estado','Observaciones']];
  tabla.querySelectorAll('tbody tr:not(.rh-hide)').forEach(function(tr){
    var td = tr.querySelectorAll('td');
    rows.push([0,1,2,3,4,5].map(function(i){ return td[i].textContent.replace(/\s+/g, ' ').trim(); }));
  });
  var csv = rows.map(function(r){ return r.map(function(c){ return '"' + c.replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
  var a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type:'text/csv;charset=utf-8'}));
  a.download = 'rrhh_seguimiento_' + S.data.hoy + '.csv'; a.click();
  setTimeout(function(){ URL.revokeObjectURL(a.href); }, 1000);
});

renderTodo();
})();
</script>
</body>
</html>
