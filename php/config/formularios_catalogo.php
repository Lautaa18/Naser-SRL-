<?php
// ==========================================
// Catalogo de formularios digitales
// Cada formulario es un archivo HTML en /formularios/<sector>/<archivo>.html
// Para agregar uno nuevo: copiar el HTML a esa carpeta y sumarlo aca.
// ==========================================
function formulariosCatalogo(): array {
    static $cat = null;
    if ($cat !== null) return $cat;
    $lista = [
        // codigo interno           sector          archivo                                   titulo                                         codigo SGI
        ['hseq-declaracion-incidente', 'hseq', 'hseq/declaracion-incidente.html', 'Declaración ante Incidente', 'PGSN10-F3'],
        ['hseq-listado-incidentes',    'hseq', 'hseq/listado-incidentes.html',    'Listado de Incidentes', 'PGSN10-F2'],
        ['hseq-no-conformidad',        'hseq', 'hseq/no-conformidad.html',        'No Conformidad', 'PGSN04-F5'],
        ['hseq-listado-nc',            'hseq', 'hseq/listado-no-conformidades.html', 'Listado de No Conformidades', ''],
        ['hseq-causa-efecto',          'hseq', 'hseq/causa-efecto.html',          'Diagrama Causa-Efecto', 'PGSN04-F7'],
        ['hseq-visita-gerencial',      'hseq', 'hseq/visita-gerencial.html',      'Visita Gerencial', 'PGSN04-F10'],
        ['hseq-minuta-reunion',        'hseq', 'hseq/minuta-reunion.html',        'Minuta de Reunión', 'PGSN04-F8'],
        ['hseq-plan-anual-auditorias', 'hseq', 'hseq/plan-anual-auditorias.html', 'Plan Anual de Auditorías', 'PGSN04-F2'],
        ['hseq-programa-auditoria',    'hseq', 'hseq/programa-auditoria.html',    'Programa de Auditoría', 'PGSN04-F3'],
        ['hseq-informe-auditoria',     'hseq', 'hseq/informe-auditoria.html',     'Informe de Auditoría', 'PGSN04-F4'],
        ['hseq-revision-direccion',    'hseq', 'hseq/revision-direccion.html',    'Revisión por la Dirección', 'PGSN04-F11'],
        ['hseq-indicadores',           'hseq', 'hseq/indicadores-gestion.html',   'Indicadores de Gestión', ''],
        ['hseq-requisitos-legales',    'hseq', 'hseq/matriz-requisitos-legales.html', 'Matriz de Requisitos Legales', 'PGSN05-F1'],
        ['hseq-aspectos-ambientales',  'hseq', 'hseq/aspectos-impactos-ambientales.html', 'Aspectos e Impactos Ambientales', 'PGSN06-F1'],
        ['hseq-peligros-riesgos',      'hseq', 'hseq/peligros-riesgos.html',      'Identificación de Peligros y Control de Riesgos', 'PGSN07-F1'],
        ['hseq-excelencia-operacional','hseq', 'hseq/excelencia-operacional.html','Programa de Excelencia Operacional', 'PGSN07-F2'],
        ['hseq-simulacro',             'hseq', 'hseq/simulacro.html',             'Planificación e Informe de Simulacro', 'PGSN08-F1'],
        ['hseq-roles-emergencia',      'hseq', 'hseq/roles-emergencia.html',      'Roles ante Emergencias', 'PGSN08-F2'],

        ['rrhh-perfil-puesto',         'rrhh', 'rrhh/perfil-puesto.html',         'Perfil de Puesto', 'PGSN02-F1'],
        ['rrhh-entrevista-ingresante', 'rrhh', 'rrhh/entrevista-ingresante.html', 'Entrevista al Personal Ingresante', 'PGSN02-F2'],
        ['rrhh-informe-medico',        'rrhh', 'rrhh/informe-medico.html',        'Informe Médico Laboral', 'PGSN02-F3'],
        ['rrhh-listado-trabajadores',  'rrhh', 'rrhh/listado-trabajadores.html',  'Listado de Trabajadores', 'PGSN02-F4'],
        ['rrhh-ingreso',               'rrhh', 'rrhh/ingreso.html',               'Formulario de Ingreso', 'PGSN02-F5'],
        ['rrhh-entrega-epp',           'rrhh', 'rrhh/entrega-epp.html',           'Registro de Entrega de EPP', 'PGSN02-F6'],
        ['rrhh-registro-formacion',    'rrhh', 'rrhh/registro-formacion.html',    'Registro de Formación', 'PGSN02-F7'],
        ['rrhh-licencias-vacaciones',  'rrhh', 'rrhh/licencias-vacaciones.html',  'Comunicación de Licencias y Vacaciones', 'PGSN02-F9'],
        ['rrhh-sancion-disciplinaria', 'rrhh', 'rrhh/sancion-disciplinaria.html', 'Sanción Disciplinaria', 'PGSN02-F10'],
        ['rrhh-plan-carrera',          'rrhh', 'rrhh/plan-carrera.html',          'Plan de Carrera', 'PGSN02-F11'],
        ['rrhh-evaluacion-desempeno',  'rrhh', 'rrhh/evaluacion-desempeno.html',  'Evaluación de Desempeño', 'PGSN02-F12'],
        ['rrhh-induccion-ingresante',  'rrhh', 'rrhh/induccion-ingresante.html',  'Inducción al Personal Ingresante', 'PGSN13-F3'],

        ['compras-pedido-materiales',  'compras', 'compras/pedido-materiales.html',  'Pedido de Materiales y Servicios', 'PGSN03-F1'],
        ['compras-listado-proveedores','compras', 'compras/listado-proveedores.html','Listado de Proveedores, Productos y Servicios', 'PGSN03-F2'],
        ['compras-alta-proveedores',   'compras', 'compras/alta-proveedores.html',   'Alta de Proveedores', 'PGSN03-F3'],
        ['compras-evaluacion-proveedores','compras','compras/evaluacion-proveedores.html','Evaluación de Proveedores', ''],
        ['compras-entrega-materiales', 'compras', 'compras/entrega-materiales.html', 'Entrega de Materiales', ''],

        ['ventas-propuesta-economica', 'ventas', 'ventas/propuesta-economica.html', 'Propuesta Económica de Servicio', 'PGSN11-F2'],

        ['operaciones-control-slickline','operaciones','operaciones/control-slickline.html','Control Operativo Slickline', 'SGI-OP-SLK-001'],
        ['operaciones-montaje-well-testing','operaciones','Operaciones/Check List de Montaje de Equipo de Well Testing.html','Check List de Montaje de Equipo de Well Testing', 'POSN03-F2'],
        ['operaciones-evaluacion-riesgos','operaciones','Operaciones/Evaluacion de riesgo y operativos.html','Evaluación de Riesgos Operativos', 'POSN04-F5'],
        ['operaciones-ingreso-egreso-locacion','operaciones','Operaciones/INGRESO Y EGRESO DE LOCACION.html','Ingreso y Egreso de Locación', 'PGSN12-F1'],
        ['operaciones-listado-operaciones','operaciones','Operaciones/listado de operaciones.html','Listado de Operaciones', 'POSN08-F2'],
        ['operaciones-planificacion-semanal','operaciones','Operaciones/Planificacion semanal.html','Planificación Semanal', 'POSN04-F1'],
        ['operaciones-propuesta-economica','operaciones','Operaciones/PROPUESTA ECONOMICA DE SERVICIO.html','Propuesta Económica de Servicio', 'PGSN11-F2'],
        ['operaciones-control-generador','operaciones','Operaciones/REGISTRO DE CONTROL DE GENERADOR.html','Registro de Control de Generador', 'POSN03-F4'],
        ['operaciones-supervision-well-testing','operaciones','Operaciones/REGISTRO DE SUPERVISION DE SERVICIO DE WELL TESTING.html','Registro de Supervisión de Servicio de Well Testing', 'POSN03-F1'],
        ['operaciones-relevamiento-datos','operaciones','Operaciones/RELEVAMIENTO MANUAL DE DATOS.html','Relevamiento Manual de Datos', 'POSN03-F3'],
        ['operaciones-remito','operaciones','Operaciones/Remito naser.html','Remito NASER', 'POSN04-F4'],
        ['operaciones-tren-herramientas','operaciones','Operaciones/tren de herramienta.html','Tren de Herramientas de Slick Line', 'POSN04-F3'],
        ['operaciones-visita-equipos','operaciones','Operaciones/visita de equipo.html','Visita a Equipos', 'POSN08-F3'],
    ];
    $cat = [];
    foreach ($lista as [$codigo, $sector, $archivo, $titulo, $sgi]) {
        $cat[$codigo] = ['codigo' => $codigo, 'sector' => $sector, 'archivo' => $archivo, 'titulo' => $titulo, 'codigo_sgi' => $sgi];
    }
    return $cat;
}

function formularioPorCodigo(string $codigo): ?array {
    return formulariosCatalogo()[$codigo] ?? null;
}
function formulariosDeSector(string $slug): array {
    return array_values(array_filter(formulariosCatalogo(), fn($f) => $f['sector'] === $slug));
}
function rutaFormulario(array $f): string {
    return dirname(__DIR__, 2) . '/formularios/' . $f['archivo'];
}
function estadoFormularioLabel(string $estado): string {
    return match ($estado) {
        'borrador' => 'Borrador', 'enviado' => 'Pendiente de aprobación', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado / a corregir', default => ucfirst($estado)
    };
}
function sectorPorSlug(PDO $pdo, string $slug): ?array {
    foreach (sectoresInfo($pdo) as $s) if ($s['slug'] === $slug) return $s;
    return null;
}
/** Registro con permisos calculados para el usuario actual. */
function permisosRegistro(PDO $pdo, array $reg): array {
    $sid = (int)$reg['sector_id'];
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    $completar = puedeCompletarSector($pdo, $sid);
    $aprobar = puedeAprobarSector($pdo, $sid);
    $esAutor = (int)($reg['creado_por'] ?? 0) === $uid;
    $editable = in_array($reg['estado'], ['borrador', 'rechazado'], true);
    return [
        'ver'      => puedeVerSector($pdo, $sid),
        'editar'   => $editable && ($completar && ($esAutor || puedeEditarSector($pdo, $sid))),
        'enviar'   => $editable && $completar && ($esAutor || puedeEditarSector($pdo, $sid)),
        'aprobar'  => $reg['estado'] === 'enviado' && $aprobar,
        'reabrir'  => in_array($reg['estado'], ['aprobado', 'enviado'], true) && $aprobar,
        'eliminar' => ($reg['estado'] === 'borrador' && $esAutor) || $aprobar,
    ];
}
/** Guarda una linea en el historial del registro. */
function historialFormulario(PDO $pdo, int $registroId, string $accion, ?string $comentario = null): void {
    $pdo->prepare('INSERT INTO formularios_historial (registro_id, usuario_id, accion, comentario) VALUES (?,?,?,?)')
        ->execute([$registroId, (int)($_SESSION['usuario_id'] ?? 0) ?: null, $accion, $comentario]);
}

/** Panel de formularios para incrustar en la pagina de un modulo (rrhh.php, compras.php, ...). */
function formulariosPanel(PDO $pdo, string $slug): void {
    $sector = sectorPorSlug($pdo, $slug);
    if (!$sector) return;
    $sid = (int)$sector['id'];
    $forms = formulariosDeSector($slug);
    $params = [$sid];
    $filtroBorradores = '';
    if (!esAdmin() && !puedeEditarSector($pdo, $sid)) {
        $filtroBorradores = " AND (r.estado <> 'borrador' OR r.creado_por = ?)";
        $params[] = (int)($_SESSION['usuario_id'] ?? 0);
    }
    $st = $pdo->prepare("SELECT r.*, u.nombre AS autor FROM formularios_registros r LEFT JOIN usuarios u ON u.id = r.creado_por WHERE r.sector_id = ?$filtroBorradores ORDER BY r.actualizado_en DESC LIMIT 8");
    $st->execute($params);
    $ultimos = $st->fetchAll();
    $cat = formulariosCatalogo();
    $pend = $pdo->prepare("SELECT COUNT(*) FROM formularios_registros WHERE sector_id = ? AND estado = 'enviado'");
    $pend->execute([$sid]);
    $pendientes = (int)$pend->fetchColumn();
    $puedeCompletar = puedeCompletarSector($pdo, $sid);
    $hub = app_url('/php/formularios/index.php') . '?sector=' . urlencode($slug);
    ?>
    <div class="module-toolbar" id="formularios">
      <h2>Formularios y checklists</h2>
      <div class="top-actions">
        <?php if ($pendientes): ?><a class="count-pill fx-pill-warn" href="<?=h($hub)?>&estado=enviado"><?=$pendientes?> pendiente(s) de aprobación</a><?php endif; ?>
        <a class="btn secondary" href="<?=h($hub)?>">Ver todos los registros</a>
      </div>
    </div>
    <?php if (!$forms): ?>
      <p class="muted">Este sector todavía no tiene formularios digitales.</p>
    <?php else: ?>
    <div class="grid-forms">
      <?php foreach ($forms as $f): ?>
      <div class="card-form">
        <div>
          <?php if ($f['codigo_sgi']): ?><span class="fx-code"><?=h($f['codigo_sgi'])?></span><?php endif; ?>
          <h3><?=h($f['titulo'])?></h3>
        </div>
        <?php if ($puedeCompletar): ?>
          <a class="btn primary" href="<?=h(app_url('/php/formularios/llenar.php') . '?f=' . urlencode($f['codigo']))?>">Completar nuevo</a>
        <?php else: ?>
          <a class="btn secondary" href="<?=h($hub . '&f=' . urlencode($f['codigo']))?>">Ver registros</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($ultimos): ?>
    <div class="table-wrapper" style="margin-bottom:30px">
      <table class="module-table">
        <thead><tr><th>#</th><th>Formulario</th><th>Referencia</th><th>Estado</th><th>Cargado por</th><th>Última modificación</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ultimos as $r): $f = $cat[$r['formulario']] ?? null; ?>
          <tr>
            <td><strong>#<?=(int)$r['id']?></strong></td>
            <td><?=h($f['titulo'] ?? $r['formulario'])?></td>
            <td><?=h($r['referencia'] ?: '—')?></td>
            <td><span class="fx-estado fx-<?=h($r['estado'])?>"><?=h(estadoFormularioLabel($r['estado']))?></span></td>
            <td><?=h($r['autor'] ?? '—')?></td>
            <td><?=h(date('d/m/Y H:i', strtotime($r['actualizado_en'])))?></td>
            <td><a class="btn secondary small" href="<?=h(app_url('/php/formularios/llenar.php') . '?id=' . (int)$r['id'])?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif;
}
