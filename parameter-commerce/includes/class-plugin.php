<?php
/**
 * Plugin bootstrap and dependency detection.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers staff editing, public specifications, and approved brand content.
 */
final class Plugin {

	/**
	 * Register callbacks without calling WooCommerce during plugin loading.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'load_translations' ) );
		add_action( 'admin_notices', array( self::class, 'dependency_notice' ) );
		add_action( 'init', array( Schema::class, 'maybe_upgrade' ), 20 );
		// Domestic baseline: international branding, login, feeds and shipping stay unregistered.
		Admin::register_hooks();
		Public_Specifications::register_hooks();
		Catalog::register_hooks();
		Suppliers::register_hooks();
		Product_Drafts::register_hooks();
		Catalog_Specifications::register_hooks();
		Comparison::register_hooks();
		Pack_Builder::register_hooks();
		Product_Discovery::register_hooks();
		Yarn_Specifications::register_hooks();
		Account_Notices::register_hooks();
		Size_Shape::register_hooks();
		Wear_Profile::register_hooks();
		Domestic_Catalog::register_hooks();
		Referrals::register_hooks();
		Referral_UI::register_hooks();
		add_action( 'activated_plugin', array( self::class, 'dependency_activated' ) );
	}

	/**
	 * Grant the store-manager capability when WooCommerce is activated later.
	 *
	 * @param string $plugin Activated plugin basename.
	 * @return void
	 */
	public static function dependency_activated( $plugin ) {
		if ( 'woocommerce/woocommerce.php' === $plugin ) {
			Schema::grant_capabilities();
		}
	}

	/**
	 * Load translations after WordPress has initialized its locale.
	 *
	 * @return void
	 */
	public static function load_translations() {
		load_plugin_textdomain(
			'parameter-commerce',
			false,
			dirname( plugin_basename( dirname( __DIR__ ) . '/parameter-commerce.php' ) ) . '/languages'
		);
	}

	/**
	 * Detect the commerce API without relying on a guessed version number.
	 *
	 * @return bool Whether the required WooCommerce entry points exist.
	 */
	public static function has_woocommerce() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Explain the missing dependency to staff, without affecting public output.
	 *
	 * @return void
	 */
	public static function dependency_notice() {
		if ( self::has_woocommerce() || ! ( current_user_can( 'activate_plugins' ) || current_user_can( 'manage_woocommerce' ) ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Parameter Commerce requires an active WooCommerce installation for commerce features. The plugin remains inactive for commerce until WooCommerce is available.', 'parameter-commerce' );
		echo '</p></div>';
	}
}
