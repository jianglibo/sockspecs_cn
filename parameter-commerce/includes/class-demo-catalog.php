<?php
/**
 * Explicit, repeatable creation of fictional draft catalog concepts.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Failures are escaped by the calling controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Never runs at activation and never creates commercial evidence or inventory. */
final class Demo_Catalog {
	/**
	 * Apply the ten bundled entries without overwriting existing or edited products.
	 *
	 * @return array Product IDs.
	 * @throws Failure On SKU collision or failed creation.
	 */
	public static function seed() {
		Repository::authorize();
		if ( ! current_user_can( 'edit_products' ) ) {
			throw new Failure( array( 'permission' => __( 'You cannot edit this product.', 'parameter-commerce' ) ), 403 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled local JSON, never a remote URL.
		$rows = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/demo-catalog.json' ), true );
		foreach ( $rows as $row ) {
			$id = wc_get_product_id_by_sku( $row['sku'] );
			if ( $id && ! wc_get_product( $id )->get_meta( '_pc_demo_catalog' ) ) {
				throw new Failure( array( 'sku' => __( 'A demo SKU already belongs to an independently managed product. Nothing was overwritten.', 'parameter-commerce' ) ), 409 );
			}
		}
		$categories = array();
		foreach ( array(
			'everyday-socks'    => 'Everyday Socks',
			'cushioned-socks'   => 'Cushioned Socks',
			'running-socks'     => 'Running Socks',
			'merino-wool-socks' => 'Merino Wool Socks',
		) as $slug => $name ) {
			$categories[ $slug ] = self::term( $name, $slug, 'product_cat' );
		}
		$ids = array();
		foreach ( $rows as $row ) {
			$id = wc_get_product_id_by_sku( $row['sku'] );
			if ( $id ) {
				$ids[] = $id;
				continue;
			}
			$product = new \WC_Product_Variable();
			$product->set_name( $row['name'] . ' (DEMO draft)' );
			$product->set_sku( $row['sku'] );
			$product->set_status( 'draft' );
			$product->set_category_ids( array( $categories[ $row['category'] ] ) );
			$product->set_tag_ids( array( self::term( ucwords( str_replace( '-', ' ', $row['use'] ) ), $row['use'], 'product_tag' ) ) );
			$attributes = array();
			foreach ( array(
				'Size'          => array( 'S', 'M', 'L' ),
				'Color'         => array( 'Black', 'Grey' ),
				'Pack quantity' => array( '3', '6', '12' ),
			) as $name => $options ) {
				$attribute = new \WC_Product_Attribute();
				$attribute->set_name( $name );
				$attribute->set_options( $options );
				$attribute->set_variation( true );
				$attribute->set_visible( true );
				$attributes[] = $attribute;
			}
			$product->set_attributes( $attributes );
			$product->update_meta_data( '_pc_demo_catalog', 1 );
			$product->update_meta_data( '_pc_design_features', implode( ',', $row['features'] ) );
			$product->save();
			$copy = Product_Drafts::description( $product );
			$product->set_short_description( $copy['short'] );
			$product->set_description( $copy['full'] . "\n\nNames, sizes, colors and pack options describe a fictional design brief only. Actual fiber composition, suppliers, dimensions and performance remain unconfirmed. Product photographs are not yet available." );
			$product->save();
			$ids[] = $product->get_id();
		}
		return $ids;
	}

	/**
	 * Resolve a native taxonomy term without changing an existing one.
	 *
	 * @param string $name Name.
	 * @param string $slug Slug.
	 * @param string $taxonomy WooCommerce taxonomy.
	 * @return int
	 * @throws Failure If the term cannot be created.
	 */
	private static function term( $name, $slug, $taxonomy ) {
		$term = term_exists( $slug, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			throw new Failure( array( 'category' => $term->get_error_message() ) );
		}
		return (int) $term['term_id'];
	}
}
