<?php
/** Mensajeria interna: cantidad de mensajes sin leer del usuario (a sus sectores o a el en persona). Nunca rompe el menu. */
function mensajesSinLeer(PDO $pdo, int $uid): int {
    try {
        if (!$uid) return 0;
        $sec = array_map('intval', array_keys(misRolesSector($pdo)));
        $cond = 'm.para_usuario_id = ?';
        $params = [$uid];
        if ($sec) {
            $cond .= ' OR (m.para_usuario_id IS NULL AND m.para_sector_id IN (' . implode(',', array_fill(0, count($sec), '?')) . '))';
            $params = array_merge($params, $sec);
        }
        $st = $pdo->prepare("SELECT COUNT(*) FROM mensajes m WHERE ($cond) AND m.de_usuario_id <> ?
                             AND NOT EXISTS (SELECT 1 FROM mensajes_leidos l WHERE l.mensaje_id = m.id AND l.usuario_id = ?)");
        $st->execute(array_merge($params, [$uid, $uid]));
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
