/* Automatic filter navigation, fallback and history restoration. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('parameter-commerce/assets/catalog.js', 'utf8');
for (const supported of [true, false]) {
 const listeners = {}, events = {}, button = {hidden:false}, hint = {hidden:true};
 let valid = true, submissions = 0, busy = false;
 const panel = {dataset:{active:'1'},open:false};
 const form = {
  querySelector: s => s.startsWith('button') ? button : hint,
  addEventListener: (k,f) => listeners[k]=f,
  reportValidity: () => valid,
  setAttribute: () => busy=true,
  removeAttribute: () => busy=false,
 };
 if (supported) form.requestSubmit = () => {
  let prevented=false; listeners.submit({preventDefault:()=>prevented=true});
  if (!prevented) submissions++;
 };
 vm.runInNewContext(script, {
  document:{querySelectorAll:s=>s==='.pc-catalog-filter-panel'?[panel]:[form]},
  window:{matchMedia:()=>({matches:false,addEventListener:()=>{}}),addEventListener:(k,f)=>events[k]=f}
 });
 assert.equal(panel.open,true); // Mobile active filters remain visible.
 assert.equal(button.hidden,supported);
 assert.equal(hint.hidden,!supported);
 if (!supported) { assert.deepEqual(Object.keys(listeners),[]); continue; }
 const change = match => listeners.change({target:{matches:()=>match}});
 change(false); assert.equal(submissions,0); // Not a public control.
 valid=false; change(true); assert.equal(submissions,0);
 valid=true; change(true); assert.equal(submissions,1); assert.equal(busy,true);
 change(true); assert.equal(submissions,1); // No duplicate navigation.
 events.pageshow(); assert.equal(busy,false);
 change(true); assert.equal(submissions,2); // Back-forward cache can submit again.
}
console.log('PASS: catalog auto-submit, validation, duplicate prevention, history, mobile state and native fallback.');
