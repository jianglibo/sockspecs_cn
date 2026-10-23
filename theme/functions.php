<?php
/** Domestic storefront theme. */
defined( 'ABSPATH' ) || exit;
add_action( 'after_setup_theme', static function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menu( 'primary', '主导航' );
} );
add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'sockspecs-cn', get_stylesheet_uri(), array(), '0.1.8' );
} );

add_action( 'woocommerce_before_main_content', static function () { echo '<main id="main" class="wrap">'; }, 10 );
add_action( 'woocommerce_after_main_content', static function () { echo '</main>'; }, 10 );

require_once __DIR__ . '/returns-policy.php';

require_once __DIR__ . '/customer-support.php';

/** Keep the registered site name visible without replacing each page's heading. */
add_filter( 'document_title_parts', static function ( $parts ) {
	$title = is_front_page() ? '首页' : ( $parts['title'] ?? '' );
	unset( $parts['title'], $parts['site'], $parts['tagline'] );
	return array_merge(
		array( 'company' => '杭州源界星图科技有限公司', 'brand' => '索克派', 'title' => $title ),
		$parts
	);
}, 99 );
add_filter( 'document_title_separator', static function () { return '｜'; }, 99 );

require_once __DIR__ . '/wear-feedback.php';
