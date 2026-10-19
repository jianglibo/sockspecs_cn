<?php
/**
 * Advertise an actually purchasable six-pair offer through Google for WooCommerce.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep Google's component-based feed aligned with the existing pack checkout. */
final class Google_Packs {
	/** Register optional integration hooks without loading the Google plugin. */
	public static function register_hooks() {
		add_filter( 'woocommerce_gla_product_attribute_values', array( self::class, 'attributes' ), 100, 3 );
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'structured_data' ), 100, 2 );
		add_action( 'wp_footer', array( self::class, 'output_structured_data' ), 9 );
	}

	/**
	 * Resolve an opted-in pack parent; ordinary products are unaffected.
	 *
	 * @param \WC_Product $product Product.
	 * @return \WC_Product|false
	 */
	private static function parent_product( $product ) {
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		return $parent && Pack_Builder::enabled( $parent->get_id() ) ? $parent : false;
	}

	/**
	 * One offer = six identical pairs of one native color/size variation.
	 * Never multiply the rounded internal pair price or pool other colors' stock.
	 *
	 * @param \WC_Product $variation Native pair variation.
	 * @return array Canonical offer shared by feed, landing page and JSON-LD.
	 * @throws \RuntimeException Unsupported pack configuration; never fall back to pair prices.
	 */
	public static function offer( $variation ) {
		$parent = self::parent_product( $variation );
		if ( ! $parent || ! $variation->is_type( 'variation' ) ) {
			throw new \RuntimeException( 'Google pack listings require a native pair variation.' );
		}
		$prices = Pack_Pricing::prices( $parent );
		if ( ! $prices || 'USD' !== get_woocommerce_currency() ) {
			throw new \RuntimeException( 'Google pack listings require configured USD pack prices.' );
		}
		$price = wc_format_decimal( wc_get_price_excluding_tax( $parent, array( 'price' => $prices[6] ) ), 2 );
		if ( wc_format_decimal( wc_get_price_to_display( $parent, array( 'price' => $prices[6] ) ), 2 ) !== $price ) {
			throw new \RuntimeException( 'Review Google US pack prices: storefront and feed tax display differ.' );
		}
		$attributes = $variation->get_attributes();
		$color_key  = isset( $attributes['pa_color'] ) ? 'pa_color' : 'color';
		$size_key   = isset( $attributes['pa_size'] ) ? 'pa_size' : 'size';
		$color      = $variation->get_attribute( $color_key );
		$size       = $variation->get_attribute( $size_key );
		if ( '' === $color || '' === $size || in_array( '', $attributes, true ) ) {
			throw new \RuntimeException( 'Google pack listings require explicit color and size.' );
		}
		unset( $attributes[ $color_key ] );
		ksort( $attributes );
		$group     = md5( wp_json_encode( $attributes ) );
		$available = false;
		try {
			$groups    = Pack_Builder::groups( $parent->get_id() );
			$available = isset( $groups[ $group ]['items'][ $variation->get_id() ] ) && $variation->get_stock_quantity() - wc_get_held_stock_quantity( $variation ) >= 6;
		} catch ( \RuntimeException $error ) {
			// Closed stores, hidden parents and invalid stock configuration cannot advertise availability.
			$available = false;
		}
		return array(
			'variation' => $variation->get_id(),
			'group'     => $group,
			'color'     => $color,
			'size'      => $size,
			'pairs'     => 6,
			'price'     => $price,
			'currency'  => 'USD',
			'available' => $available,
			/* translators: 1: model name, 2: color, 3: size. */
			'title'     => sprintf( __( '%1$s — 6 pairs — %2$s — %3$s', 'parameter-commerce' ), $parent->get_name(), $color, $size ),
			'url'       => add_query_arg(
				array(
					'pc_pack_size'    => 6,
					'pc_pack_group'   => $group,
					'pc_pack_variant' => $variation->get_id(),
				),
				get_permalink( $parent->get_id() )
			) . '#pc-pack-title',
		);
	}

	/**
	 * Override both supported Google API adapters using their public extension hook.
	 * Keep native offer IDs so inventory updates and deletions keep working.
	 *
	 * @param array       $values Previous overrides.
	 * @param \WC_Product $product Native product.
	 * @param object      $adapter Google feed adapter.
	 * @return array
	 * @throws \RuntimeException An unfamiliar adapter/currency must be reviewed before publishing.
	 * @throws \Automattic\WooCommerce\GoogleListingsAndAds\Exception\InvalidValue Per-product feed validation error.
	 */
	public static function attributes( $values, $product, $adapter ) {
		try {
			return self::feed_attributes( $values, $product, $adapter );
		} catch ( \RuntimeException $error ) {
			// Report a per-product validation failure in Google's normal batch pipeline.
			if ( class_exists( '\\Automattic\\WooCommerce\\GoogleListingsAndAds\\Exception\\InvalidValue' ) ) {
				throw new \Automattic\WooCommerce\GoogleListingsAndAds\Exception\InvalidValue( esc_html( $error->getMessage() ) );
			}
			throw $error;
		}
	}

	/**
	 * Build adapter-specific overrides; called only by the integration filter.
	 *
	 * @param array       $values Previous overrides.
	 * @param \WC_Product $product Native product.
	 * @param object      $adapter Google adapter.
	 * @return array
	 * @throws \RuntimeException Unsupported adapter or market.
	 */
	private static function feed_attributes( $values, $product, $adapter ) {
		if ( ! self::parent_product( $product ) ) {
			return $values;
		}
		$merchant = $adapter instanceof \Automattic\WooCommerce\GoogleListingsAndAds\Product\WCProductInputAdapter;
		$content  = $adapter instanceof \Automattic\WooCommerce\GoogleListingsAndAds\Product\WCProductAdapter;
		if ( ! $merchant && ! $content ) {
			throw new \RuntimeException( 'Google pack feed adapter changed; compatibility review required.' );
		}
		if ( $merchant ) {
			$original = $adapter->get_product_input()->get_attributes();
			$currency = $original['price']['currencyCode'] ?? '';
			$country  = $adapter->get_product_input()->get_feed_label();
		} else {
			$currency = $adapter->getPrice() ? $adapter->getPrice()->getCurrency() : '';
			$country  = $adapter->getTargetCountry();
		}
		if ( 'USD' !== $currency || 'US' !== $country ) {
			throw new \RuntimeException( 'Google pack integration currently supports the USD / US market only.' );
		}
		$offer     = self::offer( $product );
		$price     = $merchant
			? array(
				'amountMicros' => (string) (int) round( (float) $offer['price'] * 1000000 ),
				'currencyCode' => 'USD',
			)
			: array(
				'value'    => $offer['price'],
				'currency' => 'USD',
			);
		$overrides = array_merge(
			$values,
			array(
				'title'                  => $offer['title'],
				'description'            => wp_strip_all_tags( __( 'Six pairs in the selected color and size. The displayed price is for the entire pack. You can also mix colors or choose a 12-pair pack on our website.', 'parameter-commerce' ) . ' ' . wc_get_product( $product->get_parent_id() )->get_description() ),
				'price'                  => $price,
				'salePrice'              => null,
				'salePriceEffectiveDate' => null,
				'link'                   => $offer['url'],
				'mobileLink'             => null,
				'adsRedirect'            => null,
				'multipack'              => $merchant ? '6' : 6,
				'isBundle'               => false,
				'color'                  => $offer['color'],
				'size'                   => $offer['size'],
				'availability'           => $offer['available'] ? 'in_stock' : 'out_of_stock',
				'unitPricingMeasure'     => null,
				'unitPricingBaseMeasure' => null,
				'shippingWeight'         => null,
				'shippingLength'         => null,
				'shippingWidth'          => null,
				'shippingHeight'         => null,
			)
		);
		if ( $content ) {
			// The Content API model turns null typed overrides into empty objects.
			// Clear its public mapped properties directly so they are omitted on serialization.
			foreach ( $overrides as $key => $value ) {
				if ( null === $value ) {
					$adapter->{$key} = null;
					unset( $overrides[ $key ] );
				}
			}
			$overrides['sizes'] = array( $offer['size'] );
			unset( $overrides['size'] );
		}
		return $overrides;
	}

	/**
	 * Validate a read-only landing selection. GET never adds anything to the cart.
	 *
	 * @param \WC_Product $product Viewed parent.
	 * @return array|null
	 */
	public static function landing( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public offer selection.
		$id    = isset( $_GET['pc_pack_variant'] ) && is_string( $_GET['pc_pack_variant'] ) ? sanitize_text_field( wp_unslash( $_GET['pc_pack_variant'] ) ) : '';
		$size  = isset( $_GET['pc_pack_size'] ) && is_string( $_GET['pc_pack_size'] ) ? sanitize_text_field( wp_unslash( $_GET['pc_pack_size'] ) ) : '';
		$group = isset( $_GET['pc_pack_group'] ) && is_string( $_GET['pc_pack_group'] ) ? sanitize_key( $_GET['pc_pack_group'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! ctype_digit( $id ) || '6' !== $size ) {
			return null;
		}
		$variation = wc_get_product( (int) $id );
		if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product->get_id() || 'publish' !== $variation->get_status() || get_post_field( 'post_password', $variation->get_id() ) ) {
			return null;
		}
		try {
			$offer = self::offer( $variation );
			return $group === $offer['group'] ? $offer : null;
		} catch ( \RuntimeException $error ) {
			return null;
		}
	}

	/** Emit script-safe JSON without HTML-encoding the advertised URL's query separators. */
	public static function output_structured_data() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! WC()->structured_data ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! self::landing( $product ) ) {
			return;
		}
		// Preserve WooCommerce's product-page schema and its existing visibility filters.
		$types = array_filter( apply_filters( 'woocommerce_structured_data_type_for_page', array( 'product', '', 'review', 'breadcrumblist', 'order' ) ) );
		$data  = WC()->structured_data->get_structured_data( $types );
		$json  = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
		if ( $data && false !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* safely escapes script delimiters without corrupting JSON strings.
		}
	}

	/**
	 * Match the advertised selection's price/availability in public structured data.
	 *
	 * @param array       $markup Previously gated public product schema.
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function structured_data( $markup, $product ) {
		if ( ! $markup || ! self::parent_product( $product ) ) {
			return $markup;
		}
		$offer = self::landing( self::parent_product( $product ) );
		if ( ! $offer ) {
			return $markup;
		}
		$markup['name']   = $offer['title'];
		$markup['url']    = $offer['url'];
		$markup['color']  = $offer['color'];
		$markup['size']   = $offer['size'];
		$markup['offers'] = array(
			'@type'         => 'Offer',
			'url'           => $offer['url'],
			'price'         => $offer['price'],
			'priceCurrency' => $offer['currency'],
			'availability'  => 'https://schema.org/' . ( $offer['available'] ? 'InStock' : 'OutOfStock' ),
		);
		return $markup;
	}
}
