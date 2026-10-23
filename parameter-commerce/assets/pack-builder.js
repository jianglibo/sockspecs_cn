/* Progressive enhancement; the server validates compatibility, quantities and stock again. */
document.querySelectorAll('.pc-pack-form').forEach(form => {
	'use strict';
	const inputs = [...form.querySelectorAll('input[type="number"]')];
	const size = form.querySelector('[name="pc_pack_size"]');
	const button = form.querySelector('button[type="submit"]');
	const progress = form.querySelector('.pc-pack-progress');
	const subtotal = form.querySelector('.pc-pack-total');
	const money = new Intl.NumberFormat(subtotal.dataset.locale, { style: 'currency', currency: subtotal.dataset.currency });
	const googleOffer = form.closest('.pc-pack-builder')?.querySelector('[data-google-pack-variant]');
	const packPrices = JSON.parse(form.dataset.packPrices || '{}');
	const update = () => {
		const total = inputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
		const price = packPrices[size.value] ?? inputs.reduce((sum, input) => sum + (Number(input.value) || 0) * Number(input.dataset.price), 0);
		const valid = inputs.every(input => input.disabled ? Number(input.value) === 0 : input.validity.valid && Number.isInteger(Number(input.value)));
		const keys = new Set(inputs.filter(input => Number(input.value) > 0).map(input => input.dataset.mixKey));
		const compatible = keys.size <= 1;
		if (googleOffer?.dataset.googlePackVariant) {
			googleOffer.hidden = size.value !== '6' || !inputs.every(input => Number(input.value) === (input.name === `pc_colors[${googleOffer.dataset.googlePackVariant}]` ? 6 : 0));
		}
		button.disabled = form.dataset.preview === 'true' || !valid || !compatible || total !== Number(size.value);
		progress.textContent = `${total} / ${size.value} ${button.dataset.label}` + (compatible ? '' : ` — ${form.dataset.mixError}`);
		subtotal.hidden = !compatible;
		subtotal.textContent = `${subtotal.dataset.label}: ${money.format(price)}`;
	};
	form.addEventListener('input', update);
	form.addEventListener('change', update);
	form.addEventListener('submit', event => { update(); if (button.disabled) event.preventDefault(); });
	window.addEventListener('pageshow', update);
	update();
});
