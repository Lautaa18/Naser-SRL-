<?php
require __DIR__ . '/config/auth.php';
requireLogin();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/layout.php';
require_once __DIR__ . '/config/notificaciones.php';
verify_csrf();

$uid = (int)$_SESSION['usuario_id'];
$sectores = sectoresInfo($pdo);                       // id => datos
$mios = array_map('intval', array_keys(misRolesSector($pdo)));
$puedoComoSector = esAdmin() ? array_keys($sectores) : $mios;   // desde que sector puedo escribir
$msg = $_SESSION['flash_msg_mensajes'] ?? ''; $err = '';
unset($_SESSION['flash_msg_mensajes']);

function hiloDe(PDO $pdo, int $id): array {
    $st = $pdo->prepare('SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para
        FROM mensajes m JOIN usuarios u ON u.id = m.de_usuario_id
        JOIN sectores sd ON sd.id = m.de_sector_id JOIN sectores sp ON sp.id = m.para_sector_id
        WHERE m.id = ? OR m.respuesta_a = ? ORDER BY m.id');
    $st->execute([$id, $id]);
    return $st->fetchAll();
}
function puedeVerHilo(array $hilo, int $uid, array $mios): bool {
    if (esAdmin()) return true;
    foreach ($hilo as $m) {
        if ((int)$m['de_usuario_id'] === $uid || in_array((int)$m['para_sector_id'], $mios, true) || in_array((int)$m['de_sector_id'], $mios, true)) return true;
    }
    return false;
}
function enviarMensaje(PDO $pdo, int $uid, int $de, int $para, string $asunto, string $cuerpo, ?int $resp): int {
    $pdo->prepare('INSERT INTO mensajes (de_usuario_id, de_sector_id, para_sector_id, asunto, cuerpo, respuesta_a) VALUES (?,?,?,?,?,?)')
        ->execute([$uid, $de, $para, $asunto, $cuerpo, $resp]);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('SELECT DISTINCT us.usuario_id FROM usuario_sector us JOIN usuarios u ON u.id = us.usuario_id AND u.activo = 1 WHERE us.sector_id = ? AND us.usuario_id <> ?');
    $st->execute([$para, $uid]);
    $dest = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $nombreDe = (string)($pdo->query('SELECT nombre FROM sectores WHERE id = ' . $de)->fetchColumn());
    try {
        notificar($pdo, $dest, 'Mensaje de ' . $nombreDe . ': ' . $asunto, mb_substr($cuerpo, 0, 140), '/php/mensajes.php?ver=' . ($resp ?: $id), 'mensaje', 'msg-' . $id, true);
    } catch (Throwable $e) { error_log('[NASER] aviso de mensaje: ' . $e->getMessage()); }
    return $id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $a = $_POST['accion'] ?? '';
        $de = (int)($_POST['de_sector'] ?? 0);
        $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
        if (!in_array($de, $puedoComoSector, true)) throw new RuntimeException('Elegí desde qué sector escribís.');
        if ($cuerpo === '' || mb_strlen($cuerpo) > 5000) throw new RuntimeException('Escribí el mensaje (hasta 5000 caracteres).');

        if ($a === 'enviar') {
            $para = (int)($_POST['para_sector'] ?? 0);
            $asunto = trim((string)($_POST['asunto'] ?? ''));
            if (!isset($sectores[$para])) throw new RuntimeException('Elegí el sector destinatario.');
            if ($asunto === '' || mb_strlen($asunto) > 150) throw new RuntimeException('Escribí un asunto (hasta 150 caracteres).');
            $id = enviarMensaje($pdo, $uid, $de, $para, $asunto, $cuerpo, null);
            $_SESSION['flash_msg_mensajes'] = 'Mensaje enviado a ' . $sectores[$para]['nombre'] . '.';
            header('Location: ' . app_url('/php/mensajes.php?ver=' . $id)); exit;
        }
        if ($a === 'responder') {
            $raiz = (int)($_POST['raiz'] ?? 0);
            $hilo = hiloDe($pdo, $raiz);
            if (!$hilo || (int)$hilo[0]['id'] !== $raiz || $hilo[0]['respuesta_a'] !== null || !puedeVerHilo($hilo, $uid, $mios)) throw new RuntimeException('Conversación no encontrada.');
            $ult = end($hilo);
            // Si escribo desde el sector que recibio el ultimo mensaje, contesto al que lo envio; si no, va al sector que lo recibio
            $para = ($de === (int)$ult['para_sector_id']) ? (int)$ult['de_sector_id'] : (int)$ult['para_sector_id'];
            enviarMensaje($pdo, $uid, $de, $para, 'Re: ' . $hilo[0]['asunto'], $cuerpo, $raiz);
            $_SESSION['flash_msg_mensajes'] = 'Respuesta enviada.';
            header('Location: ' . app_url('/php/mensajes.php?ver=' . $raiz)); exit;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$ver = (int)($_GET['ver'] ?? 0);
$hilo = [];
if ($ver) {
    $hilo = hiloDe($pdo, $ver);
    if (!$hilo || $hilo[0]['respuesta_a'] !== null && (int)$hilo[0]['id'] !== $ver) {
        // si abrieron una respuesta, mostrar la conversacion completa
        $raiz = $hilo ? (int)$hilo[0]['respuesta_a'] : 0;
        $hilo = $raiz ? hiloDe($pdo, $raiz) : [];
    }
    if (!$hilo || !puedeVerHilo($hilo, $uid, $mios)) { $hilo = []; $err = $err ?: 'Conversación no encontrada.'; }
    else {
        $ins = $pdo->prepare('INSERT IGNORE INTO mensajes_leidos (mensaje_id, usuario_id) VALUES (?,?)');
        foreach ($hilo as $m) if ((int)$m['de_usuario_id'] !== $uid) $ins->execute([(int)$m['id'], $uid]);
    }
}

$vista = ($_GET['vista'] ?? '') === 'enviados' ? 'enviados' : 'recibidos';
$lista = [];
if (!$hilo) {
    if ($vista === 'enviados') {
        $st = $pdo->prepare('SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para, 1 AS leido
            FROM mensajes m JOIN usuarios u ON u.id = m.de_usuario_id JOIN sectores sd ON sd.id = m.de_sector_id JOIN sectores sp ON sp.id = m.para_sector_id
            WHERE m.de_usuario_id = ? ORDER BY m.id DESC LIMIT 100');
        $st->execute([$uid]);
    } else {
        $ids = $mios ?: [0];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para,
            EXISTS (SELECT 1 FROM mensajes_leidos l WHERE l.mensaje_id = m.id AND l.usuario_id = ?) AS leido
            FROM mensajes m JOIN usuarios u ON u.id = m.de_usuario_id JOIN sectores sd ON sd.id = m.de_sector_id JOIN sectores sp ON sp.id = m.para_sector_id
            WHERE m.para_sector_id IN ($ph) AND m.de_usuario_id <> ? ORDER BY m.id DESC LIMIT 100");
        $st->execute(array_merge([$uid], $ids, [$uid]));
    }
    $lista = $st->fetchAll();
}
function fechaMsg(string $f): string { return date('d/m/Y H:i', strtotime($f)); }
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mensajes | NASER</title><link rel="stylesheet" href="<?= asset('/style.css') ?>"></head>
<body><div class="app"><?php sidebar($pdo, 'mensajes'); ?>
<main class="content">
<header class="section-top"><div><p class="eyebrow">COMUNICACIÓN INTERNA</p><h1>Mensajes entre sectores</h1>
<p>Escribile a un sector y le llega a todos sus integrantes. Las respuestas quedan en la misma conversación.</p></div>
<?php if ($hilo): ?><a class="btn secondary" href="<?= app_url('/php/mensajes.php') ?>">Volver a la bandeja</a><?php endif; ?></header>
<?php if ($msg): ?><div class="alert success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= h($err) ?></div><?php endif; ?>

<?php if ($hilo): ?>
  <section class="table-panel"><h2><?= h($hilo[0]['asunto']) ?></h2>
  <?php foreach ($hilo as $m): ?>
    <div style="border:1px solid #dfe7e1;border-radius:12px;padding:12px 14px;margin:10px 0">
      <strong><?= h($m['autor']) ?></strong> <span class="muted">· <?= h($m['sector_de']) ?> → <?= h($m['sector_para']) ?> · <?= h(fechaMsg($m['creado_en'])) ?></span>
      <p style="margin:8px 0 0"><?= nl2br(h($m['cuerpo'])) ?></p>
    </div>
  <?php endforeach; ?></section>
  <?php if ($puedoComoSector): ?>
  <section class="form-panel"><h2>Responder</h2>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="accion" value="responder"><input type="hidden" name="raiz" value="<?= (int)$hilo[0]['id'] ?>">
    <?php $ult = end($hilo); $porDefecto = in_array((int)$ult['para_sector_id'], $puedoComoSector, true) ? (int)$ult['para_sector_id'] : (int)$ult['de_sector_id']; ?>
    <label>Respondo desde<select name="de_sector"><?php foreach ($puedoComoSector as $sid): ?><option value="<?= $sid ?>" <?= $sid === $porDefecto ? 'selected' : '' ?>><?= h($sectores[$sid]['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Mensaje<textarea name="cuerpo" rows="4" maxlength="5000" required></textarea></label>
    <button class="btn primary">Enviar respuesta</button>
  </form></section>
  <?php endif; ?>
<?php else: ?>
  <section class="form-panel"><h2>Nuevo mensaje</h2>
  <?php if (!$puedoComoSector): ?><p class="muted">Todavía no tenés un sector asignado, por eso no podés enviar mensajes. Pedile al administrador que te asigne uno.</p>
  <?php else: ?>
  <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="accion" value="enviar">
    <div class="form-row">
      <label>Desde<select name="de_sector"><?php foreach ($puedoComoSector as $sid): ?><option value="<?= $sid ?>"><?= h($sectores[$sid]['nombre']) ?></option><?php endforeach; ?></select></label>
      <label>Para (sector)<select name="para_sector" required><option value="">Elegí un sector…</option><?php foreach ($sectores as $sid => $s): ?><option value="<?= (int)$sid ?>"><?= h($s['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Asunto<input name="asunto" maxlength="150" required></label>
    <label>Mensaje<textarea name="cuerpo" rows="4" maxlength="5000" required></textarea></label>
    <button class="btn primary">Enviar mensaje</button>
  </form><?php endif; ?></section>

  <section class="table-panel">
    <div class="section-head"><div><h2><?= $vista === 'enviados' ? 'Enviados' : 'Recibidos' ?></h2></div>
      <div><a class="btn small <?= $vista === 'recibidos' ? 'primary' : 'secondary' ?>" href="<?= app_url('/php/mensajes.php') ?>">Recibidos</a>
           <a class="btn small <?= $vista === 'enviados' ? 'primary' : 'secondary' ?>" href="<?= app_url('/php/mensajes.php?vista=enviados') ?>">Enviados</a></div></div>
    <div class="table-wrap"><table><thead><tr><th>Asunto</th><th><?= $vista === 'enviados' ? 'Para' : 'De' ?></th><th>Fecha</th></tr></thead><tbody>
    <?php foreach ($lista as $m): $raiz = $m['respuesta_a'] ?: $m['id']; ?>
      <tr><td><a href="<?= app_url('/php/mensajes.php?ver=' . (int)$raiz) ?>"><?= $m['leido'] ? '' : '<strong>● ' ?><?= h($m['asunto']) ?><?= $m['leido'] ? '' : '</strong>' ?></a></td>
      <td><?= $vista === 'enviados' ? h($m['sector_para']) : h($m['autor'] . ' (' . $m['sector_de'] . ')') ?></td><td><?= h(fechaMsg($m['creado_en'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="3" class="muted">No hay mensajes.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
</main></div></body></html>
