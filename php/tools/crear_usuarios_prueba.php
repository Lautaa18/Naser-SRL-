<?php
// ==========================================
// Crea cuentas de PRUEBA, una por cada tipo de visualizacion (desde la terminal):
//
//   docker compose exec web php php/tools/crear_usuarios_prueba.php
//
// Las cuentas son @naser.test y reciben una contraseña al azar que se muestra en pantalla
// y se guarda en backups/usuarios_prueba_FECHA.csv (carpeta que no se sube a GitHub).
// Si ya existen, se les vuelve a dejar el mismo rol y se genera una contraseña nueva.
//
// Cuando termines de probar, desactivalas:
//   docker compose exec web php php/tools/crear_usuarios.php --desactivar-prueba
// ==========================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/estructura_naser.php';

// email => [nombre, descripcion, [sector => rol, ...]]
$cuentas = [];
foreach (['finanzas' => 'Finanzas', 'rrhh' => 'Recursos Humanos', 'compras' => 'Compras', 'ventas' => 'Ventas', 'gerencia' => 'Gerencia',
          'mantenimiento' => 'Mantenimiento', 'hseq' => 'HSEQ', 'operaciones' => 'Operaciones'] as $slug => $nombre) {
    $cuentas["prueba.responsable.$slug@naser.test"] = ["Prueba Responsable $nombre", "Responsable de $nombre", [$slug => 'responsable']];
}
$cuentas['prueba.operador@naser.test'] = ['Prueba Operador', 'Operador de Operaciones (ve solo Operaciones, Mantenimiento, HSEQ y SGI)', ['operaciones' => 'operador']];
$cuentas['prueba.observador@naser.test'] = ['Prueba Observador', 'Observador (mira Finanzas, RRHH, Ventas y Operaciones sin gestionar)',
    ['finanzas' => 'observador', 'rrhh' => 'observador', 'ventas' => 'observador', 'operaciones' => 'observador']];
$cuentas['prueba.superusuario@naser.test'] = ['Prueba Super Usuario', 'Super usuario (ve y gestiona todo)', [], true];
$cuentas['prueba.sinrol@naser.test'] = ['Prueba Sin Rol', 'Usuario sin sector asignado', []];

$sectores = [];
foreach ($pdo->query('SELECT id, slug FROM sectores')->fetchAll() as $s) $sectores[$s['slug']] = (int)$s['id'];

$salida = [];
foreach ($cuentas as $email => $c) {
    [$nombre, $desc, $roles] = $c;
    $rolUsuario = !empty($c[3]) ? 'admin' : 'usuario';
    $pass = passwordTemporal();
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ?');
    $st->execute([$email]);
    $id = (int)$st->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE usuarios SET nombre = ?, password = ?, rol = ?, activo = 1, debe_cambiar_password = 0 WHERE id = ?")->execute([$nombre, $hash, $rolUsuario, $id]);
    } else {
        $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo, debe_cambiar_password) VALUES (?,?,?,?,1,0)")->execute([$nombre, $email, $hash, $rolUsuario]);
        $id = (int)$pdo->lastInsertId();
    }
    // Dejar exactamente los roles definidos para esta cuenta
    $st = $pdo->prepare('SELECT sector_id FROM usuario_sector WHERE usuario_id = ?');
    $st->execute([$id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        if (!in_array((int)$sid, array_map(fn($s) => $sectores[$s] ?? 0, array_keys($roles)), true)) asignarRolSector($pdo, $id, (int)$sid, null);
    }
    foreach ($roles as $slug => $rol) {
        if (isset($sectores[$slug])) asignarRolSector($pdo, $id, $sectores[$slug], $rol);
        else echo "  ! No existe el sector '$slug'.\n";
    }
    $salida[] = [$nombre, $email, $pass, $desc];
}

$w = max(array_map(fn($c) => strlen($c[1]), $salida));
echo "\n" . str_pad('USUARIO', $w + 2) . str_pad('CONTRASEÑA', 14) . "PERMISOS\n";
foreach ($salida as $c) echo str_pad($c[1], $w + 2) . str_pad($c[2], 14) . $c[3] . "\n";

$dir = dirname(__DIR__, 2) . '/backups';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$archivo = $dir . '/usuarios_prueba_' . date('Y-m-d_His') . '.csv';
if ($fp = @fopen($archivo, 'w')) {
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Nombre', 'Usuario', 'Contraseña', 'Permisos'], ';', '"', '\\');
    foreach ($salida as $c) fputcsv($fp, [$c[0], $c[1], $c[2], $c[3]], ';', '"', '\\');
    fclose($fp);
    echo "\nGuardado en: backups/" . basename($archivo) . "\n";
}
echo "Son cuentas de prueba: desactivalas al terminar con  php php/tools/crear_usuarios.php --desactivar-prueba\n";
