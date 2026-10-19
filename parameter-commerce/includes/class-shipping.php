<?php
/**
 * Staff-confirmed single-parcel fulfillment and measured-weight memory.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WooCommerce owns orders; protected metadata holds the carrier submission. */
final class Shipping {
	/**
	 * Remove copied personal data when WooCommerce erases an order.
	 *
	 * @param \WC_Order $order Native privacy-erasure target.
	 */
	public static function erase_personal_data( $order ) {
		self::locked(
			'order-' . $order->get_id(),
			static function () use ( $order ) {
				$order->read_meta_data( true );
				foreach ( array( 'sandbox', 'production' ) as $mode ) {
					$key   = '_pc_yunexpress_' . $mode;
					$state = (array) $order->get_meta( $key );
					if ( ! $state ) {
						continue;
					}
					unset( $state['payload'], $state['error'], $state['review_hash'] );
					$state['privacy_erased'] = true;
					$state['confirmed']      = false;
					$order->update_meta_data( $key, $state );
				}
				$order->save_meta_data();
			}
		);
	}

	/**
	 * Serialize work without modifying WooCommerce tables directly.
	 *
	 * @param string   $key Lock scope.
	 * @param callable $work Protected work.
	 * @return mixed
	 * @throws \RuntimeException If busy.
	 */
	public static function locked( $key, $work ) {
		global $wpdb;
		$name = 'pc-ship-' . md5( DB_NAME . $wpdb->prefix . $key );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-scoped mutex, not order storage.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $name ) ) ) {
			throw new \RuntimeException( esc_html__( 'Shipping is busy. Please refresh before trying again.', 'parameter-commerce' ) );
		}
		try {
			return $work();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the same connection-scoped mutex.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/**
	 * Active environment; disabled is the default on every installation.
	 *
	 * @return string
	 */
	public static function mode() {
		$mode = get_option( 'pc_yunexpress_mode', 'disabled' );
		return in_array( $mode, array( 'sandbox', 'production' ), true ) ? $mode : 'disabled';
	}

	/**
	 * Non-secret, environment-specific operator configuration.
	 *
	 * @return array
	 */
	public static function settings() {
		return (array) get_option( 'pc_yunexpress_settings_' . self::mode(), array() );
	}

	/**
	 * Staff authorization shared by HTTP handlers and service entry points.
	 *
	 * @param int $order_id Optional order authorization.
	 * @return void
	 * @throws \RuntimeException On denied access.
	 */
	public static function authorize( $order_id = 0 ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ( $order_id && ! current_user_can( 'edit_shop_order', $order_id ) ) ) {
			throw new \RuntimeException( esc_html__( 'You cannot manage this shipment.', 'parameter-commerce' ) );
		}
	}

	/**
	 * Load an editable physical order. Refunds need manual parcel reconciliation.
	 *
	 * @param int  $id Order ID.
	 * @param bool $eligible Require a paid, unrefunded processing order.
	 * @return \WC_Order
	 * @throws \RuntimeException On invalid order.
	 */
	public static function order( $id, $eligible = true ) {
		self::authorize( $id );
		$order = wc_get_order( $id );
		if ( ! $order || ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
			throw new \RuntimeException( esc_html__( 'Order not found.', 'parameter-commerce' ) );
		}
		$order->get_data_store()->read( $order );
		$order->read_meta_data( true );
		if ( $eligible && ( ! $order->has_status( 'processing' ) || ( (float) $order->get_total() > 0 && ! $order->get_date_paid() ) || $order->get_refunds() ) ) {
			throw new \RuntimeException( esc_html__( 'Use an accepted, unrefunded Processing order. Review payment or refund changes before shipping.', 'parameter-commerce' ) );
		}
		return $order;
	}

	/**
	 * Shipping snapshot excludes customer identity from the weight match.
	 *
	 * @param \WC_Order $order Order.
	 * @return array Sorted physical SKU quantities and descriptors.
	 * @throws \RuntimeException On unsupported contents.
	 */
	public static function contents( $order ) {
		$rows = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				throw new \RuntimeException( esc_html__( 'An ordered product is unavailable. Review the parcel manually.', 'parameter-commerce' ) );
			}
			if ( $product->is_virtual() ) {
				continue;
			}
			if ( ! $product->is_type( 'variation' ) || ! Pack_Builder::enabled( $product->get_parent_id() ) ) {
				throw new \RuntimeException( esc_html__( 'This first shipping workflow supports Build Your Pack variants with inventory counted in pairs. Review other products manually.', 'parameter-commerce' ) );
			}
			$quantity = (float) $item->get_quantity();
			if ( $quantity <= 0 || floor( $quantity ) !== $quantity ) {
				throw new \RuntimeException( esc_html__( 'Shipping requires positive whole-pair quantities.', 'parameter-commerce' ) );
			}
			$id = $product->get_id();
			if ( ! isset( $rows[ $id ] ) ) {
				$attributes = $product->is_type( 'variation' ) ? $product->get_attributes() : array();
				ksort( $attributes );
				$rows[ $id ] = array(
					'id'         => $id,
					'sku'        => $product->get_sku(),
					'attributes' => $attributes,
					'revision'   => (string) $product->get_meta( '_pc_shipping_revision' ),
					'quantity'   => 0,
				);
			}
			$rows[ $id ]['quantity'] += (int) $quantity;
		}
		if ( ! $rows ) {
			throw new \RuntimeException( esc_html__( 'This order has no physical items to ship.', 'parameter-commerce' ) );
		}
		ksort( $rows, SORT_NUMERIC );
		return array_values( $rows );
	}

	/**
	 * Normalize positive decimal inputs before hashing or submitting.
	 *
	 * @param mixed $value Number.
	 * @param float $max Upper bound for this small-parcel workflow.
	 * @return float
	 * @throws \RuntimeException On invalid input.
	 */
	public static function number( $value, $max ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\d+(?:\.\d{1,3})?$/D', (string) $value ) || (float) $value <= 0 || (float) $value > $max ) {
			throw new \RuntimeException( esc_html__( 'Enter a positive number within the displayed limit, with at most three decimal places.', 'parameter-commerce' ) );
		}
		return (float) $value;
	}

	/**
	 * Whole grams preserve the carrier's maximum three decimal places in kg.
	 *
	 * @param mixed $value Entered grams.
	 * @return int
	 * @throws \RuntimeException On a fractional gram.
	 */
	public static function grams( $value ) {
		$value = self::number( $value, 30000 );
		if ( floor( $value ) !== $value ) {
			throw new \RuntimeException( esc_html__( 'Enter weight in whole grams, rounding up any fraction.', 'parameter-commerce' ) );
		}
		return (int) $value;
	}

	/**
	 * Normalize packaging. Weight is deliberately excluded from the key.
	 *
	 * @param array $input Staff input.
	 * @return array
	 * @throws \RuntimeException On incomplete packaging.
	 */
	public static function packaging( $input ) {
		$name = sanitize_text_field( $input['packaging'] ?? '' );
		if ( '' === $name || mb_strlen( $name ) > 80 ) {
			throw new \RuntimeException( esc_html__( 'Enter a packaging name, including its revision when materials change.', 'parameter-commerce' ) );
		}
		return array(
			'packaging' => $name,
			'length'    => self::number( $input['length'] ?? '', 200 ),
			'width'     => self::number( $input['width'] ?? '', 200 ),
			'height'    => self::number( $input['height'] ?? '', 200 ),
		);
	}

	/**
	 * Exact contents + packaging + physical revision, independent of line order.
	 *
	 * @param \WC_Order $order Native order.
	 * @param array     $parcel Normalized packaging.
	 * @return string
	 */
	public static function fingerprint( $order, $parcel ) {
		$settings = self::settings();
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'v'           => 1,
					'environment' => self::mode(),
					'revision'    => (string) ( $settings['revision'] ?? '1' ),
					'contents'    => self::contents( $order ),
					'packaging'   => self::packaging( $parcel ),
				)
			)
		);
	}

	/**
	 * Recall only an actual measurement, never a reused estimate.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $parcel Packaging.
	 * @return array
	 */
	public static function remembered( $order, $parcel ) {
		return (array) get_option( 'pc_parcel_weight_' . self::fingerprint( $order, $parcel ), array() );
	}

	/**
	 * Environment-scoped state via the HPOS-compatible order metadata API.
	 *
	 * @param \WC_Order $order Native order.
	 * @return array
	 */
	public static function state( $order ) {
		return (array) $order->get_meta( '_pc_yunexpress_' . self::mode() );
	}

	/**
	 * Persist and reload before making an external write.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $state Shipping state.
	 * @return void
	 * @throws \RuntimeException On persistence failure.
	 */
	private static function persist( $order, $state ) {
		$key = '_pc_yunexpress_' . self::mode();
		$order->update_meta_data( $key, $state );
		$order->save_meta_data();
		$order->read_meta_data( true );
		if ( $state !== $order->get_meta( $key ) ) {
			throw new \RuntimeException( esc_html__( 'Could not save shipping state. No further request was made.', 'parameter-commerce' ) );
		}
	}

	/**
	 * Save preparation; an explicit measurement updates the matching cache.
	 *
	 * @param int   $id Order ID.
	 * @param array $input Submitted parcel and customs fields.
	 * @param bool  $confirm Explicit staff confirmation.
	 * @return array
	 * @throws \RuntimeException On validation failure.
	 */
	public static function prepare( $id, $input, $confirm ) {
		return self::locked(
			'order-' . $id,
			static function () use ( $id, $input, $confirm ) {
				$order = self::order( $id );
				$state = self::state( $order );
				if ( ! in_array( $state['status'] ?? 'draft', array( 'draft', 'rejected' ), true ) ) {
					throw new \RuntimeException( esc_html__( 'A submission already exists. Reconcile it before changing this parcel.', 'parameter-commerce' ) );
				}
				if ( 'disabled' === self::mode() || ( isset( $input['review'] ) && ! hash_equals( self::review_hash( $order, $state ), (string) $input['review'] ) ) ) {
					throw new \RuntimeException( esc_html__( 'The order changed or shipping is disabled. Refresh and review the parcel.', 'parameter-commerce' ) );
				}
				$parcel      = self::packaging( $input );
				$fingerprint = self::fingerprint( $order, $parcel );
				$remembered  = self::remembered( $order, $parcel );
				$basis       = 'measured' === ( $input['basis'] ?? '' ) ? 'measured' : 'reused';
				$weight      = '' !== (string) ( $input['weight_g'] ?? '' ) ? self::grams( $input['weight_g'] ) : ( $remembered['weight_g'] ?? 0 );
				if ( $confirm && ( ! $weight || ( 'reused' === $basis && ( ! $remembered || (float) $weight !== (float) $remembered['weight_g'] ) ) ) ) {
					throw new \RuntimeException( esc_html__( 'Weigh this parcel or use the matching saved weight. A changed weight must be marked as measured.', 'parameter-commerce' ) );
				}
				$declarations = array();
				foreach ( self::contents( $order ) as $row ) {
					$entry = (array) ( $input['declarations'][ $row['id'] ] ?? array() );
					foreach ( array( 'name_en', 'name_local', 'hs_code', 'unit_price', 'unit_weight_g' ) as $field ) {
						$declarations[ $row['id'] ][ $field ] = sanitize_text_field( $entry[ $field ] ?? '' );
					}
				}
				$state = array_merge(
					$state,
					array(
						'status'       => 'draft',
						'parcel'       => $parcel,
						'weight_g'     => $weight,
						'basis'        => $basis,
						'fingerprint'  => $fingerprint,
						'confirmed'    => (bool) $confirm,
						'confirmed_by' => get_current_user_id(),
						'confirmed_at' => gmdate( 'c' ),
						'declarations' => $declarations,
					)
				);
				unset( $state['error'] );
				$state['review_hash'] = self::review_hash( $order, $state );
				self::persist( $order, $state );
				if ( $confirm && 'measured' === $basis ) {
					self::locked(
						'weight-' . $fingerprint,
						static function () use ( $fingerprint, $weight, $id ) {
							update_option(
								'pc_parcel_weight_' . $fingerprint,
								array(
									'weight_g'    => $weight,
									'order_id'    => $id,
									'measured_at' => gmdate( 'c' ),
									'measured_by' => get_current_user_id(),
								),
								false
							);
						}
					);
				}
				return $state;
			}
		);
	}

	/**
	 * Detect changed recipients, order lines, configuration or draft confirmation.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $state Prepared parcel.
	 * @return string
	 */
	public static function review_hash( $order, $state ) {
		return hash( 'sha256', wp_json_encode( array( self::contents( $order ), $order->get_address( 'shipping' ), $order->get_billing_email(), $order->get_billing_phone(), $order->get_currency(), $order->get_total(), self::settings(), $state['parcel'] ?? array(), $state['weight_g'] ?? 0, $state['declarations'] ?? array() ) ) );
	}

	/**
	 * Produce a single-parcel API payload from confirmed native order quantities.
	 *
	 * @param \WC_Order $order Native order.
	 * @param array     $state Confirmed preparation.
	 * @return array
	 * @throws \RuntimeException On missing customs/address/configuration data.
	 */
	public static function payload( $order, $state ) {
		$config = self::settings();
		if ( empty( $state['confirmed'] ) || ! hash_equals( $state['review_hash'] ?? '', self::review_hash( $order, $state ) ) || ! hash_equals( $state['fingerprint'] ?? '', self::fingerprint( $order, $state['parcel'] ) ) ) {
			throw new \RuntimeException( esc_html__( 'The order or shipping settings changed. Review and confirm the parcel again.', 'parameter-commerce' ) );
		}
		$address  = $order->get_address( 'shipping' );
		$receiver = array(
			'first_name'    => $address['first_name'] ?? '',
			'last_name'     => $address['last_name'] ?? '',
			'company'       => $address['company'] ?? '',
			'country_code'  => $address['country'] ?? '',
			'province'      => $address['state'] ?? '',
			'city'          => $address['city'] ?? '',
			'address_lines' => array_values( array_filter( array( $address['address_1'] ?? '', $address['address_2'] ?? '' ) ) ),
			'postal_code'   => $address['postcode'] ?? '',
			'phone_number'  => ! empty( $address['phone'] ) ? $address['phone'] : $order->get_billing_phone(),
			'email'         => $order->get_billing_email(),
		);
		$sender   = (array) ( $config['sender'] ?? array() );
		foreach ( array( $receiver, $sender ) as $person ) {
			foreach ( array( 'first_name', 'country_code', 'province', 'city', 'address_lines', 'postal_code', 'phone_number' ) as $field ) {
				if ( empty( $person[ $field ] ) ) {
					throw new \RuntimeException( esc_html__( 'Complete the sender and shipping recipient name, address, postal code and phone number.', 'parameter-commerce' ) );
				}
			}
		}
		if ( 'US' !== $receiver['country_code'] || 'CN' !== $sender['country_code'] || 'USD' !== $order->get_currency() || empty( $config['product_code'] ) ) {
			throw new \RuntimeException( esc_html__( 'This workflow requires China-to-US shipping, a USD order and a confirmed service code.', 'parameter-commerce' ) );
		}
		$declared = array();
		$net      = 0;
		foreach ( self::contents( $order ) as $row ) {
			$entry = $state['declarations'][ $row['id'] ] ?? array();
			if ( empty( $entry['name_en'] ) || empty( $entry['name_local'] ) || ! preg_match( '/^\d{6,10}$/D', $entry['hs_code'] ?? '' ) || '' === $row['sku'] ) {
				throw new \RuntimeException( esc_html__( 'Each SKU needs English and Chinese customs descriptions and an approved 6–10 digit HS code.', 'parameter-commerce' ) );
			}
			$unit_weight = self::grams( $entry['unit_weight_g'] ?? '' );
			$net        += $unit_weight * $row['quantity'];
			$declared[]  = array(
				'sku_code'    => $row['sku'],
				'name_en'     => $entry['name_en'],
				'name_local'  => $entry['name_local'],
				'hs_code'     => $entry['hs_code'],
				'unit_price'  => self::number( $entry['unit_price'] ?? '', 10000 ),
				'unit_weight' => $unit_weight / 1000,
				'quantity'    => $row['quantity'],
				'currency'    => 'USD',
			);
		}
		$weight = self::grams( $state['weight_g'] );
		if ( $net > $weight ) {
			throw new \RuntimeException( esc_html__( 'Declared item weights exceed the packed parcel weight. Check grams and quantities.', 'parameter-commerce' ) );
		}
		$package = $state['parcel'];
		unset( $package['packaging'] );
		$package['weight'] = $weight / 1000;
		return array(
			'customer_order_number' => $state['reference'],
			'product_code'          => $config['product_code'],
			'receiver'              => $receiver,
			'sender'                => $sender,
			'packages'              => array( $package ),
			'declaration_info'      => $declared,
			'weight_unit'           => 'KG',
			'size_unit'             => 'CM',
			'label_type'            => 'PDF',
		);
	}

	/**
	 * Stable reference separates site, environment and order; never varies on retry.
	 *
	 * @param int $id Native order ID.
	 * @return string
	 */
	private static function reference( $id ) {
		$site = substr( hash( 'sha256', home_url() ), 0, 8 );
		return 'SS-' . ( 'sandbox' === self::mode() ? 'SBX-' : 'LIVE-' ) . $site . '-' . $id . '-P1';
	}

	/**
	 * Create once. Unknown outcomes are locked for lookup, never auto-resubmitted.
	 *
	 * @param int         $id Native order ID.
	 * @param string|null $expected_review Browser-reviewed saved parcel.
	 * @return array
	 * @throws \RuntimeException On invalid preparation.
	 */
	public static function create( $id, $expected_review = null ) {
		return self::locked(
			'order-' . $id,
			static function () use ( $id, $expected_review ) {
				$order = self::order( $id );
				$state = self::state( $order );
				if ( 'draft' !== ( $state['status'] ?? '' ) ) {
					throw new \RuntimeException( esc_html__( 'This parcel has already been submitted. Use Reconcile shipment.', 'parameter-commerce' ) );
				}
				if ( null !== $expected_review && ! hash_equals( self::review_hash( $order, $state ), $expected_review ) ) {
					throw new \RuntimeException( esc_html__( 'The saved parcel changed. Refresh before submitting it.', 'parameter-commerce' ) );
				}
				$client             = new YunExpress_Client( self::mode() );
				$state['reference'] = $state['reference'] ?? self::reference( $id );
				$payload            = self::payload( $order, $state );
				$state['account']   = $client->identity();
				$state['status']    = 'submitting';
				$state['payload']   = $payload;
				self::persist( $order, $state );
				$response = $client->request( 'create', $payload );
				if ( is_wp_error( $response ) ) {
					$code            = $response->get_error_data()['provider_code'] ?? '';
					$state['status'] = in_array( $code, array( '02039009', '02039171', '02039019', '02039162', '02039306', '02039311', '02039026', '02039100', '02039209', '02039276' ), true ) ? 'rejected' : 'unknown';
					$state['error']  = $response->get_error_message();
				} else {
					$state = self::accept_result( $state, $response['result'] ?? array() );
				}
				self::persist( $order, $state );
				return $state;
			}
		);
	}

	/**
	 * Validate provider identity before binding its waybill to an order.
	 *
	 * @param array $state Submitted parcel.
	 * @param array $result Provider result.
	 * @return array
	 */
	private static function accept_result( $state, $result ) {
		if ( ! is_array( $result ) || empty( $result['waybill_number'] ) || (string) ( $result['customer_order_number'] ?? '' ) !== $state['reference'] ) {
			$state['status'] = 'unknown';
			$state['error']  = __( 'The response did not identify this parcel. Reconcile it in YunExpress before any new submission.', 'parameter-commerce' );
			return $state;
		}
		$state['status']          = 'created';
		$state['waybill_number']  = sanitize_text_field( $result['waybill_number'] );
		$state['tracking_number'] = sanitize_text_field( $result['tracking_number'] ?? '' );
		unset( $state['error'] );
		return $state;
	}

	/**
	 * Reconcile safely even if WooCommerce was refunded/cancelled after submission.
	 *
	 * @param int $id Native order ID.
	 * @return array
	 * @throws \RuntimeException On missing reference.
	 */
	public static function reconcile( $id ) {
		return self::locked(
			'order-' . $id,
			static function () use ( $id ) {
				$order = self::order( $id, false );
				$state = self::state( $order );
				if ( empty( $state['reference'] ) || 'cancelled' === ( $state['status'] ?? '' ) ) {
					throw new \RuntimeException( esc_html__( 'No active shipment reference is available.', 'parameter-commerce' ) );
				}
				$response = self::bound_client( $state )->request( 'lookup', array( 'order_number' => $state['reference'] ) );
				if ( is_wp_error( $response ) ) {
					$state['error'] = $response->get_error_message();
				} elseif ( 'cancelling' === $state['status'] ) {
						$state['error'] = __( 'Cancellation outcome needs confirmation in the YunExpress portal. No resubmission is permitted.', 'parameter-commerce' );
				} else {
					$state = self::accept_result( $state, $response['result'] ?? array() );
				}
				self::persist( $order, $state );
				return $state;
			}
		);
	}

	/**
	 * Cancel only after explicit staff action; never on a WooCommerce refund hook.
	 *
	 * @param int $id Native order ID.
	 * @return array
	 * @throws \RuntimeException When no cancellable reference exists.
	 */
	public static function cancel( $id ) {
		return self::locked(
			'order-' . $id,
			static function () use ( $id ) {
				$order = self::order( $id, false );
				$state = self::state( $order );
				if ( 'created' !== ( $state['status'] ?? '' ) || empty( $state['waybill_number'] ) ) {
					throw new \RuntimeException( esc_html__( 'Reconcile the shipment before requesting cancellation.', 'parameter-commerce' ) );
				}
				$state['status'] = 'cancelling';
				self::persist( $order, $state );
				$response = self::bound_client( $state )->request( 'cancel', array( 'waybill_number' => $state['waybill_number'] ) );
				if ( is_wp_error( $response ) ) {
					$state['error'] = $response->get_error_message();
				} else {
					$state['status'] = 'cancelled';
					unset( $state['error'] );
				}
				self::persist( $order, $state );
				return $state;
			}
		);
	}

	/**
	 * Recover only within the original carrier account.
	 *
	 * @param array $state Saved submission.
	 * @return YunExpress_Client
	 * @throws \RuntimeException On changed account.
	 */
	private static function bound_client( $state ) {
		$client = new YunExpress_Client( self::mode() );
		if ( ! hash_equals( $state['account'] ?? '', $client->identity() ) ) {
			throw new \RuntimeException( esc_html__( 'The carrier account changed. Restore the original account to reconcile this shipment.', 'parameter-commerce' ) );
		}
		return $client;
	}

	/**
	 * Retrieve label information for authorized staff without a public attachment.
	 *
	 * @param int $id Native order ID.
	 * @return array
	 * @throws \RuntimeException If label is not ready.
	 */
	public static function label( $id ) {
		$order = self::order( $id, false );
		$state = self::state( $order );
		if ( 'created' !== ( $state['status'] ?? '' ) || empty( $state['waybill_number'] ) ) {
			throw new \RuntimeException( esc_html__( 'Create or reconcile the shipment before requesting a label.', 'parameter-commerce' ) );
		}
		if ( ! empty( $state['privacy_erased'] ) ) {
			throw new \RuntimeException( esc_html__( 'Personal data was erased from this order. Label retrieval is disabled.', 'parameter-commerce' ) );
		}
		$response = self::bound_client( $state )->request( 'label', array( 'order_number' => $state['waybill_number'] ) );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( $response->get_error_message() ) );
		}
		$result = $response['result'] ?? array();
		$url    = is_array( $result ) && is_string( $result['url'] ?? null ) ? $result['url'] : '';
		$parts  = wp_parse_url( $url );
		// This is an escaped browser link, never a server-side fetch. Avoid DNS-dependent
		// HTTP-request validation, which can reject valid carrier CDN links behind proxies.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) || ! in_array( strtoupper( $result['label_type'] ?? '' ), array( 'PDF', 'PNG' ), true ) ) {
			throw new \RuntimeException( esc_html__( 'A printable PDF or PNG label is not ready. Try again later.', 'parameter-commerce' ) );
		}
		return array(
			'url'  => $result['url'],
			'type' => strtoupper( $result['label_type'] ),
		);
	}
}
