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
      if (t !== 'radio') {
        if (usadas[k] !== undefined) { usadas[k]++; k = k + '|' + usadas[k]; }
        else usadas[k] = 0;
      }
      return { el: el, key: k };
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
    claves().forEach(function (c) {
      if (!(c.key in fields)) return;
      var el = c.el, t = (el.type || '').toLowerCase(), v = fields[c.key];
      if (t === 'radio') el.checked = (String(v) === el.value);
      else if (t === 'checkbox') el.checked = !!v;
      else if (el.tagName === 'SELECT' && el.multiple && Array.isArray(v)) Array.prototype.forEach.call(el.options, function (o) { o.selected = v.indexOf(o.value) >= 0; });
      else el.value = (v === null || v === undefined) ? '' : v;
      disparar(el);
    });
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
  var FUNCIONES_GUARDADO = ['guardarFormulario', 'guardar', 'saveForm', 'guardarMinuta', 'guardarGenerales', 'saveLocal', 'saveInterview', 'saveData', 'saveChecklist', 'saveProfile', 'persist'];
  function volcarEstadoPropio() {
    var a = window.alert, c = window.confirm;
    window.alert = function () {}; window.confirm = function () { return true; };
    try {
      FUNCIONES_GUARDADO.forEach(function (n) {
        if (typeof window[n] === 'function') { try { window[n](); } catch (e) {} }
      });
    } finally { window.alert = a; window.confirm = c; }
  }

  var RE_ACCIONES = /(guardar|finalizar|limpiar|agregar|nuev|eliminar|borrar|quitar|editar|habilitar|cancelar ed|restablecer|importar)/i;

  function bloquear() {
    campos().forEach(function (el) { el.disabled = true; });
    Array.prototype.forEach.call(document.querySelectorAll('button,[role="button"],input[type="button"],input[type="submit"]'), function (b) {
      if (RE_ACCIONES.test(b.textContent || b.value || '')) { b.disabled = true; b.style.opacity = '.45'; b.style.pointerEvents = 'none'; }
    });
    var st = document.createElement('style');
    st.textContent = 'input:disabled,select:disabled,textarea:disabled{color:#1f2a24!important;-webkit-text-fill-color:#1f2a24;opacity:1!important;background:#f7f9f8!important;cursor:default}';
    document.head.appendChild(st);
  }

  // Los botones propios del formulario (Guardar / Finalizar) tambien guardan en la base
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('button,input[type="button"],input[type="submit"]') : null;
    if (!b || cfg.readonly) return;
    var txt = (b.textContent || b.value || '').trim();
    // Guardar / Finalizar / Agregar / Eliminar filas del formulario => se guarda tambien en el sistema
    if (/guardar|finalizar|agregar|a[ñn]adir|eliminar|quitar/i.test(txt) && !/limpiar/i.test(txt)) {
      setTimeout(function () { post({ type: 'naser:save-request', finalizar: /finalizar/i.test(txt) }); }, 400);
    }
  }, true);

  ['input', 'change'].forEach(function (ev) {
    document.addEventListener(ev, function () { if (!aplicando) post({ type: 'naser:dirty' }); }, true);
  });

  window.addEventListener('message', function (e) {
    if (e.origin !== parentOrigin || !e.data || typeof e.data !== 'object') return;
    var d = e.data;
    if (d.type === 'naser:collect') {
      volcarEstadoPropio();
      var storage = (window.__naserLS && window.__naserLS.__dump) ? window.__naserLS.__dump() : {};
      post({ type: 'naser:collected', reqId: d.reqId, fields: recolectar(), storage: storage, sugerencia: sugerencia() });
    } else if (d.type === 'naser:print') {
      window.print();
    }
  });

  // Altura del iframe
  var ultimaAltura = 0;
  function informarAltura() {
    var h = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
    if (Math.abs(h - ultimaAltura) > 4) { ultimaAltura = h; post({ type: 'naser:height', height: h }); }
  }

  function iniciar() {
    aplicar(cfg.fields || null);
    if (cfg.readonly) bloquear();
    informarAltura();
    if (window.ResizeObserver) new ResizeObserver(informarAltura).observe(document.documentElement);
    setInterval(informarAltura, 1200);
    post({ type: 'naser:ready' });
  }

  if (document.readyState === 'complete') setTimeout(iniciar, 0);
  else window.addEventListener('load', function () { setTimeout(iniciar, 0); });
})();
