/* global jQuery, pcSpecifications */
jQuery(function ($) {
	'use strict';
	$('.pc-specifications').each(function () {
		const root = $(this);
		const content = root.find('.pc-spec-content');
		const announcement = root.find('.pc-spec-announcement');
		const scope = root.find('.pc-scope-form select');
		const productId = String(root.data('product-id'));
		let generation = 0;
		let controller;
		const clear = message => {
			generation += 1;
			if (controller) controller.abort();
			content.empty();
			announcement.removeClass('pc-sr-only').text(message);
			root.attr('aria-busy', 'false');
		};
		const load = async variationId => {
			if (!content.length) return;
			clear(pcSpecifications.loading);
			const requestGeneration = generation;
			controller = new AbortController();
			root.attr('aria-busy', 'true');
			scope.val(String(variationId));
			try {
				const query = new URLSearchParams({ action: 'pc_specifications', product_id: productId, variation_id: String(variationId) });
				const comparison = new URLSearchParams(window.location.search).get('pc_compare');
				if (comparison) query.set('pc_compare', comparison);
				const response = await fetch(pcSpecifications.url + '?' + query, { signal: controller.signal, cache: 'no-store', credentials: 'same-origin' });
				const result = await response.json();
				if (requestGeneration !== generation) return;
				if (!response.ok || !result.success) throw new Error(result.data?.message || pcSpecifications.error);
				content.html(result.data.html);
				root.attr('data-variation-id', variationId);
				announcement.addClass('pc-sr-only').text(content.find('.pc-scope-label').text());
			} catch (error) {
				if (requestGeneration !== generation || error.name === 'AbortError') return;
				content.empty();
				announcement.text(pcSpecifications.error + ' ');
				$('<button>', { type: 'button', text: pcSpecifications.retry }).on('click', () => load(variationId)).appendTo(announcement);
			} finally {
				if (requestGeneration === generation) root.attr('aria-busy', 'false');
			}
		};
		root.find('.pc-scope-form').on('submit', event => {
			event.preventDefault();
			load(Number(scope.val()) || 0);
		});
		$('.pc-pack-evidence').on('click.pcSpecs', function (event) {
			event.preventDefault();
			load(Number(this.dataset.variationId));
			$('a[href="#tab-pc_specifications"]').trigger('click');
			root[0].scrollIntoView({ block: 'start' });
		});
		$('form.variations_form').filter(function () { return String($(this).data('product_id')) === productId; }).each(function () {
			const form = $(this);
			form.on('woocommerce_variation_select_change.pcSpecs', () => clear(pcSpecifications.selection));
			form.on('found_variation.pcSpecs', (event, variation) => {
				if (!variation.variation_is_visible || !variation.variation_is_active || !variation.is_in_stock) {
					clear(pcSpecifications.selection);
					return;
				}
				load(variation.variation_id);
			});
			form.on('reset_data.pcSpecs', () => load(0));
			const initial = Number(form.find('input.variation_id').val());
			if (initial) load(initial);
		});
	});
});
