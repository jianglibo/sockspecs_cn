<?php
/**
 * Server-rendered pair quantities. Native variation prices and stock only.
 *
 * @package ParameterCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$google_offer   = \ParameterCommerce\Google_Packs::landing( $product );
$pack_prices    = \ParameterCommerce\Pack_Pricing::prices( $product );
$display_prices = array();
foreach ( $pack_prices as $pack_size => $pack_price ) {
	$display_prices[ $pack_size ] = wc_get_price_to_display( $product, array( 'price' => $pack_price ) );
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pack selection.
$selected_pack = isset( $_GET['pc_pack_size'] ) && in_array( $_GET['pc_pack_size'], array( '4', '6', '12' ), true ) ? (int) $_GET['pc_pack_size'] : 4;
?>
<section class="pc-pack-builder" aria-labelledby="pc-pack-title">
	<h2 id="pc-pack-title"><?php esc_html_e( 'Build Your Pack', 'parameter-commerce' ); ?></h2>
	<?php if ( $google_offer ) : ?>
		<p class="pc-google-pack-offer" data-google-pack-variant="<?php echo esc_attr( $google_offer['available'] ? $google_offer['variation'] : '' ); ?>"><strong><?php echo esc_html( $google_offer['title'] ); ?></strong> — <?php echo wp_kses_post( wc_price( $google_offer['price'] ) ); ?></p>
		<?php if ( ! $google_offer['available'] ) : ?>
			<p role="status"><?php esc_html_e( 'This six-pair color and size combination is currently unavailable. You can choose other available colors below.', 'parameter-commerce' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( $pack_prices ) : ?>
		<p>
		<?php esc_html_e( 'Mix styles, colors, sizes and cuff heights within this product at the same per-pair price. Choose 4, 6 or 12 pairs in total. The price below is for the whole pack.', 'parameter-commerce' ); ?>
		</p>
		<p><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
	<?php else : ?>
	<p><?php esc_html_e( 'Choose 4, 6 or 12 pairs of this model. Prices and quantities below are per pair; the pack price is their sum. Each color has its own specifications.', 'parameter-commerce' ); ?></p>
	<?php endif; ?>
	<?php if ( $preview ) : ?>
		<p class="pc-pack-preview" role="status"><?php esc_html_e( 'Staff preview only: the store is in coming-soon mode. Adding packs to the cart is disabled. Public specifications remain unavailable until launch.', 'parameter-commerce' ); ?></p>
	<?php endif; ?>
	<form data-mix-error="<?php esc_attr_e( 'Only variants of the same product with matching per-pair prices and tax treatment can share a pack. Adjust your selection.', 'parameter-commerce' ); ?>" data-pack-group="<?php echo esc_attr( $selected ); ?>" data-pack-prices="<?php echo esc_attr( wp_json_encode( $display_prices ) ); ?>" data-preview="<?php echo $preview ? 'true' : 'false'; ?>" method="post" action="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="pc-pack-form">
		<?php wp_nonce_field( 'pc_pack_' . $product->get_id(), 'pc_pack_nonce' ); ?>
		<input type="hidden" name="pc_add_pack" value="<?php echo esc_attr( $product->get_id() ); ?>">
		<label for="pc-pack-size"><?php esc_html_e( 'Pack size', 'parameter-commerce' ); ?></label>
		<select id="pc-pack-size" name="pc_pack_size"><option value="4" <?php selected( $selected_pack, 4 ); ?>><?php esc_html_e( '4 pairs', 'parameter-commerce' ); ?></option><option value="6" <?php selected( $selected_pack, 6 ); ?>><?php esc_html_e( '6 pairs', 'parameter-commerce' ); ?></option><option value="12" <?php selected( $selected_pack, 12 ); ?>><?php esc_html_e( '12 pairs', 'parameter-commerce' ); ?></option></select>
		<?php foreach ( $groups as $group_key => $group ) : ?>
		<fieldset>
			<legend><?php echo esc_html( $group['label'] ); ?></legend>
			<?php if ( \ParameterCommerce\Size_Shape::has_cuff_height( $product ) ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'pc_pack_group', $group_key, get_permalink( $product->get_id() ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View dimensions for this option (new tab)', 'parameter-commerce' ); ?></a>
			<?php endif; ?>
			<?php foreach ( $group['items'] as $variation_id => $variation ) : ?>
				<?php $maximum = $variation->is_purchasable() && $variation->is_in_stock() ? max( 0, min( 12, $variation->get_stock_quantity() - wc_get_held_stock_quantity( $variation ) ) ) : 0; ?>
				<div class="pc-pack-color">
					<label for="pc-color-<?php echo esc_attr( $variation_id ); ?>"><?php echo esc_html( wc_get_formatted_variation( $variation, true, true ) ); ?></label>
					<?php
					if ( 0 === $maximum ) :
						?>
						<span><?php echo null === $variation->get_stock_quantity() ? esc_html__( 'Stock pending confirmation — unavailable to order.', 'parameter-commerce' ) : esc_html__( 'Currently unavailable.', 'parameter-commerce' ); ?></span><?php endif; ?>
					<?php if ( ! $pack_prices ) : ?>
					<span><?php echo wp_kses_post( $variation->get_price_html() ); ?></span>
					<?php endif; ?>
					<input id="pc-color-<?php echo esc_attr( $variation_id ); ?>" name="pc_colors[<?php echo esc_attr( $variation_id ); ?>]" type="number" min="0" max="<?php echo esc_attr( $maximum ); ?>" step="1" value="<?php echo esc_attr( $google_offer && $google_offer['available'] && $google_offer['group'] === $selected && $google_offer['variation'] === $variation_id ? 6 : 0 ); ?>" inputmode="numeric" required data-mix-key="<?php echo esc_attr( \ParameterCommerce\Pack_Builder::mix_key( $variation ) ); ?>" data-price="<?php echo esc_attr( wc_get_price_to_display( $variation ) ); ?>" <?php disabled( 0 === $maximum ); ?>>
					<?php
					$evidence_url = add_query_arg(
						array(
							'pc_variation'  => $variation_id,
							'pc_pack_group' => $group_key,
						),
						get_permalink( $product->get_id() )
					) . '#tab-pc_specifications';
					?>
					<?php if ( ! $preview ) : ?>
					<a target="_blank" rel="noopener" class="pc-pack-evidence" data-variation-id="<?php echo esc_attr( $variation_id ); ?>" href="<?php echo esc_url( $evidence_url ); ?>"><?php esc_html_e( 'Specifications for this option', 'parameter-commerce' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</fieldset>
		<?php endforeach; ?>
		<p class="pc-pack-progress" role="status" aria-live="polite"><?php esc_html_e( 'Select exactly the chosen number of pairs before adding your pack.', 'parameter-commerce' ); ?></p>
		<p class="pc-pack-total" hidden data-currency="<?php echo esc_attr( get_woocommerce_currency() ); ?>" data-locale="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>" data-label="<?php esc_attr_e( 'Pack subtotal', 'parameter-commerce' ); ?>"></p>
		<?php if ( \ParameterCommerce\Pack_Pricing::free_us_shipping() ) : ?>
		<p><?php esc_html_e( 'Free shipping to the United States. No minimum order. To change a pack in the cart, remove it and build it again.', 'parameter-commerce' ); ?></p>
		<?php else : ?>
		<p><?php esc_html_e( 'Shipping and any applicable taxes are calculated at checkout. To change a pack in the cart, remove it and build it again.', 'parameter-commerce' ); ?></p>
		<?php endif; ?>
		<button type="submit" <?php disabled( $preview ); ?> class="button alt" data-label="<?php esc_attr_e( 'pairs selected', 'parameter-commerce' ); ?>"><?php esc_html_e( 'Add pack to cart', 'parameter-commerce' ); ?></button>
	</form>
</section>
