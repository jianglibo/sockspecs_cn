<?php
/**
 * Server-rendered staff editor and POST controller.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Structured errors are escaped by the admin controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Uses WordPress capabilities and nonces on every privileged request. */
final class Admin {
	/** Register admin-only integration points. @return void */
	public static function register_hooks() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_pc_claim', array( self::class, 'handle' ) );
		add_action( 'admin_post_nopriv_pc_claim', array( self::class, 'handle' ) );
		Product_Picker::register_hooks();
	}

	/** Add the WooCommerce submenu. @return void */
	public static function menu() {
		if ( Plugin::has_woocommerce() ) {
			if ( current_user_can( 'edit_others_shop_orders' ) ) {
				add_submenu_page( 'woocommerce', __( 'Parameter Specs', 'parameter-commerce' ), __( 'Parameter Specs', 'parameter-commerce' ), 'manage_woocommerce', 'pc-specs', array( self::class, 'page' ) );
			} else {
				// A custom specs-only staff role need not have access to customer orders.
				add_menu_page( __( 'Parameter Specs', 'parameter-commerce' ), __( 'Parameter Specs', 'parameter-commerce' ), 'manage_woocommerce', 'pc-specs', array( self::class, 'page' ), 'dashicons-clipboard' );
			}
		}
	}

	/**
	 * Validate HTTP intent and execute an authorized repository operation.
	 *
	 * @return void
	 * @throws Failure Caught locally and rendered as an escaped error.
	 */
	public static function handle() {
		try {
			Repository::authorize();
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				throw new Failure( array( 'request' => __( 'Use POST for changes.', 'parameter-commerce' ) ), 405 );
			}
			check_admin_referer( 'pc_claim', 'pc_nonce' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validator enforces bounded scalar fields; operation/IDs are sanitized below.
			$input     = wp_unslash( $_POST );
			$operation = isset( $input['pc_operation'] ) && is_string( $input['pc_operation'] ) ? sanitize_key( $input['pc_operation'] ) : '';
			$id        = self::integer( $input, 'claim_id' );
			$revision  = self::integer( $input, 'revision' );
			if ( in_array( $operation, array( 'save', 'edit' ), true ) ) {
				if ( ! isset( $input['evidence'] ) || ! is_array( $input['evidence'] ) ) {
					throw new Failure( array( 'evidence' => __( 'Evidence fields are required, even for incomplete drafts.', 'parameter-commerce' ) ) );
				}
				if ( 'edit' === $operation ) {
					$publish = isset( $input['save_mode'] ) && 'publish' === $input['save_mode'];
					$id      = Repository::edit( $input, $input['evidence'], $id, $revision, $publish, self::integer( $input, 'current_id' ), self::integer( $input, 'current_revision' ) );
				} else {
					$id = Repository::save( $input, $input['evidence'], $id, $revision );
				}
			} elseif ( 'fork' === $operation ) {
				$id = Repository::fork( $id, $revision );
			} elseif ( 'transition' === $operation ) {
				if ( ! isset( $input['confirm'] ) || 'yes' !== $input['confirm'] ) {
					throw new Failure( array( 'confirm' => __( 'Confirm the status change after reviewing this claim.', 'parameter-commerce' ) ) );
				}
				$target = isset( $input['target'] ) && is_string( $input['target'] ) ? sanitize_key( $input['target'] ) : '';
				$id     = Repository::transition( $id, $revision, $target, self::integer( $input, 'replace_id' ), self::integer( $input, 'replace_revision' ) );
			} elseif ( 'metric' === $operation ) {
				$key   = isset( $input['metric_key'] ) && is_string( $input['metric_key'] ) ? sanitize_key( $input['metric_key'] ) : '';
				$label = isset( $input['label_override'] ) && is_string( $input['label_override'] ) ? sanitize_text_field( $input['label_override'] ) : '';
				Repository::configure_metric( $key, $revision, $label, isset( $input['is_filterable'] ) );
			} else {
				throw new Failure( array( 'request' => __( 'Unknown operation.', 'parameter-commerce' ) ), 400 );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'pc-specs',
						'claim_id' => $id,
						'saved'    => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		} catch ( Failure $error ) {
			if ( 403 === $error->status || 503 === $error->status ) {
				wp_die( esc_html( $error->getMessage() ), '', array( 'response' => (int) $error->status ) );
			}
			status_header( $error->status );
			Product_Picker::assets();
			// Render the submitted draft again without losing input or silently saving it.
			// admin-post.php does not initialize the admin menu. Render a standalone
			// correction page using core styles, rather than an incomplete admin shell.
			echo '<!doctype html><html ';
			language_attributes();
			echo '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><title>';
			esc_html_e( 'Parameter Specs', 'parameter-commerce' );
			echo '</title>';
			wp_print_styles( array( 'common', 'forms', 'buttons', 'list-tables', 'pc-select2' ) );
			echo '</head><body class="wp-core-ui"><main style="margin:2rem">';
			self::page( $error->fields, isset( $input ) && in_array( $operation, array( 'save', 'edit' ), true ) ? $input : null, $id ?? 0 );
			echo '</main>';
			wp_print_footer_scripts();
			echo '</body></html>';
			exit;
		}
	}

	/**
	 * Render a bounded staff listing, form, preview, and audit history.
	 *
	 * @param array      $errors Field-specific messages.
	 * @param array|null $submitted Unsaved form input following validation failure.
	 * @param int        $requested_id Claim to redisplay after a failed status change.
	 * @return void
	 */
	public static function page( $errors = array(), $submitted = null, $requested_id = 0 ) {
		try {
			Repository::authorize();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation; all writes use a nonce-protected POST.
			$id             = isset( $_GET['claim_id'] ) && is_scalar( $_GET['claim_id'] ) ? absint( $_GET['claim_id'] ) : 0;
			$product_filter = isset( $_GET['product_id'] ) && is_scalar( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
			$metric_filter  = isset( $_GET['metric_filter'] ) && is_string( $_GET['metric_filter'] ) ? sanitize_key( wp_unslash( $_GET['metric_filter'] ) ) : '';
			$original_model = isset( $_GET['original_model'] ) && is_string( $_GET['original_model'] ) ? sanitize_text_field( wp_unslash( $_GET['original_model'] ) ) : '';
			$page_number    = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
			$audit_page     = isset( $_GET['audit_page'] ) && is_scalar( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$id = $requested_id ? $requested_id : $id;
			if ( $submitted ) {
				$id = self::integer( $submitted, 'claim_id' );
			}
			$claim                    = $id ? Repository::find( $id ) : null;
			$form                     = $claim ?? array(
				'metric_key'      => 'cotton_pct',
				'unit'            => '%',
				'source_type'     => 'supplier_declared',
				'status'          => 'draft',
				'wc_product_id'   => $product_filter,
				'wc_variation_id' => 0,
				'revision'        => 0,
				'evidence'        => array( 'visibility' => 'private' ),
			);
			$form['value']            = $form['value_num'] ?? $form['value_enum'] ?? '';
			$current                  = $claim ? Repository::current_claim( $claim ) : null;
			$form['current_id']       = $current['id'] ?? 0;
			$form['current_revision'] = $current['revision'] ?? 0;
			if ( $submitted ) {
				$form = array_merge( $form, $submitted );
				// Persist the submitted revision on an error: never silently upgrade a stale form.
			}
			$definitions = Metrics::all();
			$rows        = Repository::listing( $product_filter, $page_number, $metric_filter, $original_model );
			$history     = $id ? Repository::history( $id, $audit_page ) : array();
			$preview     = $claim ? Repository::preview( $claim ) : array();
			require dirname( __DIR__ ) . '/templates/admin-page.php';
		} catch ( Failure $error ) {
			wp_die( esc_html( $error->getMessage() ), '', array( 'response' => (int) $error->status ) );
		}
	}

	/**
	 * Get a bounded nonnegative ID/revision from request input.
	 *
	 * @param array  $input Request input.
	 * @param string $key Field.
	 * @return int
	 * @throws Failure On malformed input.
	 */
	private static function integer( array $input, $key ) {
		$value = $input[ $key ] ?? '0';
		if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || (float) $value > PHP_INT_MAX ) {
			throw new Failure( array( $key => __( 'Invalid record ID or revision.', 'parameter-commerce' ) ), 400 );
		}
		return (int) $value;
	}

	/**
	 * Translate a stored workflow status.
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'draft'     => __( 'Draft', 'parameter-commerce' ),
			'reviewed'  => __( 'Reviewed', 'parameter-commerce' ),
			'published' => __( 'Published', 'parameter-commerce' ),
			'retracted' => __( 'Retracted', 'parameter-commerce' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Translate a workflow button's action.
	 *
	 * @param string $status Target status.
	 * @return string
	 */
	public static function transition_label( $status ) {
		$labels = array(
			'draft'     => __( 'Return to draft', 'parameter-commerce' ),
			'reviewed'  => __( 'Mark reviewed', 'parameter-commerce' ),
			'published' => __( 'Publish', 'parameter-commerce' ),
			'retracted' => __( 'Retract', 'parameter-commerce' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Render an escaped, labelled form input without executable markup.
	 *
	 * @param array  $form Field values.
	 * @param string $key Field key.
	 * @param string $label Translated label.
	 * @param string $type Input type; textarea uses a separate element.
	 * @param string $prefix Input namespace for evidence fields.
	 * @return void
	 */
	public static function field( array $form, $key, $label, $type = 'text', $prefix = '' ) {
		$name  = $prefix ? $prefix . '[' . $key . ']' : $key;
		$id    = 'pc-' . $prefix . '-' . $key;
		$value = isset( $form[ $key ] ) && is_scalar( $form[ $key ] ) ? (string) $form[ $key ] : '';
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
		if ( 'textarea' === $type ) {
			echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '</p>';
	}
}
