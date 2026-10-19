<?php
/**
 * Native product editor additions for sourcing, design briefs and review.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Failures are escaped by the calling controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Product identity, descriptions, attributes and commerce remain in WooCommerce. */
final class Product_Drafts {
	/** Integrate without replacing the product or claim editor. @return void */
	public static function register_hooks() {
		add_action( 'add_meta_boxes', array( self::class, 'boxes' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'protect_demo' ), 10, 2 );
	}

	/**
	 * Seeded fictional products remain drafts even if Publish is pressed.
	 *
	 * @param array $data Sanitized fields.
	 * @param array $postarr Core post input.
	 * @return array
	 */
	public static function protect_demo( $data, $postarr ) {
		if ( 'product' === $data['post_type'] && ! empty( $postarr['ID'] ) && get_post_meta( $postarr['ID'], '_pc_demo_catalog', true ) && 'trash' !== $data['post_status'] ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	/** Register a native WooCommerce product metabox. @return void */
	public static function boxes() {
		add_meta_box( 'pc-product-review', __( 'SockSpecs sourcing and readiness', 'parameter-commerce' ), array( self::class, 'box' ), 'product', 'normal', 'default' );
	}

	/** Controlled design intentions, not statements of tested performance. @return array */
	public static function design_features() {
		return array(
			'ankle-profile'   => 'ankle profile',
			'crew-profile'    => 'crew profile',
			'cushioned-sole'  => 'cushioned sole',
			'ribbed-cuff'     => 'ribbed cuff',
			'reinforced-heel' => 'reinforced heel',
			'low-profile'     => 'low profile',
		);
	}

	/** Translated editor labels; generated descriptions deliberately remain English. @return array */
	private static function feature_labels() {
		return array(
			'ankle-profile'   => __( 'Ankle profile', 'parameter-commerce' ),
			'crew-profile'    => __( 'Crew profile', 'parameter-commerce' ),
			'cushioned-sole'  => __( 'Cushioned sole', 'parameter-commerce' ),
			'ribbed-cuff'     => __( 'Ribbed cuff', 'parameter-commerce' ),
			'reinforced-heel' => __( 'Reinforced heel', 'parameter-commerce' ),
			'low-profile'     => __( 'Low profile', 'parameter-commerce' ),
		);
	}

	/**
	 * Deterministic English draft copy, with no synthesized technical values.
	 *
	 * @param \WC_Product $product Native structured product.
	 * @return array Short and full draft suggestions.
	 */
	public static function description( $product ) {
		$uses = array();
		foreach ( $product->get_tag_ids() as $term_id ) {
			$term = get_term( $term_id, 'product_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$uses[] = $term->slug; }
		}
		$uses   = is_wp_error( $uses ) ? array() : array_values( array_intersect( array( 'everyday', 'running', 'hiking', 'cold-weather' ), $uses ) );
		$design = array();
		foreach ( explode( ',', (string) $product->get_meta( '_pc_design_features' ) ) as $feature ) {
			if ( isset( self::design_features()[ $feature ] ) ) {
				$design[] = self::design_features()[ $feature ];
			}
		}
		$short = 'A sock design brief' . ( $uses ? ' intended for ' . implode( ' and ', $uses ) . ' use' : '' ) . '. Materials, fit and performance require review.';
		$full  = $short . "\n\n";
		if ( $design ) {
			$full .= 'Proposed design features: ' . implode( ', ', $design ) . ". These are design intentions, not independently verified product specifications.\n\n";
		}
		$full .= 'Consult the specification table for approved records and their sources. Unavailable measurements must remain marked as not yet measured. This description does not establish durability, comfort, breathability or performance.';
		if ( $product->get_meta( '_pc_demo_catalog' ) ) {
			$short = 'FICTIONAL DEMO — not commercial inventory. ' . $short;
			$full  = "FICTIONAL DEMO — not commercial inventory. No supplier, composition or test results have been confirmed.\n\n" . $full;
		}
		return array(
			'short' => $short,
			'full'  => $full,
		);
	}

	/**
	 * Readiness is editorial guidance, never a quality grade or certification.
	 *
	 * @param int $id Native parent product.
	 * @return array
	 */
	public static function readiness( $id ) {
		Repository::authorize();
		$product   = wc_get_product( $id );
		$spec      = Repository::specifications( $id );
		$missing   = array();
		$published = 0;
		foreach ( $spec['groups'] as $group ) {
			foreach ( $group['metrics'] as $metric ) {
				if ( null === $metric['value'] ) {
					$missing[] = $metric['label'];
				} else {
					++$published;
				}
			}
		}
		return array(
			'demo'        => (bool) $product->get_meta( '_pc_demo_catalog' ),
			'sku'         => (bool) $product->get_sku(),
			'description' => (bool) $product->get_description(),
			'image'       => (bool) $product->get_image_id(),
			'supplier'    => 'pc_supplier' === get_post_type( (int) $product->get_meta( '_pc_supplier_id' ) ) && 'private' === get_post_status( (int) $product->get_meta( '_pc_supplier_id' ) ),
			'published'   => $published,
			'missing'     => $missing,
			'variable'    => $product->is_type( 'variable' ),
		);
	}

	/**
	 * Staff-only controls inside the existing product editor.
	 *
	 * @param \WP_Post $post Product post.
	 * @return void
	 */
	public static function box( $post ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! Plugin::has_woocommerce() ) {
			return;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		wp_nonce_field( 'pc_product_review', 'pc_product_nonce' );
		echo '<p>' . esc_html__( 'Use WooCommerce for categories, product tags (intended use), Size, Color and Pack quantity attributes, images and variations. Sourcing fields below are private.', 'parameter-commerce' ) . '</p>';
		echo '<p><label>' . esc_html__( 'Supplier (private)', 'parameter-commerce' ) . ' <select name="pc_supplier_id"><option value="0">' . esc_html__( 'Not assigned', 'parameter-commerce' ) . '</option>';
		$supplier_choices  = get_posts(
			array(
				'post_type'   => 'pc_supplier',
				'post_status' => 'private',
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$selected_supplier = get_post( (int) $product->get_meta( '_pc_supplier_id' ) );
		if ( $selected_supplier && 'pc_supplier' === $selected_supplier->post_type && 'private' === $selected_supplier->post_status && ! in_array( $selected_supplier->ID, wp_list_pluck( $supplier_choices, 'ID' ), true ) ) {
			$supplier_choices[] = $selected_supplier; }
		foreach ( $supplier_choices as $supplier ) {
			echo '<option value="' . esc_attr( $supplier->ID ) . '" ' . selected( $product->get_meta( '_pc_supplier_id' ), $supplier->ID, false ) . '>' . esc_html( ( get_post_meta( $supplier->ID, '_pc_supplier_code', true ) ? get_post_meta( $supplier->ID, '_pc_supplier_code', true ) . ' — ' : '' ) . $supplier->post_title ) . '</option>';
		}
		echo '</select></label> <a href="' . esc_url( admin_url( 'post-new.php?post_type=pc_supplier' ) ) . '">' . esc_html__( 'Add private supplier', 'parameter-commerce' ) . '</a></p>';
		echo '<p><label>' . esc_html__( 'Supplier product URL (private, HTTPS)', 'parameter-commerce' ) . '<br><input class="large-text" type="url" name="pc_supplier_product_url" value="' . esc_attr( $product->get_meta( '_pc_supplier_product_url' ) ) . '"></label></p>';
		echo '<fieldset><legend>' . esc_html__( 'Proposed design features (not verified specifications)', 'parameter-commerce' ) . '</legend>';
		foreach ( self::feature_labels() as $key => $label ) {
			echo '<label style="display:inline-block;margin:0 1rem .5rem 0"><input type="checkbox" name="pc_design_features[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, explode( ',', $product->get_meta( '_pc_design_features' ) ), true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset><p><button class="button" name="pc_generate_description" value="1">' . esc_html__( 'Save draft and generate description suggestion', 'parameter-commerce' ) . '</button></p>';
		echo '<p>' . esc_html__( 'Draft products only. Review the suggestion and copy accepted wording into the native description fields; generation never publishes or replaces your description.', 'parameter-commerce' ) . '</p>';
		$suggestion = $product->get_meta( '_pc_description_suggestion' );
		if ( is_array( $suggestion ) ) {
			foreach ( $suggestion as $value ) {
				echo '<textarea class="large-text" rows="5" readonly aria-label="' . esc_attr__( 'Draft description suggestion', 'parameter-commerce' ) . '">' . esc_textarea( $value ) . '</textarea>';
			}
		}
		try {
			$review = self::readiness( $post->ID );
			echo '<h3>' . esc_html__( 'Publication review', 'parameter-commerce' ) . '</h3>';
			if ( $review['demo'] ) {
				echo '<p><strong>' . esc_html__( 'Fictional demonstration product: publication is blocked.', 'parameter-commerce' ) . '</strong></p>';
			}
			foreach ( array(
				'sku'         => __( 'SKU', 'parameter-commerce' ),
				'description' => __( 'Description', 'parameter-commerce' ),
				'image'       => __( 'Product image', 'parameter-commerce' ),
				'supplier'    => __( 'Supplier reference', 'parameter-commerce' ),
			) as $key => $label ) {
				echo '<p>' . esc_html( $label ) . ': ' . esc_html( $review[ $key ] ? __( 'Present — review required', 'parameter-commerce' ) : __( 'Missing', 'parameter-commerce' ) ) . '</p>';
			}
			echo '<p>' . esc_html__( 'Metrics without published evidence (not all are applicable):', 'parameter-commerce' ) . ' ' . esc_html( implode( ', ', $review['missing'] ) ) . '</p>';
			if ( $review['variable'] ) {
				echo '<p>' . esc_html__( 'This summary covers parent records only. Review each size/SKU in Parameter Specs; no child inherits parent measurements.', 'parameter-commerce' ) . '</p>';
			}
		} catch ( Failure $error ) {
			echo '<p>' . esc_html( $error->getMessage() ) . '</p>';
		}
		echo '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page'       => 'pc-specs',
					'product_id' => $post->ID,
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Review declarations, measurements and evidence', 'parameter-commerce' ) . '</a></p>';
	}

	/**
	 * Validate all product extension inputs before changing the product object.
	 *
	 * @param \WC_Product $product Native object, saved by WooCommerce afterward.
	 * @param array       $input Unslashed inputs.
	 * @return void
	 * @throws Failure Invalid references or forbidden access.
	 */
	public static function apply( $product, array $input ) {
		Repository::authorize();
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			throw new Failure( array( 'product' => __( 'You cannot edit this product.', 'parameter-commerce' ) ), 403 );
		}
		$id = $input['pc_supplier_id'] ?? '0';
		if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || ( $id && ( 'pc_supplier' !== get_post_type( (int) $id ) || 'private' !== get_post_status( (int) $id ) ) ) ) {
			throw new Failure( array( 'supplier' => __( 'Choose an existing private supplier.', 'parameter-commerce' ) ) );
		}
		$url      = Suppliers::url( $input['pc_supplier_product_url'] ?? '' );
		$features = $input['pc_design_features'] ?? array();
		if ( ! is_array( $features ) || count( array_filter( $features, 'is_string' ) ) !== count( $features ) || array_diff( $features, array_keys( self::design_features() ) ) ) {
			throw new Failure( array( 'features' => __( 'Choose supported design features.', 'parameter-commerce' ) ) );
		}
		if ( isset( $input['pc_generate_description'] ) && 'draft' !== $product->get_status() ) {
			throw new Failure( array( 'description' => __( 'Description generation is available only for saved draft products.', 'parameter-commerce' ) ) );
		}
		$product->update_meta_data( '_pc_supplier_id', (int) $id );
		$product->update_meta_data( '_pc_supplier_product_url', $url );
		$product->update_meta_data( '_pc_design_features', implode( ',', $features ) );
		if ( isset( $input['pc_generate_description'] ) ) {
			$product->update_meta_data( '_pc_description_suggestion', self::description( $product ) );
		}
	}

	/**
	 * Native save hook; own nonce supplements WooCommerce's capability checks.
	 *
	 * @throws \WC_Data_Exception Validation errors handled by WooCommerce.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public static function save( $product ) {
		if ( ! isset( $_POST['pc_product_nonce'] ) || ! is_string( $_POST['pc_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_product_nonce'] ) ), 'pc_product_review' ) ) {
			return;
		}
		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- apply() validates all input before mutation.
			self::apply( $product, wp_unslash( $_POST ) );
		} catch ( Failure $error ) {
			throw new \WC_Data_Exception( 'pc_product_review', $error->getMessage() );
		}
	}
}
