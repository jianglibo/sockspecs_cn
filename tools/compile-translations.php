<?php
/** Compile the checked-in Chinese PO with WordPress's bundled gettext tools. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || 'sockspecs_cn' !== DB_NAME ) {
	throw new RuntimeException( 'Domestic local WordPress only.' );
}
require_once ABSPATH . WPINC . '/pomo/po.php';
$path = dirname( __DIR__ ) . '/parameter-commerce/languages/parameter-commerce-zh_CN';
$po = new PO();
if ( ! $po->import_from_file( $path . '.po' ) ) {
	WP_CLI::error( 'Unable to read Chinese PO.' );
}
$mo = new MO();
$mo->set_headers( $po->headers );
foreach ( $po->entries as $entry ) {
	$mo->add_entry( $entry );
}
if ( ! $mo->export_to_file( $path . '.mo' ) ) {
	WP_CLI::error( 'Unable to write Chinese MO.' );
}
WP_CLI::success( 'Compiled Chinese translations.' );
