<?php
// ==========================================
// Envio de mails (SMTP simple, sin librerias externas)
// Configuracion en .env:
//   MAIL_TRANSPORT = smtp | log | off   (log = los guarda en logs/mail.log)
//   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE (tls | ssl | vacio)
//   MAIL_FROM, MAIL_FROM_NAME, APP_URL (ej: http://localhost:8080)
// En Docker viene Mailpit: todos los mails se ven en http://localhost:8025
// ==========================================

function mail_config(): array {
    $e = fn(string $k, string $d = '') => (string)(getenv($k) !== false && getenv($k) !== '' ? getenv($k) : $d);
    return [
        'transport' => strtolower($e('MAIL_TRANSPORT', 'log')),
        'host'      => $e('SMTP_HOST', 'mailpit'),
        'port'      => (int)$e('SMTP_PORT', '1025'),
        'user'      => $e('SMTP_USER'),
        'pass'      => $e('SMTP_PASS'),
        'secure'    => strtolower($e('SMTP_SECURE')),
        'from'      => $e('MAIL_FROM', 'sgi@gruponaser.com.ar'),
        'from_name' => $e('MAIL_FROM_NAME', 'NASER SGI'),
    ];
}

/** URL absoluta para los links de los mails. */
function app_absolute_url(string $path): string {
    $base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    return $base . app_url($path);
}

function mail_log(string $linea): void {
    $dir = dirname(__DIR__, 2) . '/logs';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . '] ' . $linea . "\n", FILE_APPEND);
    }
}

/**
 * Envia un mail HTML. Devuelve true si se envio (o se registro en el log).
 * Nunca corta la ejecucion: si falla, lo deja en logs/mail.log.
 */
function enviar_mail(string $para, string $asunto, string $html): bool {
    $c = mail_config();
    if ($c['transport'] === 'off' || !filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    if ($c['transport'] === 'log') {
        mail_log("PARA: $para | ASUNTO: $asunto (MAIL_TRANSPORT=log: no se envio)");
        return true;
    }
    try {
        smtp_send($c, $para, $asunto, $html);
        return true;
    } catch (Throwable $e) {
        mail_log("ERROR enviando a $para: " . $e->getMessage());
        error_log('[NASER] mail: ' . $e->getMessage());
        return false;
    }
}

function smtp_send(array $c, string $para, string $asunto, string $html): void {
    $host = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'];
    $fp = @stream_socket_client($host . ':' . $c['port'], $errno, $errstr, 10);
    if (!$fp) throw new RuntimeException("No se pudo conectar a {$c['host']}:{$c['port']} ($errstr)");
    stream_set_timeout($fp, 15);
    $leer = function () use ($fp): string {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $resp;
    };
    $cmd = function (string $linea, array $ok) use ($fp, $leer): string {
        if ($linea !== '') fwrite($fp, $linea . "\r\n");
        $r = $leer();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP: ' . trim($r) . ' (en: ' . explode(' ', $linea)[0] . ')');
        return $r;
    };
    $dominio = gethostname() ?: 'naser.local';
    $cmd('', [220]);
    $cmd('EHLO ' . $dominio, [250]);
    if ($c['secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('No se pudo iniciar TLS');
        $cmd('EHLO ' . $dominio, [250]);
    }
    if ($c['user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $cmd('MAIL FROM:<' . $c['from'] . '>', [250]);
    $cmd('RCPT TO:<' . $para . '>', [250, 251]);
    $cmd('DATA', [354]);
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc($c['from_name']) . ' <' . $c['from'] . '>',
        'To: <' . $para . '>',
        'Subject: ' . $enc($asunto),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html));
    fwrite($fp, $body . "\r\n.\r\n");
    $cmd('', [250]);
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

/** Plantilla HTML simple para los mails del sistema. */
function mail_plantilla(string $titulo, string $mensajeHtml, ?string $url = null, string $boton = 'Abrir en NASER SGI'): string {
    $btn = $url ? '<p style="margin:24px 0"><a href="' . htmlspecialchars($url, ENT_QUOTES) . '" style="background:#08783e;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold">' . htmlspecialchars($boton) . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f3f6f4;font-family:Segoe UI,Arial,sans-serif;color:#1f2a24">'
        . '<div style="max-width:560px;margin:24px auto;background:#fff;border:1px solid #dfe7e1;border-radius:14px;overflow:hidden">'
        . '<div style="background:#123f28;color:#fff;padding:16px 22px;font-weight:bold;letter-spacing:.5px">NASER SGI</div>'
        . '<div style="padding:22px"><h2 style="margin:0 0 10px;color:#164c2d;font-size:19px">' . htmlspecialchars($titulo) . '</h2>'
        . '<div style="font-size:14px;line-height:1.5">' . $mensajeHtml . '</div>' . $btn
        . '<p style="color:#7b8a83;font-size:11px;margin-top:22px">Mensaje automático del Sistema de Gestión Integrado de NASER. Podés desactivar los mails desde "Mi perfil".</p></div></div></body></html>';
}
