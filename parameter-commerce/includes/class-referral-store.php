<?php
/**
 * Transactional CNY referral balances. Only these plugin-owned tables are written.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Financial reads must bypass object caches. Identifiers below come only from wpdb.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/** Immutable credit entries and serialized first-purchase claims. */
final class Referral_Store {
	/**
	 * Explicit staff/CLI migration; never runs automatically in public requests.
	 *
	 * @throws \RuntimeException Invalid capability or storage engine.
	 */
	public static function install() {
		global $wpdb;
		if ( ! Referrals::domestic() || ! current_user_can( 'manage_woocommerce' ) ) {
			throw new \RuntimeException( 'Store manager required.' );
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'sscn_referral_';
		dbDelta(
			"CREATE TABLE {$prefix}ledger (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint unsigned NOT NULL,
			order_id bigint unsigned NOT NULL,
			delta bigint NOT NULL,
			reason varchar(40) NOT NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id,id),
			KEY order_id (order_id)
		) ENGINE=InnoDB $collate;"
		);
		dbDelta(
			"CREATE TABLE {$prefix}orders (
			order_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL DEFAULT 0,
			referrer_id bigint unsigned NOT NULL DEFAULT 0,
			credit bigint NOT NULL DEFAULT 0,
			debit bigint NOT NULL DEFAULT 0,
			reward bigint NOT NULL DEFAULT 0,
			state varchar(20) NOT NULL,
			PRIMARY KEY  (order_id)
		) ENGINE=InnoDB $collate;"
		);
		dbDelta(
			"CREATE TABLE {$prefix}claims (
			identity_key varchar(80) NOT NULL,
			order_id bigint unsigned NOT NULL,
			permanent tinyint NOT NULL DEFAULT 0,
			PRIMARY KEY  (identity_key),
			KEY order_id (order_id)
		) ENGINE=InnoDB $collate;"
		);
		foreach ( array( 'ledger', 'orders', 'claims' ) as $suffix ) {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $prefix . $suffix ) );
			if ( 'InnoDB' !== $engine ) {
				throw new \RuntimeException( 'Referral tables require InnoDB.' );
			}
		}
		update_option( 'sscn_referrals_schema', 1, false );
	}

	/**
	 * Execute a short custom-table transaction under a database-scoped mutex.
	 *
	 * @param callable $operation Work to serialize.
	 * @return mixed
	 * @throws \RuntimeException On unavailable lock or failed database operation.
	 * @throws \Throwable Rethrows failures after rolling back.
	 */
	public static function transaction( $operation ) {
		global $wpdb;
		$name = 'sscn-ref-lock-' . md5( DB_NAME . $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $name ) ) ) {
			throw new \RuntimeException( esc_html__( 'Rewards are busy. Please try again.', 'parameter-commerce' ) );
		}
		try {
			self::check( $wpdb->query( 'START TRANSACTION' ) );
			$result = $operation();
			self::check( $wpdb->query( 'COMMIT' ) );
			return $result;
		} catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/**
	 * Fail closed on database errors.
	 *
	 * @param mixed $result Query result.
	 * @throws \RuntimeException Database failure.
	 */
	private static function check( $result ) {
		if ( false === $result || null === $result ) {
			throw new \RuntimeException( esc_html__( 'Unable to update shopping credit. Please try again.', 'parameter-commerce' ) );
		}
	}

	/**
	 * Uncached signed balance in cents; a refund reversal may leave a deficit.
	 *
	 * @param int $user_id Customer ID.
	 * @return int
	 */
	public static function balance( $user_id ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(delta),0) FROM {$wpdb->prefix}sscn_referral_ledger WHERE user_id = %d", $user_id ) );
		self::check( $value );
		return (int) $value;
	}

	/**
	 * Read existing reservation.
	 *
	 * @param int $order_id Native order ID.
	 * @return array|null
	 */
	public static function record( $order_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sscn_referral_orders WHERE order_id = %d", $order_id ), ARRAY_A );
		if ( $wpdb->last_error ) {
			self::check( false );
		}
		return $row;
	}

	/**
	 * Minimal pseudonymous eligibility keys (no copied addresses or payment data).
	 *
	 * @param string $email Billing email.
	 * @param int    $user_id Customer ID.
	 * @return array
	 */
	private static function identities( $email, $user_id ) {
		$keys = array( 'email:' . hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) ) );
		if ( $user_id ) {
			$keys[] = 'user:' . $user_id;
		}
		return $keys;
	}

	/**
	 * Check native history as well as concurrent first-order claims.
	 *
	 * @param string $email Billing email.
	 * @param int    $user_id Customer ID.
	 * @param int    $exclude Current retry order.
	 * @return bool
	 */
	public static function first_order( $email, $user_id, $exclude = 0 ) {
		global $wpdb;
		if ( ! is_email( $email ) ) {
			return false;
		}
		foreach ( self::identities( $email, $user_id ) as $key ) {
			$claim = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}sscn_referral_claims WHERE identity_key = %s", $key ) );
			if ( $wpdb->last_error || ( $claim && (int) $claim !== (int) $exclude ) ) {
				return false;
			}
		}
		$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-pending', 'wc-failed', 'wc-cancelled', 'wc-checkout-draft' ) );
		$base     = array(
			'status'  => $statuses,
			'exclude' => array( $exclude ),
			'limit'   => 1,
			'return'  => 'ids',
		);
		if ( wc_get_orders( array_merge( $base, array( 'billing_email' => $email ) ) ) ) {
			return false;
		}
		return ! $user_id || ! wc_get_orders( array_merge( $base, array( 'customer_id' => $user_id ) ) );
	}

	/**
	 * Insert immutable financial event within the surrounding transaction.
	 *
	 * @param int    $user Customer.
	 * @param int    $order Order.
	 * @param int    $delta Signed cents.
	 * @param string $reason Machine reason.
	 */
	private static function entry( $user, $order, $delta, $reason ) {
		global $wpdb;
		if ( $delta ) {
			self::check(
				$wpdb->insert(
					$wpdb->prefix . 'sscn_referral_ledger',
					array(
						'user_id'  => $user,
						'order_id' => $order,
						'delta'    => $delta,
						'reason'   => $reason,
						'created'  => current_time( 'mysql', true ),
					),
					array( '%d', '%d', '%d', '%s', '%s' )
				)
			);
		}
	}

	/**
	 * Reserve before gateway processing; retries cannot spend a balance twice.
	 *
	 * @param \WC_Order $order Native order.
	 * @param int       $credit Applied credit cents.
	 * @param int       $referrer Referring account, or zero.
	 * @throws \RuntimeException Invalid eligibility or insufficient credit.
	 */
	public static function reserve( $order, $credit, $referrer ) {
		self::transaction(
			function () use ( $order, $credit, $referrer ) {
				global $wpdb;
				$id    = $order->get_id();
				$user  = $order->get_customer_id();
				$email = strtolower( trim( $order->get_billing_email() ) );
				$old   = self::record( $id );
				if ( ! $id || 'CNY' !== $order->get_currency() || $credit < 0 || ( $credit && ! $user ) ) {
					throw new \RuntimeException( esc_html__( 'Sign in to use your CNY shopping credit.', 'parameter-commerce' ) );
				}
				if ( $old && 'paid' === $old['state'] ) {
					throw new \RuntimeException( esc_html__( 'This order has already been paid.', 'parameter-commerce' ) );
				}
				if ( $old && ( (int) $old['user_id'] !== $user || (int) $old['referrer_id'] !== $referrer || (int) $old['credit'] !== $credit ) ) {
					throw new \RuntimeException( esc_html__( 'This order already has a reward reservation. Cancel it before changing the order.', 'parameter-commerce' ) );
				}
				$available = $user ? self::balance( $user ) + (int) ( $old['debit'] ?? 0 ) : 0;
				if ( $credit > $available ) {
					throw new \RuntimeException( esc_html__( 'Your credit is reserved or has changed. Pay or cancel any unpaid order, then refresh your cart.', 'parameter-commerce' ) );
				}
				if ( $referrer ) {
					$person = get_userdata( $referrer );
					if ( ! $person || $referrer === $user || strtolower( $person->user_email ) === $email || ! self::first_order( $email, $user, $id ) ) {
						throw new \RuntimeException( esc_html__( 'The referral discount is only for a new customer’s first order and cannot be used for self-referrals.', 'parameter-commerce' ) );
					}
					foreach ( self::identities( $email, $user ) as $key ) {
						self::check( $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}sscn_referral_claims (identity_key,order_id,permanent) VALUES (%s,%d,0) ON DUPLICATE KEY UPDATE order_id = order_id", $key, $id ) ) );
					}
				}
				self::entry( $user, $id, (int) ( $old['debit'] ?? 0 ) - $credit, 'credit_reserved' );
				$data = array(
					'order_id'    => $id,
					'user_id'     => $user,
					'referrer_id' => $referrer,
					'credit'      => $credit,
					'debit'       => $credit,
					'reward'      => 0,
					'state'       => 'reserved',
				);
				self::check( $old ? $wpdb->update( $wpdb->prefix . 'sscn_referral_orders', $data, array( 'order_id' => $id ) ) : $wpdb->insert( $wpdb->prefix . 'sscn_referral_orders', $data ) );
			}
		);
	}

	/**
	 * Proportional restoration, including quantity returns on zero-cash orders.
	 * Full refunds/cancellations restore all credit. Monetary-only partial refunds
	 * restore the same fraction of credit as the fraction of original cash refunded.
	 *
	 * @param \WC_Order $order Native order including refunds.
	 * @param int       $credit Original credit cents.
	 * @return int
	 */
	public static function returned_credit( $order, $credit ) {
		if ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
			return $credit;
		}
		$subtotal = 0;
		$returned = 0;
		foreach ( $order->get_items() as $item ) {
			$subtotal += (float) $item->get_subtotal();
			$returned += (float) $item->get_subtotal() * min( 1, abs( $order->get_qty_refunded_for_item( $item->get_id() ) ) / max( 1, $item->get_quantity() ) );
		}
		$fraction = $subtotal > 0 ? $returned / $subtotal : 0;
		if ( (float) $order->get_total() > 0 ) {
			$fraction = max( $fraction, (float) $order->get_total_refunded() / (float) $order->get_total() );
		}
		return min( $credit, (int) round( $credit * $fraction ) );
	}

	/**
	 * Reconcile retries/statuses/refunds to target amounts, never replay rewards.
	 * Native WC orders are read only while our custom-table transaction is open.
	 *
	 * @param \WC_Order $order Native order.
	 * @return bool Whether a late payment left an account deficit.
	 */
	public static function sync( $order ) {
		return self::transaction(
			function () use ( $order ) {
				global $wpdb;
				$id = $order->get_id();
				// Read current native state after acquiring the lock, not an earlier event snapshot.
				$order = new \WC_Order( $id );
				if ( 'CNY' !== $order->get_currency() ) {
					throw new \RuntimeException( 'Domestic rewards require CNY orders.' );
				}
				$row = self::record( $id );
				if ( ! $row ) {
					return false;
				}
				$conflict = false;
				$closed   = $order->has_status( array( 'cancelled', 'failed', 'refunded' ) );
				$paid     = $order->is_paid() || $order->get_date_paid() || 'paid' === $row['state'];
				$debit    = $closed ? 0 : (int) $row['credit'] - self::returned_credit( $order, (int) $row['credit'] );
				$reward   = (int) $row['reward'];
				if ( $closed || ( (float) $order->get_total() > 0 && $order->get_total_refunded() >= (float) $order->get_total() ) ) {
					$reward = 0;
				} elseif ( $row['referrer_id'] && $order->has_status( 'completed' ) ) {
					$reward = Referrals::REWARD_FEN;
				}
				if ( $paid && $row['referrer_id'] ) {
					foreach ( self::identities( $order->get_billing_email(), $order->get_customer_id() ) as $key ) {
						$owner = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}sscn_referral_claims WHERE identity_key = %s", $key ) );
						if ( $owner && (int) $owner !== $id ) {
							$reward   = 0;
							$conflict = true;
							continue;
						}
						self::check( $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}sscn_referral_claims (identity_key,order_id,permanent) VALUES (%s,%d,1) ON DUPLICATE KEY UPDATE permanent = 1", $key, $id ) ) );
					}
				}
				if ( $closed && ! $paid ) {
					self::check( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sscn_referral_claims WHERE order_id = %d AND permanent = 0", $id ) ) );
				}
				self::entry( (int) $row['user_id'], $id, (int) $row['debit'] - $debit, $debit > (int) $row['debit'] ? 'credit_reapplied' : 'credit_returned' );
				self::entry( (int) $row['referrer_id'], $id, $reward - (int) $row['reward'], $reward ? 'referral_earned' : 'referral_reversed' );
				self::check(
					$wpdb->update(
						$wpdb->prefix . 'sscn_referral_orders',
						array(
							'debit'  => $debit,
							'reward' => $reward,
							'state'  => $closed ? 'released' : ( $paid ? 'paid' : 'reserved' ),
						),
						array( 'order_id' => $id )
					)
				);
				return $conflict || ( $row['user_id'] && self::balance( (int) $row['user_id'] ) < 0 ) || ( $row['referrer_id'] && self::balance( (int) $row['referrer_id'] ) < 0 );
			}
		);
	}

	/**
	 * Private customer history, newest first.
	 *
	 * @param int $user_id Current user.
	 * @return array
	 */
	public static function history( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT delta,reason,created FROM {$wpdb->prefix}sscn_referral_ledger WHERE user_id = %d ORDER BY id DESC LIMIT 50", $user_id ), ARRAY_A );
	}
}
