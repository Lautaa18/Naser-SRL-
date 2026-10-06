<?php
require __DIR__ . '/config/auth.php';
requireLogin();
verify_csrf(); // protege todos los formularios POST de esta pagina
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require_once __DIR__ . '/config/formularios_catalogo.php';

$slug = 'ventas';

$st = $pdo->prepare('SELECT id,nombre,slug FROM sectores WHERE slug=? LIMIT 1');
$st->execute([$slug]);
$sector = $st->fetch();

if (!$sector) {
    http_response_code(404);
    exit('Sector Ventas no encontrado. Verifica que exista el sector con slug "ventas".');
}

$sid = (int)$sector['id'];

if (!puedeVerSector($pdo, $sid)) {
    http_response_code(403);
    exit('No tenés acceso al sector Ventas.');
}

$canEdit = puedeEditarSector($pdo, $sid);
$uid = (int)($_SESSION['usuario_id'] ?? 0);
$msg = '';
$err = '';

function ventasAudit(PDO $pdo, int $uid, string $accion, string $detalle): void {
    try {
        $st = $pdo->prepare('INSERT INTO actividad(usuario_id,accion,detalle) VALUES(?,?,?)');
        $st->execute([$uid,$accion,$detalle]);
    } catch (Throwable $e) {
        // Ignorar si la tabla no existe
    }
}

function ventasExigirEdicion(bool $canEdit): void {
    if (!$canEdit) {
        http_response_code(403);
        exit('No tenés permiso para modificar el sector Ventas.');
    }
}

function normalizarEstadoContrato(string $v): string {
    return in_array($v, ['Activo', 'En Proceso', 'Licitación', 'Finalizado'], true) ? $v : 'Activo';
}

function normalizarEstadoCotizacion(string $v): string {
    return in_array($v, ['Ganada', 'En Estudio', 'No Adjudicada'], true) ? $v : 'En Estudio';
}

$uploadDir = dirname(__DIR__) . '/uploads/ventas';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ventasExigirEdicion($canEdit);

    try {
        $accion = $_POST['accion'] ?? '';

        /* =====================================================
           1. CLIENTES + CONTRATOS
           ===================================================== */
        if ($accion === 'guardar_contrato') {
            $id = (int)($_POST['id'] ?? 0);
            $razon = trim($_POST['razon_social'] ?? '');
            $cuit = trim($_POST['cuit'] ?? '');
            $contacto = trim($_POST['contacto_nombre'] ?? '');
            $email = trim($_POST['contacto_email'] ?? '');
            $telefono = trim($_POST['contacto_telefono'] ?? '');
            $provincia = trim($_POST['provincia'] ?? 'Neuquén');

            $numero = trim($_POST['numero_contrato'] ?? '');
            $servicio = trim($_POST['servicio_operativo'] ?? '');
            $monto = (float)($_POST['monto_estimado_usd'] ?? 0);
            $inicio = ($_POST['fecha_inicio'] ?? '') ?: null;
            $fin = ($_POST['fecha_fin'] ?? '') ?: null;
            $estado = normalizarEstadoContrato($_POST['estado'] ?? 'Activo');
            $obs = trim($_POST['observaciones'] ?? '');

            if ($razon === '' || $numero === '' || $servicio === '') {
                throw new RuntimeException('Completá cliente, número de contrato y servicio.');
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $st = $pdo->prepare('SELECT cliente_id FROM ventas_contratos WHERE id=? AND sector_id=? LIMIT 1');
                $st->execute([$id,$sid]);
                $clienteId = (int)$st->fetchColumn();
                if (!$clienteId) throw new RuntimeException('Contrato no encontrado.');

                $st = $pdo->prepare('UPDATE ventas_clientes
                    SET razon_social=?,cuit=?,contacto_nombre=?,contacto_email=?,contacto_telefono=?,provincia=?,actualizado_por=?,actualizado_en=NOW()
                    WHERE id=? AND sector_id=?');
                $st->execute([$razon,$cuit,$contacto,$email,$telefono,$provincia,$uid,$clienteId,$sid]);

                $st = $pdo->prepare('UPDATE ventas_contratos
                    SET numero_contrato=?,servicio_operativo=?,monto_estimado_usd=?,fecha_inicio=?,fecha_fin=?,estado=?,observaciones=?,actualizado_por=?,actualizado_en=NOW()
                    WHERE id=? AND sector_id=?');
                $st->execute([$numero,$servicio,$monto,$inicio,$fin,$estado,$obs,$uid,$id,$sid]);

                $pdo->commit();
                ventasAudit($pdo,$uid,'ventas_contrato_editar',"Contrato #$id - $razon");
                $msg = 'Contrato actualizado correctamente.';
            } else {
                $st = $pdo->prepare('INSERT INTO ventas_clientes
                    (sector_id,razon_social,cuit,contacto_nombre,contacto_email,contacto_telefono,provincia,creado_por,actualizado_por)
                    VALUES(?,?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$razon,$cuit,$contacto,$email,$telefono,$provincia,$uid,$uid]);
                $clienteId = (int)$pdo->lastInsertId();

                $st = $pdo->prepare('INSERT INTO ventas_contratos
                    (sector_id,cliente_id,numero_contrato,servicio_operativo,monto_estimado_usd,fecha_inicio,fecha_fin,estado,observaciones,creado_por,actualizado_por)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$clienteId,$numero,$servicio,$monto,$inicio,$fin,$estado,$obs,$uid,$uid]);

                $contratoId = (int)$pdo->lastInsertId();
                $pdo->commit();

                ventasAudit($pdo,$uid,'ventas_contrato_crear',"Contrato #$contratoId - $razon");
                $msg = 'Cliente y contrato registrados correctamente.';
            }
        }

        if ($accion === 'eliminar_contrato') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT cliente_id,numero_contrato FROM ventas_contratos WHERE id=? AND sector_id=? LIMIT 1');
            $st->execute([$id,$sid]);
            $row = $st->fetch();
            if (!$row) throw new RuntimeException('Contrato no encontrado.');

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM ventas_contratos WHERE id=? AND sector_id=?')->execute([$id,$sid]);

            $st = $pdo->prepare('SELECT COUNT(*) FROM ventas_contratos WHERE cliente_id=?');
            $st->execute([(int)$row['cliente_id']]);
            if ((int)$st->fetchColumn() === 0) {
                $pdo->prepare('DELETE FROM ventas_clientes WHERE id=? AND sector_id=?')->execute([(int)$row['cliente_id'],$sid]);
            }

            $pdo->commit();
            ventasAudit($pdo,$uid,'ventas_contrato_eliminar',(string)$row['numero_contrato']);
            $msg = 'Contrato eliminado.';
        }

        /* =====================================================
           2. PRECIOS
           ===================================================== */
        if ($accion === 'guardar_precio') {
            $id = (int)($_POST['id'] ?? 0);
            $servicio = trim($_POST['servicio_nombre'] ?? '');
            $unidad = trim($_POST['unidad_medida'] ?? '');
            $modalidad = trim($_POST['modalidad'] ?? '');
            $tarifa = (float)($_POST['tarifa_base_usd'] ?? 0);
            $ticket = (float)($_POST['ticket_promedio_tipo_usd'] ?? 0);

            if ($servicio === '' || $unidad === '') throw new RuntimeException('Completá servicio y unidad.');

            if ($id) {
                $st = $pdo->prepare('UPDATE ventas_precios
                    SET servicio_nombre=?,unidad_medida=?,modalidad=?,tarifa_base_usd=?,ticket_promedio_tipo_usd=?,actualizado_por=?,actualizado_en=NOW()
                    WHERE id=? AND sector_id=?');
                $st->execute([$servicio,$unidad,$modalidad,$tarifa,$ticket,$uid,$id,$sid]);
                ventasAudit($pdo,$uid,'ventas_precio_editar',$servicio);
                $msg = 'Tarifa actualizada.';
            } else {
                $st = $pdo->prepare('INSERT INTO ventas_precios
                    (sector_id,servicio_nombre,unidad_medida,modalidad,tarifa_base_usd,ticket_promedio_tipo_usd,creado_por,actualizado_por)
                    VALUES(?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$servicio,$unidad,$modalidad,$tarifa,$ticket,$uid,$uid]);
                ventasAudit($pdo,$uid,'ventas_precio_crear',$servicio);
                $msg = 'Tarifa agregada.';
            }
        }

        if ($accion === 'eliminar_precio') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM ventas_precios WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            ventasAudit($pdo,$uid,'ventas_precio_eliminar',"Precio #$id");
            $msg = 'Tarifa eliminada.';
        }

        /* =====================================================
           3. COSTOS OPERATIVOS
           ===================================================== */
        if ($accion === 'guardar_costo') {
            $id = (int)($_POST['id'] ?? 0);
            $linea = trim($_POST['linea_servicio'] ?? '');
            $ingreso = (float)($_POST['ingreso_diario_usd'] ?? 0);
            $directo = (float)($_POST['costo_directo_finanzas_usd'] ?? 0);
            $mantenimiento = (float)($_POST['costo_mantenimiento_usd'] ?? 0);

            if ($linea === '' || $ingreso <= 0) throw new RuntimeException('Completá línea de servicio e ingreso diario.');

            $margen = $ingreso > 0 ? (($ingreso - ($directo + $mantenimiento)) / $ingreso) * 100 : 0;
            $estado = $margen >= 40 ? 'Excelente' : ($margen >= 20 ? 'Sostenible' : ($margen >= 10 ? 'Aceptable' : 'Crítico'));

            if ($id) {
                $st=$pdo->prepare('UPDATE ventas_costos_operativos
                    SET linea_servicio=?,ingreso_diario_usd=?,costo_directo_finanzas_usd=?,costo_mantenimiento_usd=?,estado_rentabilidad=?,actualizado_por=?,actualizado_en=NOW()
                    WHERE id=? AND sector_id=?');
                $st->execute([$linea,$ingreso,$directo,$mantenimiento,$estado,$uid,$id,$sid]);
                $msg='Costo operativo actualizado.';
            } else {
                $st=$pdo->prepare('INSERT INTO ventas_costos_operativos
                    (sector_id,linea_servicio,ingreso_diario_usd,costo_directo_finanzas_usd,costo_mantenimiento_usd,estado_rentabilidad,creado_por,actualizado_por)
                    VALUES(?,?,?,?,?,?,?,?)');
                $st->execute([$sid,$linea,$ingreso,$directo,$mantenimiento,$estado,$uid,$uid]);
                $msg='Costo operativo registrado.';
            }
            ventasAudit($pdo,$uid,'ventas_costo',$linea);
        }

        /* =====================================================
           4. PRESENTACIONES / NASER Y PETRONEU
           ===================================================== */
        if ($accion === 'subir_presentacion') {
            $titulo=trim($_POST['titulo']??'');
            $empresa=trim($_POST['empresa']??'Naser');
            $categoria=trim($_POST['categoria']??'General');

            if($titulo==='') throw new RuntimeException('Ingresá un título.');
            if(empty($_FILES['archivo']['name']) || ($_FILES['archivo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) {
                throw new RuntimeException('Seleccioná un archivo.');
            }

            $orig=$_FILES['archivo']['name'];
            $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
            if(!in_array($ext,['pdf','ppt','pptx','doc','docx'],true)) throw new RuntimeException('Formato no permitido.');

            $safe='presentacion-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            if(!move_uploaded_file($_FILES['archivo']['tmp_name'],$uploadDir.'/'.$safe)) throw new RuntimeException('No se pudo guardar el archivo.');

            $st=$pdo->prepare('INSERT INTO ventas_presentaciones
                (sector_id,titulo,empresa,categoria,archivo_path,creado_por)
                VALUES(?,?,?,?,?,?)');
            $st->execute([$sid,$titulo,$empresa,$categoria,'ventas/'.$safe,$uid]);

            ventasAudit($pdo,$uid,'ventas_presentacion',$titulo);
            $msg='Presentación cargada exitosamente.';
        }

        if ($accion === 'eliminar_presentacion') {
            $id=(int)($_POST['id']??0);
            $st=$pdo->prepare('SELECT archivo_path,titulo FROM ventas_presentaciones WHERE id=? AND sector_id=? LIMIT 1');
            $st->execute([$id,$sid]);
            $p=$st->fetch();
            if($p){
                $pdo->prepare('DELETE FROM ventas_presentaciones WHERE id=? AND sector_id=?')->execute([$id,$sid]);
                $ruta=dirname(__DIR__).'/uploads/'.ltrim($p['archivo_path'],'/');
                if(is_file($ruta)) @unlink($ruta);
                ventasAudit($pdo,$uid,'ventas_presentacion_eliminar',$p['titulo']);
            }
            $msg='Presentación eliminada.';
        }

        /* =====================================================
           5. PLANTILLA DE SEGUIMIENTO CRM INTERACTIVA
           ===================================================== */
        if ($accion === 'guardar_crm') {
            $clienteId=(int)($_POST['cliente_id']??0);
            $oportunidad=trim($_POST['oportunidad_servicio']??'');
            $etapa=trim($_POST['etapa_pipeline']??'Prospecto');
            $fecha=($_POST['fecha_ultimo_contacto']??'')?:date('Y-m-d');
            $proxima=trim($_POST['proxima_accion']??'');
            $responsable=trim($_POST['responsable_naser']??($_SESSION['nombre']??''));

            $permitidas=['Prospecto','Cotizado','En Negociación','Cierre Ganado','Perdido'];
            if(!in_array($etapa,$permitidas,true)) $etapa='Prospecto';
            if(!$clienteId || $oportunidad==='') throw new RuntimeException('Seleccioná cliente e ingresá la oportunidad.');

            $st=$pdo->prepare('INSERT INTO ventas_crm
                (sector_id,cliente_id,oportunidad_servicio,etapa_pipeline,fecha_ultimo_contacto,proxima_accion,responsable_naser,creado_por,actualizado_por)
                VALUES(?,?,?,?,?,?,?,?,?)');
            $st->execute([$sid,$clienteId,$oportunidad,$etapa,$fecha,$proxima,$responsable,$uid,$uid]);

            ventasAudit($pdo,$uid,'ventas_crm',$oportunidad);
            $msg='Seguimiento CRM registrado.';
        }

        if ($accion === 'eliminar_crm') {
            $id=(int)($_POST['id']??0);
            $pdo->prepare('DELETE FROM ventas_crm WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            ventasAudit($pdo,$uid,'ventas_crm_eliminar',"CRM #$id");
            $msg='Seguimiento eliminado.';
        }

        /* =====================================================
           6. CALENDARIO INTERACTIVO & ALERTAS COMPLETO (CRUD)
           ===================================================== */
        if ($accion === 'guardar_evento_calendario') {
            $id = (int)($_POST['evento_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $mensaje = trim($_POST['mensaje'] ?? '');
            $tipo = trim($_POST['tipo_alerta'] ?? 'Aviso');
            $fechaAlarma = trim($_POST['fecha_alarma'] ?? '');
            $horaAlarma = substr(trim($_POST['hora_alarma'] ?? '09:00'), 0, 5);
            $leido = !empty($_POST['leido']) ? 1 : 0;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaAlarma)) $fechaAlarma = date('Y-m-d');
            if (!preg_match('/^\d{2}:\d{2}$/', $horaAlarma)) $horaAlarma = '09:00';

            if (!in_array($tipo, ['Alerta', 'Aviso', 'Finanzas', 'Vencimiento'], true)) $tipo = 'Aviso';
            if ($titulo === '') throw new RuntimeException('Ingresá un título o referencia.');

            $timestamp = $fechaAlarma . ' ' . $horaAlarma . ':00';
            $detalleMensaje = $mensaje;

            if ($id > 0) {
                $st = $pdo->prepare('UPDATE ventas_notificaciones 
                    SET titulo=?, mensaje=?, tipo_alerta=?, created_at=?, leido=?
                    WHERE id=? AND sector_id=?');
                $st->execute([$titulo, $detalleMensaje, $tipo, $timestamp, $leido, $id, $sid]);
                ventasAudit($pdo, $uid, 'ventas_alerta_editar', "$titulo (#$id)");
                $msg = 'Evento / Alerta actualizado correctamente.';
            } else {
                $st = $pdo->prepare('INSERT INTO ventas_notificaciones
                    (sector_id,titulo,mensaje,tipo_alerta,leido,creado_por,created_at)
                    VALUES(?,?,?,?,?,?,?)');
                $st->execute([$sid, $titulo, $detalleMensaje, $tipo, $leido, $uid, $timestamp]);
                ventasAudit($pdo, $uid, 'ventas_alerta_crear', "$titulo ($fechaAlarma)");
                $msg = 'Evento / Alarma agendado correctamente en el calendario.';
            }
        }

        if ($accion === 'eliminar_evento_calendario') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM ventas_notificaciones WHERE id=? AND sector_id=?')->execute([$id, $sid]);
            ventasAudit($pdo, $uid, 'ventas_alerta_eliminar', "Alerta #$id");
            $msg = 'Evento / Alarma eliminado del calendario.';
        }

        if ($accion === 'marcar_leida') {
            $id=(int)($_POST['id']??0);
            $pdo->prepare('UPDATE ventas_notificaciones SET leido=1 WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            $msg='Notificación marcada como completada/leída.';
        }

        /* =====================================================
           COTIZACIONES / LICITACIONES
           ===================================================== */
        if ($accion === 'guardar_cotizacion') {
            $clienteId = (int)($_POST['cliente_id'] ?? 0);
            $codigo = trim($_POST['codigo_cotizacion'] ?? '');
            $montoCot = (float)($_POST['monto_usd'] ?? 0);
            $estadoCot = normalizarEstadoCotizacion($_POST['estado_kpi'] ?? 'En Estudio');
            $fPres = ($_POST['fecha_presentacion'] ?? '') ?: date('Y-m-d');
            $fResol = ($_POST['fecha_resolucion'] ?? '') ?: null;

            if (!$clienteId || $codigo === '') throw new RuntimeException('Seleccioná cliente e ingresá el código de cotización.');

            $st = $pdo->prepare('INSERT INTO ventas_cotizaciones
                (sector_id,cliente_id,codigo_cotizacion,monto_usd,estado_kpi,fecha_presentacion,fecha_resolucion,creado_por,actualizado_por)
                VALUES(?,?,?,?,?,?,?,?,?)');
            $st->execute([$sid,$clienteId,$codigo,$montoCot,$estadoCot,$fPres,$fResol,$uid,$uid]);

            ventasAudit($pdo,$uid,'ventas_cotizacion',$codigo);
            $msg = 'Cotización registrada.';
        }

        if ($accion === 'eliminar_cotizacion') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM ventas_cotizaciones WHERE id=? AND sector_id=?')->execute([$id,$sid]);
            ventasAudit($pdo,$uid,'ventas_cotizacion_eliminar',"Cotización #$id");
            $msg = 'Cotización eliminada.';
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

/* =========================================================
   CONSULTAS
   ========================================================= */

$contratos=$pdo->prepare('SELECT c.*,cl.razon_social,cl.cuit,cl.contacto_nombre,cl.contacto_email,cl.contacto_telefono,cl.provincia,u.nombre actualizado_nombre
    FROM ventas_contratos c
    JOIN ventas_clientes cl ON cl.id=c.cliente_id
    LEFT JOIN usuarios u ON u.id=c.actualizado_por
    WHERE c.sector_id=?
    ORDER BY c.fecha_inicio DESC,c.id DESC');
$contratos->execute([$sid]);
$contratos=$contratos->fetchAll();

$clientes=$pdo->prepare('SELECT * FROM ventas_clientes WHERE sector_id=? ORDER BY razon_social');
$clientes->execute([$sid]);
$clientes=$clientes->fetchAll();

$precios=$pdo->prepare('SELECT p.*,u.nombre actualizado_nombre FROM ventas_precios p LEFT JOIN usuarios u ON u.id=p.actualizado_por WHERE p.sector_id=? ORDER BY p.servicio_nombre');
$precios->execute([$sid]);
$precios=$precios->fetchAll();

$costos=$pdo->prepare('SELECT c.*,u.nombre actualizado_nombre FROM ventas_costos_operativos c LEFT JOIN usuarios u ON u.id=c.actualizado_por WHERE c.sector_id=? ORDER BY c.linea_servicio');
$costos->execute([$sid]);
$costos=$costos->fetchAll();

$cotizaciones=$pdo->prepare('SELECT co.*,cl.razon_social FROM ventas_cotizaciones co JOIN ventas_clientes cl ON cl.id=co.cliente_id WHERE co.sector_id=? ORDER BY co.fecha_presentacion DESC,co.id DESC');
$cotizaciones->execute([$sid]);
$cotizaciones=$cotizaciones->fetchAll();

$presentaciones=$pdo->prepare('SELECT * FROM ventas_presentaciones WHERE sector_id=? ORDER BY fecha_carga DESC,id DESC');
$presentaciones->execute([$sid]);
$presentaciones=$presentaciones->fetchAll();

$crm=$pdo->prepare('SELECT cr.*,cl.razon_social FROM ventas_crm cr JOIN ventas_clientes cl ON cl.id=cr.cliente_id WHERE cr.sector_id=? ORDER BY cr.fecha_ultimo_contacto DESC,cr.id DESC');
$crm->execute([$sid]);
$crm=$crm->fetchAll();

$alertas=$pdo->prepare('SELECT * FROM ventas_notificaciones WHERE sector_id=? ORDER BY created_at DESC,id DESC LIMIT 150');
$alertas->execute([$sid]);
$alertas=$alertas->fetchAll();

/* KPI */
$totalCot=count($cotizaciones);
$ganadas=count(array_filter($cotizaciones,fn($r)=>$r['estado_kpi']==='Ganada'));
$eficiencia=$totalCot ? round(($ganadas/$totalCot)*100,1) : 0;
$activos=count(array_filter($contratos,fn($r)=>$r['estado']==='Activo'));
$enProceso=count(array_filter($contratos,fn($r)=>$r['estado']==='En Proceso'));
$licitacion=count(array_filter($contratos,fn($r)=>$r['estado']==='Licitación'));
$finalizados=count(array_filter($contratos,fn($r)=>$r['estado']==='Finalizado'));
$ticketPromedio=$precios ? array_sum(array_map(fn($r)=>(float)$r['ticket_promedio_tipo_usd'],$precios))/count($precios) : 0;
$noLeidas=count(array_filter($alertas,fn($r)=>(int)$r['leido']===0));

/* PARÁMETROS DEL CALENDARIO SANITIZADOS */
$calMes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$calAnio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

if ($calMes < 1) { $calMes = 12; $calAnio--; }
if ($calMes > 12) { $calMes = 1; $calAnio++; }
if ($calAnio < 1970 || $calAnio > 2099) { $calAnio = (int)date('Y'); }

$editContrato=null;
if($canEdit && !empty($_GET['editar_contrato'])){
    $id=(int)$_GET['editar_contrato'];
    foreach($contratos as $r) if((int)$r['id']===$id){$editContrato=$r;break;}
}

$editPrecio=null;
if($canEdit && !empty($_GET['editar_precio'])){
    $id=(int)$_GET['editar_precio'];
    foreach($precios as $r) if((int)$r['id']===$id){$editPrecio=$r;break;}
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ventas y Contratos | NASER SGI</title>
<link rel="stylesheet" href="<?=asset('/style.css')?>"><link rel="stylesheet" href="<?=asset('/css/modules.css')?>"><link rel="stylesheet" href="<?=asset('/css/checklists.css')?>">
<style>
:root{--vg:#15803d;--vd:#14532d;--vl:#e4ebe6;--vs:#f0fdf4;--vt:#1f2937;--vm:#6b7280}
.sales-hero{position:relative;overflow:hidden;background:linear-gradient(125deg,#103c26,#15803d);color:#fff;border-radius:20px;padding:27px 29px;margin-bottom:20px;box-shadow:0 12px 30px rgba(20,70,40,.13)}
.sales-hero:after{content:"";position:absolute;width:230px;height:230px;border-radius:50%;right:-70px;top:-110px;background:rgba(255,255,255,.08)}
.sales-hero h1{margin:4px 0 7px;font-size:30px}.sales-hero p{margin:0;color:#e8f7ed}.sales-hero .eyebrow{color:#d9f6e2}
.sales-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px;padding:8px;background:#fff;border:1px solid var(--vl);border-radius:15px}
.sales-tabs button{border:0;background:transparent;padding:10px 13px;border-radius:10px;font-size:12px;font-weight:800;color:#526057;cursor:pointer}
.sales-tabs button.active{background:var(--vs);color:var(--vd)}
.sales-tab{display:none}.sales-tab.active{display:block}
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0 24px}
.kpi{background:#fff;border:1px solid var(--vl);border-radius:16px;padding:18px;box-shadow:0 4px 16px rgba(31,41,55,.04)}
.kpi span{font-size:11px;text-transform:uppercase;font-weight:900;color:#718078}.kpi strong{display:block;font-size:29px;color:var(--vg);margin-top:6px}.kpi small{color:var(--vm)}
.sales-card{background:#fff;border:1px solid var(--vl);border-radius:17px;padding:19px;margin-bottom:16px;box-shadow:0 4px 16px rgba(31,41,55,.035)}
.sales-card h2,.sales-card h3{margin-top:0}.sales-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.sales-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sales-form .full{grid-column:1/-1}
.sales-form label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:800;color:#374151}
.sales-form input,.sales-form select,.sales-form textarea{width:100%;box-sizing:border-box;padding:10px 11px;border:1px solid #d6dfd8;border-radius:9px;background:#fbfdfb}
.sales-form textarea{min-height:80px}.sales-table-wrap{overflow:auto;border:1px solid var(--vl);border-radius:14px;background:#fff}
.sales-table{width:100%;border-collapse:collapse;min-width:820px}.sales-table th,.sales-table td{padding:11px 12px;border-bottom:1px solid #edf1ee;text-align:left;font-size:12px;vertical-align:top}
.sales-table th{background:#f1f7f3;color:#27583a;font-size:10px;text-transform:uppercase}.sales-table tr:last-child td{border-bottom:0}
.sales-actions{display:flex;gap:6px;flex-wrap:wrap}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef6f0;color:#25623a;font-size:10px;font-weight:900}
.pill.warn{background:#fff6dd;color:#8a6400}.pill.danger{background:#fff0ee;color:#9d1c13}.pill.info{background:#e0f2fe;color:#0369a1}
.notice{padding:13px 15px;border-radius:11px;margin:12px 0;font-weight:700;font-size:13px}.notice.ok{background:#eaf7ef;color:#176337}.notice.err{background:#fff1f0;color:#9b231b}
.calc-result{background:var(--vs);border:1px solid #ccebd8;border-radius:13px;padding:16px}.calc-result strong{display:block;font-size:28px;color:var(--vd)}
.permission{padding:10px 13px;border:1px solid var(--vl);background:#f8faf8;border-radius:11px;font-size:12px;margin-bottom:16px}

/* Estilos de Calendario Interactivo */
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #f8faf8; padding: 10px 15px; border-radius: 12px; border: 1px solid var(--vl); }
.calendar-header h3 { margin: 0; font-size: 18px; color: var(--vd); }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.calendar-day-head { text-align: center; font-weight: bold; font-size: 11px; padding: 8px; background: #f1f7f3; color: #27583a; border-radius: 6px; text-transform: uppercase; }
.calendar-cell { border: 1px solid var(--vl); min-height: 95px; border-radius: 10px; padding: 8px; background: #fff; cursor: pointer; transition: all 0.2s; position: relative; display: flex; flex-direction: column; justify-content: space-between; }
.calendar-cell:hover { background: var(--vs); border-color: var(--vg); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.calendar-cell.today { background: #f0fdf4; border: 2px solid var(--vg); }
.calendar-cell.empty { background: #fdfdfd; cursor: default; border: 1px dashed #e2e8f0; min-height: 95px; opacity: 0.3; box-shadow: none; pointer-events: none; }
.calendar-date-num { font-weight: 800; font-size: 13px; color: var(--vt); }
.calendar-events-container { margin-top: 4px; display: flex; flex-direction: column; gap: 3px; max-height: 60px; overflow-y: auto; }
.calendar-event-tag { font-size: 10px; padding: 3px 6px; border-radius: 5px; font-weight: 700; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.calendar-event-tag.Alerta { background: #fee2e2; color: #991b1b; }
.calendar-event-tag.Aviso { background: #e0f2fe; color: #0369a1; }
.calendar-event-tag.Vencimiento { background: #fef3c7; color: #92400e; }
.calendar-event-tag.Finanzas { background: #dcfce7; color: #166534; }
.calendar-event-tag.leido { opacity: 0.5; text-decoration: line-through; }

/* Modal y detalle de día */
.day-detail-box { background: #f9fafb; border: 1px solid var(--vl); border-radius: 12px; padding: 15px; margin-top: 15px; }
.day-event-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
</style>
</head>
<body>
<div class="app">
<?php sidebar($pdo,$slug); ?>
<main class="content">

<section class="sales-hero">
<p class="eyebrow">SERVICIOS NASER SRL · VENTAS & COMERCIAL</p>
<h1>Gestión Comercial, Contratos y Precios</h1>
<p>Clientes, contratos, tarifarios, costos, cotizaciones, CRM, presentaciones y calendario interactivo.</p>
</section>

<div class="permission">
🔐 <?php if($canEdit): ?>Tenés permisos para consultar y modificar Ventas.<?php else: ?>Modo consulta: solo el responsable de Ventas y el administrador pueden modificar.<?php endif; ?>
&nbsp; · &nbsp; <a href="<?=app_url('/php/sector.php?sector=ventas')?>">Abrir documentación del sector</a>
</div>

<?php if($msg):?><div class="notice ok"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="notice err"><?=h($err)?></div><?php endif;?>

<section class="kpi-grid">
<div class="kpi"><span>Conversión cotizaciones</span><strong><?=$eficiencia?>%</strong><small><?=$ganadas?> ganadas de <?=$totalCot?></small></div>
<div class="kpi"><span>Contratos Activos / En Proceso</span><strong><?=$activos?> / <?=$enProceso?></strong><small>Finalizados: <?=$finalizados?></small></div>
<div class="kpi"><span>En licitación</span><strong><?=$licitacion?></strong><small>Oportunidades/contratos</small></div>
<div class="kpi"><span>Ticket promedio tipo</span><strong>USD <?=number_format($ticketPromedio,0,',','.')?></strong><small><?=count($precios)?> tarifa(s)</small></div>
</section>

<div class="sales-tabs">
<button type="button" data-tab="contratos" class="active" onclick="tabVentas('contratos')">Clientes y Contratos</button>
<button type="button" data-tab="precios" onclick="tabVentas('precios')">Precios y Ticket</button>
<button type="button" data-tab="costos" onclick="tabVentas('costos')">Costos Operativos</button>
<button type="button" data-tab="presentaciones" onclick="tabVentas('presentaciones')">Presentaciones Naser &amp; Petroneu</button>
<button type="button" data-tab="crm" onclick="tabVentas('crm')">CRM Interactivo</button>
<button type="button" data-tab="calendario" onclick="tabVentas('calendario')">Calendario &amp; Alarmas</button>
<button type="button" data-tab="alertas" onclick="tabVentas('alertas')">Avisos <span class="pill danger"><?=$noLeidas?></span></button>
<button type="button" data-tab="formularios" onclick="tabVentas('formularios')">Formularios</button>
</div>

<!-- 1. CONTRATOS (ACTIVOS, EN PROCESO, FINALIZADOS) -->
<section id="tab-contratos" class="sales-tab active">
<?php if($canEdit):?>
<div class="sales-card">
<h2><?=$editContrato?'Modificar cliente / contrato':'Nuevo cliente / contrato'?></h2>
<form method="post" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="guardar_contrato">
<input type="hidden" name="id" value="<?=h($editContrato['id']??'')?>">
<label>Razón social<input name="razon_social" required value="<?=h($editContrato['razon_social']??'')?>"></label>
<label>CUIT<input name="cuit" value="<?=h($editContrato['cuit']??'')?>"></label>
<label>Contacto<input name="contacto_nombre" value="<?=h($editContrato['contacto_nombre']??'')?>"></label>
<label>Email<input type="email" name="contacto_email" value="<?=h($editContrato['contacto_email']??'')?>"></label>
<label>Teléfono<input name="contacto_telefono" value="<?=h($editContrato['contacto_telefono']??'')?>"></label>
<label>Provincia<input name="provincia" value="<?=h($editContrato['provincia']??'Neuquén')?>"></label>
<label>N° contrato<input name="numero_contrato" required value="<?=h($editContrato['numero_contrato']??'')?>"></label>
<label>Servicio operativo<input name="servicio_operativo" required value="<?=h($editContrato['servicio_operativo']??'')?>"></label>
<label>Monto estimado USD<input type="number" step="0.01" name="monto_estimado_usd" value="<?=h($editContrato['monto_estimado_usd']??0)?>"></label>
<label>Estado
    <select name="estado">
        <?php foreach(['Activo','En Proceso','Licitación','Finalizado'] as $v):?>
            <option <?=($editContrato['estado']??'Activo')===$v?'selected':''?>><?=$v?></option>
        <?php endforeach;?>
    </select>
</label>
<label>Fecha inicio<input type="date" name="fecha_inicio" value="<?=h($editContrato['fecha_inicio']??'')?>"></label>
<label>Fecha fin<input type="date" name="fecha_fin" value="<?=h($editContrato['fecha_fin']??'')?>"></label>
<label class="full">Observaciones<textarea name="observaciones"><?=h($editContrato['observaciones']??'')?></textarea></label>
<div class="full"><button class="btn primary">Guardar</button><?php if($editContrato):?> <a class="btn secondary" href="<?=app_url('/php/ventas.php')?>?tab=contratos">Cancelar</a><?php endif;?></div>
</form>
</div>
<?php endif;?>

<div class="sales-card">
<h2>Directorio de clientes y contratos</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Cliente</th><th>Contrato</th><th>Servicio</th><th>Monto</th><th>Vigencia</th><th>Estado</th><th>Responsable</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead>
<tbody>
<?php foreach($contratos as $r):?>
<tr>
<td><strong><?=h($r['razon_social'])?></strong><br><small><?=h($r['cuit']??'')?></small></td>
<td><?=h($r['numero_contrato'])?></td>
<td><?=h($r['servicio_operativo'])?></td>
<td>USD <?=number_format((float)$r['monto_estimado_usd'],2,',','.')?></td>
<td><?=h($r['fecha_inicio']??'')?> → <?=h($r['fecha_fin']??'')?></td>
<td>
    <span class="pill <?=$r['estado']==='Licitación'?'warn':($r['estado']==='En Proceso'?'info':($r['estado']==='Finalizado'?'danger':''))?>">
        <?=h($r['estado'])?>
    </span>
</td>
<td><?=h($r['actualizado_nombre']??'Sistema')?></td>
<?php if($canEdit):?>
<td class="sales-actions">
    <a class="btn secondary" href="?tab=contratos&editar_contrato=<?=(int)$r['id']?>">Editar</a>
    <form method="post" onsubmit="return confirm('¿Eliminar contrato?')"><?=csrf_field()?>
        <input type="hidden" name="accion" value="eliminar_contrato">
        <input type="hidden" name="id" value="<?=(int)$r['id']?>">
        <button class="btn secondary">Eliminar</button>
    </form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 2. PRECIOS Y TICKET TIPO -->
<section id="tab-precios" class="sales-tab">
<div class="sales-grid">
<?php if($canEdit):?>
<div class="sales-card">
<h2><?=$editPrecio?'Modificar tarifa':'Nueva tarifa'?></h2>
<form method="post" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="guardar_precio">
<input type="hidden" name="id" value="<?=h($editPrecio['id']??'')?>">
<label class="full">Servicio<input name="servicio_nombre" required value="<?=h($editPrecio['servicio_nombre']??'')?>"></label>
<label>Unidad<input name="unidad_medida" required placeholder="Por Día / Hora / Etapa" value="<?=h($editPrecio['unidad_medida']??'')?>"></label>
<label>Modalidad<input name="modalidad" value="<?=h($editPrecio['modalidad']??'')?>"></label>
<label>Tarifa base USD<input id="tarifaBase" type="number" step="0.01" name="tarifa_base_usd" value="<?=h($editPrecio['tarifa_base_usd']??0)?>"></label>
<label>Ticket promedio USD<input type="number" step="0.01" name="ticket_promedio_tipo_usd" value="<?=h($editPrecio['ticket_promedio_tipo_usd']??0)?>"></label>
<div class="full"><button class="btn primary">Guardar tarifa</button></div>
</form>
</div>
<?php endif;?>

<div class="sales-card">
<h2>Calculadora Ticket Tipo</h2>
<div class="sales-form">
<label>Tarifa diaria/base
<select id="calcTarifa">
<?php foreach($precios as $p):?>
<option value="<?=h($p['tarifa_base_usd'])?>"><?=h($p['servicio_nombre'])?> — USD <?=number_format((float)$p['tarifa_base_usd'],0,',','.')?></option>
<?php endforeach;?>
</select>
</label>
<label>Días / unidades<input type="number" id="calcDias" min="1" value="5"></label>
<label>Recargo / viáticos %<input type="number" id="calcRecargo" min="0" value="15"></label>
</div>
<div class="calc-result" style="margin-top:14px">
<span>Estimación comercial</span>
<strong id="calcResultado">USD 0</strong>
</div>
</div>
</div>

<div class="sales-card">
<h2>Tarifario vigente</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Servicio</th><th>Unidad</th><th>Modalidad</th><th>Tarifa Base</th><th>Ticket Tipo</th><th>Responsable</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
<tbody>
<?php foreach($precios as $p):?>
<tr>
<td><?=h($p['servicio_nombre'])?></td>
<td><?=h($p['unidad_medida'])?></td>
<td><?=h($p['modalidad'])?></td>
<td>USD <?=number_format((float)$p['tarifa_base_usd'],2,',','.')?></td>
<td>USD <?=number_format((float)$p['ticket_promedio_tipo_usd'],2,',','.')?></td>
<td><?=h($p['actualizado_nombre']??'Sistema')?></td>
<?php if($canEdit):?>
<td class="sales-actions">
    <a class="btn secondary" href="?tab=precios&editar_precio=<?=(int)$p['id']?>">Editar</a>
    <form method="post"><?=csrf_field()?>
        <input type="hidden" name="accion" value="eliminar_precio">
        <input type="hidden" name="id" value="<?=(int)$p['id']?>">
        <button class="btn secondary">Eliminar</button>
    </form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 3. COSTOS OPERATIVOS -->
<section id="tab-costos" class="sales-tab">
<?php if($canEdit):?>
<div class="sales-card">
<h2>Registrar costo operativo</h2>
<p>Compara el ingreso comercial estimado con los costos directos informados por Finanzas y Mantenimiento.</p>
<form method="post" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="guardar_costo">
<label class="full">Línea de servicio<input name="linea_servicio" required></label>
<label>Ingreso diario USD<input type="number" step="0.01" name="ingreso_diario_usd" required></label>
<label>Costo directo Finanzas USD<input type="number" step="0.01" name="costo_directo_finanzas_usd"></label>
<label>Costo Mantenimiento USD<input type="number" step="0.01" name="costo_mantenimiento_usd"></label>
<div><button class="btn primary">Guardar costo</button></div>
</form>
</div>
<?php endif;?>

<div class="sales-card">
<h2>Estructura de costos operativos</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Servicio</th><th>Ingreso Diario</th><th>Costo Finanzas</th><th>Mantenimiento</th><th>Margen %</th><th>Rentabilidad</th></tr></thead>
<tbody>
<?php foreach($costos as $c): 
    $ing=(float)$c['ingreso_diario_usd'];
    $tot=(float)$c['costo_directo_finanzas_usd']+(float)$c['costo_mantenimiento_usd'];
    $m=$ing>0?(($ing-$tot)/$ing)*100:0;
?>
<tr>
<td><?=h($c['linea_servicio'])?></td>
<td>USD <?=number_format($ing,2,',','.')?></td>
<td>USD <?=number_format((float)$c['costo_directo_finanzas_usd'],2,',','.')?></td>
<td>USD <?=number_format((float)$c['costo_mantenimiento_usd'],2,',','.')?></td>
<td><?=number_format($m,1,',','.')?>%</td>
<td><span class="pill"><?=$c['estado_rentabilidad']?></span></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 4. PRESENTACIONES DE NASER Y PETRONEU + KPI -->
<section id="tab-presentaciones" class="sales-tab">
<section class="sales-grid">
<?php if($canEdit):?>
<div class="sales-card">
<h2>Nueva cotización / licitación</h2>
<form method="post" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="guardar_cotizacion">
<label>Cliente
<select name="cliente_id" required>
<option value="">Seleccionar...</option>
<?php foreach($clientes as $c):?>
<option value="<?=(int)$c['id']?>"><?=h($c['razon_social'])?></option>
<?php endforeach;?>
</select>
</label>
<label>Código<input name="codigo_cotizacion" required></label>
<label>Monto USD<input type="number" step="0.01" name="monto_usd"></label>
<label>Estado<select name="estado_kpi"><option>En Estudio</option><option>Ganada</option><option>No Adjudicada</option></select></label>
<label>Presentación<input type="date" name="fecha_presentacion" value="<?=date('Y-m-d')?>"></label>
<label>Resolución<input type="date" name="fecha_resolucion"></label>
<div><button class="btn primary">Guardar cotización</button></div>
</form>
</div>

<div class="sales-card">
<h2>Subir presentación (Naser / Petroneu)</h2>
<form method="post" enctype="multipart/form-data" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="subir_presentacion">
<label class="full">Título<input name="titulo" required></label>
<label>Empresa
<select name="empresa">
<option value="Naser">Naser</option>
<option value="Petroneu">Petroneu</option>
</select>
</label>
<label>Categoría<input name="categoria" value="General"></label>
<label class="full">Archivo<input type="file" name="archivo" required accept=".pdf,.ppt,.pptx,.doc,.docx"></label>
<div><button class="btn primary">Subir</button></div>
</form>
</div>
<?php endif;?>
</section>

<div class="sales-card">
<h2>Cotizaciones y licitaciones</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Estado</th><th>Presentación</th><th>Resolución</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
<tbody>
<?php foreach($cotizaciones as $co):?>
<tr>
<td><?=h($co['codigo_cotizacion'])?></td>
<td><?=h($co['razon_social'])?></td>
<td>USD <?=number_format((float)$co['monto_usd'],2,',','.')?></td>
<td><span class="pill <?=$co['estado_kpi']==='Ganada'?'':($co['estado_kpi']==='No Adjudicada'?'danger':'warn')?>"><?=h($co['estado_kpi'])?></span></td>
<td><?=h($co['fecha_presentacion']??'')?></td>
<td><?=h($co['fecha_resolucion']??'')?></td>
<?php if($canEdit):?>
<td>
<form method="post" onsubmit="return confirm('¿Eliminar cotización?')"><?=csrf_field()?>
<input type="hidden" name="accion" value="eliminar_cotizacion">
<input type="hidden" name="id" value="<?=(int)$co['id']?>">
<button class="btn secondary">Eliminar</button>
</form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>

<div class="sales-card">
<h2>Presentaciones corporativas (Dossiers Naser & Petroneu)</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Título</th><th>Empresa</th><th>Categoría</th><th>Fecha</th><th>Archivo</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
<tbody>
<?php foreach($presentaciones as $p): 
    $url=app_url('/uploads/'.implode('/',array_map('rawurlencode',explode('/',$p['archivo_path']))));
?>
<tr>
<td><?=h($p['titulo'])?></td>
<td><span class="pill <?=$p['empresa']==='Petroneu'?'info':''?>"><?=h($p['empresa'])?></span></td>
<td><?=h($p['categoria'])?></td>
<td><?=h($p['fecha_carga'])?></td>
<td><a class="btn secondary" target="_blank" href="<?=h($url)?>">Abrir</a></td>
<?php if($canEdit):?>
<td>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="accion" value="eliminar_presentacion">
<input type="hidden" name="id" value="<?=(int)$p['id']?>">
<button class="btn secondary">Eliminar</button>
</form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 5. PLANTILLA DE SEGUIMIENTO DE CLIENTES INTERACTIVA (CRM) -->
<section id="tab-crm" class="sales-tab">
<?php if($canEdit):?>
<div class="sales-card">
<h2>Nuevo seguimiento comercial</h2>
<form method="post" class="sales-form"><?=csrf_field()?>
<input type="hidden" name="accion" value="guardar_crm">
<label>Cliente
<select name="cliente_id" required>
<option value="">Seleccionar...</option>
<?php foreach($clientes as $c):?>
<option value="<?=(int)$c['id']?>"><?=h($c['razon_social'])?></option>
<?php endforeach;?>
</select>
</label>
<label>Etapa
<select name="etapa_pipeline">
<option>Prospecto</option>
<option>Cotizado</option>
<option>En Negociación</option>
<option>Cierre Ganado</option>
<option>Perdido</option>
</select>
</label>
<label class="full">Oportunidad / servicio<input name="oportunidad_servicio" required></label>
<label>Último contacto<input type="date" name="fecha_ultimo_contacto" value="<?=date('Y-m-d')?>"></label>
<label>Responsable<input name="responsable_naser" value="<?=h($_SESSION['nombre']??'')?>"></label>
<label class="full">Próxima acción<textarea name="proxima_accion"></textarea></label>
<div><button class="btn primary">Guardar seguimiento</button></div>
</form>
</div>
<?php endif;?>

<div class="sales-card">
<h2>Plantilla interactiva de seguimiento de clientes</h2>
<div style="margin-bottom: 12px;">
    <input type="text" id="filtroCrm" onkeyup="filtrarTablaCrm()" placeholder="Buscar por cliente u oportunidad..." style="padding: 8px 12px; border: 1px solid var(--vl); border-radius: 8px; width: 300px;">
</div>
<div class="sales-table-wrap">
<table class="sales-table" id="tablaCrm">
<thead><tr><th>Cliente</th><th>Oportunidad</th><th>Etapa</th><th>Último contacto</th><th>Próxima acción</th><th>Responsable</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
<tbody>
<?php foreach($crm as $r):?>
<tr>
<td><strong><?=h($r['razon_social'])?></strong></td>
<td><?=h($r['oportunidad_servicio'])?></td>
<td><span class="pill <?=in_array($r['etapa_pipeline'],['Cierre Ganado'])?'':'warn'?>"><?=h($r['etapa_pipeline'])?></span></td>
<td><?=h($r['fecha_ultimo_contacto'])?></td>
<td><?=h($r['proxima_accion'])?></td>
<td><?=h($r['responsable_naser'])?></td>
<?php if($canEdit):?>
<td>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="accion" value="eliminar_crm">
<input type="hidden" name="id" value="<?=(int)$r['id']?>">
<button class="btn secondary">Eliminar</button>
</form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 6. CALENDARIO INTERACTIVO & ALERTAS COMPLETO -->
<section id="tab-calendario" class="sales-tab">
<div class="sales-card">
    <?php
    $mesesNombres = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
    $prevMes = $calMes - 1; $prevAnio = $calAnio;
    if ($prevMes < 1) { $prevMes = 12; $prevAnio--; }
    $nextMes = $calMes + 1; $nextAnio = $calAnio;
    if ($nextMes > 12) { $nextMes = 1; $nextAnio++; }
    ?>
    <div class="calendar-header">
        <a class="btn secondary" href="?tab=calendario&mes=<?=$prevMes?>&anio=<?=$prevAnio?>">◀ Mes Anterior</a>
        <h3>🗓️ <?=$mesesNombres[$calMes]?> <?=$calAnio?></h3>
        <a class="btn secondary" href="?tab=calendario&mes=<?=$nextMes?>&anio=<?=$nextAnio?>">Mes Siguiente ▶</a>
    </div>

    <div class="calendar-grid">
        <div class="calendar-day-head">Dom</div>
        <div class="calendar-day-head">Lun</div>
        <div class="calendar-day-head">Mar</div>
        <div class="calendar-day-head">Mié</div>
        <div class="calendar-day-head">Jue</div>
        <div class="calendar-day-head">Vie</div>
        <div class="calendar-day-head">Sáb</div>

        <?php
        // CÁLCULO PRECISO Y CORREGIDO DEL DÍA DE LA SEMANA
        $firstDayStr = sprintf('%04d-%02d-01', $calAnio, $calMes);
        $daysInMonth = (int)date('t', strtotime($firstDayStr));
        $dayOfWeek   = (int)date('w', strtotime($firstDayStr)); // 0 (Domingo) a 6 (Sábado)
        $hoyStr      = date('Y-m-d');

        // Espacios vacíos necesarios en el inicio del mes
        for ($i = 0; $i < $dayOfWeek; $i++) {
            echo '<div class="calendar-cell empty"></div>';
        }

        // Días numéricos del mes
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = sprintf('%04d-%02d-%02d', $calAnio, $calMes, $d);
            $isToday     = ($currentDate === $hoyStr);
            
            // Eventos agendados para este día
            $eventosDia = array_filter($alertas, fn($a) => substr($a['created_at'], 0, 10) === $currentDate);

            echo '<div class="calendar-cell '.($isToday ? 'today' : '').'" onclick="abrirModalCalendario(\''.$currentDate.'\')">';
            echo '<div><span class="calendar-date-num">'.$d.'</span>'.($isToday ? ' <small style="color:var(--vg);font-weight:bold">(Hoy)</small>' : '').'</div>';
            
            echo '<div class="calendar-events-container">';
            foreach ($eventosDia as $ev) {
                $claseTipo = h($ev['tipo_alerta']);
                $esLeida   = (int)$ev['leido'] === 1 ? 'leido' : '';
                echo '<span class="calendar-event-tag '.$claseTipo.' '.$esLeida.'" title="'.h($ev['mensaje']).'">📌 '.h($ev['titulo']).'</span>';
            }
            echo '</div>';
            
            echo '</div>';
        }

        // Espacios vacíos al final del mes
        $resto = ($dayOfWeek + $daysInMonth) % 7;
        if ($resto !== 0) {
            for ($i = 0; $i < (7 - $resto); $i++) {
                echo '<div class="calendar-cell empty"></div>';
            }
        }
        ?>
    </div>
</div>

<!-- Panel / Modal de Vista de Eventos del Día -->
<div class="sales-card" id="modalCalendario" style="display:none; border-color: var(--vg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="margin:0;">📅 Eventos y Tareas para el Día: <span id="fechaSeleccionadaTexto"></span></h3>
        <button type="button" class="btn secondary" onclick="cerrarCalendarioModal()">Cerrar ✖</button>
    </div>

    <!-- Contenedor con lista de eventos guardados para ese día -->
    <div id="listaEventosDia"></div>

    <?php if($canEdit):?>
    <hr style="border:0; border-top:1px solid var(--vl); margin:15px 0;">
    <h4 id="tituloFormModal" style="margin-top:0;">➕ Agregar Nuevo Evento / Alarma</h4>
    
    <form method="post" class="sales-form" id="formCalendario"><?=csrf_field()?>
        <input type="hidden" name="accion" value="guardar_evento_calendario">
        <input type="hidden" name="evento_id" id="inputEventoId" value="0">
        <input type="hidden" name="fecha_alarma" id="inputFechaAlarma">
        
        <label>Título / Referencia<input name="titulo" id="inputTitulo" required placeholder="Ej. Reunión Cliente / Vencimiento Cotización"></label>
        <label>Tipo
            <select name="tipo_alerta" id="selectTipo">
                <option value="Aviso">Aviso</option>
                <option value="Alerta">Alerta Urgencia</option>
                <option value="Vencimiento">Vencimiento Oferta</option>
                <option value="Finanzas">Pago / Finanzas</option>
            </select>
        </label>
        <label>Hora<input type="time" name="hora_alarma" id="inputHora" value="09:00"></label>
        <label>Estado
            <select name="leido" id="selectEstado">
                <option value="0">Pendiente</option>
                <option value="1">Completado / Leído</option>
            </select>
        </label>
        <label class="full">Detalles u Observaciones<textarea name="mensaje" id="inputMensaje" placeholder="Escribí aquí los detalles del evento..."></textarea></label>
        
        <div class="full" style="display:flex; gap:8px;">
            <button class="btn primary" id="btnSubmitModal">Guardar en Calendario</button>
            <button type="button" class="btn secondary" onclick="resetFormCalendario()">Limpiar Formulario</button>
        </div>
    </form>
    <?php endif;?>
</div>
</section>

<!-- 7. ALERTAS -->
<section id="tab-alertas" class="sales-tab">
<div class="sales-card">
<h2>Alertas y Avisos Registrados</h2>
<div class="sales-table-wrap">
<table class="sales-table">
<thead><tr><th>Fecha / Hora</th><th>Tipo</th><th>Título</th><th>Mensaje</th><th>Estado</th><?php if($canEdit):?><th>Acciones</th><?php endif;?></tr></thead>
<tbody>
<?php foreach($alertas as $a):?>
<tr>
<td><?=h($a['created_at'])?></td>
<td><span class="pill <?=h($a['tipo_alerta'])=='Alerta'?'danger':(h($a['tipo_alerta'])=='Vencimiento'?'warn':'')?>"><?=h($a['tipo_alerta'])?></span></td>
<td><?=h($a['titulo'])?></td>
<td><?=h($a['mensaje'])?></td>
<td><?=$a['leido']?'<span class="pill">Completada / Leída</span>':'<span class="pill warn">Pendiente</span>'?></td>
<?php if($canEdit):?>
<td class="sales-actions">
<?php if(!$a['leido']):?>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="accion" value="marcar_leida">
<input type="hidden" name="id" value="<?=(int)$a['id']?>">
<button class="btn secondary">Marcar completada</button>
</form>
<?php endif;?>
<form method="post" onsubmit="return confirm('¿Eliminar evento?')"><?=csrf_field()?>
<input type="hidden" name="accion" value="eliminar_evento_calendario">
<input type="hidden" name="id" value="<?=(int)$a['id']?>">
<button class="btn secondary">Eliminar</button>
</form>
</td>
<?php endif;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</section>

<!-- 9. FORMULARIOS DIGITALES -->
<section id="tab-formularios" class="sales-tab">
<?php formulariosPanel($pdo, 'ventas'); ?>
</section>

</main>
</div>

<script>
const listaAlertasJS = <?=json_encode($alertas, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const puedeEditar = <?=json_encode($canEdit)?>;

function escHtml(t){
    return String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function tabVentas(id){
    const el=document.getElementById('tab-'+id);
    if(!el) return;
    document.querySelectorAll('.sales-tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.sales-tabs button').forEach(x=>x.classList.toggle('active',x.dataset.tab===id));
    el.classList.add('active');
    try{
        const u=new URL(window.location.href);
        u.searchParams.set('tab',id);
        window.history.replaceState(null,'',u.toString());
    }catch(e){}
}

(function(){
    const t=new URLSearchParams(window.location.search).get('tab');
    if(t) tabVentas(t);
})();

function calcTicket(){
    const tarifa=parseFloat(document.getElementById('calcTarifa')?.value||0);
    const dias=parseFloat(document.getElementById('calcDias')?.value||0);
    const rec=parseFloat(document.getElementById('calcRecargo')?.value||0);
    const total=(tarifa*dias)*(1+(rec/100));
    const out=document.getElementById('calcResultado');
    if(out) out.textContent='USD '+total.toLocaleString('es-AR',{maximumFractionDigits:2});
}

['calcTarifa','calcDias','calcRecargo'].forEach(id=>{
    const e=document.getElementById(id);
    if(e){e.addEventListener('input',calcTicket);e.addEventListener('change',calcTicket);}
});
calcTicket();

function filtrarTablaCrm() {
    const input = document.getElementById('filtroCrm');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('tablaCrm');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let tdCliente = tr[i].getElementsByTagName('td')[0];
        let tdOportunidad = tr[i].getElementsByTagName('td')[1];
        if (tdCliente || tdOportunidad) {
            let txtValue = (tdCliente.textContent || tdCliente.innerText) + ' ' + (tdOportunidad.textContent || tdOportunidad.innerText);
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

/* FUNCIONES DE CALENDARIO */
function abrirModalCalendario(fecha) {
    const modal = document.getElementById('modalCalendario');
    if (!modal) return;
    
    document.getElementById('fechaSeleccionadaTexto').textContent = fecha;
    if (document.getElementById('inputFechaAlarma')) {
        document.getElementById('inputFechaAlarma').value = fecha;
    }
    
    const csrfEl = document.querySelector('#formCalendario input[type=hidden]');
    const csrfHidden = csrfEl ? `<input type="hidden" name="${escHtml(csrfEl.name)}" value="${escHtml(csrfEl.value)}">` : '';

    const eventos = listaAlertasJS.filter(a => a.created_at && a.created_at.substring(0, 10) === fecha);
    const contenedor = document.getElementById('listaEventosDia');
    
    if (eventos.length === 0) {
        contenedor.innerHTML = '<p style="color:var(--vm); font-style:italic;">No hay eventos ni alarmas agendados para este día.</p>';
    } else {
        let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
        eventos.forEach(ev => {
            const hora = ev.created_at.substring(11, 16);
            const estaLeido = parseInt(ev.leido) === 1;
            html += `
                <div class="day-event-card">
                    <div>
                        <strong style="${estaLeido ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escHtml(ev.titulo)}</strong>
                        <span class="pill ${escHtml(ev.tipo_alerta)}">${escHtml(ev.tipo_alerta)}</span>
                        <small>🕒 ${hora}</small>
                        <p style="margin:4px 0 0 0; font-size:12px; color:#4b5563;">${escHtml(ev.mensaje) || 'Sin detalles'}</p>
                    </div>
                    <div style="display:flex; gap:6px;">
                        ${puedeEditar ? `
                            <button class="btn secondary" onclick="cargarEventoParaEditar(${ev.id})">✏️ Editar</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este evento?')">
                                ${csrfHidden}
                                <input type="hidden" name="accion" value="eliminar_evento_calendario">
                                <input type="hidden" name="id" value="${ev.id}">
                                <button class="btn secondary">🗑️</button>
                            </form>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        contenedor.innerHTML = html;
    }

    resetFormCalendario();
    modal.style.display = 'block';
    modal.scrollIntoView({ behavior: 'smooth' });
}

function cargarEventoParaEditar(id) {
    const ev = listaAlertasJS.find(a => parseInt(a.id) === parseInt(id));
    if (!ev) return;

    document.getElementById('tituloFormModal').textContent = '✏️ Modificar Evento #' + ev.id;
    document.getElementById('inputEventoId').value = ev.id;
    document.getElementById('inputTitulo').value = ev.titulo;
    document.getElementById('selectTipo').value = ev.tipo_alerta;
    document.getElementById('inputHora').value = ev.created_at.substring(11, 16);
    document.getElementById('selectEstado').value = ev.leido;
    document.getElementById('inputMensaje').value = ev.mensaje;
    document.getElementById('btnSubmitModal').textContent = 'Actualizar Evento';
}

function resetFormCalendario() {
    if (!document.getElementById('formCalendario')) return;
    document.getElementById('tituloFormModal').textContent = '➕ Agregar Nuevo Evento / Alarma';
    document.getElementById('inputEventoId').value = '0';
    document.getElementById('inputTitulo').value = '';
    document.getElementById('selectTipo').value = 'Aviso';
    document.getElementById('inputHora').value = '09:00';
    document.getElementById('selectEstado').value = '0';
    document.getElementById('inputMensaje').value = '';
    document.getElementById('btnSubmitModal').textContent = 'Guardar en Calendario';
}

function cerrarCalendarioModal() {
    const modal = document.getElementById('modalCalendario');
    if (modal) modal.style.display = 'none';
}
</script>
</body>
</html>