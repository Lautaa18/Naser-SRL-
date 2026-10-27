<?php
// ==========================================================
// Reparte los documentos del SGI entre los sectores.
// Cada documento queda EN EL SGI (no se toca) y ademas aparece en el sector que le corresponde,
// dentro de una carpeta "Documentación SGI". No se copian archivos: la copia apunta al mismo archivo.
//
// Uso (desde la carpeta del proyecto, en el servidor):
//   docker compose exec web php php/tools/clasificar_documentos.php             -> SOLO MUESTRA el plan (no cambia nada)
//   docker compose exec web php php/tools/clasificar_documentos.php --aplicar   -> crea las copias en los sectores
//   docker compose exec web php php/tools/clasificar_documentos.php --deshacer  -> mueve esas copias a la papelera (el SGI queda igual)
//
// Las reglas estan en php/config/clasificacion_sgi.php. El plan se guarda en backups/clasificacion_sgi_FECHA.csv
// ==========================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/documentos_storage.php';
require __DIR__ . '/../config/clasificacion_sgi.php';

const CARPETA_COPIAS = 'Documentación SGI';

$opts = array_slice($argv, 1);
$aplicar  = in_array('--aplicar', $opts, true);
$deshacer = in_array('--deshacer', $opts, true);
if ($aplicar && $deshacer) { fwrite(STDERR, "Elegi --aplicar o --deshacer, no los dos.\n"); exit(1); }

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sectores = [];   // slug => [id, nombre]
foreach ($pdo->query('SELECT id, slug, nombre FROM sectores')->fetchAll(PDO::FETCH_ASSOC) as $s) $sectores[$s['slug']] = ['id' => (int)$s['id'], 'nombre' => $s['nombre']];
if (!isset($sectores['sgi'])) { fwrite(STDERR, "ERROR: no existe el sector 'sgi'.\n"); exit(1); }
$sgiId = $sectores['sgi']['id'];

// ---------- DESHACER: solamente copias SGI dentro de la carpeta creada por esta herramienta ----------
if ($deshacer) {
    $docsQuitados=0; $carpetasQuitadas=0;
    $pdo->beginTransaction();
    try {
        $pdo->exec('SET @naser_actor = NULL');
        $roots=$pdo->prepare('SELECT id,sector_id,nombre FROM carpetas WHERE sector_id<>? AND carpeta_padre_id IS NULL AND nombre=? AND activa=1 AND eliminado_lote IS NULL FOR UPDATE');
        $roots->execute([$sgiId,CARPETA_COPIAS]);
        foreach ($roots->fetchAll(PDO::FETCH_ASSOC) as $root) {
            $sid=(int)$root['sector_id']; $ids=[(int)$root['id']]; $queue=$ids; $seen=[]; $ajeno=false;
            while ($queue) {
                $id=array_shift($queue);
                if (isset($seen[$id])) throw new RuntimeException('Hay un ciclo en la estructura de carpetas.');
                $seen[$id]=true;
                $children=$pdo->prepare('SELECT id,sector_id FROM carpetas WHERE carpeta_padre_id=? FOR UPDATE');
                $children->execute([$id]);
                foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) {
                    if ((int)$child['sector_id']!==$sid) {$ajeno=true;continue;}
                    $ids[]=(int)$child['id'];$queue[]=(int)$child['id'];
                }
            }
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $st=$pdo->prepare("SELECT d.* FROM documentos d WHERE d.carpeta_id IN ($ph) FOR UPDATE");
            $st->execute($ids); $filas=$st->fetchAll(PDO::FETCH_ASSOC); $copias=[];
            $esCopia=$pdo->prepare('SELECT 1 FROM documentos WHERE sector_id=? AND archivo=? AND titulo=? LIMIT 1');
            foreach ($filas as $d) {
                if ((int)$d['sector_id']!==$sid || !(int)$d['activo'] || $d['eliminado_lote']!==null) {$ajeno=true;continue;}
                $esCopia->execute([$sgiId,$d['archivo'],$d['titulo']]);
                if ($esCopia->fetchColumn()) $copias[]=$d; else $ajeno=true;
            }
            if (!$copias) continue;
            // Con contenido ajeno se conservan las carpetas y se retiran únicamente las copias identificadas.
            if ($ajeno) {
                foreach ($copias as $d) {
                    $lote=enviarPapelera($pdo,'documento',(int)$d['id'],$sid,(string)$d['titulo']);
                    $pdo->prepare('UPDATE documentos SET activo=0,eliminado_lote=? WHERE id=? AND sector_id=? AND activo=1 AND eliminado_lote IS NULL')->execute([$lote,(int)$d['id'],$sid]);
                    $docsQuitados++;
                }
                continue;
            }
            $lote=enviarPapelera($pdo,'carpeta',(int)$root['id'],$sid,(string)$root['nombre']);
            $st=$pdo->prepare("UPDATE documentos SET activo=0,eliminado_lote=? WHERE sector_id=? AND carpeta_id IN ($ph) AND activo=1 AND eliminado_lote IS NULL");
            $st->execute([$lote,$sid,...$ids]);$docsQuitados+=$st->rowCount();
            $st=$pdo->prepare("UPDATE carpetas SET activa=0,eliminado_lote=? WHERE sector_id=? AND id IN ($ph) AND activa=1 AND eliminado_lote IS NULL");
            $st->execute([$lote,$sid,...$ids]);$carpetasQuitadas+=$st->rowCount();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fwrite(STDERR,'ERROR, no se cambió nada: '.$e->getMessage()."\n");exit(1);
    }
    echo "Listo: $docsQuitados copias y $carpetasQuitadas carpetas enviadas a la papelera. El SGI y los archivos compartidos se conservaron.\n";
    echo "Podés recuperarlas desde Gestión documental > Papelera.\n";
    exit(0);
}

// ---------- Armar el plan ----------
$carpetas = [];
$st = $pdo->prepare('SELECT id, nombre, carpeta_padre_id FROM carpetas WHERE sector_id = ? AND activa=1 AND eliminado_lote IS NULL');
$st->execute([$sgiId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $carpetas[(int)$c['id']] = $c;
$cadena = function (?int $id) use ($carpetas): array {   // nombres de carpetas desde la raiz hasta la del documento
    $out = []; $guard = 0;
    while ($id && isset($carpetas[$id]) && $guard++ < 30) { array_unshift($out, $carpetas[$id]['nombre']); $id = $carpetas[$id]['carpeta_padre_id'] ? (int)$carpetas[$id]['carpeta_padre_id'] : null; }
    return $out;
};
$st = $pdo->prepare("SELECT * FROM documentos WHERE sector_id = ? AND activo = 1 AND eliminado_lote IS NULL AND archivo IS NOT NULL AND archivo <> '' ORDER BY id");
$st->execute([$sgiId]);
$docs = $st->fetchAll(PDO::FETCH_ASSOC);

$plan = []; $porSector = []; $sinClasificar = [];
foreach ($docs as $d) {
    try {validarCarpetaSector($pdo,$sgiId,$d['carpeta_id']!==null?(int)$d['carpeta_id']:null);} catch (RuntimeException $e) {continue;}
    $nombre = $d['nombre_original'] ?: basename((string)$d['archivo']);
    $cad = $cadena($d['carpeta_id'] !== null ? (int)$d['carpeta_id'] : null);
    $r = clasificarDocumentoSgi($nombre, $cad);
    $slug = $r['sector'];
    if ($slug !== null && !isset($sectores[$slug])) { $r['motivo'] .= " — pero el sector '$slug' no existe"; $slug = null; }
    $plan[] = ['doc' => $d, 'slug' => $slug, 'motivo' => $r['motivo'], 'cadena' => $cad, 'nombre' => $nombre];
    if ($slug === null) $sinClasificar[] = implode(' / ', array_merge($cad, [$nombre]));
    else $porSector[$slug] = ($porSector[$slug] ?? 0) + 1;
}

// CSV con el plan completo (backups/ no se sube a GitHub ni se abre desde el navegador)
$dirBackups = dirname(__DIR__, 2) . '/backups';
if (!is_dir($dirBackups)) @mkdir($dirBackups, 0775, true);
$csv = $dirBackups . '/clasificacion_sgi_' . date('Ymd_His') . '.csv';
if ($fh = @fopen($csv, 'w')) {
    fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, ['id', 'sector_destino', 'motivo', 'carpeta_origen', 'archivo'], ';');
    foreach ($plan as $p) fputcsv($fh, [$p['doc']['id'], $p['slug'] ?? '(solo SGI)', $p['motivo'], implode(' / ', $p['cadena']), $p['nombre']], ';');
    fclose($fh);
}

echo "==================================================\n";
echo $aplicar ? "CLASIFICACIÓN DE DOCUMENTOS DEL SGI — APLICANDO\n" : "CLASIFICACIÓN DE DOCUMENTOS DEL SGI — SOLO PLAN (no se cambia nada)\n";
echo "==================================================\n";
echo "Documentos activos en el SGI: " . count($docs) . "\n\n";
arsort($porSector);
foreach ($porSector as $slug => $n) printf("  %-16s %4d documentos\n", $sectores[$slug]['nombre'], $n);
printf("  %-16s %4d documentos (quedan solo en el SGI)\n", 'Sin clasificar', count($sinClasificar));
foreach (array_slice($sinClasificar, 0, 15) as $x) echo "      - $x\n";
echo "\nDetalle completo: $csv\n";

if (!$aplicar) {
    echo "\nPara crear las copias en los sectores: agregá --aplicar\n";
    exit(0);
}

// ---------- APLICAR ----------
$buscarCarpeta = function (int $sectorId, string $nombre, ?int $padre) use ($pdo): int {
    validarCarpetaSector($pdo,$sectorId,$padre);
    if ($padre === null) { $q = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND activa=1 AND eliminado_lote IS NULL AND nombre=? AND carpeta_padre_id IS NULL LIMIT 1'); $q->execute([$sectorId, $nombre]); }
    else { $q = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND activa=1 AND eliminado_lote IS NULL AND nombre=? AND carpeta_padre_id=? LIMIT 1'); $q->execute([$sectorId, $nombre, $padre]); }
    $id = $q->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare('INSERT INTO carpetas (sector_id, nombre, carpeta_padre_id, orden, activa) VALUES (?,?,?,900,1)')->execute([$sectorId, $nombre, $padre]);
    return (int)$pdo->lastInsertId();
};
$existe = $pdo->prepare('SELECT id FROM documentos WHERE sector_id = ? AND archivo = ? AND activo=1 AND eliminado_lote IS NULL LIMIT 1');
$ins = $pdo->prepare('INSERT INTO documentos (sector_id, carpeta_id, titulo, descripcion, tipo, archivo, nombre_original, activo, fecha_actualizacion, estado, version, fecha_vencimiento, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$creados = 0; $yaEstaban = 0; $cacheCarpetas = [];

$pdo->beginTransaction();
try {
    $pdo->exec('SET @naser_actor = NULL');
    foreach ($plan as $p) {
        if ($p['slug'] === null) continue;
        $sid = $sectores[$p['slug']]['id']; $d = $p['doc'];
        $actual=$pdo->prepare('SELECT archivo,carpeta_id FROM documentos WHERE id=? AND sector_id=? AND activo=1 AND eliminado_lote IS NULL FOR UPDATE');
        $actual->execute([(int)$d['id'],$sgiId]);$source=$actual->fetch(PDO::FETCH_ASSOC);
        if(!$source || $source['archivo']!==$d['archivo'] || $source['carpeta_id']!=$d['carpeta_id'])continue;
        validarCarpetaSector($pdo,$sgiId,$d['carpeta_id']!==null?(int)$d['carpeta_id']:null);
        $existe->execute([$sid, $d['archivo']]);
        if ($existe->fetchColumn()) { $yaEstaban++; continue; }
        // Carpeta destino: "Documentación SGI" / (misma estructura que en el SGI, sin la carpeta raiz)
        $clave = $sid . '|' . implode('/', array_slice($p['cadena'], 1));
        if (!isset($cacheCarpetas[$clave])) {
            $padre = $buscarCarpeta($sid, CARPETA_COPIAS, null);
            foreach (array_slice($p['cadena'], 1) as $nombreCarpeta) $padre = $buscarCarpeta($sid, $nombreCarpeta, $padre);
            $cacheCarpetas[$clave] = $padre;
        }
        $ins->execute([$sid, $cacheCarpetas[$clave], $d['titulo'], $d['descripcion'] ?? null, $d['tipo'] ?? 'documentacion', $d['archivo'], $d['nombre_original'] ?? null, 1,
                       $d['fecha_actualizacion'] ?? date('Y-m-d'), $d['estado'] ?? 'aprobado', $d['version'] ?? '1.0', $d['fecha_vencimiento'] ?? null, $d['creado_por'] ?? null]);
        $creados++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR, no se cambió nada: " . $e->getMessage() . "\n");
    exit(1);
}
echo "\nListo: $creados copias creadas en los sectores ($yaEstaban ya existían). El SGI quedó igual.\n";
echo "Para volver atrás: php php/tools/clasificar_documentos.php --deshacer\n";
