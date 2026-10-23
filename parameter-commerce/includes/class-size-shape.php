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
		if ( $product->get_meta( '_pc_structured_specs' ) || self::has_cuff_height( $product ) ) {
			$rows = self::structured_dimensions( $product );
			return $rows ? array_merge( reset( $rows ), count( $rows ) > 1 ? array( 'sizes' => $rows ) : array() ) : array();
		}
		if ( $product->meta_exists( '_pc_shape_dimensions' ) ) {
			$data = $product->get_meta( '_pc_shape_dimensions' );
			if ( ! is_array( $data ) ) {
				return array();
			}
			$leg  = self::centimeters( $data['leg_cm'] ?? '' );
			$foot = self::centimeters( $data['foot_cm'] ?? '' );
			if ( ! $leg || ! $foot || empty( $data['source'] ) || ! is_string( $data['source'] ) ) {
				return array();
			}
			return array(
				'leg_cm'  => $leg,
				'foot_cm' => $foot,
				'source'  => $data['source'],
			);
		}
		$catalog = $product->get_meta( '_pc_catalog_workbook_source' );
		$sizes   = self::size_dimensions( $product );
		if ( $sizes ) {
			return array_merge( reset( $sizes ), array( 'sizes' => $sizes ) );
		}
		if ( ! is_array( $catalog ) || ! is_array( $catalog['row'] ?? null ) ) {
			return array();
		}
		$matches = array();
		foreach ( $catalog['row'] as $cell => $text ) {
			if ( preg_match( '/^C[1-9]\d*$/D', (string) $cell ) && is_string( $text ) && preg_match( '/^\s*袜筒\s*(\d+(?:\.\d+)?)\s*cm\s*[，,]\s*脚底\s*(\d+(?:\.\d+)?)\s*cm\s*$/uiD', $text, $parts ) ) {
				$matches[] = array(
					'leg_cm'  => self::centimeters( $parts[1] ),
					'foot_cm' => self::centimeters( $parts[2] ),
					'source'  => __( 'Supplier catalog', 'parameter-commerce' ),
				);
			}
		}
		return 1 === count( $matches ) && $matches[0]['leg_cm'] && $matches[0]['foot_cm'] ? $matches[0] : array();
	}

	/**
	 * Derive diagram dimensions from the same approved records as comparison.
	 *
	 * @param \WC_Product $product Parent.
	 * @return array
	 */
	private static function structured_dimensions( $product ) {
		$rows = array();
		foreach ( self::selected_children( $product ) as $id ) {
			try {
				$spec = current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ? Repository::specifications( $product->get_id(), $id ) : Repository::public_specifications( $product->get_id(), $id );
			} catch ( Failure $error ) {
				continue; }
			$m = $spec['groups']['measurement']['metrics'];
			if ( null === $m['foot_length_mm']['value'] || null === $m['catalog_height_mm']['value'] ) {
				if ( self::has_cuff_height( $product ) ) {
					return array();
				}
				continue; }
			$child   = wc_get_product( $id );
			$options = self::option_attributes( $child );
			$key     = wp_json_encode( $options );
			$size    = wc_get_formatted_variation( $options, true, false );
			$row     = array(
				'size'    => $size,
				'leg_cm'  => self::centimeters( (string) ( (float) $m['catalog_height_mm']['value'] / 10 ) ),
				'foot_cm' => self::centimeters( (string) ( (float) $m['foot_length_mm']['value'] / 10 ) ),
				'source'  => __( 'Supplier catalog', 'parameter-commerce' ),
			);
			if ( isset( $rows[ $key ] ) && $rows[ $key ] !== $row ) {
				return array(); }
			$rows[ $key ] = $row;
		}
		return array_values( $rows );
	}

	/**
	 * Explicit catalog dimensions for native size labels; never infer shoe fit.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array
	 */
	public static function size_dimensions( $product ) {
		if ( $product->get_meta( '_pc_structured_specs' ) || self::has_cuff_height( $product ) ) {
			return self::structured_dimensions( $product );
		}
		$rows = $product->get_meta( '_pc_shape_sizes' );
		if ( ! is_array( $rows ) || count( $rows ) > 12 ) {
			return array();
		}
		$valid = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['size'] ?? null ) || ! preg_match( '/^[A-Za-z0-9 -]{1,20}$/D', $row['size'] ) || ! is_string( $row['source'] ?? null ) || ! $row['source'] || strlen( $row['source'] ) > 191 ) {
				return array();
			}
			$leg  = self::centimeters( $row['leg_cm'] ?? '' );
			$foot = self::centimeters( $row['foot_cm'] ?? '' );
			if ( ! $leg || ! $foot || isset( $valid[ $row['size'] ] ) ) {
				return array();
			}
			$valid[ $row['size'] ] = array(
				'size'    => $row['size'],
				'leg_cm'  => $leg,
				'foot_cm' => $foot,
				'source'  => $row['source'],
			);
		}
		return array_values( $valid );
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
		if ( ! self::visible( $product ) || ( ! $product->get_meta( '_pc_structured_specs' ) && ! self::has_cuff_height( $product ) ) ) {
			return '';
		}
		$rows    = array();
		$missing = array();
		foreach ( self::selected_children( $product ) as $id ) {
			try {
				$spec = current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ? Repository::specifications( $product->get_id(), $id ) : Repository::public_specifications( $product->get_id(), $id );
			} catch ( Failure $error ) {
				continue;
			}
			$child      = wc_get_product( $id );
			$metric     = $spec['groups']['measurement']['metrics']['pair_weight_g'];
			$conditions = json_decode( $metric['record']['conditions_json'] ?? '{}', true );
			$size       = wc_get_formatted_variation( self::option_attributes( $child ), true, false );
			$color      = $child->get_attribute( 'color' );
			if ( null === $metric['value'] || (float) $metric['value'] <= 0 || 'g' !== $metric['unit'] || 'excluded' !== ( $conditions['packaging'] ?? '' ) || 'one pair (operator confirmed)' !== ( $conditions['quantity'] ?? '' ) ) {
				$missing[] = $color . ' / ' . $size;
				continue;
			}
			$key                      = $size . ':' . $metric['value'];
			$rows[ $key ]['size']     = $size;
			$rows[ $key ]['weight']   = $metric['value'];
			$rows[ $key ]['colors'][] = $color;
		}
		if ( ! $rows ) {
			return '';
		}
		$html = '<section class="pc-pair-weight"><h2>' . esc_html__( 'Weight per pair', 'parameter-commerce' ) . '</h2><ul>';
		foreach ( $rows as $row ) {
			$html .= '<li><strong>' . esc_html( wc_format_localized_decimal( wc_format_decimal( $row['weight'], 2, true ) ) ) . ' g</strong> — ' . esc_html( $row['size'] );
			if ( $missing || count( array_filter( $rows, static fn( $r ) => $r['size'] === $row['size'] ) ) > 1 ) {
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
		$top = round( 205 - min( 170, max( 30, 190 * (float) $dimensions['leg_cm'] / (float) $dimensions['foot_cm'] ) ), 2 );
		// X70's photo shows a low opening and heel tab. Its catalog height has no confirmed endpoints.
		$low_cut = in_array( $product->get_sku(), array( 'SS-X70', 'SS-001-X70' ), true ) && 'X70' === $product->get_meta( '_pc_selected_supplier_model' );
		ob_start();
		require dirname( __DIR__ ) . ( $low_cut ? '/templates/size-shape-low-cut.php' : '/templates/size-shape.php' );
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

	/** Fields in Product data > General; native size attributes remain unchanged. @return void */
	public static function settings() {
		global $product_object;
		if ( $product_object && ( $product_object->get_meta( '_pc_structured_specs' ) || self::has_cuff_height( $product_object ) ) ) {
			echo '<p>' . esc_html__( 'Size & Shape uses the approved dimensions in WooCommerce → Parameter Specs.', 'parameter-commerce' ) . '</p>';
			return;
		}
		$sizes = $product_object ? self::size_dimensions( $product_object ) : array();
		if ( $sizes && ! $product_object->meta_exists( '_pc_shape_dimensions' ) ) {
			echo '<div class="options_group"><p><strong>' . esc_html__( 'Size & Shape', 'parameter-commerce' ) . '</strong></p>';
			foreach ( $sizes as $index => $row ) {
				foreach ( array(
					'leg_cm'  => __( 'Leg length (cm)', 'parameter-commerce' ),
					'foot_cm' => __( 'Foot length (cm)', 'parameter-commerce' ),
				) as $key => $label ) {
					woocommerce_wp_text_input(
						array(
							'id'    => '_pc_shape_size_' . $index . '_' . $key,
							'label' => $row['size'] . ' — ' . $label,
							'value' => $row[ $key ],
						)
					);
				}
			}
			echo '</div>';
			return;
		}
		$data = self::dimensions( $product_object );
		echo '<div class="options_group show_if_simple show_if_variable"><p><strong>' . esc_html__( 'Size & Shape', 'parameter-commerce' ) . '</strong></p>';
		foreach ( array(
			'leg_cm'  => __( 'Leg length (cm)', 'parameter-commerce' ),
			'foot_cm' => __( 'Foot length (cm)', 'parameter-commerce' ),
			'source'  => __( 'Dimension source', 'parameter-commerce' ),
		) as $key => $label ) {
			woocommerce_wp_text_input(
				array(
					'id'          => '_pc_shape_' . $key,
					'label'       => $label,
					'value'       => $data[ $key ] ?? '',
					'description' => 'source' === $key ? __( 'Shown to shoppers. Use a public source label, not a private URL. These are model dimensions; manage shoe sizes under Attributes.', 'parameter-commerce' ) : '',
				)
			);
		}
		echo '<p>' . esc_html__( 'Enter both dimensions and their source to show the guide. Clear both dimensions to hide it.', 'parameter-commerce' ) . '</p></div>';
	}

	/**
	 * Save only authorized, nonce-checked native product edits, atomically per field group.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public static function save_settings( $product ) {
		if ( $product->get_meta( '_pc_structured_specs' ) || self::has_cuff_height( $product ) ) {
			return; }
		if ( ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['woocommerce_meta_nonce'] ) || ! is_string( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$sizes = self::size_dimensions( $product );
		if ( $sizes && ! $product->meta_exists( '_pc_shape_dimensions' ) ) {
			foreach ( $sizes as $index => &$row ) {
				foreach ( array( 'leg_cm', 'foot_cm' ) as $key ) {
					$field = '_pc_shape_size_' . $index . '_' . $key;
					if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
						return;
					}
					$value = self::centimeters( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
					if ( ! $value ) {
						\WC_Admin_Meta_Boxes::add_error( __( 'Size & Shape was not changed. Enter valid lengths for every size.', 'parameter-commerce' ) );
						return;
					}
					$row[ $key ] = $value;
				}
			}
			unset( $row );
			$product->update_meta_data( '_pc_shape_sizes', $sizes );
			return;
		}
		$data = array();
		foreach ( array( 'leg_cm', 'foot_cm', 'source' ) as $key ) {
			if ( ! isset( $_POST[ '_pc_shape_' . $key ] ) || ! is_string( $_POST[ '_pc_shape_' . $key ] ) ) {
				return;
			}
			$data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ '_pc_shape_' . $key ] ) );
		}
		if ( '' === $data['leg_cm'] && '' === $data['foot_cm'] ) {
			$product->update_meta_data( '_pc_shape_dimensions', array() );
			return;
		}
		$leg  = self::centimeters( $data['leg_cm'] );
		$foot = self::centimeters( $data['foot_cm'] );
		if ( ! $leg || ! $foot || '' === $data['source'] || strlen( $data['source'] ) > 191 ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Size & Shape was not changed. Enter both lengths between 0 and 100 cm (up to two decimal places) and a short public source label.', 'parameter-commerce' ) );
			return;
		}
		$product->update_meta_data(
			'_pc_shape_dimensions',
			array(
				'leg_cm'  => $leg,
				'foot_cm' => $foot,
				'source'  => $data['source'],
			)
		);
	}
}
