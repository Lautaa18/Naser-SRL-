<?php
// ==========================================
// Funciones comunes de las paginas de modulo (RRHH, Compras, Finanzas...)
// Antes estaban copiadas en cada pagina.
// ==========================================

/**
 * Carga el sector por su slug y verifica que el usuario pueda verlo.
 * Devuelve [$sector, $sid, $canEdit].
 */
function cargarModulo(PDO $pdo, string $slug): array
{
    $st = $pdo->prepare('SELECT id,nombre,slug FROM sectores WHERE slug=? LIMIT 1');
    $st->execute([$slug]);
    $sector = $st->fetch();
    if (!$sector) { http_response_code(404); exit('Sector no encontrado.'); }
    $sid = (int)$sector['id'];
    if (!puedeVerSector($pdo, $sid)) { http_response_code(403); exit('No tenés acceso a este sector.'); }
    return [$sector, $sid, puedeEditarSector($pdo, $sid)];
}

/** Registra una accion en la auditoria (tabla actividad). */
function auditModulo(PDO $pdo, int $uid, string $accion, string $detalle): void
{
    try {
        $st = $pdo->prepare('INSERT INTO actividad(usuario_id,accion,detalle) VALUES(?,?,?)');
        $st->execute([$uid, $accion, mb_substr($detalle, 0, 255)]);
    } catch (Throwable $e) {}
}

/** Corta la ejecucion si el usuario no tiene permiso de edicion. */
function exigirEdicion(bool $canEdit): void
{
    if (!$canEdit) { http_response_code(403); exit('No tenés permiso para modificar este sector.'); }
}
