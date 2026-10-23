<?php
// ==========================================================
// Saca los documentos REPETIDOS (mismo contenido) y deja el original.
//
// Uso (en el servidor, desde la carpeta del proyecto):
//   docker compose exec web php php/tools/depurar_duplicados.php            -> SOLO MUESTRA el plan (no cambia nada)
//   docker compose exec web php php/tools/depurar_duplicados.php --aplicar  -> aplica el plan
//
// Como decide:
//   - Compara el CONTENIDO de los archivos (SHA-256), no el nombre: dos archivos con distinto nombre pero
//     igual contenido son repetidos; dos con el mismo nombre y distinto contenido NO.
//   - El ORIGINAL es el documento mas viejo (menor id). Los demas repetidos DEL MISMO SECTOR se eliminan.
//   - Las copias en OTROS sectores (por ejemplo las de "Documentación SGI" que crea clasificar_documentos.php)
//     se respetan: siguen apareciendo en su sector; solo pasan a apuntar al archivo original.
//   - Los archivos fisicos que quedan sin uso no se borran: se MUEVEN a backups/duplicados_FECHA/.
//     Ahi tambien queda un CSV con todos los datos de las filas eliminadas, por si hay que recuperar algo.
//   - Los documentos cuyo archivo no esta en el disco no se tocan (se listan al final).
//
// Antes de --aplicar conviene hacer un backup de la base (backup-db.sh).
// ==========================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require __DIR__ . '/../config/db.php';

$aplicar = in_array('--aplicar', array_slice($argv, 1), true);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$uploads = realpath(dirname(__DIR__, 2) . '/uploads');
if ($uploads === false) { fwrite(STDERR, "ERROR: no existe la carpeta uploads.\n"); exit(1); }

/** Ruta fisica de un documento (puede estar guardado como "sgi/..." o solo con el nombre). */
$rutaFisica = function (string $archivo) use ($uploads): ?string {
    $archivo = ltrim(str_replace('\\', '/', $archivo), '/');
    if ($archivo === '' || str_contains($archivo, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $archivo)) return null;
    foreach ([$archivo, basename($archivo)] as $rel) {
        $p = realpath($uploads . '/' . $rel);
        if ($p !== false && is_file($p) && str_starts_with($p, $uploads . DIRECTORY_SEPARATOR)) return $p;
    }
    return null;
};

$sectores = [];
foreach ($pdo->query('SELECT id, nombre FROM sectores')->fetchAll(PDO::FETCH_ASSOC) as $s) $sectores[(int)$s['id']] = $s['nombre'];
$carpetas = [];
foreach ($pdo->query('SELECT id, nombre, carpeta_padre_id FROM carpetas')->fetchAll(PDO::FETCH_ASSOC) as $c) $carpetas[(int)$c['id']] = $c;
$rutaCarpeta = function (?int $id) use ($carpetas): string {
    $out = []; $guard = 0;
    while ($id && isset($carpetas[$id]) && $guard++ < 30) { array_unshift($out, $carpetas[$id]['nombre']); $id = $carpetas[$id]['carpeta_padre_id'] ? (int)$carpetas[$id]['carpeta_padre_id'] : null; }
    return implode(' / ', $out) ?: '(sin carpeta)';
};

// ---------- 1. Hash de cada archivo fisico distinto ----------
$docs = $pdo->query("SELECT * FROM documentos WHERE activo = 1 AND archivo IS NOT NULL AND archivo <> '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hashDe = [];      // archivo (como esta en la base) => sha256
$rutaDe = [];      // archivo => ruta fisica
$faltan = [];      // documentos sin archivo en el disco
foreach ($docs as $d) {
    $a = (string)$d['archivo'];
    if (isset($hashDe[$a]) || isset($faltan[$a])) continue;
    $p = $rutaFisica($a);
    if ($p === null) { $faltan[$a] = true; continue; }
    $hashDe[$a] = hash_file('sha256', $p);
    $rutaDe[$a] = $p;
}

// ---------- 2. Archivo canonico por contenido (el del documento mas viejo) ----------
$canonico = [];    // hash => archivo
foreach ($docs as $d) {
    $a = (string)$d['archivo'];
    if (!isset($hashDe[$a])) continue;
    $canonico[$hashDe[$a]] ??= $a;
}
$repuntar = [];    // archivo redundante => archivo canonico (mismo contenido)
foreach ($hashDe as $a => $h) if ($canonico[$h] !== $a) $repuntar[$a] = $canonico[$h];

// ---------- 3. Filas repetidas dentro de un mismo sector ----------
$vistos = [];      // sector|hash => doc original
$quitar = [];      // filas a eliminar: [doc, original]
foreach ($docs as $d) {
    $a = (string)$d['archivo'];
    if (!isset($hashDe[$a])) continue;
    $clave = (int)$d['sector_id'] . '|' . $hashDe[$a];
    if (!isset($vistos[$clave])) { $vistos[$clave] = $d; continue; }
    $quitar[] = ['doc' => $d, 'original' => $vistos[$clave]];
}
$idsQuitar = array_map(fn($q) => (int)$q['doc']['id'], $quitar);

// Archivos fisicos que quedarian sin ninguna fila (considerando tambien filas inactivas)
$todasLasFilas = $pdo->query("SELECT id, archivo FROM documentos WHERE archivo IS NOT NULL AND archivo <> ''")->fetchAll(PDO::FETCH_ASSOC);
$usos = [];
foreach ($todasLasFilas as $f) {
    $a = (string)$f['archivo'];
    if (in_array((int)$f['id'], $idsQuitar, true)) continue;          // la fila se va
    $usos[$repuntar[$a] ?? $a] = ($usos[$repuntar[$a] ?? $a] ?? 0) + 1; // la fila queda, apuntando al canonico
}
$sobrantes = [];   // archivos fisicos a mover
foreach (array_keys($hashDe) as $a) if (empty($usos[$a])) $sobrantes[$a] = $rutaDe[$a];

// ---------- Informe ----------
$dirBackups = dirname(__DIR__, 2) . '/backups';
if (!is_dir($dirBackups)) @mkdir($dirBackups, 0775, true);
$marca = date('Ymd_His');
$csv = $dirBackups . "/duplicados_$marca.csv";
if ($quitar && ($fh = @fopen($csv, 'w'))) {
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['accion', 'id', 'sector', 'carpeta', 'titulo', 'archivo', 'se_conserva_id', 'se_conserva_carpeta'], ';');
    foreach ($quitar as $q) {
        $d = $q['doc']; $o = $q['original'];
        fputcsv($fh, ['ELIMINAR', $d['id'], $sectores[(int)$d['sector_id']] ?? $d['sector_id'], $rutaCarpeta($d['carpeta_id'] !== null ? (int)$d['carpeta_id'] : null),
                      $d['titulo'], $d['archivo'], $o['id'], $rutaCarpeta($o['carpeta_id'] !== null ? (int)$o['carpeta_id'] : null)], ';');
    }
    fclose($fh);
}

echo "==================================================\n";
echo $aplicar ? "DEPURAR DOCUMENTOS REPETIDOS — APLICANDO\n" : "DEPURAR DOCUMENTOS REPETIDOS — SOLO PLAN (no se cambia nada)\n";
echo "==================================================\n";
echo "Documentos activos revisados: " . count($docs) . " (" . count($hashDe) . " archivos distintos en disco)\n";
echo "Repetidos a eliminar (mismo sector, mismo contenido): " . count($quitar) . "\n";
$porSector = [];
foreach ($quitar as $q) { $n = $sectores[(int)$q['doc']['sector_id']] ?? '?'; $porSector[$n] = ($porSector[$n] ?? 0) + 1; }
arsort($porSector);
foreach ($porSector as $n => $c) printf("  %-18s %4d\n", $n, $c);
echo "Copias en otros sectores que pasan a apuntar al original: " . count($repuntar) . " archivo(s)\n";
echo "Archivos que se mueven a backups/duplicados_$marca/: " . count($sobrantes) . "\n";
if ($faltan) echo "Sin archivo en el disco (no se tocan): " . count($faltan) . "\n";
echo "\nEjemplos (se conserva el más viejo):\n";
foreach (array_slice($quitar, 0, 12) as $q) {
    printf("  - [%s] #%d \"%s\"  ←  igual a #%d \"%s\"\n", $sectores[(int)$q['doc']['sector_id']] ?? '?', $q['doc']['id'], $q['doc']['titulo'], $q['original']['id'], $q['original']['titulo']);
}
if ($quitar) echo "\nDetalle completo: $csv\n";

if (!$quitar && !$repuntar) { echo "\nNo hay documentos repetidos.\n"; exit(0); }
if (!$aplicar) { echo "\nSi el plan está bien: hacé un backup de la base y volvé a correr con --aplicar\n"; exit(0); }

// ---------- Aplicar ----------
$dirMover = $dirBackups . "/duplicados_$marca";
$pdo->beginTransaction();
try {
    // Copia de seguridad de las filas que se van a eliminar
    if ($quitar) {
        $fh = fopen($dirBackups . "/duplicados_filas_$marca.csv", 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        $cols = array_keys($quitar[0]['doc']);
        fputcsv($fh, $cols, ';');
        foreach ($quitar as $q) fputcsv($fh, array_values($q['doc']), ';');
        fclose($fh);
    }
    $upd = $pdo->prepare('UPDATE documentos SET archivo = ? WHERE archivo = ?');
    foreach ($repuntar as $viejo => $nuevo) $upd->execute([$nuevo, $viejo]);
    $del = $pdo->prepare('DELETE FROM documentos WHERE id = ?');
    foreach ($idsQuitar as $id) $del->execute([$id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR, no se cambió nada en la base: " . $e->getMessage() . "\n");
    exit(1);
}

// Los archivos se mueven DESPUES de confirmar la base (si algo falla, el archivo queda donde estaba)
$movidos = 0;
if ($sobrantes) {
    if (!is_dir($dirMover)) @mkdir($dirMover, 0775, true);
    foreach ($sobrantes as $a => $ruta) {
        $destino = $dirMover . '/' . ($movidos + 1) . '_' . basename($ruta);
        if (@rename($ruta, $destino) || (@copy($ruta, $destino) && @unlink($ruta))) $movidos++;
        else fwrite(STDERR, "No se pudo mover: $ruta\n");
    }
}
echo "\nListo: " . count($idsQuitar) . " documentos repetidos eliminados, $movidos archivos movidos a backups/duplicados_$marca/.\n";
echo "Si algo no estaba bien: las filas eliminadas están en backups/duplicados_filas_$marca.csv y los archivos en esa carpeta.\n";
