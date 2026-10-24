<?php
/**
 * Catalog garment dimensions, separate from shoe fit and approved measurements.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A lightweight, server-rendered product shape guide. */
final class Size_Shape {
	/**
	 * Already rendered product IDs, avoiding duplicate classic/block output.
	 *
	 * @var array
	 */
	private static $rendered = array();

	/** Register product-page and native editor integrations. @return void */
	public static function register_hooks() {
		add_action( 'woocommerce_single_product_summary', array( self::class, 'panel' ), 25 );
		add_filter( 'render_block_woocommerce/product-summary', array( self::class, 'block' ), 10, 3 );
		add_filter( 'render_block_core/post-excerpt', array( self::class, 'block' ), 10, 3 );
		add_filter( 'woocommerce_blocks_hook_compatibility_additional_data', array( self::class, 'block_hooks' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'settings' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_settings' ) );
	}

	/**
	 * Let block rendering control placement instead of WooCommerce's before-excerpt shim.
	 *
	 * @param array  $hooks Existing compatibility definitions.
	 * @param string $template Compatibility template class.
	 * @return array
	 */
	public static function block_hooks( $hooks, $template ) {
		if ( 'SingleProductTemplateCompatibility' === $template ) {
			$hooks[] = array(
				'hook'     => 'woocommerce_single_product_summary',
				'function' => array( self::class, 'panel' ),
				'priority' => 25,
			);
		}
		return $hooks;
	}

	/** Enqueue only on product pages. @return void */
	public static function assets() {
		if ( Plugin::has_woocommerce() && function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_style( 'pc-size-shape', plugins_url( 'assets/size-shape.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.26' );
		}
	}

	/**
	 * Strict positive centimeter value; no measurement is inferred from zero.
	 *
	 * @param mixed $value Input.
	 * @return string
	 */
	public static function centimeters( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\d{1,3}(?:\.\d{1,2})?$/D', (string) $value ) || (float) $value <= 0 || (float) $value > 100 ) {
			return '';
		}
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Optional native cuff-height attribute; never infer it from a measurement.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return bool
	 */
	public static function has_cuff_height( $product ) {
		return $product && $product->is_type( 'variable' ) && ( isset( $product->get_variation_attributes()['pa_cuff-height'] ) || isset( $product->get_variation_attributes()['cuff-height'] ) );
	}

	/**
	 * Every non-color option identifies a distinct shape/weight scope.
	 *
	 * @param \WC_Product $child Variation.
	 * @return array
	 */
	public static function option_attributes( $child ) {
		$attributes = $child->get_attributes();
		unset( $attributes['color'], $attributes['pa_color'] );
		ksort( $attributes );
		return $attributes;
	}

	/**
	 * Match the pack selector's server-rendered selection for cuff-height products.
	 * Missing or invalid groups must not display another height's dimensions.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array Variation IDs.
	 */
	private static function selected_children( $product ) {
		if ( ! self::has_cuff_height( $product ) || ! Pack_Builder::enabled( $product->get_id() ) ) {
			return $product->get_children();
		}
		try {
			$preview = 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) && current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() );
			$groups  = Pack_Builder::groups( $product->get_id(), $preview, true );
		} catch ( \RuntimeException $error ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only option selection.
		$requested = isset( $_GET['pc_pack_group'] ) && is_string( $_GET['pc_pack_group'] ) ? sanitize_key( $_GET['pc_pack_group'] ) : '';
		$selected  = isset( $groups[ $requested ] ) ? $requested : array_key_first( $groups );
		return isset( $groups[ $selected ] ) ? array_keys( $groups[ $selected ]['items'] ) : array();
	}

	/**
	 * Whitelist just the two dimensions from the imported workbook C column.
	 * Never expose the raw row, procurement details, source path or private claims.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	public static function dimensions( $product ) {
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return array();
		}
		$rows = self::structured_dimensions( $product );
		return $rows ? array_merge( reset( $rows ), array( 'sizes' => $rows ) ) : array();
	}

	/**
	 * Derive diagram dimensions from the same approved records as comparison.
	 *
	 * @param \WC_Product $product Parent.
	 * @return array
	 */
	private static function structured_dimensions( $product ) {
		$rows     = array();
		$ids      = $product->is_type( 'variable' ) ? self::selected_children( $product ) : array( 0 );
		$expected = array();
		foreach ( $ids as $id ) {
			$child             = $id ? wc_get_product( $id ) : $product;
			$options           = $id ? self::option_attributes( $child ) : array();
			$size              = $options ? wp_strip_all_tags( wc_get_formatted_variation( $options, true, false ) ) : __( '型号参考（尺码覆盖未确认）', 'parameter-commerce' );
			$expected[ $size ] = ( $expected[ $size ] ?? 0 ) + 1;
		}
		foreach ( $ids as $id ) {
			$spec = self::spec( $product, $id );
			if ( ! $spec ) {
				continue;
			}
			$m   = $spec['groups']['measurement']['metrics'];
			$leg = null !== $m['leg_height_mm']['value'] ? $m['leg_height_mm'] : $m['catalog_height_mm'];
			if ( null === $m['foot_length_mm']['value'] || null === $leg['value'] ) {
				continue;
			}
			$child      = $id ? wc_get_product( $id ) : $product;
			$options    = $id ? self::option_attributes( $child ) : array();
			$conditions = json_decode( $leg['record']['conditions_json'] ?? '{}', true );
			$confirmed  = 'heel_leg_boundary_to_cuff_excluding_heel_curve' === ( $conditions['leg_endpoints'] ?? '' );
			$size       = $options ? wp_strip_all_tags( wc_get_formatted_variation( $options, true, false ) ) : __( '型号参考（尺码覆盖未确认）', 'parameter-commerce' );
			$row        = array(
				'size'      => $size,
				'leg_cm'    => self::centimeters( (string) ( (float) $leg['value'] / 10 ) ),
				'foot_cm'   => self::centimeters( (string) ( (float) $m['foot_length_mm']['value'] / 10 ) ),
				'confirmed' => $confirmed,
				'source'    => $confirmed ? __( '袜筒从后跟分界线至袜口，不含脚跟弧度。', 'parameter-commerce' ) : __( '目录尺寸，测量起止点尚未确认。', 'parameter-commerce' ),
			);
			if ( ! $row['leg_cm'] || ! $row['foot_cm'] ) {
				continue;
			}
			$key = md5( wp_json_encode( $row ) );
			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = $row;
			}
			$rows[ $key ]['colors'][] = $id ? ( $child->get_attribute( 'color' ) ? $child->get_attribute( 'color' ) : $child->get_attribute( 'pa_color' ) ) : '';
		}
		// Product reference is explicit, never copied to individual SKU measurements.
		if ( ! $rows && $product->is_type( 'variable' ) && ! self::has_cuff_height( $product ) ) {
			$spec = self::spec( $product, 0 );
			$m    = $spec['groups']['measurement']['metrics'] ?? array();
			$leg  = $m['leg_height_mm']['value'] ?? $m['catalog_height_mm']['value'] ?? null;
			$foot = $m['foot_length_mm']['value'] ?? null;
			if ( $leg && $foot ) {
				$rows[] = array(
					'size'      => __( '型号参考（尺码覆盖未确认）', 'parameter-commerce' ),
					'leg_cm'    => self::centimeters( (string) ( (float) $leg / 10 ) ),
					'foot_cm'   => self::centimeters( (string) ( (float) $foot / 10 ) ),
					'confirmed' => false,
					'source'    => __( '型号参考，测量起止点与尺码覆盖请查看参数记录。', 'parameter-commerce' ),
				);
			}
		}
		$size_counts = array_count_values( array_column( $rows, 'size' ) );
		foreach ( $rows as &$row ) {
			if ( ! empty( $row['colors'] ) && ( $size_counts[ $row['size'] ] > 1 || count( $row['colors'] ) < ( $expected[ $row['size'] ] ?? 0 ) ) ) {
				$row['size'] .= ' (' . implode( ', ', array_unique( $row['colors'] ) ) . ')';
			}
		}
		unset( $row );
		return array_values( $rows );
	}

	/**
	 * Shared approved projection for the viewer.
	 *
	 * @param \WC_Product $product Parent.
	 * @param int         $id Scope.
	 * @return array
	 */
	private static function spec( $product, $id ) {
		try {
			return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ? Repository::specifications( $product->get_id(), $id ) : Repository::public_specifications( $product->get_id(), $id );
		} catch ( Failure $error ) {
			return array();
		}
	}

	/**
	 * Dimensions share the same structured source as product tables.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function size_dimensions( $product ) {
		return $product ? self::structured_dimensions( $product ) : array();
	}

	/**
	 * Only native size attributes describe fit; never derive size from centimeters.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	public static function sizing( $product ) {
		foreach ( array( 'pa_eu-size', 'eu-size', 'pa_size', 'size' ) as $key ) {
			$value = $product->get_attribute( $key );
			if ( $value ) {
				return array(
					'label' => str_contains( $key, 'eu-size' ) ? __( 'EU size', 'parameter-commerce' ) : __( 'Size', 'parameter-commerce' ),
					'value' => $value,
				);
			}
		}
		return array(
			'label' => __( 'EU size', 'parameter-commerce' ),
			'value' => __( 'To be confirmed', 'parameter-commerce' ),
		);
	}

	/**
	 * Honor storefront visibility, with the normal authorized draft preview.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	private static function visible( $product ) {
		if ( ! $product || get_post_field( 'post_password', $product->get_id() ) ) {
			return false;
		}
		return Public_Specifications::catalog_visible( $product->get_id() ) || ( current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) );
	}

	/**
	 * Visible net weights from exact, approved size/color scopes; never infer missing values.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return string Escaped summary.
	 */
	public static function weight_summary( $product ) {
		if ( ! self::visible( $product ) ) {
			return '';
		}
		$rows = array();
		foreach ( $product->is_type( 'variable' ) ? self::selected_children( $product ) : array( 0 ) as $id ) {
			try {
				$spec = current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ? Repository::specifications( $product->get_id(), $id ) : Repository::public_specifications( $product->get_id(), $id );
			} catch ( Failure $error ) {
				continue;
			}
			$child      = $id ? wc_get_product( $id ) : $product;
			$metric     = $spec['groups']['measurement']['metrics']['pair_weight_g'];
			$conditions = json_decode( $metric['record']['conditions_json'] ?? '{}', true );
			$size       = $id ? wp_strip_all_tags( wc_get_formatted_variation( self::option_attributes( $child ), true, false ) ) : __( '型号参考', 'parameter-commerce' );
			$color      = $child->get_attribute( 'color' ) ? $child->get_attribute( 'color' ) : $child->get_attribute( 'pa_color' );
			if ( null === $metric['value'] || (float) $metric['value'] <= 0 || 'g' !== $metric['unit'] || 'excluded' !== ( $conditions['packaging'] ?? '' ) || ! in_array( $conditions['quantity'] ?? '', array( 'one pair (operator confirmed)', 'one_pair', 'one pair' ), true ) ) {
				continue;
			}
			$key                      = $size . ':' . $metric['value'];
			$rows[ $key ]['size']     = $size;
			$rows[ $key ]['weight']   = $metric['value'];
			$rows[ $key ]['colors'][] = $color;
		}
		if ( ! $rows && $product->is_type( 'variable' ) ) {
			$spec       = self::spec( $product, 0 );
			$metric     = $spec['groups']['measurement']['metrics']['pair_weight_g'] ?? array();
			$conditions = json_decode( $metric['record']['conditions_json'] ?? '{}', true );
			if ( ! empty( $metric['value'] ) && 'excluded' === ( $conditions['packaging'] ?? '' ) && in_array( $conditions['quantity'] ?? '', array( 'one pair (operator confirmed)', 'one_pair', 'one pair' ), true ) ) {
				$rows[] = array(
					'size'   => __( '型号参考（尺码覆盖未确认）', 'parameter-commerce' ),
					'weight' => $metric['value'],
					'colors' => array(),
				);
			}
		}
		if ( ! $rows ) {
			return '';
		}
		$html = '<section class="pc-pair-weight"><h2>' . esc_html__( 'Weight per pair', 'parameter-commerce' ) . '</h2><ul>';
		foreach ( $rows as $row ) {
			$html .= '<li><strong>' . esc_html__( '约 ', 'parameter-commerce' ) . esc_html( wc_format_localized_decimal( wc_format_decimal( $row['weight'], 2, true ) ) ) . ' g</strong> — ' . esc_html( $row['size'] );
			// Only actual weight differences need color labels; missing measurements stay in evidence.
			if ( count( array_filter( $rows, static fn( $r ) => $r['size'] === $row['size'] ) ) > 1 ) {
				$html .= ' (' . esc_html( implode( ', ', $row['colors'] ) ) . ')';
			}
			$html .= '</li>';
		}
		$html .= '</ul><p>' . esc_html__( 'Excludes packaging.', 'parameter-commerce' ) . '</p>';
		return $html . '</section>';
	}

	/**
	 * Escaped markup; model dimensions remain distinct from selected-SKU evidence.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function render( $product ) {
		if ( ! self::visible( $product ) ) {
			return '';
		}
		$weight     = self::weight_summary( $product );
		$dimensions = self::dimensions( $product );
		if ( ! $dimensions ) {
			return $weight;
		}
		$sizing = self::has_cuff_height( $product ) ? array(
			'label' => __( 'Size / options', 'parameter-commerce' ),
			'value' => $dimensions['size'] ?? '',
		) : self::sizing( $product );
		$id     = wp_unique_id( 'pc-size-shape-' );
		// Change the relative leg height; the outline is explicitly schematic.
		$confirmed = ! empty( $dimensions['confirmed'] );
		$top       = $confirmed ? round( 205 - min( 170, max( 30, 190 * (float) $dimensions['leg_cm'] / (float) $dimensions['foot_cm'] ) ), 2 ) : 85;
		// X70's photo shows a low opening and heel tab. Its catalog height has no confirmed endpoints.
		$legacy  = $product->get_meta( '_pc_legacy_sku' );
		$low_cut = in_array( $legacy ? $legacy : $product->get_sku(), array( 'SS-X70', 'SS-001-X70' ), true ) && 'X70' === $product->get_meta( '_pc_selected_supplier_model' );
		ob_start();
		require dirname( __DIR__ ) . ( $low_cut && ! $confirmed ? '/templates/size-shape-low-cut.php' : '/templates/size-shape.php' );
		return $weight . ob_get_clean();
	}

	/** Native classic product summary, immediately before purchase options. @return void */
	public static function panel() {
		global $product;
		if ( $product && empty( self::$rendered[ $product->get_id() ] ) ) {
			$html = self::render( $product );
			if ( $html ) {
				self::$rendered[ $product->get_id() ] = true;
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes each template field.
				echo $html;
			}
		}
	}

	/**
	 * Product Summary block support; exclude related/query-loop product cards.
	 *
	 * @param string    $html Block markup.
	 * @param array     $parsed Parsed block.
	 * @param \WP_Block $block Block instance.
	 * @return string
	 */
	public static function block( $html, $parsed, $block ) {
		if ( ! Plugin::has_woocommerce() || ! is_product() ) {
			return $html;
		}
		$id = (int) ( $block->context['postId'] ?? 0 );
		if ( get_queried_object_id() !== $id || isset( self::$rendered[ $id ] ) || isset( $block->context['queryId'] ) ) {
			return $html;
		}
		$panel = self::render( wc_get_product( $id ) );
		if ( $panel ) {
			self::$rendered[ $id ] = true;
		}
		return $html . $panel;
	}

	/** Native unified measurement editor. @return void */
	public static function settings() {
		Shape_Editor::settings();
	}

	/**
	 * Save through the shared claim repository.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	public static function save_settings( $product ) {
		Shape_Editor::save( $product );
	}
}
