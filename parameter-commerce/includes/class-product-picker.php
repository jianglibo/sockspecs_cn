<?php
/**
 * Staff-only WooCommerce product and variation selectors.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Small progressive enhancement; WooCommerce remains the product authority. */
final class Product_Picker {
	/** Register scoped assets and authenticated lookup. @return void */
	public static function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'wp_ajax_pc_products', array( self::class, 'lookup' ) );
		add_action( 'wp_ajax_nopriv_pc_products', array( self::class, 'lookup' ) );
	}

	/**
	 * Load WooCommerce's own select component only in our editor.
	 *
	 * @param string $hook Admin page hook; empty for the standalone correction page.
	 * @return void
	 */
	public static function assets( $hook = '' ) {
		if ( ( $hook && ! str_ends_with( $hook, '_page_pc-specs' ) ) || ! current_user_can( 'manage_woocommerce' ) || ! Plugin::has_woocommerce() || ! function_exists( 'WC' ) ) {
			return;
		}
		wp_enqueue_script( 'pc-metric-editor', plugins_url( 'assets/metric-editor.js', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '1.0.0', true );
		wp_enqueue_style( 'pc-select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
		if ( ! wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_register_script( 'selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full.min.js', array( 'jquery' ), WC_VERSION, true );
		}
		wp_enqueue_script( 'pc-product-picker', plugins_url( 'assets/product-picker.js', dirname( __DIR__ ) . '/parameter-commerce.php' ), array( 'selectWoo' ), '0.2.1', true );
		wp_localize_script(
			'pc-product-picker',
			'pcProductPicker',
			array(
				'url'           => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'pc_products' ),
				'product'       => __( 'Search products by name, SKU, or ID', 'parameter-commerce' ),
				'scope'         => __( 'Whole product (no variation)', 'parameter-commerce' ),
				'empty'         => __( 'No matching products or variations.', 'parameter-commerce' ),
				'error'         => __( 'Could not load choices. Try again.', 'parameter-commerce' ),
				'searching'     => __( 'Searching…', 'parameter-commerce' ),
				'more'          => __( 'Loading more choices…', 'parameter-commerce' ),
				'refine'        => __( 'Showing the first 20 matches. Refine your search if needed.', 'parameter-commerce' ),
				'chooseProduct' => __( 'Choose a product first.', 'parameter-commerce' ),
			)
		);
	}

	/** Return bounded choices only to authorized staff with a valid nonce. @return void */
	public static function lookup() {
		try {
			Repository::authorize();
		} catch ( Failure $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), $error->status );
		}
		check_ajax_referer( 'pc_products', 'nonce' );
		$term = isset( $_GET['term'] ) && is_string( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) > 100 || ( isset( $_GET['term'] ) && ! is_string( $_GET['term'] ) ) ) {
			wp_send_json_error( array(), 400 );
		}
		$parent = isset( $_GET['parent'] ) && is_scalar( $_GET['parent'] ) ? absint( $_GET['parent'] ) : 0;
		$page   = isset( $_GET['page'] ) && is_scalar( $_GET['page'] ) ? max( 1, min( 1000, absint( $_GET['page'] ) ) ) : 1;
		$kind   = isset( $_GET['kind'] ) && is_string( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : 'product';
		$items  = array();
		$more   = false;
		$full   = false;
		if ( 'variation' === $kind ) {
			$product = $parent ? wc_get_product( $parent ) : false;
			if ( ! $product || ! $product->is_type( 'variable' ) || ! self::selectable( $product ) ) {
				wp_send_json(
					array(
						'results'    => array( self::scope() ),
						'pagination' => array( 'more' => false ),
					)
				);
			}
			$products = wc_get_products(
				array(
					'type'    => 'variation',
					'parent'  => $parent,
					'status'  => array( 'publish', 'private' ),
					'limit'   => 21,
					'offset'  => ( $page - 1 ) * 20,
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$more     = count( $products ) > 20;
			if ( 1 === $page ) {
				$items[] = self::scope();
			}
		} elseif ( '' === $term ) {
			$products = wc_get_products(
				array(
					'limit'   => 21,
					'status'  => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'orderby' => 'title',
					'order'   => 'ASC',
				)
			);
			$full     = count( $products ) > 20;
		} else {
			$store = \WC_Data_Store::load( 'product' );
			if ( ! $store->has_callable( 'search_products' ) ) {
				wp_send_json_error( array(), 503 );
			}
			// WooCommerce's admin search supports names, SKUs and numeric IDs.
			$ids      = $store->search_products( $term, '', false, true, 21 );
			$products = array_filter( array_map( 'wc_get_product', array_slice( $ids, 0, 22 ) ) );
			$products = array_values( array_filter( $products, array( self::class, 'selectable' ) ) );
			$full     = count( $products ) > 20;
		}
		foreach ( array_slice( $products, 0, 20 ) as $product ) {
			if ( self::selectable( $product ) && ( 'variation' === $kind ? $product->get_parent_id() === $parent : ! $product->is_type( 'variation' ) ) ) {
				$items[] = array(
					'id'   => $product->get_id(),
					'text' => self::label( $product ),
				);
			}
		}
		wp_send_json(
			array(
				'results'    => $items,
				'pagination' => array( 'more' => $more ),
				'truncated'  => $full,
			)
		);
	}

	/**
	 * Exclude deleted and auto-draft objects from staff choices.
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool
	 */
	public static function selectable( $product ) {
		return in_array( $product->get_status(), array( 'publish', 'private', 'draft', 'pending', 'future' ), true );
	}

	/**
	 * Plain-text identity including variation attributes and optional SKU.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public static function label( $product ) {
		$label = $product->get_name();
		if ( $product->is_type( 'variation' ) ) {
			$attributes = wc_get_formatted_variation( $product, true, true, true );
			if ( $attributes ) {
				$label .= ' — ' . $attributes;
			}
		}
		$label .= ' (#' . $product->get_id() . ')';
		if ( $product->get_sku() ) {
			$label .= ' — ' . $product->get_sku();
		}
		return html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/** Product-level scope is explicit, never a made-up variation. @return array */
	private static function scope() {
		return array(
			'id'   => 0,
			'text' => __( 'Whole product (no variation)', 'parameter-commerce' ),
		);
	}

	/**
	 * Render a selected value and an accessible manual fallback.
	 *
	 * @param string $name Submitted field name.
	 * @param int    $value Current ID.
	 * @param string $label Field label.
	 * @param string $kind Product or variation.
	 * @param bool   $locked Whether a saved claim fixes this scope.
	 * @return void
	 */
	public static function field( $name, $value, $label, $kind = 'product', $locked = false ) {
		$id      = 'pc-picker-' . $name;
		$value   = is_scalar( $value ) ? absint( $value ) : 0;
		$product = $value ? wc_get_product( $value ) : false;
		$text    = $product ? self::label( $product ) : ( $value ? '#' . $value : ( 'variation' === $kind ? self::scope()['text'] : '' ) );
		echo '<div class="pc-picker-field"><p><label id="' . esc_attr( $id . '-label' ) . '" for="' . esc_attr( $locked ? $id : $id . '-fallback' ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
		if ( $locked ) {
			echo '<output id="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</output><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		} else {
			echo '<select hidden disabled class="pc-product-picker" style="width:100%;max-width:40rem" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-kind="' . esc_attr( $kind ) . '">';
			echo '<option value="' . esc_attr( $value ? $value : ( 'variation' === $kind ? '0' : '' ) ) . '" selected>' . esc_html( $text ) . '</option></select>';
			echo '<input id="' . esc_attr( $id . '-fallback' ) . '" class="pc-picker-fallback regular-text" aria-label="' . esc_attr( $label ) . '" type="number" min="0" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
			echo '<span class="pc-picker-fallback">' . esc_html__( 'Enter an ID if JavaScript is unavailable.', 'parameter-commerce' ) . '</span>';
		}
		echo '</p><p class="description pc-picker-message" role="status" aria-live="polite"></p></div>';
	}
}
