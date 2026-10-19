<?php
/** Dependency-free CLI tests, isolated by process for optional WC symbols. */
$scenario = $argv[1] ?? null;
if ( null === $scenario ) {
	$failed = false;
	foreach ( array( 'missing', 'class-only', 'function-only', 'available' ) as $case ) {
		passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $case ), $status );
		$failed = $failed || 0 !== $status;
	}
	exit( $failed ? 1 : 0 );
}

require __DIR__ . '/bootstrap.php';
if ( in_array( $scenario, array( 'class-only', 'available' ), true ) ) {
	class WooCommerce {}
}
if ( in_array( $scenario, array( 'function-only', 'available' ), true ) ) {
	function wc_get_product() {
		throw new RuntimeException( 'The M0 scaffold must not query products.' );
	}
}

try {
	ob_start();
	require dirname( __DIR__ ) . '/parameter-commerce/parameter-commerce.php';
	pc_assert( '' === ob_get_clean(), 'Bootstrap must produce no activation/public output.' );
	foreach ( array( 'woocommerce_product_tabs', 'wp_ajax_pc_specifications', 'woocommerce_checkout_create_order_line_item', 'woocommerce_single_product_summary' ) as $hook ) {
		pc_assert( isset( $GLOBALS['pc_test_hooks'][ $hook ] ), 'Shared product feature hook missing: ' . $hook );
	}
	foreach ( array( 'wp_ajax_pc_google_start', 'wp_ajax_pc_google_finish', 'woocommerce_gla_product_attribute_values', 'pc_referrals_reconcile', 'admin_post_pc_shipping', 'determine_locale' ) as $hook ) {
		pc_assert( ! isset( $GLOBALS['pc_test_hooks'][ $hook ] ), 'International integration must not register: ' . $hook );
	}
	pc_assert( ! isset( $GLOBALS['pc_test_loaded_domain'] ), 'Translations must wait for init.' );
	call_user_func( $GLOBALS['pc_test_hooks']['init'][0] );
	pc_assert( array( 'parameter-commerce', 'parameter-commerce/languages' ) === $GLOBALS['pc_test_loaded_domain'], 'Translation domain/path mismatch.' );
	$available = 'available' === $scenario;
	pc_assert( $available === ParameterCommerce\Plugin::has_woocommerce(), 'Dependency detection failed.' );
	pc_assert( '' === pc_notice(), 'Visitors/customers must not see staff notices.' );
	foreach ( array( 'activate_plugins', 'manage_woocommerce' ) as $cap ) {
		$GLOBALS['pc_test_caps'] = array( $cap );
		$notice = pc_notice();
		pc_assert( $available ? '' === $notice : str_contains( $notice, 'requires an active WooCommerce' ), 'Staff dependency notice incorrect.' );
	}
	$GLOBALS['pc_test_translation'] = '<script>unsafe</script> & translated';
	$notice = pc_notice();
	pc_assert( ! str_contains( $notice, '<script>' ), 'Translated text must be escaped.' );
	if ( ! $available ) {
		pc_assert( str_contains( $notice, '&lt;script&gt;' ), 'Escaping must preserve text.' );
		pc_assert( array( 'parameter-commerce' ) === array_unique( $GLOBALS['pc_test_domains'] ), 'Incorrect text domain.' );
	}
	$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/demo-product.json' ), true, 512, JSON_THROW_ON_ERROR );
	pc_assert( true === $fixture['demo'] && 'draft' === $fixture['status'] && array() === $fixture['claims'], 'Fixture must be demo-only with no claims.' );
	echo "PASS: $scenario (bootstrap, dependency detection, permissions, escaping, translations, fixture)\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, "FAIL: $scenario: {$error->getMessage()}\n" );
	exit( 1 );
}
