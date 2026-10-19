<?php
/**
 * Google customer identity, bound to native WooCommerce accounts.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

use ParameterCommerce\Vendor\GoogleJWT\JWT;
use ParameterCommerce\Vendor\GoogleJWT\Key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'JWTExceptionWithPayloadInterface', 'BeforeValidException', 'ExpiredException', 'SignatureInvalidException', 'Key', 'JWT' ) as $pc_jwt_file ) {
	require_once dirname( __DIR__ ) . '/lib/google-jwt/' . $pc_jwt_file . '.php';
}
unset( $pc_jwt_file );

/** Customer-only sign-in. No access/refresh tokens or client secrets are needed. */
final class Google_Login {
	const META = '_pc_google_subject';
	const KEYS = 'pc_google_public_keys';

	/** Register endpoints without depending on WooCommerce during bootstrap. */
	public static function register_hooks() {
		foreach ( array(
			'pc_google_start'  => 'start',
			'pc_google_finish' => 'finish',
		) as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( self::class, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( self::class, $method ) );
		}
		add_action( 'wp_ajax_pc_google_disconnect', array( self::class, 'disconnect' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'woocommerce_before_customer_login_form', array( self::class, 'button' ) );
		add_action( 'woocommerce_before_checkout_form', array( self::class, 'button' ), 5 );
		add_filter( 'render_block_woocommerce/checkout', array( self::class, 'checkout_block' ) );
		add_action( 'woocommerce_edit_account_form_end', array( self::class, 'account' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'erasers' ) );
	}

	/** Get the enabled public client ID. @return string */
	public static function client_id() {
		$id = get_option( 'pc_google_client_id', '' );
		return Plugin::has_woocommerce() && 'yes' === get_option( 'pc_google_login_enabled' ) && is_string( $id ) && preg_match( '/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/D', $id ) ? $id : '';
	}

	/** Get the exact site origin, including any local port. @return string */
	public static function origin() {
		$p = wp_parse_url( home_url( '/' ) );
		return $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	/** Name the host-only browser binding cookie. @return string */
	public static function cookie_name() {
		return is_ssl() ? '__Host-pc_google' : 'pc_google_local';
	}

	/**
	 * Exclude staff accounts from customer authentication.
	 *
	 * @param \WP_User|false $user Candidate user.
	 * @return bool
	 */
	public static function customer( $user ) {
		return $user instanceof \WP_User && $user->exists() && ! array_diff( $user->roles, array( 'customer', 'subscriber' ) ) && count( $user->roles ) > 0 && ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'manage_woocommerce' );
	}

	/** Same-origin POST only, no CORS and no tokens in URLs or logs. */
	private static function guard() {
		nocache_headers();
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( ! self::client_id() || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || self::origin() !== $origin || ( 'production' === wp_get_environment_type() && ! is_ssl() ) ) {
			wp_send_json_error( array( 'message' => __( 'Google sign-in is unavailable. Please use email sign-in.', 'parameter-commerce' ) ), 403 );
		}
		// Bound work per source address; never persist a plain IP address.
		$key   = 'pc_google_rate_' . hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ), wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );
		if ( $count >= 60 ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a minute before trying Google sign-in again.', 'parameter-commerce' ) ), 429 );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	}

	/** Begin a five-minute browser- and session-bound, one-use challenge. */
	public static function start() {
		self::guard();
		$cookie = isset( $_COOKIE[ self::cookie_name() ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::cookie_name() ] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $cookie ) ) {
			$cookie = bin2hex( random_bytes( 32 ) );
		}
		setcookie(
			self::cookie_name(),
			$cookie,
			array(
				'expires'  => time() + 600,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
		$nonce = bin2hex( random_bytes( 32 ) );
		$state = array(
			'nonce'   => $nonce,
			'user'    => get_current_user_id(),
			'session' => hash( 'sha256', wp_get_session_token() ),
			'expires' => time() + 300,
		);
		set_transient( 'pc_google_state_' . hash( 'sha256', $cookie ), $state, 300 );
		wp_send_json_success(
			array(
				'nonce'    => $nonce,
				'clientId' => self::client_id(),
			)
		);
	}

	/**
	 * Get Google's public certificates with bounded requests and cache lifetime.
	 *
	 * @return array|\WP_Error RS256 verification keys.
	 */
	public static function keys() {
		$certs = get_transient( self::KEYS );
		if ( false === $certs ) {
			if ( get_transient( 'pc_google_keys_backoff' ) ) {
				return new \WP_Error( 'google_keys', __( 'Google sign-in is temporarily unavailable. Please try email sign-in.', 'parameter-commerce' ) );
			}
			set_transient( 'pc_google_keys_backoff', 1, 30 );
			$response = wp_remote_get(
				'https://www.googleapis.com/oauth2/v1/certs',
				array(
					'timeout'             => 8,
					'redirection'         => 0,
					'limit_response_size' => 65536,
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return new \WP_Error( 'google_keys', __( 'Google sign-in is temporarily unavailable. Please try email sign-in.', 'parameter-commerce' ) );
			}
			$certs = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $certs ) || ! $certs || count( $certs ) > 10 ) {
				return new \WP_Error( 'google_keys', __( 'Google sign-in is temporarily unavailable. Please try email sign-in.', 'parameter-commerce' ) );
			}
			$ttl = 300;
			if ( preg_match( '/max-age=(\d+)/', wp_remote_retrieve_header( $response, 'cache-control' ), $match ) ) {
				$ttl = min( 21600, max( 1, (int) $match[1] ) );
			}
			set_transient( self::KEYS, $certs, $ttl );
		}
		$keys = array();
		foreach ( $certs as $kid => $pem ) {
			if ( ! is_string( $pem ) || ! str_contains( $pem, '-----BEGIN CERTIFICATE-----' ) ) {
				return new \WP_Error( 'google_keys', __( 'Google sign-in is temporarily unavailable. Please try email sign-in.', 'parameter-commerce' ) );
			}
			$keys[ $kid ] = new Key( $pem, 'RS256' );
		}
		return $keys;
	}

	/**
	 * Verify signature and every required ID-token claim before using identity.
	 *
	 * @param string $token Google's ID token.
	 * @param string $nonce Expected browser challenge.
	 * @return array|\WP_Error
	 */
	public static function verify( $token, $nonce ) {
		$error = new \WP_Error( 'google_identity', __( 'Google sign-in could not be verified. Please try again or use email sign-in.', 'parameter-commerce' ) );
		if ( ! is_string( $token ) || strlen( $token ) > 12000 || ! preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token ) ) {
			return $error;
		}
		$keys = self::keys();
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}
		try {
			$claims = (array) JWT::decode( $token, $keys );
		} catch ( \Throwable $exception ) {
			return $error;
		}
		if ( ! in_array( $claims['iss'] ?? '', array( 'https://accounts.google.com', 'accounts.google.com' ), true ) || self::client_id() !== ( $claims['aud'] ?? '' ) || ( isset( $claims['azp'] ) && self::client_id() !== $claims['azp'] ) || ! is_int( $claims['exp'] ?? null ) || $claims['exp'] <= time() || ! is_int( $claims['iat'] ?? null ) || $claims['iat'] > time() || time() - 600 > $claims['iat'] || ! is_string( $claims['nonce'] ?? null ) || ! hash_equals( $nonce, $claims['nonce'] ) || ! is_string( $claims['sub'] ?? null ) || '' === $claims['sub'] || strlen( $claims['sub'] ) > 255 || true !== ( $claims['email_verified'] ?? false ) || ! is_string( $claims['email'] ?? null ) || ! is_email( $claims['email'] ) ) {
			return $error;
		}
		return $claims;
	}

	/**
	 * Resolve a verified identity. Caller holds the global identity mutation lock.
	 * Existing accounts ALWAYS require an authenticated linking step, even Gmail.
	 *
	 * @param array $claims Verified Google claims.
	 * @param int   $current Current signed-in user, zero for sign-in/registration.
	 * @return int|\WP_Error Native WooCommerce customer ID.
	 */
	public static function resolve( $claims, $current ) {
		$subject = hash( 'sha256', $claims['sub'] );
		$owners  = get_users(
			array(
				'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact serialized identity lookup.
				'meta_value' => $subject, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact serialized identity lookup.
				'number'     => 2,
				'fields'     => 'ID',
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery -- Private exact identity lookup, serialized and bounded.
		$error   = new \WP_Error( 'google_link', __( 'Please sign in with your existing SockSpecs account, then connect Google under Account details. You can reset your password if needed.', 'parameter-commerce' ) );
		if ( count( $owners ) > 1 ) {
			return $error;
		}
		$owner = $owners ? (int) $owners[0] : 0;
		if ( $current ) {
			$user = get_userdata( $current );
			if ( ! self::customer( $user ) || ( $owner && $owner !== $current ) || strtolower( $user->user_email ) !== strtolower( $claims['email'] ) ) {
				return new \WP_Error( 'google_link', __( 'Use the Google account with the same email address as your SockSpecs account. Staff accounts use the existing sign-in method.', 'parameter-commerce' ) );
			}
			$existing = get_user_meta( $current, self::META, true );
			if ( $existing && ! hash_equals( $existing, $subject ) ) {
				return new \WP_Error( 'google_link', __( 'Disconnect the current Google account before connecting another.', 'parameter-commerce' ) );
			}
			$owner = $current;
		} elseif ( $owner ) {
			if ( ! self::customer( get_userdata( $owner ) ) ) {
				return $error;
			}
			return $owner;
		} else {
			if ( email_exists( $claims['email'] ) || 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) {
				return $error;
			}
			// Google may not control ownership of third-party email addresses.
			if ( ! str_ends_with( strtolower( $claims['email'] ), '@gmail.com' ) && empty( $claims['hd'] ) ) {
				return new \WP_Error( 'google_email', __( 'Please create an account using email first, then connect Google under Account details.', 'parameter-commerce' ) );
			}
			$owner = wc_create_new_customer(
				$claims['email'],
				'',
				'',
				array(
					'first_name' => sanitize_text_field( $claims['given_name'] ?? '' ),
					'last_name'  => sanitize_text_field( $claims['family_name'] ?? '' ),
					'source'     => 'google',
				)
			);
			if ( is_wp_error( $owner ) || ! self::customer( get_userdata( $owner ) ) ) {
				return $error;
			}
		}
		update_user_meta( $owner, self::META, $subject );
		return get_user_meta( $owner, self::META, true ) === $subject ? $owner : $error;
	}

	/**
	 * Consume a browser challenge and resolve identity under one named DB lock.
	 *
	 * @param string $cookie Browser secret, never included in JavaScript.
	 * @param string $token Signed ID token.
	 * @return int|\WP_Error
	 */
	public static function complete( $cookie, $token ) {
		global $wpdb;
		$error = new \WP_Error( 'google_state', __( 'Your sign-in session expired. Please refresh this page and try again.', 'parameter-commerce' ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $cookie ) ) {
			return $error;
		}
		$lock = 'pc_google_' . substr( hash( 'sha256', DB_NAME . $wpdb->prefix ), 0, 40 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic identity ownership and challenge consumption.
			return $error;
		}
		try {
			$key   = 'pc_google_state_' . hash( 'sha256', $cookie );
			$state = get_transient( $key );
			delete_transient( $key );
			if ( ! is_array( $state ) || time() > $state['expires'] || get_current_user_id() !== $state['user'] || ! hash_equals( $state['session'], hash( 'sha256', wp_get_session_token() ) ) ) {
				return $error;
			}
			$claims = self::verify( $token, $state['nonce'] );
			return is_wp_error( $claims ) ? $claims : self::resolve( $claims, get_current_user_id() );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release owned identity lock.
		}
	}

	/** Complete sign-in using native cookies, cart/session handlers and login hook. */
	public static function finish() {
		self::guard();
		$cookie = isset( $_COOKIE[ self::cookie_name() ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::cookie_name() ] ) ) : '';
		$token  = isset( $_POST['credential'] ) && is_string( $_POST['credential'] ) ? wp_unslash( $_POST['credential'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cryptographically verified with a single-use browser-bound nonce in complete().
		$result = self::complete( $cookie, $token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		if ( ! is_user_logged_in() ) {
			wc_set_customer_auth_cookie( $result );
			$user = get_userdata( $result );
			do_action( 'wp_login', $user->user_login, $user );
		}
		wp_send_json_success( array( 'message' => __( 'Google connected. Your orders and shopping credit stay with this account.', 'parameter-commerce' ) ) );
	}

	/** Disconnect only with native authenticated nonce; orders/credit are untouched. */
	public static function disconnect() {
		self::guard();
		check_ajax_referer( 'pc_google_disconnect' );
		if ( ! self::customer( wp_get_current_user() ) ) {
			wp_send_json_error( array( 'message' => __( 'Customer account required.', 'parameter-commerce' ) ), 403 );
		}
		delete_user_meta( get_current_user_id(), self::META );
		wp_send_json_success( array( 'message' => __( 'Google disconnected. Use email sign-in or reset your password next time.', 'parameter-commerce' ) ) );
	}

	/** Small assets on account and checkout screens only. */
	public static function assets() {
		if ( ! self::client_id() || ! ( is_account_page() || is_checkout() ) || is_order_received_page() ) {
			return;
		}
		$base = plugin_dir_url( dirname( __DIR__ ) . '/parameter-commerce.php' );
		wp_enqueue_style( 'pc-google-login', $base . 'assets/google-login.css', array(), '0.5.14' );
		wp_enqueue_script( 'pc-google-login', $base . 'assets/google-login.js', array(), '0.5.15', true );
		wp_localize_script(
			'pc-google-login',
			'pcGoogleLogin',
			array(
				'url'             => admin_url( 'admin-ajax.php' ),
				'error'           => __( 'Google sign-in is unavailable. Please use email sign-in or try again.', 'parameter-commerce' ),
				'retry'           => __( 'Reload Google sign-in', 'parameter-commerce' ),
				'expired'         => __( 'For your security, please reload Google sign-in to continue.', 'parameter-commerce' ),
				'loading'         => __( 'Loading Google sign-in…', 'parameter-commerce' ),
				'disconnectNonce' => wp_create_nonce( 'pc_google_disconnect' ),
			)
		);
	}

	/** Optional official Google button with an email fallback; no automatic prompts. */
	public static function button() {
		if ( ! self::client_id() || is_user_logged_in() ) {
			return;
		}
		self::panel();
	}

	/** Render the shared customer sign-in container. */
	private static function panel() {
		$privacy = get_privacy_policy_url();
		$logo    = plugin_dir_url( dirname( __DIR__ ) . '/parameter-commerce.php' ) . 'assets/google-g.png';
		echo '<div class="pc-google-login"><button type="button" class="button pc-google-load"><img class="pc-google-logo" src="' . esc_url( $logo ) . '" width="20" height="20" alt="" aria-hidden="true" /><span>' . esc_html__( 'Sign in with Google', 'parameter-commerce' ) . '</span></button><div class="pc-google-button"></div><p class="pc-google-status" role="status" aria-live="polite"></p><p class="pc-google-help">' . esc_html__( 'Google shares your name and email to sign you in. New here? We’ll create a SockSpecs customer account. Your existing email sign-in remains available.', 'parameter-commerce' ) . '</p><noscript>' . esc_html__( 'Please use email sign-in when JavaScript is disabled.', 'parameter-commerce' ) . '</noscript>';
		if ( $privacy ) {
			echo '<p class="pc-google-help"><a href="' . esc_url( $privacy ) . '">' . esc_html__( 'Privacy policy', 'parameter-commerce' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Block checkout does not run classic before-checkout hooks.
	 *
	 * @param string $content Native checkout block.
	 * @return string
	 */
	public static function checkout_block( $content ) {
		if ( ! self::client_id() || is_user_logged_in() || ! is_checkout() || is_order_received_page() ) {
			return $content;
		}
		ob_start();
		self::button();
		return ob_get_clean() . $content;
	}

	/** Account details: explicit link or unlink; no nested form. */
	public static function account() {
		if ( ! self::client_id() || ! self::customer( wp_get_current_user() ) ) {
			return;
		}
		echo '<fieldset class="pc-google-account"><legend>' . esc_html__( 'Google sign-in', 'parameter-commerce' ) . '</legend>';
		if ( get_user_meta( get_current_user_id(), self::META, true ) ) {
			echo '<p>' . esc_html__( 'Google is connected to this account. Your orders and shopping credit are unchanged.', 'parameter-commerce' ) . '</p><button type="button" class="button pc-google-disconnect">' . esc_html__( 'Disconnect Google', 'parameter-commerce' ) . '</button><p class="pc-google-status" role="status" aria-live="polite"></p>';
		} else {
			echo '<p>' . esc_html__( 'Connect the Google account with the same email address as this account. Save any account changes first.', 'parameter-commerce' ) . '</p>';
			self::panel();
		}
		echo '</fieldset>';
	}

	/**
	 * Register the native privacy exporter.
	 *
	 * @param array $items Native privacy input.
	 * @return array
	 */
	public static function exporters( $items ) {
		$items['pc-google-login'] = array(
			'exporter_friendly_name' => __( 'Google sign-in', 'parameter-commerce' ),
			'callback'               => array( self::class, 'export_data' ),
		);
		return $items;
	}

	/**
	 * Export the stored connection without tokens.
	 *
	 * @param string $email Native privacy input.
	 * @return array
	 */
	public static function export_data( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user && get_user_meta( $user->ID, self::META, true ) ) {
			$data[] = array(
				'group_id'    => 'pc-google-login',
				'group_label' => __( 'Google sign-in', 'parameter-commerce' ),
				'item_id'     => 'pc-google-' . $user->ID,
				'data'        => array(
					array(
						'name'  => __( 'Google account connected', 'parameter-commerce' ),
						'value' => __( 'Yes', 'parameter-commerce' ),
					),
					array(
						'name'  => __( 'Google identity hash', 'parameter-commerce' ),
						'value' => get_user_meta( $user->ID, self::META, true ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Register the native privacy eraser.
	 *
	 * @param array $items Native privacy input.
	 * @return array
	 */
	public static function erasers( $items ) {
		$items['pc-google-login'] = array(
			'eraser_friendly_name' => __( 'Google sign-in', 'parameter-commerce' ),
			'callback'             => array( self::class, 'erase_data' ),
		);
		return $items;
	}

	/**
	 * Erase the connection while preserving native financial history.
	 *
	 * @param string $email Native privacy input.
	 * @return array
	 */
	public static function erase_data( $email ) {
		$user    = get_user_by( 'email', $email );
		$removed = $user ? delete_user_meta( $user->ID, self::META ) : false;
		return array(
			'items_removed'  => (bool) $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
