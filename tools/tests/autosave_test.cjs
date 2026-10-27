/* Run: node tools/tests/autosave_test.cjs. No network, browser storage or dependencies. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/naser-autosave.js'), 'utf8');
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve=yes; reject=no; }); return {promise, resolve, reject}; }
function response(status=200, data={}, extra={}) { return {ok:status>=200&&status<300,status,redirected:false,json:async()=>data,...extra}; }
function saved(id=41, revision=1) { return response(200,{ok:true,id,revision,estado:'borrador',estado_label:'Borrador',permisos:{eliminar:true},mensaje:'Guardado.'}); }
function editor(options={}) {
  let now=100000,timerId=0,randomId=0;
  const timers=new Map(),listeners=new Map(),elements=new Map(),calls=[],replies=[];
  const forbidden=()=>{throw new Error('Los datos no deben persistirse en el navegador.');};
  class Element {
    constructor(id) {
      this.id=id; this.hidden=['fxDirty','fxConflict','fxSession'].includes(id);
      this.value=''; this.style={}; this.dataset={}; this.children=[]; this.handlers={};
      const classes=new Set();
      this.classList={add:v=>classes.add(v),remove:v=>classes.delete(v),toggle:(v,on)=>on?classes.add(v):classes.delete(v)};
    }
    addEventListener(name,fn) {(this.handlers[name]||=[]).push(fn);}
    dispatch(name,event={}) {for(const fn of this.handlers[name]||[])fn(event);}
    setAttribute(name,value) {this[name]=value;}
    getAttribute(name) {return name==='data-accion'?this.dataset.accion:this[name];}
    closest() {return this;}
    appendChild(child) {this.children.push(child);}
    querySelector(selector) {return selector==='small'?this.children.find(el=>el.tagName==='small'):null;}
    focus() {} click() {this.dispatch('click');} showModal() {}
  }
  const get=id=>{if(!elements.has(id))elements.set(id,new Element(id));return elements.get(id);};
  const location={origin:'http://qa.test',pathname:'/php/formularios/llenar.php',search:'',href:''};
  const cfg={id:null,revision:0,clientToken:'qa-initial-token-1234567890',editar:true,formulario:'hseq-minuta-reunion',
    csrf:'csrf-original',usuarioId:7,descargar:true,api:'/php/formularios/api.php',editorUrl:location.pathname,...options};
  const listen=(target,name,fn)=>{const key=target+':'+name;if(!listeners.has(key))listeners.set(key,[]);listeners.get(key).push(fn);};
  const win={__NASER_EDITOR__:cfg,location,addEventListener:(name,fn)=>listen('window',name,fn),
    crypto:{randomUUID:()=>'qa-random-token-1234567890-'+(++randomId)}};
  Object.defineProperties(win,{localStorage:{get:forbidden},sessionStorage:{get:forbidden}});
  const document={body:get('body'),visibilityState:'visible',getElementById:get,
    addEventListener:(name,fn)=>listen('document',name,fn),
    querySelector:selector=>selector==='.fx-head h1'?get('h1'):selector==='.fx-toolbar-side'?get('side'):
      get('side').children.find(el=>selector==='[data-accion="'+el.dataset.accion+'"]')||null,
    createElement:tag=>{const el=new Element();el.tagName=tag;return el;}};
  const navigator={onLine:true};
  const clock={setTimeout:(fn,delay=0)=>{const id=++timerId;timers.set(id,{when:now+delay,fn});return id;},clearTimeout:id=>timers.delete(id)};
  class FakeDate extends Date {constructor(...args){super(...(args.length?args:[now]));}static now(){return now;}}
  const emit=(target,name,data={})=>{for(const fn of listeners.get(target+':'+name)||[])fn(data);};
  const frame=get('fxFrame');
  let fields={'#nombre':'Datos locales'};
  const storage={lista:'["fila local"]'};
  frame.contentWindow={postMessage:(message,origin)=>{
    assert.equal(origin,location.origin);
    if(message.type==='naser:collect')clock.setTimeout(()=>emit('window','message',{
      origin:location.origin,source:frame.contentWindow,
      data:{type:'naser:collected',reqId:message.reqId,fields:structuredClone(fields),storage:structuredClone(storage),valid:true}
    }),0);
  }};
  const fetch=async(url,init={})=>{
    const call={url,init,payload:init.body?JSON.parse(init.body):null};calls.push(call);
    assert.ok(replies.length,'Toda petición debe tener una respuesta de prueba');
    const reply=replies.shift();if(reply instanceof Error)throw reply;
    return typeof reply==='function'?reply(call):reply;
  };
  const context={window:win,document,location,navigator,crypto:win.crypto,history:{replaceState(){}},
    Date:FakeDate,fetch,URLSearchParams,Uint8Array,Blob,URL,confirm:()=>true,setTimeout:clock.setTimeout,clearTimeout:clock.clearTimeout};
  Object.defineProperties(context,{localStorage:{get:forbidden},sessionStorage:{get:forbidden}});
  vm.runInNewContext(source,context,{filename:'naser-autosave.js'});
  async function flush(){for(let i=0;i<20;i++)await Promise.resolve();}
  async function advance(ms){
    await flush();
    const target=now+ms;
    for(;;){
      const next=[...timers].filter(([,t])=>t.when<=target).sort((a,b)=>a[1].when-b[1].when||a[0]-b[0])[0];
      if(!next)break;now=next[1].when;timers.delete(next[0]);next[1].fn();await flush();
    }
    now=target;await flush();
  }
  return {calls,replies,cfg,get,navigator,document,flush,advance,
    edit:(name='Datos locales')=>{fields={'#nombre':name};emit('window','message',{origin:location.origin,source:frame.contentWindow,data:{type:'naser:dirty'}});},
    forgedEdit:()=>emit('window','message',{origin:'http://otro.test',source:frame.contentWindow,data:{type:'naser:dirty'}}),
    action:name=>{const button=new Element();button.dataset.accion=name;get('fxToolbar').dispatch('click',{target:button});},
    event:name=>emit('window',name),
    leave:()=>{const e={prevented:false,preventDefault(){this.prevented=true;}};emit('window','beforeunload',e);return e;}
  };
}
const tests=[];
function test(label,fn){tests.push([label,fn]);}
test('Espera 8 segundos y conserva campos, filas, token y CSRF en el servidor',async()=>{
  const e=editor();e.replies.push(saved());e.edit();await e.advance(7999);assert.equal(e.calls.length,0);
  await e.advance(1);assert.equal(e.calls.length,1);
  assert.equal(e.calls[0].payload.datos['#nombre'],'Datos locales');assert.equal(e.calls[0].payload.storage.lista,'["fila local"]');
  assert.equal(e.calls[0].payload.client_token,e.cfg.clientToken);assert.equal(e.calls[0].init.headers['X-CSRF-Token'],'csrf-original');
  assert.equal(e.calls[0].init.credentials,'same-origin');assert.equal(e.get('fxDirty').hidden,true);assert.equal(e.leave().prevented,false);
});
test('Los cambios continuos se guardan como máximo a los 30 segundos',async()=>{
  const e=editor();e.replies.push(saved());e.edit('t0');
  for(let i=1;i<=4;i++){await e.advance(7000);e.edit('t'+i);}
  assert.equal(e.calls.length,0);await e.advance(2000);assert.equal(e.calls.length,1);assert.equal(e.calls[0].payload.datos['#nombre'],'t4');
});
test('Un error de red reintenta con el mismo token y conserva cambios pendientes',async()=>{
  const e=editor();e.replies.push(new Error('Red interrumpida'),saved());e.edit();await e.advance(8000);
  assert.equal(e.calls.length,1);assert.equal(e.get('fxDirty').hidden,false);assert.equal(e.leave().prevented,true);
  await e.advance(4999);assert.equal(e.calls.length,1);await e.advance(1);assert.equal(e.calls.length,2);
  assert.equal(e.calls[1].payload.client_token,e.calls[0].payload.client_token);
  assert.deepEqual(e.calls[1].payload.datos,e.calls[0].payload.datos);assert.equal(e.get('fxDirty').hidden,true);
});
test('Los cambios hechos durante un guardado se guardan luego con la revisión nueva',async()=>{
  const e=editor({id:41,revision:4}),first=deferred();
  e.replies.push(()=>first.promise,saved(41,6));e.edit('versión A');await e.advance(8000);
  e.edit('versión B');first.resolve(saved(41,5));await e.flush();
  assert.equal(e.get('fxDirty').hidden,false);assert.equal(e.calls.length,1);await e.advance(8000);
  assert.equal(e.calls.length,2);assert.equal(e.calls[0].payload.revision,4);assert.equal(e.calls[1].payload.revision,5);
  assert.equal(e.calls[1].payload.datos['#nombre'],'versión B');assert.equal(e.get('fxDirty').hidden,true);
});
test('Un 409 detiene el guardado, conserva los datos y permite una copia separada',async()=>{
  const e=editor({id:41,revision:4});
  e.replies.push(response(409,{ok:false,error:'Otra persona guardó.',conflicto:true,id:41,revision:5}),saved(99));
  e.edit('mi trabajo');await e.advance(8000);
  assert.equal(e.get('fxConflict').hidden,false);assert.equal(e.get('fxDirty').hidden,false);
  await e.advance(60000);assert.equal(e.calls.length,1);assert.equal(e.leave().prevented,true);
  e.get('fxGuardarCopia').click();await e.advance(0);
  assert.equal(e.calls[1].payload.accion,'guardar_copia');assert.equal(e.calls[1].payload.id,41);
  assert.equal(e.calls[1].payload.datos['#nombre'],'mi trabajo');assert.notEqual(e.calls[1].payload.client_token,e.calls[0].payload.client_token);
  assert.equal(e.get('fxConflict').hidden,true);assert.equal(e.get('fxDirty').hidden,true);
});
test('Una copia cuya respuesta se pierde conserva el token al reintentar manualmente',async()=>{
  const e=editor({id:41,revision:4});
  e.replies.push(response(409,{ok:false,conflicto:true,id:41}),new Error('Respuesta perdida'),saved(99));
  e.edit('copia local');await e.advance(8000);e.get('fxGuardarCopia').click();await e.advance(0);
  assert.equal(e.get('fxDirty').hidden,false);e.get('fxGuardarCopia').click();await e.advance(0);
  assert.equal(e.calls.length,3);assert.equal(e.calls[1].payload.client_token,e.calls[2].payload.client_token);
  assert.equal(e.calls[2].payload.datos['#nombre'],'copia local');assert.equal(e.get('fxDirty').hidden,true);
});
test('Un 403 recupera CSRF para la misma cuenta y vuelve a guardar sin recargar',async()=>{
  const e=editor({id:41,revision:2});
  e.replies.push(response(403,{ok:false,error:'CSRF vencido'}),response(200,{ok:true,usuario_id:7,csrf:'csrf-recuperado'}),saved(41,3));
  e.edit('sesión recuperable');await e.advance(8000);assert.equal(e.get('fxSession').hidden,false);
  await e.advance(60000);assert.equal(e.calls.length,1);e.get('fxReanudar').click();await e.advance(0);
  assert.equal(e.calls.length,3);assert.equal(e.calls[1].url,e.cfg.api+'?sesion=1');
  assert.equal(e.calls[2].init.headers['X-CSRF-Token'],'csrf-recuperado');assert.equal(e.calls[2].payload.revision,2);
  assert.equal(e.calls[2].payload.datos['#nombre'],'sesión recuperable');assert.equal(e.get('fxSession').hidden,true);assert.equal(e.get('fxDirty').hidden,true);
});
test('Una sesión de otra cuenta nunca envía los cambios de la cuenta original',async()=>{
  const e=editor({id:41,revision:2});
  e.replies.push(response(403,{ok:false}),response(200,{ok:true,usuario_id:88,csrf:'otra-cuenta'}));
  e.edit('privado de cuenta 7');await e.advance(8000);e.get('fxReanudar').click();await e.advance(0);await e.advance(60000);
  assert.equal(e.calls.length,2);assert.equal(e.cfg.csrf,'csrf-original');assert.equal(e.get('fxSession').hidden,false);
  assert.equal(e.get('fxDirty').hidden,false);assert.match(e.get('fxMsg').textContent,/cuenta diferente/);assert.equal(e.leave().prevented,true);
});
test('La sesión renovada sigue verificando conflictos del registro',async()=>{
  const e=editor({id:41,revision:2});
  e.replies.push(response(403,{ok:false}),response(200,{ok:true,usuario_id:7,csrf:'nuevo'}),response(409,{ok:false,id:41,revision:3,conflicto:true}));
  e.edit('no pisar');await e.advance(8000);e.get('fxReanudar').click();await e.advance(0);
  assert.equal(e.get('fxConflict').hidden,false);assert.equal(e.get('fxDirty').hidden,false);await e.advance(60000);assert.equal(e.calls.length,3);
});
test('Sin conexión no guarda y al volver la conexión reintenta los datos actuales',async()=>{
  const e=editor();e.replies.push(saved());e.edit('antes');e.navigator.onLine=false;e.event('offline');
  await e.advance(60000);assert.equal(e.calls.length,0);e.edit('durante corte');e.navigator.onLine=true;e.event('online');await e.advance(1000);
  assert.equal(e.calls.length,1);assert.equal(e.calls[0].payload.datos['#nombre'],'durante corte');
});
test('Mensajes de otro origen y el modo consulta no marcan ni guardan cambios',async()=>{
  const e=editor();e.forgedEdit();await e.advance(60000);assert.equal(e.calls.length,0);assert.equal(e.get('fxDirty').hidden,true);
  const readonly=editor({editar:false,id:41,revision:1});readonly.edit();await readonly.advance(60000);
  assert.equal(readonly.calls.length,0);assert.equal(readonly.get('fxDirty').hidden,true);
});
test('El formulario integrado usa almacenamiento en memoria sin leer ni escribir el almacenamiento real',async()=>{
  const render=fs.readFileSync(path.join(__dirname,'../../php/formularios/render.php'),'utf8');
  const start=render.indexOf('(function(){'),end=render.indexOf('</script>',start);
  assert.ok(start>=0&&end>start,'El formulario debe instalar su aislamiento antes del HTML original');
  const initial={lista:'["fila guardada"]'};
  const shim=render.slice(start,end).replace('$sto',JSON.stringify(initial));
  let reads=0,writes=0;
  const durable={getItem(){reads++;return 'otro usuario';},setItem(){writes++;},removeItem(){writes++;}};
  const window={addEventListener(){},localStorage:durable,sessionStorage:durable};
  vm.runInNewContext(shim,{window});
  assert.notEqual(window.localStorage,durable);assert.notEqual(window.sessionStorage,durable);
  assert.equal(window.localStorage.getItem('lista'),initial.lista);
  window.localStorage.setItem('privado','solo en memoria');
  assert.equal(window.__naserLS.__dump().privado,'solo en memoria');
  const next={addEventListener(){},localStorage:durable,sessionStorage:durable};
  vm.runInNewContext(shim,{window:next});
  assert.equal(next.localStorage.getItem('privado'),null);assert.equal(reads,0);assert.equal(writes,0);
});
(async()=>{for(const[label,fn]of tests){await fn();console.log('OK: '+label);}console.log('TOTAL: '+tests.length+' pruebas de autosave aprobadas.');})()
  .catch(error=>{console.error(error);process.exitCode=1;});

