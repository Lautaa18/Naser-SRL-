<?php
require_once __DIR__ . '/app.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // La sesión dura 8 h sin actividad (por defecto PHP la borra a los 24 min y salía "sesión expirada")
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . app_url('/php/login.php'));
        exit;
    }
    // Primer ingreso o contraseña reseteada: obligar a cambiarla
    if (!empty($_SESSION['debe_cambiar_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['perfil.php', 'logout.php'], true)) {
            header('Location: ' . app_url('/php/perfil.php') . '?primer_ingreso=1');
            exit;
        }
    }
}
/** Super usuario: ve y edita todo, administra usuarios y permisos. */
function esAdmin(): bool { return ($_SESSION['rol'] ?? '') === 'admin'; }
function requireAdmin(): void {
    requireLogin();
    if (!esAdmin()) { http_response_code(403); exit('Acceso denegado.'); }
}

/* ==========================================================
   PERMISOS POR SECTOR
   ----------------------------------------------------------
   Roles por sector (tabla usuario_sector.rol_sector):
     - responsable: edita, gestiona y APRUEBA formularios del sector
     - operador:    ve y completa formularios
     - observador:  solo ve
   Reglas:
     - Todos ven todos los sectores, salvo los "restringidos"
       (hoy ninguno: Operaciones lo ve todo el personal, pero solo su
       equipo completa formularios), que solo ven sus integrantes.
     - Burbuja colaborativa: los responsables de un sector de la
       burbuja pueden editar y completar en todos los sectores de
       esa burbuja, pero solo aprueba el responsable del area.
     - El super usuario (rol admin) puede todo.
   ========================================================== */

/** Datos de todos los sectores (cacheado por request). */
function sectoresInfo(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $rows = $pdo->query('SELECT s.id, s.nombre, s.slug, s.orden, s.burbuja_id, s.restringido, COALESCE(b.colaborativa,0) AS colaborativa, b.nombre AS burbuja
                         FROM sectores s LEFT JOIN burbujas b ON b.id = s.burbuja_id ORDER BY s.orden, s.nombre')->fetchAll();
    foreach ($rows as $r) {
        // El SGI no es un sector de trabajo: es el apartado de documentacion. Asi se muestra en todo el sistema.
        if ($r['slug'] === 'sgi') $r['nombre'] = 'SGI (documentación)';
        $cache[(int)$r['id']] = $r;
    }
    return $cache;
}
/** Roles del usuario logueado: [sector_id => 'responsable'|'operador'|'observador']. */
function misRolesSector(PDO $pdo): array {
    static $cache = [];
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if (isset($cache[$uid])) return $cache[$uid];
    $st = $pdo->prepare('SELECT sector_id, rol_sector FROM usuario_sector WHERE usuario_id = ?');
    $st->execute([$uid]);
    $roles = [];
    foreach ($st->fetchAll() as $r) $roles[(int)$r['sector_id']] = $r['rol_sector'];
    return $cache[$uid] = $roles;
}
function rolEnSector(PDO $pdo, int $sectorId): ?string {
    if (esAdmin()) return 'responsable';
    return misRolesSector($pdo)[$sectorId] ?? null;
}
function nombreRolSector(?string $rol): string {
    return match ($rol) { 'responsable' => 'Responsable', 'operador' => 'Operador', 'observador' => 'Observador', default => 'Sin asignar' };
}
/** ¿Es responsable de algun sector de la misma burbuja colaborativa? */
function esResponsableEnBurbuja(PDO $pdo, int $sectorId): bool {
    $info = sectoresInfo($pdo);
    $s = $info[$sectorId] ?? null;
    if (!$s || !$s['burbuja_id'] || !(int)$s['colaborativa']) return false;
    foreach (misRolesSector($pdo) as $sid => $rol) {
        if ($rol === 'responsable' && isset($info[$sid]) && (int)$info[$sid]['burbuja_id'] === (int)$s['burbuja_id']) return true;
    }
    return false;
}
/** Operador "puro": tiene rol de operador y no es responsable de ningun sector (ni super usuario). */
function esSoloOperador(PDO $pdo): bool {
    if (esAdmin()) return false;
    $roles = array_values(misRolesSector($pdo));
    return in_array('operador', $roles, true) && !in_array('responsable', $roles, true);
}
/** Unicos sectores que ve un operador (ademas del SGI). */
const SECTORES_DE_OPERADOR = ['operaciones', 'mantenimiento', 'hseq'];

function puedeVerSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    $s = sectoresInfo($pdo)[$sectorId] ?? null;
    if (!$s) return false;
    if ($s['slug'] === 'sgi') return true;
    if (esSoloOperador($pdo)) return in_array($s['slug'], SECTORES_DE_OPERADOR, true);
    if ((int)$s['restringido']) return isset(misRolesSector($pdo)[$sectorId]);
    return true;
}
/** Completar formularios: responsable u operador del sector, o responsable de su burbuja colaborativa. */
function puedeCompletarSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    if (!puedeVerSector($pdo, $sectorId)) return false;
    $rol = misRolesSector($pdo)[$sectorId] ?? null;
    return in_array($rol, ['responsable', 'operador'], true) || esResponsableEnBurbuja($pdo, $sectorId);
}
/** Editar / gestionar el modulo del sector (registros, documentos). */
function puedeEditarSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    if (!puedeVerSector($pdo, $sectorId)) return false;
    return (misRolesSector($pdo)[$sectorId] ?? null) === 'responsable' || esResponsableEnBurbuja($pdo, $sectorId);
}
/** Aprobar formularios: SOLO el responsable del area (o el super usuario). */
function puedeAprobarSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    return (misRolesSector($pdo)[$sectorId] ?? null) === 'responsable';
}
function puedeGestionarDocumentos(PDO $pdo): bool {
    if (esAdmin()) return true;
    return in_array('responsable', misRolesSector($pdo), true);
}
function requireDocumentManager(PDO $pdo): void {
    requireLogin();
    if (!puedeGestionarDocumentos($pdo)) { http_response_code(403); exit('No tenés permisos para gestionar documentación.'); }
}
function sectoresVisibles(PDO $pdo): array {
    return array_values(array_filter(sectoresInfo($pdo), fn($s) => puedeVerSector($pdo, (int)$s['id'])));
}
function sectoresEditables(PDO $pdo): array {
    return array_values(array_filter(sectoresInfo($pdo), fn($s) => puedeEditarSector($pdo, (int)$s['id'])));
}
/** Usuarios activos con un rol dado en un sector (para avisos). */
function usuariosConRol(PDO $pdo, int $sectorId, string $rol = 'responsable'): array {
    $st = $pdo->prepare("SELECT u.id, u.nombre, u.email, u.recibe_mails FROM usuario_sector us JOIN usuarios u ON u.id = us.usuario_id
                         WHERE us.sector_id = ? AND us.rol_sector = ? AND u.activo = 1 ORDER BY u.nombre");
    $st->execute([$sectorId, $rol]);
    return $st->fetchAll();
}
/** Guarda el rol de un usuario en un sector (null = quitar). Mantiene puede_ver/puede_editar sincronizados. */
function asignarRolSector(PDO $pdo, int $usuarioId, int $sectorId, ?string $rol): void {
    if ($rol === null || $rol === '') {
        $pdo->prepare('DELETE FROM usuario_sector WHERE usuario_id = ? AND sector_id = ?')->execute([$usuarioId, $sectorId]);
        return;
    }
    if (!in_array($rol, ['responsable', 'operador', 'observador'], true)) throw new InvalidArgumentException('Rol inválido');
    $pdo->prepare('INSERT INTO usuario_sector (usuario_id, sector_id, puede_ver, puede_editar, rol_sector) VALUES (?,?,1,?,?)
                   ON DUPLICATE KEY UPDATE puede_ver = 1, puede_editar = VALUES(puede_editar), rol_sector = VALUES(rol_sector)')
        ->execute([$usuarioId, $sectorId, $rol === 'responsable' ? 1 : 0, $rol]);
}
/** Texto corto del rol para mostrar en el menu. */
function etiquetaRolUsuario(PDO $pdo): string {
    if (esAdmin()) return 'Super usuario';
    $roles = misRolesSector($pdo);
    $info = sectoresInfo($pdo);
    $resp = [];
    foreach ($roles as $sid => $r) if ($r === 'responsable' && isset($info[$sid]) && $info[$sid]['slug'] !== 'sgi') $resp[] = $info[$sid]['nombre'];
    if (count($resp) >= 6) return 'Responsable general';
    if ($resp) return 'Responsable: ' . implode(', ', $resp);
    if (in_array('operador', $roles, true)) return 'Operador';
    return 'Usuario';
}
function registrarActividad(PDO $pdo, string $accion, string $detalle=''): void {
    try {
        $st = $pdo->prepare('INSERT INTO actividad(usuario_id,accion,detalle) VALUES(?,?,?)');
        $st->execute([$_SESSION['usuario_id'] ?? null, $accion, mb_substr($detalle,0,255)]);
    } catch (Throwable $e) {}
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $enviado = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $esperado = (string)($_SESSION['csrf'] ?? '');
    // Un token vacio nunca es valido (antes "vacio == vacio" dejaba pasar el envio sin token)
    if ($esperado === '' || $enviado === '' || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || isset($_SERVER['HTTP_X_CSRF_TOKEN'])) exit('Sesión expirada. Recargá la página e ingresá de nuevo.');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;max-width:480px;margin:15vh auto;padding:0 16px"><h2>La página estuvo abierta demasiado tiempo</h2><p>Por seguridad, la sesión de esa pantalla venció. No se perdió nada: volvé a cargar la página y repetí la acción.</p><p><a href="' . h(app_url('/php/login.php')) . '" style="display:inline-block;padding:10px 18px;background:#1f4e8c;color:#fff;border-radius:6px;text-decoration:none">Ir a ingresar</a></p></body>');
    }
}

/**
 * IP real de quien visita. Detras de Caddy o Cloudflare Tunnel, REMOTE_ADDR es la IP del proxy
 * (la misma para todos), asi que solo en ese caso (proxy en red privada/local) se toma la IP
 * que informa el proxy.
 */
function naser_client_ip(): string {
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $remotoEsProxy = $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($remotoEsProxy) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $cab) {
            if (empty($_SERVER[$cab])) continue;
            $primera = trim(explode(',', (string)$_SERVER[$cab])[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP)) return $primera;
        }
    }
    return $remote;
}

/** Cierra la sesion y borra la cookie. */
function naser_cerrar_sesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/**
 * Revalida la sesion contra la base en cada pedido: si la cuenta se desactivo o se borro, se corta
 * la sesion en el momento; si cambio el rol o se reseteo la clave, se actualiza sin pedir otro login.
 */
function naser_validar_sesion(PDO $pdo): void {
    if (empty($_SESSION['usuario_id'])) return;
    $st = $pdo->prepare('SELECT activo, rol, debe_cambiar_password FROM usuarios WHERE id = ?');
    $st->execute([(int)$_SESSION['usuario_id']]);
    $u = $st->fetch();
    if (!$u || (int)$u['activo'] !== 1) {
        naser_cerrar_sesion();
        $quiereJson = ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== '' || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($quiereJson) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok' => false, 'error' => 'Tu sesión terminó o tu cuenta fue desactivada. Volvé a ingresar.'], JSON_UNESCAPED_UNICODE));
        }
        header('Location: ' . app_url('/php/login.php'));
        exit;
    }
    $_SESSION['rol'] = $u['rol'];
    if ((int)$u['debe_cambiar_password'] === 1) $_SESSION['debe_cambiar_password'] = true;
    else unset($_SESSION['debe_cambiar_password']);
}
