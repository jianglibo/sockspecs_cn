<?php
/**
 * Public discovery of pack purchase units, without a second commerce API.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep public machine-readable data consistent with visible pack offers. */
final class Product_Discovery {
	/** Register native extension points; no database changes. */
	public static function register_hooks() {
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'structured_data' ), 90, 2 );
		add_action( 'woocommerce_blocks_loaded', array( self::class, 'register_store_data' ) );
	}

	/** Register read-only product fields with WooCommerce's existing Store API. */
	public static function register_store_data() {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema::IDENTIFIER,
					'namespace'       => 'parameter-commerce',
					'data_callback'   => array( self::class, 'store_data' ),
					'schema_callback' => array( self::class, 'store_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Public parent scope only, including when called by logged-in staff.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return \WC_Product|false
	 */
	private static function public_parent( $product ) {
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $parent || ! Public_Specifications::visible( $parent->get_id() ) ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) && ! Public_Specifications::visible( $parent->get_id(), $product->get_id() ) ) {
			return false;
		}
		return $parent;
	}

	/**
	 * Copy only the already-visible facts table, retaining each full row label.
	 * No inference from source files, private metadata, weights or diagrams.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	public static function facts( $product ) {
		if ( $product->get_meta( '_pc_structured_specs' ) ) {
			$spec  = Repository::public_specifications( $product->get_id() );
			$facts = array();
			foreach ( $spec['groups'] as $group ) {
				foreach ( $group['metrics'] as $metric ) {
					if ( null !== $metric['value'] ) {
						$facts[] = array(
							'name'  => $metric['label'] . ' — ' . $metric['record']['size_scope'],
							'value' => Public_Specifications::value( $metric ) . ' ' . $metric['unit'] . ' (' . $metric['source_label'] . ')',
						);
					}
				}
			}
			return $facts;
		}
		if ( ! class_exists( '\DOMDocument' ) ) {
			return Yarn_Specifications::facts( $product );
		}
		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$source   = $product->meta_exists( Catalog_Specifications::META ) ? Catalog_Specifications::html( $product ) : $product->get_description();
		$document->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $source . '</body></html>', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new \DOMXPath( $document );
		$rows  = $xpath->query( '//section[contains(concat(" ",normalize-space(@class)," ")," pc-product-facts ")]//table[not(ancestor::details)]/tbody/tr' );
		$facts = Yarn_Specifications::facts( $product );
		foreach ( $rows as $row ) {
			$labels = $xpath->query( './th', $row );
			$values = $xpath->query( './td', $row );
			if ( 1 !== $labels->length || 1 !== $values->length ) {
				continue;
			}
			foreach ( $xpath->query( './/br', $values->item( 0 ) ) as $break ) {
				$break->parentNode->replaceChild( $document->createTextNode( '; ' ), $break ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			}
			$facts[] = array(
				'name'  => sanitize_text_field( $labels->item( 0 )->textContent ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				'value' => sanitize_text_field( $values->item( 0 )->textContent ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			);
		}
		return $facts;
	}

	/**
	 * Pack offers and same-size stock capacity from the existing purchase validator.
	 * Native component prices are never represented as standalone offers.
	 *
	 * @param \WC_Product $product Parent.
	 * @return array
	 */
	private static function packs( $product ) {
		try {
			$prices = Pack_Pricing::prices( $product );
			$groups = Pack_Builder::groups( $product->get_id() );
		} catch ( \RuntimeException $e ) {
			return array();
		}
		$capacity = 0;
		foreach ( $groups as $group ) {
			$pairs = 0;
			foreach ( $group['items'] as $variation ) {
				$pairs += max( 0, (int) $variation->get_stock_quantity() - wc_get_held_stock_quantity( $variation ) );
			}
			$capacity = max( $capacity, $pairs );
		}
		$packs = array();
		foreach ( $prices as $quantity => $price ) {
			$packs[] = array(
				'pairs'     => $quantity,
				'price'     => wc_format_decimal( wc_get_price_to_display( $product, array( 'price' => $price ) ), wc_get_price_decimals() ),
				'currency'  => get_woocommerce_currency(),
				'available' => $capacity >= $quantity,
				'url'       => add_query_arg( 'pc_pack_size', $quantity, get_permalink( $product->get_id() ) ) . '#pc-pack-title',
			);
		}
		return $packs;
	}

	/**
	 * Replace the native per-pair offers only for opted-in pack products.
	 *
	 * @param array       $markup Native JSON-LD object.
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function structured_data( $markup, $product ) {
		$parent = self::public_parent( $product );
		if ( ! $parent ) {
			return array();
		}
		foreach ( self::facts( $parent ) as $fact ) {
			$markup['additionalProperty'][] = array_merge( array( '@type' => 'PropertyValue' ), $fact );
		}
		if ( ! Pack_Builder::enabled( $parent->get_id() ) ) {
			return $markup;
		}
		// Ignore native selected-variation overrides: they identify single pairs.
		$markup['sku']  = $parent->get_sku();
		$markup['url']  = get_permalink( $parent->get_id() );
		$markup['name'] = $parent->get_name();
		unset( $markup['gtin'], $markup['inProductGroupWithID'], $markup['offers'] );
		$markup['additionalProperty'][] = array(
			'@type' => 'PropertyValue',
			'name'  => __( 'Purchase options', 'parameter-commerce' ),
			'value' => __( '6 or 12 pairs of one model in one size; colors can be mixed. Individual pairs are not sold separately.', 'parameter-commerce' ),
		);
		foreach ( self::packs( $parent ) as $pack ) {
			/* translators: %d: number of pairs. */
			$name  = sprintf( __( '%d-pair pack', 'parameter-commerce' ), $pack['pairs'] );
			$offer = array(
				'@type'         => 'Offer',
				'@id'           => get_permalink( $parent->get_id() ) . '#pack-' . $pack['pairs'],
				'name'          => $name,
				'description'   => __( 'Whole-pack price. Choose one size and mix colors within this model.', 'parameter-commerce' ),
				'price'         => $pack['price'],
				'priceCurrency' => $pack['currency'],
				'url'           => $pack['url'],
				'availability'  => 'https://schema.org/' . ( $pack['available'] ? 'InStock' : 'OutOfStock' ),
				'seller'        => array(
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
					'url'   => home_url( '/' ),
				),
				'itemOffered'   => array(
					'@type'              => 'Product',
					'name'               => $parent->get_name() . ' — ' . $name,
					'additionalProperty' => array(
						'@type' => 'PropertyValue',
						'name'  => __( 'Pairs per pack', 'parameter-commerce' ),
						'value' => $pack['pairs'],
					),
				),
			);
			if ( Pack_Pricing::free_us_shipping() ) {
				$offer['shippingDetails'] = array(
					'@type'               => 'OfferShippingDetails',
					'shippingRate'        => array(
						'@type'    => 'MonetaryAmount',
						'value'    => '0',
						'currency' => $pack['currency'],
					),
					'shippingDestination' => array(
						'@type'          => 'DefinedRegion',
						'addressCountry' => 'US',
					),
				);
			}
			$markup['offers'][] = $offer;
		}
		return $markup;
	}

	/**
	 * Valid color/size combinations and links to the existing server-rendered form.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	private static function size_groups( $product ) {
		try {
			$groups = Pack_Builder::groups( $product->get_id() );
		} catch ( \RuntimeException $e ) {
			return array();
		}
		$result = array();
		foreach ( $groups as $key => $group ) {
			$row = array(
				'label'      => wp_strip_all_tags( $group['label'] ),
				'url'        => add_query_arg( 'pc_pack_group', $key, get_permalink( $product->get_id() ) ) . '#pc-pack-title',
				'variations' => array(),
			);
			foreach ( $group['items'] as $variation ) {
				$row['variations'][] = array(
					'id'      => $variation->get_id(),
					'sku'     => $variation->get_sku(),
					'options' => wp_strip_all_tags( wc_get_formatted_variation( $variation, true, true ) ),
				);
			}
			$result[] = $row;
		}
		return $result;
	}

	/**
	 * Safe extension; native price fields remain component prices for compatibility.
	 *
	 * @param \WC_Product $product Native response product.
	 * @return array
	 */
	public static function store_data( $product ) {
		$parent = self::public_parent( $product );
		if ( ! $parent ) {
			return array();
		}
		$data = array( 'facts' => self::facts( $parent ) );
		if ( Pack_Builder::enabled( $parent->get_id() ) ) {
			$data['purchase_unit']           = 'pack';
			$data['component_quantity_unit'] = 'pair';
			$data['native_price_basis']      = 'component_pair_not_standalone_offer';
			$data['same_model_and_size']     = true;
			$data['mixed_colors']            = true;
			$data['purchase_url']            = get_permalink( $parent->get_id() ) . '#pc-pack-title';
			$data['purchase_method']         = 'product_page_pack_form';
			$data['size_groups']             = self::size_groups( $parent );
			$data['packs']                   = self::packs( $parent );
			$data['facts_scope']             = 'parent_product_rows_as_labelled_not_variation_measurements';
		}
		return $data;
	}

	/** API schema. @return array */
	public static function store_schema() {
		$schema = array();
		foreach ( array( 'purchase_unit', 'component_quantity_unit', 'native_price_basis', 'purchase_url', 'purchase_method', 'facts_scope' ) as $key ) {
			$schema[ $key ] = array(
				'type'     => 'string',
				'readonly' => true,
			);
		}
		foreach ( array( 'same_model_and_size', 'mixed_colors' ) as $key ) {
			$schema[ $key ] = array(
				'type'     => 'boolean',
				'readonly' => true,
			);
		}
		$schema['facts']       = array(
			'type'     => 'array',
			'readonly' => true,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array( 'type' => 'string' ),
					'value' => array( 'type' => 'string' ),
				),
			),
		);
		$schema['size_groups'] = array(
			'type'     => 'array',
			'readonly' => true,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					'label'      => array( 'type' => 'string' ),
					'url'        => array( 'type' => 'string' ),
					'variations' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'      => array( 'type' => 'integer' ),
								'sku'     => array( 'type' => 'string' ),
								'options' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
		);
		$schema['packs']       = array(
			'type'     => 'array',
			'readonly' => true,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					'pairs'     => array(
						'type' => 'integer',
						'enum' => array( 6, 12 ),
					),
					'price'     => array( 'type' => 'string' ),
					'currency'  => array( 'type' => 'string' ),
					'available' => array( 'type' => 'boolean' ),
					'url'       => array( 'type' => 'string' ),
				),
			),
		);
		return $schema;
	}
}
