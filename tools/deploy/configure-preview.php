<?php
/** Initial domestic staging settings; never run against another database. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'SSCN_DOMESTIC_SITE' ) || ! SSCN_DOMESTIC_SITE || 'staging' !== wp_get_environment_type() || 'sockspecs_cn' !== DB_NAME ) {
	throw new RuntimeException( 'Domestic staging installation only.' );
}
$options = array(
	'WPLANG' => 'zh_CN', 'blog_public' => 0, 'timezone_string' => 'Asia/Shanghai',
	'woocommerce_coming_soon' => 'yes', 'woocommerce_store_pages_only' => 'yes',
	'woocommerce_private_link' => 'no', 'woocommerce_allow_tracking' => 'no',
	'woocommerce_currency' => 'CNY', 'woocommerce_default_country' => 'CN:CN12',
	'woocommerce_allowed_countries' => 'specific', 'woocommerce_specific_allowed_countries' => array( 'CN' ),
	'woocommerce_ship_to_countries' => 'specific', 'woocommerce_specific_ship_to_countries' => array( 'CN' ),
	'woocommerce_review_rating_verification_required' => 'yes', 'woocommerce_price_num_decimals' => 2,
	'woocommerce_weight_unit' => 'g', 'woocommerce_dimension_unit' => 'cm',
);
foreach ( $options as $name => $value ) {
	update_option( $name, $value );
}
// Hide only WordPress's untouched installer examples on this fresh installation.
foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $item ) {
	$post = get_page_by_path( $item[0], OBJECT, $item[1] );
	if ( $post && ! get_option( 'sscn_local_pages_seeded' ) ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
	}
}
WP_CLI::success( 'Chinese/CNY preview configured; coming-soon remains enabled.' );
