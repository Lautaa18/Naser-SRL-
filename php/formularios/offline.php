<?php
// Descarga un formulario como archivo HTML único para completarlo SIN conexión (en el campo).
// Los datos se guardan en un archivo .json que después se sube desde "Importar archivos del campo".
require __DIR__ . '/_init.php';

$codigo = (string)($_GET['f'] ?? '');
$form = formularioPorCodigo($codigo);
if (!$form) { http_response_code(404); exit('Formulario no encontrado.'); }
$sector = sectorPorSlug($pdo, $form['sector']);
if (!$sector || !puedeCompletarFormulario($pdo, $form['codigo'], (int)$sector['id']) || !puedeDescargarSector($pdo, (int)$sector['id'])) { http_response_code(403); exit('No tenés permiso para completar formularios de este sector.'); }
if (esHerramientaDeTrabajo($form['codigo'])) { http_response_code(400); exit('Esta herramienta es una pantalla compartida y se completa desde el sector.'); }
$archivo = rutaFormulario($form);
if (!is_file($archivo)) { http_response_code(404); exit('Falta el archivo del formulario.'); }
$html = file_get_contents($archivo);
$raiz = dirname(__DIR__, 2);
$dirForm = dirname($archivo);

// --- Dejar el archivo autosuficiente: CSS/JS/imágenes locales se incrustan ---
$incrustar = function (string $ruta) use ($dirForm, $raiz): ?string {
    $real = realpath($dirForm . '/' . $ruta);
    if ($real === false || strpos($real, $raiz . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) return null;
    return $real;
};
$html = preg_replace_callback('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>|<link\b[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i', function ($m) use ($incrustar) {
    $href = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
    if (preg_match('#^(https?:)?//#i', $href)) return $m[0];
    $real = $incrustar(strtok($href, '?#'));
    return $real ? '<style>' . str_ireplace('</style', '<\/style', file_get_contents($real)) . '</style>' : $m[0];
}, $html);
$html = preg_replace_callback('/<script\b([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>\s*<\/script>/i', function ($m) use ($incrustar) {
    if (preg_match('#^(https?:)?//#i', $m[2])) return $m[0];
    $real = $incrustar(strtok($m[2], '?#'));
    return $real ? '<script>' . str_ireplace('</script', '<\/script', file_get_contents($real)) . '</script>' : $m[0];
}, $html);
$html = preg_replace_callback('/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i', function ($m) use ($incrustar) {
    if (preg_match('#^(https?:|data:|//)#i', $m[2])) return $m[0];
    $real = $incrustar(strtok($m[2], '?#'));
    if (!$real) return $m[0];
    $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'][strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? null;
    return $mime ? $m[1] . 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($real)) . $m[3] : $m[0];
}, $html);

$html = preg_replace_callback('/<body\b([^>]*)>/i', static function ($m) {
    $a = $m[1];
    if (preg_match('/\bclass\s*=\s*([\'"])(.*?)\1/is', $a)) $a = preg_replace('/\bclass\s*=\s*([\'"])(.*?)\1/is', 'class="naser-embedded naser-offline $2"', $a, 1);
    else $a .= ' class="naser-embedded naser-offline"';
    return '<body' . $a . '>';
}, $html, 1);

$J = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$meta = json_encode(['offline_key' => bin2hex(random_bytes(20)), 'codigo' => $form['codigo'], 'titulo' => $form['titulo'], 'sgi' => $form['codigo_sgi'] ?? '', 'sector' => $sector['nombre'], 'generado' => date('c'), 'usuario' => (string)($_SESSION['nombre'] ?? '')], $J);

$leer = fn(string $r) => file_get_contents($raiz . $r);
$estilos = '<style>' . $leer('/css/form-movil.css') . "\n" . $leer('/css/formularios-responsive.css') . "\n" . <<<'CSS'
body.naser-offline{padding-top:64px!important}
#naserOffBar{position:fixed;top:0;left:0;right:0;z-index:99999;background:#14452f;color:#fff;font:14px/1.3 system-ui,sans-serif;display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:8px 12px;box-shadow:0 2px 8px #0004}
#naserOffBar b{font-size:15px}#naserOffBar span.s{opacity:.85;flex:1;min-width:140px}
#naserOffBar button,#naserOffBar label.b{background:#fff;color:#14452f;border:0;border-radius:6px;padding:8px 12px;font-weight:700;cursor:pointer;font-size:14px}
#naserOffBar label.b input{display:none}
@media print{#naserOffBar{display:none}body.naser-offline{padding-top:0!important}}
CSS . '</style>';

$barra = <<<'HTML'
<div id="naserOffBar" data-naser-ignore>
  <b>NASER · sin conexión</b><span class="s" id="naserOffEstado">Se guarda solo en este equipo mientras completás.</span>
  <button type="button" id="naserOffGuardar">⬇ Guardar archivo para subir</button>
  <button type="button" id="naserOffNuevo">Nuevo formulario</button>
  <label class="b">Abrir archivo guardado<input type="file" id="naserOffAbrir" accept=".json,application/json"></label>
</div>
HTML;

$J2 = $J;
$cabeza = <<<HTML
<script>
window.__NASER_OFFLINE_META__ = $meta;
(function(){
  var M = window.__NASER_OFFLINE_META__, KEY = 'naser-offline:' + M.offline_key, ls;
  try { ls = window.localStorage; ls.setItem(KEY + ':prueba','1'); ls.removeItem(KEY + ':prueba'); } catch (e) { ls = null; }
  var guardado = null;
  try { guardado = ls ? JSON.parse(ls.getItem(KEY) || 'null') : null; } catch (e) {}
  if (!guardado || !guardado.id) guardado = { id: (window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'o' + Date.now() + Math.random().toString(16).slice(2)), fields: null, storage: {} };
  window.__NASER_OFFLINE_STATE__ = guardado;
  window.__NASER_OFFLINE_SAVE__ = function(state) {
    if (!ls) throw new Error('No hay almacenamiento disponible.');
    if (state) ls.setItem(KEY, JSON.stringify(state)); else ls.removeItem(KEY);
  };
  window.__NASER_FORM__ = { readonly: false, offline: true, canDownload: true, fields: guardado.fields || {}, hasNativeStorage: !!Object.keys(guardado.storage || {}).length };
  window.postMessage = function (m) { if (typeof window.__naserOfflineMsg === 'function') window.__naserOfflineMsg(m); };
  // Cada archivo descargado tiene un almacén propio. No lee borradores de otras cuentas o formularios.
  function crear(inicial) {
    var d = {}; Object.keys(inicial || {}).forEach(function(k){ d[k] = String(inicial[k]); });
    return {
      getItem: function(k){ return Object.prototype.hasOwnProperty.call(d,k) ? d[k] : null; },
      setItem: function(k,v){ d[k] = String(v); }, removeItem: function(k){ delete d[k]; },
      clear: function(){ d = {}; }, key: function(i){ return Object.keys(d)[i] || null; },
      get length(){ return Object.keys(d).length; },
      __dump: function(){ var o = {}; Object.keys(d).forEach(function(k){ o[k] = d[k]; }); return o; }
    };
  }
  var nativeStore = crear(guardado.storage), sessionStore = crear({});
  try { Object.defineProperty(window, 'localStorage', { value: nativeStore, configurable: true }); } catch (e) {}
  try { Object.defineProperty(window, 'sessionStorage', { value: sessionStore, configurable: true }); } catch (e) {}
  window.__naserLS = nativeStore;
})();
</script>
HTML;

$pie = <<<'HTML'
<script>
(function(){
  var M = window.__NASER_OFFLINE_META__, S = window.__NASER_OFFLINE_STATE__;
  var est = document.getElementById('naserOffEstado'), timer = null;
  function msg(t) { if (est) est.textContent = t; }
  function persistir(r) {
    S.fields = r.fields; S.storage = r.storage; S.actualizado = new Date().toISOString();
    try { window.__NASER_OFFLINE_SAVE__(S); msg('Guardado en este equipo · ' + new Date().toLocaleTimeString().slice(0, 5)); }
    catch (e) { msg('No se pudo guardar en el equipo: usá "Guardar archivo para subir".'); }
  }
  window.__naserOfflineMsg = function (m) {
    if (!m || (m.type !== 'naser:dirty' && m.type !== 'naser:save-request')) return;
    clearTimeout(timer);
    timer = setTimeout(function () { var r = window.__naserCollect(false); if (r && r.valid) persistir(r); }, 600);
  };
  function nombreArchivo() {
    var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'NASER_' + M.codigo + '_' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '_' + p(d.getHours()) + p(d.getMinutes()) + '.json';
  }
  document.getElementById('naserOffGuardar').addEventListener('click', function () {
    var r = window.__naserCollect(false);
    if (!r || !r.valid) { msg('Revisá el formulario: hay algo que impide guardarlo.'); return; }
    persistir(r);
    var datos = { naser_offline: 1, offline_id: S.id, formulario: M.codigo, titulo: M.titulo, generado: new Date().toISOString(), descargado_por: M.usuario, referencia: r.sugerencia || '', datos: r.fields, storage: r.storage };
    var blob = new Blob([JSON.stringify(datos)], { type: 'application/json' }), a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = nombreArchivo(); document.body.appendChild(a); a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    msg('Archivo guardado: ' + a.download + '. Subilo desde el sistema cuando tengas conexión.');
  });
  document.getElementById('naserOffNuevo').addEventListener('click', function () {
    if (!confirm('¿Comenzar otro formulario? Guardá primero el archivo del actual para conservarlo.')) return;
    clearTimeout(timer);
    try { window.__NASER_OFFLINE_SAVE__(null); } catch (e) {}
    location.reload();
  });
  document.getElementById('naserOffAbrir').addEventListener('change', function (e) {
    var f = e.target.files[0]; if (!f) return;
    var rd = new FileReader();
    rd.onload = function () {
      try {
        var d = JSON.parse(rd.result);
        if (!d || d.naser_offline !== 1 || d.formulario !== M.codigo) throw new Error('Ese archivo es de otro formulario.');
        if (!d.datos || typeof d.datos !== 'object' || !/^[A-Za-z0-9_-]{8,128}$/.test(d.offline_id || '')) throw new Error('El archivo no tiene datos válidos.');
        S.id = d.offline_id; S.fields = d.datos; S.storage = d.storage || {};
        try { window.__NASER_OFFLINE_SAVE__(S); } catch (x) { throw new Error('Este navegador no permite abrir archivos guardados. Importá el archivo al sistema cuando tengas conexión.'); }
        location.reload();
      } catch (err) { msg(err.message || 'No se pudo leer el archivo.'); }
    };
    rd.readAsText(f);
  });
})();
</script>
HTML;

$bridge = '<script>' . str_ireplace('</script', '<\/script', $leer('/js/naser-form-bridge.js')) . '</script>';
if (in_array($codigo, ['operaciones-diagrama-trabajo', 'operaciones-stock-herramientas'], true)) {
    $bridge = '<script>' . str_ireplace('</script', '<\/script', $leer('/js/naser-collection-form.js')) . '</script>' . $bridge;
}

if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1] + strlen($m[0][0]);
    $html = substr($html, 0, $pos) . "\n" . $cabeza . "\n" . substr($html, $pos);
} else { $html = $cabeza . $html; }
$html = stripos($html, '</head>') !== false ? str_ireplace('</head>', $estilos . "\n</head>", $html) : $estilos . $html;
if (preg_match('/<body\b[^>]*>/i', $html)) $html = preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . addcslashes($barra, '\\$'), $html, 1);
else $html = $barra . "\n<style>body{padding-top:64px}</style>\n" . $html; // formularios sin <body>: la barra va arriba de todo
$fin = $bridge . $pie;
if (stripos($html, '</body>') !== false) { $pos = strripos($html, '</body>'); $html = substr($html, 0, $pos) . $fin . "\n" . substr($html, $pos); }
else $html .= $fin;
if (stripos($html, '<meta charset') === false) $html = '<meta charset="utf-8">' . $html;

registrarActividad($pdo, 'formulario_descarga_offline', $form['titulo']);
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="NASER_' . $codigo . '.html"');
header('Cache-Control: no-store');
echo $html;
