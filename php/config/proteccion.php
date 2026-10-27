<?php
/** Estado de protección: solo metadatos, nunca credenciales ni contenido de copias. */
function leerEstadoProteccion(string $nombre, ?string $base = null): ?array {
    $base ??= dirname(__DIR__, 2) . '/logs/estado';
    if (!preg_match('/^[a-z-]+\.json$/', $nombre)) return null;
    $archivo = $base . '/' . $nombre;
    if (!is_file($archivo) || filesize($archivo) > 65536) return null;
    $s = json_decode((string)file_get_contents($archivo), true);
    return is_array($s) ? $s : null;
}
function fechaProteccion(?int $hora): string {
    return $hora ? date('d/m/Y H:i', $hora) : 'Sin registro';
}
function estadoProteccion(PDO $pdo, ?string $estadoDir = null, ?string $backupDir = null): array {
    $dir = $backupDir ?? dirname(__DIR__, 2) . '/backups';
    $archivos = glob($dir . '/db_????-??-??.sql.gz') ?: [];
    sort($archivos, SORT_STRING);
    $ultimo = $archivos ? $archivos[count($archivos)-1] : null;
    $latidoBackup = leerEstadoProteccion('backup-latido.json', $estadoDir);
    $intentoBackup = leerEstadoProteccion('backup-intento.json', $estadoDir);
    $exitoBackup = leerEstadoProteccion('backup-exito.json', $estadoDir);
    $latidoVerificador = leerEstadoProteccion('verificador-latido.json', $estadoDir);
    $verificacion = leerEstadoProteccion('verificacion-intento.json', $estadoDir);
    $exitoVerificacion = leerEstadoProteccion('verificacion-exito.json', $estadoDir);
    $latidoTareas = leerEstadoProteccion('tareas-latido.json', $estadoDir);
    $ahora = time();
    $latidoValido = static fn(?array $s, int $max): bool => isset($s['hora']) && (int)$s['hora'] <= $ahora+60 && (int)$s['hora'] >= $ahora-$max;
    $tarea = $pdo->query("SELECT * FROM sistema_tareas WHERE nombre='avisos'")->fetch() ?: null;
    $latidoTareasHora = isset($latidoTareas['hora']) ? (int)$latidoTareas['hora'] : null;
    $tareaActiva = $latidoValido($latidoTareas,900);
    $pendientes = (int)$pdo->query("SELECT COUNT(*) FROM notificaciones n JOIN usuarios u ON u.id=n.usuario_id WHERE (n.tipo='vencimiento' OR n.clave LIKE 'form:%') AND n.mail_enviado=0 AND u.activo=1 AND u.recibe_mails=1")->fetchColumn();
    $transporte = function_exists('mail_config') ? mail_config()['transport'] : 'desconocido';
    $recientes = $pdo->query("SELECT n.id,n.titulo,n.creado_en,n.mail_enviado,n.mail_intentado_en,n.mail_enviado_en,n.mail_error,n.mail_transporte,u.nombre destinatario,u.activo,u.recibe_mails FROM notificaciones n JOIN usuarios u ON u.id=n.usuario_id WHERE n.tipo='vencimiento' OR n.clave LIKE 'form:%' ORDER BY n.id DESC LIMIT 50")->fetchAll();
    $fallidos = (int)$pdo->query("SELECT COUNT(*) FROM notificaciones n JOIN usuarios u ON u.id=n.usuario_id WHERE n.mail_enviado=0 AND n.mail_error IS NOT NULL AND u.activo=1 AND u.recibe_mails=1 AND (n.tipo='vencimiento' OR n.clave LIKE 'form:%')")->fetchColumn();
    $backupActivo = $latidoValido($latidoBackup,900);
    $verificadorActivo = $latidoValido($latidoVerificador,900);
    // La fecha y el nombre no prueban que se haya restaurado ese mismo contenido.
    $hashUltimo = $ultimo ? @hash_file('sha256', $ultimo) : false;
    $corresponde = $ultimo && ($exitoVerificacion['estado']??'') === 'correcto'
        && ($exitoVerificacion['archivo']??'') === basename($ultimo)
        && (int)($exitoVerificacion['hora']??0) >= (int)filemtime($ultimo)
        && (int)($exitoVerificacion['hora']??0) <= $ahora+60
        && is_string($hashUltimo) && hash_equals($hashUltimo, (string)($exitoVerificacion['sha256']??''));
    $completada = !empty($tarea['completado_en']) ? strtotime($tarea['completado_en']) : false;
    $nivel = 'ok'; $razones = [];
    if (!$backupActivo) $razones[]='El servicio de copias no tiene una señal reciente.';
    if (!$ultimo) $razones[]='Todavía no hay una copia automática de la base.';
    elseif (filemtime($ultimo) < $ahora-36*3600) $razones[]='La última copia tiene más de 36 horas.';
    if (!$ultimo || ($exitoBackup['estado']??'') !== 'correcto' || ($exitoBackup['archivo']??'') !== basename($ultimo)
        || (int)($exitoBackup['hora']??0) < (int)filemtime($ultimo) || (int)($exitoBackup['hora']??0) > $ahora+60)
        $razones[]='La última copia todavía no tiene un respaldo completo confirmado.';
    if (($intentoBackup['estado']??'')==='error') { $nivel='error'; $razones[]='Falló el último intento de respaldo.'; }
    if (($intentoBackup['estado']??'')==='en_curso') $razones[]='Hay una copia de seguridad en curso.';
    if (!$verificadorActivo) $razones[]='El verificador de restauración no tiene una señal reciente.';
    if (!$corresponde) $razones[]='La copia más reciente todavía no tiene restauración comprobada.';
    if (($verificacion['estado']??'')==='error') { $nivel='error'; $razones[]='Falló la última prueba de restauración.'; }
    if (($verificacion['estado']??'')==='en_curso') $razones[]='Hay una prueba de restauración en curso.';
    if (!$tareaActiva) $razones[]='El servicio de avisos no tiene una señal reciente.';
    if (!$completada || $completada < $ahora-900 || $completada > $ahora+60) $razones[]='No hay una revisión de avisos completada en los últimos 15 minutos.';
    if (!empty($tarea['error']) || !empty($latidoTareas['error'])) { $nivel='error'; $razones[]='Los avisos registraron un error pendiente.'; }
    if ($fallidos>0 && $transporte!=='off') { $nivel='error'; $razones[]='Hay correos que fallaron y necesitan reintento.'; }
    if ($transporte==='smtp' && $pendientes>0) $razones[]='Hay avisos de vencimiento sin envío confirmado.';
    if ($razones && $nivel==='ok') $nivel='aviso';
    return [
        'backup'=>['ultimo_archivo'=>$ultimo?basename($ultimo):null,'ultimo_backup'=>$ultimo?(int)filemtime($ultimo):null,
            'ultimo_intento'=>$intentoBackup,'ultimo_exito'=>$exitoBackup,'latido'=>isset($latidoBackup['hora'])?(int)$latidoBackup['hora']:null,
            'cantidad'=>count($archivos),'retencion_dias'=>(int)($latidoBackup['retencion_dias']??7),'activo'=>$backupActivo],
        'verificacion'=>['ultimo_resultado'=>$verificacion,'ultimo_exito'=>$exitoVerificacion,
            'latido'=>isset($latidoVerificador['hora'])?(int)$latidoVerificador['hora']:null,'activo'=>$verificadorActivo,'corresponde_ultimo'=>(bool)$corresponde],
        'tareas'=>['fila'=>$tarea,'latido'=>$latidoTareasHora,'activo'=>$tareaActiva,'estado_archivo'=>$latidoTareas],
        'correo'=>['transporte'=>$transporte,'pendientes'=>$pendientes,'fallidos'=>$fallidos,'recientes'=>$recientes],
        'resumen'=>['nivel'=>$nivel,'texto'=>$razones?implode(' ',$razones):'Copias recientes, restauración comprobada y servicios con señal reciente.'],
    ];
}

/** Reintenta un correo ya registrado, sin crear otra notificación. */
function enviarCorreoPendiente(PDO $pdo, int $id, bool $propagar = false): bool {
    $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $lock='naser_mail_'.substr(hash('sha256',$db),0,20).'_'.$id;
    $st=$pdo->prepare('SELECT GET_LOCK(?,0)');$st->execute([$lock]);
    if ((int)$st->fetchColumn()!==1) {
        if ($propagar) throw new RuntimeException('El correo está siendo procesado; se reintentará.');
        return false;
    }
    try {
        $st=$pdo->prepare('SELECT n.*,u.nombre,u.email,u.activo,u.recibe_mails FROM notificaciones n JOIN usuarios u ON u.id=n.usuario_id WHERE n.id=?');
        $st->execute([$id]);$n=$st->fetch();
        if (!$n || (int)$n['mail_enviado']===1 || !(int)$n['activo'] || !(int)$n['recibe_mails']) return true;
        $transporte=mail_config()['transport'];
        if ($transporte==='off') return true;
        $pdo->prepare('UPDATE notificaciones SET mail_intentado_en=NOW(),mail_transporte=?,mail_error=NULL WHERE id=?')->execute([$transporte,$id]);
        $html=mail_plantilla($n['titulo'],'<p>Hola '.htmlspecialchars(explode(' ',$n['nombre'])[0],ENT_QUOTES,'UTF-8').',</p><p>'.nl2br(htmlspecialchars((string)$n['mensaje'],ENT_QUOTES,'UTF-8')).'</p>',$n['url']?app_absolute_url($n['url']):null);
        if (!enviar_mail($n['email'],$n['titulo'],$html)) {
            $pdo->prepare('UPDATE notificaciones SET mail_error=? WHERE id=?')->execute(['No se pudo enviar el correo; se reintentará.',$id]);
            if ($propagar) throw new RuntimeException('No se pudo enviar el correo; se reintentará.');
            return false;
        }
        $pdo->prepare('UPDATE notificaciones SET mail_enviado=1,mail_enviado_en=NOW(),mail_error=NULL WHERE id=?')->execute([$id]);
        return true;
    } finally {
        $st=$pdo->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$lock]);
    }
}
/** Solo cola de vencimientos y formularios; otros avisos pueden haberse creado sin solicitar correo. */
function procesarCorreosPendientes(PDO $pdo, ?callable $progreso=null): array {
    if (mail_config()['transport']==='off') return ['enviados'=>0,'fallidos'=>0];
    $ids=$pdo->query("SELECT n.id FROM notificaciones n JOIN usuarios u ON u.id=n.usuario_id WHERE n.mail_enviado=0 AND u.activo=1 AND u.recibe_mails=1 AND (n.tipo='vencimiento' OR n.clave LIKE 'form:%') ORDER BY n.id LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
    $r=['enviados'=>0,'fallidos'=>0,'ocupados'=>0];
    foreach($ids as $id) {
        try {$ok=enviarCorreoPendiente($pdo,(int)$id);$r[$ok?'enviados':'fallidos']++;}
        catch(Throwable $e){$r['fallidos']++;error_log('[NASER correo pendiente] '.$e->getMessage());}
        if($progreso)$progreso();
    }
    return $r;
}

function resultadoCorreoProteccion(array $n): string {
    if (!(int)$n['activo']) return 'Destinatario inactivo';
    if (!(int)$n['recibe_mails']) return 'Correo no solicitado';
    if ((int)$n['mail_enviado']===1) return match($n['mail_transporte']??null) {
        'log'=>'Registrado en modo de prueba', 'smtp'=>'Aceptado por el servidor de correo',
        default=>'Confirmado anteriormente; canal sin registro',
    };
    if (($n['mail_transporte']??null)==='off') return 'Envío desactivado';
    if (!empty($n['mail_error'])) return 'Falló; pendiente de reintento';
    return 'Pendiente';
}
