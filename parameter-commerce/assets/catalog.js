/* Native GET forms and details remain usable without JavaScript. */
(() => {
	const desktop = window.matchMedia('(min-width: 701px)');
	const panels = document.querySelectorAll('.pc-catalog-filter-panel');
	const update = () => panels.forEach((panel) => {
		panel.open = desktop.matches || Number(panel.dataset.active) > 0;
	});
	update();
	desktop.addEventListener('change', update);

	document.querySelectorAll('.pc-catalog-filters').forEach((form) => {
		if (typeof form.requestSubmit !== 'function') return;
		let submitting = false;
		const button = form.querySelector('button[type="submit"]');
		const hint = form.querySelector('.pc-catalog-auto-hint');
		form.addEventListener('change', (event) => {
			if (!event.target.matches('select, input:not([type="hidden"])') || submitting) return;
			// Keep native input validity checks before navigation.
			if (!form.reportValidity()) return;
			form.requestSubmit();
		});
		form.addEventListener('submit', (event) => {
			if (submitting) { event.preventDefault(); return; }
			submitting = true;
			form.setAttribute('aria-busy', 'true');
		});
		window.addEventListener('pageshow', () => {
			submitting = false;
			form.removeAttribute('aria-busy');
		});
		if (button) button.hidden = true;
		if (hint) hint.hidden = false;
	});
})();
