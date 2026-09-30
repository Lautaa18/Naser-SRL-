<?php
// ==========================================
// PERSONAL: legajos, habilitaciones con vencimiento e importacion desde Excel
// ==========================================

/** Tipos de habilitacion que se controlan (licencias, cursos, examenes). */
function tiposHabilitacion(): array {
    return [
        'licencia_conducir' => 'Licencia de conducir',
        'manejo_defensivo'  => 'Manejo defensivo',
        'cnrt_cargas'       => 'CNRT cargas generales',
        'cnrt_psicofisico'  => 'CNRT psicofísico',
        'cnrt_mercancias'   => 'CNRT mercancías peligrosas',
        'well_control'      => 'Well Control',
        'eslingador'        => 'Eslingador y señalero',
        'hidrogrua'         => 'Operador de hidrogrúa',
        'torque'            => 'Torque',
        'supervisor_izaje'  => 'Supervisor de izaje',
        'trabajo_altura'    => 'Trabajo en altura',
        'rescate_altura'    => 'Rescate en altura',
        'examen_medico'     => 'Examen médico',
    ];
}

/** Quién puede ver el personal: super usuario o cualquier integrante de RRHH. */
function puedeVerPersonal(PDO $pdo): bool {
    if (esAdmin()) return true;
    $rrhh = sectorIdPorSlug($pdo, 'rrhh');
    return $rrhh && rolEnSector($pdo, $rrhh) !== null;
}
/** Responsables operativos: ven las habilitaciones (sin datos personales). */
function puedeVerHabilitaciones(PDO $pdo): bool {
    if (puedeVerPersonal($pdo)) return true;
    foreach (['operaciones', 'hseq', 'mantenimiento', 'gerencia'] as $slug) {
        $sid = sectorIdPorSlug($pdo, $slug);
        if ($sid && rolEnSector($pdo, $sid) === 'responsable') return true;
    }
    return false;
}
function puedeEditarPersonal(PDO $pdo): bool {
    $rrhh = sectorIdPorSlug($pdo, 'rrhh');
    return esAdmin() || ($rrhh && puedeEditarSector($pdo, $rrhh));
}
function sectorIdPorSlug(PDO $pdo, string $slug): ?int {
    foreach (sectoresInfo($pdo) as $s) if ($s['slug'] === $slug) return (int)$s['id'];
    return null;
}
/** Sector segun el "servicio" de la planilla. */
function sectorDeServicio(PDO $pdo, ?string $servicio): ?int {
    $s = mb_strtoupper(trim((string)$servicio));
    if ($s === '') return null;
    if (preg_match('/OPERADOR|SUPERVISOR|SLICK/', $s)) return sectorIdPorSlug($pdo, 'operaciones');
    if (str_contains($s, 'MANTEN')) return sectorIdPorSlug($pdo, 'mantenimiento');
    if (str_contains($s, 'HSE') || str_contains($s, 'SEGURIDAD')) return sectorIdPorSlug($pdo, 'hseq');
    return null;
}
/** Estado de un vencimiento: vencido / pronto (30 dias) / vigente / sin fecha. */
function estadoVencimiento(?string $fecha): array {
    if (!$fecha) return ['sin', 'Sin fecha'];
    $dias = (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($fecha))->format('%r%a');
    if ($dias < 0) return ['vencido', 'Vencido'];
    if ($dias <= 30) return ['pronto', $dias === 0 ? 'Vence hoy' : "Vence en $dias d"];
    return ['ok', 'Vigente'];
}
function edadDesde(?string $fecha): ?int {
    return $fecha ? (int)(new DateTimeImmutable($fecha))->diff(new DateTimeImmutable('today'))->y : null;
}

/* ==========================================================
   LECTOR SIMPLE DE .XLSX (sin librerias: un xlsx es un zip con XML)
   Devuelve [nombreHoja => [[celdas...], ...]] con indices 0..n
   ========================================================== */
function leerXlsx(string $archivo, array $soloHojas = []): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Falta la extensión ZipArchive de PHP.');
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== true) throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
    $xml = function (string $ruta) use ($zip) {
        $c = $zip->getFromName($ruta);
        return $c === false ? null : simplexml_load_string($c, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    };
    $shared = [];
    if ($ss = $xml('xl/sharedStrings.xml')) {
        foreach ($ss->si as $si) {
            if (isset($si->t)) $shared[] = (string)$si->t;
            else { $txt = ''; foreach ($si->r as $r) $txt .= (string)$r->t; $shared[] = $txt; }
        }
    }
    $rels = [];
    if ($r = $xml('xl/_rels/workbook.xml.rels')) foreach ($r->Relationship as $rel) $rels[(string)$rel['Id']] = (string)$rel['Target'];
    $wb = $xml('xl/workbook.xml');
    if (!$wb) throw new RuntimeException('No se pudo leer el libro de Excel.');
    $hojas = [];
    foreach ($wb->sheets->sheet as $sh) {
        $nombre = (string)$sh['name'];
        if ($soloHojas && !in_array(mb_strtoupper($nombre), array_map('mb_strtoupper', $soloHojas), true)) continue;
        $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $target = $rels[$rid] ?? null;
        if (!$target) continue;
        $ruta = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        $s = $xml($ruta);
        if (!$s) continue;
        $filas = [];
        foreach ($s->sheetData->row as $row) {
            $n = (int)$row['r'] - 1;
            $celdas = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
                $col = 0;
                foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
                $t = (string)$c['t'];
                if ($t === 's') $v = $shared[(int)$c->v] ?? '';
                elseif ($t === 'inlineStr') $v = (string)$c->is->t;
                else $v = isset($c->v) ? (string)$c->v : '';
                $celdas[$col - 1] = $v;
            }
            $filas[$n] = $celdas;
        }
        $hojas[$nombre] = $filas;
    }
    $zip->close();
    return $hojas;
}
/** Convierte un valor de Excel (numero de serie o texto dd/mm/aaaa) a Y-m-d. */
function fechaExcel($v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    if (is_numeric($v) && (float)$v > 1000 && (float)$v < 80000) {
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)floor((float)$v) . ' days')->format('Y-m-d');
    }
    if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) {
        $a = (int)$m[3]; if ($a < 100) $a += 2000;
        return checkdate((int)$m[2], (int)$m[1], $a) ? sprintf('%04d-%02d-%02d', $a, $m[2], $m[1]) : null;
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}#', $v)) return substr($v, 0, 10);
    return null;
}
function normalizarTexto(string $s): string {
    $s = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
    return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
}

/**
 * Importa el "Listado de Empleados" (hoja PERSONAL + hoja MAILS).
 * Actualiza por legajo. Devuelve un resumen.
 */
function importarEmpleadosXlsx(PDO $pdo, string $archivo, bool $marcarBajas): array {
    $hojas = leerXlsx($archivo, ['PERSONAL', 'MAILS']);
    $personal = null;
    foreach ($hojas as $n => $h) if (mb_strtoupper($n) === 'PERSONAL') $personal = $h;
    if (!$personal) throw new RuntimeException('No se encontró la hoja "PERSONAL" en el Excel.');

    // 1) Buscar la fila de encabezados (la que tiene LEGAJO y APELLIDO Y NOMBRE)
    $hdrRow = null;
    foreach ($personal as $n => $fila) {
        $txt = array_map(fn($x) => normalizarTexto((string)$x), $fila);
        if (in_array('LEGAJO', $txt, true) && in_array('APELLIDO Y NOMBRE', $txt, true)) { $hdrRow = $n; break; }
    }
    if ($hdrRow === null) throw new RuntimeException('No se encontraron los encabezados (LEGAJO / APELLIDO Y NOMBRE) en la hoja PERSONAL.');
    $h1 = array_map(fn($x) => normalizarTexto((string)$x), $personal[$hdrRow]);
    $h2 = array_map(fn($x) => normalizarTexto((string)$x), $personal[$hdrRow + 1] ?? []);
    $col = function (string ...$nombres) use ($h1) { foreach ($nombres as $n) { $i = array_search(normalizarTexto($n), $h1, true); if ($i !== false) return $i; } return null; };
    $C = [
        'legajo' => $col('LEGAJO'), 'nombre' => $col('APELLIDO Y NOMBRE'), 'dni' => $col('DNI'), 'cuil' => $col('CUIL'),
        'servicio' => $col('SERVICIO'), 'ingreso' => $col('FEC ING'), 'ooss' => $col('OOSS'), 'convenio' => $col('CONVENIO'),
        'encuadre' => $col('ENCUADRE'), 'categoria' => $col('CATEGORIA'), 'nueva_categoria' => $col('NUEVA CATEGORIA'),
        'cargo' => $col('CARGO/FUNCION'), 'domicilio' => $col('DOMICILIO'), 'cp' => $col('CP'), 'localidad' => $col('LOCALIDAD'),
        'provincia' => $col('PROVINCIA'), 'nacimiento' => $col('FEC NAC'), 'telefono' => $col('TELEFONOS'), 'estudios' => $col('ESTUDIOS'),
        'sindicato' => $col('SINDICATO'), 'mutual' => $col('MUTUAL'), 'deposito' => $col('DEPOSITO'),
    ];
    // Grupos de habilitaciones: encabezado de grupo en la fila 1 y subtitulos en la fila 2
    $grupo = function (string $nombre) use ($h1) { foreach ($h1 as $i => $v) if (str_starts_with($v, normalizarTexto($nombre))) return $i; return null; };
    $sub = function (string $nombre, int $desde = 0) use ($h2) { foreach ($h2 as $i => $v) if ($i >= $desde && str_starts_with($v, normalizarTexto($nombre))) return $i; return null; };
    $gLic = $grupo('LICENCIA'); $gDef = $grupo('CARNET MANEJO'); $gCnrt = $grupo('CNRT'); $gExa = $grupo('EXAMENES MEDICOS');
    $H = []; // tipo => [colDetalle|null, colRealizacion|null, colVencimiento]
    if ($gLic !== null) $H['licencia_conducir'] = [$gLic + 1, null, $gLic + 2];
    if ($gDef !== null) $H['manejo_defensivo'] = [$gDef + 1, null, $gDef + 2];
    if ($gCnrt !== null) {
        foreach (['cnrt_cargas' => 'CARGAS GENERALES', 'cnrt_psicofisico' => 'PSICOFISICO', 'cnrt_mercancias' => 'MERCANCIAS'] as $t => $n)
            if (($i = $sub($n, $gCnrt)) !== null) $H[$t] = [null, null, $i];
    }
    foreach (['well_control' => 'WELL CONTROL', 'eslingador' => 'ESLINGADOR', 'hidrogrua' => 'HIDROGRUA', 'torque' => 'TORQUE', 'supervisor_izaje' => 'SUPERVISOR IZAJE', 'trabajo_altura' => 'TRABAJO EN ALTURA', 'rescate_altura' => 'RESCATE EN ALTURA'] as $t => $n)
        if (($i = $sub($n)) !== null) $H[$t] = [null, null, $i];
    if ($gExa !== null) $H['examen_medico'] = [$gExa, $gExa + 1, $gExa + 2];

    // 2) Mails (hoja MAILS): por legajo, el primero @gruponaser es el corporativo
    $mails = [];
    foreach ($hojas as $n => $h) {
        if (mb_strtoupper($n) !== 'MAILS') continue;
        foreach ($h as $fila) {
            $leg = trim((string)($fila[1] ?? ''));
            if ($leg === '' || !ctype_digit($leg)) continue;
            foreach ($fila as $v) {
                $v = strtolower(trim((string)$v));
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) continue;
                if (str_ends_with($v, '@gruponaser.com.ar') && empty($mails[$leg]['corp'])) $mails[$leg]['corp'] = $v;
                elseif (empty($mails[$leg]['pers']) && !str_ends_with($v, '@gruponaser.com.ar')) $mails[$leg]['pers'] = $v;
            }
        }
    }

    // 3) Guardar
    $g = fn(array $f, ?int $i) => $i === null ? null : (trim((string)($f[$i] ?? '')) === '' ? null : trim((string)$f[$i]));
    $res = ['nuevos' => 0, 'actualizados' => 0, 'habilitaciones' => 0, 'bajas' => 0, 'omitidos' => 0];
    $legajosArchivo = [];
    $pdo->beginTransaction();
    try {
        $sel = $pdo->prepare('SELECT id FROM empleados WHERE legajo = ?');
        $ins = $pdo->prepare('INSERT INTO empleados (legajo, apellido_nombre) VALUES (?, ?)');
        $upd = $pdo->prepare('UPDATE empleados SET apellido_nombre=?, dni=?, cuil=?, servicio=?, sector_id=COALESCE(?, sector_id), cargo=?, fecha_ingreso=?, fecha_nacimiento=?, obra_social=?, convenio=?, encuadre=?, categoria=?, nueva_categoria=?, domicilio=?, cp=?, localidad=?, provincia=?, telefono=?, estudios=?, sindicato=?, mutual=?, deposito=?, email_corporativo=COALESCE(?, email_corporativo), email_personal=COALESCE(?, email_personal), activo=1, fecha_baja=NULL WHERE id=?');
        $hab = $pdo->prepare('INSERT INTO empleados_habilitaciones (empleado_id, tipo, detalle, fecha_realizacion, fecha_vencimiento) VALUES (?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE detalle=VALUES(detalle), fecha_realizacion=VALUES(fecha_realizacion), fecha_vencimiento=VALUES(fecha_vencimiento)');
        foreach ($personal as $n => $f) {
            if ($n <= $hdrRow + 1) continue;
            $leg = $g($f, $C['legajo']); $nom = $g($f, $C['nombre']);
            if (!$leg || !$nom || !preg_match('/^\d+$/', $leg)) { if ($leg && !$nom) $res['omitidos']++; continue; }
            $legajosArchivo[] = $leg;
            $sel->execute([$leg]);
            $id = (int)$sel->fetchColumn();
            if (!$id) { $ins->execute([$leg, $nom]); $id = (int)$pdo->lastInsertId(); $res['nuevos']++; } else $res['actualizados']++;
            $servicio = $g($f, $C['servicio']);
            $upd->execute([
                mb_convert_case(mb_strtolower($nom), MB_CASE_TITLE), $g($f, $C['dni']), $g($f, $C['cuil']), $servicio, sectorDeServicio($pdo, $servicio),
                $g($f, $C['cargo']), fechaExcel($g($f, $C['ingreso'])), fechaExcel($g($f, $C['nacimiento'])), $g($f, $C['ooss']), $g($f, $C['convenio']),
                $g($f, $C['encuadre']), $g($f, $C['categoria']), $g($f, $C['nueva_categoria']), $g($f, $C['domicilio']), $g($f, $C['cp']),
                $g($f, $C['localidad']), $g($f, $C['provincia']), $g($f, $C['telefono']), $g($f, $C['estudios']), $g($f, $C['sindicato']),
                $g($f, $C['mutual']), $g($f, $C['deposito']), $mails[$leg]['corp'] ?? null, $mails[$leg]['pers'] ?? null, $id,
            ]);
            foreach ($H as $tipo => [$cDet, $cReal, $cVenc]) {
                $venc = fechaExcel($g($f, $cVenc));
                $real = $cReal !== null ? fechaExcel($g($f, $cReal)) : null;
                $det = $cDet !== null ? $g($f, $cDet) : null;
                if ($det !== null && fechaExcel($det)) $det = null;
                if (!$venc && !$real) continue;
                $hab->execute([$id, $tipo, $det ? mb_substr($det, 0, 120) : null, $real, $venc]);
                $res['habilitaciones']++;
            }
        }
        if ($marcarBajas && $legajosArchivo) {
            $ph = implode(',', array_fill(0, count($legajosArchivo), '?'));
            $st = $pdo->prepare("UPDATE empleados SET activo = 0, fecha_baja = CURDATE() WHERE activo = 1 AND legajo NOT IN ($ph)");
            $st->execute($legajosArchivo);
            $res['bajas'] = $st->rowCount();
        }
        // Vincular con usuarios del sistema por mail corporativo
        $pdo->exec('UPDATE empleados e JOIN usuarios u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(e.email_corporativo) COLLATE utf8mb4_unicode_ci SET e.usuario_id = u.id');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $res;
}
