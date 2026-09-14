<?php
// NASER: navegación nativa de módulos por sector.
function moduloUrlSector(string $slug): ?string {
    return match($slug){
        'rrhh' => app_url('/php/rrhh.php'),
        'finanzas' => app_url('/php/finanzas.php'),
        'compras' => app_url('/php/compras.php'),
        'ventas' => app_url('/php/ventas.php'),
        default => null,
    };
}

function botonModuloSector(string $slug): void {
    $url = moduloUrlSector($slug);
    if (!$url) return;

    $nombres = [
        'rrhh' => 'Gestión RRHH',
        'finanzas' => 'Gestión financiera',
        'compras' => 'Gestión de compras',
        'ventas' => 'Gestión comercial',
    ];

    echo '<a class="btn primary" href="'.h($url).'">'.h($nombres[$slug]).'</a>';
}
