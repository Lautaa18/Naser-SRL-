<?php
// ==========================================
// Crea todos los usuarios de NASER con sus permisos (desde la terminal)
//
//   docker compose exec web php php/tools/crear_usuarios.php
//
// Opciones:
//   --resetear-claves     genera contraseña temporal nueva para TODAS las personas de la estructura
//   --desactivar-prueba   desactiva las cuentas de prueba @naser.test (menos admin@naser.test)
//
// Las contraseñas se muestran en pantalla y se guardan en backups/usuarios_iniciales_FECHA.csv
// (esa carpeta no se sube a GitHub ni se puede abrir desde el navegador).
// ==========================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/estructura_naser.php';

$opts = array_slice($argv, 1);
$resetear = in_array('--resetear-claves', $opts, true);
$desactivar = in_array('--desactivar-prueba', $opts, true);

$inf = aplicarEstructuraNaser($pdo, false, false, false);
$credenciales = $inf['creados'];

if ($resetear) {
    $ya = array_column($credenciales, 'email');
    foreach (estructuraNaser()['personas'] as $clave => $p) {
        $email = strtolower($p[2] ?? emailNaser($clave));
        if (in_array($email, $ya, true)) continue;
        $pass = passwordTemporal();
        $st = $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 1 WHERE LOWER(email) = ?');
        $st->execute([password_hash($pass, PASSWORD_DEFAULT), $email]);
        if ($st->rowCount()) $credenciales[] = ['nombre' => $p[0], 'email' => $email, 'password' => $pass];
    }
}
if ($desactivar) {
    $n = $pdo->exec("UPDATE usuarios SET activo = 0 WHERE email LIKE '%@naser.test' AND email <> 'admin@naser.test'");
    echo "Cuentas de prueba desactivadas: $n\n";
}

echo "\nUsuarios creados: " . count($inf['creados']) . " · actualizados: {$inf['actualizados']}\n";
foreach ($inf['avisos'] as $a) echo "  ! $a\n";

if (!$credenciales) {
    echo "\nNo hay contraseñas nuevas (los usuarios ya existían). Para generar nuevas: --resetear-claves\n";
    exit(0);
}

// Tabla en pantalla
$w = max(array_map(fn($c) => mb_strlen($c['nombre']), $credenciales));
$we = max(array_map(fn($c) => strlen($c['email']), $credenciales));
echo "\n" . str_pad('NOMBRE', $w + 2) . str_pad('USUARIO (MAIL)', $we + 2) . "CONTRASEÑA TEMPORAL\n";
foreach ($credenciales as $c) {
    echo $c['nombre'] . str_repeat(' ', $w + 2 - mb_strlen($c['nombre'])) . str_pad($c['email'], $we + 2) . $c['password'] . "\n";
}

// CSV para Excel
$dir = dirname(__DIR__, 2) . '/backups';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$archivo = $dir . '/usuarios_iniciales_' . date('Y-m-d_His') . '.csv';
$fp = @fopen($archivo, 'w');
if ($fp) {
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Nombre y apellido', 'Usuario (mail)', 'Contraseña temporal', 'Ingreso'], ';', '"', '\\');
    $url = rtrim((string)(getenv('APP_URL') ?: 'http://localhost:8080'), '/') . '/php/login.php';
    foreach ($credenciales as $c) fputcsv($fp, [$c['nombre'], $c['email'], $c['password'], $url], ';', '"', '\\');
    fclose($fp);
    echo "\nGuardado en: backups/" . basename($archivo) . "  (abrilo con Excel)\n";
}
echo "Cada persona tiene que cambiar la contraseña la primera vez que entra.\n";
