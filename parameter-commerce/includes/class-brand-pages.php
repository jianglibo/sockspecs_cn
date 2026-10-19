<?php
/**
 * Approved SockSpecs messaging, rendered inside ordinary WordPress pages.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Content shortcodes do not create pages or alter site settings on activation. */
final class Brand_Pages {
	/** Register shortcodes after WordPress initializes. @return void */
	public static function register() {
		add_shortcode( 'pc_home', array( self::class, 'home' ) );
		add_shortcode( 'pc_about', array( self::class, 'about' ) );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'returns_link' ), 35 );
	}
	/** Link the approved English policy from the native product summary. @return void */
	public static function returns_link() {
		$page = get_page_by_path( 'refund_returns' );
		if ( ! $page || 'publish' !== $page->post_status || $page->post_password ) {
			return;
		}
		echo '<p class="pc-returns-link"><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'Returns & Refunds', 'parameter-commerce' ) . '</a></p>';
	}
	/** Load brand styles only on a page using either shortcode. @return void */
	public static function assets() {
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'pc_home' ) || has_shortcode( $post->post_content, 'pc_about' ) ) ) {
			wp_enqueue_style( 'pc-brand', plugins_url( 'assets/brand.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.25' );
		}
	}
	/** Render the homepage message without unsubstantiated verification claims. @return string */
	public static function home() {
		ob_start();
		?>
		<div class="pc-brand pc-home" lang="en">
			<p class="pc-wordmark">SOCKSPECS</p>
			<h1><?php esc_html_e( 'Pay for what goes into your socks.', 'parameter-commerce' ); ?></h1>
			<p class="pc-brand-lead"><?php esc_html_e( 'Cotton, synthetic and wool blends, from low-cut sports socks to cushioned crews.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'We choose socks for their materials, construction and fit. Our aim is to keep brand markup and marketing overhead low, so more of what you pay goes toward the socks you wear.', 'parameter-commerce' ); ?></p>
			<div class="pc-brand-actions">
			<?php
			if ( Plugin::has_woocommerce() ) :
				?>
				<a class="pc-brand-button" href="<?php echo esc_url( Catalog::url() ); ?>"><?php esc_html_e( 'Explore the socks', 'parameter-commerce' ); ?></a><?php endif; ?>
			<?php $about = get_page_by_path( 'sockspecs-about' ); ?>
			<?php
			if ( $about && 'publish' === $about->post_status ) :
				?>
				<a href="<?php echo esc_url( get_permalink( $about ) ); ?>"><?php esc_html_e( 'Our philosophy', 'parameter-commerce' ); ?></a><?php endif; ?>
			</div>
			<div class="pc-brand-grid">
				<section><h2><?php esc_html_e( 'See the differences', 'parameter-commerce' ); ?></h2><p><?php esc_html_e( 'Look at the fiber blend, toe construction, cushioning and size range. Product pages bring the available details together, and Compare helps you view them side by side.', 'parameter-commerce' ); ?></p></section>
				<section><h2><?php esc_html_e( 'Build a pack you will wear', 'parameter-commerce' ); ?></h2><p><?php esc_html_e( 'Choose 6 or 12 pairs of one model and size, then mix the available colors. The pack builder shows the total price as you choose, so you can put together a useful everyday supply.', 'parameter-commerce' ); ?></p></section>
				<section><h2><?php esc_html_e( 'Made in China, chosen with care', 'parameter-commerce' ); ?></h2><p><?php esc_html_e( 'Our socks are made in China. We look at what goes into each pair: the materials, the knitting and the finishing details. We aim for good value, rather than the lowest possible price at the expense of the sock itself.', 'parameter-commerce' ); ?></p></section>
			</div>
			<?php $guides = get_page_by_path( 'sock-guides' ); ?>
			<?php if ( $guides && 'publish' === $guides->post_status ) : ?>
				<p class="pc-home-guides"><?php esc_html_e( 'A higher price is worthwhile when the difference matters to you. Our short guides explain materials, fit and construction to help you choose.', 'parameter-commerce' ); ?> <a href="<?php echo esc_url( get_permalink( $guides ) ); ?>"><?php esc_html_e( 'Read Sock Guides', 'parameter-commerce' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
	/** Render the approved About copy, including the implemented comparison feature. @return string */
	public static function about() {
		ob_start();
		?>
		<article class="pc-brand pc-about">
			<p class="pc-wordmark">SOCKSPECS</p>
			<h1><?php esc_html_e( 'Our Philosophy', 'parameter-commerce' ); ?></h1>
			<p class="pc-brand-lead"><?php esc_html_e( 'Products should compete on quality, not marketing budgets.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'At SockSpecs, we believe the value of a product should come from its materials, construction, performance, and durability—not simply the name printed on its packaging.', 'parameter-commerce' ); ?></p>
			<p><?php echo Comparison::url() ? esc_html__( 'That’s why we make product specifications and available quality evidence easy to examine and compare.', 'parameter-commerce' ) : esc_html__( 'That’s why we make product specifications and available quality evidence easy to examine.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'Our goal is simple: better-informed choices, better value, and fair prices.', 'parameter-commerce' ); ?></p>
			<hr><h2><?php esc_html_e( 'Evidence before promises', 'parameter-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Each published measurement identifies its source and method. A manufacturer declaration is labelled as a declaration. Where measurements are unavailable, we say Not yet measured.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'We do not claim equivalent quality, a longer lifetime, or a particular saving without evidence that supports the claim.', 'parameter-commerce' ); ?></p>
			<hr>
			<section aria-labelledby="pc-company-heading">
				<h2 id="pc-company-heading"><?php esc_html_e( 'Who operates SockSpecs?', 'parameter-commerce' ); ?></h2>
				<p><?php esc_html_e( 'SockSpecs is operated by:', 'parameter-commerce' ); ?> <strong lang="zh-Hans">杭州源界星图科技有限公司</strong></p>
				<p><?php esc_html_e( 'English translation of the company name:', 'parameter-commerce' ); ?> <span lang="en">Hangzhou Yuanjie Xingtu Technology Co., Ltd.</span></p>
				<p><?php esc_html_e( 'We are based in China. Orders ship from Zhejiang, China.', 'parameter-commerce' ); ?></p>
				<p><?php esc_html_e( 'For questions about our products, specifications or orders, contact:', 'parameter-commerce' ); ?> <a href="mailto:hello@sockspecs.com">hello@sockspecs.com</a></p>
			</section>
		</article>
		<?php
		return ob_get_clean();
	}
}
