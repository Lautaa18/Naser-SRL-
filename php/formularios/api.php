<?php
// API de formularios: guardar, enviar a aprobación, aprobar, rechazar, reabrir, duplicar, eliminar.
// Recibe JSON por POST (con cabecera X-CSRF-Token) y responde JSON.
require __DIR__ . '/_init.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fallar(string $msg, int $code = 400): never { responder(['ok' => false, 'error' => $msg], $code); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fallar('Método no permitido.', 405);
verify_csrf();

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) fallar('Datos inválidos.');

$accion = (string)($in['accion'] ?? '');
$id = (int)($in['id'] ?? 0);
$uid = (int)$_SESSION['usuario_id'];
$yo = (string)($_SESSION['nombre'] ?? 'Un usuario');

$limpiarJson = function ($v): ?string {
    if ($v === null) return null;
    $j = json_encode($v, JSON_UNESCAPED_UNICODE);
    if ($j === false) fallar('No se pudieron leer los datos del formulario.');
    if (strlen($j) > 8 * 1024 * 1024) fallar('El formulario es demasiado grande.');
    return $j;
};
$referencia = mb_substr(trim((string)($in['referencia'] ?? '')), 0, 200);

$reg = $id ? cargarRegistro($pdo, $id) : null;
if ($id && !$reg) fallar('El registro no existe (puede que lo hayan eliminado).', 404);

// ---------- helpers ----------
$urlRegistro = fn(int $rid) => '/php/formularios/llenar.php?id=' . $rid;
$guardarDatos = function (int $rid) use ($pdo, $in, $uid, $referencia, $limpiarJson) {
    $pdo->prepare('UPDATE formularios_registros SET datos_json = ?, storage_json = ?, referencia = ?, actualizado_por = ? WHERE id = ?')
        ->execute([$limpiarJson($in['datos'] ?? new stdClass()), $limpiarJson($in['storage'] ?? new stdClass()), $referencia ?: null, $uid, $rid]);
};
$respuestaRegistro = function (int $rid, string $mensaje) use ($pdo) {
    $r = cargarRegistro($pdo, $rid);
    responder(['ok' => true, 'id' => $rid, 'estado' => $r['estado'], 'estado_label' => estadoFormularioLabel($r['estado']), 'permisos' => permisosRegistro($pdo, $r), 'mensaje' => $mensaje]);
};

switch ($accion) {

    // ---------- GUARDAR (crea el registro si es nuevo) ----------
    case 'guardar':
    case 'enviar':
        if (!$reg) {
            $form = formularioPorCodigo((string)($in['formulario'] ?? ''));
            if (!$form) fallar('Formulario desconocido.');
            $sector = sectorPorSlug($pdo, $form['sector']);
            if (!$sector || !puedeCompletarSector($pdo, (int)$sector['id'])) fallar('No tenés permiso para completar formularios de este sector.', 403);
            $pdo->prepare('INSERT INTO formularios_registros (formulario, sector_id, estado, creado_por, actualizado_por) VALUES (?,?,?,?,?)')
                ->execute([$form['codigo'], (int)$sector['id'], 'borrador', $uid, $uid]);
            $id = (int)$pdo->lastInsertId();
            $guardarDatos($id);
            historialFormulario($pdo, $id, 'creado');
            registrarActividad($pdo, 'formulario_creado', $form['titulo'] . " #$id");
            $reg = cargarRegistro($pdo, $id);
        } else {
            $perm = permisosRegistro($pdo, $reg);
            if (!$perm['editar']) fallar($reg['estado'] === 'enviado' ? 'El formulario está pendiente de aprobación y no se puede modificar.' : 'No tenés permiso para modificar este formulario.', 403);
            $guardarDatos($id);
            // Para no llenar el historial: un "guardado" cada 10 minutos por usuario
            $st = $pdo->prepare("SELECT COUNT(*) FROM formularios_historial WHERE registro_id = ? AND usuario_id = ? AND accion IN ('guardado','creado') AND creado_en > NOW() - INTERVAL 10 MINUTE");
            $st->execute([$id, $uid]);
            if (!(int)$st->fetchColumn()) historialFormulario($pdo, $id, 'guardado');
            $reg = cargarRegistro($pdo, $id);
        }
        if ($accion === 'guardar') $respuestaRegistro($id, 'Guardado en el sistema.');

        // ---------- ENVIAR A APROBACIÓN ----------
        $perm = permisosRegistro($pdo, $reg);
        if (!$perm['enviar']) fallar('No podés enviar este formulario.', 403);
        $pdo->prepare("UPDATE formularios_registros SET estado = 'enviado', enviado_por = ?, enviado_en = NOW(), comentario_revision = NULL WHERE id = ?")->execute([$uid, $id]);
        historialFormulario($pdo, $id, 'enviado', trim((string)($in['comentario'] ?? '')) ?: null);
        $form = formularioPorCodigo($reg['formulario']);
        $sectorNombre = sectoresInfo($pdo)[(int)$reg['sector_id']]['nombre'] ?? '';
        $responsables = array_column(usuariosConRol($pdo, (int)$reg['sector_id'], 'responsable'), 'id');
        if (!$responsables) $responsables = array_column($pdo->query("SELECT id FROM usuarios WHERE rol='admin' AND activo=1")->fetchAll(), 'id');
        $responsables = array_diff($responsables, [$uid]);
        $titulo = 'Para aprobar: ' . ($form['titulo'] ?? 'Formulario') . ($reg['referencia'] ? ' — ' . $reg['referencia'] : '');
        $n = notificar($pdo, $responsables, $titulo, "$yo completó el formulario \"" . ($form['titulo'] ?? '') . "\" de $sectorNombre y lo envió para tu aprobación.", $urlRegistro($id), 'aprobacion');
        registrarActividad($pdo, 'formulario_enviado', ($form['titulo'] ?? '') . " #$id");
        $respuestaRegistro($id, $n ? "Enviado. Se avisó a $n responsable(s) del sector." : 'Enviado para aprobación.');

    // ---------- APROBAR / RECHAZAR ----------
    case 'aprobar':
    case 'rechazar':
        if (!$reg) fallar('Falta el registro.');
        $perm = permisosRegistro($pdo, $reg);
        if (!$perm['aprobar']) fallar('Solo el responsable del área puede aprobar o rechazar este formulario.', 403);
        $comentario = trim((string)($in['comentario'] ?? ''));
        if ($accion === 'rechazar' && $comentario === '') fallar('Escribí el motivo del rechazo para que lo puedan corregir.');
        $nuevo = $accion === 'aprobar' ? 'aprobado' : 'rechazado';
        $pdo->prepare('UPDATE formularios_registros SET estado = ?, revisado_por = ?, revisado_en = NOW(), comentario_revision = ? WHERE id = ?')
            ->execute([$nuevo, $uid, $comentario ?: null, $id]);
        historialFormulario($pdo, $id, $nuevo, $comentario ?: null);
        $form = formularioPorCodigo($reg['formulario']);
        $dest = array_diff(array_unique([(int)$reg['creado_por'], (int)$reg['enviado_por']]), [$uid, 0]);
        $titulo = ($nuevo === 'aprobado' ? 'Aprobado: ' : 'Rechazado: ') . ($form['titulo'] ?? 'Formulario') . ($reg['referencia'] ? ' — ' . $reg['referencia'] : '');
        $msg = $nuevo === 'aprobado' ? "$yo aprobó el formulario." : "$yo rechazó el formulario. Motivo: $comentario";
        notificar($pdo, $dest, $titulo, $msg . ($nuevo === 'aprobado' && $comentario ? " Comentario: $comentario" : ''), $urlRegistro($id), $nuevo);
        registrarActividad($pdo, 'formulario_' . $nuevo, ($form['titulo'] ?? '') . " #$id");
        $respuestaRegistro($id, $nuevo === 'aprobado' ? 'Formulario aprobado.' : 'Formulario rechazado. Se avisó a quien lo cargó.');

    // ---------- REABRIR ----------
    case 'reabrir':
        if (!$reg) fallar('Falta el registro.');
        if (!permisosRegistro($pdo, $reg)['reabrir']) fallar('No tenés permiso para reabrir este formulario.', 403);
        $pdo->prepare("UPDATE formularios_registros SET estado = 'borrador' WHERE id = ?")->execute([$id]);
        historialFormulario($pdo, $id, 'reabierto', trim((string)($in['comentario'] ?? '')) ?: null);
        notificar($pdo, array_diff([(int)$reg['creado_por']], [$uid]), 'Formulario reabierto para edición', "$yo reabrió el formulario #$id para que se pueda modificar.", $urlRegistro($id), 'info');
        $respuestaRegistro($id, 'El formulario volvió a borrador y se puede editar.');

    // ---------- DUPLICAR ----------
    case 'duplicar':
        if (!$reg) fallar('Falta el registro.');
        if (!puedeCompletarSector($pdo, (int)$reg['sector_id'])) fallar('No tenés permiso para completar formularios de este sector.', 403);
        $pdo->prepare("INSERT INTO formularios_registros (formulario, sector_id, referencia, estado, datos_json, storage_json, creado_por, actualizado_por) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$reg['formulario'], $reg['sector_id'], $reg['referencia'] ? mb_substr('Copia de ' . $reg['referencia'], 0, 200) : null, 'borrador', $reg['datos_json'], $reg['storage_json'], $uid, $uid]);
        $nuevoId = (int)$pdo->lastInsertId();
        historialFormulario($pdo, $nuevoId, 'creado', "Copia del registro #$id");
        responder(['ok' => true, 'id' => $nuevoId, 'redirect' => app_url($urlRegistro($nuevoId)), 'mensaje' => 'Se creó una copia en borrador.']);

    // ---------- ELIMINAR ----------
    case 'eliminar':
        if (!$reg) fallar('Falta el registro.');
        if (!permisosRegistro($pdo, $reg)['eliminar']) fallar('No tenés permiso para eliminar este formulario.', 403);
        $pdo->prepare('DELETE FROM formularios_historial WHERE registro_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM formularios_registros WHERE id = ?')->execute([$id]);
        $form = formularioPorCodigo($reg['formulario']);
        registrarActividad($pdo, 'formulario_eliminado', ($form['titulo'] ?? $reg['formulario']) . " #$id");
        $slug = sectoresInfo($pdo)[(int)$reg['sector_id']]['slug'] ?? '';
        responder(['ok' => true, 'redirect' => app_url('/php/formularios/index.php') . '?sector=' . urlencode($slug), 'mensaje' => 'Formulario eliminado.']);

    default:
        fallar('Acción desconocida.');
}
