<?php
/**
 * Domestic parameter previews are independent of sale readiness.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Permit specifications, never checkout, for explicitly marked preview products. */
final class Domestic_Catalog {
	/** Register narrowly scoped storefront and editor hooks. */
	public static function register_hooks() {
		add_filter( 'woocommerce_is_purchasable', array( self::class, 'purchasable' ), 20, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( self::class, 'purchasable' ), 20, 2 );
		add_filter( 'woocommerce_attribute_label', array( self::class, 'attribute_label' ), 20, 2 );
		add_filter( 'woocommerce_get_availability_text', array( self::class, 'availability' ), 20, 2 );
		add_action( 'wp', array( self::class, 'preview_summary' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'setting' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * Read only the domestic marker; children inherit it from their parent.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function preview( $product ) {
		if ( $product && $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		return $product && 'yes' === $product->get_meta( '_sscn_catalog_preview' );
	}

	/**
	 * Preview products cannot enter checkout, even if staff later add a price.
	 *
	 * @param bool        $allowed Native purchase decision.
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function purchasable( $allowed, $product ) {
		return self::preview( $product ) ? false : $allowed;
	}

	/**
	 * Keep stable attribute keys for the inherited specification modules.
	 *
	 * @param string $label Label.
	 * @param string $name Attribute name.
	 * @return string
	 */
	public static function attribute_label( $label, $name ) {
		return array(
			'color'          => __( 'Color', 'parameter-commerce' ),
			'size'           => __( 'Size', 'parameter-commerce' ),
			'model'          => __( 'Model', 'parameter-commerce' ),
			'cuff-height'    => __( 'Cuff style', 'parameter-commerce' ),
			'pa_cuff-height' => __( 'Cuff style', 'parameter-commerce' ),
		)[ strtolower( $name ) ] ?? $label;
	}

	/**
	 * Unknown stock is not a claim that the product has sold out.
	 *
	 * @param string      $text Native availability.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function availability( $text, $product ) {
		if ( ! self::preview( $product ) ) {
			return $text;
		}
		if ( '' === $product->get_price() && $product->is_in_stock() ) {
			return __( 'Price pending confirmation.', 'parameter-commerce' );
		}
		return '' !== $product->get_price() ? __( 'Stock pending confirmation.', 'parameter-commerce' ) : __( 'Price and stock pending confirmation.', 'parameter-commerce' );
	}

	/** Replace the native unavailable purchase form only for a preview product. */
	public static function preview_summary() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! self::preview( wc_get_product( get_queried_object_id() ) ) ) {
			return;
		}
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'notice' ), 30 );
	}

	/** Explain where to inspect individual variants. */
	public static function notice() {
		echo '<p class="sscn-preview-notice">' . esc_html__( 'Product preview. Purchasing is not yet available. Choose a color and size in Specifications & evidence to view its parameters.', 'parameter-commerce' ) . '</p>';
	}

	/** Staff can explicitly release a product after setting domestic commerce data. */
	public static function setting() {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_sscn_catalog_preview',
				'label'       => __( 'Specifications preview only', 'parameter-commerce' ),
				'description' => __( 'Disable purchasing until domestic prices and stock are ready.', 'parameter-commerce' ),
			)
		);
	}

	/**
	 * WooCommerce has already checked the product editor nonce and capabilities.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function save( $product ) {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native authenticated product-save lifecycle.
		$product->update_meta_data( '_sscn_catalog_preview', isset( $_POST['_sscn_catalog_preview'] ) ? 'yes' : 'no' );
	}

	/**
	 * Keep measurement storage unchanged while displaying Chinese count units.
	 *
	 * @param array $metric Structured metric.
	 * @return string
	 */
	public static function unit( $metric ) {
		if ( 'count' === $metric['unit'] && str_starts_with( get_locale(), 'zh' ) ) {
			return 'needle_count' === $metric['key'] ? '针' : '股';
		}
		return $metric['unit'];
	}

	/**
	 * Human-readable Chinese for stored condition tokens, without changing logic.
	 *
	 * @param string $text Machine key or value.
	 * @return string
	 */
	public static function condition_text( $text ) {
		$labels = array(
			'applicability'                 => '适用范围',
			'all_variations'                => '全部已列颜色和尺码',
			'basis'                         => '数据依据',
			'supplier_catalog'              => '厂家产品手册',
			'supplier_listing'              => '厂家详情页',
			'quantity'                      => '计量单位',
			'one pair (operator confirmed)' => '一双（已确认）',
			'packaging'                     => '包装',
			'excluded'                      => '不含包装',
			'inclusion unconfirmed'         => '是否包含包装未确认',
			'basis_confirmed_at'            => '确认日期',
			'catalog garment dimension'     => '产品手册中的袜子平铺尺寸',
			'conversion'                    => '单位换算',
			'cm to mm ×10'                  => '厘米×10换算为毫米',
			'measurement_endpoints'         => '测量起止位置',
			'not confirmed by samples'      => '尚待样品确认',
			'tested_area'                   => '检测部位',
			'excluding heel and toe'        => '不含袜跟及袜头',
			'sole, excluding heel and toe'  => '袜底，不含袜跟及袜头',
			'sample_color'                  => '样品颜色',
			'black'                         => '黑色',
			'black/gray'                    => '黑灰色',
			'blue'                          => '蓝色',
			'light gray'                    => '浅灰色',
			'sample_quantity'               => '样品数量',
			'not stated'                    => '未注明',
			'one pair'                      => '一双',
			'date_basis'                    => '日期说明',
			'report issue date; test date not separately stated' => '报告出具日期；检测日期未单独注明',
		);
		return str_starts_with( get_locale(), 'zh' ) ? ( $labels[ $text ] ?? $text ) : ucfirst( str_replace( '_', ' ', $text ) );
	}
}
