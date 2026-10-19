<?php
/**
 * Plugin Name: SockSpecs CN — Parameter Commerce
 * Description: Evidence-led product specifications and staff claim management for the independent SockSpecs China store.
 * Version: 0.1.0
 * License: GPL-2.0-or-later
 * Text Domain: parameter-commerce
 * Domain Path: /languages
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-failure.php';
require_once __DIR__ . '/includes/class-metrics.php';
require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-validator.php';
require_once __DIR__ . '/includes/class-repository.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-product-picker.php';
require_once __DIR__ . '/includes/class-public-specifications.php';
require_once __DIR__ . '/includes/class-catalog-specifications.php';
require_once __DIR__ . '/includes/class-brand-pages.php';
require_once __DIR__ . '/includes/class-public-policies.php';
require_once __DIR__ . '/includes/class-storefront-language.php';

require_once __DIR__ . '/includes/class-catalog.php';
require_once __DIR__ . '/includes/class-suppliers.php';
require_once __DIR__ . '/includes/class-product-drafts.php';
require_once __DIR__ . '/includes/class-demo-catalog.php';
require_once __DIR__ . '/includes/class-comparison.php';

require_once __DIR__ . '/includes/class-pack-builder.php';
require_once __DIR__ . '/includes/class-pack-pricing.php';
require_once __DIR__ . '/includes/class-product-discovery.php';
require_once __DIR__ . '/includes/class-google-packs.php';
require_once __DIR__ . '/includes/class-yarn-specifications.php';
require_once __DIR__ . '/includes/class-account-notices.php';
require_once __DIR__ . '/includes/class-google-login.php';
require_once __DIR__ . '/includes/class-size-shape.php';

require_once __DIR__ . '/includes/class-referral-store.php';
require_once __DIR__ . '/includes/class-referrals.php';
require_once __DIR__ . '/includes/class-referral-ui.php';

require_once __DIR__ . '/includes/class-yunexpress-client.php';
require_once __DIR__ . '/includes/class-shipping.php';
require_once __DIR__ . '/includes/class-shipping-admin.php';

register_activation_hook( __FILE__, array( Schema::class, 'activate' ) );
Plugin::register_hooks();
