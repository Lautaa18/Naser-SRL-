<?php
// ==========================================================
// Saca los documentos REPETIDOS (mismo contenido) y deja el original.
//
// Uso (en el servidor, desde la carpeta del proyecto):
//   docker compose exec web php php/tools/depurar_duplicados.php            -> SOLO MUESTRA el plan (no cambia nada)
//   docker compose exec web php php/tools/depurar_duplicados.php --aplicar  -> aplica el plan
//   Agregar --conservar=pdf si el original que querés conservar es el PDF (por defecto: el editable, Word/Excel).
//
// Como decide (dos casos):
//   A) MISMO CONTENIDO: el mismo archivo subido mas de una vez.
//   B) MISMO DOCUMENTO EN DISTINTOS FORMATOS: igual nombre (sin la extension) en el mismo sector, por ejemplo
//      "PGSN02-F1 Perfil de puesto" en .docx, .xlsx y .pdf. Se conserva UN formato: por defecto el editable
//      (Word/Excel/PowerPoint), despues el PDF, despues el resto. Con --conservar=pdf se invierte.
//      Si hay dos archivos del mismo formato con el mismo nombre y distinto contenido, NO se tocan (pueden ser versiones).
//
//   - Compara el CONTENIDO de los archivos (SHA-256), no el nombre: dos archivos con distinto nombre pero
//     igual contenido son repetidos; dos con el mismo nombre y distinto contenido NO.
//   - El ORIGINAL es el documento mas viejo (menor id). Los demas repetidos DEL MISMO SECTOR van a papelera.
//   - Las copias en OTROS sectores (por ejemplo las de "Documentación SGI" que crea clasificar_documentos.php)
//     se respetan: siguen apareciendo en su sector y conservan su archivo.
//   - Las filas repetidas se mueven a la papelera y se recuperan desde la aplicación.
//   - Se conservan todas las rutas y archivos, también los compartidos y las versiones.
//     El CSV es un informe adicional; la recuperación usa la papelera del sistema.
//   - Los documentos cuyo archivo no esta en el disco no se tocan (se listan al final).
//
// Antes de --aplicar conviene hacer un backup de la base (backup-db.sh).
// ==========================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require __DIR__ . '/../config/db.php';

$aplicar = in_array('--aplicar', array_slice($argv, 1), true);
$prefierePdf = in_array('--conservar=pdf', array_slice($argv, 1), true);
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
$docs = $pdo->query("SELECT * FROM documentos WHERE activo = 1 AND eliminado_lote IS NULL AND archivo IS NOT NULL AND archivo <> '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
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

// ---------- 3. Filas repetidas dentro de un mismo sector ----------
$vistos = [];      // sector|hash => doc original
$quitar = [];      // filas a enviar a papelera: [doc, original]
foreach ($docs as $d) {
    $a = (string)$d['archivo'];
    if (!isset($hashDe[$a])) continue;
    $clave = (int)$d['sector_id'] . '|' . $hashDe[$a];
    if (!isset($vistos[$clave])) { $vistos[$clave] = $d; continue; }
    $quitar[] = ['doc' => $d, 'original' => $vistos[$clave], 'motivo' => 'mismo contenido'];
}
$idsQuitar = array_map(fn($q) => (int)$q['doc']['id'], $quitar);

// ---------- 3b. El mismo documento en distintos formatos (mismo sector, mismo nombre) ----------
$normalizar = function (string $t): string {
    $t = mb_strtolower($t);
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t;
    $t = preg_replace('/\b(copia|copy)\b( de)?/', ' ', $t) ?? $t;        // "- copia", "copy"
    $t = preg_replace('/\s\d{1,2}$/', '', trim($t)) ?? $t;               // "(1)", "(2)" del explorador de Windows
    return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
};
$prioridadFormato = $prefierePdf
    ? ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'csv', 'txt', 'png', 'jpg', 'jpeg']
    : ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'pdf', 'csv', 'txt', 'png', 'jpg', 'jpeg'];
$peso = fn(string $ext) => ($i = array_search($ext, $prioridadFormato, true)) === false ? 99 : $i;
$yaSeVan = array_flip($idsQuitar);
$grupos = [];      // sector|nombre normalizado => filas
foreach ($docs as $d) {
    if (isset($yaSeVan[(int)$d['id']]) || !isset($hashDe[(string)$d['archivo']])) continue;
    $nombreBase = pathinfo((string)($d['nombre_original'] ?: $d['titulo']), PATHINFO_FILENAME);
    $norm = $normalizar($nombreBase !== '' ? $nombreBase : (string)$d['titulo']);
    if ($norm === '' || mb_strlen($norm) < 4) continue;   // nombres demasiado cortos o vacios: no se agrupan
    $grupos[(int)$d['sector_id'] . '|' . $norm][] = $d;
}
$gruposFormato = 0;
foreach ($grupos as $filas) {
    $exts = array_unique(array_map(fn($d) => strtolower(pathinfo((string)$d['archivo'], PATHINFO_EXTENSION)), $filas));
    if (count($exts) < 2) continue;                        // un solo formato: no hay nada que unificar
    usort($filas, fn($a, $b) => [$peso(strtolower(pathinfo((string)$a['archivo'], PATHINFO_EXTENSION))), (int)$a['id']] <=> [$peso(strtolower(pathinfo((string)$b['archivo'], PATHINFO_EXTENSION))), (int)$b['id']]);
    $original = $filas[0];
    $extOriginal = strtolower(pathinfo((string)$original['archivo'], PATHINFO_EXTENSION));
    $gruposFormato++;
    foreach ($filas as $d) {
        if ((int)$d['id'] === (int)$original['id']) continue;
        if (strtolower(pathinfo((string)$d['archivo'], PATHINFO_EXTENSION)) === $extOriginal) continue;   // mismo formato: puede ser otra version, no se toca
        $quitar[] = ['doc' => $d, 'original' => $original, 'motivo' => 'mismo documento en otro formato'];
    }
}
$idsQuitar = array_map(fn($q) => (int)$q['doc']['id'], $quitar);

// Los documentos enviados a papelera mantienen su archivo y sus versiones sin cambios.

// ---------- Informe ----------
$dirBackups = dirname(__DIR__, 2) . '/backups';
if (!is_dir($dirBackups)) @mkdir($dirBackups, 0775, true);
$marca = date('Ymd_His');
$csv = $dirBackups . "/duplicados_$marca.csv";
if ($quitar && ($fh = @fopen($csv, 'w'))) {
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['accion', 'motivo', 'id', 'sector', 'carpeta', 'titulo', 'archivo', 'se_conserva_id', 'se_conserva_titulo', 'se_conserva_archivo', 'se_conserva_carpeta'], ';');
    foreach ($quitar as $q) {
        $d = $q['doc']; $o = $q['original'];
        fputcsv($fh, ['PAPELERA', $q['motivo'], $d['id'], $sectores[(int)$d['sector_id']] ?? $d['sector_id'], $rutaCarpeta($d['carpeta_id'] !== null ? (int)$d['carpeta_id'] : null),
                      $d['titulo'], $d['archivo'], $o['id'], $o['titulo'], $o['archivo'], $rutaCarpeta($o['carpeta_id'] !== null ? (int)$o['carpeta_id'] : null)], ';');
    }
    fclose($fh);
}

echo "==================================================\n";
echo $aplicar ? "DEPURAR DOCUMENTOS REPETIDOS — APLICANDO\n" : "DEPURAR DOCUMENTOS REPETIDOS — SOLO PLAN (no se cambia nada)\n";
echo "==================================================\n";
echo "Documentos activos revisados: " . count($docs) . " (" . count($hashDe) . " archivos distintos en disco)\n";
$nContenido = count(array_filter($quitar, fn($q) => $q['motivo'] === 'mismo contenido'));
echo "Repetidos a mover a papelera: " . count($quitar) . " ($nContenido por mismo contenido, " . (count($quitar) - $nContenido) . " por ser el mismo documento en otro formato)\n";
echo "Formato que se conserva: " . ($prefierePdf ? 'PDF' : 'el editable (Word/Excel/PowerPoint) y si no hay, el PDF') . "\n";
$porSector = [];
foreach ($quitar as $q) { $n = $sectores[(int)$q['doc']['sector_id']] ?? '?'; $porSector[$n] = ($porSector[$n] ?? 0) + 1; }
arsort($porSector);
foreach ($porSector as $n => $c) printf("  %-18s %4d\n", $n, $c);
echo "Las copias en otros sectores conservan sus referencias y archivos.\n";
echo "Archivos físicos movidos o borrados: 0 (se conservan para recuperar).\n";
if ($faltan) echo "Sin archivo en el disco (no se tocan): " . count($faltan) . "\n";
echo "\nEjemplos:\n";
foreach (array_slice($quitar, 0, 15) as $q) {
    printf("  - [%s] se va #%d \"%s\" (.%s)  →  queda #%d \"%s\" (.%s)  [%s]\n", $sectores[(int)$q['doc']['sector_id']] ?? '?', $q['doc']['id'], $q['doc']['titulo'], strtolower(pathinfo((string)$q['doc']['archivo'], PATHINFO_EXTENSION)), $q['original']['id'], $q['original']['titulo'], strtolower(pathinfo((string)$q['original']['archivo'], PATHINFO_EXTENSION)), $q['motivo']);
}
if ($quitar) echo "\nDetalle completo: $csv\n";

if (!$quitar) { echo "\nNo hay documentos repetidos.\n"; exit(0); }
if (!$aplicar) { echo "\nSi el plan está bien: hacé un backup de la base y volvé a correr con --aplicar\n"; exit(0); }

// ---------- Aplicar: misma papelera recuperable que la aplicación ----------
$pdo->beginTransaction();
$enviados = 0;
try {
    $pdo->exec('SET @naser_actor = NULL');
    $actual = $pdo->prepare('SELECT sector_id,archivo,activo,eliminado_lote FROM documentos WHERE id=? FOR UPDATE');
    $mover = $pdo->prepare('UPDATE documentos SET activo=0,eliminado_lote=? WHERE id=? AND sector_id=? AND activo=1 AND eliminado_lote IS NULL');
    foreach ($quitar as $q) {
        $d=$q['doc']; $original=$q['original'];
        // Si un documento cambió desde el plan, conservarlo y volver a calcular en otra ejecución.
        $actual->execute([(int)$original['id']]); $o=$actual->fetch(PDO::FETCH_ASSOC);
        if (!$o || !(int)$o['activo'] || $o['eliminado_lote']!==null || $o['archivo']!==$original['archivo']) continue;
        $actual->execute([(int)$d['id']]); $r=$actual->fetch(PDO::FETCH_ASSOC);
        if (!$r || !(int)$r['activo'] || $r['eliminado_lote']!==null || $r['archivo']!==$d['archivo'] || (int)$r['sector_id']!==(int)$d['sector_id']) continue;
        $lote=enviarPapelera($pdo,'documento',(int)$d['id'],(int)$d['sector_id'],(string)$d['titulo']);
        $mover->execute([$lote,(int)$d['id'],(int)$d['sector_id']]);
        $enviados += $mover->rowCount();
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR, no se cambió nada en la base: " . $e->getMessage() . "\n");
    exit(1);
}
echo "\nListo: $enviados documentos repetidos enviados a la papelera.\n";
echo "Podés recuperarlos desde Gestión documental > Papelera. Todos sus archivos y versiones se conservaron.\n";
