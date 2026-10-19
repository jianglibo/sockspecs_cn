<?php
/**
 * Public referral explanation and customer invitations.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Discovery surfaces; no financial mutations. */
final class Referral_UI {
	/** Register native storefront hooks. */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'template_redirect', array( self::class, 'private_headers' ) );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'product' ), 36 );
		add_filter( 'woocommerce_blocks_hook_compatibility_additional_data', array( self::class, 'block_hooks' ), 10, 2 );
		add_filter( 'render_block_woocommerce/add-to-cart-form', array( self::class, 'product_block' ), 10, 3 );
		add_action( 'woocommerce_thankyou', array( self::class, 'thankyou' ), 5 );
	}

	/** Register the editable explanation page's personal-link panel. */
	public static function shortcodes() {
		add_shortcode( 'pc_refer_and_earn', array( self::class, 'panel' ) );
	}

	/**
	 * Link only to a published, public explanation page.
	 *
	 * @return string
	 */
	public static function url() {
		$page = get_page_by_path( 'refer-and-earn' );
		return $page && 'publish' === $page->post_status && ! $page->post_password ? get_permalink( $page ) : '';
	}

	/** Prevent caching personalized explanation-page content before output. */
	public static function private_headers() {
		if ( is_page( 'refer-and-earn' ) && is_user_logged_in() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/** Small, progressive enhancement only on relevant screens. */
	public static function assets() {
		if ( ! Referrals::enabled() || ! ( is_page( 'refer-and-earn' ) || is_product() || is_checkout() || is_account_page() ) ) {
			return;
		}
		$base = plugin_dir_url( dirname( __DIR__ ) . '/parameter-commerce.php' );
		wp_enqueue_style( 'pc-referrals', $base . 'assets/referrals.css', array(), '0.5.6' );
		wp_enqueue_script( 'pc-referrals', $base . 'assets/referrals.js', array(), '0.5.6', true );
	}

	/** Subdued product invitation below the purchase area. */
	public static function product() {
		$url = self::url();
		if ( Referrals::enabled() && $url ) {
			echo '<p class="pc-referral-invitation">' . esc_html__( 'Give friends $2 off. Earn $2 in shopping credit.', 'parameter-commerce' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Refer & Earn', 'parameter-commerce' ) . '</a></p>';
		}
	}

	/**
	 * Let the purchase block control placement instead of the before-excerpt shim.
	 *
	 * @param array  $hooks Compatibility definitions.
	 * @param string $template Template class.
	 * @return array
	 */
	public static function block_hooks( $hooks, $template ) {
		if ( 'SingleProductTemplateCompatibility' === $template ) {
			$hooks[] = array(
				'hook'     => 'woocommerce_single_product_summary',
				'function' => array( self::class, 'product' ),
				'priority' => 36,
			);
		}
		return $hooks;
	}

	/**
	 * Add the invitation immediately after the current product's purchase block.
	 *
	 * @param string    $content Rendered purchase block.
	 * @param array     $parsed Parsed block.
	 * @param \WP_Block $block Block context.
	 * @return string
	 */
	public static function product_block( $content, $parsed, $block ) {
		if ( ! is_product() || (int) ( $block->context['postId'] ?? 0 ) !== get_queried_object_id() ) {
			return $content;
		}
		ob_start();
		self::product();
		return $content . ob_get_clean();
	}

	/**
	 * Render only the viewer's own link, never a link derived from an order owner.
	 *
	 * @return string
	 */
	public static function panel() {
		if ( ! Referrals::enabled() ) {
			return '<p>' . esc_html__( 'The referral program is currently paused. Your recorded credit is preserved.', 'parameter-commerce' ) . '</p>';
		}
		$intro = '<h2>' . esc_html__( 'Happy with your socks? Share with friends.', 'parameter-commerce' ) . '</h2><p>' . esc_html__( 'If you’re happy with the quality and value of your SockSpecs socks, share your referral link with friends or on your social profiles. Each new customer gets $2 off their first order, and you earn $2 in shopping credit when their order is completed. Your rewards add up with no earning cap.', 'parameter-commerce' ) . '</p>';
		if ( ! is_user_logged_in() ) {
			return '<div class="pc-referral-share">' . $intro . '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Sign in or create an account to get your referral link.', 'parameter-commerce' ) . '</a></p></div>';
		}
		$user = get_current_user_id();
		$url  = add_query_arg( 'ss_ref', Referrals::code( $user ), home_url( '/' ) );
		ob_start();
		echo '<div class="pc-referral-share">' . $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped translated text.
		echo '<p>' . esc_html__( 'Available shopping credit:', 'parameter-commerce' ) . ' <strong>' . wp_kses_post( wc_price( max( 0, Referral_Store::balance( $user ) ) / 100, array( 'currency' => 'USD' ) ) ) . '</strong></p>';
		echo '<div class="pc-referral-copy-field"><label>' . esc_html__( 'Your referral link', 'parameter-commerce' ) . '<input type="text" readonly value="' . esc_attr( $url ) . '"></label>';
		echo '<button type="button" class="button pc-referral-copy" hidden data-copied="' . esc_attr__( 'Link copied', 'parameter-commerce' ) . '" data-fallback="' . esc_attr__( 'Select and copy the link above.', 'parameter-commerce' ) . '">' . esc_html__( 'Copy link', 'parameter-commerce' ) . '</button> <span class="pc-referral-copy-status" role="status" aria-live="polite"></span></div>';
		/* translators: %s: the signed-in customer's personal referral URL. */
		$message = sprintf( __( 'Shopping for socks? Use my link for $2 off your first SockSpecs order. I’ll also receive $2 in shopping credit when your order is completed: %s', 'parameter-commerce' ), $url );
		echo '<div class="pc-referral-copy-field"><p><label>' . esc_html__( 'A message you can share', 'parameter-commerce' ) . '<textarea readonly rows="5">' . esc_textarea( $message ) . '</textarea></label></p>';
		echo '<button type="button" class="button pc-referral-copy" hidden data-copied="' . esc_attr__( 'Message copied', 'parameter-commerce' ) . '" data-fallback="' . esc_attr__( 'Select and copy the message above.', 'parameter-commerce' ) . '">' . esc_html__( 'Copy message', 'parameter-commerce' ) . '</button> <span class="pc-referral-copy-status" role="status" aria-live="polite"></span></div>';
		echo '<p><a href="' . esc_url( wc_get_account_endpoint_url( 'referrals' ) ) . '">' . esc_html__( 'View your credit and activity', 'parameter-commerce' ) . '</a></p></div>';
		return ob_get_clean();
	}

	/**
	 * Native classic and Blocks confirmation hook, after order access checks.
	 *
	 * @param int $order_id Native order ID.
	 */
	public static function thankyou( $order_id ) {
		if ( ! Referrals::enabled() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( array( 'pending', 'on-hold', 'processing', 'completed' ) ) ) {
			return;
		}
		if ( is_user_logged_in() && (int) $order->get_customer_id() !== get_current_user_id() ) {
			return;
		}
		echo '<section class="pc-referral-confirmation"><h2>' . esc_html__( 'Give friends $2 off. Earn $2 in shopping credit.', 'parameter-commerce' ) . '</h2><p>' . esc_html__( 'Share your link with a friend. They get $2 off their first order, and you earn $2 in shopping credit when that order is completed.', 'parameter-commerce' ) . '</p>';
		echo self::panel(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped renderer.
		$url = self::url();
		if ( $url ) {
			echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'How Refer & Earn works', 'parameter-commerce' ) . '</a></p>';
		}
		echo '</section>';
	}
}
