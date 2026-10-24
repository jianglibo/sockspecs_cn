<?php
/** Permanent public model numbers and private manufacturer references.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

defined( 'ABSPATH' ) || exit;

/** Numbers are reserved in options permanently, even after product deletion. */
final class Product_Identity {
	/** Register native WooCommerce editor hooks. */
	public static function register_hooks() {
		add_filter( 'woocommerce_get_cart_item_from_session', array( self::class, 'cart_style' ) );
		add_action( 'add_meta_boxes', array( self::class, 'boxes' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( self::class, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( self::class, 'save_variation' ), 10, 2 );
		add_action( 'woocommerce_before_product_object_save', array( self::class, 'protect' ) );
		add_filter( 'woocommerce_duplicate_product_exclude_meta', array( self::class, 'duplicate_exclusions' ) );
		add_filter( 'manage_product_posts_columns', array( self::class, 'columns' ), 30 );
		add_action( 'manage_product_posts_custom_column', array( self::class, 'column' ), 30, 2 );
		add_filter( 'request', array( self::class, 'product_search' ), 30 );
	}
	/**
	 * Replace the brand column with sourcing information in the staff list.
	 *
	 * @param array $columns Native product columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $columns;
		}
		$result = array();
		foreach ( $columns as $key => $label ) {
			if ( in_array( $key, array( 'taxonomy-product_brand', 'product_brand' ), true ) ) {
				$result['pc_supplier'] = __( 'Supplier', 'parameter-commerce' );
				continue;
			}
			$result[ $key ] = $label;
			if ( 'sku' === $key ) {
				$result['pc_original_model'] = __( 'Manufacturer model', 'parameter-commerce' );
			}
		}
		$result['pc_original_model'] = __( 'Manufacturer model', 'parameter-commerce' );
		$result['pc_supplier']       = __( 'Supplier', 'parameter-commerce' );
		return $result;
	}

	/**
	 * Render private source references without modifying the public brand taxonomy.
	 *
	 * @param string $column Column key.
	 * @param int    $id Parent product ID.
	 */
	public static function column( $column, $id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		if ( 'pc_original_model' === $column ) {
			$value = get_post_meta( $id, '_pc_selected_supplier_model', true );
			echo esc_html( $value ? $value : '—' );
		} elseif ( 'pc_supplier' === $column ) {
			$supplier = (int) get_post_meta( $id, '_pc_supplier_id', true );
			if ( ! $supplier || 'pc_supplier' !== get_post_type( $supplier ) || ! current_user_can( 'edit_post', $supplier ) ) {
				echo '—';
				return;
			}
			$code  = get_post_meta( $supplier, '_pc_supplier_code', true );
			$title = get_the_title( $supplier );
			echo '<a href="' . esc_url( get_edit_post_link( $supplier, 'raw' ) ) . '">' . esc_html( ( $code ? $code . ' · ' : '' ) . $title ) . '</a>';
		}
	}

	/**
	 * Prepared parent-ID subquery matching private manufacturer references.
	 * Includes child references so a combined-style product can be found by either model.
	 *
	 * @param string $term Literal partial model reference.
	 * @return string
	 */
	public static function manufacturer_query( $term ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! is_string( $term ) || '' === trim( $term ) || strlen( $term ) > 100 ) {
			return 'SELECT 0';
		}
		global $wpdb;
		return $wpdb->prepare(
			"SELECT DISTINCT CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END FROM %i p INNER JOIN %i m ON m.post_id = p.ID WHERE p.post_type IN ('product', 'product_variation') AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future') AND m.meta_key = %s AND m.meta_value LIKE %s",
			$wpdb->posts,
			$wpdb->postmeta,
			'_pc_selected_supplier_model',
			'%' . $wpdb->esc_like( trim( $term ) ) . '%'
		);
	}

	/**
	 * Get matching parent IDs for staff search only.
	 *
	 * @param string $term Manufacturer model.
	 * @param int    $limit Zero for native paginated list; bounded for picker.
	 * @return array
	 */
	public static function manufacturer_ids( $term, $limit = 0 ) {
		global $wpdb;
		$sql = self::manufacturer_query( $term );
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared subquery above; staff-only current catalog lookup.
		return array_map( 'absint', $wpdb->get_col( $sql ) );
	}

	/**
	 * Extend WooCommerce's native admin search after it resolves names/SKUs/IDs.
	 *
	 * @param array $query_vars Native request arguments.
	 * @return array
	 */
	public static function product_search( $query_vars ) {
		global $pagenow, $typenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || 'product' !== $typenow || ! current_user_can( 'manage_woocommerce' ) || empty( $query_vars['product_search'] ) || ! isset( $query_vars['post__in'] ) ) {
			return $query_vars;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only native product-list search.
		$term                   = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ( $query_vars['product_search_term'] ?? '' );
		$query_vars['post__in'] = array_values( array_unique( array_merge( $query_vars['post__in'], self::manufacturer_ids( $term ) ) ) );
		return $query_vars;
	}

	/** Private editor section. */
	public static function boxes() {
		add_meta_box( 'pc-product-identity', __( 'Product model and sourcing', 'parameter-commerce' ), array( self::class, 'box' ), 'product', 'normal', 'high' );
	}
	/**
	 * Render the current public number and private original model.
	 *
	 * @param \WP_Post $post Product post.
	 */
	public static function box( $post ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		wp_nonce_field( 'pc_product_identity', 'pc_identity_nonce' );
		$code = $product->get_meta( '_pc_public_model' );
		echo '<p><label>' . esc_html__( 'SockSpecs model', 'parameter-commerce' ) . '<br><input type="text" readonly value="' . esc_attr( $code ) . '"></label></p>';
		if ( ! $code ) {
			echo '<p><label>' . esc_html__( 'Product family', 'parameter-commerce' ) . ' <select name="pc_model_family"><option value="S">' . esc_html__( 'Socks', 'parameter-commerce' ) . '</option><option value="T">' . esc_html__( 'Towels', 'parameter-commerce' ) . '</option></select></label></p>';
			echo '<p><label><input type="checkbox" name="pc_assign_model" value="1"> ' . esc_html__( 'Assign a permanent model number when saving', 'parameter-commerce' ) . '</label></p>';
		}
		echo '<p>' . esc_html__( 'Assigned numbers cannot be changed or reused, including after deletion. Candidates can stay unnumbered.', 'parameter-commerce' ) . '</p>';
		echo '<p><label for="pc-original-model">' . esc_html__( 'Manufacturer model (private)', 'parameter-commerce' ) . '</label><br><input id="pc-original-model" class="regular-text" type="text" name="pc_original_model" maxlength="100" value="' . esc_attr( $product->get_meta( '_pc_selected_supplier_model' ) ) . '"></p>';
		echo '<p>' . esc_html__( 'For products containing different manufacturer models, enter the exact model on each variation.', 'parameter-commerce' ) . '</p>';
	}
	/**
	 * Validate a private reference without silently truncating it.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 * @throws \WC_Data_Exception Invalid input.
	 */
	public static function original( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 100 ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'Manufacturer model must be text of at most 100 bytes.', 'parameter-commerce' ) );
		}
		return sanitize_text_field( $value );
	}
	/**
	 * Reserve a number under a family-wide lock. Explicit codes are for migrations.
	 *
	 * @param \WC_Product $product Saved parent product.
	 * @param string      $family S or T.
	 * @param string      $requested Optional exact migration code.
	 * @return string
	 * @throws \WC_Data_Exception Invalid, unavailable or exhausted number.
	 */
	public static function assign( $product, $family, $requested = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $product->get_id() ) || ! $product->get_id() || $product->is_type( 'variation' ) || $product->get_meta( '_pc_demo_catalog' ) || ! in_array( $family, array( 'S', 'T' ), true ) ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'Only authorized staff can number a saved, non-demo parent product.', 'parameter-commerce' ) );
		}
		if ( $requested && ! preg_match( '/^SS-' . $family . '(?!0000)[0-9]{4}$/D', $requested ) ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'Use SS-S0001 to SS-S9999 for socks, or SS-T0001 to SS-T9999 for towels.', 'parameter-commerce' ) );
		}
		global $wpdb;
		$lock = 'pc_model_' . get_current_blog_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Serialize number allocation on this database connection.
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'Model numbering is busy. Please try again.', 'parameter-commerce' ) );
		}
		try {
			$current = get_post_meta( $product->get_id(), '_pc_public_model', true );
			$current = $current ? $current : get_option( 'pc_model_product_' . $product->get_id(), '' );
			if ( $current ) {
				if ( ( $requested && $requested !== $current ) || substr( $current, 3, 1 ) !== $family || (int) get_option( 'pc_model_reserved_' . $current ) !== $product->get_id() ) {
					throw new \WC_Data_Exception( 'pc_model', esc_html__( 'This model number is already assigned and cannot be changed.', 'parameter-commerce' ) );
				}
				$product->update_meta_data( '_pc_public_model', $current );
				$product->set_sku( $current );
				return $current;
			}
			$last = (int) get_option( 'pc_model_sequence_' . $family, 0 );
			$next = $requested ? (int) substr( $requested, 4 ) : $last + 1;
			for ( ; $next <= 9999; ++$next ) {
				$code  = 'SS-' . $family . sprintf( '%04d', $next );
				$owner = (int) get_option( 'pc_model_reserved_' . $code, 0 );
				$sku   = wc_get_product_id_by_sku( $code );
				if ( ( $owner && $owner !== $product->get_id() ) || ( $sku && $sku !== $product->get_id() ) ) {
					if ( $requested ) {
						throw new \WC_Data_Exception( 'pc_model', esc_html__( 'This model number is already reserved.', 'parameter-commerce' ) );
					}
					continue;
				}
				if ( ! $owner && ! add_option( 'pc_model_reserved_' . $code, $product->get_id(), '', false ) ) {
					throw new \WC_Data_Exception( 'pc_model', esc_html__( 'Model numbering is busy. Please try again.', 'parameter-commerce' ) );
				}
				add_option( 'pc_model_product_' . $product->get_id(), $code, '', false );
				update_option( 'pc_model_sequence_' . $family, max( $last, $next ), false );
				$product->update_meta_data( '_pc_public_model', $code );
				$product->set_sku( $code );
				return $code;
			}
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'All four-digit numbers in this product family are reserved.', 'parameter-commerce' ) );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release connection-owned advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
	/**
	 * Protect existing public identities through all native product saves.
	 *
	 * @param \WC_Product $product Native product.
	 * @throws \WC_Data_Exception Attempted reassignment.
	 */
	public static function protect( $product ) {
		$current  = get_post_meta( $product->get_id(), '_pc_public_model', true );
		$proposed = $product->get_meta( '_pc_public_model' );
		if ( preg_match( '/^SS-[ST][0-9]{4}$/D', $product->get_sku() ) && ! $proposed ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'This model number is not reserved for this product.', 'parameter-commerce' ) );
		}
		if ( $proposed && ( ! preg_match( '/^SS-[ST](?!0000)[0-9]{4}$/D', $proposed ) || (int) get_option( 'pc_model_reserved_' . $proposed ) !== $product->get_id() || $product->get_sku() !== $proposed ) ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'This model number is not reserved for this product.', 'parameter-commerce' ) );
		}
		if ( $current && ( $product->get_meta( '_pc_public_model' ) !== $current || $product->get_sku() !== $current ) ) {
			throw new \WC_Data_Exception( 'pc_model', esc_html__( 'This model number is already assigned and cannot be changed.', 'parameter-commerce' ) );
		}
	}
	/**
	 * Native editor save, with a separate nonce and capability checks.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['pc_identity_nonce'] ) || ! is_string( $_POST['pc_identity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_identity_nonce'] ) ), 'pc_product_identity' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- original() validates and sanitizes before mutation.
		$original = self::original( isset( $_POST['pc_original_model'] ) ? wp_unslash( $_POST['pc_original_model'] ) : '' );
		if ( isset( $_POST['pc_assign_model'] ) ) {
			$family = isset( $_POST['pc_model_family'] ) && is_string( $_POST['pc_model_family'] ) ? sanitize_text_field( wp_unslash( $_POST['pc_model_family'] ) ) : '';
			self::assign( $product, $family );
		}
		$product->update_meta_data( '_pc_selected_supplier_model', $original );
	}
	/**
	 * Private original model on each variation.
	 *
	 * @param int      $loop Row index.
	 * @param array    $data Variation data.
	 * @param \WP_Post $variation Native post.
	 */
	public static function variation_field( $loop, $data, $variation ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_nonce_field( 'pc_variation_identity', 'pc_variation_identity_nonce[' . $loop . ']' );
		woocommerce_wp_text_input(
			array(
				'id'            => 'pc_original_variation_' . $loop,
				'name'          => 'pc_original_variation[' . $loop . ']',
				'label'         => __( 'Manufacturer model (private)', 'parameter-commerce' ),
				'value'         => get_post_meta( $variation->ID, '_pc_selected_supplier_model', true ),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}
	/**
	 * Save private variation reference; never change its commerce attributes.
	 *
	 * @param int $id Variation ID.
	 * @param int $loop Row index.
	 */
	public static function save_variation( $id, $loop ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $id ) || ! isset( $_POST['pc_variation_identity_nonce'][ $loop ] ) || ! is_string( $_POST['pc_variation_identity_nonce'][ $loop ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_variation_identity_nonce'][ $loop ] ) ), 'pc_variation_identity' ) || ! isset( $_POST['pc_original_variation'][ $loop ] ) ) {
			return;
		}
		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- original() validates the scalar and sanitizes text.
			$value   = self::original( wp_unslash( $_POST['pc_original_variation'][ $loop ] ) );
			$product = wc_get_product( $id );
			$product->update_meta_data( '_pc_selected_supplier_model', $value );
			$product->save();
		} catch ( \WC_Data_Exception $error ) {
			\WC_Admin_Meta_Boxes::add_error( $error->getMessage() );
		}
	}
	/**
	 * Copied products must receive new identities; source references may be copied.
	 *
	 * @param array $keys Excluded metadata.
	 * @return array
	 */
	public static function duplicate_exclusions( $keys ) {
		return array_merge( $keys, array( '_pc_public_model', '_pc_legacy_sku' ) );
	}
	/**
	 * Preserve existing bookmarked pack-group IDs when a public style is renamed.
	 *
	 * @param array       $attributes Non-color attributes.
	 * @param \WC_Product $variation Variation.
	 * @return string
	 */
	public static function pack_group( $attributes, $variation ) {
		$legacy = $variation->get_meta( '_pc_legacy_model_attribute' );
		$style  = $variation->get_meta( '_pc_public_style' );
		if ( $legacy && $style && ( $attributes['model'] ?? '' ) === $style ) {
			$attributes['model'] = $legacy;
		}
		return md5( wp_json_encode( $attributes ) );
	}
	/**
	 * Upgrade an existing session's style spelling, retaining its variation ID.
	 *
	 * @param array $item Restored cart line.
	 * @return array
	 */
	public static function cart_style( $item ) {
		$product = $item['data'] ?? null;
		if ( $product && $product->is_type( 'variation' ) ) {
			$legacy = $product->get_meta( '_pc_legacy_model_attribute' );
			$style  = $product->get_meta( '_pc_public_style' );
			if ( $legacy && $style && ( $item['variation']['attribute_model'] ?? '' ) === $legacy && $product->get_attribute( 'model' ) === $style ) {
				$item['variation']['attribute_model'] = $style;
			}
		}
		return $item;
	}
}
