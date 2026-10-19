<?php
/**
 * Public company/policy information while the shop remains in coming-soon mode.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Narrow read-only page exceptions; never change the store's visibility option. */
final class Public_Policies {
	/** Register the documented WooCommerce exclusion filter. */
	public static function register_hooks() {
		add_filter( 'woocommerce_coming_soon_exclude', array( self::class, 'exclude' ) );
		add_filter( 'pre_do_shortcode_tag', array( self::class, 'home' ), 10, 2 );
	}

	/**
	 * Identify a published, readable informational page and a plain read request.
	 *
	 * @return bool
	 */
	private static function readable() {
		if ( 'yes' !== get_option( 'pc_public_policies_enabled' ) || ! in_array( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ), array( 'GET', 'HEAD' ), true ) || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() || is_preview() ) {
			return false;
		}
		// A page query cannot be used to grant an exception to a commerce action.
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-key allowlist; no state changes.
			if ( ! in_array( $key, array( 'page_id', 'pagename', 'lang' ), true ) ) {
				return false;
			}
		}
		$post = get_queried_object();
		return $post instanceof \WP_Post && 'page' === $post->post_type && 'publish' === $post->post_status && ! $post->post_password && ( ( is_front_page() && (int) get_option( 'page_on_front' ) === $post->ID ) || in_array( $post->post_name, array( 'privacy-policy', 'terms-conditions', 'refund_returns' ), true ) );
	}

	/**
	 * Allow only public informational pages, preserving all existing exclusions.
	 *
	 * @param bool $excluded Existing exclusion.
	 * @return bool
	 */
	public static function exclude( $excluded ) {
		return $excluded || self::readable();
	}

	/**
	 * Show a clear prelaunch homepage instead of links to closed shopping flows.
	 *
	 * @param false|string $output Existing shortcode override.
	 * @param string       $tag Shortcode name.
	 * @return false|string
	 */
	public static function home( $output, $tag ) {
		if ( false !== $output || 'pc_home' !== $tag || 'yes' !== get_option( 'woocommerce_coming_soon' ) || ! self::readable() || ! is_front_page() || current_user_can( 'manage_woocommerce' ) ) {
			return $output;
		}
		ob_start();
		?>
		<section class="pc-brand pc-coming-soon-home" lang="en">
			<p class="pc-wordmark">SOCKSPECS</p>
			<h1><?php esc_html_e( 'Know your socks. See the specifications.', 'parameter-commerce' ); ?></h1>
			<p class="pc-brand-lead"><?php esc_html_e( 'Socks selected for their materials, construction and documented specifications.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'SockSpecs is an online sock store serving US customers, with orders dispatched from Zhejiang, China. We explain what is known about each product and identify the source of its specifications.', 'parameter-commerce' ); ?></p>
			<p><strong><?php esc_html_e( 'We’re preparing to launch. Ordering is not available yet.', 'parameter-commerce' ); ?></strong></p>
			<p><?php esc_html_e( 'Customer accounts let you manage orders and referral shopping credit. Optional Google sign-in uses your name, email and Google account identifier to create or connect your SockSpecs account. Email sign-in remains available.', 'parameter-commerce' ); ?></p>
			<p><?php esc_html_e( 'Operated by', 'parameter-commerce' ); ?> <span lang="zh-Hans">杭州源界星图科技有限公司</span> (Hangzhou Yuanjie Xingtu Technology Co., Ltd., English translation).</p>
			<p><a href="mailto:hello@sockspecs.com">hello@sockspecs.com</a></p>
			<nav aria-label="<?php esc_attr_e( 'Policies', 'parameter-commerce' ); ?>">
			<?php
			foreach ( array( 'privacy-policy', 'terms-conditions', 'refund_returns' ) as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page && 'publish' === $page->post_status && ! $page->post_password ) {
					echo '<p><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></p>';
				}
			}
			?>
			</nav>
		</section>
		<?php
		return ob_get_clean();
	}
}
