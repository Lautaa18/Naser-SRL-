/* Guardado automático en el servidor; no guarda datos personales en el navegador. */
(function () {
  'use strict';
  var cfg = window.__NASER_EDITOR__;
  if (!cfg) return;
  var id = cfg.id || null, serverRevision = cfg.revision || 0, clientToken = cfg.clientToken;
  var puedeEditar = !!cfg.editar, frame = document.getElementById('fxFrame'), msg = document.getElementById('fxMsg');
  var dirty = document.getElementById('fxDirty'), status = document.getElementById('fxSaveStatus'), refInput = document.getElementById('fxReferencia');
  var sessionPanel = document.getElementById('fxSession');
  var panel = document.getElementById('fxConflict'), dlg = document.getElementById('fxDialog');
  var ocupado = false, reqs = {}, reqN = 0, revisionLocal = 0, timer = null, dirtySince = 0, retries = 0;
  var downloadToken = null;
  var blocked = false, conflictId = null, guardarPendiente = false, copyToken = null, duplicateToken = null, validacionPendiente = false;
  function token() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var data = new Uint8Array(20); crypto.getRandomValues(data);
    return Array.prototype.map.call(data, function (x) { return x.toString(16).padStart(2, '0'); }).join('');
  }
  function estado(texto, tipo) { status.textContent = texto; status.className = 'fx-save-status ' + (tipo || ''); }
  function aviso(texto, tipo) { msg.textContent = texto; msg.className = 'fx-msg ' + (tipo || 'ok'); }
  function cancelarTimer() { if (timer !== null) clearTimeout(timer); timer = null; }
  function programar(delay) {
    cancelarTimer();
    if (!puedeEditar || blocked || dirty.hidden || validacionPendiente || !navigator.onLine) return;
    var plazo = Math.min(Date.now() + 8000, (dirtySince || Date.now()) + 30000);
    timer = setTimeout(function () { timer = null; accion('guardar', true); }, delay === undefined ? Math.max(0, plazo - Date.now()) : delay);
  }
  function cambio() {
    if (!puedeEditar) return;
    revisionLocal++; dirty.hidden = false; if (!dirtySince) dirtySince = Date.now();
    validacionPendiente = false;
    if (!blocked) estado(navigator.onLine ? 'Cambios pendientes' : 'Sin conexión. Conservá esta pestaña abierta.', 'pending');
    programar();
  }
  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin || e.source !== frame.contentWindow || !e.data) return;
    var d = e.data;
    if (d.type === 'naser:height' && Number.isFinite(d.height)) frame.style.height = Math.max(300, d.height + 16) + 'px';
    else if (d.type === 'naser:dirty') cambio();
    else if (d.type === 'naser:collected' && reqs[d.reqId]) { var resolve = reqs[d.reqId]; delete reqs[d.reqId]; resolve(d); }
    else if (d.type === 'naser:save-request' && puedeEditar && !blocked) accion('guardar');
    else if (d.type === 'naser:submit-request' && !cfg.directo && puedeEditar && !blocked) accion('enviar');
    else if (d.type === 'naser:new-request' && !cfg.directo && puedeEditar && !ocupado) {
      if (dirty.hidden || confirm('¿Comenzar un formulario nuevo? Los cambios pendientes del actual se perderán.')) { dirty.hidden = true; window.location.href = cfg.editorUrl + '?f=' + encodeURIComponent(cfg.formulario); }
    }
  });
  refInput.addEventListener('input', cambio);
  window.addEventListener('beforeunload', function (e) { if (!dirty.hidden) { e.preventDefault(); e.returnValue = ''; } });
  window.addEventListener('offline', function () { cancelarTimer(); if (!dirty.hidden) estado('Sin conexión. Conservá esta pestaña abierta.', 'error'); });
  window.addEventListener('online', function () { retries = 0; if (!blocked && !dirty.hidden) { estado('Conexión recuperada. Guardado pendiente.', 'pending'); programar(1000); } });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && !dirty.hidden && !blocked && navigator.onLine) accion('guardar', true); });
  function recolectar(validar) {
    return new Promise(function (ok, mal) {
      var n = ++reqN; reqs[n] = ok;
      frame.contentWindow.postMessage({ type: 'naser:collect', reqId: n, validate: !!validar }, window.location.origin);
      setTimeout(function () { if (reqs[n]) { delete reqs[n]; mal(new Error('El formulario no respondió. Intentá guardar nuevamente.')); } }, 5000);
    });
  }
  function pedirComentario(titulo, texto, obligatorio) {
    return new Promise(function (ok) {
      document.getElementById('fxDialogTitulo').textContent = titulo; document.getElementById('fxDialogTexto').textContent = texto || '';
      var ta = document.getElementById('fxDialogComentario'); ta.value = ''; ta.required = !!obligatorio;
      dlg.returnValue = ''; dlg.onclose = function () { ok(dlg.returnValue === 'ok' ? ta.value.trim() : null); }; dlg.showModal(); ta.focus();
    });
  }
  async function enviarApi(payload) {
    if (cfg.directo) {
      if (payload.accion !== 'guardar') throw new Error('La herramienta solo admite guardar cambios.');
      payload.accion = 'herramienta_guardar';
    }
    payload.id = payload.accion === 'guardar_copia' ? (conflictId || id) : id;
    payload.formulario = cfg.formulario; payload.revision = serverRevision;
    payload.client_token = payload.accion === 'guardar_copia' ? (copyToken || (copyToken = token())) : (payload.accion === 'duplicar' ? (duplicateToken || (duplicateToken = token())) : clientToken);
    var r;
    try { r = await fetch(cfg.api, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf }, body: JSON.stringify(payload), credentials: 'same-origin' }); }
    catch (err) { err.status = 0; throw err; }
    if (r.redirected && /\/(?:login|perfil)\.php(?:\?|$)/.test(r.url)) { var loginError = new Error('Tu sesión terminó. Ingresá de nuevo en otra pestaña antes de guardar.'); loginError.status = 403; throw loginError; }
    var j;
    try { j = await r.json(); } catch (_) { var parseError = new Error(r.status === 403 ? 'La sesión o el permiso cambió. Conservá esta pestaña abierta e ingresá nuevamente.' : 'El servidor no pudo completar la operación. Intentá guardar nuevamente.'); parseError.status = r.status; throw parseError; }
    if (!r.ok || !j.ok) { var error = new Error(j.error || 'No se pudo completar la acción.'); error.status = r.status; error.detalle = j; throw error; }
    return j;
  }
  function actualizarRegistro(j, eraNuevo) {
    id = j.id; serverRevision = j.revision;
    history.replaceState(null, '', cfg.directo ? cfg.directUrl : (location.pathname + '?id=' + id));
    var e = document.getElementById('fxEstado'); e.textContent = j.estado_label; e.className = 'fx-estado fx-' + j.estado;
    var h1 = document.querySelector('.fx-head h1'), numero = h1.querySelector('small');
    if (!numero) { numero = document.createElement('small'); h1.appendChild(numero); } numero.textContent = ' #' + id;
    if (eraNuevo && !cfg.directo) ['duplicar', 'eliminar'].forEach(function (a) {
      if (a === 'eliminar' && !j.permisos.eliminar) return;
      if (document.querySelector('[data-accion="' + a + '"]')) return;
      var b = document.createElement('button'); b.type = 'button'; b.className = 'btn ' + (a === 'eliminar' ? 'danger' : 'secondary'); b.dataset.accion = a; b.textContent = a === 'eliminar' ? 'Eliminar' : 'Duplicar'; document.querySelector('.fx-toolbar-side').appendChild(b);
    });
  }
  function protegerEdicion(activar) {
    document.body.classList.toggle('fx-transition', activar);
    frame.inert = activar; refInput.disabled = activar || !puedeEditar;
    if (activar) { msg.setAttribute('tabindex', '-1'); msg.focus(); }
  }
  async function accion(nombre, automatico) {
    if (nombre === 'descargar_borrador') { descargarBorrador(); return; }
    if (cfg.directo && ['guardar', 'imprimir'].indexOf(nombre) < 0) return;
    if (nombre === 'imprimir' && !cfg.descargar) return;
    if (nombre === 'imprimir') { frame.contentWindow.postMessage({ type: 'naser:print' }, window.location.origin); return; }
    if (blocked && nombre !== 'guardar_copia') { if (!automatico) aviso('Primero resolvé el aviso de conflicto o de permisos. Tus cambios siguen en pantalla.', 'err'); return; }
    if (ocupado) { if (nombre === 'guardar') guardarPendiente = true; return; }
    if (automatico && dirty.hidden) return;
    if (nombre === 'duplicar' && !dirty.hidden) { aviso('Guardá los cambios pendientes antes de duplicar el formulario.', 'err'); return; }
    ocupado = true; cancelarTimer(); var payload = { accion: nombre }, pasoCompleto = false, transicion = false;
    var revisionGuardada = revisionLocal;
    try {
      if (nombre === 'enviar' && !confirm('¿Enviar el formulario para aprobación? Mientras esté pendiente no se podrá modificar.')) return;
      if (nombre === 'aprobar') { var c = await pedirComentario('Aprobar formulario', 'Podés dejar un comentario.', false); if (c === null) return; payload.comentario = c; }
      if (nombre === 'rechazar') { var c2 = await pedirComentario('Rechazar formulario', 'Indicá qué hay que corregir.', true); if (c2 === null) return; if (!c2) { aviso('Escribí el motivo del rechazo.', 'err'); return; } payload.comentario = c2; }
      if (nombre === 'reabrir' && !confirm('¿Reabrir el formulario? Volverá a borrador.')) return;
      if (nombre === 'eliminar' && !confirm('¿Enviar el formulario a la papelera? Un responsable puede recuperarlo.')) return;
      transicion = ['enviar', 'aprobar', 'rechazar', 'reabrir', 'eliminar', 'duplicar'].indexOf(nombre) >= 0;
      if (transicion) protegerEdicion(true);
      document.body.classList.add('fx-busy');
      if (nombre === 'guardar' || nombre === 'enviar' || nombre === 'guardar_copia') {
        estado('Guardando…', 'saving');
        var d = await recolectar(nombre === 'enviar');
        if (d.valid === false) { validacionPendiente = true; estado('Hay datos pendientes de revisar. No se guardó.', 'error'); aviso('Corregí los datos indicados para poder guardar o enviar.', 'err'); return; }
        payload.datos = d.fields; payload.storage = d.storage;
        if (!refInput.value.trim() && d.sugerencia) refInput.value = d.sugerencia;
        payload.referencia = refInput.value.trim(); revisionGuardada = revisionLocal;
      }
      var j = await enviarApi(payload); retries = 0; validacionPendiente = false;
      var eraNuevo = !id;
      if (nombre === 'guardar_copia') { blocked = false; conflictId = null; panel.hidden = true; clientToken = copyToken; copyToken = null; eraNuevo = true; }
      dirty.hidden = revisionLocal === revisionGuardada;
      if (dirty.hidden) dirtySince = 0;
      if (nombre === 'guardar' || nombre === 'guardar_copia') {
        actualizarRegistro(j, eraNuevo);
        if (dirty.hidden) estado('Guardado a las ' + new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }), 'saved');
        else { dirtySince = Date.now(); estado('Cambios pendientes', 'pending'); }
        if (!automatico || nombre === 'guardar_copia') aviso('✔ ' + j.mensaje);
        if (nombre === 'guardar_copia' && dirty.hidden && j.redirect) { pasoCompleto = true; window.location.href = j.redirect; }
        return;
      }
      serverRevision = j.revision; pasoCompleto = true; aviso('✔ ' + j.mensaje);
      setTimeout(function () { window.location.href = j.redirect || (cfg.editorUrl + '?id=' + j.id + '&ok=' + encodeURIComponent(j.mensaje)); }, 400);
    } catch (err) {
      var code = err.status || 0;
      if (code === 409) {
        blocked = true; conflictId = (err.detalle && err.detalle.id) || id; panel.hidden = false;
        if (nombre === 'guardar_copia') copyToken = null;
        estado('Conflicto: tus cambios aún no se guardaron.', 'error'); aviso(err.message, 'err');
      } else if (code === 401 || code === 403 || code === 404) {
        blocked = true; sessionPanel.hidden = false; estado('Guardado detenido. Conservá esta pestaña abierta.', 'error'); aviso(err.message, 'err');
      } else {
        estado(navigator.onLine ? 'No se pudo guardar. Los cambios siguen pendientes.' : 'Sin conexión. Conservá esta pestaña abierta.', 'error'); aviso(err.message || 'No se pudo guardar.', 'err');
        if ((nombre === 'guardar' || nombre === 'guardar_copia') && code !== 400 && !dirty.hidden && navigator.onLine && retries < 5) { retries++; programar(Math.min(30000, 5000 * retries)); }
        else if (code === 400 || retries >= 5) validacionPendiente = true;
      }
    } finally {
      ocupado = false; document.body.classList.remove('fx-busy');
      if (transicion && !pasoCompleto) protegerEdicion(false);
      if (!pasoCompleto && !blocked && !dirty.hidden && !validacionPendiente && timer === null) programar();
      if (guardarPendiente) { guardarPendiente = false; if (!pasoCompleto && !blocked && !dirty.hidden && !validacionPendiente) programar(100); }
    }
  }
  document.getElementById('fxToolbar').addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b) accion(b.getAttribute('data-accion')); });
  panel.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b) accion(b.getAttribute('data-accion')); });
  document.getElementById('fxGuardarCopia').addEventListener('click', function () { accion('guardar_copia'); });
  document.getElementById('fxAbrirActual').addEventListener('click', function () {
    if (!conflictId) return;
    if (dirty.hidden || confirm(cfg.directo ? '¿Abrir la versión actual de la herramienta? Descargá primero tus cambios para conservarlos.' : '¿Abrir la versión actual del servidor? Los cambios pendientes de esta pantalla se perderán. Podés guardarlos como copia antes.')) { dirty.hidden = true; window.location.href = cfg.directo ? cfg.directUrl : (cfg.editorUrl + '?id=' + conflictId); }
  });
  document.getElementById('fxReanudar').addEventListener('click', async function () {
    if (ocupado) return;
    try {
      var r = await fetch(cfg.api + '?sesion=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (r.redirected && /\/(?:login|perfil)\.php(?:\?|$)/.test(r.url)) {
        document.getElementById('fxSessionLink').href = r.url;
        throw new Error('Completá el ingreso o el cambio de contraseña en la otra pestaña y volvé a reanudar.');
      }
      var j = await r.json();
      if (!r.ok || !j.ok || !j.csrf) throw new Error('No se pudo recuperar la sesión. Ingresá nuevamente en otra pestaña.');
      if (j.usuario_id !== cfg.usuarioId) throw new Error('La otra pestaña usa una cuenta diferente. Ingresá con la misma cuenta que abrió este formulario.');
      cfg.csrf = j.csrf; blocked = false; retries = 0; validacionPendiente = false; sessionPanel.hidden = true;
      aviso('Sesión recuperada. Se comprobará que el formulario no haya cambiado.', 'ok');
      if (!dirty.hidden) accion('guardar'); else estado('Sesión recuperada. Sin cambios pendientes.', 'saved');
    } catch (err) { blocked = true; estado('Guardado detenido. Tus cambios siguen en pantalla.', 'error'); aviso(err.message, 'err'); }
  });
  async function descargarBorrador() {
    if (ocupado || !cfg.descargar) return;
    try {
      var d = await recolectar(false);
      if (d.valid === false) throw new Error('Corregí los datos indicados en el formulario antes de descargar el borrador.');
      var contenido = { naser_offline: 1, offline_id: downloadToken || (downloadToken = token()), generado: new Date().toISOString(), formulario: cfg.formulario, referencia: refInput.value, registro_origen: id, revision_origen: serverRevision, datos: d.fields, storage: d.storage, descargado_en: new Date().toISOString() };
      var archivo = new Blob([JSON.stringify(contenido, null, 2)], { type: 'application/json' });
      var url = URL.createObjectURL(archivo), link = document.createElement('a'); link.href = url; link.download = 'borrador-naser-' + cfg.formulario + '.json'; link.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
      aviso('Se descargó una copia de tus cambios. El registro del servidor conserva su versión actual.', 'ok');
    } catch (err) { aviso(err.message, 'err'); }
  }
  document.getElementById('fxDescargarBorrador').addEventListener('click', descargarBorrador);
  var params = new URLSearchParams(location.search);
  if (params.get('ok')) { aviso('✔ ' + params.get('ok')); history.replaceState(null, '', location.pathname + '?id=' + id); }
  estado(puedeEditar ? (id ? 'Guardado. Sin cambios pendientes.' : 'Todavía no guardado.') : 'Modo consulta', puedeEditar && !id ? 'pending' : 'saved');
})();
