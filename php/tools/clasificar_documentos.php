<?php
// ==========================================================
// Reparte los documentos del SGI entre los sectores.
// Cada documento queda EN EL SGI (no se toca) y ademas aparece en el sector que le corresponde,
// dentro de una carpeta "Documentación SGI". No se copian archivos: la copia apunta al mismo archivo.
//
// Uso (desde la carpeta del proyecto, en el servidor):
//   docker compose exec web php php/tools/clasificar_documentos.php             -> SOLO MUESTRA el plan (no cambia nada)
//   docker compose exec web php php/tools/clasificar_documentos.php --aplicar   -> crea las copias en los sectores
//   docker compose exec web php php/tools/clasificar_documentos.php --deshacer  -> borra esas copias (el SGI queda igual)
//
// Las reglas estan en php/config/clasificacion_sgi.php. El plan se guarda en backups/clasificacion_sgi_FECHA.csv
// ==========================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require __DIR__ . '/../config/db.php';
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

// ---------- DESHACER ----------
if ($deshacer) {
    $pdo->beginTransaction();
    $st = $pdo->prepare("DELETE FROM documentos WHERE sector_id <> ? AND archivo LIKE 'sgi/%'");
    $st->execute([$sgiId]); $docs = $st->rowCount();
    $st = $pdo->prepare('DELETE FROM carpetas WHERE sector_id <> ? AND carpeta_padre_id IS NULL AND nombre = ?');
    $st->execute([$sgiId, CARPETA_COPIAS]); $carp = $st->rowCount();
    $pdo->commit();
    echo "Listo: se quitaron $docs copias de documentos y $carp carpetas '" . CARPETA_COPIAS . "'. El SGI quedó igual.\n";
    exit(0);
}

// ---------- Armar el plan ----------
$carpetas = [];
$st = $pdo->prepare('SELECT id, nombre, carpeta_padre_id FROM carpetas WHERE sector_id = ?');
$st->execute([$sgiId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $carpetas[(int)$c['id']] = $c;
$cadena = function (?int $id) use ($carpetas): array {   // nombres de carpetas desde la raiz hasta la del documento
    $out = []; $guard = 0;
    while ($id && isset($carpetas[$id]) && $guard++ < 30) { array_unshift($out, $carpetas[$id]['nombre']); $id = $carpetas[$id]['carpeta_padre_id'] ? (int)$carpetas[$id]['carpeta_padre_id'] : null; }
    return $out;
};
$st = $pdo->prepare("SELECT * FROM documentos WHERE sector_id = ? AND activo = 1 AND archivo IS NOT NULL AND archivo <> '' ORDER BY id");
$st->execute([$sgiId]);
$docs = $st->fetchAll(PDO::FETCH_ASSOC);

$plan = []; $porSector = []; $sinClasificar = [];
foreach ($docs as $d) {
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
    if ($padre === null) { $q = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND nombre=? AND carpeta_padre_id IS NULL LIMIT 1'); $q->execute([$sectorId, $nombre]); }
    else { $q = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND nombre=? AND carpeta_padre_id=? LIMIT 1'); $q->execute([$sectorId, $nombre, $padre]); }
    $id = $q->fetchColumn();
    if ($id) { $pdo->prepare('UPDATE carpetas SET activa=1 WHERE id=?')->execute([(int)$id]); return (int)$id; }
    $pdo->prepare('INSERT INTO carpetas (sector_id, nombre, carpeta_padre_id, orden, activa) VALUES (?,?,?,900,1)')->execute([$sectorId, $nombre, $padre]);
    return (int)$pdo->lastInsertId();
};
$existe = $pdo->prepare('SELECT id FROM documentos WHERE sector_id = ? AND archivo = ? LIMIT 1');
$ins = $pdo->prepare('INSERT INTO documentos (sector_id, carpeta_id, titulo, descripcion, tipo, archivo, nombre_original, activo, fecha_actualizacion, estado, version, fecha_vencimiento, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$creados = 0; $yaEstaban = 0; $cacheCarpetas = [];

$pdo->beginTransaction();
try {
    foreach ($plan as $p) {
        if ($p['slug'] === null) continue;
        $sid = $sectores[$p['slug']]['id']; $d = $p['doc'];
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
