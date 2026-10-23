/* Server-owned, revision-checked drafts; no price or stock authority in the browser. */
(() => {
    'use strict';
    const config = window.sscnSelection;
    if (!config) return;
    const root = document.querySelector('.sscn-selection');
    const entry = document.querySelector('.sscn-selection-entry');
    let state = root ? JSON.parse(root.dataset.state) : null;
    let busy = false;
    const money = value => value == null ? '待确认' : new Intl.NumberFormat('zh-CN', {style:'currency', currency:'CNY'}).format(value);
    const text = (tag, value, className) => {
        const el = document.createElement(tag); el.textContent = value;
        if (className) el.className = className;
        return el;
    };
    const message = value => {
        const el = root?.querySelector('.sscn-selection-notice') || entry?.querySelector('[data-selection-message]');
        if (el) el.textContent = value;
    };
    async function request(op, payload = {}) {
        const response = await fetch(config.endpoint, {method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'}, body:JSON.stringify({op, csrf:config.csrf, revision:state?.revision, ...payload})});
        const json = await response.json();
        if (!json.success) {
            if (json.data?.state) { state = json.data.state; render(); }
            throw new Error(json.data?.message || '暂时无法保存，请稍后重试。');
        }
        state = json.data; render(); return state;
    }
    async function run(op, payload, success = '已保存') {
        if (busy) return;
        busy = true; setBusy(); message('正在保存…');
        try {
            const result = await request(op, payload);
            message(success);
            if (result.redirect) window.location.assign(result.redirect);
            return result;
        } catch (error) { message(error.message || '网络连接失败，修改尚未保存，请重试。'); }
        finally { busy = false; setBusy(); }
    }
    function setBusy() {
        root?.setAttribute('aria-busy', String(busy));
        root?.querySelectorAll('button,input,select').forEach(el => {
            el.disabled = busy || el.dataset.unavailable === 'true';
        });
        const submit = root?.querySelector('[data-selection-checkout]');
        if (submit) submit.disabled = busy || state.count < state.minimum || Boolean(state.error || state.conflict);
        const button = entry?.querySelector('[data-selection-add]');
        if (button) button.disabled = busy;
    }
    function itemMap() { return Object.fromEntries((state?.items || []).map(item => [item.id,item.quantity])); }
    function render() {
        if (!root || !state) return;
        root.querySelectorAll('input[name="revision"]').forEach(el => el.value=state.revision);
        const oldConflict = root.querySelector('.sscn-selection-login-choice');
        oldConflict?.remove();
        if (state.conflict) {
            const box = text('section','', 'sscn-selection-login-choice');
            box.append(text('p','账户和登录前各有一份清单，请选择要继续的那份。'));
            const summary=text('p',state.conflict.items.map(i=>`${i.name} × ${i.quantity}`).join('；'));
            box.append(summary);
            for (const [choice,label] of [['account',`继续账户清单（${state.count}双）`],['guest',`继续登录前清单（${state.conflict.count}双）`]]) {
                const b=text('button',label,'button');b.type='button';
                b.addEventListener('click',()=>run('resolve',{choice,guest_revision:state.conflict.revision}));box.append(b);
            }
            root.querySelector('.sscn-selection-notice').after(box);
        }
        const body = root.querySelector('.sscn-selection-sidebar-body'); body.replaceChildren();
        if (state.state === 'committed') {
            body.append(text('p','这份选择已加入购物车。你可以继续挑选，开始下一份清单。'));
            const cart=text('a','查看购物车','sscn-button');cart.href=config.cart;body.append(cart);
            const start=text('button','重新选一份','button');start.type='button';start.onclick=()=>run('clear',{});body.append(start);
        } else {
            if (!state.items.length) body.append(text('p','先选喜欢的款式，再选颜色和尺码。','sscn-selection-empty'));
            for (const item of state.items) {
                const line=text('div','','sscn-selection-line'); const img=document.createElement('img');img.src=item.image;img.alt='';img.width=56;img.height=56;
                const copy=document.createElement('div');const link=text('a',item.name);link.href=item.url;copy.append(link,text('small',item.label));
                const qtyLabel=text('label','双数 ');const input=document.createElement('input');input.type='number';input.min='0';input.step='1';input.value=item.quantity;input.setAttribute('aria-label',`${item.name} ${item.label} 双数`);
                input.addEventListener('change',()=>{
                    if (!input.checkValidity()) { input.reportValidity(); return; }
                    run('save',{size:state.size,items:{...itemMap(),[item.id]:Number(input.value)}});
                });qtyLabel.append(input);copy.append(qtyLabel);
                if (item.amount!=null) copy.append(text('span',`${money(item.unit)} / 双 · 小计 ${money(item.amount)}`,'sscn-selection-line-price'));
                if (item.available<item.quantity) copy.append(text('p',`当前可用${item.available}双，请调整。`,'sscn-selection-error'));
                const remove=text('button','移除','sscn-selection-text-button');remove.type='button';remove.onclick=()=>run('save',{size:state.size,items:{...itemMap(),[item.id]:0}});copy.append(remove);
                line.append(img,copy);body.append(line);
            }
            body.append(text('p',`已选 ${state.count} 双 · ${money(state.total)}`,'sscn-selection-total'));
            if (state.error) body.append(text('p',state.error,'sscn-selection-error'));
            if (state.price_changed) body.append(text('p','商品价格有更新，请确认当前总价。','sscn-selection-error'));
            if (state.count<state.minimum) body.append(text('p',`再选 ${state.minimum-state.count} 双即可加入购物车。`));
            const submit=text('button','加入购物车','sscn-button');submit.type='button';submit.dataset.selectionCheckout='';
            submit.onclick=()=>run('commit',{signature:state.signature},'已加入购物车');body.append(submit);
            if (state.items.length) {const clear=text('button','清空清单','sscn-selection-text-button');clear.type='button';clear.onclick=()=>run('clear',{});body.append(clear);}
        }
        const mobile=root.querySelector('.sscn-selection-mobile');
        mobile.textContent=state.state==='committed'?'已加入购物车 · 查看清单':`已选 ${state.count} 双 · ${money(state.total)}　查看清单 ↑`;
        setBusy();
    }
    if (root) {
        root.querySelectorAll('.sscn-selection-product-add').forEach(form=>form.addEventListener('submit',async event=>{
            event.preventDefault();if (!form.reportValidity()) return;
            const id=form.elements.variation.value, qty=Number(form.elements.quantity.value);
            await run('add',{items:{[id]:qty}},'已加入清单');
        }));
        root.querySelector('.sscn-selection-edit-cart')?.addEventListener('submit',async event=>{
            event.preventDefault();const result=await run('edit_cart',{group:event.currentTarget.elements.group.value},'已取回，修改后请重新加入购物车');
            if(result) root.querySelector('.sscn-selection-edit-cart')?.remove();
        });
        const search=root.querySelector('.sscn-selection-search');search.hidden=false;
        search.querySelector('input').addEventListener('input',event=>{
            const term=event.target.value.trim().toLocaleLowerCase();
            root.querySelectorAll('.sscn-selection-product').forEach(card=>card.hidden=!card.textContent.toLocaleLowerCase().includes(term));
        });
        render();
        window.addEventListener('pageshow',event=>{if(event.persisted&&!busy)run('load',{},'已恢复保存的清单');});
        window.addEventListener('focus',()=>{if(!busy)run('load',{},'清单已同步');});
    }
    if (entry) {
        const button=entry.querySelector('[data-selection-add]');button.hidden=false;
        button.addEventListener('click',async event=>{
            event.preventDefault();
            if(!entry.querySelector('form').reportValidity())return;
            if(busy)return;busy=true;setBusy();
            try {
                if (!state) await request('load');
                if(state.conflict){message('账户与登录前各有一份清单，请进入自由选袜页面选择要继续的那份。');return;}
                const form=entry.querySelector('.sscn-product-selection'), items={};
                form?.querySelectorAll('input[name^="items["]').forEach(input=>{
                    if(!input.disabled && Number(input.value)>0)items[input.name.match(/\[(\d+)\]/)[1]]=Number(input.value);
                });
                if(!Object.keys(items).length){message('请选择规格和双数。');return;}
                await request('add',{items});
                form?.querySelectorAll('input[name^="items["]').forEach(input=>input.value='0');
                form?.dispatchEvent(new Event('input',{bubbles:true}));
                message(`已保存，共选 ${state.count} 双。`);
                const link=text('a','查看已选并继续选袜 →');link.href=config.url;entry.querySelector('[data-selection-message]').append(document.createTextNode(' '),link);
            }catch(error){message(error.message || '尚未保存，请检查连接后重试。');}
            finally{busy=false;setBusy();}
        });
    }
})();
