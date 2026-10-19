/* Progressive enhancement; the server validates all quantities and stock again. */
document.querySelectorAll('.pc-pack-form').forEach(form => {
	'use strict';
	const inputs = [...form.querySelectorAll('input[type="number"]')];
	const size = form.querySelector('[name="pc_pack_size"]');
	const button = form.querySelector('button[type="submit"]');
	const progress = form.querySelector('.pc-pack-progress');
	const subtotal = form.querySelector('.pc-pack-total');
	const money = new Intl.NumberFormat(subtotal.dataset.locale, { style: 'currency', currency: subtotal.dataset.currency });
	const optionsForm = form.closest('.pc-pack-builder')?.querySelector('.pc-pack-options');
	const googleOffer = form.closest('.pc-pack-builder')?.querySelector('[data-google-pack-variant]');
	const group = optionsForm?.querySelector('[name="pc_pack_group"]');
	const optionsPackSize = optionsForm?.querySelector('[name="pc_pack_size"]');
	const quantities = form.querySelector('fieldset');
	// The rendered group is authoritative, including after browser history restores controls.
	const pendingGroup = () => Boolean(group && group.value !== form.dataset.packGroup);
	const packPrices = JSON.parse(form.dataset.packPrices || '{}');
	const update = () => {
		const total = inputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
		const price = packPrices[size.value] ?? inputs.reduce((sum, input) => sum + (Number(input.value) || 0) * Number(input.dataset.price), 0);
		const valid = inputs.every(input => input.disabled || (input.validity.valid && Number.isInteger(Number(input.value))));
		if (optionsPackSize) optionsPackSize.value = size.value;
		if (googleOffer?.dataset.googlePackVariant) {
			googleOffer.hidden = pendingGroup() || size.value !== '6' || !inputs.every(input => Number(input.value) === (input.name === `pc_colors[${googleOffer.dataset.googlePackVariant}]` ? 6 : 0));
		}
		quantities.disabled = pendingGroup();
		form.setAttribute('aria-busy', String(pendingGroup()));
		button.disabled = form.dataset.preview === 'true' || pendingGroup() || !valid || total !== Number(size.value);
		progress.textContent = pendingGroup() ? optionsForm.dataset.loading : `${total} / ${size.value} ${button.dataset.label}`;
		subtotal.hidden = false;
		subtotal.textContent = `${subtotal.dataset.label}: ${money.format(price)}`;
	};
	form.addEventListener('input', update);
	form.addEventListener('change', update);
	form.addEventListener('submit', event => { update(); if (button.disabled) event.preventDefault(); });
	const changeGroup = () => {
		update();
		// Submit only the read-only options form, never the add-to-cart form.
		if (pendingGroup()) optionsForm.requestSubmit();
	};
	group?.addEventListener('change', changeGroup);
	optionsForm?.addEventListener('submit', update);
	window.addEventListener('pageshow', () => {
		// History may restore a newer dropdown value into an older page. Match the
		// rendered rows without creating another navigation or a back-button loop.
		if (group) group.value = form.dataset.packGroup;
		update();
	});
	update();
	// Keep the server-rendered fallback unless automatic switching initialized.
	const showColors = group && optionsForm.querySelector('button[type="submit"]');
	if (showColors) showColors.hidden = true;
});
