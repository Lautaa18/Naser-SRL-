<?php

/**
 * Utilidades de almacenamiento físico para NASER.
 * Los archivos nuevos se guardan como:
 * uploads/<sector>/<carpeta>/<subcarpeta>/<archivo>
 */

function naser_safe_segment(string $value): string {
    $value = trim($value);

    $value = preg_replace(
        '/[\\\\\/:*?"<>|\x00-\x1F]+/u',
        '_',
        $value
    ) ?? $value;

    $value = preg_replace(
        '/\s+/u',
        ' ',
        $value
    ) ?? $value;

    $value = trim(
        $value,
        " .\t\n\r\0\x0B"
    );

    if ($value === '') {
        $value = '_';
    }

    return mb_substr(
        $value,
        0,
        150
    );
}


function naser_sector_slug(
    PDO $pdo,
    int $sectorId
): string {

    static $cache = [];

    if (isset($cache[$sectorId])) {
        return $cache[$sectorId];
    }

    $st = $pdo->prepare(
        'SELECT slug
         FROM sectores
         WHERE id = ?
         LIMIT 1'
    );

    $st->execute([
        $sectorId
    ]);

    $slug = (string)$st->fetchColumn();

    if ($slug === '') {
        throw new RuntimeException(
            'Sector inválido.'
        );
    }

    return $cache[$sectorId] =
        naser_safe_segment($slug);
}


function naser_folder_parts(
    PDO $pdo,
    int $sectorId,
    ?int $folderId
): array {

    if (!$folderId) {
        return [];
    }

    $parts = [];
    $current = $folderId;
    $guard = 0;

    while (
        $current &&
        $guard < 200
    ) {

        $st = $pdo->prepare(
            'SELECT
                id,
                nombre,
                carpeta_padre_id
             FROM carpetas
             WHERE id = ?
             AND sector_id = ?
             LIMIT 1'
        );

        $st->execute([
            $current,
            $sectorId
        ]);

        $row = $st->fetch();

        if (!$row) {
            throw new RuntimeException(
                'La carpeta indicada no pertenece al sector.'
            );
        }

        array_unshift(
            $parts,
            naser_safe_segment(
                (string)$row['nombre']
            )
        );

        $current =
            $row['carpeta_padre_id'] !== null
            ? (int)$row['carpeta_padre_id']
            : null;

        $guard++;
    }

    if ($guard >= 200) {
        throw new RuntimeException(
            'Se detectó una estructura de carpetas inválida.'
        );
    }

    return $parts;
}


function naser_relative_folder(
    PDO $pdo,
    int $sectorId,
    ?int $folderId
): string {

    $parts = array_merge(
        [
            naser_sector_slug(
                $pdo,
                $sectorId
            )
        ],
        naser_folder_parts(
            $pdo,
            $sectorId,
            $folderId
        )
    );

    return implode(
        '/',
        array_map(
            'naser_safe_segment',
            $parts
        )
    );
}


function naser_ensure_folder_dir(
    PDO $pdo,
    int $sectorId,
    ?int $folderId,
    string $uploadsRoot
): string {

    $uploadsRoot =
        rtrim(
            $uploadsRoot,
            '/\\'
        )
        . DIRECTORY_SEPARATOR;

    $rel =
        naser_relative_folder(
            $pdo,
            $sectorId,
            $folderId
        );

    $dir =
        $uploadsRoot
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $rel
        );

    if (
        !is_dir($dir) &&
        !mkdir(
            $dir,
            0775,
            true
        ) &&
        !is_dir($dir)
    ) {

        throw new RuntimeException(
            'No se pudo crear la carpeta física de destino.'
        );
    }

    return
        rtrim(
            $dir,
            '/\\'
        )
        . DIRECTORY_SEPARATOR;
}


function naser_relative_file_path(
    PDO $pdo,
    int $sectorId,
    ?int $folderId,
    string $storedName
): string {

    return
        naser_relative_folder(
            $pdo,
            $sectorId,
            $folderId
        )
        . '/'
        . basename($storedName);
}


function naser_upload_physical_path(
    string $uploadsRoot,
    string $relativeFile
): string {

    $uploadsRoot =
        rtrim(
            $uploadsRoot,
            '/\\'
        )
        . DIRECTORY_SEPARATOR;

    $relativeFile =
        str_replace(
            '\\',
            '/',
            trim($relativeFile)
        );

    $relativeFile =
        ltrim(
            $relativeFile,
            '/'
        );

    $parts = [];

    foreach (
        explode(
            '/',
            $relativeFile
        )
        as $part
    ) {

        if (
            $part === '' ||
            $part === '.'
        ) {
            continue;
        }

        if ($part === '..') {
            throw new RuntimeException(
                'Ruta de archivo inválida.'
            );
        }

        $parts[] = $part;
    }

    return
        $uploadsRoot
        . implode(
            DIRECTORY_SEPARATOR,
            $parts
        );
}


function naser_encode_relative_url(
    string $relativeFile
): string {

    $relativeFile =
        str_replace(
            '\\',
            '/',
            trim($relativeFile)
        );

    $parts =
        array_values(
            array_filter(
                explode(
                    '/',
                    $relativeFile
                ),
                static fn($p) =>
                    $p !== ''
            )
        );

    return implode(
        '/',
        array_map(
            'rawurlencode',
            $parts
        )
    );
}


function naser_upload_url(
    string $relativeFile
): string {

    return app_url(
        '/uploads/'
        . naser_encode_relative_url(
            $relativeFile
        )
    );
}


function naser_delete_tree(
    string $path
): void {

    if (!file_exists($path)) {
        return;
    }

    if (
        is_file($path) ||
        is_link($path)
    ) {

        @unlink($path);

        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {

        if (
            $item === '.' ||
            $item === '..'
        ) {
            continue;
        }

        naser_delete_tree(
            $path
            . DIRECTORY_SEPARATOR
            . $item
        );
    }

    @rmdir($path);
}