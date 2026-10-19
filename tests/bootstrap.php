<?php
/** Minimal WP doubles; real WordPress lifecycle tests live in integration/. */
define( 'ABSPATH', __DIR__ . '/fake-wordpress/' );
$GLOBALS['pc_test_hooks'] = array();
$GLOBALS['pc_test_caps'] = array();
$GLOBALS['pc_test_translation'] = null;
$GLOBALS['pc_test_domains'] = array();

function add_action( $hook, $callback ) {
	$GLOBALS['pc_test_hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback ) { add_action( $hook, $callback ); }
function register_activation_hook( $file, $callback ) {
	$GLOBALS['pc_test_activation'] = $callback;
}
function current_user_can( $capability ) {
	return in_array( $capability, $GLOBALS['pc_test_caps'], true );
}
function esc_html__( $text, $domain ) {
	$GLOBALS['pc_test_domains'][] = $domain;
	return htmlspecialchars( $GLOBALS['pc_test_translation'] ?? $text, ENT_QUOTES, 'UTF-8' );
}
function plugin_basename( $path ) {
	return 'parameter-commerce/' . basename( $path );
}
function load_plugin_textdomain( $domain, $deprecated, $path ) {
	$GLOBALS['pc_test_loaded_domain'] = array( $domain, $path );
}
function pc_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function pc_notice() {
	ob_start();
	ParameterCommerce\Plugin::dependency_notice();
	return ob_get_clean();
}
