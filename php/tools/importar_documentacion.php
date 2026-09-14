<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este importador solo puede ejecutarse desde la terminal.\n");
}

require __DIR__ . '/../config/db.php';

$uploadsRoot = dirname(__DIR__, 2) . '/uploads';
$options = getopt('', ['sector::','carpeta::']);
$sectorSlug = trim((string)($options['sector'] ?? 'sgi')) ?: 'sgi';
$carpetaRaiz = trim((string)($options['carpeta'] ?? 'Documentacion NASER'));

if ($carpetaRaiz === '') {
    fwrite(STDERR, "ERROR: La carpeta raíz no puede estar vacía.\n");
    exit(1);
}

function cleanFolderNameImport(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? $name;
    return mb_substr($name, 0, 160);
}

function allowedImportExtension(string $name): bool {
    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), [
        'pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','csv','txt'
    ], true);
}

function isTemporaryImportFile(string $name): bool {
    $base = basename(str_replace('\\', '/', $name));
    return str_starts_with($base, '~$')
        || str_starts_with($base, '.~lock.')
        || $base === '.DS_Store'
        || $base === 'Thumbs.db';
}

function detectDocumentType(array $parts): string {
    $text = mb_strtolower(implode(' ', $parts));
    if (str_contains($text, 'procedim')) return 'procedimiento';
    if (str_contains($text, 'formular')) return 'formulario';
    if (str_contains($text, 'registro')) return 'registro';
    if (str_contains($text, 'checklist') || str_contains($text, 'check list')) return 'checklist';
    if (str_contains($text, 'certific')) return 'certificado';
    return 'documentacion';
}

function findOrCreateImportFolder(PDO $pdo, int $sectorId, string $name, ?int $parentId): int {
    $name = cleanFolderNameImport($name);
    if ($name === '') throw new RuntimeException('Se encontró una carpeta con nombre vacío.');

    if ($parentId === null) {
        $stmt = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND nombre=? AND carpeta_padre_id IS NULL LIMIT 1');
        $stmt->execute([$sectorId, $name]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM carpetas WHERE sector_id=? AND nombre=? AND carpeta_padre_id=? LIMIT 1');
        $stmt->execute([$sectorId, $name, $parentId]);
    }

    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE carpetas SET activa=1 WHERE id=?')->execute([(int)$id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare('INSERT INTO carpetas(sector_id,nombre,carpeta_padre_id,orden,activa) VALUES(?,?,?,999,1)');
    $stmt->execute([$sectorId, $name, $parentId]);
    return (int)$pdo->lastInsertId();
}

function documentAlreadyImported(PDO $pdo, int $sectorId, ?int $folderId, string $relativePath): bool {
    if ($folderId === null) {
        $stmt = $pdo->prepare('SELECT id FROM documentos WHERE sector_id=? AND carpeta_id IS NULL AND archivo=? LIMIT 1');
        $stmt->execute([$sectorId, $relativePath]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM documentos WHERE sector_id=? AND carpeta_id=? AND archivo=? LIMIT 1');
        $stmt->execute([$sectorId, $folderId, $relativePath]);
    }
    return (bool)$stmt->fetchColumn();
}

function normalizedRelativePath(string $absolute, string $uploadsRoot): string {
    $absolute = str_replace('\\', '/', $absolute);
    $uploadsRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/');
    if (!str_starts_with($absolute, $uploadsRoot . '/')) {
        throw new RuntimeException('Archivo fuera de uploads: ' . $absolute);
    }
    return ltrim(substr($absolute, strlen($uploadsRoot)), '/');
}

$stmt = $pdo->prepare('SELECT id,nombre,slug FROM sectores WHERE slug=? LIMIT 1');
$stmt->execute([$sectorSlug]);
$sector = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sector) {
    fwrite(STDERR, "ERROR: No existe el sector con slug '{$sectorSlug}'.\n");
    exit(1);
}

$sectorId = (int)$sector['id'];
$sectorPhysicalRoot = $uploadsRoot . '/' . $sectorSlug;
$importRoot = $sectorPhysicalRoot . '/' . $carpetaRaiz;

if (!is_dir($importRoot)) {
    fwrite(STDERR, "ERROR: No encontré la carpeta:\n{$importRoot}\n\n");
    fwrite(STDERR, "Copiá primero tu documentación dentro de:\n{$sectorPhysicalRoot}/\n");
    exit(1);
}

$rootFolderId = findOrCreateImportFolder($pdo, $sectorId, basename($importRoot), null);
$folderCache = ['' => $rootFolderId];
$stats = [
    'folders_created_or_reused' => 1,
    'documents_imported' => 0,
    'duplicates_skipped' => 0,
    'unsupported_skipped' => 0,
    'temporary_skipped' => 0,
    'errors' => 0
];

echo "=============================================\n";
echo "NASER - IMPORTACIÓN MASIVA\n";
echo "=============================================\n";
echo "Sector: {$sector['nombre']} ({$sector['slug']})\n";
echo "Carpeta física: {$importRoot}\n";
echo "Modo: SOLO INDEXACIÓN. No se mueven archivos.\n";
echo "=============================================\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($importRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$pdo->beginTransaction();

try {
    foreach ($iterator as $item) {
        $absolutePath = $item->getPathname();
        $relativeInsideRoot = ltrim(str_replace('\\','/', substr($absolutePath, strlen(rtrim($importRoot, '/\\')))), '/');
        if ($relativeInsideRoot === '') continue;

        $parts = array_values(array_filter(explode('/', $relativeInsideRoot), static fn($part) => $part !== ''));
        if (!$parts) continue;

        if ($item->isDir()) {
            $parentId = $rootFolderId;
            $currentKey = '';
            foreach ($parts as $part) {
                $currentKey = $currentKey === '' ? $part : $currentKey . '/' . $part;
                if (isset($folderCache[$currentKey])) {
                    $parentId = $folderCache[$currentKey];
                    continue;
                }
                $parentId = findOrCreateImportFolder($pdo, $sectorId, $part, $parentId);
                $folderCache[$currentKey] = $parentId;
                $stats['folders_created_or_reused']++;
            }
            continue;
        }

        $fileName = array_pop($parts);
        if (isTemporaryImportFile($fileName)) {
            $stats['temporary_skipped']++;
            continue;
        }
        if (!allowedImportExtension($fileName)) {
            $stats['unsupported_skipped']++;
            continue;
        }

        $folderId = $rootFolderId;
        $folderKey = '';
        foreach ($parts as $part) {
            $folderKey = $folderKey === '' ? $part : $folderKey . '/' . $part;
            if (isset($folderCache[$folderKey])) {
                $folderId = $folderCache[$folderKey];
                continue;
            }
            $folderId = findOrCreateImportFolder($pdo, $sectorId, $part, $folderId);
            $folderCache[$folderKey] = $folderId;
            $stats['folders_created_or_reused']++;
        }

        $relativeStoredPath = normalizedRelativePath($absolutePath, $uploadsRoot);
        if (documentAlreadyImported($pdo, $sectorId, $folderId, $relativeStoredPath)) {
            $stats['duplicates_skipped']++;
            continue;
        }

        $title = trim(pathinfo($fileName, PATHINFO_FILENAME));
        if ($title === '') $title = $fileName;
        $documentType = detectDocumentType(array_merge($parts, [$fileName]));
        $modifiedTimestamp = $item->getMTime();
        $modifiedDate = $modifiedTimestamp > 0 ? date('Y-m-d', $modifiedTimestamp) : date('Y-m-d');

        try {
            $stmt = $pdo->prepare('INSERT INTO documentos(sector_id,carpeta_id,titulo,descripcion,tipo,archivo,nombre_original,activo,fecha_actualizacion,estado,version,fecha_vencimiento,creado_por) VALUES(?,?,?,?,?,?,?,1,?,?,?,NULL,NULL)');
            $stmt->execute([
                $sectorId,
                $folderId,
                mb_substr($title, 0, 180),
                '',
                $documentType,
                $relativeStoredPath,
                basename($fileName),
                $modifiedDate,
                'aprobado',
                '1.0'
            ]);
            $stats['documents_imported']++;
            if ($stats['documents_imported'] % 250 === 0) {
                echo "Importados: {$stats['documents_imported']} documentos...\n";
            }
        } catch (Throwable $e) {
            $stats['errors']++;
            fwrite(STDERR, "ERROR en {$relativeStoredPath}: {$e->getMessage()}\n");
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "\nIMPORTACIÓN CANCELADA:\n{$e->getMessage()}\n");
    exit(1);
}

echo "\n=============================================\n";
echo "IMPORTACIÓN FINALIZADA\n";
echo "=============================================\n";
echo "Carpetas creadas/reutilizadas: {$stats['folders_created_or_reused']}\n";
echo "Documentos registrados: {$stats['documents_imported']}\n";
echo "Duplicados omitidos: {$stats['duplicates_skipped']}\n";
echo "Temporales omitidos: {$stats['temporary_skipped']}\n";
echo "Extensiones no admitidas: {$stats['unsupported_skipped']}\n";
echo "Errores: {$stats['errors']}\n";
echo "=============================================\n";
echo "Los archivos físicos NO fueron movidos ni borrados.\n";
