/* Native details remain usable without JavaScript; enhance the desktop layout. */
(() => {
	const desktop = window.matchMedia('(min-width: 701px)');
	const panels = document.querySelectorAll('.pc-catalog-filter-panel');
	const update = () => panels.forEach((panel) => { panel.open = desktop.matches; });
	update();
	desktop.addEventListener('change', update);
})();
