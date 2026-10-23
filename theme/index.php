<?php
/** Main layout for the domestic preview. */
defined('ABSPATH') || exit;
get_header();
?>
<main id="main" class="wrap">
<?php
if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
	woocommerce_content();
} elseif ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		if ( ! is_front_page() && ! has_shortcode( get_the_content(), 'pc_catalog' ) && ! has_shortcode( get_the_content(), 'pc_compare' ) ) {
			the_title( '<h1>', '</h1>' );
		}
		the_content();
	}
} else {
	echo '<h1>暂时没有内容</h1><p>页面正在准备中，请稍后再来。</p>';
}
?>
</main>
<?php get_footer(); ?>
