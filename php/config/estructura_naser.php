<?php
// ==========================================
// ESTRUCTURA DE NASER: personas, responsables por sector y burbujas
// ------------------------------------------
// Editá este archivo cuando tengas los nombres y apellidos completos
// y después, en "Usuarios y permisos", tocá "Aplicar estructura NASER".
// Se puede aplicar las veces que quieras: crea lo que falta y
// actualiza los permisos de estas personas (no toca a los demás).
// ==========================================

const NASER_DOMINIO_MAIL = 'gruponaser.com.ar';

function estructuraNaser(): array {
    return [
        // clave => [Nombre y apellido, super usuario?, mail corporativo]
        // Datos del "Listado de Empleados Actual" (hojas PERSONAL y MAILS).
        // (*) = no figuraba en la hoja MAILS: se armo nombre.apellido@gruponaser.com.ar (confirmar)
        'personas' => [
            'sigifredo'       => ['Tristán Sigifredo Pizarro', true, 'sigifredo.pizarro@gruponaser.com.ar'],   // SUPER USUARIO
            'ariel.costallat' => ['Ariel Eduardo Costallat', false, 'ariel.costallat@gruponaser.com.ar'],      // responsable en todos los sectores
            'vanessa'         => ['Vanesa Lorena Marcon', false, 'vanesa.marcon@gruponaser.com.ar'],
            'noelia'          => ['Noelia Gisell Cuñelao', false, 'noelia.cunelao@gruponaser.com.ar'],
            'carina'          => ['Carina Noemí Jara', false, 'carina.jara@gruponaser.com.ar'],
            'victor'          => ['Víctor Andrés Bonfils', false, 'victor.bonfils@gruponaser.com.ar'],          // (*)
            'osvaldo'         => ['Osvaldo Adolfo Furlan', false, 'osvaldo.furlan@gruponaser.com.ar'],          // (*)
            'ariel.ibanez'    => ['Oscar Ariel Ibañez', false, 'ariel.ibanez@gruponaser.com.ar'],               // (*)
            'juan'            => ['Juan Manuel Basoalto', false, 'juan.basoalto@gruponaser.com.ar'],
            'silvio'          => ['Silvio Marcelo Oksman Kritz', false, 'silvio.oksman@gruponaser.com.ar'],     // (*)
            'cintia'          => ['Cynthia Pamela Riquelme', false, 'cynthia.riquelme@gruponaser.com.ar'],      // (*)
            'andres.belizon'  => ['Juan Andrés Belizón', false, 'andres.belizon@gruponaser.com.ar'],

            // Personal operativo (Slick Line): operadores de Operaciones
            'diego.espeche'    => ['Diego Sebastián Espeche Miranda', false, 'diego.espeche@gruponaser.com.ar'],
            'mariano.dominguez'=> ['Roque Mariano Domínguez', false, 'mariano.dominguez@gruponaser.com.ar'],
            'sergio.medel'     => ['Sergio Emilio Medel', false, 'sergio.medel@gruponaser.com.ar'],
            'javier.peralta'   => ['Javier Alejandro Peralta', false, 'javier.peralta@gruponaser.com.ar'],
            'ignacio.vece'     => ['Ignacio Martín Vece', false, 'ignacio.vece@gruponaser.com.ar'],
            'walter.lucero'    => ['Walter Ricardo Lucero', false, 'walter.lucero@gruponaser.com.ar'],
            'facundo.grier'    => ['Facundo Grier', false, 'facundo.grier@gruponaser.com.ar'],
            'daniel.almonacid' => ['Daniel Fernando Almonacid Noriega', false, 'daniel.almonacid@gruponaser.com.ar'],
            'miguel.decroce'   => ['Miguel Ángel De Croce', false, 'miguel.decroce@gruponaser.com.ar'],
            'rodrigo.lagos'    => ['Rodrigo Andrés Lagos', false, 'rodrigo.lagos@gruponaser.com.ar'],
            'marco.echaniz'    => ['Marco Daniel Echaniz', false, 'marco.echaniz@gruponaser.com.ar'],
            'alan.berrocal'    => ['Alan Alberto Berrocal', false, 'alan.berrocal@gruponaser.com.ar'],
            'carlos.bermar'    => ['Carlos Alberto Bermar', false, 'carlos.bermar@gruponaser.com.ar'],
            'lucas.molina'     => ['Lucas Omar Molina', false, 'lucas.molina@gruponaser.com.ar'],               // (*)
        ],

        // Responsables por sector: editan, gestionan y APRUEBAN
        'responsables' => [
            'finanzas'      => ['vanessa', 'ariel.costallat'],
            'rrhh'          => ['noelia', 'vanessa', 'ariel.costallat'],
            'compras'       => ['noelia', 'vanessa', 'ariel.costallat'],
            'ventas'        => ['osvaldo', 'ariel.ibanez', 'ariel.costallat'],
            'gerencia'      => ['victor', 'ariel.costallat'],
            'mantenimiento' => ['juan', 'silvio', 'ariel.costallat'],
            'hseq'          => ['cintia', 'ariel.costallat'],
            'operaciones'   => ['andres.belizon', 'juan', 'ariel.costallat'],
            'sgi'           => ['victor', 'ariel.costallat'],   // SGI: ven todos; solo Gerencia (Bonfils y Costallat) carga y edita documentos
        ],

        // Operadores: ven y completan formularios
        'operadores' => [
            'operaciones' => ['diego.espeche', 'mariano.dominguez', 'sergio.medel', 'javier.peralta', 'ignacio.vece', 'walter.lucero',
                              'facundo.grier', 'daniel.almonacid', 'miguel.decroce', 'rodrigo.lagos', 'marco.echaniz', 'alan.berrocal',
                              'carlos.bermar', 'lucas.molina'],
        ],

        // Permisos de observacion
        'observadores' => [
            'finanzas' => ['noelia', 'carina', 'victor', 'ariel.costallat', 'osvaldo', 'ariel.ibanez'],
            'rrhh'     => ['noelia', 'carina', 'victor', 'ariel.costallat', 'osvaldo', 'ariel.ibanez'],
            'ventas'   => ['noelia', 'carina', 'victor', 'ariel.costallat', 'osvaldo', 'ariel.ibanez'],
            'operaciones' => ['victor'],   // Gerencia puede mirar Operaciones (sector restringido)
        ],

        // Sectores que SOLO ven sus integrantes (el resto de los sectores los ven todos)
        'restringidos' => ['operaciones'],

        // Burbujas: en una burbuja colaborativa, los responsables de cualquiera de sus sectores
        // pueden editar y completar formularios en todos ellos, pero aprueba solo el responsable del área.
        'burbujas' => [
            ['nombre' => 'Burbuja 1 - Dirección',      'colaborativa' => false, 'sectores' => ['gerencia', 'sgi'],                        'descripcion' => 'Visualiza todos los sectores de la burbuja 2.'],
            ['nombre' => 'Burbuja 2 - Administración', 'colaborativa' => true,  'sectores' => ['finanzas', 'rrhh', 'compras', 'ventas'], 'descripcion' => 'Los responsables pueden editar y completar en todos los sectores de la burbuja; aprueba el responsable del área.'],
            ['nombre' => 'Operativa',                  'colaborativa' => false, 'sectores' => ['hseq', 'mantenimiento', 'operaciones'],   'descripcion' => 'Sectores operativos.'],
        ],
    ];
}

function emailNaser(string $clave): string {
    return strtolower($clave) . '@' . NASER_DOMINIO_MAIL;
}

function passwordTemporal(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $num = '23456789';
    $p = '';
    for ($i = 0; $i < 6; $i++) $p .= $abc[random_int(0, strlen($abc) - 1)];
    for ($i = 0; $i < 3; $i++) $p .= $num[random_int(0, strlen($num) - 1)];
    return 'Naser-' . $p;
}

/**
 * Aplica la estructura. Devuelve un informe con los usuarios creados y sus contraseñas temporales.
 */
function aplicarEstructuraNaser(PDO $pdo, bool $enviarMails = false, bool $desactivarPrueba = false, bool $operadoresDesdePersonal = false): array {
    $e = estructuraNaser();
    $sectores = [];
    foreach ($pdo->query('SELECT id, slug FROM sectores')->fetchAll() as $s) $sectores[$s['slug']] = (int)$s['id'];
    $informe = ['creados' => [], 'actualizados' => 0, 'avisos' => [], 'cambios' => []];

    $pdo->beginTransaction();
    try {
        // 1) Burbujas
        $pdo->exec('UPDATE sectores SET burbuja_id = NULL');
        foreach ($e['burbujas'] as $b) {
            $st = $pdo->prepare('SELECT id FROM burbujas WHERE nombre = ?');
            $st->execute([$b['nombre']]);
            $bid = (int)$st->fetchColumn();
            if (!$bid) {
                $pdo->prepare('INSERT INTO burbujas (nombre, descripcion, colaborativa) VALUES (?,?,?)')->execute([$b['nombre'], $b['descripcion'], $b['colaborativa'] ? 1 : 0]);
                $bid = (int)$pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE burbujas SET descripcion = ?, colaborativa = ? WHERE id = ?')->execute([$b['descripcion'], $b['colaborativa'] ? 1 : 0, $bid]);
            }
            foreach ($b['sectores'] as $slug) {
                if (isset($sectores[$slug])) $pdo->prepare('UPDATE sectores SET burbuja_id = ? WHERE id = ?')->execute([$bid, $sectores[$slug]]);
            }
        }
        // 2) Sectores restringidos
        $pdo->exec('UPDATE sectores SET restringido = 0');
        foreach ($e['restringidos'] as $slug) {
            if (isset($sectores[$slug])) $pdo->prepare('UPDATE sectores SET restringido = 1 WHERE id = ?')->execute([$sectores[$slug]]);
        }

        // 3) Personas
        $ids = [];
        foreach ($e['personas'] as $clave => $p) {
            [$nombre, $super] = $p;
            $email = strtolower($p[2] ?? emailNaser($clave));
            $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ?');
            $st->execute([$email]);
            $id = (int)$st->fetchColumn();
            if (!$id) {
                // Si se habia creado con el mail provisorio (clave@gruponaser.com.ar), se le corrige el mail
                $st->execute([emailNaser($clave)]);
                if ($id = (int)$st->fetchColumn()) $pdo->prepare('UPDATE usuarios SET email = ? WHERE id = ?')->execute([$email, $id]);
            }
            if (!$id) {
                $pass = passwordTemporal();
                $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol, activo, debe_cambiar_password) VALUES (?,?,?,?,1,1)')
                    ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT), $super ? 'admin' : 'usuario']);
                $id = (int)$pdo->lastInsertId();
                $informe['creados'][] = ['nombre' => $nombre, 'email' => $email, 'password' => $pass, 'id' => $id];
            } else {
                $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ?, activo = 1 WHERE id = ?')->execute([$nombre, $super ? 'admin' : 'usuario', $id]);
                $informe['actualizados']++;
            }
            $ids[$clave] = $id;
        }

        // 4) Roles por sector (el rol mas alto gana: responsable > operador > observador)
        $peso = ['observador' => 1, 'operador' => 2, 'responsable' => 3];
        $deseado = [];
        foreach (['observadores' => 'observador', 'operadores' => 'operador', 'responsables' => 'responsable'] as $grupo => $rol) {
            foreach ($e[$grupo] as $slug => $claves) {
                if (!isset($sectores[$slug])) { $informe['avisos'][] = "No existe el sector '$slug'."; continue; }
                foreach ($claves as $clave) {
                    if (!isset($ids[$clave])) { $informe['avisos'][] = "'$clave' no está en la lista de personas."; continue; }
                    $actual = $deseado[$clave][$slug] ?? null;
                    if (!$actual || $peso[$rol] > $peso[$actual]) $deseado[$clave][$slug] = $rol;
                }
            }
        }
        $slugDe = array_flip($sectores);
        $nombreSector = [];
        foreach ($pdo->query('SELECT id, nombre FROM sectores')->fetchAll() as $s) $nombreSector[(int)$s['id']] = $s['nombre'];
        foreach ($ids as $clave => $uid) {
            // Roles que tenia antes, para informar que cambio
            $antes = [];
            $st = $pdo->prepare('SELECT sector_id, rol_sector FROM usuario_sector WHERE usuario_id = ?');
            $st->execute([$uid]);
            foreach ($st->fetchAll() as $r) $antes[$slugDe[(int)$r['sector_id']] ?? ''] = $r['rol_sector'];

            $pdo->prepare('DELETE FROM usuario_sector WHERE usuario_id = ?')->execute([$uid]);
            foreach ($deseado[$clave] ?? [] as $slug => $rol) asignarRolSector($pdo, $uid, $sectores[$slug], $rol);

            $dif = [];
            foreach ($deseado[$clave] ?? [] as $slug => $rol) {
                if (($antes[$slug] ?? null) !== $rol) $dif[] = '+ ' . ($nombreSector[$sectores[$slug]] ?? $slug) . ' (' . $rol . ')';
            }
            foreach ($antes as $slug => $rol) {
                if (!isset($deseado[$clave][$slug])) $dif[] = '- ' . ($nombreSector[$sectores[$slug] ?? 0] ?? $slug);
            }
            if ($dif) $informe['cambios'][] = $e['personas'][$clave][0] . ': ' . implode(', ', $dif);
        }

        // 5) Personal operativo con mail corporativo => usuario "operador" de su sector (Personal cargado desde el Excel)
        if ($operadoresDesdePersonal) {
            $yaEstan = array_map('strtolower', array_map(fn($p) => $p[2] ?? '', $e['personas']));
            $rows = $pdo->query("SELECT id, apellido_nombre, email_corporativo, sector_id FROM empleados
                                 WHERE activo = 1 AND sector_id IS NOT NULL AND email_corporativo LIKE '%@gruponaser.com.ar'")->fetchAll();
            foreach ($rows as $emp) {
                $email = strtolower($emp['email_corporativo']);
                if (in_array($email, $yaEstan, true)) continue;
                $partes = array_map('trim', explode(',', $emp['apellido_nombre'], 2));
                $nombre = count($partes) === 2 ? $partes[1] . ' ' . $partes[0] : $emp['apellido_nombre'];
                $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ?');
                $st->execute([$email]);
                $uid = (int)$st->fetchColumn();
                if (!$uid) {
                    $pass = passwordTemporal();
                    $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo, debe_cambiar_password) VALUES (?,?,?,'usuario',1,1)")
                        ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    $uid = (int)$pdo->lastInsertId();
                    $informe['creados'][] = ['nombre' => $nombre, 'email' => $email, 'password' => $pass, 'id' => $uid];
                }
                // Solo se agrega el rol si todavia no tiene uno en ese sector (no pisa responsables)
                $st = $pdo->prepare('SELECT COUNT(*) FROM usuario_sector WHERE usuario_id = ? AND sector_id = ?');
                $st->execute([$uid, (int)$emp['sector_id']]);
                if (!(int)$st->fetchColumn()) asignarRolSector($pdo, $uid, (int)$emp['sector_id'], 'operador');
                $pdo->prepare('UPDATE empleados SET usuario_id = ? WHERE id = ?')->execute([$uid, (int)$emp['id']]);
            }
        }
        // Vincular personal con usuarios por mail
        try { $pdo->exec('UPDATE empleados e JOIN usuarios u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(e.email_corporativo) COLLATE utf8mb4_unicode_ci SET e.usuario_id = u.id'); } catch (Throwable $ex) {}

        // 6) Cuentas de prueba
        if ($desactivarPrueba) {
            $n = $pdo->exec("UPDATE usuarios SET activo = 0 WHERE email LIKE '%@naser.test' AND id <> " . (int)($_SESSION['usuario_id'] ?? 0));
            if ($n) $informe['avisos'][] = "Se desactivaron $n cuenta(s) de prueba @naser.test.";
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    // 7) Bienvenida (aviso interno + mail opcional con la contraseña temporal)
    require_once __DIR__ . '/notificaciones.php';
    foreach ($informe['creados'] as &$c) {
        notificar($pdo, [$c['id']], 'Bienvenido/a a NASER SGI', 'Tu usuario ya está creado. Revisá en "Mi perfil" los sectores en los que participás.', '/php/perfil.php', 'bienvenida', 'bienvenida', false);
        if ($enviarMails) {
            $html = mail_plantilla('Tu acceso a NASER SGI', '<p>Hola ' . htmlspecialchars(explode(' ', $c['nombre'])[0]) . ', ya tenés usuario en el Sistema de Gestión Integrado de NASER.</p>'
                . '<p><strong>Usuario:</strong> ' . htmlspecialchars($c['email']) . '<br><strong>Contraseña temporal:</strong> ' . htmlspecialchars($c['password']) . '</p>'
                . '<p>Al ingresar por primera vez te va a pedir que la cambies.</p>', app_absolute_url('/php/login.php'), 'Ingresar');
            $c['mail'] = enviar_mail($c['email'], 'Tu acceso a NASER SGI', $html);
        }
    }
    unset($c);
    return $informe;
}
