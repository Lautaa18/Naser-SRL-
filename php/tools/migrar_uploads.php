<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

require __DIR__.'/../config/db.php';
require __DIR__.'/../config/storage.php';

$uploads=dirname(__DIR__,2).'/uploads/';
if(!is_dir($uploads)) mkdir($uploads,0775,true);

$docs=$pdo->query('SELECT id,sector_id,carpeta_id,archivo FROM documentos WHERE archivo IS NOT NULL AND archivo<>"" ORDER BY id')->fetchAll();
$moved=0;$updated=0;$missing=0;$already=0;$errors=0;

foreach($docs as $d){
    try{
        $oldRel=str_replace('\\','/',trim((string)$d['archivo']));
        $filename=basename($oldRel);
        $newRel=naser_relative_file_path($pdo,(int)$d['sector_id'],$d['carpeta_id']!==null?(int)$d['carpeta_id']:null,$filename);
        $oldPath=naser_upload_physical_path($uploads,$oldRel);
        $newDir=naser_ensure_folder_dir($pdo,(int)$d['sector_id'],$d['carpeta_id']!==null?(int)$d['carpeta_id']:null,$uploads);
        $newPath=$newDir.$filename;

        if($oldRel===$newRel && is_file($newPath)){$already++;continue;}

        // Compatibilidad con archivos viejos guardados solo por basename en la raíz.
        if(!is_file($oldPath)){
            $legacy=$uploads.$filename;
            if(is_file($legacy)) $oldPath=$legacy;
        }

        if(is_file($oldPath)){
            if(realpath($oldPath)!==realpath($newPath)){
                if(is_file($newPath)){
                    // Si ya existe el destino, no pisar; solo usarlo si es el mismo contenido.
                    if(hash_file('sha256',$oldPath)===hash_file('sha256',$newPath)){
                        @unlink($oldPath);
                    }else{
                        $base=pathinfo($filename,PATHINFO_FILENAME);
                        $ext=pathinfo($filename,PATHINFO_EXTENSION);
                        $filename=$base.'_'.bin2hex(random_bytes(3)).($ext!==''?'.'.$ext:'');
                        $newPath=$newDir.$filename;
                        $newRel=naser_relative_file_path($pdo,(int)$d['sector_id'],$d['carpeta_id']!==null?(int)$d['carpeta_id']:null,$filename);
                        if(!@rename($oldPath,$newPath) && !(@copy($oldPath,$newPath)&&@unlink($oldPath))) throw new RuntimeException('No se pudo mover archivo.');
                        $moved++;
                    }
                }else{
                    if(!@rename($oldPath,$newPath) && !(@copy($oldPath,$newPath)&&@unlink($oldPath))) throw new RuntimeException('No se pudo mover archivo.');
                    $moved++;
                }
            }
        }else{
            $missing++;
        }

        if((string)$d['archivo']!==$newRel){
            $pdo->prepare('UPDATE documentos SET archivo=? WHERE id=?')->execute([$newRel,(int)$d['id']]);
            $updated++;
        }
    }catch(Throwable $e){
        $errors++;
        fwrite(STDERR,"Documento {$d['id']}: {$e->getMessage()}\n");
    }
}

echo "Migración NASER finalizada\n";
echo "Movidos físicamente: $moved\n";
echo "Rutas BD actualizadas: $updated\n";
echo "Ya organizados: $already\n";
echo "Archivos no encontrados: $missing\n";
echo "Errores: $errors\n";
