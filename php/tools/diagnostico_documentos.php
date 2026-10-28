<?php
// Muestra si los documentos del SGI estan en la base y en el disco. NO modifica nada.
// Uso (en el servidor, dentro de la carpeta del proyecto):
//   docker compose exec web php php/tools/diagnostico_documentos.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }
require __DIR__ . '/../config/db.php';

$uploads = dirname(__DIR__, 2) . '/uploads';
echo "== Base de datos ==\n";
foreach ($pdo->query("SELECT s.slug, COUNT(d.id) AS docs, SUM(d.activo = 1) AS activos FROM sectores s LEFT JOIN documentos d ON d.sector_id = s.id GROUP BY s.id, s.slug ORDER BY s.slug")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo str_pad($r['slug'], 18) . " documentos: " . str_pad((string)$r['docs'], 6) . " activos: " . (int)$r['activos'] . "\n";
}
try {
    echo "Documentos en la papelera (eliminado_lote): " . (int)$pdo->query('SELECT COUNT(*) FROM documentos WHERE eliminado_lote IS NOT NULL')->fetchColumn() . "\n";
    echo "Carpetas en la papelera: " . (int)$pdo->query('SELECT COUNT(*) FROM carpetas WHERE eliminado_lote IS NOT NULL')->fetchColumn() . "\n";
    echo "Documentos inactivos (activo=0): " . (int)$pdo->query('SELECT COUNT(*) FROM documentos WHERE activo = 0')->fetchColumn() . "\n";
    echo "\nCarpetas del SGI con su cantidad de documentos activos:\n";
    foreach ($pdo->query("SELECT c.id, c.nombre, c.carpeta_padre_id, c.activa, c.eliminado_lote, (SELECT COUNT(*) FROM documentos d WHERE d.carpeta_id = c.id AND d.activo = 1 AND d.eliminado_lote IS NULL) AS docs FROM carpetas c JOIN sectores s ON s.id = c.sector_id WHERE s.slug = 'sgi' ORDER BY c.id LIMIT 60")->fetchAll(PDO::FETCH_ASSOC) as $c)
        echo '  #' . $c['id'] . ' ' . mb_substr((string)$c['nombre'], 0, 50) . ' · docs: ' . $c['docs'] . ($c['activa'] ? '' : ' · INACTIVA') . ($c['eliminado_lote'] ? ' · EN PAPELERA' : '') . "\n";
} catch (Throwable $e) { echo "(no se pudo leer la papelera: " . $e->getMessage() . ")\n"; }
echo "Carpetas en la base: " . (int)$pdo->query('SELECT COUNT(*) FROM carpetas')->fetchColumn() . "\n";
echo "Documentos sin carpeta: " . (int)$pdo->query('SELECT COUNT(*) FROM documentos WHERE carpeta_id IS NULL AND activo = 1')->fetchColumn() . "\n\n";

echo "== Disco (uploads/) ==\n";
if (!is_dir($uploads)) { echo "No existe la carpeta uploads.\n"; exit(0); }
foreach (scandir($uploads) as $dir) {
    if ($dir[0] === '.' || !is_dir("$uploads/$dir")) continue;
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$uploads/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $n++;
    echo str_pad($dir, 18) . " archivos: $n\n";
    if ($dir === 'sgi') foreach (scandir("$uploads/$dir") as $sub) if ($sub[0] !== '.') echo "    carpeta: $sub\n";
}
