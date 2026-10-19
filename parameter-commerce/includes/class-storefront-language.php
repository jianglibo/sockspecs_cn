<?php
/**
 * Explicit storefront language preference; staff locale stays user-controlled.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lightweight English / Simplified Chinese interface switching. */
final class Storefront_Language {
	/** Register before translation files are loaded. @return void */
	public static function register_hooks() {
		add_filter( 'locale', array( self::class, 'locale' ) );
		add_filter( 'determine_locale', array( self::class, 'locale' ) );
		add_action( 'init', array( self::class, 'remember' ), 1 );
		add_action( 'template_redirect', array( self::class, 'uncached' ) );
		add_action( 'wp_body_open', array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/** Do not change staff, CLI, cron, login or generic API locales. @return bool */
	private static function applies() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			return false;
		}
		if ( is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identifies the existing read-only public endpoint; grants no access.
			return wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'pc_specifications' === $_REQUEST['action'];
		}
		return true;
	}

	/** Only fixed language codes are accepted from query/cookie inputs. @return string */
	private static function choice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display preference, not a privileged state change.
		$query = isset( $_GET['pc_lang'] ) && is_string( $_GET['pc_lang'] ) ? sanitize_key( wp_unslash( $_GET['pc_lang'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$cookie = isset( $_COOKIE['pc_language'] ) && is_string( $_COOKIE['pc_language'] ) ? sanitize_key( wp_unslash( $_COOKIE['pc_language'] ) ) : '';
		return in_array( $query, array( 'en', 'zh' ), true ) ? $query : ( in_array( $cookie, array( 'en', 'zh' ), true ) ? $cookie : '' );
	}

	/**
	 * Apply explicit visitor preferences without changing WordPress settings.
	 *
	 * @param string $locale Original WordPress locale.
	 * @return string
	 */
	public static function locale( $locale ) {
		if ( ! self::applies() ) {
			return $locale;
		}
		$choice = self::choice();
		return $choice ? ( 'zh' === $choice ? 'zh_CN' : 'en_US' ) : $locale;
	}

	/** Save an explicit choice in a host-only session cookie. @return void */
	public static function remember() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Non-sensitive display preference selected by the visitor.
		if ( ! self::applies() || ! isset( $_GET['pc_lang'] ) || headers_sent() ) {
			return;
		}
		$choice = self::choice();
		if ( ! $choice ) {
			return;
		}
		setcookie(
			'pc_language',
			$choice,
			array(
				'expires'  => 0,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/** Prevent shared caches from mixing visitor language choices. @return void */
	public static function uncached() {
		if ( self::applies() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/** Small standalone style also works without WooCommerce. @return void */
	public static function assets() {
		wp_enqueue_style( 'pc-language', plugins_url( 'assets/language.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.3.1' );
	}

	/** Render ordinary links on the same page, retaining its other query arguments. @return void */
	public static function render() {
		$active = 'zh_CN' === determine_locale() ? 'zh' : 'en';
		echo '<nav class="pc-language-switcher" aria-label="Language / 语言">';
		foreach ( array(
			'en' => 'English',
			'zh' => '中文',
		) as $code => $label ) {
			echo '<a href="' . esc_url( add_query_arg( 'pc_lang', $code ) ) . '" lang="' . esc_attr( 'zh' === $code ? 'zh-CN' : 'en' ) . '"' . ( $active === $code ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}
}
