<?php
/**
 * Optional, area-specific yarn records in native product metadata.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Optional product descriptions; never infer test results or composition. */
final class Yarn_Specifications {
	const META = '_pc_yarn_specifications';

	/** Native product editor and public tabs. */
	public static function register_hooks() {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'editor_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'editor' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
		add_filter( 'woocommerce_product_tabs', array( self::class, 'product_tab' ) );
	}

	/** Supported anatomical scopes. @return array */
	public static function areas() {
		return array(
			'whole'    => __( 'Whole sock', 'parameter-commerce' ),
			'body'     => __( 'Body', 'parameter-commerce' ),
			'sole'     => __( 'Sole', 'parameter-commerce' ),
			'heel_toe' => __( 'Heel and toe', 'parameter-commerce' ),
			'cuff'     => __( 'Cuff', 'parameter-commerce' ),
		);
	}

	/** No speculative defaults, including source classification. @return array */
	public static function fields() {
		return array(
			'count'       => __( 'Yarn count', 'parameter-commerce' ),
			'system'      => __( 'Count system', 'parameter-commerce' ),
			'ply'         => __( 'Yarn ply', 'parameter-commerce' ),
			'micron'      => __( 'Mean wool fibre diameter (μm)', 'parameter-commerce' ),
			'wool_type'   => __( 'Wool type', 'parameter-commerce' ),
			'applies_to'  => __( 'Applicable sizes / colors', 'parameter-commerce' ),
			'source_type' => __( 'Source type', 'parameter-commerce' ),
			'source'      => __( 'Public source reference', 'parameter-commerce' ),
			'date'        => __( 'Source / measurement date', 'parameter-commerce' ),
			'method'      => __( 'Method / version', 'parameter-commerce' ),
			'sample'      => __( 'Sample description / quantity', 'parameter-commerce' ),
			'batch'       => __( 'Batch reference', 'parameter-commerce' ),
		);
	}

	/** Source labels do not assert independent certification. @return array */
	private static function sources() {
		return array(
			'supplier_declared' => __( 'Manufacturer-declared', 'parameter-commerce' ),
			'platform_measured' => __( 'Platform-measured', 'parameter-commerce' ),
			'third_party_lab'   => __( 'Laboratory report', 'parameter-commerce' ),
		);
	}

	/**
	 * Strict string inputs; blank is unknown, never zero. Invalid edits are atomic.
	 *
	 * @param array $input Submitted fields for one area.
	 * @return array
	 * @throws \InvalidArgumentException Invalid input.
	 */
	public static function normalize( $input ) {
		$row = array();
		foreach ( self::fields() as $key => $label ) {
			$value = $input[ $key ] ?? '';
			if ( ! is_string( $value ) || strlen( $value ) > 400 ) {
				throw new \InvalidArgumentException( esc_html__( 'Use short text values for yarn specifications.', 'parameter-commerce' ) );
			}
			$row[ $key ] = sanitize_text_field( $value );
		}
		foreach ( array( 'count', 'micron' ) as $key ) {
			if ( '' !== $row[ $key ] && ( ! preg_match( '/^\d{1,6}(?:\.\d{1,3})?$/D', $row[ $key ] ) || (float) $row[ $key ] <= 0 ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Yarn count and fibre diameter must be positive numbers, or blank if unknown. Use at most three decimal places.', 'parameter-commerce' ) );
			}
		}
		if ( '' !== $row['ply'] && ! preg_match( '/^[1-9]\d{0,2}$/D', $row['ply'] ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Yarn ply must be a positive whole number, or blank.', 'parameter-commerce' ) );
		}
		if ( ! in_array( $row['system'], array( '', 'Ne', 'Nm', 'tex', 'dtex', 'denier' ), true ) || ( '' !== $row['source_type'] && ! isset( self::sources()[ $row['source_type'] ] ) ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Choose a listed count system and source type, or leave them blank.', 'parameter-commerce' ) );
		}
		if ( '' !== $row['date'] ) {
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $row['date'] );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $row['date'] ) {
				throw new \InvalidArgumentException( esc_html__( 'Use a valid date in YYYY-MM-DD format, or leave it blank.', 'parameter-commerce' ) );
			}
		}
		return $row;
	}

	/**
	 * Require meaningful scope/provenance before displaying any recorded value.
	 *
	 * @param array $row Normalized fields.
	 * @return bool
	 */
	private static function ready( $row ) {
		if ( ! $row['source_type'] || ! $row['source'] || ! $row['date'] || ! $row['applies_to'] || ! self::values( $row ) ) {
			return false;
		}
		if ( $row['count'] && ! $row['system'] ) {
			return false;
		}
		return 'supplier_declared' === $row['source_type'] || ( $row['method'] && $row['sample'] && $row['batch'] );
	}

	/**
	 * Only present fields become table rows; a system alone is not a yarn count.
	 *
	 * @param array $row Normalized record.
	 * @return array
	 */
	public static function values( $row ) {
		$values = array();
		if ( $row['count'] && $row['system'] ) {
			$values[ __( 'Yarn count', 'parameter-commerce' ) ] = $row['count'] . ' ' . $row['system'];
		}
		foreach ( array( 'ply', 'micron', 'wool_type' ) as $key ) {
			if ( '' !== $row[ $key ] ) {
				$values[ self::fields()[ $key ] ] = $row[ $key ];
			}
		}
		return $values;
	}

	/**
	 * Reviewed rows only; no staff-only notes or reviewer identifiers in projection.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function records( $product ) {
		if ( ! $product || $product->get_meta( '_pc_structured_specs' ) || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return array();
		}
		$saved = $product->get_meta( self::META );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		$result = array();
		foreach ( self::areas() as $area => $label ) {
			$row = $saved[ $area ] ?? null;
			if ( ! is_array( $row ) || (int) ( $row['reviewed_by'] ?? 0 ) < 1 || empty( $row['reviewed_at'] ) ) {
				continue;
			}
			try {
				$clean = self::normalize( $row );
				if ( self::ready( $clean ) && hash_equals( hash( 'sha256', wp_json_encode( $clean ) ), (string) ( $row['reviewed_hash'] ?? '' ) ) ) {
					$result[ $area ] = $clean;
				}
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
		}
		return $result;
	}

	/**
	 * Native editor tab.
	 *
	 * @param array $tabs Native tabs.
	 * @return array
	 */
	public static function editor_tab( $tabs ) {
		$tabs['pc_yarn'] = array(
			'label'    => __( 'Yarn & wool', 'parameter-commerce' ),
			'target'   => 'pc-yarn-data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/** Optional fields, collapsed by anatomical scope; native WC form/nonce. */
	public static function editor() {
		global $product_object;
		if ( ! $product_object || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $product_object->get_id() ) ) {
			return;
		}
		if ( $product_object->get_meta( '_pc_structured_specs' ) ) {
			echo '<div id="pc-yarn-data" class="panel woocommerce_options_panel hidden"><p>' . esc_html__( 'Edit cotton, wool and yarn specifications in the shared parameter system. Unknown values stay blank. Published records appear in Specifications & evidence and Compare.', 'parameter-commerce' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=pc-specs' ) ) . '">' . esc_html__( 'Open Parameter Specs', 'parameter-commerce' ) . '</a></p></div>';
			return;
		}
		$saved = $product_object->get_meta( self::META );
		echo '<div id="pc-yarn-data" class="panel woocommerce_options_panel hidden"><p>' . esc_html__( 'Optional specifications. Leave unknown values blank. Enter the yarn count as supplied for one strand, with its count system; record ply separately. Do not infer Merino type, fibre diameter or wool content.', 'parameter-commerce' ) . '</p>';
		echo '<p>' . esc_html__( 'Only reviewed records appear to shoppers. Source and scope fields are public: use a report or catalog reference, never supplier contacts, purchase costs or private URLs. Multiple yarns in one area should remain unpublished until recorded separately.', 'parameter-commerce' ) . '</p>';
		foreach ( self::areas() as $area => $label ) {
			$row = is_array( $saved ) && is_array( $saved[ $area ] ?? null ) ? $saved[ $area ] : array();
			echo '<details class="options_group"' . ( $row ? ' open' : '' ) . '><summary style="padding:12px;cursor:pointer"><strong>' . esc_html( $label ) . '</strong></summary>';
			foreach ( self::fields() as $key => $field_label ) {
				$args = array(
					'id'    => 'pc_yarn_' . $area . '_' . $key,
					'name'  => 'pc_yarn[' . $area . '][' . $key . ']',
					'label' => $field_label,
					'value' => $row[ $key ] ?? '',
				);
				if ( in_array( $key, array( 'system', 'source_type' ), true ) ) {
					$options         = 'system' === $key ? array_combine( array( 'Ne', 'Nm', 'tex', 'dtex', 'denier' ), array( 'Ne', 'Nm', 'tex', 'dtex', 'denier' ) ) : self::sources();
					$args['options'] = array( '' => __( 'Not supplied', 'parameter-commerce' ) ) + $options;
					woocommerce_wp_select( $args );
				} else {
					$args['type'] = 'date' === $key ? 'date' : 'text';
					woocommerce_wp_text_input( $args );
				}
			}
			woocommerce_wp_checkbox(
				array(
					'id'          => 'pc_yarn_' . $area . '_reviewed',
					'name'        => 'pc_yarn[' . $area . '][reviewed]',
					'label'       => __( 'Reviewed for display', 'parameter-commerce' ),
					'value'       => ! empty( $row['reviewed_by'] ) ? 'yes' : 'no',
					'description' => __( 'I have checked these values and their scope. Requires source type, public reference, date and applicable sizes/colors. Measured/report records also require method, sample and batch. Uncheck to keep this record private.', 'parameter-commerce' ),
				)
			);
			echo '</details>';
		}
		echo '</div>';
	}

	/**
	 * Validate every submitted area before updating any; blank groups remove data.
	 *
	 * @param \WC_Product $product Native product being saved.
	 * @throws \InvalidArgumentException Caught and shown as a native admin error.
	 */
	public static function save( $product ) {
		if ( $product->get_meta( '_pc_structured_specs' ) || ! $product->is_type( array( 'simple', 'variable' ) ) || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['woocommerce_meta_nonce'], $_POST['pc_yarn'] ) || ! is_string( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every area/key validated and sanitized by normalize().
		$input = wp_unslash( $_POST['pc_yarn'] );
		$data  = array();
		try {
			if ( ! is_array( $input ) || array_diff( array_keys( $input ), array_keys( self::areas() ) ) || array_diff( array_keys( self::areas() ), array_keys( $input ) ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Yarn specifications were not changed. Reload the complete product form.', 'parameter-commerce' ) );
			}
			foreach ( self::areas() as $area => $label ) {
				if ( ! is_array( $input[ $area ] ) || array_diff( array_keys( self::fields() ), array_keys( $input[ $area ] ) ) ) {
					throw new \InvalidArgumentException( esc_html__( 'Yarn specifications were not changed. Reload the complete product form.', 'parameter-commerce' ) );
				}
				$row = self::normalize( $input[ $area ] );
				if ( ! array_filter( $row, static fn( $value ) => '' !== $value ) ) {
					continue;
				}
				if ( 'yes' === ( $input[ $area ]['reviewed'] ?? '' ) ) {
					if ( ! current_user_can( 'publish_pc_claims' ) || ! self::ready( $row ) ) {
						throw new \InvalidArgumentException( esc_html__( 'Yarn specifications were not changed. Complete the source and scope fields before reviewing, or uncheck Reviewed for display to save an incomplete record privately.', 'parameter-commerce' ) );
					}
					$row['reviewed_hash'] = hash( 'sha256', wp_json_encode( $row ) );
					$row['reviewed_by']   = get_current_user_id();
					$row['reviewed_at']   = gmdate( 'Y-m-d H:i:s' );
				}
				$data[ $area ] = $row;
			}
			if ( $data ) {
				$product->update_meta_data( self::META, $data );
			} else {
				$product->delete_meta_data( self::META );
			}
		} catch ( \InvalidArgumentException $e ) {
			\WC_Admin_Meta_Boxes::add_error( $e->getMessage() );
		}
	}

	/**
	 * Public scope or authorized preview, without exposing unreviewed records.
	 *
	 * @param \WC_Product $product Parent.
	 * @return bool
	 */
	private static function visible( $product ) {
		return $product && ! get_post_field( 'post_password', $product->get_id() ) && ( Public_Specifications::visible( $product->get_id() ) || ( current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ) );
	}

	/**
	 * Hide the entire tab when no reviewed values exist.
	 *
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public static function product_tab( $tabs ) {
		global $product;
		if ( self::visible( $product ) && self::records( $product ) && ! Catalog_Specifications::html( $product ) ) {
			$tabs['pc_yarn'] = array(
				'title'    => __( 'Yarn & wool', 'parameter-commerce' ),
				'priority' => 16,
				'callback' => array( self::class, 'panel' ),
			);
		}
		return $tabs;
	}

	/** Render the reviewed optional fields. */
	public static function panel() {
		global $product;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes all values.
		echo self::render( $product );
	}

	/**
	 * Render no placeholder rows, no empty headings and no presumed values.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return string
	 */
	public static function render( $product ) {
		if ( ! self::visible( $product ) ) {
			return '';
		}
		$records = self::records( $product );
		if ( ! $records ) {
			return '';
		}
		ob_start();
		require dirname( __DIR__ ) . '/templates/yarn-specifications.php';
		return ob_get_clean();
	}

	/**
	 * Same public values and provenance for discovery, without inferred inheritance.
	 * Caller must enforce its public product visibility boundary.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function facts( $product ) {
		$facts = array();
		foreach ( self::records( $product ) as $area => $row ) {
			$context = self::areas()[ $area ] . '; ' . $row['applies_to'];
			$source  = self::sources()[ $row['source_type'] ] . '; ' . $row['source'] . '; ' . $row['date'];
			foreach ( array( 'method', 'sample', 'batch' ) as $key ) {
				if ( $row[ $key ] ) {
					$source .= '; ' . self::fields()[ $key ] . ': ' . $row[ $key ];
				}
			}
			foreach ( self::values( $row ) as $name => $value ) {
				$facts[] = array(
					'name'  => $name . ' — ' . $context,
					'value' => $value . ' (' . $source . ')',
				);
			}
		}
		return $facts;
	}
}
