/* Keep dictionary units in sync without converting or changing the entered value. */
(function () {
	'use strict';
	function init() {
		const metric = document.getElementById('pc-metric');
		if (!metric || !metric.form) return;
		const unit = metric.form.querySelector('[name="unit"]');
		if (!unit) return;
		function sync(changed) {
			const option = metric.options[metric.selectedIndex];
			if (!option || !option.hasAttribute('data-unit')) return;
			let expected;
			try { expected = JSON.parse(option.getAttribute('data-unit')); } catch (_) { return; }
			if (expected !== null && typeof expected !== 'string') return;
			// Null denotes a choice of units (e.g. yarn count); never guess a system.
			if (changed) unit.value = expected === null ? '' : expected;
			// Unitless values are enums: clearing a stale % cannot convert a quantity.
			else if (expected === '') unit.value = '';
			// Keep a mismatched numeric unit editable on error redisplay. Do not relabel it.
			unit.readOnly = expected !== null && unit.value === expected;
		}
		metric.addEventListener('change', function () { sync(true); });
		// Includes the standalone validation-error page and browser history restoration.
		window.addEventListener('pageshow', function () { sync(false); });
		sync(false);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
}());
