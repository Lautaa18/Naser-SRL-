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
// Primero los sectores donde soy responsable u operador (el sector "propio"), despues los de solo lectura
$pesoRol = ['responsable' => 0, 'operador' => 1, 'observador' => 2];
$misRoles = misRolesSector($pdo);
usort($puedoComoSector, fn($x, $y) => ($pesoRol[$misRoles[$x] ?? ''] ?? 3) <=> ($pesoRol[$misRoles[$y] ?? ''] ?? 3));
$msg = $_SESSION['flash_msg_mensajes'] ?? ''; $err = '';
unset($_SESSION['flash_msg_mensajes']);

function hiloDe(PDO $pdo, int $id): array {
    $st = $pdo->prepare('SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para, up.nombre AS persona_para
        FROM mensajes m LEFT JOIN usuarios u ON u.id = m.de_usuario_id LEFT JOIN usuarios up ON up.id = m.para_usuario_id
        JOIN sectores sd ON sd.id = m.de_sector_id LEFT JOIN sectores sp ON sp.id = m.para_sector_id
        WHERE m.id = ? OR m.respuesta_a = ? ORDER BY m.id');
    $st->execute([$id, $id]);
    return $st->fetchAll();
}
/** Conversacion entre dos personas: solo la ven quien escribio y quien recibio (ni siquiera el super usuario desde la bandeja). */
function esHiloPrivado(array $hilo): bool { return $hilo && $hilo[0]['para_usuario_id'] !== null; }
function puedeVerHilo(array $hilo, int $uid, array $mios): bool {
    if (esHiloPrivado($hilo)) {
        foreach ($hilo as $m) if ((int)$m['de_usuario_id'] === $uid || (int)$m['para_usuario_id'] === $uid) return true;
        return false;
    }
    if (esAdmin()) return true;
    foreach ($hilo as $m) {
        if ((int)$m['de_usuario_id'] === $uid || in_array((int)$m['para_sector_id'], $mios, true) || in_array((int)$m['de_sector_id'], $mios, true)) return true;
    }
    return false;
}
/** Texto del destinatario de un mensaje: la persona o el sector. */
function destinoMsg(array $m): string { return $m['persona_para'] ?? null ? (string)$m['persona_para'] : (string)($m['sector_para'] ?? ''); }
/** Envia un mensaje a un SECTOR ($paraSector) o a UNA PERSONA ($paraUsuario). */
function enviarMensaje(PDO $pdo, int $uid, int $de, ?int $paraSector, ?int $paraUsuario, string $asunto, string $cuerpo, ?int $resp): int {
    $pdo->prepare('INSERT INTO mensajes (de_usuario_id, de_sector_id, para_sector_id, para_usuario_id, asunto, cuerpo, respuesta_a) VALUES (?,?,?,?,?,?,?)')
        ->execute([$uid, $de, $paraUsuario ? null : $paraSector, $paraUsuario ?: null, $asunto, $cuerpo, $resp]);
    $id = (int)$pdo->lastInsertId();
    if ($paraUsuario) {
        $dest = [$paraUsuario];
    } else {
        $st = $pdo->prepare('SELECT DISTINCT us.usuario_id FROM usuario_sector us JOIN usuarios u ON u.id = us.usuario_id AND u.activo = 1 WHERE us.sector_id = ? AND us.usuario_id <> ?');
        $st->execute([$paraSector, $uid]);
        $dest = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    $nombreDe = (string)($pdo->query('SELECT nombre FROM sectores WHERE id = ' . $de)->fetchColumn());
    $nombreYo = (string)($_SESSION['nombre'] ?? '');
    $quien = $paraUsuario && $nombreYo !== '' ? $nombreYo . ' (' . $nombreDe . ')' : $nombreDe;
    try {
        notificar($pdo, $dest, 'Mensaje de ' . $quien . ': ' . $asunto, mb_substr($cuerpo, 0, 140), '/php/mensajes.php?ver=' . ($resp ?: $id), 'mensaje', 'msg-' . $id, true);
    } catch (Throwable $e) { error_log('[NASER] aviso de mensaje: ' . $e->getMessage()); }
    return $id;
}
/** Persona activa a la que se le puede escribir (null si no existe, esta desactivada o es uno mismo). */
function destinatarioValido(PDO $pdo, int $uid, int $destino): ?array {
    if ($destino <= 0 || $destino === $uid) return null;
    $st = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE id = ? AND activo = 1');
    $st->execute([$destino]);
    return $st->fetch() ?: null;
}
/** Personas a las que se les puede escribir, con sus sectores (sin mostrar mails). */
function personasDestino(PDO $pdo, int $uid): array {
    $st = $pdo->prepare("SELECT u.id, u.nombre, GROUP_CONCAT(DISTINCT s.nombre ORDER BY s.orden SEPARATOR ', ') AS sectores
        FROM usuarios u LEFT JOIN usuario_sector us ON us.usuario_id = u.id LEFT JOIN sectores s ON s.id = us.sector_id
        WHERE u.activo = 1 AND u.id <> ? GROUP BY u.id, u.nombre ORDER BY u.nombre");
    $st->execute([$uid]);
    return $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $a = $_POST['accion'] ?? '';
        $de = (int)($_POST['de_sector'] ?? 0);
        $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
        if (!in_array($de, $puedoComoSector, true)) throw new RuntimeException('Elegí desde qué sector escribís.');
        if ($cuerpo === '' || mb_strlen($cuerpo) > 5000) throw new RuntimeException('Escribí el mensaje (hasta 5000 caracteres).');

        if ($a === 'enviar') {
            $para = (string)($_POST['para'] ?? '');      // "u:ID" = una persona, "s:ID" = un sector entero
            $asunto = trim((string)($_POST['asunto'] ?? ''));
            if ($asunto === '' || mb_strlen($asunto) > 150) throw new RuntimeException('Escribí un asunto (hasta 150 caracteres).');
            if (preg_match('/^u:(\d+)$/', $para, $mm)) {
                $persona = destinatarioValido($pdo, $uid, (int)$mm[1]);
                if (!$persona) throw new RuntimeException('Elegí a quién le escribís (esa persona no está disponible).');
                $id = enviarMensaje($pdo, $uid, $de, null, (int)$persona['id'], $asunto, $cuerpo, null);
                $_SESSION['flash_msg_mensajes'] = 'Mensaje enviado a ' . $persona['nombre'] . '.';
            } elseif (preg_match('/^s:(\d+)$/', $para, $mm) && isset($sectores[(int)$mm[1]])) {
                $id = enviarMensaje($pdo, $uid, $de, (int)$mm[1], null, $asunto, $cuerpo, null);
                $_SESSION['flash_msg_mensajes'] = 'Mensaje enviado al sector ' . $sectores[(int)$mm[1]]['nombre'] . '.';
            } else {
                throw new RuntimeException('Elegí a quién le escribís: una persona o un sector.');
            }
            header('Location: ' . app_url('/php/mensajes.php?ver=' . $id)); exit;
        }
        if ($a === 'responder') {
            $raiz = (int)($_POST['raiz'] ?? 0);
            $hilo = hiloDe($pdo, $raiz);
            if (!$hilo || (int)$hilo[0]['id'] !== $raiz || $hilo[0]['respuesta_a'] !== null || !puedeVerHilo($hilo, $uid, $mios)) throw new RuntimeException('Conversación no encontrada.');
            $ult = end($hilo);
            if (esHiloPrivado($hilo)) {
                // Conversacion entre dos personas: la respuesta va a la otra persona
                $otro = (int)$ult['de_usuario_id'] === $uid ? (int)$ult['para_usuario_id'] : (int)$ult['de_usuario_id'];
                if (!destinatarioValido($pdo, $uid, $otro)) throw new RuntimeException('Esa persona ya no está disponible para recibir mensajes.');
                enviarMensaje($pdo, $uid, $de, null, $otro, 'Re: ' . $hilo[0]['asunto'], $cuerpo, $raiz);
            } else {
                // Si escribo desde el sector que recibio el ultimo mensaje, contesto al que lo envio; si no, va al sector que lo recibio
                $para = ($de === (int)$ult['para_sector_id']) ? (int)$ult['de_sector_id'] : (int)$ult['para_sector_id'];
                enviarMensaje($pdo, $uid, $de, $para, null, 'Re: ' . $hilo[0]['asunto'], $cuerpo, $raiz);
            }
            $_SESSION['flash_msg_mensajes'] = 'Respuesta enviada.';
            header('Location: ' . app_url('/php/mensajes.php?ver=' . $raiz)); exit;
        }
    } catch (Throwable $e) {
        $err = mensaje_error($e);
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
        $st = $pdo->prepare('SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para, up.nombre AS persona_para, 1 AS leido
            FROM mensajes m LEFT JOIN usuarios u ON u.id = m.de_usuario_id LEFT JOIN usuarios up ON up.id = m.para_usuario_id
            JOIN sectores sd ON sd.id = m.de_sector_id LEFT JOIN sectores sp ON sp.id = m.para_sector_id
            WHERE m.de_usuario_id = ? ORDER BY m.id DESC LIMIT 100');
        $st->execute([$uid]);
    } else {
        $ids = $mios ?: [0];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT m.*, u.nombre AS autor, sd.nombre AS sector_de, sp.nombre AS sector_para, up.nombre AS persona_para,
            EXISTS (SELECT 1 FROM mensajes_leidos l WHERE l.mensaje_id = m.id AND l.usuario_id = ?) AS leido
            FROM mensajes m LEFT JOIN usuarios u ON u.id = m.de_usuario_id LEFT JOIN usuarios up ON up.id = m.para_usuario_id
            JOIN sectores sd ON sd.id = m.de_sector_id LEFT JOIN sectores sp ON sp.id = m.para_sector_id
            WHERE (m.para_usuario_id = ? OR (m.para_usuario_id IS NULL AND m.para_sector_id IN ($ph))) AND m.de_usuario_id <> ? ORDER BY m.id DESC LIMIT 100");
        $st->execute(array_merge([$uid, $uid], $ids, [$uid]));
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
<header class="section-top"><div><p class="eyebrow">COMUNICACIÓN INTERNA</p><h1>Mensajes</h1>
<p>Escribile a una persona (solo la ven ustedes dos) o a un sector entero (le llega a todos sus integrantes). Las respuestas quedan en la misma conversación.</p></div>
<?php if ($hilo): ?><a class="btn secondary" href="<?= app_url('/php/mensajes.php') ?>">Volver a la bandeja</a><?php endif; ?></header>
<?php if ($msg): ?><div class="alert success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= h($err) ?></div><?php endif; ?>

<?php if ($hilo): ?>
  <section class="table-panel"><h2><?= h($hilo[0]['asunto']) ?></h2>
  <?php foreach ($hilo as $m): ?>
    <div style="border:1px solid #dfe7e1;border-radius:12px;padding:12px 14px;margin:10px 0">
      <strong><?= h($m['autor'] ?? 'Usuario eliminado') ?></strong> <span class="muted">· <?= h($m['sector_de']) ?> → <?= h(destinoMsg($m)) ?><?= $m['persona_para'] ? ' (en persona)' : '' ?> · <?= h(fechaMsg($m['creado_en'])) ?></span>
      <p style="margin:8px 0 0"><?= nl2br(h($m['cuerpo'])) ?></p>
    </div>
  <?php endforeach; ?></section>
  <?php if ($puedoComoSector): ?>
  <section class="form-panel"><h2>Responder</h2>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="accion" value="responder"><input type="hidden" name="raiz" value="<?= (int)$hilo[0]['id'] ?>">
    <?php $ult = end($hilo); $porDefecto = in_array((int)$ult['para_sector_id'], $puedoComoSector, true) ? (int)$ult['para_sector_id'] : (int)$ult['de_sector_id'];
          if (!in_array($porDefecto, $puedoComoSector, true)) $porDefecto = $puedoComoSector[0]; ?>
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
      <label>Para<select name="para" required><option value="">Elegí una persona o un sector…</option>
        <optgroup label="Una persona"><?php foreach (personasDestino($pdo, $uid) as $p): ?><option value="u:<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?><?= $p['sectores'] ? ' — ' . h($p['sectores']) : '' ?></option><?php endforeach; ?></optgroup>
        <optgroup label="Un sector entero (le llega a todos)"><?php foreach ($sectores as $sid => $s): ?><option value="s:<?= (int)$sid ?>"><?= h($s['nombre']) ?></option><?php endforeach; ?></optgroup>
      </select></label>
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
      <td><?= $vista === 'enviados' ? h(destinoMsg($m)) : h(($m['autor'] ?? 'Usuario eliminado') . ' (' . $m['sector_de'] . ')') ?><?= $m['persona_para'] ? ' <span class="muted">· personal</span>' : '' ?></td><td><?= h(fechaMsg($m['creado_en'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="3" class="muted">No hay mensajes.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
</main></div></body></html>
