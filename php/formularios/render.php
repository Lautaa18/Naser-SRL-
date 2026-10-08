<?php
// Devuelve el HTML original del formulario con el "puente" que conecta sus campos con la base de datos.
// Se muestra dentro de un iframe en llenar.php
require __DIR__ . '/_init.php';

$codigo = (string)($_GET['f'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$reg = null;
if ($id) {
    $reg = cargarRegistro($pdo, $id);
    if (!$reg) { http_response_code(404); exit('Registro no encontrado.'); }
    $codigo = $reg['formulario'];
}
$form = formularioPorCodigo($codigo);
if (!$form) { http_response_code(404); exit('Formulario no encontrado.'); }
$sector = sectorPorSlug($pdo, $form['sector']);
if (!$sector || !puedeVerSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('Sin acceso.'); }

if ($reg && $reg['estado'] === 'borrador' && (int)$reg['creado_por'] !== (int)$_SESSION['usuario_id'] && !puedeEditarSector($pdo, (int)$reg['sector_id'])) {
    http_response_code(403); exit('Este formulario es un borrador de otra persona.');
}
if ($reg) {
    $perm = permisosRegistro($pdo, $reg);
    $readonly = !$perm['editar'] || isset($_GET['solo_lectura']);
    $fields = json_decode((string)$reg['datos_json'], true) ?: new stdClass();
    $storage = json_decode((string)$reg['storage_json'], true) ?: new stdClass();
} else {
    $readonly = !puedeCompletarSector($pdo, (int)$sector['id']);
    $fields = new stdClass();
    $storage = new stdClass();
}

$archivo = rutaFormulario($form);
if (!is_file($archivo)) { http_response_code(404); exit('Falta el archivo del formulario: ' . h($form['archivo'])); }
$html = file_get_contents($archivo);

// El estado de edición lo administra el sistema, incluso al reabrir o duplicar.
if (!$readonly && is_array($storage)) {
    foreach ($storage as $key => $value) {
        $native = is_string($value) ? json_decode($value, true) : null;
        if (!is_array($native)) continue;
        foreach (['locked', 'finalized', 'finalizado'] as $flag) {
            if (isset($native[$flag]) && is_bool($native[$flag])) $native[$flag] = false;
        }
        $storage[$key] = json_encode($native, JSON_UNESCAPED_UNICODE);
    }
}
$html = preg_replace_callback('/<body\b([^>]*)>/i', static function ($m) {
    $attributes = $m[1];
    if (preg_match('/\bclass\s*=\s*([\'"])(.*?)\1/is', $attributes)) {
        $attributes = preg_replace('/\bclass\s*=\s*([\'"])(.*?)\1/is', 'class="naser-embedded $2"', $attributes, 1);
    } else {
        $attributes .= ' class="naser-embedded"';
    }
    return '<body' . $attributes . '>';
}, $html, 1);

$J = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$cfg = json_encode(['readonly' => $readonly, 'fields' => $fields, 'hasNativeStorage' => (bool)(array)$storage], $J);
$sto = json_encode($storage, $J);

// Se reemplaza el localStorage del navegador por uno en memoria: asi cada registro
// tiene sus propios datos y no se mezclan entre formularios ni entre usuarios.
$head = <<<HTML
<script>
window.__NASER_FORM__ = $cfg;
(function(){
  // El contenedor avisa sobre cambios sin guardar; evitar avisos locales duplicados.
  var listen = window.addEventListener;
  window.addEventListener = function(type, listener, options){
    if (type !== 'beforeunload') return listen.call(this, type, listener, options);
  };
  Object.defineProperty(window, 'onbeforeunload', { configurable: true, get: function(){ return null; }, set: function(){} });
  function crear(inicial){
    var d = {}; for (var k in (inicial||{})) if (Object.prototype.hasOwnProperty.call(inicial,k)) d[k] = String(inicial[k]);
    return {
      getItem: function(k){ return Object.prototype.hasOwnProperty.call(d,k) ? d[k] : null; },
      setItem: function(k,v){ d[k] = String(v); },
      removeItem: function(k){ delete d[k]; },
      clear: function(){ for (var k in d) delete d[k]; },
      key: function(i){ var ks = Object.keys(d); return i < ks.length ? ks[i] : null; },
      get length(){ return Object.keys(d).length; },
      __dump: function(){ var o = {}; for (var k in d) o[k] = d[k]; return o; }
    };
  }
  var ls = crear($sto), ss = crear({});
  try { Object.defineProperty(window, 'localStorage', { value: ls, configurable: true }); } catch (e) {}
  try { Object.defineProperty(window, 'sessionStorage', { value: ss, configurable: true }); } catch (e) {}
  window.__naserLS = ls;
})();
</script>
HTML;
$head .= "\n<link rel=\"stylesheet\" href=\"" . h(asset('/css/form-movil.css')) . "\">";
$bridge = '<script src="' . h(asset('/js/naser-form-bridge.js')) . '"></script>';
$responsive = '<link rel="stylesheet" href="' . h(asset('/css/formularios-responsive.css')) . '">';

if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1] + strlen($m[0][0]);
    $html = substr($html, 0, $pos) . "\n" . $head . "\n" . substr($html, $pos);
} else {
    $html = $head . $html;
}
if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', $responsive . "\n</head>", $html);
} else {
    $html = $responsive . $html;
}
if (stripos($html, '</body>') !== false) {
    $pos = strripos($html, '</body>');
    $html = substr($html, 0, $pos) . $bridge . "\n" . substr($html, $pos);
} else {
    $html .= $bridge;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');
echo $html;
