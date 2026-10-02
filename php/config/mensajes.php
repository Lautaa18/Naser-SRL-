<?php
/** Mensajeria entre sectores: cantidad de mensajes sin leer del usuario (nunca rompe el menu). */
function mensajesSinLeer(PDO $pdo, int $uid): int {
    try {
        $sec = array_map('intval', array_keys(misRolesSector($pdo)));
        if (!$sec || !$uid) return 0;
        $ph = implode(',', array_fill(0, count($sec), '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM mensajes m WHERE m.para_sector_id IN ($ph) AND m.de_usuario_id <> ?
                             AND NOT EXISTS (SELECT 1 FROM mensajes_leidos l WHERE l.mensaje_id = m.id AND l.usuario_id = ?)");
        $st->execute(array_merge($sec, [$uid, $uid]));
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
