'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const code = fs.readFileSync('parameter-commerce/assets/metric-editor.js', 'utf8');
let checks=0;
function check(ok) { assert.ok(ok); ++checks; }
function setup(expected, original, ready='complete') {
 const callbacks={}, windowCallbacks={};
 const unit={value:original,readOnly:false};
 const other={value:'partial-terry',note:'unchanged'};
 let selected=expected;
 const metric={form:{querySelector:s=>s==='[name="unit"]'?unit:other}, selectedIndex:0,
  options:[{hasAttribute:()=>true,getAttribute:()=>JSON.stringify(selected)}],addEventListener:(e,fn)=>{callbacks[e]=fn;}};
 const document={readyState:ready,getElementById:()=>metric,addEventListener:(e,fn)=>{callbacks[e]=fn;}};
 vm.runInNewContext(code,{document,window:{addEventListener:(e,fn)=>{windowCallbacks[e]=fn;}}});
 if(ready==='loading')callbacks.DOMContentLoaded();
 return {unit,other,change:v=>{selected=v;callbacks.change();}, restore:()=>windowCallbacks.pageshow()};
}
let t=setup('%','%'); check(t.unit.readOnly);
t.change('');check(t.unit.value===''&&t.unit.readOnly);check(t.other.value==='partial-terry'&&t.other.note==='unchanged');
t.change('g');check(t.unit.value==='g'&&t.unit.readOnly);
t.change('mm');check(t.unit.value==='mm'&&t.unit.readOnly);
t.change(null);check(t.unit.value===''&&!t.unit.readOnly);
t.unit.value='Nm';t.restore();check(t.unit.value==='Nm'&&!t.unit.readOnly);
t.change('%');check(t.unit.value==='%'&&t.unit.readOnly);
t=setup('','%', 'loading');check(t.unit.value===''&&t.unit.readOnly);
t.unit.value='%';t.restore();check(t.unit.value==='');
t=setup('mm','cm');check(t.unit.value==='cm'&&!t.unit.readOnly); // no silent numeric conversion
 t=setup(null,'Ne');check(t.unit.value==='Ne'&&!t.unit.readOnly);
vm.runInNewContext(code,{document:{readyState:'complete',getElementById:()=>null}});check(true);
console.log('PASS: '+checks+' metric editor checks (fixed/unitless/multi-unit, error redisplay, history, untouched values).');
