<?php
function validarCarpetaSector(PDO $pdo, int $sid, ?int $folder): void {
    $vistos=[];
    while($folder!==null) {
        if($folder<=0||isset($vistos[$folder])||count($vistos)>200) throw new RuntimeException('La estructura de carpetas no es válida.');
        $vistos[$folder]=true;
        $st=$pdo->prepare('SELECT carpeta_padre_id FROM carpetas WHERE id=? AND sector_id=? AND activa=1 AND eliminado_lote IS NULL'.($pdo->inTransaction()?' FOR UPDATE':''));
        $st->execute([$folder,$sid]);$row=$st->fetch();
        if(!$row) throw new RuntimeException('La carpeta indicada no pertenece al sector o está en papelera.');
        $folder=$row['carpeta_padre_id']===null?null:(int)$row['carpeta_padre_id'];
    }
}

function eliminarArchivoSinReferencias(PDO $pdo, string $uploads, ?string $archivo): void {
    if (!$archivo) return;
    $root = realpath($uploads);
    $path = realpath($uploads . '/' . $archivo);
    if (!$root || !$path || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) return;
    // Resolver también alias de ruta antes de borrar un archivo compartido.
    $refs = $pdo->query("SELECT archivo FROM documentos WHERE archivo IS NOT NULL UNION SELECT archivo FROM documento_versiones");
    foreach ($refs as $ref) {
        if (realpath($uploads . '/' . $ref['archivo']) === $path) return;
    }
    if (!unlink($path)) throw new RuntimeException('El registro se eliminó, pero no se pudo borrar su archivo físico.');
}
