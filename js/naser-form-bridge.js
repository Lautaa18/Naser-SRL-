/* ==========================================================
   NASER SGI - Puente entre los formularios HTML y el sistema
   ----------------------------------------------------------
   Se inyecta dentro de cada formulario (iframe). Se encarga de:
   - leer todos los campos para guardarlos en la base de datos
   - volver a cargar los valores guardados
   - bloquear el formulario cuando esta enviado / aprobado
   - avisar al sistema cuando se usan los botones propios del
     formulario (Guardar / Finalizar) para guardar en la base
   ========================================================== */
(function () {
  'use strict';
  var cfg = window.__NASER_FORM__ || {};
  var parentOrigin = window.location.origin;
  var SKIP = { button: 1, submit: 1, reset: 1, file: 1, image: 1 };
  var aplicando = false;

  function post(msg) {
    try { window.parent.postMessage(msg, parentOrigin); } catch (e) {}
  }

  function campos() {
    return Array.prototype.slice.call(document.querySelectorAll('input,select,textarea')).filter(function (el) {
      return !SKIP[(el.type || '').toLowerCase()] && !el.closest('[data-naser-ignore]');
    });
  }

  // Clave estable de cada campo: id > name > posicion
  function claves() {
    var usadas = {};
    return campos().map(function (el, i) {
      var t = (el.type || '').toLowerCase(), k;
      if (t === 'radio') k = 'radio:' + (el.name || el.id || i);
      else if (el.id) k = '#' + el.id;
      else if (el.name) k = '@' + el.name + (t === 'checkbox' ? ':' + el.value : '');
      else k = '~' + i;
      var legacyKey = k;
      var row = el.closest('[data-naser-row]');
      if (row && el.dataset.naserField) k = 'row:' + row.dataset.naserRow + ':' + el.dataset.naserField;
      if (t !== 'radio') {
        if (usadas[k] !== undefined) { usadas[k]++; k = k + '|' + usadas[k]; }
        else usadas[k] = 0;
      }
      return { el: el, key: k, legacyKey: legacyKey };
    });
  }

  function recolectar() {
    var out = {};
    claves().forEach(function (c) {
      var el = c.el, t = (el.type || '').toLowerCase();
      if (t === 'radio') { if (el.checked) out[c.key] = el.value; else if (!(c.key in out)) out[c.key] = ''; }
      else if (t === 'checkbox') out[c.key] = !!el.checked;
      else if (el.tagName === 'SELECT' && el.multiple) out[c.key] = Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; });
      else out[c.key] = el.value;
    });
    return out;
  }

  function disparar(el) {
    ['input', 'change'].forEach(function (ev) {
      try { el.dispatchEvent(new Event(ev, { bubbles: true })); } catch (e) {}
    });
  }

  function aplicar(fields) {
    if (!fields) return;
    aplicando = true;
    function aplicarCampo(c) {
      var key = c.key;
      if (!(key in fields)) {
        if (cfg.hasNativeStorage || !(c.legacyKey in fields)) return;
        key = c.legacyKey;
      }
      var el = c.el, t = (el.type || '').toLowerCase(), v = fields[key];
      if (t === 'radio') el.checked = (String(v) === el.value);
      else if (t === 'checkbox') el.checked = !!v;
      else if (el.tagName === 'SELECT' && el.multiple && Array.isArray(v)) Array.prototype.forEach.call(el.options, function (o) { o.selected = v.indexOf(o.value) >= 0; });
      else el.value = (v === null || v === undefined) ? '' : v;
      disparar(el);
    }
    // Los filtros pueden reconstruir la tabla: volver a obtener los campos de fila.
    claves().filter(function(c){ return !c.el.closest('tbody'); }).forEach(aplicarCampo);
    claves().filter(function(c){ return !!c.el.closest('tbody'); }).forEach(aplicarCampo);
    aplicando = false;
  }

  // Sugerencia de "referencia" (trabajador, proveedor, pozo...) para el listado
  function sugerencia() {
    var re = /(trabajador|empleado|apellido|nombre|proveedor|raz[oó]n|cliente|pozo|equipo|puesto|t[ií]tulo|motivo|asunto)/i;
    var lista = claves();
    for (var i = 0; i < lista.length; i++) {
      var el = lista[i].el, t = (el.type || '').toLowerCase();
      if (t === 'checkbox' || t === 'radio' || t === 'date' || t === 'number' || el.tagName === 'SELECT') continue;
      var etiqueta = (el.id || '') + ' ' + (el.name || '') + ' ' + (el.placeholder || '');
      var lbl = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
      if (lbl) etiqueta += ' ' + lbl.textContent;
      else if (el.closest('label')) etiqueta += ' ' + el.closest('label').textContent;
      if (re.test(etiqueta) && String(el.value || '').trim()) return String(el.value).trim().slice(0, 150);
    }
    return '';
  }


  // Muchos formularios guardan su estado interno (listas, filas agregadas) solo cuando
  // se toca su boton "Guardar". Antes de leer los datos se llama a su funcion de guardado
  // (sin mostrar mensajes) para que no se pierda nada.
  var FUNCIONES_GUARDADO = ['guardarFormulario', 'guardar', 'save', 'saveDraft', 'saveForm', 'guardarMinuta', 'guardarGenerales', 'saveLocal', 'saveInterview', 'saveData', 'saveChecklist', 'saveProfile', 'persist'];
  function volcarEstadoPropio() {
    var a = window.alert, c = window.confirm;
    window.alert = function () {}; window.confirm = function () { return true; };
    try {
      FUNCIONES_GUARDADO.forEach(function (n) {
        if (typeof window[n] === 'function') { try { window[n](false); } catch (e) {} }
      });
    } finally { window.alert = a; window.confirm = c; }
  }

  function bloquear() {
    document.querySelectorAll('input,select,textarea,fieldset').forEach(function (el) { el.disabled = true; });
    Array.prototype.forEach.call(document.querySelectorAll('button,[role="button"],input[type="button"],input[type="submit"]'), function (b) {
      if (!/imprimir|pdf|exportar|descargar/i.test(b.textContent || b.value || b.getAttribute('aria-label') || '')) { b.disabled = true; b.style.opacity = '.45'; b.style.pointerEvents = 'none'; }
    });
    document.body.classList.add('naser-readonly');
    if (!document.getElementById('naser-readonly-style')) {
      var st = document.createElement('style'); st.id = 'naser-readonly-style';
      st.textContent = 'input:disabled,select:disabled,textarea:disabled{color:#1f2a24!important;-webkit-text-fill-color:#1f2a24;opacity:1!important;background:#f7f9f8!important;cursor:default}.naser-readonly canvas{pointer-events:none!important}';
      document.head.appendChild(st);
    }
  }

  // Los botones propios del formulario (Guardar / Finalizar) tambien guardan en la base
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('button,input[type="button"],input[type="submit"]') : null;
    if (!b || cfg.readonly || b.hasAttribute('data-naser-managed-action')) return;
    var txt = (b.textContent || b.value || '').trim();
    // Guardar / Finalizar / Agregar / Eliminar filas del formulario => se guarda tambien en el sistema
    if (/guardar|finalizar|agregar|a[ñn]adir|eliminar|quitar|limpiar|borrar|restablecer/i.test(txt)) {
      setTimeout(function () { post({ type: 'naser:dirty' }); post({ type: 'naser:save-request' }); }, 400);
    }
  }, true);

  ['input', 'change'].forEach(function (ev) {
    document.addEventListener(ev, function () { if (!aplicando) post({ type: 'naser:dirty' }); }, true);
  });

  window.addEventListener('message', function (e) {
    if (e.origin !== parentOrigin || e.source !== window.parent || !e.data || typeof e.data !== 'object') return;
    var d = e.data;
    if (d.type === 'naser:collect') {
      if (d.validate) {
        if (typeof window.naserValidate === 'function' && window.naserValidate() === false) {
          post({ type: 'naser:collected', reqId: d.reqId, valid: false });
          return;
        }
        var invalid = campos().find(function (el) { return !el.disabled && el.willValidate && !el.checkValidity(); });
        if (invalid) {
          invalid.reportValidity();
          post({ type: 'naser:collected', reqId: d.reqId, valid: false });
          return;
        }
      }
      volcarEstadoPropio();
      var storage = (window.__naserLS && window.__naserLS.__dump) ? window.__naserLS.__dump() : {};
      post({ type: 'naser:collected', reqId: d.reqId, valid: true, fields: recolectar(), storage: storage, sugerencia: sugerencia() });
    } else if (d.type === 'naser:print') {
      window.print();
    }
  });

  // Altura del iframe
  var ultimaAltura = 0;
  function informarAltura() {
    // Medir contenido, sin sumar nuevamente la altura del viewport del iframe.
    var h = document.body ? Math.ceil(document.body.getBoundingClientRect().height) : 0;
    if (Math.abs(h - ultimaAltura) > 4) { ultimaAltura = h; post({ type: 'naser:height', height: h }); }
  }

  function iniciar() {
    aplicar(cfg.fields || null);
    if (cfg.readonly) bloquear();
    informarAltura();
    prepararTablas();
    if (window.ResizeObserver) new ResizeObserver(informarAltura).observe(document.body);
    if (window.MutationObserver) new MutationObserver(function (changes) {
      if (!changes.some(function (change) { return change.addedNodes.length; })) return;
      prepararTablas(); if (cfg.readonly) bloquear(); informarAltura();
    }).observe(document.body, { childList: true, subtree: true });
    setInterval(informarAltura, 1200);
    post({ type: 'naser:ready' });
  }

  function prepararTablas() {
    document.querySelectorAll('table').forEach(function (table) {
      if (table.closest('.naser-table-scroll')) return;
      var wrap = table.parentElement;
      var overflow = window.getComputedStyle(wrap).overflowX;
      if (!/auto|scroll/.test(overflow)) {
        wrap = document.createElement('div');
        table.parentNode.insertBefore(wrap, table); wrap.appendChild(table);
      }
      wrap.classList.add('naser-table-scroll');
      wrap.setAttribute('role', 'region');
      wrap.setAttribute('aria-label', table.getAttribute('aria-label') || 'Tabla del formulario: deslizá para ver todas las columnas');
      wrap.tabIndex = 0;
    });
  }

  if (document.readyState === 'complete') setTimeout(iniciar, 0);
  else window.addEventListener('load', function () { setTimeout(iniciar, 0); });
})();
