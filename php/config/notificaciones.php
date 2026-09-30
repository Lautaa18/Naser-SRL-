<?php
// ==========================================
// Notificaciones: aviso dentro del sistema (campanita) + mail
// ==========================================
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/empleados.php';

/**
 * Crea una notificacion para cada usuario y le manda un mail.
 * $clave (opcional) evita avisar dos veces lo mismo al mismo usuario.
 */
function notificar(PDO $pdo, array $usuarioIds, string $titulo, string $mensaje = '', ?string $url = null, string $tipo = 'info', ?string $clave = null, bool $conMail = true): int {
    $usuarioIds = array_values(array_unique(array_filter(array_map('intval', $usuarioIds))));
    if (!$usuarioIds) return 0;
    $ins = $pdo->prepare('INSERT IGNORE INTO notificaciones (usuario_id, tipo, titulo, mensaje, url, clave) VALUES (?,?,?,?,?,?)');
    $ph = implode(',', array_fill(0, count($usuarioIds), '?'));
    $st = $pdo->prepare("SELECT id, nombre, email, recibe_mails FROM usuarios WHERE activo = 1 AND id IN ($ph)");
    $st->execute($usuarioIds);
    $enviados = 0;
    foreach ($st->fetchAll() as $u) {
        $ins->execute([(int)$u['id'], $tipo, mb_substr($titulo, 0, 200), $mensaje, $url, $clave]);
        if ($ins->rowCount() === 0) continue; // ya estaba avisado (misma clave)
        $notifId = (int)$pdo->lastInsertId();
        $enviados++;
        if ($conMail && (int)$u['recibe_mails'] === 1) {
            $html = mail_plantilla($titulo, '<p>Hola ' . htmlspecialchars(explode(' ', $u['nombre'])[0]) . ',</p><p>' . nl2br(htmlspecialchars($mensaje)) . '</p>', $url ? app_absolute_url($url) : null);
            if (enviar_mail($u['email'], $titulo, $html)) {
                $pdo->prepare('UPDATE notificaciones SET mail_enviado = 1 WHERE id = ?')->execute([$notifId]);
            }
        }
    }
    return $enviados;
}

function notificacionesSinLeer(PDO $pdo, int $usuarioId): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
    $st->execute([$usuarioId]);
    return (int)$st->fetchColumn();
}

/* ==========================================================
   TAREA DIARIA: avisos de vencimientos
   Se ejecuta sola una vez por dia (la primera visita del dia),
   no hace falta configurar cron.
   ========================================================== */
function tareasDiarias(PDO $pdo): void {
    $hoy = date('Y-m-d');
    try {
        $ultima = $pdo->query("SELECT valor FROM sistema_config WHERE clave = 'tareas_diarias'")->fetchColumn();
        if ($ultima === $hoy) return;
        $pdo->prepare("INSERT INTO sistema_config (clave, valor) VALUES ('tareas_diarias', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$hoy]);
    } catch (Throwable $e) { return; }

    try {
        foreach (vencimientosProximos($pdo, 15) as $v) {
            $dias = (int)$v['dias'];
            if ($dias < -30) continue; // lo vencido hace mucho no se vuelve a avisar
            $cuando = $dias < 0 ? 'venció hace ' . abs($dias) . ' día(s)' : ($dias === 0 ? 'vence HOY' : "vence en $dias día(s)");
            // Un aviso a los 15 dias, otro a los 3 y otro el dia del vencimiento / vencido
            $tramo = $dias < 0 ? 'vencido' : ($dias === 0 ? 'hoy' : ($dias <= 3 ? '3d' : '15d'));
            $ids = $v['sector_id'] ? array_column(usuariosConRol($pdo, (int)$v['sector_id'], 'responsable'), 'id') : [];
            if (!empty($v['sector_extra'])) $ids = array_merge($ids, array_column(usuariosConRol($pdo, (int)$v['sector_extra'], 'responsable'), 'id'));
            notificar($pdo, $ids, "Vencimiento: {$v['titulo']}", "{$v['detalle']} — $cuando ({$v['fecha']}).", $v['url'], 'vencimiento', "venc:{$v['origen']}:{$v['id']}:{$v['fecha']}:$tramo");
        }
    } catch (Throwable $e) {
        error_log('[NASER] tareas diarias: ' . $e->getMessage());
    }
}

/** Junta vencimientos de RRHH, Finanzas y Documentos en una sola lista. */
function vencimientosProximos(PDO $pdo, int $dias = 30, ?array $sectorIds = null): array {
    $out = [];
    $lim = date('Y-m-d', strtotime("+$dias days"));
    $q = function (string $sql, array $p) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(); } catch (Throwable $e) { return []; } };
    foreach ($q("SELECT id, sector_id, empleado_nombre, tipo, fecha_vencimiento FROM rrhh_vencimientos WHERE fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ?", [$lim]) as $r)
        $out[] = ['origen' => 'rrhh', 'id' => $r['id'], 'sector_id' => $r['sector_id'], 'titulo' => $r['tipo'] ?: 'Vencimiento RRHH', 'detalle' => $r['empleado_nombre'], 'fecha' => $r['fecha_vencimiento'], 'url' => '/php/rrhh.php'];
    foreach ($q("SELECT id, sector_id, nombre_completo, vencimiento_carnet, vencimiento_defensivo FROM finanzas_trabajadores", []) as $r) {
        if ($r['vencimiento_carnet'] && $r['vencimiento_carnet'] <= $lim)
            $out[] = ['origen' => 'fin-carnet', 'id' => $r['id'], 'sector_id' => $r['sector_id'], 'titulo' => 'Carnet de conducir', 'detalle' => $r['nombre_completo'], 'fecha' => $r['vencimiento_carnet'], 'url' => '/php/finanzas.php'];
        if ($r['vencimiento_defensivo'] && $r['vencimiento_defensivo'] <= $lim)
            $out[] = ['origen' => 'fin-defensivo', 'id' => $r['id'], 'sector_id' => $r['sector_id'], 'titulo' => 'Curso de manejo defensivo', 'detalle' => $r['nombre_completo'], 'fecha' => $r['vencimiento_defensivo'], 'url' => '/php/finanzas.php'];
    }
    // Personal: licencias, cursos, examenes medicos (avisa a RRHH y al sector del empleado)
    $rrhhId = null; foreach (sectoresInfo($pdo) as $s) if ($s['slug'] === 'rrhh') $rrhhId = (int)$s['id'];
    $tiposHab = function_exists('tiposHabilitacion') ? tiposHabilitacion() : [];
    foreach ($q("SELECT h.id, h.tipo, h.detalle, h.fecha_vencimiento, e.id AS emp_id, e.apellido_nombre, e.sector_id FROM empleados_habilitaciones h JOIN empleados e ON e.id = h.empleado_id
                 WHERE e.activo = 1 AND h.fecha_vencimiento IS NOT NULL AND h.fecha_vencimiento <= ?", [$lim]) as $r)
        $out[] = ['origen' => 'emp', 'id' => $r['id'], 'sector_id' => $rrhhId, 'sector_extra' => $r['sector_id'] ? (int)$r['sector_id'] : null,
                  'titulo' => $tiposHab[$r['tipo']] ?? ucfirst(str_replace('_', ' ', $r['tipo'])), 'detalle' => $r['apellido_nombre'], 'fecha' => $r['fecha_vencimiento'], 'url' => '/php/personal.php?id=' . $r['emp_id']];
    foreach ($q("SELECT id, sector_id, titulo, fecha_vencimiento FROM documentos WHERE activo = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ?", [$lim]) as $r)
        $out[] = ['origen' => 'doc', 'id' => $r['id'], 'sector_id' => $r['sector_id'], 'titulo' => 'Documento', 'detalle' => $r['titulo'], 'fecha' => $r['fecha_vencimiento'], 'url' => '/php/buscar.php?q=' . rawurlencode($r['titulo'])];
    $hoy = new DateTimeImmutable('today');
    foreach ($out as &$v) $v['dias'] = (int)$hoy->diff(new DateTimeImmutable($v['fecha']))->format('%r%a');
    unset($v);
    // Lo vencido hace mas de 90 dias no se muestra (ya se aviso en su momento)
    $out = array_values(array_filter($out, fn($v) => $v['dias'] >= -90));
    if ($sectorIds !== null) $out = array_values(array_filter($out, fn($v) => in_array((int)$v['sector_id'], $sectorIds, true) || in_array((int)($v['sector_extra'] ?? 0), $sectorIds, true)));
    usort($out, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $out;
}
