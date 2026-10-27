<?php
/** Migraciones aditivas y trazabilidad de NASER. */
function naser_mejoras_schema(PDO $pdo): void {
    $columnas = [
        'usuario_sector' => ['puede_descargar' => 'TINYINT(1) NOT NULL DEFAULT 1', 'puede_aprobar' => 'TINYINT(1) NOT NULL DEFAULT 1'],
        'formularios_registros' => ['revision' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'client_token' => 'VARCHAR(64) NULL', 'eliminado_lote' => 'CHAR(32) NULL'],
        'documentos' => ['eliminado_lote' => 'CHAR(32) NULL'],
        'carpetas' => ['eliminado_lote' => 'CHAR(32) NULL'],
        'finanzas_trabajadores' => ['empleado_id' => 'INT NULL'],
        'notificaciones' => ['mail_intentado_en'=>'DATETIME NULL','mail_enviado_en'=>'DATETIME NULL','mail_error'=>'VARCHAR(255) NULL','mail_transporte'=>'VARCHAR(12) NULL'],
    ];
    foreach ($columnas as $tabla => $campos) foreach ($campos as $campo => $def) {
        if (!hasColumn($pdo, $tabla, $campo)) $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `$campo` $def");
    }
    $pdo->exec('ALTER TABLE empleados MODIFY legajo VARCHAR(50) NOT NULL');
    $pdo->exec('ALTER TABLE finanzas_trabajadores MODIFY legajo VARCHAR(50) NOT NULL, MODIFY nombre_completo VARCHAR(160) NOT NULL, MODIFY email VARCHAR(160) NULL, MODIFY telefono VARCHAR(60) NULL');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uk_formulario_cliente ON formularios_registros(client_token)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS papelera (
        id INT AUTO_INCREMENT PRIMARY KEY, lote CHAR(32) NOT NULL UNIQUE,
        tipo VARCHAR(30) NOT NULL, registro_id INT NOT NULL, sector_id INT NOT NULL,
        nombre VARCHAR(200) NOT NULL, usuario_id INT NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, restaurado_en DATETIME NULL,
        INDEX idx_papelera_sector(sector_id, restaurado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria_cambios (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, tabla VARCHAR(60) NOT NULL, registro_id INT NULL,
        sector_id INT NULL, usuario_id INT NULL, accion VARCHAR(12) NOT NULL,
        antes LONGTEXT NULL, despues LONGTEXT NULL, creado_en DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_auditoria_sector(sector_id, id), INDEX idx_auditoria_registro(tabla, registro_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_conciliacion (
        id INT AUTO_INCREMENT PRIMARY KEY, finanzas_id INT NOT NULL UNIQUE, empleado_id INT NOT NULL,
        diferencias_json LONGTEXT NOT NULL, resuelto_en DATETIME NULL, resuelto_por INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_tareas (
        nombre VARCHAR(50) PRIMARY KEY, iniciado_en DATETIME NULL, completado_en DATETIME NULL,
        error TEXT NULL, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    naser_consolidar_personal($pdo);
    // Triggers cubren también importaciones y cambios desde módulos existentes.
    $tablas = ['documentos', 'carpetas', 'formularios_registros', 'empleados', 'empleados_habilitaciones',
        'finanzas_trabajadores', 'finanzas_checklist', 'finanzas_indicadores', 'rrhh_vencimientos', 'usuarios', 'usuario_sector'];
    $rrhh = (int)$pdo->query("SELECT id FROM sectores WHERE slug='rrhh'")->fetchColumn();
    foreach ($tablas as $tabla) {
        $campos = $pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN);
        // No registrar credenciales ni valores derivados que cambian al guardar.
        $campos = array_values(array_diff($campos, ['password', 'ultimo_acceso', 'actualizado_en']));
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $accion) {
            $json = static function (string $prefijo) use ($campos): string {
                return 'JSON_OBJECT(' . implode(',', array_map(fn($c) => "'$c', $prefijo.`$c`", $campos)) . ')';
            };
            $prefijo = $accion === 'DELETE' ? 'OLD' : 'NEW';
            $antes = $accion === 'INSERT' ? 'NULL' : $json('OLD');
            $despues = $accion === 'DELETE' ? 'NULL' : $json('NEW');
            $id = in_array('id', $campos, true) ? "$prefijo.id" : "$prefijo.usuario_id";
            $sector = str_starts_with($tabla, 'empleados') ? (string)$rrhh : (in_array('sector_id', $campos, true) ? "$prefijo.sector_id" : 'NULL');
            $condicion = $accion === 'UPDATE' ? "IF NOT (BINARY $antes <=> BINARY $despues) THEN " : '';
            $fin = $accion === 'UPDATE' ? ' END IF;' : '';
            $nombre = 'naser_audit_' . $tabla . '_' . strtolower($accion);
            $pdo->exec("CREATE OR REPLACE TRIGGER `$nombre` AFTER $accion ON `$tabla` FOR EACH ROW BEGIN
                $condicion INSERT INTO auditoria_cambios(tabla,registro_id,sector_id,usuario_id,accion,antes,despues)
                VALUES ('$tabla',$id,$sector,NULLIF(@naser_actor,0),'$accion',$antes,$despues); $fin END");
        }
    }
}

/** Vincula por legajo, conserva diferencias y nunca reemplaza el maestro automáticamente. */
function naser_consolidar_personal(PDO $pdo): void {
    foreach ($pdo->query('SELECT * FROM finanzas_trabajadores WHERE empleado_id IS NULL ORDER BY id')->fetchAll() as $f) {
        $legajo = trim((string)$f['legajo']);
        if ($legajo === '') continue;
        $st = $pdo->prepare('SELECT * FROM empleados WHERE legajo = ?'); $st->execute([$legajo]); $e = $st->fetch();
        if (!$e) {
            $pdo->prepare('INSERT INTO empleados(legajo,apellido_nombre,dni,email_corporativo,telefono) VALUES(?,?,?,?,?)')
                ->execute([$legajo, $f['nombre_completo'], $f['dni'], $f['email'], $f['telefono']]);
            $st->execute([$legajo]); $e = $st->fetch();
        }
        $diferencias = [];
        foreach (['nombre_completo'=>'apellido_nombre','dni'=>'dni','email'=>'email_corporativo','telefono'=>'telefono'] as $origen=>$destino) {
            if (trim((string)$f[$origen]) !== '' && trim((string)$f[$origen]) !== trim((string)$e[$destino])) {
                $diferencias[$destino] = ['maestro'=>$e[$destino], 'finanzas'=>$f[$origen]];
            }
        }
        foreach (['vencimiento_carnet'=>'licencia_conducir','vencimiento_defensivo'=>'manejo_defensivo'] as $campo=>$tipo) {
            if (!$f[$campo]) continue;
            $st = $pdo->prepare('SELECT fecha_vencimiento FROM empleados_habilitaciones WHERE empleado_id=? AND tipo=?');
            $st->execute([$e['id'],$tipo]); $actual = $st->fetchColumn();
            if ($actual === false) {
                $pdo->prepare('INSERT INTO empleados_habilitaciones(empleado_id,tipo,fecha_vencimiento) VALUES(?,?,?)')->execute([$e['id'],$tipo,$f[$campo]]);
            } elseif ($actual !== $f[$campo]) $diferencias[$tipo] = ['maestro'=>$actual,'finanzas'=>$f[$campo]];
        }
        if ($diferencias) $pdo->prepare('INSERT INTO personal_conciliacion(finanzas_id,empleado_id,diferencias_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE diferencias_json=VALUES(diferencias_json)')
            ->execute([$f['id'],$e['id'],json_encode($diferencias, JSON_UNESCAPED_UNICODE)]);
        $pdo->prepare('UPDATE finanzas_trabajadores SET empleado_id=? WHERE id=?')->execute([$e['id'],$f['id']]);
    }
}

function enviarPapelera(PDO $pdo, string $tipo, int $id, int $sid, string $nombre): string {
    $lote = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO papelera(lote,tipo,registro_id,sector_id,nombre,usuario_id) VALUES(?,?,?,?,?,?)')
        ->execute([$lote,$tipo,$id,$sid,mb_substr($nombre,0,200),$_SESSION['usuario_id']??null]);
    return $lote;
}

function restaurarPapelera(PDO $pdo, int $id): void {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM papelera WHERE id=? AND restaurado_en IS NULL FOR UPDATE'); $st->execute([$id]); $p=$st->fetch();
        if (!$p || !puedeEditarSector($pdo,(int)$p['sector_id'])) throw new RuntimeException('No tenés permiso para restaurar este elemento.');
        $sid=(int)$p['sector_id']; $lote=$p['lote'];
        if ($p['tipo']==='formulario') {
            $pdo->prepare('UPDATE formularios_registros SET eliminado_lote=NULL, revision=revision+1 WHERE eliminado_lote=? AND sector_id=?')->execute([$lote,$sid]);
        } else {
            // Si el padre sigue en papelera, restaurar la carpeta o documento en la raíz.
            $pdo->prepare('UPDATE carpetas c LEFT JOIN carpetas p ON p.id=c.carpeta_padre_id SET c.carpeta_padre_id=NULL WHERE c.eliminado_lote=? AND c.sector_id=? AND p.activa=0 AND COALESCE(p.eliminado_lote,\'\')<>?')->execute([$lote,$sid,$lote]);
            $pdo->prepare('UPDATE carpetas SET activa=1, eliminado_lote=NULL WHERE eliminado_lote=? AND sector_id=?')->execute([$lote,$sid]);
            $pdo->prepare('UPDATE documentos d LEFT JOIN carpetas c ON c.id=d.carpeta_id SET d.carpeta_id=NULL WHERE d.eliminado_lote=? AND d.sector_id=? AND c.activa=0')->execute([$lote,$sid]);
            $pdo->prepare('UPDATE documentos SET activo=1, eliminado_lote=NULL WHERE eliminado_lote=? AND sector_id=?')->execute([$lote,$sid]);
        }
        $pdo->prepare('UPDATE papelera SET restaurado_en=NOW() WHERE id=?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
