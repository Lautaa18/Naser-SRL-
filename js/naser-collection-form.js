// Las listas guardan después de una modificación válida, incluso con botones de icono.
(function () {
  'use strict';
  var cfg = window.__NASER_FORM__;
  var storage = window.__naserLS;
  if (!cfg || !storage || cfg.readonly) return;
  var timer;
  var setItem = storage.setItem;
  var keys = new Set([
    'naser_cronograma_trabajadores_manual_v3',
    'naser_cronograma_trabajadores_ocultos_v3',
    'naser_cronograma_mensual_registros_v3',
    'NASER_POSN08_F1_HERRAMIENTAS_VERIFICADO_V4',
    'NASER_POSN08_F1_UNIDADES_EXTRA'
  ]);
  // El puente genérico no debe guardar por el texto del botón antes de validar.
  document.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (button) button.setAttribute('data-naser-managed-action', '');
  }, true);
  document.querySelectorAll('button').forEach(function (button) {
    button.setAttribute('data-naser-managed-action', '');
  });
  storage.setItem = function (key, value) {
    var changed = storage.getItem(key) !== String(value);
    setItem.call(storage, key, value);
    if (!changed || !keys.has(key)) return;
    if (cfg.collecting) { clearTimeout(timer); return; }
    window.parent.postMessage({ type: 'naser:dirty' }, window.location.origin);
    clearTimeout(timer);
    timer = setTimeout(function () {
      window.parent.postMessage({ type: 'naser:save-request' }, window.location.origin);
    }, 400);
  };
})();
