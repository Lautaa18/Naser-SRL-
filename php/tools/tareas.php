<?php
/** Ejecutor de avisos independiente del navegador. Nunca se expone por HTTP. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$unaVez = in_array('--once', $argv, true);
$intervalo = max(60, (int)(getenv('TAREAS_INTERVALO') ?: 300));
if (!$unaVez) {
    // Cada revisión usa un proceso nuevo: permisos, sectores y esquema no quedan cacheados.
    while (true) {
        $proceso = proc_open([PHP_BINARY, __FILE__, '--once'], [0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>STDOUT,2=>STDERR], $pipes);
        if (is_resource($proceso)) proc_close($proceso);
        else fwrite(STDERR, "No se pudo iniciar la revisión de avisos.\n");
        sleep($intervalo);
    }
}
require_once __DIR__ . '/../config/app.php';
// La salida CLI siempre informa fallo, incluso si no puede escribir su propio estado.
set_exception_handler(static function(Throwable $e): void {
    error_log('[NASER tareas] '.$e->getMessage());
    fwrite(STDERR,"No se pudo completar el servicio de avisos. Revisá sus permisos y el registro de errores.\n");
    exit(1);
});
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/notificaciones.php';
require_once __DIR__ . '/../config/proteccion.php';
$dir = dirname(__DIR__,2) . '/logs/estado';
if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar el registro de tareas.');
function latidoTareas(string $dir, ?string $error = null): void {
    $json=json_encode(['hora'=>time(),'error'=>$error], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $tmp=$dir.'/tareas-latido.json.tmp';
    if (file_put_contents($tmp,$json,LOCK_EX)===false || !rename($tmp,$dir.'/tareas-latido.json')) throw new RuntimeException('No se pudo actualizar la señal de tareas.');
}
do {
    $pdo=null;
    try {
        latidoTareas($dir);
        $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'db').';dbname='.(getenv('DB_NAME')?:'naser_sgi_prueba').';charset=utf8mb4',getenv('DB_USER')?:'naser',getenv('DB_PASS')?:'naser2026',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        configurarZonaHorariaDB($pdo);
        naser_bootstrap($pdo);
        $pdo->exec('SET @naser_actor=0');
        $pdo->exec("INSERT INTO sistema_tareas(nombre,iniciado_en) VALUES('avisos',NOW()) ON DUPLICATE KEY UPDATE iniciado_en=NOW()");
        $errorDiario=null;
        try { tareasDiarias($pdo,true); } catch (Throwable $e) { $errorDiario=$e; }
        $correos=procesarCorreosPendientes($pdo,static fn()=>latidoTareas($dir));
        if ($errorDiario) throw $errorDiario;
        if ($correos['fallidos']>0) throw new RuntimeException('Hay correos pendientes que no pudieron enviarse.');
        $dia=$pdo->query("SELECT valor FROM sistema_config WHERE clave='tareas_diarias'")->fetchColumn();
        if ($dia!==date('Y-m-d')) throw new RuntimeException('Los avisos del día no terminaron; se reintentará en la próxima revisión.');
        $pdo->exec("UPDATE sistema_tareas SET completado_en=NOW(),error=NULL WHERE nombre='avisos'");
        latidoTareas($dir);
        fwrite(STDOUT,'['.date('c')."] Revisión de avisos completa.\n");
    } catch (Throwable $e) {
        // No persistir detalles de conexión, usuarios o secretos de los proveedores.
        $mensaje=$pdo ? 'No se completó la revisión de avisos. Revisá el registro de errores del sistema.' : 'No se pudo conectar el servicio de avisos con su base de datos.';
        error_log('[NASER tareas] '.$e->getMessage());
        if ($pdo) try { $st=$pdo->prepare("INSERT INTO sistema_tareas(nombre,iniciado_en,error) VALUES('avisos',NOW(),?) ON DUPLICATE KEY UPDATE error=VALUES(error)");$st->execute([$mensaje]); } catch (Throwable $ignorar) {}
        latidoTareas($dir,$mensaje);
        fwrite(STDERR,$mensaje."\n");
        if ($unaVez) exit(1);
    }
    $pdo=null;
    if (!$unaVez) sleep($intervalo);
} while (!$unaVez);
