<?php
/** Los datos personales tienen una única fuente: empleados y sus habilitaciones. */
function finanzasFechaValida(string $fecha): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}

function empleadoParaFinanzas(PDO $pdo, int $id, bool $bloquear = false): ?array {
    $st = $pdo->prepare("SELECT id, legajo, apellido_nombre, dni, email_corporativo, email_personal, telefono, activo
        FROM empleados WHERE id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$id]); $e = $st->fetch();
    if (!$e) return null;
    $st = $pdo->prepare("SELECT tipo, detalle, fecha_vencimiento FROM empleados_habilitaciones
        WHERE empleado_id = ? AND tipo IN ('licencia_conducir','manejo_defensivo')" . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$id]); $h = [];
    foreach ($st->fetchAll() as $r) $h[$r['tipo']] = $r;
    $e['categoria_carnet'] = $h['licencia_conducir']['detalle'] ?? null;
    $e['vencimiento_carnet'] = $h['licencia_conducir']['fecha_vencimiento'] ?? null;
    $e['curso_defensivo'] = $h['manejo_defensivo']['detalle'] ?? null;
    $e['vencimiento_defensivo'] = $h['manejo_defensivo']['fecha_vencimiento'] ?? null;
    return $e;
}

function versionEmpleadoFinanzas(array $e): string {
    $campos = ['id','legajo','apellido_nombre','dni','email_corporativo','email_personal','telefono','activo',
        'categoria_carnet','vencimiento_carnet','curso_defensivo','vencimiento_defensivo'];
    return hash('sha256', json_encode(array_map(fn($c) => (string)($e[$c] ?? ''), $campos), JSON_UNESCAPED_UNICODE));
}

/** Selector mínimo: no expone domicilio, CUIL, datos médicos ni contactos de toda la plantilla. */
function personasDisponiblesFinanzas(PDO $pdo): array {
    return $pdo->query("SELECT e.id,e.legajo,e.apellido_nombre,e.dni,e.email_corporativo,e.email_personal,e.telefono,e.activo,
        hc.detalle AS categoria_carnet,hc.fecha_vencimiento AS vencimiento_carnet,
        hd.detalle AS curso_defensivo,hd.fecha_vencimiento AS vencimiento_defensivo
        FROM empleados e
        LEFT JOIN empleados_habilitaciones hc ON hc.empleado_id=e.id AND hc.tipo='licencia_conducir'
        LEFT JOIN empleados_habilitaciones hd ON hd.empleado_id=e.id AND hd.tipo='manejo_defensivo'
        WHERE e.activo=1 AND NOT EXISTS(SELECT 1 FROM finanzas_trabajadores f WHERE f.empleado_id=e.id)
        ORDER BY e.apellido_nombre,e.legajo")->fetchAll();
}

function consultaPersonalFinanzas(): string {
    return "SELECT f.*,
        CASE WHEN f.empleado_id IS NULL THEN f.legajo ELSE e.legajo END AS legajo,
        CASE WHEN f.empleado_id IS NULL THEN f.nombre_completo ELSE e.apellido_nombre END AS nombre_completo,
        CASE WHEN f.empleado_id IS NULL THEN f.dni ELSE e.dni END AS dni,
        CASE WHEN f.empleado_id IS NULL THEN f.email ELSE COALESCE(NULLIF(e.email_corporativo,''),e.email_personal) END AS email,
        CASE WHEN f.empleado_id IS NULL THEN f.telefono ELSE e.telefono END AS telefono,
        CASE WHEN f.empleado_id IS NULL THEN f.categoria_carnet ELSE hc.detalle END AS categoria_carnet,
        CASE WHEN f.empleado_id IS NULL THEN f.vencimiento_carnet ELSE hc.fecha_vencimiento END AS vencimiento_carnet,
        CASE WHEN f.empleado_id IS NULL THEN f.curso_defensivo ELSE hd.detalle END AS curso_defensivo,
        CASE WHEN f.empleado_id IS NULL THEN f.vencimiento_defensivo ELSE hd.fecha_vencimiento END AS vencimiento_defensivo,
        e.activo AS empleado_activo, e.id AS maestro_existente, u.nombre AS actualizado_nombre
        FROM finanzas_trabajadores f
        LEFT JOIN empleados e ON e.id=f.empleado_id
        LEFT JOIN empleados_habilitaciones hc ON hc.empleado_id=e.id AND hc.tipo='licencia_conducir'
        LEFT JOIN empleados_habilitaciones hd ON hd.empleado_id=e.id AND hd.tipo='manejo_defensivo'
        LEFT JOIN usuarios u ON u.id=f.actualizado_por";
}

function trabajadorFinanzas(PDO $pdo, int $id, int $sid): ?array {
    $st = $pdo->prepare(consultaPersonalFinanzas() . ' WHERE f.id=? AND f.sector_id=?');
    $st->execute([$id,$sid]); return $st->fetch() ?: null;
}

function versionTrabajadorFinanzas(array $f): string {
    $campos = ['id','empleado_id','sector_id','legajo','nombre_completo','dni','email','telefono','categoria_carnet',
        'vencimiento_carnet','curso_defensivo','vencimiento_defensivo','empleado_activo'];
    return hash('sha256', json_encode(array_map(fn($c) => (string)($f[$c] ?? ''), $campos), JSON_UNESCAPED_UNICODE));
}

/** Finanzas solo modifica los datos de conducción; identidad y contacto se leen del maestro. */
function guardarTrabajadorFinanzas(PDO $pdo, int $sid, int $uid, array $in): array {
    if (!puedeEditarSector($pdo,$sid)) throw new RuntimeException('No tenés permiso para modificar este sector.');
    $sector = $pdo->prepare("SELECT nombre FROM sectores WHERE id=? AND slug='finanzas'"); $sector->execute([$sid]);
    $nombreSector = $sector->fetchColumn();
    if (!$nombreSector) throw new RuntimeException('El sector no corresponde a Finanzas.');
    $id = (int)($in['id'] ?? 0); $empleado = (int)($in['empleado_id'] ?? 0);
    $vc = (string)($in['venc_carnet'] ?? ''); $vd = (string)($in['venc_defensivo'] ?? '');
    $categoria = trim((string)($in['categoria'] ?? '')); $curso = trim((string)($in['curso'] ?? ''));
    if (!$empleado) throw new RuntimeException('Seleccioná una persona de la ficha central de Personal.');
    if (!finanzasFechaValida($vc) || !finanzasFechaValida($vd)) throw new RuntimeException('Ingresá fechas de vencimiento válidas.');
    if (mb_strlen($categoria)>50 || mb_strlen($curso)>100) throw new RuntimeException('La categoría o el curso superan el largo permitido.');
    $pdo->beginTransaction();
    try {
        // Igual orden de bloqueo que RRHH y conciliación: maestro antes del seguimiento.
        $e=empleadoParaFinanzas($pdo,$empleado,true);
        if (!$e || !(int)$e['activo']) throw new RuntimeException('Seleccioná una persona activa. Las altas se gestionan desde Personal.');
        if (trim((string)$e['legajo'])==='' || trim((string)$e['apellido_nombre'])==='') throw new RuntimeException('La ficha central está incompleta. Pedile a RRHH que revise el legajo y nombre.');
        if (!hash_equals(versionEmpleadoFinanzas($e),(string)($in['ficha_version']??''))) {
            throw new RuntimeException('La ficha central cambió mientras completabas el formulario. Recargá para revisar sus datos actuales.');
        }
        if ($id) {
            $st=$pdo->prepare('SELECT id,empleado_id FROM finanzas_trabajadores WHERE id=? AND sector_id=? FOR UPDATE'); $st->execute([$id,$sid]); $actual=$st->fetch();
            if (!$actual) throw new RuntimeException('El registro ya no existe o pertenece a otro sector.');
            if ($actual['empleado_id'] && (int)$actual['empleado_id']!==$empleado) throw new RuntimeException('Un seguimiento existente no se puede reasignar a otra persona.');
            $vista = trabajadorFinanzas($pdo,$id,$sid);
            if (!hash_equals(versionTrabajadorFinanzas($vista),(string)($in['registro_version']??''))) {
                throw new RuntimeException('Otra persona modificó esta ficha. Recargá la página antes de guardar.');
            }
        }
        $st=$pdo->prepare('SELECT id FROM finanzas_trabajadores WHERE empleado_id=? AND id<>? FOR UPDATE'); $st->execute([$empleado,$id]);
        if ($st->fetchColumn()) throw new RuntimeException('Esta persona ya tiene un seguimiento en Finanzas. Editá el registro existente.');
        if ($id) {
            // Conservar la fila histórica; los datos compartidos se muestran desde el maestro.
            $pdo->prepare('UPDATE finanzas_trabajadores SET empleado_id=?,categoria_carnet=?,vencimiento_carnet=?,curso_defensivo=?,vencimiento_defensivo=?,actualizado_por=?,actualizado_en=NOW() WHERE id=? AND sector_id=?')
                ->execute([$empleado,$categoria,$vc,$curso,$vd,$uid,$id,$sid]);
        } else {
            $email=$e['email_corporativo'] ?: $e['email_personal'];
            $pdo->prepare('INSERT INTO finanzas_trabajadores(empleado_id,sector_id,legajo,nombre_completo,dni,sector,email,telefono,categoria_carnet,vencimiento_carnet,curso_defensivo,vencimiento_defensivo,creado_por,actualizado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$empleado,$sid,$e['legajo'],$e['apellido_nombre'],$e['dni']??'',$nombreSector,$email,$e['telefono'],$categoria,$vc,$curso,$vd,$uid,$uid]);
            $id=(int)$pdo->lastInsertId();
        }
        $st=$pdo->prepare('INSERT INTO empleados_habilitaciones(empleado_id,tipo,detalle,fecha_vencimiento) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE detalle=VALUES(detalle),fecha_vencimiento=VALUES(fecha_vencimiento)');
        $st->execute([$empleado,'licencia_conducir',$categoria?:null,$vc]);
        $st->execute([$empleado,'manejo_defensivo',$curso?:null,$vd]);
        $pdo->commit(); return ['id'=>$id,'nombre'=>$e['apellido_nombre']];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function puedeConciliarPersonal(PDO $pdo): bool {
    if (esAdmin()) return true;
    $sid=(int)$pdo->query("SELECT id FROM sectores WHERE slug='rrhh'")->fetchColumn();
    return $sid && rolEnSector($pdo,$sid)==='responsable' && puedeEditarSector($pdo,$sid);
}

/** Cada adopción valida la instantánea para no reemplazar una actualización posterior de RRHH. */
function resolverConciliacionPersonal(PDO $pdo, int $id, array $decisiones): void {
    if (!puedeConciliarPersonal($pdo)) throw new RuntimeException('Solo el responsable de RRHH puede conciliar los datos de Personal.');
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT * FROM personal_conciliacion WHERE id=? AND resuelto_en IS NULL FOR UPDATE');$st->execute([$id]);$p=$st->fetch();
        if (!$p) throw new RuntimeException('La revisión ya fue resuelta o no existe.');
        $st=$pdo->prepare('SELECT * FROM empleados WHERE id=? FOR UPDATE');$st->execute([$p['empleado_id']]);$e=$st->fetch();
        $st=$pdo->prepare('SELECT * FROM finanzas_trabajadores WHERE id=? FOR UPDATE');$st->execute([$p['finanzas_id']]);$f=$st->fetch();
        if (!$e || !$f || (int)$f['empleado_id']!==(int)$e['id']) throw new RuntimeException('La vinculación cambió. Revisá el legajo antes de continuar.');
        $diff=json_decode($p['diferencias_json'],true,512,JSON_THROW_ON_ERROR);
        $map=['apellido_nombre'=>'nombre_completo','dni'=>'dni','email_corporativo'=>'email','telefono'=>'telefono'];
        $limites=['apellido_nombre'=>160,'dni'=>20,'email_corporativo'=>160,'telefono'=>60];
        foreach ($diff as $campo=>$valores) {
            $decision=(string)($decisiones[$campo]??'');
            if (!in_array($decision,['mantener','adoptar'],true)) throw new RuntimeException('Elegí una decisión para cada diferencia.');
            if ($decision==='mantener') continue;
            if (isset($map[$campo])) {
                $actual=$e[$campo];$legado=$f[$map[$campo]];
                if ((string)$actual!==(string)$valores['maestro'] || (string)$legado!==(string)$valores['finanzas']) throw new RuntimeException('Una ficha cambió después de generar esta revisión. Conservá el dato actual o revisalo en Personal.');
                $valor=trim((string)$legado);
                if (mb_strlen($valor)>$limites[$campo] || ($campo==='apellido_nombre' && $valor==='') || ($campo==='email_corporativo' && $valor!=='' && !filter_var($valor,FILTER_VALIDATE_EMAIL))) throw new RuntimeException('El valor de Finanzas no es válido para '.$campo.'.');
                $pdo->prepare("UPDATE empleados SET `$campo`=? WHERE id=?")->execute([$valor?:null,$e['id']]);
            } elseif (in_array($campo,['licencia_conducir','manejo_defensivo'],true)) {
                $st=$pdo->prepare('SELECT fecha_vencimiento FROM empleados_habilitaciones WHERE empleado_id=? AND tipo=? FOR UPDATE');$st->execute([$e['id'],$campo]);$actual=$st->fetchColumn();
                $legado=$f[$campo==='licencia_conducir'?'vencimiento_carnet':'vencimiento_defensivo'];
                if ((string)$actual!==(string)$valores['maestro'] || (string)$legado!==(string)$valores['finanzas']) throw new RuntimeException('El vencimiento cambió después de generar esta revisión. Conservá el dato actual o revisalo en Personal.');
                if ($legado!==null && !finanzasFechaValida((string)$legado)) throw new RuntimeException('El vencimiento histórico no es una fecha válida.');
                $pdo->prepare('INSERT INTO empleados_habilitaciones(empleado_id,tipo,fecha_vencimiento) VALUES(?,?,?) ON DUPLICATE KEY UPDATE fecha_vencimiento=VALUES(fecha_vencimiento)')->execute([$e['id'],$campo,$legado]);
            } else throw new RuntimeException('La revisión contiene un campo no permitido.');
        }
        $pdo->prepare('UPDATE personal_conciliacion SET resuelto_en=NOW(),resuelto_por=? WHERE id=?')->execute([$_SESSION['usuario_id']??null,$id]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
