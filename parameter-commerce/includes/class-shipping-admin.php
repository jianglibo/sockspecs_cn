<?php
/**
 * Staff shipping preparation and YunExpress controls.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A dedicated form avoids nesting inside WooCommerce's order editor form. */
final class Shipping_Admin {
	/** Register staff-only UI; no checkout, payment or completion hooks. */
	public static function register_hooks() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( Shipping::class, 'erase_personal_data' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( self::class, 'order_link' ) );
		add_action( 'admin_post_pc_shipping', array( self::class, 'handle' ) );
		add_action( 'admin_post_nopriv_pc_shipping', array( self::class, 'handle' ) );
	}

	/**
	 * Load styles only on this staff screen.
	 *
	 * @param string $hook Admin screen hook.
	 */
	public static function assets( $hook ) {
		if ( 'woocommerce_page_pc-shipping' === $hook ) {
			wp_enqueue_style( 'pc-shipping', plugins_url( '../assets/shipping.css', __FILE__ ), array(), '0.5.7' );
		}
	}

	/** Staff menu. */
	public static function menu() {
		if ( Plugin::has_woocommerce() ) {
			add_submenu_page( 'woocommerce', __( 'YunExpress shipping', 'parameter-commerce' ), __( 'YunExpress shipping', 'parameter-commerce' ), 'manage_woocommerce', 'pc-shipping', array( self::class, 'page' ) );
		}
	}

	/**
	 * Direct link from both classic and HPOS order editors.
	 *
	 * @param \WC_Order $order Native order.
	 */
	public static function order_link( $order ) {
		if ( current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			echo '<p><a class="button" href="' . esc_url( self::url( $order->get_id() ) ) . '">' . esc_html__( 'Prepare YunExpress shipment', 'parameter-commerce' ) . '</a></p>';
		}
	}

	/**
	 * Staff page URL.
	 *
	 * @param int $id Optional order.
	 * @return string
	 */
	public static function url( $id = 0 ) {
		return add_query_arg(
			array(
				'page'     => 'pc-shipping',
				'order_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Accessible input helper.
	 *
	 * @param string $name Field name.
	 * @param string $label Visible label.
	 * @param mixed  $value Current value.
	 * @param string $type Input type.
	 */
	private static function field( $name, $label, $value = '', $type = 'text' ) {
		$id = 'pc-ship-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		echo '<p class="pc-shipping-field"><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) $value ) . '"' . ( 'number' === $type ? ' min="0.001" step="0.001"' : '' ) . '></p>';
	}

	/**
	 * CSRF-protected independent form.
	 *
	 * @param int $id Order ID.
	 */
	private static function form( $id = 0 ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'pc_shipping_' . $id );
		echo '<input type="hidden" name="environment" value="' . esc_attr( Shipping::mode() ) . '">';
		echo '<input type="hidden" name="action" value="pc_shipping"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
	}

	/**
	 * Render operation button.
	 *
	 * @param string $operation Server operation.
	 * @param string $label Label.
	 * @param bool   $primary Primary style.
	 */
	private static function button( $operation, $label, $primary = false ) {
		echo '<button class="button ' . ( $primary ? 'button-primary' : '' ) . '" type="submit" name="operation" value="' . esc_attr( $operation ) . '">' . esc_html( $label ) . '</button> ';
	}

	/** Render private settings and shipment page. */
	public static function page() {
		Shipping::authorize();
		nocache_headers();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page selector; authorization is checked above and per order.
		$id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		echo '<div class="wrap pc-shipping"><h1>' . esc_html__( 'YunExpress shipping', 'parameter-commerce' ) . '</h1>';
		echo '<p><strong>' . esc_html__( 'Environment:', 'parameter-commerce' ) . ' ' . esc_html( Shipping::mode() ) . '</strong> · <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings and orders', 'parameter-commerce' ) . '</a></p>';
		$notice = get_transient( 'pc_shipping_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'pc_shipping_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		try {
			if ( $id ) {
				self::order_page( Shipping::order( $id, false ) );
			} else {
				self::settings_page();
			}
		} catch ( \Throwable $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error->getMessage() ) . '</p></div>';
		}
		echo '</div>';
	}

	/** Settings and a small ready-to-pack queue, using native order APIs. */
	private static function settings_page() {
		$config = Shipping::settings();
		self::form();
		echo '<h2>' . esc_html__( 'Connection', 'parameter-commerce' ) . '</h2><p>' . esc_html__( 'Credentials are loaded from private server configuration. They are never displayed here. Sandbox shipments are tests only.', 'parameter-commerce' ) . '</p>';
		echo '<p><label for="pc-mode">' . esc_html__( 'Environment', 'parameter-commerce' ) . '</label> <select id="pc-mode" name="mode">';
		foreach ( array(
			'disabled'   => __( 'Disabled', 'parameter-commerce' ),
			'sandbox'    => __( 'Sandbox', 'parameter-commerce' ),
			'production' => __( 'Production (server approval required)', 'parameter-commerce' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( Shipping::mode(), $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		self::button( 'mode', __( 'Switch environment', 'parameter-commerce' ) );
		echo '</form>';
		if ( 'disabled' !== Shipping::mode() ) {
			self::form();
			self::button( 'connection', __( 'Test connection and list services', 'parameter-commerce' ) );
			echo '</form>';
			$services = get_transient( 'pc_shipping_services_' . Shipping::mode() );
			if ( is_array( $services ) ) {
				echo '<details><summary>' . esc_html__( 'Service catalogue (availability must be confirmed with YunExpress)', 'parameter-commerce' ) . '</summary><ul>';
				foreach ( $services as $service ) {
					echo '<li>' . esc_html( ( $service['product_code'] ?? '' ) . ' — ' . ( $service['product_name'] ?? '' ) ) . '</li>';
				}
				echo '</ul></details>';
			}
			self::form();
			echo '<h2>' . esc_html__( 'Service and packaging', 'parameter-commerce' ) . '</h2>';
			self::field( 'product_code', __( 'Approved YunExpress service code', 'parameter-commerce' ), $config['product_code'] ?? '' );
			self::field( 'revision', __( 'Weight matching revision (change when products or packaging change)', 'parameter-commerce' ), $config['revision'] ?? '1' );
			foreach ( self::parcel_fields() as $field => $label ) {
				self::field( $field, $label, $config['parcel'][ $field ] ?? '', 'packaging' === $field ? 'text' : 'number' );
			}
			echo '<h2>' . esc_html__( 'Sender in China', 'parameter-commerce' ) . '</h2>';
			foreach ( self::sender_fields() as $field => $label ) {
				self::field( 'sender[' . $field . ']', $label, 'address' === $field ? implode( ', ', $config['sender']['address_lines'] ?? array() ) : ( $config['sender'][ $field ] ?? '' ) );
			}
			self::button( 'settings', __( 'Save shipping settings', 'parameter-commerce' ), true );
			echo '</form>';
		}
		echo '<h2>' . esc_html__( 'Recent Processing orders', 'parameter-commerce' ) . '</h2><ul>';
		foreach ( wc_get_orders(
			array(
				'status'  => 'processing',
				'limit'   => 30,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		) as $order ) {
			echo '<li><a href="' . esc_url( self::url( $order->get_id() ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a></li>';
		}
		echo '</ul><p>' . esc_html__( 'You can also open shipment preparation directly from any WooCommerce order.', 'parameter-commerce' ) . '</p>';
	}

	/**
	 * Parcel fields with UI units and bounds.
	 *
	 * @return array
	 */
	private static function parcel_fields() {
		return array(
			'packaging' => __( 'Packaging name / revision', 'parameter-commerce' ),
			'length'    => __( 'Parcel length (cm, maximum 200)', 'parameter-commerce' ),
			'width'     => __( 'Parcel width (cm, maximum 200)', 'parameter-commerce' ),
			'height'    => __( 'Parcel height (cm, maximum 200)', 'parameter-commerce' ),
		);
	}

	/**
	 * Sender input labels.
	 *
	 * @return array
	 */
	private static function sender_fields() {
		return array(
			'first_name'   => __( 'First name', 'parameter-commerce' ),
			'last_name'    => __( 'Last name', 'parameter-commerce' ),
			'company'      => __( 'Company', 'parameter-commerce' ),
			'province'     => __( 'Province', 'parameter-commerce' ),
			'city'         => __( 'City', 'parameter-commerce' ),
			'address'      => __( 'Street address', 'parameter-commerce' ),
			'postal_code'  => __( 'Postal code', 'parameter-commerce' ),
			'phone_number' => __( 'Phone', 'parameter-commerce' ),
			'email'        => __( 'Email', 'parameter-commerce' ),
		);
	}

	/**
	 * Native order + remembered measurements, kept separate from carrier state.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function order_page( $order ) {
		if ( 'disabled' === Shipping::mode() ) {
			echo '<p>' . esc_html__( 'Choose an environment in shipping settings before preparing this order.', 'parameter-commerce' ) . '</p>';
			return;
		}
		$id     = $order->get_id();
		$state  = Shipping::state( $order );
		$config = Shipping::settings();
		$parcel = $state['parcel'] ?? ( $config['parcel'] ?? array() );
		echo '<h2>' . esc_html( '#' . $order->get_order_number() ) . '</h2><p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Edit order / recipient', 'parameter-commerce' ) . '</a></p>';
		echo '<p>' . wp_kses_post( $order->get_formatted_shipping_address() ) . '</p>';
		echo '<p>' . esc_html__( 'Shipment state:', 'parameter-commerce' ) . ' <strong>' . esc_html( $state['status'] ?? 'draft' ) . '</strong></p>';
		if ( ! empty( $state['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $state['error'] ) . '</p></div>';
		}
		foreach ( array( 'reference', 'waybill_number', 'tracking_number' ) as $key ) {
			if ( ! empty( $state[ $key ] ) ) {
				echo '<p>' . esc_html( $key . ': ' . $state[ $key ] ) . '</p>';
			}
		}
		echo '<p>' . esc_html__( 'A label is not proof of dispatch or delivery. Keep the WooCommerce order Processing until delivery is confirmed; completing it releases eligible referral credit.', 'parameter-commerce' ) . '</p>';
		if ( ! in_array( $state['status'] ?? 'draft', array( 'draft', 'rejected' ), true ) ) {
			self::form( $id );
			self::button( 'reconcile', __( 'Reconcile shipment / refresh tracking number', 'parameter-commerce' ) );
			if ( 'created' === $state['status'] ) {
				self::button( 'label', __( 'Get printable label', 'parameter-commerce' ), true );
				echo '<p><label><input type="checkbox" name="cancel_confirm" value="1"> ' . esc_html__( 'Request cancellation of this YunExpress shipment. This does not refund the order.', 'parameter-commerce' ) . '</label></p>';
				self::button( 'cancel', __( 'Request carrier cancellation', 'parameter-commerce' ) );
			}
			echo '</form>';
			return;
		}
		Shipping::order( $id );
		$remembered = array();
		try {
			$remembered = Shipping::remembered( $order, $parcel );
		} catch ( \RuntimeException $error ) {
			// Packaging is incomplete; staff can enter it below and look up again.
			$remembered = array();
		}
		self::form( $id );
		echo '<input type="hidden" name="review" value="' . esc_attr( Shipping::review_hash( $order, $state ) ) . '">';
		echo '<h3>' . esc_html__( 'Packed parcel', 'parameter-commerce' ) . '</h3>';
		echo '<div class="pc-shipping-dimensions">';
		foreach ( self::parcel_fields() as $field => $label ) {
			self::field( $field, $label, $parcel[ $field ] ?? '', 'packaging' === $field ? 'text' : 'number' );
		}
		echo '</div>';
		self::button( 'lookup_weight', __( 'Find matching weight', 'parameter-commerce' ) );
		$weight = ! empty( $state['weight_g'] ) ? $state['weight_g'] : ( $remembered['weight_g'] ?? '' );
		if ( $remembered ) {
			echo '<p>' . esc_html( sprintf( /* translators: 1: weight in grams, 2: original measured order ID, 3: UTC date. */ __( 'Last measured matching parcel: %1$s g · order #%2$s · %3$s (UTC). Match includes exact variants, quantities, packaging and dimensions.', 'parameter-commerce' ), $remembered['weight_g'], $remembered['order_id'], $remembered['measured_at'] ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No measured match found. Weigh this complete parcel, including packaging.', 'parameter-commerce' ) . '</p>';
		}
		self::field( 'weight_g', __( 'Total packed weight (whole g, maximum 30000)', 'parameter-commerce' ), $weight, 'number' );
		echo '<p><label><input type="radio" name="basis" value="measured" ' . checked( ! $remembered, true, false ) . '> ' . esc_html__( 'I weighed this parcel; remember this measurement', 'parameter-commerce' ) . '</label><br><label><input type="radio" name="basis" value="reused" ' . checked( (bool) $remembered, true, false ) . '> ' . esc_html__( 'Use the matching saved weight; this parcel was not reweighed', 'parameter-commerce' ) . '</label></p>';
		echo '<h3>' . esc_html__( 'Contents and customs declaration', 'parameter-commerce' ) . '</h3><p>' . esc_html__( 'Quantities below are individual pairs. Enter approved customs values and classifications. Supplier listing weights are not filled in automatically.', 'parameter-commerce' ) . '</p>';
		foreach ( Shipping::contents( $order ) as $row ) {
			$product = wc_get_product( $row['id'] );
			echo '<fieldset class="pc-shipping-customs"><legend><strong>' . esc_html( $product->get_name() . ' · ' . $row['sku'] . ' × ' . $row['quantity'] ) . '</strong></legend>';
			$entry = $state['declarations'][ $row['id'] ] ?? array();
			foreach ( array(
				'name_en'       => __( 'English customs description', 'parameter-commerce' ),
				'name_local'    => __( 'Chinese customs description (required by carrier)', 'parameter-commerce' ),
				'hs_code'       => __( 'Approved HS code', 'parameter-commerce' ),
				'unit_price'    => __( 'Declared value per pair (USD)', 'parameter-commerce' ),
				'unit_weight_g' => __( 'Net weight per pair (whole g)', 'parameter-commerce' ),
			) as $field => $label ) {
				self::field( 'declarations[' . $row['id'] . '][' . $field . ']', $label, $entry[ $field ] ?? '', in_array( $field, array( 'unit_price', 'unit_weight_g' ), true ) ? 'number' : 'text' );
			}
			echo '</fieldset>';
		}
		echo '<p><label><input type="checkbox" name="confirmed" value="1"> ' . esc_html__( 'I confirm the contents, packaging and weight basis above.', 'parameter-commerce' ) . '</label></p>';
		self::button( 'prepare', __( 'Save confirmed parcel', 'parameter-commerce' ), true );
		echo '</form>';
		if ( ! empty( $state['confirmed'] ) && 'draft' === $state['status'] ) {
			self::form( $id );
			echo '<input type="hidden" name="review" value="' . esc_attr( Shipping::review_hash( $order, $state ) ) . '">';
			echo '<p><strong>' . esc_html( sprintf( /* translators: 1: saved weight in grams, 2: packaging name. */ __( 'Saved parcel to submit: %1$s g · %2$s', 'parameter-commerce' ), $state['weight_g'], $state['parcel']['packaging'] ) ) . '</strong></p>';
			echo '<p><label><input type="checkbox" name="submit_confirm" value="1" required> ' . esc_html__( 'I reviewed the saved parcel, recipient, service and customs declaration and want to submit it to YunExpress.', 'parameter-commerce' ) . '</label></p>';
			self::button( 'create', 'sandbox' === Shipping::mode() ? __( 'Create sandbox shipment', 'parameter-commerce' ) : __( 'Create live shipment', 'parameter-commerce' ), true );
			echo '<p>' . esc_html__( 'Submission uses the saved parcel. Save any changes above before creating the shipment.', 'parameter-commerce' ) . '</p></form>';
		}
	}

	/** POST dispatch, capability + nonce checked before reading or writing data.
	 *
	 * @throws \RuntimeException Validation failures are caught and displayed only to staff.
	 */
	public static function handle() {
		$id = 0;
		try {
			Shipping::authorize();
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				throw new \RuntimeException( esc_html__( 'Use the shipping form to perform this action.', 'parameter-commerce' ) );
			}
			$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'pc_shipping_' . $id );
			Shipping::authorize( $id );
			$input     = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
			$operation = $input['operation'] ?? '';
			if ( ( $input['environment'] ?? '' ) !== Shipping::mode() ) {
				throw new \RuntimeException( esc_html__( 'The shipping environment changed. Reload this page before continuing.', 'parameter-commerce' ) );
			}
			$notice = __( 'Shipping information saved.', 'parameter-commerce' );
			if ( ! $id ) {
				$notice = self::configuration( $operation, $input );
			} elseif ( in_array( $operation, array( 'prepare', 'lookup_weight' ), true ) ) {
				if ( 'prepare' === $operation && empty( $input['confirmed'] ) ) {
					throw new \RuntimeException( esc_html__( 'Confirm the parcel before saving its weight.', 'parameter-commerce' ) );
				}
				if ( 'lookup_weight' === $operation ) {
					$input['weight_g'] = '';
				}
				Shipping::prepare( $id, $input, 'prepare' === $operation );
			} elseif ( 'create' === $operation && ! empty( $input['submit_confirm'] ) ) {
				Shipping::create( $id, (string) ( $input['review'] ?? '' ) );
			} elseif ( 'reconcile' === $operation ) {
				Shipping::reconcile( $id );
			} elseif ( 'cancel' === $operation && ! empty( $input['cancel_confirm'] ) ) {
				Shipping::cancel( $id );
			} elseif ( 'label' === $operation ) {
				$label = Shipping::label( $id );
				nocache_headers();
				header( 'Referrer-Policy: no-referrer' );
				echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>' . esc_html__( 'Shipping label', 'parameter-commerce' ) . '</title><body><h1>' . esc_html__( 'Shipping label', 'parameter-commerce' ) . '</h1><p><a target="_blank" rel="noopener noreferrer" href="' . esc_url( $label['url'] ) . '">' . esc_html__( 'Open printable carrier label', 'parameter-commerce' ) . ' (' . esc_html( $label['type'] ) . ')</a></p><p><a href="' . esc_url( self::url( $id ) ) . '">' . esc_html__( 'Back to shipment', 'parameter-commerce' ) . '</a></p></body></html>';
				exit;
			} else {
				throw new \RuntimeException( esc_html__( 'Choose and confirm a supported shipping action.', 'parameter-commerce' ) );
			}
		} catch ( \Throwable $error ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Shipping access denied.', 'parameter-commerce' ), '', array( 'response' => 403 ) );
			}
			$notice = $error->getMessage();
		}
		set_transient( 'pc_shipping_notice_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( self::url( $id ) );
		exit;
	}

	/**
	 * Save no secrets through WordPress forms. Environment switch is independent.
	 *
	 * @param string $operation Selected operation.
	 * @param array  $input Sanitized fields.
	 * @return string
	 * @throws \RuntimeException On invalid configuration.
	 */
	private static function configuration( $operation, $input ) {
		if ( 'mode' === $operation ) {
			$mode = $input['mode'] ?? 'disabled';
			if ( ! in_array( $mode, array( 'disabled', 'sandbox', 'production' ), true ) ) {
				throw new \RuntimeException( esc_html__( 'Invalid shipping environment.', 'parameter-commerce' ) );
			}
			if ( 'production' === $mode ) {
				new YunExpress_Client( $mode );
			}
			update_option( 'pc_yunexpress_mode', $mode, false );
		} elseif ( 'connection' === $operation ) {
			$response = ( new YunExpress_Client( Shipping::mode() ) )->request( 'products', array( 'country_code' => 'US' ) );
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( esc_html( $response->get_error_message() ) );
			}
			set_transient( 'pc_shipping_services_' . Shipping::mode(), (array) ( $response['detail'] ?? array() ), HOUR_IN_SECONDS );
			return __( 'Authentication and signed service lookup succeeded. The catalogue is not confirmation that a service is enabled for your account.', 'parameter-commerce' );
		} elseif ( 'settings' === $operation && 'disabled' !== Shipping::mode() ) {
			$sender = array( 'country_code' => 'CN' );
			foreach ( self::sender_fields() as $field => $label ) {
				if ( 'address' === $field ) {
					$sender['address_lines'] = array_filter( array( $input['sender'][ $field ] ?? '' ) );
				} else {
					$sender[ $field ] = $input['sender'][ $field ] ?? '';
				}
			}
			update_option(
				'pc_yunexpress_settings_' . Shipping::mode(),
				array(
					'sender'       => $sender,
					'product_code' => $input['product_code'] ?? '',
					'revision'     => $input['revision'] ?? '1',
					'parcel'       => Shipping::packaging( $input ),
				),
				false
			);
		} else {
			throw new \RuntimeException( esc_html__( 'Choose a supported configuration action.', 'parameter-commerce' ) );
		}
		return __( 'Shipping settings saved. Existing shipments are retained.', 'parameter-commerce' );
	}
}
