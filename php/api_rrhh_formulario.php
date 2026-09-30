<?php
// Guarda un checklist / formulario de RRHH (POST desde rrhh.php)
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('/php/rrhh.php'));
    exit;
}
verify_csrf();

$uid = (int)($_SESSION['usuario_id'] ?? 0);

try {
    // El sector se toma de la base (no del formulario) y se verifica el permiso de edicion
    $st = $pdo->prepare('SELECT id FROM sectores WHERE slug = ? LIMIT 1');
    $st->execute(['rrhh']);
    $sectorId = (int)$st->fetchColumn();
    if (!$sectorId) throw new RuntimeException('No existe el sector RRHH.');
    if (!puedeEditarSector($pdo, $sectorId)) {
        http_response_code(403);
        exit('No tenés permiso para cargar formularios de RRHH.');
    }

    $codigoForm = mb_substr(trim((string)($_POST['codigo_form'] ?? '')), 0, 100);
    $empleado   = mb_substr(trim((string)($_POST['empleado_nombre'] ?? '')), 0, 150);
    $legajo     = mb_substr(trim((string)($_POST['legajo'] ?? '')), 0, 50);
    $fecha      = (string)($_POST['fecha_documento'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
    $estado     = (($_POST['estado'] ?? '') === 'finalizado') ? 'finalizado' : 'en_edicion';

    if ($codigoForm === '' || $empleado === '') {
        throw new RuntimeException('Completá los campos obligatorios.');
    }

    // Todos los campos del checklist se guardan como JSON
    $datosChecklist = $_POST['checklist'] ?? [];
    if (!is_array($datosChecklist)) $datosChecklist = [];
    $payload = json_encode($datosChecklist, JSON_UNESCAPED_UNICODE);

    $st = $pdo->prepare('INSERT INTO rrhh_formularios (sector_id, codigo_form, empleado_nombre, legajo, fecha_documento, estado, datos_json, creado_por, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([$sectorId, $codigoForm, $empleado, $legajo, $fecha, $estado, $payload, $uid, $uid]);
    registrarActividad($pdo, 'rrhh_checklist', "Checklist $codigoForm - $empleado");

    header('Location: ' . app_url('/php/rrhh.php') . '?msg=checklist_guardado#formularios');
    exit;
} catch (Throwable $e) {
    error_log('[NASER] api_rrhh_formulario: ' . $e->getMessage());
    header('Location: ' . app_url('/php/rrhh.php') . '?err=' . urlencode($e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el formulario.') . '#formularios');
    exit;
}
