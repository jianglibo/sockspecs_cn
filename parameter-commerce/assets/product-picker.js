/* global jQuery, pcProductPicker */
jQuery(function ($) {
	'use strict';
	if (!$.fn.selectWoo) {
		return;
	}
	const config = pcProductPicker;
	$('.pc-product-picker').each(function () {
		const select = $(this);
		const variation = select.data('kind') === 'variation';
		const product = select.closest('form').find('[name="wc_product_id"]');
		const message = select.closest('.pc-picker-field').find('.pc-picker-message');
		const labelId = select.attr('id') + '-label';
		const labelWidget = () => {
			const widget = select.next('.select2-container');
			widget.css('max-width', '40rem');
			const selection = widget.find('.select2-selection');
			selection.attr('aria-labelledby', labelId + ' ' + selection.attr('aria-labelledby'));
		};
		const options = {
			width: 'resolve',
			placeholder: variation ? config.scope : config.product,
			allowClear: !variation,
			minimumResultsForSearch: variation ? Infinity : 0,
			language: {
				noResults: () => config.empty,
				errorLoading: () => config.error,
				searching: () => config.searching,
				loadingMore: () => config.more
			},
			ajax: {
				url: config.url,
				dataType: 'json',
				delay: 250,
				cache: false,
				data: params => ({
					action: 'pc_products', nonce: config.nonce,
					kind: variation ? 'variation' : 'product',
					parent: variation ? product.filter(':enabled').val() : 0,
					term: params.term || '', page: params.page || 1
				}),
				processResults: data => {
					message.text(data.truncated ? config.refine : '');
					return data;
				}
			}
		};
		select.prop('hidden', false).prop('disabled', false).selectWoo(options);
		labelWidget();
		$('#' + labelId).attr('for', select.attr('id'));
		select.closest('.pc-picker-field').find('.pc-picker-fallback').prop('disabled', true).hide();
		if (variation) {
			const reset = () => {
				// Recreate the widget to discard pending results for the previous parent.
				select.selectWoo('destroy').empty().append(new Option(config.scope, '0', true, true));
				select.prop('disabled', !product.filter(':enabled').val()).selectWoo(options);
				labelWidget();
				message.text(product.filter(':enabled').val() ? '' : config.chooseProduct);
			};
			product.on('change', reset);
			select.prop('disabled', !product.filter(':enabled').val());
		}
	});
});
