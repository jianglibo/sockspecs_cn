/* Actual pack enhancement: cross-fieldset quantities, price/tax boundaries, history. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('parameter-commerce/assets/pack-builder.js', 'utf8');
for (const preview of ['true', 'false']) for (const fixed of [false, true]) {
 const inputs = [1,2,3].map(id=>({name:`pc_colors[${id}]`,value:id===3?'0':'2',dataset:{price:'10',mixKey:'same-product-price-tax'},validity:{valid:true},disabled:id===3}));
 const size={value:'4'},button={dataset:{label:'双已选'}},progress={},subtotal={dataset:{locale:'zh-CN',currency:'CNY',label:'总价'}};
 const listeners={},windowListeners={},offer={dataset:{googlePackVariant:'1'}};
 const form={dataset:{preview,mixError:'不能混选不同单价或税务设置',packPrices:fixed?JSON.stringify({4:39.60,6:59.40,12:118.80}):'{}'},querySelectorAll:()=>inputs,querySelector:key=>({'[name="pc_pack_size"]':size,'button[type="submit"]':button,'.pc-pack-progress':progress,'.pc-pack-total':subtotal})[key],closest:()=>({querySelector:()=>offer}),addEventListener:(k,v)=>listeners[k]=v};
 vm.runInNewContext(script,{document:{querySelectorAll:()=>[form]},window:{addEventListener:(k,v)=>windowListeners[k]=v},Intl});
 for(const n of [4,6,12]){
  size.value=String(n);inputs[1].value=String(n-2);listeners.change();
  assert.equal(button.disabled,preview==='true');assert.equal(progress.textContent,`${n} / ${n} 双已选`);
  const total=fixed?{4:39.60,6:59.40,12:118.80}[n]:n*10;
  assert.equal(subtotal.textContent,`总价: ${new Intl.NumberFormat('zh-CN',{style:'currency',currency:'CNY'}).format(total)}`);
  windowListeners.pageshow();assert.equal(inputs[0].value,'2');assert.equal(inputs[1].value,String(n-2));
 }
 inputs[1].dataset.mixKey='another-price-or-tax';listeners.input();assert.equal(button.disabled,true);assert.equal(subtotal.hidden,true);assert.match(progress.textContent,/不能混选/);
 let prevented=false;listeners.submit({preventDefault:()=>prevented=true});assert.equal(prevented,true);
 inputs[1].value='0';inputs[0].value='12';listeners.input();assert.equal(button.disabled,preview==='true');
 inputs[2].value='1';inputs[0].value='11';listeners.input();assert.equal(button.disabled,true); // Unavailable options cannot contribute.
 inputs[2].value='0';inputs[0].value='12';inputs[0].validity.valid=false;listeners.input();assert.equal(button.disabled,true);
 inputs[0].validity.valid=true;inputs[0].value='11.5';listeners.input();assert.equal(button.disabled,true);
 size.value='6';inputs[0].value='6';listeners.input();assert.equal(offer.hidden,false);
 inputs[0].value='2';inputs[1].value='4';inputs[1].dataset.mixKey='same-product-price-tax';listeners.input();assert.equal(offer.hidden,true);assert.equal(button.disabled,preview==='true');
}
console.log('PASS: mixed-pack JS — all groups counted, 4/6/12 prices, compatibility, unavailable stock, invalid totals, preview and history.');
