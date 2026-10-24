<?php
/**
 * Opt-in packs composed of native, individually stocked pair variations.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep commerce on native component lines; never maintain a second stock ledger. */
final class Pack_Builder {
	/** Trusted, server-validated addition in progress.
	 *
	 * @var bool
	 */
	private static $adding = false;
	/** Guard recursive group removal/restoration.
	 *
	 * @var bool
	 */
	private static $changing = false;

	/** Register integrations without requiring WooCommerce at bootstrap. */
	public static function register_hooks() {
		Pack_Pricing::register_hooks();
		add_action( 'woocommerce_product_options_inventory_product_data', array( self::class, 'settings' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_settings' ) );
		add_action( 'woocommerce_variable_add_to_cart', array( self::class, 'form' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'template_redirect', array( self::class, 'submit' ), 15 );
		add_filter( 'woocommerce_add_to_cart_validation', array( self::class, 'allow_add' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( self::class, 'guard_add' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( self::class, 'check_cart' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( self::class, 'api_errors' ), 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( self::class, 'allow_update' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_quantity', array( self::class, 'quantity_html' ), 10, 3 );
		foreach ( array( 'minimum', 'maximum', 'editable' ) as $limit ) {
			add_filter( 'woocommerce_store_api_product_quantity_' . $limit, array( self::class, 'quantity_limit' ), 10, 3 );
		}
		add_action( 'woocommerce_cart_item_removed', array( self::class, 'remove_group' ), 5, 2 );
		add_action( 'woocommerce_cart_item_restored', array( self::class, 'restore_group' ), 5, 2 );
		add_filter( 'woocommerce_get_item_data', array( self::class, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'order_item' ), 10, 3 );
	}

	/**
	 * Explicit product opt-in, independent of purchasability.
	 *
	 * @param int $id Parent ID.
	 * @return bool
	 */
	public static function enabled( $id ) {
		$product = wc_get_product( $id );
		return $product && $product->is_type( 'variable' ) && 'yes' === $product->get_meta( '_pc_pack_builder' );
	}

	/**
	 * Optional browsing of confirmed variants whose stock is not available yet.
	 * This affects display only; cart validation always uses sellable groups.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return bool
	 */
	public static function show_unavailable( $product ) {
		return $product && 'yes' === $product->get_meta( '_pc_pack_show_unavailable' );
	}

	/** Native product editor setting, covered by its product-save nonce. */
	public static function settings() {
		echo '<div class="options_group show_if_variable">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_pc_pack_builder',
				'label'       => __( 'Build Your Pack', 'parameter-commerce' ),
				'description' => __( 'Sell 4, 6 or 12 pairs with a quantity per color. Enable only when every variation price, weight and stock quantity represents ONE PAIR. Use explicit Color and Size attributes, variation-level stock and no backorders. Existing prepacked stock must not be reused.', 'parameter-commerce' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save only through authorized native product editing.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_settings( $product ) {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $_POST['woocommerce_meta_nonce'] ) || ! is_string( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$product->update_meta_data( '_pc_pack_builder', $product->is_type( 'variable' ) && isset( $_POST['_pc_pack_builder'] ) ? 'yes' : 'no' );
	}

	/**
	 * Available, unambiguous native children, grouped by all non-color attributes.
	 *
	 * @param int  $id Parent ID.
	 * @param bool $preview Staff-only, read-only coming-soon preview. Never used for cart validation.
	 * @param bool $display Include unavailable options only for opted-in display. Never for cart validation.
	 * @return array Groups of native variations.
	 * @throws \RuntimeException Invalid configuration.
	 */
	public static function groups( $id, $preview = false, $display = false ) {
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			throw new \RuntimeException( esc_html__( 'Build Your Pack requires an existing variable product.', 'parameter-commerce' ) );
		}
		if ( ! self::enabled( $id ) ) {
			throw new \RuntimeException( esc_html__( 'Build Your Pack is not enabled for this product.', 'parameter-commerce' ) );
		}
		if ( $preview && ! self::can_preview( $id ) ) {
			throw new \RuntimeException( esc_html__( 'Only authorized store staff can preview this pack.', 'parameter-commerce' ) );
		}
		if ( ! $preview && 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) ) {
			throw new \RuntimeException( esc_html__( 'This pack cannot be purchased while the store is in coming-soon mode.', 'parameter-commerce' ) );
		}
		if ( get_post_meta( $id, '_pc_demo_catalog', true ) ) {
			throw new \RuntimeException( esc_html__( 'Demonstration products cannot be purchased as packs.', 'parameter-commerce' ) );
		}
		if ( 'publish' !== $product->get_status() ) {
			throw new \RuntimeException( esc_html__( 'This product must be published before its pack builder is available.', 'parameter-commerce' ) );
		}
		if ( 'hidden' === $product->get_catalog_visibility() || get_post_field( 'post_password', $id ) || ! $product->is_visible() ) {
			throw new \RuntimeException( esc_html__( 'This product is not publicly accessible. Check its catalog visibility and password protection.', 'parameter-commerce' ) );
		}
		// Match WooCommerce's native option names, defaults and boolean conversion.
		$manage_stock = wc_string_to_bool( get_option( 'woocommerce_manage_stock', 'yes' ) );
		if ( ! $manage_stock ) {
			throw new \RuntimeException( esc_html__( 'WooCommerce global stock management is disabled. Enable Manage stock in Products > Inventory.', 'parameter-commerce' ) );
		}
		if ( (int) get_option( 'woocommerce_hold_stock_minutes', 60 ) < 1 ) {
			throw new \RuntimeException( esc_html__( 'WooCommerce Hold stock must be a positive number of minutes in Products > Inventory.', 'parameter-commerce' ) );
		}
		if ( ! apply_filters( 'woocommerce_hold_stock_for_checkout', $manage_stock ) ) {
			throw new \RuntimeException( esc_html__( 'Checkout stock reservations are disabled by the woocommerce_hold_stock_for_checkout filter. Review the plugin or customization supplying this filter.', 'parameter-commerce' ) );
		}
		$pack_prices = Pack_Pricing::prices( $product );
		$keys        = array_map( 'sanitize_title', array_keys( $product->get_variation_attributes() ) );
		$colors      = array_values( array_intersect( $keys, array( 'color', 'pa_color' ) ) );
		if ( 1 !== count( $colors ) || array_intersect( $keys, array( 'pack-quantity', 'pa_pack-quantity', 'pack-size', 'pa_pack-size' ) ) || count( $product->get_children() ) > 100 ) {
			throw new \RuntimeException( esc_html__( 'Pack setup requires one Color attribute, single-pair variations and at most 100 variations.', 'parameter-commerce' ) );
		}
		$display = $display && self::show_unavailable( $product );
		$groups  = array();
		$seen    = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child || 'publish' !== $child->get_status() ) {
				continue;
			}
			if ( $pack_prices && ( $child->get_tax_status() !== $product->get_tax_status() || $child->get_tax_class() !== $product->get_tax_class() ) ) {
				throw new \RuntimeException( esc_html__( 'All colors and sizes in a priced pack must share the parent product tax settings.', 'parameter-commerce' ) );
			}
			$attributes = $child->get_attributes();
			if ( array_diff( $keys, array_keys( $attributes ) ) || in_array( '', $attributes, true ) || ! $child->get_manage_stock() || $child->get_stock_managed_by_id() !== $child_id || $child->backorders_allowed() || $child->is_sold_individually() || $child->is_virtual() ) {
				throw new \RuntimeException( esc_html__( 'Each pack variation needs explicit attributes, its own pair stock, physical shipping and no backorders.', 'parameter-commerce' ) );
			}
			$color = $attributes[ $colors[0] ];
			unset( $attributes[ $colors[0] ] );
			ksort( $attributes );
			$group = Product_Identity::pack_group( $attributes, $child );
			if ( isset( $seen[ $group ][ $color ] ) ) {
				throw new \RuntimeException( esc_html__( 'Duplicate color variations must be resolved before selling packs.', 'parameter-commerce' ) );
			}
			$seen[ $group ][ $color ] = true;
			if ( get_post_field( 'post_password', $child_id ) || ! $child->variation_is_visible() || ! $child->variation_is_active() || ( ! $display && ( ! $child->is_purchasable() || ! $child->is_in_stock() ) ) ) {
				continue;
			}
			$groups[ $group ]['label']              = $attributes ? wc_get_formatted_variation( $attributes, true, false ) : __( 'One size', 'parameter-commerce' );
			$groups[ $group ]['items'][ $child_id ] = $child;
		}
		return $groups;
	}

	/**
	 * All variation attributes may differ within a single-product pack.
	 * Preserve current single-pair price and native tax treatment.
	 *
	 * @param \WC_Product_Variation $variation Native single-pair variation.
	 * @return string Compatibility identity, never trusted from a request.
	 */
	public static function mix_key( $variation ) {
		return md5(
			wp_json_encode(
				array( $variation->get_parent_id(), wc_format_decimal( $variation->get_price(), 6 ), $variation->get_tax_status(), $variation->get_tax_class() )
			)
		);
	}

	/**
	 * Strictly validate a complete pack without trusting client labels or prices.
	 *
	 * @param int   $id Parent product ID.
	 * @param mixed $size Pack size.
	 * @param mixed $quantities Variation ID => pair count.
	 * @return array Canonical selected variations and quantities.
	 * @throws \RuntimeException Invalid selection.
	 */
	public static function selection( $id, $size, $quantities ) {
		if ( ! in_array( $size, array( 4, 6, 12, '4', '6', '12' ), true ) || ! is_array( $quantities ) || count( $quantities ) > 100 ) {
			throw new \RuntimeException( esc_html__( 'Choose a 4-pair, 6-pair or 12-pair pack.', 'parameter-commerce' ) );
		}
		$chosen = array();
		foreach ( $quantities as $variation_id => $quantity ) {
			if ( ! ctype_digit( (string) $variation_id ) || ! is_scalar( $quantity ) || ! preg_match( '/^(0|[1-9][0-9]?)$/D', (string) $quantity ) || (int) $quantity > (int) $size ) {
				throw new \RuntimeException( esc_html__( 'Variant quantities must be whole numbers between zero and the pack size.', 'parameter-commerce' ) );
			}
			if ( (int) $quantity ) {
				$chosen[ (int) $variation_id ] = (int) $quantity;
			}
		}
		if ( array_sum( $chosen ) !== (int) $size ) {
			throw new \RuntimeException( esc_html__( 'The selected quantities must equal the pack size.', 'parameter-commerce' ) );
		}
		$compatible = array();
		foreach ( self::groups( $id ) as $group ) {
			foreach ( $group['items'] as $variation_id => $variation ) {
				$compatible[ self::mix_key( $variation ) ][ $variation_id ] = true;
			}
		}
		foreach ( $compatible as $items ) {
			if ( ! array_diff_key( $chosen, $items ) ) {
				ksort( $chosen );
				return $chosen;
			}
		}
		throw new \RuntimeException( esc_html__( 'Choose available variants of the same product with matching per-pair prices and tax treatment.', 'parameter-commerce' ) );
	}

	/**
	 * Add all native lines or roll back every line created by this operation.
	 *
	 * @param int   $id Product ID.
	 * @param mixed $size Pack size.
	 * @param mixed $quantities Requested quantities.
	 * @return string Pack reference.
	 * @throws \RuntimeException Invalid selection or insufficient stock.
	 * @throws \Throwable Native add-to-cart failure, after rollback.
	 */
	public static function add_pack( $id, $size, $quantities ) {
		$chosen  = self::selection( $id, $size, $quantities );
		$in_cart = WC()->cart->get_cart_item_quantities();
		$summary = array();
		foreach ( $chosen as $variation_id => $quantity ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation->get_stock_quantity() - wc_get_held_stock_quantity( $variation ) < $quantity + ( $in_cart[ $variation_id ] ?? 0 ) ) {
				throw new \RuntimeException( esc_html__( 'Not enough stock for the selected variants. Please adjust your pack.', 'parameter-commerce' ) );
			}
			$summary[] = wc_get_formatted_variation( $variation, true, true ) . ' × ' . $quantity;
		}
		$pack         = array(
			'id'       => wp_generate_uuid4(),
			'product'  => (int) $id,
			'size'     => (int) $size,
			'items'    => $chosen,
			'contents' => implode( '; ', $summary ),
		);
		$added        = array();
		self::$adding = true;
		try {
			foreach ( $chosen as $variation_id => $quantity ) {
				$variation = wc_get_product( $variation_id );
				if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $id, $quantity, $variation_id, $variation->get_variation_attributes() ) ) {
					throw new \RuntimeException( esc_html__( 'This pack could not be added. Please check availability.', 'parameter-commerce' ) );
				}
				$key = WC()->cart->add_to_cart( $id, $quantity, $variation_id, $variation->get_variation_attributes(), array( 'pc_pack' => $pack ) );
				if ( ! $key ) {
					throw new \RuntimeException( esc_html__( 'This pack could not be added. Please check availability.', 'parameter-commerce' ) );
				}
				$added[] = $key;
			}
			WC()->cart->calculate_totals();
		} catch ( \Throwable $error ) {
			foreach ( $added as $key ) {
				WC()->cart->remove_cart_item( $key );
			}
			// Failed additions must not be recoverable as incomplete packs via Undo.
			foreach ( WC()->cart->removed_cart_contents as $key => $item ) {
				if ( ( $item['pc_pack']['id'] ?? '' ) === $pack['id'] ) {
					unset( WC()->cart->removed_cart_contents[ $key ] );
				}
			}
			WC()->cart->calculate_totals();
			throw $error;
		} finally {
			self::$adding = false;
		}
		return $pack['id'];
	}

	/**
	 * A display-only exception to the store launch gate, never a purchasing permission.
	 *
	 * @param int $id Product ID.
	 * @return bool
	 */
	private static function can_preview( $id ) {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $id );
	}

	/** Load assets in the head/footer of opted-in product pages only. */
	public static function assets() {
		if ( Plugin::has_woocommerce() && function_exists( 'is_product' ) && is_product() && self::enabled( get_queried_object_id() ) ) {
			wp_enqueue_script( 'pc-pack-builder', plugins_url( 'assets/pack-builder.js', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.24', true );
			wp_enqueue_style( 'pc-pack-builder', plugins_url( 'assets/pack-builder.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.1' );
		}
	}

	/** Replace only an opted-in product's native variable add-to-cart form.
	 *
	 * @throws \RuntimeException Caught and rendered as an unavailable message.
	 */
	public static function form() {
		global $product;
		if ( ! $product || ! self::enabled( $product->get_id() ) ) {
			return;
		}
		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		try {
			$preview = 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) && self::can_preview( $product->get_id() );
			$groups  = self::groups( $product->get_id(), $preview, true );
			if ( ! $groups ) {
				throw new \RuntimeException( esc_html__( 'No colors are currently available for this pack.', 'parameter-commerce' ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display selection.
			$requested = isset( $_GET['pc_pack_group'] ) && is_string( $_GET['pc_pack_group'] ) ? sanitize_key( $_GET['pc_pack_group'] ) : '';
			$selected  = isset( $groups[ $requested ] ) ? $requested : array_key_first( $groups );
			include dirname( __DIR__ ) . '/templates/pack-builder.php';
		} catch ( \RuntimeException $error ) {
			echo '<p>' . esc_html( $error->getMessage() ) . '</p>';
		}
	}

	/** Guest and authenticated form submission through the native WC session.
	 *
	 * @throws \RuntimeException Caught and redirected as a native notice.
	 */
	public static function submit() {
		if ( ! Plugin::has_woocommerce() || ! isset( $_POST['pc_add_pack'] ) ) {
			return;
		}
		nocache_headers();
		$id = isset( $_POST['pc_add_pack'] ) && is_scalar( $_POST['pc_add_pack'] ) ? absint( $_POST['pc_add_pack'] ) : 0;
		try {
			if ( ! isset( $_POST['pc_pack_nonce'] ) || ! is_string( $_POST['pc_pack_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_pack_nonce'] ) ), 'pc_pack_' . $id ) ) {
				throw new \RuntimeException( esc_html__( 'Please reload the product page and try again.', 'parameter-commerce' ) );
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean recursively sanitizes; selection strictly validates IDs/counts.
			self::add_pack( $id, isset( $_POST['pc_pack_size'] ) ? wc_clean( wp_unslash( $_POST['pc_pack_size'] ) ) : '', isset( $_POST['pc_colors'] ) ? wc_clean( wp_unslash( $_POST['pc_colors'] ) ) : array() );
			wc_add_notice( __( 'Your pack was added. Quantities are pairs; removing a variant removes the entire pack. To change your selection, remove the pack and build it again.', 'parameter-commerce' ) );
			wp_safe_redirect( wc_get_cart_url() );
		} catch ( \RuntimeException $error ) {
			wc_add_notice( $error->getMessage(), 'error' );
			wp_safe_redirect( $id && wc_get_product( $id ) ? get_permalink( $id ) : wc_get_cart_url() );
		}
		exit;
	}

	/**
	 * Disallow single-pair bypasses, including Store API requests.
	 *
	 * @param bool $passed Earlier validation.
	 * @param int  $id Product ID.
	 * @return bool
	 */
	public static function allow_add( $passed, $id ) {
		$product = wc_get_product( $id );
		$id      = $product && $product->is_type( 'variation' ) ? $product->get_parent_id() : $id;
		if ( ! self::$adding && ! self::selection_adding() && self::enabled( $id ) ) {
			wc_add_notice( __( 'Please use Build Your Pack on the product page.', 'parameter-commerce' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Also protect direct WC_Cart additions and native reorder metadata.
	 *
	 * @param array $data Cart data.
	 * @param int   $id Parent ID.
	 * @return array
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Invalid Store API addition.
	 * @throws \RuntimeException Invalid addition on installations without Store API.
	 */
	public static function guard_add( $data, $id ) {
		if ( ! self::$adding && ! self::selection_adding() && ( self::enabled( $id ) || isset( $data['pc_pack'] ) ) ) {
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'pc_pack_required', esc_html__( 'Please use Build Your Pack on the product page.', 'parameter-commerce' ), 400 );
			}
			throw new \RuntimeException( esc_html__( 'Please use Build Your Pack on the product page.', 'parameter-commerce' ) );
		}
		return $data;
	}

	/** Whether the domestic selection service is adding validated native lines.
	 *
	 * @return bool
	 */
	private static function selection_adding() {
		return class_exists( '\SockSpecsCN\Commerce\SelectionCart' ) && \SockSpecsCN\Commerce\SelectionCart::adding();
	}

	/**
	 * Validate group integrity again on both checkout paths, including restored carts.
	 *
	 * @param \WC_Cart $cart Cart.
	 * @return string Empty on success.
	 * @throws \RuntimeException Caught locally to reject invalid cart groups.
	 */
	public static function cart_error( $cart ) {
		$groups = array();
		try {
			foreach ( $cart->get_cart() as $item ) {
				$pack = $item['pc_pack'] ?? null;
				if ( ! $pack ) {
					if ( self::enabled( $item['product_id'] ) ) {
						throw new \RuntimeException();
					}
					continue;
				}
				if ( ! is_array( $pack ) || ! is_string( $pack['id'] ?? null ) || ! wp_is_uuid( $pack['id'] ) ) {
					throw new \RuntimeException();
				}
				if ( 'selection-v1' === ( $pack['kind'] ?? '' ) && class_exists( '\SockSpecsCN\Commerce\SelectionCart' ) ) {
					$groups[ $pack['id'] ]['selection'][] = $item;
					continue;
				}
				if ( empty( $pack['id'] ) || (int) $item['product_id'] !== $pack['product'] || ! isset( $pack['items'][ $item['variation_id'] ] ) || (float) $item['quantity'] !== (float) $pack['items'][ $item['variation_id'] ] ) {
					throw new \RuntimeException();
				}
				if ( isset( $groups[ $pack['id'] ] ) && ( $groups[ $pack['id'] ]['pack'] ?? null ) !== $pack ) {
					throw new \RuntimeException();
				}
				$groups[ $pack['id'] ]['pack']                           = $pack;
				$groups[ $pack['id'] ]['count'][ $item['variation_id'] ] = ( $groups[ $pack['id'] ]['count'][ $item['variation_id'] ] ?? 0 ) + $item['quantity'];
			}
			foreach ( $groups as $group ) {
				if ( isset( $group['selection'] ) ) {
					if ( isset( $group['pack'] ) ) {
						throw new \RuntimeException();
					}
					\SockSpecsCN\Commerce\SelectionCart::validate( $group['selection'][0]['pc_pack'], $group['selection'], $cart );
					continue;
				}
				$pack     = $group['pack'];
				$expected = self::selection( $pack['product'], $pack['size'], $pack['items'] );
				ksort( $group['count'] );
				if ( array_map( 'intval', $group['count'] ) !== $expected ) {
					throw new \RuntimeException();
				}
			}
		} catch ( \RuntimeException $error ) {
			return __( 'A pack is incomplete or no longer available. Remove that pack and build it again before checkout.', 'parameter-commerce' );
		}
		return '';
	}

	/** Classic checkout validation. */
	public static function check_cart() {
		$error = self::cart_error( WC()->cart );
		if ( $error && ! wc_has_notice( $error, 'error' ) ) {
			wc_add_notice( $error, 'error' );
		}
	}

	/**
	 * Blocks checkout validation.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param \WC_Cart  $cart Cart.
	 */
	public static function api_errors( $errors, $cart ) {
		$error = self::cart_error( $cart );
		if ( $error ) {
			$errors->add( 'pc_invalid_pack', $error );
		}
	}

	/**
	 * Prevent partial edits in classic cart; remove/rebuild is the editing workflow.
	 *
	 * @param bool   $valid Existing validation.
	 * @param string $key Cart key.
	 * @param array  $item Cart item.
	 * @param int    $quantity Requested count.
	 * @return bool
	 */
	public static function allow_update( $valid, $key, $item, $quantity ) {
		if ( isset( $item['pc_pack'] ) && (float) $quantity !== (float) $item['quantity'] && 0 !== (int) $quantity ) {
			wc_add_notice( __( 'To change colors or pack size, remove the pack and build it again.', 'parameter-commerce' ), 'error' );
			return false;
		}
		return $valid;
	}

	/**
	 * Native Blocks quantity limits.
	 *
	 * @param mixed       $value Existing limit.
	 * @param \WC_Product $product Native variation.
	 * @param array|null  $item Cart item.
	 * @return mixed
	 */
	public static function quantity_limit( $value, $product, $item ) {
		return isset( $item['pc_pack'] ) ? ( 'woocommerce_store_api_product_quantity_editable' === current_filter() ? false : $item['pc_pack']['items'][ $item['variation_id'] ] ) : $value;
	}

	/**
	 * Display fixed pair counts in classic cart.
	 *
	 * @param string $html Quantity markup.
	 * @param string $key Cart key.
	 * @param array  $item Item.
	 * @return string
	 */
	public static function quantity_html( $html, $key, $item ) {
		return isset( $item['pc_pack'] ) ? esc_html( $item['quantity'] ) : $html;
	}

	/**
	 * Remove every component in the same pack through native cart APIs.
	 *
	 * @param string   $key Removed key.
	 * @param \WC_Cart $cart Cart.
	 */
	public static function remove_group( $key, $cart ) {
		self::change_group( $key, $cart, false );
	}

	/**
	 * Restore all components for native Undo.
	 *
	 * @param string   $key Restored key.
	 * @param \WC_Cart $cart Cart.
	 */
	public static function restore_group( $key, $cart ) {
		self::change_group( $key, $cart, true );
	}

	/**
	 * Recursive group operation with a reentrancy guard.
	 *
	 * @param string   $key Trigger key.
	 * @param \WC_Cart $cart Cart.
	 * @param bool     $restore Restore instead of remove.
	 */
	private static function change_group( $key, $cart, $restore ) {
		$item = $restore ? ( $cart->cart_contents[ $key ] ?? array() ) : ( $cart->removed_cart_contents[ $key ] ?? array() );
		if ( self::$changing || empty( $item['pc_pack']['id'] ) ) {
			return;
		}
		self::$changing = true;
		try {
			$items = $restore ? $cart->removed_cart_contents : $cart->cart_contents;
			foreach ( $items as $other_key => $other ) {
				if ( $other_key !== $key && ( $other['pc_pack']['id'] ?? '' ) === $item['pc_pack']['id'] ) {
					$restore ? $cart->restore_cart_item( $other_key ) : $cart->remove_cart_item( $other_key );
				}
			}
		} finally {
			self::$changing = false;
		}
	}

	/**
	 * Human-readable cart, checkout and packing metadata.
	 *
	 * @param array $data Native display data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function item_data( $data, $item ) {
		if ( 'selection-v1' === ( $item['pc_pack']['kind'] ?? '' ) ) {
			return $data;
		}
		if ( isset( $item['pc_pack'] ) ) {
			$pack   = $item['pc_pack'];
			$data[] = array(
				'key'   => __( 'Pack reference', 'parameter-commerce' ),
				'value' => $pack['id'],
			);
			$data[] = array(
				'key'   => __( 'Pairs in pack', 'parameter-commerce' ),
				'value' => (string) $pack['size'],
			);
			$data[] = array(
				'key'   => __( 'Pack contents', 'parameter-commerce' ),
				'value' => $pack['contents'],
			);
		}
		return $data;
	}

	/**
	 * Native line metadata appears in order admin, receipts and customer emails.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $key Cart key.
	 * @param array                  $values Cart values.
	 */
	public static function order_item( $item, $key, $values ) {
		if ( isset( $values['pc_pack'] ) ) {
			$item->add_meta_data( '_pc_pack', $values['pc_pack'], true );
			foreach ( self::item_data( array(), $values ) as $data ) {
				$item->add_meta_data( $data['key'], $data['value'], true );
			}
		}
	}
}
