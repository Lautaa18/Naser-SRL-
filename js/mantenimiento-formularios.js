(function () {
  'use strict';
  const config = JSON.parse(document.getElementById('maintenance-config').textContent);
  const root = document.getElementById('maintenance-form');
  const embedded = !!window.__NASER_FORM__;
  const readonly = !!(window.__NASER_FORM__ || {}).readonly;
  const key = 'naserMaintenance:' + config.id;
  let saved = {}, serial = 0, finished = false;
  try { saved = JSON.parse(localStorage.getItem(key) || '{}'); } catch (_) {}
  const rows = {};
  const node = (tag, text, cls) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (cls) el.className = cls; return el; };
  const post = (type) => { if (embedded) window.parent.postMessage({ type }, window.location.origin); };
  const message = (text, error) => { const el = document.getElementById('mf-message'); el.textContent = text; el.classList.toggle('error', !!error); };
  const controls = () => Array.from(root.querySelectorAll('input,select,textarea'));
  function button(text, action, cls) {
    const b = node('button', text, 'mf-btn ' + (cls || ''));
    b.type = 'button'; b.dataset.naserManagedAction = 'true';
    b.addEventListener('click', action); return b;
  }
  function control(def, id, caption) {
    let input;
    if (def.type === 'select') {
      input = node('select'); input.appendChild(new Option('Seleccionar…', ''));
      (def.options || []).forEach(opt => input.appendChild(new Option(opt, opt)));
    } else if (def.type === 'textarea') { input = node('textarea'); input.rows = 2; }
    else { input = node('input'); input.type = def.type || 'text'; if (input.type === 'number') { input.step = def.step || 'any'; input.min = def.min === undefined ? '0' : def.min; } }
    input.id = id; input.name = id; input.required = !!def.required;
    input.setAttribute('aria-label', caption || def.label);
    if (saved.fields && Object.prototype.hasOwnProperty.call(saved.fields, id)) input.value = saved.fields[id];
    const printed = node('span', '', 'mf-print-value'); printed.setAttribute('aria-hidden', 'true');
    input.addEventListener('input', update); input.addEventListener('change', update);
    const wrap = node('div'); wrap.append(input, printed); return wrap;
  }
  function fields(defs, parent, prefix) {
    const box = node('div', undefined, 'mf-fields');
    (defs || []).forEach(def => {
      const label = node('label', undefined, 'mf-field' + (def.type === 'textarea' ? ' full' : ''));
      const id = (prefix || 'general') + '-' + def.key;
      label.htmlFor = id; label.append(node('span', def.label + (def.required ? ' *' : '')), control(def, id)); box.append(label);
    });
    parent.append(box);
  }
  function sectionCard(id, title, description) {
    const card = node('section', undefined, 'mf-card'); card.id = id;
    const head = node('header', undefined, 'mf-card-head'), copy = node('div');
    copy.append(node('h2', title)); if (description) copy.append(node('p', description)); head.append(copy); card.append(head); root.append(card); return card;
  }
  function rowId() { serial++; return 'r' + Date.now().toString(36) + '-' + serial; }
  function drawRow(section, item, tbody, index) {
    const tr = node('tr'); tr.dataset.naserRow = section.key + ':' + item.key;
    const number = node('td', String(index + 1), 'mf-number'); tr.append(number);
    if (section.type !== 'log') {
      const heading = node('th', item.label); heading.scope = 'row'; if (item.detail) heading.append(node('small', item.detail)); tr.append(heading);
    }
    section.columns.forEach(col => {
      const td = node('td'); const fieldDef = Object.assign({}, col, {required: col.required === undefined ? section.type !== 'log' : col.required});
      const cell = control(fieldDef, section.key + '-' + item.key + '-' + col.key, (item.label || 'Fila ' + (index + 1)) + ': ' + col.label);
      const input = cell.querySelector('input,select,textarea'); input.dataset.naserField = col.key;
      if (section.type === 'log' && !Object.prototype.hasOwnProperty.call(saved.fields || {}, input.id) && item.values && item.values[col.key] !== undefined) input.value = item.values[col.key];
      td.append(cell); tr.append(td);
    });
    if (section.type === 'log') {
      const td = node('td', undefined, 'mf-row-actions');
      td.append(button('Eliminar fila', () => {
        if (readonly || finished || !confirm('¿Eliminar esta fila? Los demás registros se conservarán.')) return;
        rows[section.key] = rows[section.key].filter(r => r.key !== item.key); tr.remove();
        if (!rows[section.key].length) addRow(section, tbody);
        renumber(tbody); post('naser:dirty'); update();
      }, 'danger')); tr.append(td);
    }
    tbody.append(tr);
  }
  function renumber(tbody) { Array.from(tbody.children).forEach((tr, i) => { tr.firstChild.textContent = String(i + 1); }); }
  function addRow(section, tbody) {
    const item = { key: rowId() }; rows[section.key].push(item); drawRow(section, item, tbody, rows[section.key].length - 1);
  }
  function update() {
    // Solo exigir la revisión de las columnas de extintores identificados.
    if (config.id === 'inspeccion-extintores') {
      for (let i = 1; i <= 6; i++) {
        const identity = document.getElementById('general-extintor' + i);
        if (identity) root.querySelectorAll('[data-naser-field="extintor' + i + '"]').forEach(el => { el.required = identity.value.trim() !== ''; });
      }
    }
    const required = controls().filter(el => el.required), count = required.filter(el => el.value.trim() !== '').length;
    document.getElementById('mf-progress-count').textContent = count + ' / ' + required.length + ' campos obligatorios completos';
    const progress = document.getElementById('mf-progress'); progress.max = Math.max(required.length, 1); progress.value = count;
    controls().forEach(el => {
      if (el.tagName === 'SELECT') el.dataset.result = /^(No cumple|Incorrecto|Malo|F|No|Rechazad[oa]|M|X)$/i.test(el.value) ? 'negative' : /^(Sí cumple|Cumple|Correcto|Bueno|OK|Sí|Aceptad[oa]|B|S)$/i.test(el.value) ? 'positive' : '';
      el.nextElementSibling.textContent = el.value || '—';
    });
  }
  function snapshot() {
    const data = { fields: {}, rows: {}, finished: finished && !embedded };
    controls().forEach(el => { data.fields[el.id] = el.value; });
    Object.keys(rows).forEach(k => { data.rows[k] = rows[k].map(row => ({ key: row.key })); }); return data;
  }
  window.saveChecklist = function (notify) {
    if (readonly) return;
    localStorage.setItem(key, JSON.stringify(snapshot()));
    if (notify === true) { if (embedded) post('naser:save-request'); else message('Checklist guardado. Podés continuar completándolo más tarde.'); }
  };
  function validate() {
    update();
    if (config.id === 'inspeccion-extintores' && !controls().some(el => /^general-extintor[1-6]$/.test(el.id) && el.value.trim())) {
      message('Identificá al menos un extintor para completar la inspección.', true); document.getElementById('general-extintor1').focus(); return false;
    }
    const invalid = controls().find(el => !el.disabled && !el.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.scrollIntoView({block:'center'}); message('Completá los campos obligatorios antes de finalizar.', true); return false; }
    return true;
  }
  // Permite que el envío desde la barra superior use la misma validación.
  window.naserValidate = validate;
  function lock() { finished = true; root.classList.add('mf-readonly'); controls().forEach(el=>el.disabled=true); root.querySelectorAll('button').forEach(b=>{if(!/imprimir|comenzar/i.test(b.textContent)) b.disabled=true;}); }
  function finish() {
    if (readonly || finished || !validate()) return;
    if (embedded) { post('naser:submit-request'); return; }
    if (!confirm('¿Finalizar el checklist? Se bloqueará su edición.')) return;
    finished = true; window.saveChecklist(false); lock(); message('Checklist finalizado.');
  }
  function clear() {
    if (readonly || !confirm('¿Limpiar los datos del formulario actual? Guardá después para conservar el cambio.')) return;
    finished = false; root.classList.remove('mf-readonly');
    controls().forEach(el=>{el.value='';el.disabled=false;});
    root.querySelectorAll('button').forEach(b=>b.disabled=false);
    config.sections.filter(s=>s.type==='log').forEach(section=>{
      const tbody=document.getElementById('rows-'+section.key); tbody.replaceChildren();rows[section.key]=[];saved={};addRow(section,tbody);
    });
    localStorage.removeItem(key); update(); post('naser:dirty'); message('Datos limpiados. Podés comenzar a completar el formulario.');
  }
  const brand = node('div', undefined, 'mf-brand'); const logo = node('img'); logo.src='../../img/logo-naser.png';logo.alt='NASER';brand.append(logo,node('span','SERVICIOS NASER SRL · MANTENIMIENTO'));root.append(brand);
  const hero=node('header',undefined,'mf-hero'),intro=node('div');intro.append(node('p','INSPECCIÓN Y CONTROL · MANTENIMIENTO','mf-eyebrow'),node('h1',config.title));if(config.description)intro.append(node('p',config.description));
  const code=node('div',undefined,'mf-code');code.append(node('strong',config.code||'MANTENIMIENTO'),node('span',config.revision||''));hero.append(intro,code);root.append(hero);
  const progressBox=node('div',undefined,'mf-progress');const count=node('span','','');count.id='mf-progress-count';const progress=node('progress');progress.id='mf-progress';progress.setAttribute('aria-label','Progreso del formulario');progressBox.append(count,progress);root.append(progressBox);
  const jump=node('nav',undefined,'mf-jump');jump.setAttribute('aria-label','Secciones del formulario');config.sections.forEach(s=>{const a=node('a',s.title);a.href='#section-'+s.key;jump.append(a);});root.append(jump);
  fields(config.generalFields,sectionCard('datos-generales','Datos generales'),'general');
  config.sections.forEach(section=>{
    const card=sectionCard('section-'+section.key,section.title,section.description);
    if(section.type==='log') card.firstChild.append(button('Agregar fila',()=>{if(readonly||finished)return;addRow(section,tbody);post('naser:dirty');update();}));
    card.append(node('p','Deslizá la tabla horizontalmente para ver todas las columnas.','mf-help'));
    const scroll=node('div',undefined,'mf-scroll');scroll.tabIndex=0;scroll.setAttribute('role','region');scroll.setAttribute('aria-label',section.title);
    const table=node('table',undefined,'mf-table');table.setAttribute('aria-label',section.title);const thead=node('thead'),head=node('tr');
    ['N°',...(section.type==='log'?[]:[section.itemLabel||'Elemento / criterio']),...section.columns.map(c=>c.label),...(section.type==='log'?['Acciones']:[])].forEach((label,i)=>{const th=node('th',label);th.scope='col';if(i===0)th.className='mf-number';if(label==='Acciones')th.className='mf-row-actions';head.append(th);});thead.append(head);table.append(thead);
    const tbody=node('tbody');tbody.id='rows-'+section.key;table.append(tbody);scroll.append(table);card.append(scroll);
    if(section.type==='log'){
      rows[section.key]=(saved.rows&&Array.isArray(saved.rows[section.key])&&saved.rows[section.key].length?saved.rows[section.key]:Array.from({length:section.initialRows||1},()=>({key:rowId()}))).map(r=>({key:r.key}));
      rows[section.key].forEach((r,i)=>drawRow(section,r,tbody,i));
    }else(section.rows||[]).forEach((r,i)=>drawRow(section,r,tbody,i));
    if(section.footerFields)fields(section.footerFields,card,section.key+'-resultado');
  });
  if(config.notes&&config.notes.length){const notes=node('aside',undefined,'mf-notes');config.notes.forEach(text=>notes.append(node('p',text)));root.append(notes);}
  const final=sectionCard('observaciones-responsables','Observaciones y responsables');fields([{key:'observaciones',label:config.observationsLabel||'Observaciones',type:'textarea'},...(config.finalFields||[])],final,'final');
  const actions=node('div',undefined,'mf-actions');actions.append(button('GUARDAR',()=>window.saveChecklist(true),'primary'),button('LIMPIAR',clear),button('FINALIZAR CHECKLIST',finish,'dark'),button('COMENZAR NUEVO',()=>{if(embedded)post('naser:new-request');else clear();}),button('IMPRIMIR / PDF',()=>{update();window.print();}));root.append(actions);
  const status=node('div',undefined,'mf-message');status.id='mf-message';status.setAttribute('role','status');status.setAttribute('aria-live','polite');root.append(status, node('footer','NASER SRL · Sistema de Gestión Integrado · '+(config.code||config.title),'mf-footer'));
  update();if(readonly || (!embedded&&saved.finished))lock();
  window.addEventListener('beforeprint',update);
})();
