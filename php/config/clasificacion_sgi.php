<?php
// ==========================================================
// Reglas para repartir los documentos del SGI entre los sectores.
// Se usa desde php/tools/clasificar_documentos.php
//
// El codigo del documento manda: PG-SN-02 / PGSN02-F1 -> numero 02 de los Procedimientos Generales (PG).
//   PG = Procedimiento General   PO = Procedimiento Operativo   MG/MS/MO = Manuales   PE = Politica
// Para cambiar a que sector va cada uno, editar SOLO la tabla de abajo (usar el "slug" del sector).
// ==========================================================

function mapaSectoresSgi(): array {
    return [
        'PG' => [
            '01' => 'gerencia',     // Control de informacion documentada
            '02' => 'rrhh',         // Gestion de las personas
            '03' => 'compras',      // Gestion de compras
            '04' => 'hseq',         // Evaluacion de desempeno y mejora
            '05' => 'hseq',         // Legislacion aplicable
            '06' => 'hseq',         // Gestion ambiental
            '07' => 'hseq',         // Gestion del riesgo
            '08' => 'hseq',         // Respuesta ante emergencias
            '09' => 'hseq',         // Gestion de residuos
            '10' => 'hseq',         // Investigacion de incidentes
            '11' => 'ventas',       // Cotizaciones
            '12' => 'operaciones',  // Bienes y propiedad del cliente
            '13' => 'rrhh',         // Comunicaciones / induccion al personal
        ],
        'PO' => [
            '01' => 'mantenimiento', // Mantenimiento de equipos
            '02' => 'operaciones',   // Procedimiento Slick Line
            '03' => 'operaciones',   // Servicio de Well Testing
            '04' => 'operaciones',   // Planificacion del servicio
            '05' => 'operaciones',   // Manejo del cambio
            '06' => 'mantenimiento', // Uso de vehiculos
            '07' => 'hseq',          // Trabajo en altura
            '08' => 'operaciones',   // Control y trazabilidad
        ],
        'MG' => ['01' => 'gerencia'],     // Manual de gestion
        'MS' => ['01' => 'hseq'],         // Manual de seguridad e higiene
        'MO' => ['01' => 'operaciones'],  // Manual de operaciones de Slickline
        'PE' => ['01' => 'gerencia'],     // Politica de calidad, ambiente, seguridad y salud
    ];
}

/** Si no hay codigo, se busca alguna de estas palabras en el nombre. */
function palabrasClaveSgi(): array {
    return [
        'cotizaci'         => 'ventas',
        'politica'         => 'gerencia',
        'política'         => 'gerencia',
        'organigrama'      => 'gerencia',
        'mapa de procesos' => 'gerencia',
        'listado maestro'  => 'gerencia',
    ];
}

/** Busca un codigo tipo PG-SN-02, PGSN02-F1, POSN-02, MG-SN-01, PE-01. Devuelve [tipo, numero] o null. */
function codigoSgiEn(string $texto): ?array {
    if (preg_match('/(?<![A-Za-z])(PG|PO|MG|MS|MO)[-\s]?SN[-\s]?(\d{2})/i', $texto, $m)) return [strtoupper($m[1]), $m[2]];
    if (preg_match('/(?<![A-Za-z])(PE)[-\s]?(\d{2})(?!\d)/i', $texto, $m)) return [strtoupper($m[1]), $m[2]];
    return null;
}

/**
 * Decide el sector de un documento.
 *   $nombreArchivo: nombre del archivo (o titulo)   $carpetas: nombres de carpetas de arriba hacia abajo
 * Devuelve ['sector' => slug|null, 'motivo' => texto].
 * Prioridad: codigo en el nombre -> codigo en la carpeta (la mas cercana primero) -> palabra clave.
 */
function clasificarDocumentoSgi(string $nombreArchivo, array $carpetas = []): array {
    $mapa = mapaSectoresSgi();
    $probar = function (string $texto, string $donde) use ($mapa) {
        $c = codigoSgiEn($texto);
        if ($c && isset($mapa[$c[0]][$c[1]])) return ['sector' => $mapa[$c[0]][$c[1]], 'motivo' => 'código ' . ($c[0] === 'PE' ? 'PE-' . $c[1] : "$c[0]-SN-$c[1]") . " ($donde)"];
        return null;
    };
    if ($r = $probar($nombreArchivo, 'nombre')) return $r;
    foreach (array_reverse($carpetas) as $carpeta) {
        if ($r = $probar($carpeta, 'carpeta')) return $r;
    }
    $todo = mb_strtolower($nombreArchivo . ' ' . implode(' ', $carpetas));
    foreach (palabrasClaveSgi() as $palabra => $slug) {
        if (str_contains($todo, mb_strtolower($palabra))) return ['sector' => $slug, 'motivo' => "palabra \"$palabra\""];
    }
    return ['sector' => null, 'motivo' => 'sin código ni palabra clave: queda solo en el SGI'];
}
