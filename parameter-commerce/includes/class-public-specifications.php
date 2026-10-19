<?php
/**
 * Public product specifications with exact scope and visibility checks.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WooCommerce owns all catalog and purchase behavior. */
final class Public_Specifications {
	/** Register native template integration and a read-only endpoint. @return void */
	public static function register_hooks() {
		add_filter( 'woocommerce_product_tabs', array( self::class, 'tabs' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'template_redirect', array( self::class, 'uncached' ) );
		add_action( 'wp_ajax_pc_specifications', array( self::class, 'endpoint' ) );
		add_action( 'wp_ajax_nopriv_pc_specifications', array( self::class, 'endpoint' ) );
	}

	/** Avoid serving withdrawn evidence from a plugin/page cache. @return void */
	public static function uncached() {
		if ( Plugin::has_woocommerce() && function_exists( 'is_product' ) && is_product() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/**
	 * Public scope checks never inherit logged-in staff preview privileges.
	 *
	 * @param int $product_id Parent product.
	 * @param int $variation_id Child product or zero.
	 * @return bool
	 */
	public static function visible( $product_id, $variation_id = 0 ) {
		if ( 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) ) {
			return false;
		}
		return self::catalog_visible( $product_id, $variation_id );
	}

	/**
	 * Catalog-only staff preview; never authorizes public evidence or checkout.
	 *
	 * @param int $product_id Parent product.
	 * @param int $variation_id Child product or zero.
	 * @return bool
	 */
	public static function catalog_visible( $product_id, $variation_id = 0 ) {
		if ( ! Plugin::has_woocommerce() || ( 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) && ! current_user_can( 'manage_woocommerce' ) ) ) {
			return false;
		}
		foreach ( array( $product_id, $variation_id ) as $id ) {
			if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (float) $id > 2147483647 ) {
				return false;
			}
		}
		$product = $product_id ? wc_get_product( (int) $product_id ) : false;
		if ( ! $product || get_post_meta( $product->get_id(), '_pc_demo_catalog', true ) || $product->is_type( 'variation' ) || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility() || get_post_field( 'post_password', $product->get_id() ) || ! $product->is_visible() ) {
			return false;
		}
		if ( (int) $variation_id ) {
			$variation = wc_get_product( (int) $variation_id );
			return $product->is_type( 'variable' ) && $variation && $variation->is_type( 'variation' ) && (int) $variation->get_parent_id() === (int) $product_id && 'publish' === $variation->get_status() && ! get_post_field( 'post_password', $variation->get_id() ) && $variation->variation_is_visible() && $variation->variation_is_active() && $variation->is_in_stock();
		}
		return true;
	}

	/**
	 * Add a native tab; WooCommerce also injects it into Product Details blocks.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function tabs( $tabs ) {
		global $product;
		if ( Catalog_Specifications::visible( $product ) && ( Catalog_Specifications::html( $product ) || self::record_scopes( $product ) ) ) {
			$tabs['pc_specifications'] = array(
				'title'    => __( 'Specifications & evidence', 'parameter-commerce' ),
				'priority' => 15,
				'callback' => array( self::class, 'panel' ),
			);
		}
		return $tabs;
	}

	/** Scope assets to WooCommerce single-product pages. @return void */
	public static function assets() {
		if ( ! Plugin::has_woocommerce() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_enqueue_style( 'pc-specifications', plugins_url( 'assets/specifications.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.19' );
		wp_enqueue_script( 'pc-specifications', plugins_url( 'assets/specifications.js', dirname( __DIR__ ) . '/parameter-commerce.php' ), array( 'jquery' ), '0.5.17', true );
		wp_localize_script(
			'pc-specifications',
			'pcSpecifications',
			array(
				'url'       => admin_url( 'admin-ajax.php' ),
				'loading'   => __( 'Loading specifications for this selection…', 'parameter-commerce' ),
				'error'     => __( 'Specifications could not be loaded. Please retry.', 'parameter-commerce' ),
				'retry'     => __( 'Retry', 'parameter-commerce' ),
				'selection' => __( 'Choose an available size or SKU to see its measurements.', 'parameter-commerce' ),
			)
		);
	}

	/** Render the initial accessible server-side panel. @return void */
	public static function panel() {
		global $product;
		if ( ! Catalog_Specifications::visible( $product ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only scope selection; visibility is checked again below.
		$variation_id = isset( $_GET['pc_variation'] ) ? ( is_string( $_GET['pc_variation'] ) ? sanitize_text_field( wp_unslash( $_GET['pc_variation'] ) ) : 'invalid' ) : 0;
		echo '<section class="pc-specifications" data-product-id="' . esc_attr( $product->get_id() ) . '" data-variation-id="' . esc_attr( $variation_id ) . '">';
		echo '<h2>' . esc_html__( 'Specifications & evidence', 'parameter-commerce' ) . '</h2>';
		if ( ! $product->get_meta( '_pc_structured_specs' ) ) {
			echo wp_kses_post( Catalog_Specifications::html( $product ) );
		}
		if ( Catalog_Specifications::html( $product ) && ! $product->get_meta( '_pc_structured_specs' ) ) {
			echo wp_kses_post( Yarn_Specifications::render( $product ) );
		}
		$record_scopes = self::record_scopes( $product );
		if ( ! $record_scopes ) {
			echo '</section>';
			return;
		}
		if ( $product->is_type( 'variable' ) && array_diff( $record_scopes, array( 0 ) ) ) {
			// A bounded, server-rendered alternative also works without variation JS.
			$choices = wc_get_products(
				array(
					'type'    => 'variation',
					'parent'  => $product->get_id(),
					'status'  => 'publish',
					'limit'   => 101,
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			echo '<form class="pc-scope-form" method="get" action="' . esc_url( get_permalink( $product->get_id() ) . '#tab-pc_specifications' ) . '">';
			Comparison::hidden_selection();
			$permalink_query = array();
			wp_parse_str( (string) wp_parse_url( get_permalink( $product->get_id() ), PHP_URL_QUERY ), $permalink_query );
			foreach ( $permalink_query as $key => $value ) {
				if ( is_scalar( $value ) ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}
			echo '<p>' . esc_html__( 'Choose a size/color for its weight and dimensions, or Product-level records for sample composition reports.', 'parameter-commerce' ) . '</p>';
			echo '<label>' . esc_html__( 'Specification scope', 'parameter-commerce' ) . ' <select name="pc_variation"><option value="0">' . esc_html__( 'Product-level records', 'parameter-commerce' ) . '</option>';
			foreach ( array_slice( $choices, 0, 100 ) as $choice ) {
				if ( in_array( $choice->get_id(), $record_scopes, true ) && self::scope_visible( $product, $choice->get_id() ) ) {
					echo '<option value="' . esc_attr( $choice->get_id() ) . '" ' . selected( $variation_id, $choice->get_id(), false ) . '>' . esc_html( self::scope_label( $choice ) ) . '</option>';
				}
			}
			echo '</select></label> <button type="submit">' . esc_html__( 'Show specifications', 'parameter-commerce' ) . '</button></form>';
			if ( count( $choices ) > 100 ) {
				echo '<p>' . esc_html__( 'For further sizes, use the product options above.', 'parameter-commerce' ) . '</p>';
			}
		}
		echo '<p class="pc-spec-announcement" role="status" aria-live="polite"></p><div class="pc-spec-content">';
		try {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes every template field.
			echo self::render( $product->get_id(), $variation_id, true );
		} catch ( Failure $error ) {
			echo '<p role="alert">' . esc_html( $error->getMessage() ) . '</p>';
		}
		echo '</div></section>';
	}

	/**
	 * Find nonempty published scopes without exposing private draft records.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	public static function record_scopes( $product ) {
		if ( ! $product || ! Catalog_Specifications::visible( $product ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Publication changes must be visible immediately; bounded indexed read.
		$scopes = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT wc_variation_id FROM %i WHERE wc_product_id = %d AND status = %s AND verified_by > 0 AND published_at IS NOT NULL AND published_slot IS NOT NULL AND (value_num IS NOT NULL OR value_enum IS NOT NULL) LIMIT 101', $wpdb->prefix . 'pc_claims', $product->get_id(), 'published' ) );
		return array_values( array_filter( array_map( 'intval', $scopes ), static fn( $id ) => self::scope_visible( $product, $id ) ) );
	}

	/**
	 * Scope choice for public pages or authorized draft previews.
	 *
	 * @param \WC_Product $product Parent.
	 * @param int         $id Child ID, or zero.
	 * @return bool
	 */
	public static function scope_visible( $product, $id ) {
		if ( self::visible( $product->get_id(), $id ) ) {
			return true;
		}
		$child = $id ? wc_get_product( $id ) : $product;
		return Catalog_Specifications::visible( $product ) && current_user_can( 'manage_woocommerce' ) && $child && ( ! $id || (int) $child->get_parent_id() === $product->get_id() );
	}

	/** Public, bounded GET reads need no mutation nonce. @return void */
	public static function endpoint() {
		nocache_headers();
		if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_send_json_error( array(), 405 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Anonymous read only, strict catalog visibility and exact scope below.
		$product_id   = isset( $_GET['product_id'] ) && is_string( $_GET['product_id'] ) ? sanitize_text_field( wp_unslash( $_GET['product_id'] ) ) : '';
		$variation_id = isset( $_GET['variation_id'] ) ? ( is_string( $_GET['variation_id'] ) ? sanitize_text_field( wp_unslash( $_GET['variation_id'] ) ) : 'invalid' ) : '0';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		try {
			wp_send_json_success(
				array(
					'html'         => self::render( $product_id, $variation_id ),
					'variation_id' => (int) $variation_id,
				)
			);
		} catch ( Failure $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), $error->status );
		}
	}

	/**
	 * Render one public scope without leaking raw claims or evidence.
	 *
	 * @param int  $product_id Parent product ID.
	 * @param int  $variation_id Variation ID.
	 * @param bool $staff_preview Allow authorized page preview, never the public endpoint.
	 * @return string Escaped HTML.
	 */
	public static function render( $product_id, $variation_id = 0, $staff_preview = false ) {
		$specifications = $staff_preview && current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product_id ) ? Repository::specifications( $product_id, $variation_id ) : Repository::public_specifications( $product_id, $variation_id );
		foreach ( $specifications['groups'] as $key => &$group ) {
			$group['metrics'] = array_filter( $group['metrics'], static fn( $metric ) => null !== $metric['value'] && ! empty( $metric['record'] ) );
			if ( ! $group['metrics'] ) {
				unset( $specifications['groups'][ $key ] );
			}
		}
		unset( $group );
		if ( ! $specifications['groups'] ) {
			return '';
		}
		$variation_id  = (int) $variation_id;
		$product       = wc_get_product( (int) $product_id );
		$scope_product = $variation_id ? wc_get_product( (int) $variation_id ) : $product;
		ob_start();
		require dirname( __DIR__ ) . '/templates/specifications.php';
		return ob_get_clean();
	}

	/**
	 * Consumer scope name with SKU, without internal database IDs.
	 *
	 * @param \WC_Product $product Scope product.
	 * @return string
	 */
	public static function scope_label( $product ) {
		return $product->get_name() . ( $product->get_sku() ? ' — ' . $product->get_sku() : '' );
	}

	/**
	 * Localize numbers and construction choices without changing stored data.
	 *
	 * @param array $metric Projected metric.
	 * @return string
	 */
	public static function value( array $metric ) {
		if ( null === $metric['value'] ) {
			return $metric['missing_label'];
		}
		if ( 'enum' !== $metric['type'] ) {
			$value = (string) $metric['value'];
			return wc_format_localized_decimal( str_contains( $value, '.' ) ? rtrim( rtrim( $value, '0' ), '.' ) : $value );
		}
		$labels = array(
			'carded'             => __( 'Carded', 'parameter-commerce' ),
			'short-staple'       => __( 'Short-staple', 'parameter-commerce' ),
			'medium-staple'      => __( 'Medium-staple', 'parameter-commerce' ),
			'long-staple'        => __( 'Long-staple', 'parameter-commerce' ),
			'extra-long-staple'  => __( 'Extra-long-staple', 'parameter-commerce' ),
			'merino'             => __( 'Merino', 'parameter-commerce' ),
			'other-sheep-breed'  => __( 'Other documented sheep breed', 'parameter-commerce' ),
			'mixed-sheep-breeds' => __( 'Mixed sheep breeds', 'parameter-commerce' ),
			'woollen'            => __( 'Woollen', 'parameter-commerce' ),
			'semi-worsted'       => __( 'Semi-worsted', 'parameter-commerce' ),
			'worsted'            => __( 'Worsted', 'parameter-commerce' ),
			'ring-spun'          => __( 'Ring-spun', 'parameter-commerce' ),
			'compact-ring-spun'  => __( 'Compact ring-spun', 'parameter-commerce' ),
			'rotor-spun'         => __( 'Rotor-spun', 'parameter-commerce' ),
			'air-jet-spun'       => __( 'Air-jet-spun', 'parameter-commerce' ),

			'single-needle'      => __( 'Single-needle', 'parameter-commerce' ),
			'terry-sole'         => __( 'Terry sole', 'parameter-commerce' ),
			'terry-cushioning'   => __( 'Terry cushioning', 'parameter-commerce' ),
			'present'            => __( 'Present', 'parameter-commerce' ),
			'jacquard'           => __( 'Jacquard', 'parameter-commerce' ),
			'tonal-jacquard'     => __( 'Tonal jacquard', 'parameter-commerce' ),
			'shaped'             => __( 'Shaped', 'parameter-commerce' ),
			'combed'             => __( 'Combed', 'parameter-commerce' ),

			'reinforced'         => __( 'Reinforced heel', 'parameter-commerce' ),
			'no-show'            => __( 'No-show', 'parameter-commerce' ),
			'ankle'              => __( 'Ankle', 'parameter-commerce' ),
			'crew'               => __( 'Crew', 'parameter-commerce' ),
			'knee-high'          => __( 'Knee-high', 'parameter-commerce' ),
			'flat'               => __( 'Flat seam', 'parameter-commerce' ),
			'linked'             => __( 'Linked toe', 'parameter-commerce' ),
			'standard'           => __( 'Standard seam', 'parameter-commerce' ),
			'unknown'            => __( 'Unknown', 'parameter-commerce' ),
			'none'               => __( 'No cushioning', 'parameter-commerce' ),
			'light'              => __( 'Light cushioning', 'parameter-commerce' ),
			'medium'             => __( 'Medium cushioning', 'parameter-commerce' ),
			'heavy'              => __( 'Heavy cushioning', 'parameter-commerce' ),
		);
		if ( 'none' === $metric['value'] && 'heel_reinforcement' === ( $metric['key'] ?? '' ) ) {
			return __( 'None declared', 'parameter-commerce' );
		}
		return $labels[ $metric['value'] ] ?? $metric['value'];
	}
}
