<?php
/**
 * Product-level catalog facts and their existing source disclosures.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep product descriptions separate from specification content. */
final class Catalog_Specifications {
	const META = '_pc_catalog_specifications';

	/** Native editor integration, no public metadata registration. */
	public static function register_hooks() {
		add_action( 'add_meta_boxes', array( self::class, 'boxes' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/** Register the dedicated specification editor. */
	public static function boxes() {
		add_meta_box( 'pc-catalog-specifications', __( 'Specifications & evidence — product details', 'parameter-commerce' ), array( self::class, 'editor' ), 'product', 'normal', 'default' );
	}

	/**
	 * Read only the explicitly public-facing specification field.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function html( $product ) {
		$value = $product ? $product->get_meta( self::META ) : '';
		return is_string( $value ) ? wp_kses_post( $value ) : '';
	}

	/**
	 * Match existing authorized draft-preview behavior without opening public APIs.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function visible( $product ) {
		return $product && ! $product->is_type( 'variation' ) && ! get_post_field( 'post_password', $product->get_id() ) && ( Public_Specifications::visible( $product->get_id() ) || ( current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $product->get_id() ) ) );
	}

	/**
	 * Render an escaped native editor. Published values retain source/scope text.
	 *
	 * @param \WP_Post $post Product post.
	 */
	public static function editor( $post ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_pc_structured_specs', true ) ) {
			echo '<p>' . esc_html__( 'These specifications are maintained in WooCommerce → Parameter Specs. Product pages, comparison and filters use the same approved records.', 'parameter-commerce' ) . '</p>';
			return;
		}
		wp_nonce_field( 'pc_catalog_specifications', 'pc_catalog_specifications_nonce' );
		echo '<p>' . esc_html__( 'Public parameters and source details for the Specifications & evidence tab. Keep sample limitations and sources with the values. Leave unknown fields out. Use Description for product introductions and intended uses.', 'parameter-commerce' ) . '</p>';
		wp_editor(
			self::html( wc_get_product( $post->ID ) ),
			'pc_catalog_specifications',
			array(
				'textarea_name' => 'pc_catalog_specifications',
				'textarea_rows' => 16,
				'media_buttons' => false,
			)
		);
	}

	/**
	 * Save only an explicit authorized editor submission; missing fields preserve data.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['pc_catalog_specifications_nonce'], $_POST['pc_catalog_specifications'] ) || ! is_string( $_POST['pc_catalog_specifications_nonce'] ) || ! is_string( $_POST['pc_catalog_specifications'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_catalog_specifications_nonce'] ) ), 'pc_catalog_specifications' ) ) {
			return;
		}
		if ( $product->get_meta( '_pc_structured_specs' ) ) {
			return;
		}
		$value = wp_kses_post( wp_unslash( $_POST['pc_catalog_specifications'] ) );
		if ( '' === trim( $value ) ) {
			$product->delete_meta_data( self::META );
		} else {
			$product->update_meta_data( self::META, $value );
		}
	}
}
