<?php
/**
 * First-purchase referrals and non-withdrawable shopping credit.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native coupon integration; WooCommerce retains orders, taxes and payments. */
final class Referrals {
	/** Domestic first-order benefit, in fen, for each side. */
	const REWARD_FEN = 500;

	/** Require this independent domestic installation. */
	public static function domestic() {
		return defined( 'DB_NAME' ) && 'sockspecs_cn' === DB_NAME &&
			in_array( home_url(), array( 'https://sockspecs.cn', 'http://127.0.0.1:8089' ), true );
	}
	/** Register inert hooks; an explicit migration and option enable the program. */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'menu' ) );
		add_action( 'woocommerce_account_referrals_endpoint', array( self::class, 'account' ) );
		add_action( 'template_redirect', array( self::class, 'requests' ), 20 );
		add_filter( 'the_content', array( self::class, 'checkout_panel' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( self::class, 'apply_pending' ), 30 );
		add_action( 'woocommerce_removed_coupon', array( self::class, 'removed' ) );
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::class, 'coupon' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'valid' ), 10, 2 );
		add_filter( 'woocommerce_cart_totals_coupon_label', array( self::class, 'label' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'reserve' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'reserve_blocks' ), 20 );
		add_action( 'woocommerce_before_pay_action', array( self::class, 'reserve' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'changed' ), 20 );
		add_action( 'woocommerce_payment_complete', array( self::class, 'changed' ), 20 );
		add_action( 'woocommerce_order_refunded', array( self::class, 'changed' ), 20 );
		add_action( 'sscn_referrals_reconcile', array( self::class, 'reconcile' ) );
		// This recovery sweep is short and only processes existing local order IDs.
		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) );
		add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
	}

	/**
	 * Financial storage is available independently of new-referral enrollment.
	 *
	 * @return bool
	 */
	public static function ready() {
		return self::domestic() && Plugin::has_woocommerce() && 1 === (int) get_option( 'sscn_referrals_schema' );
	}

	/**
	 * Only the operator-approved CNY program is available for new checkout use.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return self::ready() && 'yes' === get_option( 'sscn_referrals_enabled' ) && 'CNY' === get_woocommerce_currency() && 2 === wc_get_price_decimals();
	}

	/**
	 * Register with WooCommerce's account endpoint dispatcher.
	 *
	 * @param array $vars Native endpoints.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars['referrals'] = 'referrals';
		return $vars;
	}

	/** Register account endpoint (flush only during explicit installation). */
	public static function endpoint() {
		add_rewrite_endpoint( 'referrals', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add a private account menu item.
	 *
	 * @param array $items Native links.
	 * @return array
	 */
	public static function menu( $items ) {
		if ( self::ready() ) {
			$logout = $items['customer-logout'] ?? null;
			unset( $items['customer-logout'] );
			$items['referrals'] = __( 'Referrals & credit', 'parameter-commerce' );
			if ( $logout ) {
				$items['customer-logout'] = $logout;
			}
		}
		return $items;
	}

	/**
	 * Signed share code. It contains no email or other customer profile data.
	 *
	 * @param int $user Account ID.
	 * @return string
	 */
	public static function code( $user ) {
		return 'sscn-ref-' . $user . '-' . substr( hash_hmac( 'sha256', 'sockspecs-cn-referral:' . $user, wp_salt( 'auth' ) ), 0, 20 );
	}

	/**
	 * Resolve a genuine referral code.
	 *
	 * @param string $code Submitted code.
	 * @return int
	 */
	public static function referrer( $code ) {
		if ( preg_match( '/^sscn-ref-([1-9][0-9]*)-[a-f0-9]{20}$/D', $code, $match ) ) {
			$id = (int) $match[1];
			if ( hash_equals( self::code( $id ), $code ) && get_userdata( $id ) ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Amount available to this session, including its own unpaid retry reservation.
	 *
	 * @return int
	 */
	public static function available() {
		$user = get_current_user_id();
		if ( ! $user || ! self::ready() ) {
			return 0;
		}
		$balance = Referral_Store::balance( $user );
		$retry   = self::retry_order();
		$row     = $retry ? Referral_Store::record( $retry ) : null;
		$order   = $retry ? wc_get_order( $retry ) : null;
		if ( $row && $order && (int) $row['user_id'] === $user && 'reserved' === $row['state'] && $order->has_status( 'pending' ) && self::same_items( $order ) ) {
			$balance += (int) $row['debit'];
		}
		return max( 0, $balance );
	}

	/**
	 * Use the current native checkout's private retry order.
	 *
	 * @return int
	 */
	private static function retry_order() {
		if ( ! WC()->session ) {
			return 0;
		}
		$classic = (int) WC()->session->get( 'order_awaiting_payment' );
		$blocks  = (int) WC()->session->get( 'store_api_draft_order' );
		return WC()->is_store_api_request() ? $blocks : ( $classic ? $classic : $blocks );
	}

	/**
	 * Only reuse a reservation for the same native product quantities.
	 *
	 * @param \WC_Order $order Pending native order.
	 * @return bool
	 */
	private static function same_items( $order ) {
		if ( ! WC()->cart ) {
			return false;
		}
		$cart_items  = array();
		$order_items = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$id                = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			$cart_items[ $id ] = ( $cart_items[ $id ] ?? 0 ) + $item['quantity'];
		}
		foreach ( $order->get_items() as $item ) {
			$id                 = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$order_items[ $id ] = ( $order_items[ $id ] ?? 0 ) + $item->get_quantity();
		}
		ksort( $cart_items );
		ksort( $order_items );
		return $cart_items === $order_items;
	}

	/**
	 * Native virtual fixed-cart coupons; never create a negative fee or cash wallet.
	 *
	 * @param mixed  $data Earlier filter data.
	 * @param string $code Coupon code.
	 * @return mixed
	 */
	public static function coupon( $data, $code ) {
		if ( ! self::enabled() ) {
			return $data;
		}
		if ( 'sscn-credit' === $code || self::referrer( $code ) ) {
			return array(
				'discount_type'  => 'fixed_cart',
				'amount'         => 'sscn-credit' === $code ? self::available() / 100 : self::REWARD_FEN / 100,
				'individual_use' => false,
				'free_shipping'  => false,
			);
		}
		return $data;
	}

	/**
	 * Validate coupon visibility; final billing eligibility is checked under lock.
	 *
	 * @param bool       $valid Native validity.
	 * @param \WC_Coupon $coupon Coupon.
	 * @return bool
	 * @throws \Exception Invalid referral/credit use.
	 */
	public static function valid( $valid, $coupon ) {
		$code = $coupon->get_code();
		if ( 'sscn-credit' !== $code && ! str_starts_with( $code, 'sscn-ref-' ) ) {
			return $valid;
		}
		if ( ! self::enabled() || ( 'sscn-credit' === $code && ! self::available() ) ) {
			throw new \Exception( esc_html__( 'Sign in with an available CNY shopping credit balance to use this reward.', 'parameter-commerce' ) );
		}
		if ( 'sscn-credit' === $code ) {
			return $valid;
		}
		$id    = self::referrer( $code );
		$user  = get_current_user_id();
		$email = WC()->customer ? WC()->customer->get_billing_email() : '';
		$retry = self::retry_order();
		if ( ! $id || $id === $user || ( $email && ( strtolower( get_userdata( $id )->user_email ) === strtolower( $email ) || ! Referral_Store::first_order( $email, $user, $retry ) ) ) ) {
			throw new \Exception( esc_html__( 'This referral offer is for a new customer’s first order only. Self-referrals are not eligible.', 'parameter-commerce' ) );
		}
		foreach ( WC()->cart ? WC()->cart->get_applied_coupons() : array() as $applied ) {
			if ( $code !== $applied && str_starts_with( $applied, 'sscn-ref-' ) ) {
				throw new \Exception( esc_html__( 'Only one referral can be used for your first order.', 'parameter-commerce' ) );
			}
		}
		return $valid;
	}

	/**
	 * Friendly classic checkout label; Blocks retain native coupon code support.
	 *
	 * @param string     $label Native label.
	 * @param \WC_Coupon $coupon Coupon.
	 * @return string
	 */
	public static function label( $label, $coupon ) {
		return 'sscn-credit' === $coupon->get_code() ? __( 'Shopping credit', 'parameter-commerce' ) : ( self::referrer( $coupon->get_code() ) ? __( 'First-order referral discount', 'parameter-commerce' ) : $label );
	}

	/** Handle first-party share links and nonce-protected reward form submissions. */
	public static function requests() {
		if ( ! self::enabled() || ! WC()->session || ! WC()->cart ) {
			return;
		}
		// A referral link only selects a nonfinancial discount. Final eligibility is server checked.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['sscn_ref'] ) && is_string( $_GET['sscn_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['sscn_ref'] ) ) : '';
		if ( $code && self::referrer( $code ) ) {
			WC()->session->set_customer_session_cookie( true );
			WC()->session->set( 'sscn_referral_code', $code );
			self::apply_pending();
			wp_safe_redirect( remove_query_arg( 'sscn_ref' ) );
			exit;
		}
		if ( empty( $_POST['pc_reward_action'] ) || ! is_string( $_POST['pc_reward_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		check_admin_referer( 'pc_rewards', 'pc_rewards_nonce' );
		$action = sanitize_key( wp_unslash( $_POST['pc_reward_action'] ) );
		if ( 'credit' === $action ) {
			WC()->cart->apply_coupon( 'sscn-credit' );
		} elseif ( 'remove' === $action ) {
			WC()->cart->remove_coupon( 'sscn-credit' );
		} elseif ( 'referral' === $action && isset( $_POST['sscn_referral_code'] ) && is_string( $_POST['sscn_referral_code'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_POST['sscn_referral_code'] ) );
			if ( self::referrer( $code ) ) {
				WC()->cart->apply_coupon( $code );
			} else {
				wc_add_notice( __( 'Please enter a valid referral code.', 'parameter-commerce' ), 'error' );
			}
		}
		WC()->cart->calculate_totals();
		wp_safe_redirect( is_checkout() ? wc_get_checkout_url() : wc_get_cart_url() );
		exit;
	}

	/** Apply a remembered share code when products first enter the cart. */
	public static function apply_pending() {
		if ( self::enabled() && WC()->session && WC()->cart && ! WC()->cart->is_empty() ) {
			$code = WC()->session->get( 'sscn_referral_code' );
			if ( is_string( $code ) && self::referrer( $code ) && ! WC()->cart->has_discount( $code ) ) {
				WC()->cart->apply_coupon( $code );
			}
		}
	}

	/**
	 * Respect explicit coupon removal.
	 *
	 * @param string $code Removed coupon.
	 */
	public static function removed( $code ) {
		if ( WC()->session && WC()->session->get( 'sscn_referral_code' ) === $code ) {
			WC()->session->__unset( 'sscn_referral_code' );
		}
	}

	/**
	 * Server-rendered controls work with both native checkout layouts.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function checkout_panel( $content ) {
		if ( ! self::enabled() || ! in_the_loop() || ! is_main_query() || ! ( is_cart() || is_checkout() ) || is_wc_endpoint_url() || ! WC()->cart || WC()->cart->is_empty() ) {
			return $content;
		}
		ob_start();
		echo '<section class="woocommerce-info" aria-label="' . esc_attr__( 'Referrals and shopping credit', 'parameter-commerce' ) . '">';
		if ( is_user_logged_in() ) {
			$available = self::available();
			echo '<p>' . esc_html__( 'Available shopping credit:', 'parameter-commerce' ) . ' ' . wp_kses_post( wc_price( $available / 100 ) ) . '</p>';
			if ( $available || WC()->cart->has_discount( 'sscn-credit' ) ) {
				echo '<form method="post">';
				wp_nonce_field( 'pc_rewards', 'pc_rewards_nonce' );
				$applied = WC()->cart->has_discount( 'sscn-credit' );
				echo '<button class="button" name="pc_reward_action" value="' . ( $applied ? 'remove' : 'credit' ) . '">' . esc_html( $applied ? __( 'Remove shopping credit', 'parameter-commerce' ) : __( 'Apply shopping credit', 'parameter-commerce' ) ) . '</button></form>';
			}
		} else {
			echo '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Sign in to use shopping credit', 'parameter-commerce' ) . '</a></p>';
		}
		echo '<details><summary>' . esc_html__( 'Have a referral code? Get ¥5 off your first order.', 'parameter-commerce' ) . '</summary><form method="post">';
		wp_nonce_field( 'pc_rewards', 'pc_rewards_nonce' );
		echo '<label>' . esc_html__( 'Referral code', 'parameter-commerce' ) . ' <input name="sscn_referral_code" type="text" required maxlength="80"></label> <button class="button" name="pc_reward_action" value="referral">' . esc_html__( 'Apply referral', 'parameter-commerce' ) . '</button></form></details></section>';
		return ob_get_clean() . $content;
	}

	/** Render only the authenticated customer's own balance and share link. */
	public static function account() {
		if ( ! self::ready() || ! is_user_logged_in() ) {
			return;
		}
		nocache_headers();
		$user    = get_current_user_id();
		$balance = Referral_Store::balance( $user );
		echo '<h2>' . esc_html__( 'Referrals & shopping credit', 'parameter-commerce' ) . '</h2><p>' . esc_html__( 'Available credit:', 'parameter-commerce' ) . ' <strong>' . wp_kses_post( wc_price( max( 0, $balance ) / 100, array( 'currency' => 'CNY' ) ) ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Give a friend ¥5 off their first order. Earn ¥5 in shopping credit when that order is completed. Credit accumulates, has no expiry and can cover the full product total on any SockSpecs products. It cannot be withdrawn or exchanged for cash.', 'parameter-commerce' ) . '</p>';
		echo '<p>' . esc_html__( 'One reward per new customer. Self-referrals do not qualify. Cancelled or fully refunded orders reverse the reward. Partial refunds keep the referral reward while a purchase remains. Credit used on refunded purchases is returned proportionally as credit. If a reversed reward has already been spent, future rewards first cover the adjustment.', 'parameter-commerce' ) . '</p>';
		if ( self::enabled() ) {
			$code = self::code( $user );
			echo '<p><label>' . esc_html__( 'Your referral link', 'parameter-commerce' ) . '<input type="text" readonly style="width:100%" value="' . esc_attr( add_query_arg( 'sscn_ref', $code, home_url( '/' ) ) ) . '"></label></p><p>' . esc_html__( 'Your referral code:', 'parameter-commerce' ) . ' <code>' . esc_html( $code ) . '</code></p>';
		} else {
			echo '<p>' . esc_html__( 'The referral program is currently paused. Your recorded credit is preserved.', 'parameter-commerce' ) . '</p>';
		}
		if ( $balance < 0 ) {
			echo '<p>' . esc_html__( 'A reversed reward is being offset against future credit.', 'parameter-commerce' ) . ' ' . wp_kses_post( wc_price( abs( $balance ) / 100, array( 'currency' => 'CNY' ) ) ) . '</p>';
		}
		echo '<h3>' . esc_html__( 'Recent activity', 'parameter-commerce' ) . '</h3><table class="shop_table"><thead><tr><th>' . esc_html__( 'Date (UTC)', 'parameter-commerce' ) . '</th><th>' . esc_html__( 'Activity', 'parameter-commerce' ) . '</th><th>' . esc_html__( 'Amount', 'parameter-commerce' ) . '</th></tr></thead><tbody>';
		$labels = array(
			'credit_reserved'   => __( 'Credit applied to an order', 'parameter-commerce' ),
			'credit_returned'   => __( 'Credit returned', 'parameter-commerce' ),
			'credit_reapplied'  => __( 'Order credit adjustment', 'parameter-commerce' ),
			'referral_earned'   => __( 'Referral reward', 'parameter-commerce' ),
			'referral_reversed' => __( 'Referral reward reversed', 'parameter-commerce' ),
		);
		foreach ( Referral_Store::history( $user ) as $entry ) {
			echo '<tr><td>' . esc_html( $entry['created'] ) . '</td><td>' . esc_html( $labels[ $entry['reason'] ] ?? __( 'Credit adjustment', 'parameter-commerce' ) ) . '</td><td>' . wp_kses_post( wc_price( $entry['delta'] / 100, array( 'currency' => 'CNY' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Final pre-payment validation and reservation using persisted native totals.
	 *
	 * @param \WC_Order $order Checkout or order-pay order.
	 * @throws \RuntimeException Invalid or stale referral/credit amounts.
	 */
	public static function reserve( $order ) {
		$credit   = 0;
		$referrer = 0;
		$count    = 0;
		$special  = false;
		foreach ( $order->get_items( 'coupon' ) as $item ) {
			$code = $item->get_code();
			if ( 'sscn-credit' === $code ) {
				$credit += (int) round( (float) $item->get_discount() * 100 );
				$special = true;
			} elseif ( str_starts_with( $code, 'sscn-ref-' ) ) {
				++$count;
				$special  = true;
				$referrer = self::referrer( $code );
				if ( ! $referrer || (float) $item->get_discount() > self::REWARD_FEN / 100 + 0.001 ) {
					throw new \RuntimeException( esc_html__( 'Invalid referral discount. Please rebuild your cart.', 'parameter-commerce' ) );
				}
			}
		}
		if ( ! $special ) {
			if ( self::ready() && Referral_Store::record( $order->get_id() ) ) {
				throw new \RuntimeException( esc_html__( 'Cancel the previous reward order before changing its discounts.', 'parameter-commerce' ) );
			}
			return;
		}
		if ( ! self::enabled() || $count > 1 || ( $credit && get_current_user_id() !== $order->get_customer_id() ) ) {
			throw new \RuntimeException( esc_html__( 'This reward cannot be used for this order. Please sign in and refresh your cart.', 'parameter-commerce' ) );
		}
		Referral_Store::reserve( $order, $credit, $referrer );
		$order->update_meta_data( '_sscn_referral_program', 'cny-first-order-v1' );
		$order->save_meta_data();
	}

	/**
	 * Convert validation failures to normal Store API checkout errors.
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Invalid reward.
	 *
	 * @param \WC_Order $order Checkout order.
	 */
	public static function reserve_blocks( $order ) {
		try {
			self::reserve( $order );
		} catch ( \RuntimeException $error ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'sscn_referral_invalid', esc_html( $error->getMessage() ), 400 );
		}
	}

	/**
	 * Event reconciliation also operates while new enrollment is paused.
	 *
	 * @param int $order_id Native order ID.
	 */
	public static function changed( $order_id ) {
		if ( ! self::ready() || ! Referral_Store::record( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		try {
			$review = Referral_Store::sync( $order );
			if ( $review && ! $order->get_meta( '_pc_credit_review' ) ) {
				$order->update_meta_data( '_pc_credit_review', 'yes' );
				$order->save_meta_data();
				$order->add_order_note( __( 'Shopping credit requires review: a reversal or late payment left a credit adjustment. Future credit offsets it; no cash is charged automatically.', 'parameter-commerce' ) );
			}
		} catch ( \Throwable $error ) {
			wc_get_logger()->error( 'Referral reconciliation failed for order ' . absint( $order_id ) . '; scheduled reconciliation will retry.', array( 'source' => 'pc-referrals' ) );
		}
	}

	/**
	 * Five-minute recovery sweep, independent of a browser session.
	 *
	 * @param array $schedules Native schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['sscn_referrals_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes (referral reconciliation)', 'parameter-commerce' ),
		);
		return $schedules;
	}

	/** Reconcile 100 recorded orders per sweep; cycle without starving old orders. */
	public static function reconcile() {
		global $wpdb;
		if ( ! self::ready() ) {
			return;
		}
		$cursor = (int) get_option( 'sscn_referrals_cursor', 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}sscn_referral_orders WHERE order_id > %d ORDER BY order_id LIMIT 100", $cursor ) );
		foreach ( $ids as $id ) {
			self::changed( (int) $id );
		}
		update_option( 'sscn_referrals_cursor', count( $ids ) === 100 ? (int) end( $ids ) : 0, false );
	}

	/** Add a read-only staff overview. */
	public static function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'Referral credit', 'parameter-commerce' ), __( 'Referral credit', 'parameter-commerce' ), 'manage_woocommerce', 'pc-referral-credit', array( self::class, 'admin' ) );
	}

	/** Display balances without exposing one customer's details to another. */
	public static function admin() {
		global $wpdb;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::ready() ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Referral credit', 'parameter-commerce' ) . '</h1><p>' . esc_html__( 'CNY: ¥5 off a referred first order; ¥5 credit after completion. No expiry or cash withdrawal. Mark an order Completed after delivery to release its reward. Balances include pending checkout reservations. Negative balances offset future rewards.', 'parameter-commerce' ) . '</p>';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT user_id,SUM(delta) AS balance FROM {$wpdb->prefix}sscn_referral_ledger GROUP BY user_id ORDER BY user_id LIMIT 200", ARRAY_A );
		echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Customer', 'parameter-commerce' ) . '</th><th>' . esc_html__( 'Balance (CNY)', 'parameter-commerce' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><a href="' . esc_url( get_edit_user_link( $row['user_id'] ) ) . '">' . esc_html( $row['user_id'] ) . '</a></td><td>' . wp_kses_post( wc_price( $row['balance'] / 100, array( 'currency' => 'CNY' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
