<?php
/**
 * Small private supplier address book, separate from public evidence projections.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Failures are escaped by the calling controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native WordPress records; no public routes, supplier prices, or ERP workflows. */
final class Suppliers {
	/** Register admin-only storage and edit controls. @return void */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'add_meta_boxes', array( self::class, 'boxes' ) );
		add_action( 'save_post_pc_supplier', array( self::class, 'save_post' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'private_status' ) );
		add_filter( 'manage_pc_supplier_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_pc_supplier_posts_custom_column', array( self::class, 'column' ), 10, 2 );
	}

	/** Supplier capabilities intentionally map only to existing staff authority. @return void */
	public static function register() {
		$caps = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_woocommerce' );
		register_post_type(
			'pc_supplier',
			array(
				'labels'              => array(
					'name'          => __( 'Suppliers (private)', 'parameter-commerce' ),
					'singular_name' => __( 'Supplier', 'parameter-commerce' ),
					'add_new_item'  => __( 'Add private supplier', 'parameter-commerce' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'show_in_menu'        => current_user_can( 'edit_others_shop_orders' ) ? 'woocommerce' : 'pc-specs',
				'supports'            => array( 'title' ),
				'capabilities'        => $caps,
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Even an attempted Publish stays private. Trash remains available.
	 *
	 * @param array $data Core sanitized post fields.
	 * @return array
	 */
	public static function private_status( $data ) {
		if ( 'pc_supplier' === $data['post_type'] && ! in_array( $data['post_status'], array( 'trash', 'auto-draft' ), true ) ) {
			$data['post_status'] = 'private';
		}
		return $data;
	}

	/** Labels also define the allowlist of stored fields. @return array */
	public static function fields() {
		return array(
			'location'           => __( 'Supplier location', 'parameter-commerce' ),
			'website'            => __( 'Supplier website (HTTPS)', 'parameter-commerce' ),
			'original_url'       => __( 'Original product URL (HTTPS)', 'parameter-commerce' ),
			'contact'            => __( 'Contact information (private)', 'parameter-commerce' ),
			'verification_notes' => __( 'Verification notes (private)', 'parameter-commerce' ),
			'remarks'            => __( 'Internal remarks', 'parameter-commerce' ),
		);
	}

	/**
	 * Add the stable code to the private supplier list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$columns['pc_code'] = __( 'Supplier code', 'parameter-commerce' );
		return $columns;
	}

	/**
	 * Render private list column.
	 *
	 * @param string $column Column.
	 * @param int    $id Supplier.
	 */
	public static function column( $column, $id ) {
		if ( 'pc_code' === $column ) {
			echo esc_html( get_post_meta( $id, '_pc_supplier_code', true ) );
		}
	}

	/**
	 * Reserve an immutable code. The option remains even if a supplier is deleted.
	 *
	 * @param int    $id Supplier record.
	 * @param string $code Three-digit code, explicitly shared between environments.
	 * @throws Failure Invalid, changed or previously reserved code.
	 */
	public static function assign_code( $id, $code ) {
		Repository::authorize();
		if ( 'pc_supplier' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) || ! is_string( $code ) || ! preg_match( '/^(?!000)[0-9]{3}$/D', $code ) ) {
			throw new Failure( array( 'code' => __( 'Use a supplier code from 001 to 999.', 'parameter-commerce' ) ) );
		}
		global $wpdb;
		$lock = 'pc_supplier_code_' . get_current_blog_id() . '_' . (int) $id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Advisory lock must use this database connection, without caching.
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			throw new Failure( array( 'code' => __( 'Supplier is being updated. Please try again.', 'parameter-commerce' ) ), 409 );
		}
		try {
			clean_post_cache( $id );
			$current = get_post_meta( $id, '_pc_supplier_code', true );
			if ( $current && $current !== $code ) {
				throw new Failure( array( 'code' => __( 'An assigned supplier code cannot be changed.', 'parameter-commerce' ) ) );
			}
			$key = 'pc_supplier_code_' . $code;
			if ( ! add_option( $key, (int) $id, '', false ) && (int) get_option( $key ) !== (int) $id ) {
				throw new Failure( array( 'code' => __( 'This supplier code is already reserved. Choose another code.', 'parameter-commerce' ) ), 409 );
			}
			update_post_meta( $id, '_pc_supplier_code', $code );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the connection's advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Register the supplier detail box. @return void */
	public static function boxes() {
		add_meta_box( 'pc-supplier', __( 'Private sourcing details', 'parameter-commerce' ), array( self::class, 'box' ), 'pc_supplier', 'normal', 'high' );
	}

	/**
	 * Render escaped staff-only fields and associated product references.
	 *
	 * @param \WP_Post $post Supplier.
	 * @return void
	 */
	public static function box( $post ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_nonce_field( 'pc_supplier', 'pc_supplier_nonce' );
		$code = get_post_meta( $post->ID, '_pc_supplier_code', true );
		echo '<p><label for="pc-supplier-code">' . esc_html__( 'Supplier code', 'parameter-commerce' ) . '</label><br><input type="text" id="pc-supplier-code" name="pc_supplier[code]" value="' . esc_attr( $code ) . '" pattern="[0-9]{3}" maxlength="3" placeholder="001" ' . ( $code ? 'readonly' : '' ) . '><br><span class="description">' . esc_html__( 'Optional code from 001 to 999. Once assigned, it stays fixed and cannot be reused.', 'parameter-commerce' ) . '</span></p>';
		echo '<p>' . esc_html__( 'Always private. Record supplier declarations in Parameter Specs; supplier notes never become verified measurements.', 'parameter-commerce' ) . '</p>';
		foreach ( self::fields() as $key => $label ) {
			echo '<p><label for="pc-supplier-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><textarea class="large-text" id="pc-supplier-' . esc_attr( $key ) . '" name="pc_supplier[' . esc_attr( $key ) . ']" maxlength="4000">' . esc_textarea( get_post_meta( $post->ID, '_pc_' . $key, true ) ) . '</textarea></p>';
		}
		echo '<h3>' . esc_html__( 'Product references', 'parameter-commerce' ) . '</h3>';
		if ( Plugin::has_woocommerce() ) {
			foreach ( wc_get_products(
				array(
					'pc_supplier_id' => $post->ID,
					'limit'          => 100,
					'status'         => array( 'publish', 'private', 'draft', 'pending' ),
				)
			) as $product ) {
				echo '<p><a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . esc_html( Product_Picker::label( $product ) ) . '</a></p>';
			}
		}
	}

	/**
	 * Validate all input before writing any supplier metadata.
	 *
	 * @param int   $id Supplier ID.
	 * @param array $input Unslashed fields.
	 * @return void
	 * @throws Failure On unauthorized or invalid input.
	 */
	public static function save( $id, array $input ) {
		Repository::authorize();
		if ( 'pc_supplier' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			throw new Failure( array( 'supplier' => __( 'Choose an existing private supplier.', 'parameter-commerce' ) ), 403 );
		}
		$clean = array();
		foreach ( self::fields() as $key => $label ) {
			$value = $input[ $key ] ?? '';
			if ( ! is_string( $value ) || mb_strlen( $value ) > 4000 ) {
				throw new Failure( array( $key => __( 'Use text of at most 4000 characters.', 'parameter-commerce' ) ) );
			}
			if ( in_array( $key, array( 'website', 'original_url' ), true ) ) {
				$clean[ $key ] = self::url( $value );
			} else {
				$clean[ $key ] = sanitize_textarea_field( $value );
			}
		}
		if ( isset( $input['code'] ) && '' !== $input['code'] ) {
			self::assign_code( $id, $input['code'] );
		}
		foreach ( $clean as $key => $value ) {
			update_post_meta( $id, '_pc_' . $key, $value );
		}
	}

	/**
	 * HTTPS reference only, never fetched by the server.
	 *
	 * @param string $value URL.
	 * @return string
	 * @throws Failure On invalid URL.
	 */
	public static function url( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
			throw new Failure( array( 'url' => __( 'Use a valid HTTPS URL without credentials.', 'parameter-commerce' ) ) );
		}
		$value = trim( $value );
		$parts = wp_parse_url( $value );
		if ( '' !== $value && ( ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) ) {
			throw new Failure( array( 'url' => __( 'Use a valid HTTPS URL without credentials.', 'parameter-commerce' ) ) );
		}
		return esc_url_raw( $value, array( 'https' ) );
	}

	/**
	 * Native editor save; missing nonce/autosave never overwrites metadata.
	 *
	 * @param int $id Supplier ID.
	 * @return void
	 */
	public static function save_post( $id ) {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['pc_supplier_nonce'] ) || ! is_string( $_POST['pc_supplier_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_supplier_nonce'] ) ), 'pc_supplier' ) ) {
			return;
		}
		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() validates every allowlisted field before writing.
			self::save( $id, isset( $_POST['pc_supplier'] ) && is_array( $_POST['pc_supplier'] ) ? wp_unslash( $_POST['pc_supplier'] ) : array() );
		} catch ( Failure $error ) {
			wp_die(
				esc_html( $error->getMessage() ),
				'',
				array(
					'response'  => (int) $error->status,
					'back_link' => true,
				)
			);
		}
	}
}
