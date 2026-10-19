/* Regression for the actual progressive-enhancement script; no browser dependencies. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('parameter-commerce/assets/pack-builder.js', 'utf8');
for (const preview of ['true', 'false']) {
for (const fixed of [false, true]) {
	const inputs = [{name:'pc_colors[1]',value:'2',dataset:{price:'2'},validity:{valid:true}}, {name:'pc_colors[2]',value:'4',dataset:{price:'2'},validity:{valid:true}}];
	const size = {value:'6'};
	const button = {disabled:preview==='true',dataset:{label:'pairs selected'}};
	const progress = {};
	const subtotal = {dataset:{locale:'en-US',currency:'USD',label:'Pack subtotal'}};
	const fieldset = {};
	const listeners = {}, groupListeners = {}, optionsListeners = {}, windowListeners = {};
	const group = {value:'M',addEventListener:(name,fn)=>{groupListeners[name]=fn;}};
	const optionsPackSize = {value:'6'};
	const showColors = {hidden:false};
	const googleOffer = {dataset:{googlePackVariant:'1'}};
	let navigations = [];
	const options = {dataset:{loading:'Loading colors for the selected size…'},querySelector:key=>({'[name="pc_pack_group"]':group,'[name="pc_pack_size"]':optionsPackSize,'button[type="submit"]':showColors})[key],
		addEventListener:(name,fn)=>{optionsListeners[name]=fn;},requestSubmit:()=>{optionsListeners.submit();navigations.push({group:group.value,packSize:optionsPackSize.value});}};
	const elements = {'[name="pc_pack_size"]':size,'button[type="submit"]':button,'.pc-pack-progress':progress,'.pc-pack-total':subtotal,'fieldset':fieldset};
	const attrs = {};
	const form = {dataset:{preview,packGroup:'M',packPrices:fixed ? JSON.stringify({6:23.90,12:42.90}) : '{}'},closest:()=>({querySelector:key=>key==='.pc-pack-options' ? options : googleOffer}),setAttribute:(k,v)=>{attrs[k]=v;},querySelectorAll:()=>inputs,querySelector:key=>elements[key],addEventListener:(name,fn)=>{listeners[name]=fn;}};
	vm.runInNewContext(script,{document:{querySelectorAll:()=>[form]},window:{addEventListener:(name,fn)=>{windowListeners[name]=fn;}},Intl});
	assert.equal(button.disabled,preview==='true');
	assert.equal(progress.textContent,'6 / 6 pairs selected');
	assert.equal(subtotal.textContent,fixed ? 'Pack subtotal: $23.90' : 'Pack subtotal: $12.00');
	let prevented = false;
	listeners.submit({preventDefault:()=>{prevented=true;}});
	assert.equal(prevented,preview==='true');
	size.value='12'; listeners.change(); assert.equal(button.disabled,true);
	assert.equal(optionsPackSize.value,'12');
	inputs[1].value='10'; listeners.input(); assert.equal(button.disabled,preview==='true');
	assert.equal(subtotal.textContent,fixed ? 'Pack subtotal: $42.90' : 'Pack subtotal: $24.00');
	// M -> L loads the exact group, preserves 12 pairs, and cannot submit the old M rows.
	group.value='L';groupListeners.change();
	assert.deepEqual(navigations,[{group:'L',packSize:'12'}]);
	assert.equal(button.disabled,true);assert.equal(fieldset.disabled,true);assert.equal(attrs['aria-busy'],'true');
	assert.equal(progress.textContent,options.dataset.loading);
	prevented=false;listeners.submit({preventDefault:()=>{prevented=true;}});assert.equal(prevented,true);
	listeners.input();assert.equal(button.disabled,true); // Input events cannot reopen stale submission.
	// Cancelled navigation / restored selection of M unlocks only the rendered group's rows.
	group.value='M';groupListeners.change();assert.equal(navigations.length,1);assert.equal(fieldset.disabled,false);assert.equal(button.disabled,preview==='true');
	// History restores the rendered selection without adding a navigation/back loop.
	group.value='L';windowListeners.pageshow();assert.equal(navigations.length,1);assert.equal(group.value,'M');assert.equal(button.disabled,preview==='true');
	// Fresh L markup after the GET is coherent; choosing M goes back with the pack size intact.
	form.dataset.packGroup='L';windowListeners.pageshow();assert.equal(navigations.length,1);assert.equal(group.value,'L');assert.equal(fieldset.disabled,false);
	size.value='6';inputs[1].value='4';listeners.change();group.value='M';groupListeners.change();
	assert.deepEqual(navigations[1],{group:'M',packSize:'6'});
	group.value='L';inputs[0].value='6';inputs[1].value='0';listeners.input();assert.equal(googleOffer.hidden,false);
	size.value='12';listeners.change();assert.equal(googleOffer.hidden,true);
	size.value='6';inputs[0].value='3';inputs[1].value='3';listeners.input();assert.equal(googleOffer.hidden,true);
	inputs[0].value='6';inputs[1].value='0';listeners.input();assert.equal(googleOffer.hidden,false);
}
}
console.log('PASS: pack JS — M/L switching, pack size preservation, stale-submit prevention, history restoration, preview and pricing.');
