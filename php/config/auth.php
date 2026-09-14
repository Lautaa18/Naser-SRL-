<?php
require_once __DIR__ . '/app.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function requireLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . app_url('/php/login.php'));
        exit;
    }
}
function esAdmin(): bool { return ($_SESSION['rol'] ?? '') === 'admin'; }
function requireAdmin(): void {
    requireLogin();
    if (!esAdmin()) { http_response_code(403); exit('Acceso denegado.'); }
}
function puedeVerSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    $st = $pdo->prepare('SELECT slug FROM sectores WHERE id=? LIMIT 1');
    $st->execute([$sectorId]);
    if ($st->fetchColumn() === 'sgi') return true;
    $st = $pdo->prepare('SELECT 1 FROM usuario_sector WHERE usuario_id=? AND sector_id=? AND puede_ver=1 LIMIT 1');
    $st->execute([$_SESSION['usuario_id'] ?? 0, $sectorId]);
    return (bool)$st->fetchColumn();
}
function puedeEditarSector(PDO $pdo, int $sectorId): bool {
    if (esAdmin()) return true;
    $st = $pdo->prepare('SELECT 1 FROM usuario_sector WHERE usuario_id=? AND sector_id=? AND puede_editar=1 LIMIT 1');
    $st->execute([$_SESSION['usuario_id'] ?? 0, $sectorId]);
    return (bool)$st->fetchColumn();
}
function puedeGestionarDocumentos(PDO $pdo): bool {
    if (esAdmin()) return true;
    $st = $pdo->prepare('SELECT 1 FROM usuario_sector WHERE usuario_id=? AND puede_editar=1 LIMIT 1');
    $st->execute([$_SESSION['usuario_id'] ?? 0]);
    return (bool)$st->fetchColumn();
}
function requireDocumentManager(PDO $pdo): void {
    requireLogin();
    if (!puedeGestionarDocumentos($pdo)) { http_response_code(403); exit('No tenés permisos para gestionar documentación.'); }
}
function sectoresVisibles(PDO $pdo): array {
    if (esAdmin()) return $pdo->query('SELECT id,nombre,slug,orden FROM sectores ORDER BY orden,nombre')->fetchAll();
    $st = $pdo->prepare('SELECT DISTINCT s.id,s.nombre,s.slug,s.orden FROM sectores s LEFT JOIN usuario_sector us ON us.sector_id=s.id AND us.usuario_id=? WHERE s.slug="sgi" OR us.puede_ver=1 ORDER BY s.orden,s.nombre');
    $st->execute([$_SESSION['usuario_id'] ?? 0]);
    return $st->fetchAll();
}
function sectoresEditables(PDO $pdo): array {
    if (esAdmin()) return $pdo->query('SELECT id,nombre,slug,orden FROM sectores ORDER BY orden,nombre')->fetchAll();
    $st = $pdo->prepare('SELECT s.id,s.nombre,s.slug,s.orden FROM usuario_sector us JOIN sectores s ON s.id=us.sector_id WHERE us.usuario_id=? AND us.puede_editar=1 ORDER BY s.orden,s.nombre');
    $st->execute([$_SESSION['usuario_id'] ?? 0]);
    return $st->fetchAll();
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Sesión expirada. Volvé atrás y reintentá.');
    }
}
