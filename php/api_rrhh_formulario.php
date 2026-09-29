<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';

$uid = (int)($_SESSION['usuario_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sector_id = (int)($_POST['sector_id'] ?? 0);
        $codigo_form = trim($_POST['codigo_form'] ?? '');
        $empleado = trim($_POST['empleado_nombre'] ?? '');
        $legajo = trim($_POST['legajo'] ?? '');
        $fecha = $_POST['fecha_documento'] ?: date('Y-m-d');
        $estado = ($_POST['estado'] === 'finalizado') ? 'finalizado' : 'en_edicion';

        if (!$sector_id || !$codigo_form || !$empleado) {
            throw new Exception('Completá los campos obligatorios.');
        }

        // Convertimos todos los campos del checklist recibidos por POST a JSON
        $datosChecklist = $_POST['checklist'] ?? [];
        $payload = json_encode($datosChecklist, JSON_UNESCAPED_UNICODE);

        $st = $pdo->prepare('INSERT INTO rrhh_formularios (sector_id, codigo_form, empleado_nombre, legajo, fecha_documento, estado, datos_json, creado_por, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([$sector_id, $codigo_form, $empleado, $legajo, $fecha, $estado, $payload, $uid, $uid]);

        header('Location: /php/rrhh.php?msg=checklist_guardado');
        exit;
    } catch (Throwable $e) {
        header('Location: /php/rrhh.php?err=' . urlencode($e->getMessage()));
        exit;
    }
}