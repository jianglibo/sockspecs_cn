<?php
/**
 * Optional fixed pack totals on native variation cart lines.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Allocate pack prices without a second inventory or order ledger. */
final class Pack_Pricing {
	/** Register native hooks. */
	public static function register_hooks() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( self::class, 'settings' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'calculate' ), 20 );
		add_filter( 'woocommerce_get_price_html', array( self::class, 'price_html' ), 20, 2 );
	}

	/**
	 * Get and validate stored prices. An empty array preserves legacy pair pricing.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 * @throws \RuntimeException Invalid or stale currency configuration.
	 */
	public static function prices( $product ) {
		$config = $product->get_meta( '_pc_pack_prices' );
		if ( ! $config ) {
			return array();
		}
		if ( ! is_array( $config ) || ( $config['currency'] ?? '' ) !== get_woocommerce_currency() ) {
			throw new \RuntimeException( esc_html__( 'Pack prices need review before this product can be purchased.', 'parameter-commerce' ) );
		}
		foreach ( array( 6, 12 ) as $size ) {
			$value = $config[ $size ] ?? null;
			if ( ! is_scalar( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value <= 0 || wc_format_decimal( $value, wc_get_price_decimals() ) !== (string) $value ) {
				throw new \RuntimeException( esc_html__( 'Pack prices need review before this product can be purchased.', 'parameter-commerce' ) );
			}
		}
		return array(
			6  => $config[6],
			12 => $config[12],
		);
	}

	/** Native product editor fields. */
	public static function settings() {
		global $product_object;
		$config = $product_object ? $product_object->get_meta( '_pc_pack_prices' ) : array();
		echo '<div class="options_group show_if_variable">';
		foreach ( array( 6, 12 ) as $size ) {
			woocommerce_wp_text_input(
				array(
					'id'          => '_pc_pack_price_' . $size,
					/* translators: 1: number of pairs, 2: store currency. */
					'label'       => sprintf( __( '%1$d-pair pack price (%2$s)', 'parameter-commerce' ), $size, get_woocommerce_currency() ),
					'value'       => is_array( $config ) ? ( $config[ $size ] ?? '' ) : '',
					'data_type'   => 'price',
					'description' => __( 'Optional: enter both pack totals, using the same tax basis as regular prices. Leave both blank to sum pair prices. Variation stock and weight still represent one pair.', 'parameter-commerce' ),
				)
			);
		}
		echo '</div>';
	}

	/**
	 * Save both prices together under native product permissions and nonce.
	 *
	 * @param \WC_Product $product Product being edited.
	 */
	public static function save( $product ) {
		if ( ! $product->is_type( 'variable' ) || ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['woocommerce_meta_nonce'], $_POST['_pc_pack_price_6'], $_POST['_pc_pack_price_12'] ) || ! is_string( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$config = array( 'currency' => get_woocommerce_currency() );
		foreach ( array( 6, 12 ) as $size ) {
			$raw = wp_unslash( $_POST[ '_pc_pack_price_' . $size ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Both keys checked above; normalized below.
			if ( ! is_string( $raw ) ) {
				return;
			}
			$config[ $size ] = '' === trim( $raw ) ? '' : wc_format_decimal( $raw, wc_get_price_decimals() );
		}
		if ( '' === $config[6] && '' === $config[12] ) {
			$product->delete_meta_data( '_pc_pack_prices' );
			return;
		}
		$trial = clone $product;
		$trial->update_meta_data( '_pc_pack_prices', $config );
		try {
			self::prices( $trial );
			$product->update_meta_data( '_pc_pack_prices', $config );
		} catch ( \RuntimeException $e ) {
			\WC_Admin_Meta_Boxes::add_error( $e->getMessage() );
		}
	}

	/**
	 * Show the policy only while native settings offer unconditional free US shipping.
	 * More complex coverage must be reviewed before reusing this broad label.
	 *
	 * @return bool
	 */
	public static function free_us_shipping() {
		if ( ! wc_shipping_enabled() || 'specific' !== get_option( 'woocommerce_ship_to_countries' ) || array( 'US' ) !== get_option( 'woocommerce_specific_ship_to_countries' ) ) {
			return false;
		}
		$zones = \WC_Shipping_Zones::get_zones();
		if ( 1 !== count( $zones ) || ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) ) {
			return false;
		}
		$row       = reset( $zones );
		$locations = $row['zone_locations'];
		if ( 1 !== count( $locations ) || 'US' !== $locations[0]->code || 'country' !== $locations[0]->type ) {
			return false;
		}
		$methods = ( new \WC_Shipping_Zone( $row['id'] ) )->get_shipping_methods( true );
		$method  = reset( $methods );
		return 1 === count( $methods ) && 'free_shipping' === $method->id && '' === $method->get_option( 'requires' );
	}

	/**
	 * Full pack prices for product and catalog displays, including staff drafts.
	 *
	 * @param string      $html Original native price HTML.
	 * @param \WC_Product $product Displayed product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $parent || ! Pack_Builder::enabled( $parent->get_id() ) ) {
			return $html;
		}
		try {
			$prices = self::prices( $parent );
		} catch ( \RuntimeException $e ) {
			return esc_html( $e->getMessage() );
		}
		$parts = array();
		foreach ( $prices as $size => $price ) {
			/* translators: 1: number of pairs, 2: formatted price. */
			$parts[] = sprintf( __( '%1$d pairs: %2$s', 'parameter-commerce' ), $size, wc_price( wc_get_price_to_display( $parent, array( 'price' => $price ) ) ) ) . $parent->get_price_suffix( $price );
		}
		if ( $parts && self::free_us_shipping() ) {
			$parts[] = '<small class="pc-free-shipping">' . esc_html__( 'Free US shipping', 'parameter-commerce' ) . '</small>';
		}
		return $parts ? implode( ' · ', $parts ) : $html;
	}

	/**
	 * Reprice complete validated groups from current server configuration.
	 * Invalid groups retain native prices and are rejected by pack cart validation.
	 * Each line receives whole minor currency units, preserving exact pack totals.
	 *
	 * @param \WC_Cart $cart Native cart.
	 */
	public static function calculate( $cart ) {
		$groups = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['pc_pack'] ) ) {
				continue;
			}
			// Reset from storage on every pass; never reuse a prior discounted object.
			$fresh = wc_get_product( $item['variation_id'] );
			if ( $fresh ) {
				$cart->cart_contents[ $key ]['data'] = clone $fresh;
			}
			$id = $item['pc_pack']['id'] ?? '';
			if ( is_string( $id ) && '' !== $id ) {
				$groups[ $id ][ $key ] = $item;
			}
		}
		foreach ( $groups as $items ) {
			$first = reset( $items );
			$pack  = $first['pc_pack'];
			try {
				$parent = wc_get_product( $first['product_id'] );
				if ( ! $parent || ! Pack_Builder::enabled( $parent->get_id() ) ) {
					continue;
				}
				$prices = self::prices( $parent );
				$size   = (int) ( $pack['size'] ?? 0 );
				if ( ! isset( $prices[ $size ] ) ) {
					continue;
				}
				$selected = Pack_Builder::selection( $parent->get_id(), $size, $pack['items'] ?? array() );
				$actual   = array();
				foreach ( $items as $item ) {
					if ( $item['pc_pack'] !== $pack || (int) $item['product_id'] !== $parent->get_id() || (int) ( $pack['product'] ?? 0 ) !== $parent->get_id() ) {
						continue 2;
					}
					$vid            = (int) $item['variation_id'];
					$actual[ $vid ] = ( $actual[ $vid ] ?? 0 ) + $item['quantity'];
				}
				ksort( $actual );
				if ( array_map( 'intval', $actual ) !== $selected || (float) array_sum( $actual ) !== (float) array_sum( array_map( 'intval', $actual ) ) ) {
					continue;
				}
				$factor    = 10 ** wc_get_price_decimals();
				$target    = (int) round( (float) $prices[ $size ] * $factor );
				$remaining = $target;
				$allocated = array();
				foreach ( $items as $key => $item ) {
					$numerator         = $target * (int) $item['quantity'];
					$allocated[ $key ] = array(
						'amount'    => intdiv( $numerator, $size ),
						'remainder' => $numerator % $size,
						'variation' => $item['variation_id'],
					);
					$remaining        -= $allocated[ $key ]['amount'];
				}
				uasort(
					$allocated,
					static function ( $a, $b ) {
						$order = $b['remainder'] <=> $a['remainder'];
						return 0 !== $order ? $order : ( $a['variation'] <=> $b['variation'] );
					}
				);
				foreach ( $allocated as $key => $line ) {
					$amount = $line['amount'] + ( $remaining-- > 0 ? 1 : 0 );
					$cart->cart_contents[ $key ]['data']->set_price( $amount / $factor / $items[ $key ]['quantity'] );
				}
			} catch ( \RuntimeException $e ) {
				continue;
			}
		}
	}
}
