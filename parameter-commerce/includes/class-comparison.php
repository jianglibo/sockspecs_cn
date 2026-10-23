<?php
/**
 * Bounded, read-only product comparison using the existing public projection.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Controllers escape Failure messages.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Selections live in ordinary URLs, never in customer records or custom tables. */
final class Comparison {
	/** Register the shortcode and scoped assets. @return void */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/** Register the server-rendered page. @return void */
	public static function register() {
		add_shortcode( 'pc_compare', array( self::class, 'render' ) );
	}

	/** Managed page URL; a missing page disables entry controls. @return string */
	public static function url() {
		$page = get_page_by_path( 'compare' );
		return $page && 'publish' === $page->post_status && ! $page->post_password && has_shortcode( $page->post_content, 'pc_compare' ) ? get_permalink( $page ) : '';
	}

	/** Load only on comparison, catalog and product screens. @return void */
	public static function assets() {
		$post = get_post();
		if ( ( $post && ( has_shortcode( $post->post_content, 'pc_compare' ) || has_shortcode( $post->post_content, 'pc_catalog' ) ) ) || ( Plugin::has_woocommerce() && function_exists( 'is_product' ) && is_product() ) ) {
			wp_enqueue_style( 'pc-comparison', plugins_url( 'assets/comparison.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.20' );
		}
	}

	/**
	 * Reject malformed or oversized selections before querying products.
	 *
	 * @param mixed $value Comma-separated native product/variation IDs.
	 * @return array Unique IDs, in visitor order.
	 * @throws Failure Invalid selection.
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 43 || ( '' !== $value && ! preg_match( '/^[1-9][0-9]{0,9}(?:,[1-9][0-9]{0,9}){0,3}$/D', $value ) ) ) {
			throw new Failure( array( 'comparison' => __( 'Select up to four products or sizes for comparison.', 'parameter-commerce' ) ) );
		}
		$ids = '' === $value ? array() : explode( ',', $value );
		foreach ( $ids as $id ) {
			if ( (float) $id > 2147483647 ) {
				throw new Failure( array( 'comparison' => __( 'Invalid comparison selection.', 'parameter-commerce' ) ) );
			}
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/** Read-only URL selection; no mutation nonce is needed. @return array */
	public static function current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict bounded parser; IDs are reauthorized before any display.
		return self::parse( isset( $_GET['pc_compare'] ) ? wp_unslash( $_GET['pc_compare'] ) : '' );
	}

	/**
	 * Recheck native visibility on every read, including logged-in staff requests.
	 *
	 * @param array $ids Parsed IDs.
	 * @return array Safe public product scopes (no measurement reads yet).
	 * @throws Failure On a missing dependency or unavailable selection.
	 */
	public static function items( array $ids ) {
		$ids = self::parse( implode( ',', $ids ) );
		if ( ! Plugin::has_woocommerce() || Schema::VERSION !== (int) get_option( 'pc_schema_version' ) ) {
			throw new Failure( array( 'comparison' => __( 'Comparison is temporarily unavailable.', 'parameter-commerce' ) ), 503 );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$product   = wc_get_product( $id );
			$variation = $product && $product->is_type( 'variation' ) ? $id : 0;
			$parent_id = $variation ? $product->get_parent_id() : $id;
			$parent    = $variation ? wc_get_product( $parent_id ) : $product;
			if ( ! $parent || ! $parent->is_type( array( 'simple', 'variable' ) ) || ! in_array( $parent->get_catalog_visibility(), array( 'visible', 'catalog' ), true ) || ! Public_Specifications::visible( $parent_id, $variation ) ) {
				throw new Failure( array( 'comparison' => __( 'A selected product or size is no longer available for comparison. Clear the selection and choose from the catalog.', 'parameter-commerce' ) ), 404 );
			}
			$items[] = array(
				'product'      => $product,
				'parent_id'    => $parent_id,
				'variation_id' => $variation,
			);
		}
		return $items;
	}

	/**
	 * Carry only bounded selection state to a known local destination.
	 *
	 * @param string     $url Destination.
	 * @param array|null $ids Explicit selection or current URL selection.
	 * @return string
	 */
	public static function carry( $url, $ids = null ) {
		try {
			$ids = null === $ids ? self::current() : self::parse( implode( ',', $ids ) );
			return $ids ? add_query_arg( 'pc_compare', implode( ',', $ids ), $url ) : remove_query_arg( 'pc_compare', $url );
		} catch ( Failure $error ) {
			return remove_query_arg( 'pc_compare', $url );
		}
	}

	/** Hidden GET input for existing catalog/scope forms. @return void */
	public static function hidden_selection() {
		try {
			$ids = self::current();
			if ( $ids ) {
				echo '<input type="hidden" name="pc_compare" value="' . esc_attr( implode( ',', $ids ) ) . '">';
			}
		} catch ( Failure $error ) {
			// Invalid state is never propagated through a form.
			return;
		}
	}

	/**
	 * Catalog/product add/remove link; a fifth item has no actionable add control.
	 *
	 * @param int    $id Native exact scope.
	 * @param string $destination Optional catalog URL; otherwise comparison page.
	 * @return void
	 */
	public static function action( $id, $destination = '' ) {
		if ( ! self::url() ) {
			return;
		}
		try {
			$ids = self::current();
			self::items( $ids );
			$item     = self::items( array( $id ) )[0];
			$selected = in_array( $id, $ids, true );
			if ( ! $selected && count( $ids ) >= 4 ) {
				echo '<p class="pc-compare-limit">' . esc_html__( 'Four selected. Remove an item before adding another.', 'parameter-commerce' ) . '</p>';
				return;
			}
			$next  = $selected ? array_values( array_diff( $ids, array( $id ) ) ) : array_merge( $ids, array( $id ) );
			$label = $selected ? __( 'Remove from comparison', 'parameter-commerce' ) : __( 'Add to comparison', 'parameter-commerce' );
			echo '<p><a class="pc-compare-action" href="' . esc_url( self::carry( $destination ? $destination : self::url(), $next ) ) . '" aria-label="' . esc_attr( $label . ': ' . Public_Specifications::scope_label( $item['product'] ) ) . '">' . esc_html( $label ) . '</a></p>';
		} catch ( Failure $error ) {
			echo '<p class="pc-compare-error">' . esc_html( $error->getMessage() ) . ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Clear comparison', 'parameter-commerce' ) . '</a></p>';
		}
	}

	/**
	 * Selection summary with named remove links and an explicit reset.
	 *
	 * @param string $destination Return location for removal links.
	 * @return void
	 */
	public static function toolbar( $destination = '' ) {
		if ( ! self::url() ) {
			return;
		}
		echo '<nav class="pc-compare-selection" aria-label="' . esc_attr__( 'Comparison selection', 'parameter-commerce' ) . '">';
		try {
			$ids   = self::current();
			$items = self::items( $ids );
			/* translators: %d: number of selected products or variations, at most four. */
			echo '<p>' . esc_html( sprintf( __( '%d of 4 comparison items selected', 'parameter-commerce' ), count( $items ) ) ) . '</p><ul>';
			foreach ( $items as $item ) {
				$p     = $item['product'];
				$next  = array_values( array_diff( $ids, array( $p->get_id() ) ) );
				$label = Public_Specifications::scope_label( $p );
				echo '<li>' . esc_html( $label ) . ' <a href="' . esc_url( self::carry( $destination ? $destination : self::url(), $next ) ) . '" aria-label="' . esc_attr( __( 'Remove from comparison', 'parameter-commerce' ) . ': ' . $label ) . '">' . esc_html__( 'Remove', 'parameter-commerce' ) . '</a></li>';
			}
			echo '</ul><a href="' . esc_url( self::carry( self::url(), $ids ) ) . '">' . esc_html__( 'Compare selections', 'parameter-commerce' ) . '</a> · ';
		} catch ( Failure $error ) {
			echo '<p role="alert">' . esc_html( $error->getMessage() ) . '</p>';
		}
		echo '<a href="' . esc_url( remove_query_arg( 'pc_compare', $destination ? $destination : self::url() ) ) . '">' . esc_html__( 'Clear comparison', 'parameter-commerce' ) . '</a></nav>';
	}

	/**
	 * Normalize JSON object key ordering only, not scientific terminology or units.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( is_object( $value ) ) {
			$properties = get_object_vars( $value );
			ksort( $properties );
			return (object) array_map( array( self::class, 'canonical' ), $properties );
		}
		return is_array( $value ) ? array_map( array( self::class, 'canonical' ), $value ) : $value;
	}

	/**
	 * Conservative metadata checks are not a scientific equivalence assessment.
	 *
	 * @param array $cells Public metric cells, with explicit scope_type.
	 * @param array $definition Existing dictionary policy.
	 * @return string Translated explanation; never a grade or winner.
	 */
	public static function assess( array $cells, array $definition ) {
		$available = array_values( array_filter( $cells, static fn( $cell ) => null !== $cell['value'] && 'unknown' !== $cell['value'] ) );
		if ( count( $available ) < 2 ) {
			return __( 'Not enough measurements to compare.', 'parameter-commerce' );
		}
		foreach ( $available as $cell ) {
			if ( 'platform_observed' === ( $cell['record']['source_type'] ?? '' ) ) {
				return __( 'Based on direct sample observations; see any recorded scope and notes. These are not laboratory measurements or a quality ranking.', 'parameter-commerce' );
			}
		}
		$first = $available[0];
		// Color identity is not a different weight basis. Keep the actual packaging uncertainty visible.
		$first_conditions = json_decode( $first['record']['conditions_json'], true );
		if ( in_array( $first['record']['metric_key'] ?? '', array( 'pair_weight_g', 'catalog_pair_weight_g' ), true ) && count( $available ) === count( $cells ) ) {
			$net_pairs = array_filter(
				$available,
				static function ( $cell ) use ( $first ) {
					$conditions = json_decode( $cell['record']['conditions_json'], true );
					return 'g' === $cell['unit'] && 'supplier_declared' === $cell['record']['source_type'] && $cell['scope_type'] === $first['scope_type'] && 'supplier_catalog' === ( $conditions['basis'] ?? '' ) && 'one pair (operator confirmed)' === ( $conditions['quantity'] ?? '' ) && 'excluded' === ( $conditions['packaging'] ?? '' );
				}
			);
			if ( count( $net_pairs ) === count( $available ) ) {
				return __( 'Manufacturer workbook weights per pair, excluding packaging. Compare the stated sizes; these are not independently measured results.', 'parameter-commerce' );
			}
			$declared_pairs = array_filter(
				$available,
				static function ( $cell ) use ( $first, $first_conditions ) {
					$conditions = json_decode( $cell['record']['conditions_json'], true );
					return 'g' === $cell['unit'] && 'supplier_declared' === $cell['record']['source_type'] && $cell['scope_type'] === $first['scope_type'] && in_array( $conditions['basis'] ?? '', array( 'supplier_listing', 'supplier_catalog' ), true ) && ( $first_conditions['basis'] ?? '' ) === $conditions['basis'] && 'one pair (operator confirmed)' === ( $conditions['quantity'] ?? '' ) && 'inclusion unconfirmed' === ( $conditions['packaging'] ?? '' );
				}
			);
			if ( count( $declared_pairs ) === count( $available ) ) {
				return __( 'Declared pair weights. Packaging inclusion is unconfirmed; these are not verified net weights.', 'parameter-commerce' );
			}
		}
		foreach ( $available as $cell ) {
			if ( $cell['unit'] !== $first['unit'] ) {
				return __( 'Not comparable: different units. Original values are shown without conversion.', 'parameter-commerce' );
			}
			if ( $cell['scope_type'] !== $first['scope_type'] || $cell['record']['size_scope'] !== $first['record']['size_scope'] ) {
				return __( 'Not comparable: different product or specimen scopes.', 'parameter-commerce' );
			}
			if ( $cell['record']['source_type'] !== $first['record']['source_type'] ) {
				return __( 'Not comparable: different evidence source categories.', 'parameter-commerce' );
			}
		}
		$tested = 'supplier_declared' !== $first['record']['source_type'] || 'matching_method_version_conditions_unit' === $definition['comparison_policy'];
		foreach ( $available as $cell ) {
			$r          = $cell['record'];
			$conditions = json_decode( $r['conditions_json'] );
			if ( ! is_object( $conditions ) || ( $tested && ( '' === $r['method_code'] || '' === $r['method_version'] || '' === $r['size_scope'] || ! get_object_vars( $conditions ) ) ) ) {
				return __( 'Comparability not established: method, version, conditions or specimen scope is incomplete.', 'parameter-commerce' );
			}
			if ( $r['method_code'] !== $first['record']['method_code'] || $r['method_version'] !== $first['record']['method_version'] || wp_json_encode( self::canonical( $conditions ), JSON_PRESERVE_ZERO_FRACTION ) !== wp_json_encode( self::canonical( json_decode( $first['record']['conditions_json'] ) ), JSON_PRESERVE_ZERO_FRACTION ) ) {
				return __( 'Not comparable: different methods, versions or test conditions.', 'parameter-commerce' );
			}
		}
		if ( count( $available ) !== count( $cells ) ) {
			return __( 'Incomplete comparison: one or more measurements are unavailable.', 'parameter-commerce' );
		}
		return 'supplier_declared' === $first['record']['source_type'] ? __( 'Declared values only; these are not independently verified test results.', 'parameter-commerce' ) : __( 'Recorded units, source category, method and specimen conditions match. Review the evidence; this does not establish equivalent quality.', 'parameter-commerce' );
	}

	/**
	 * Bounded public projection for two to four products or exact variations.
	 *
	 * @param string $selection Comma-separated IDs.
	 * @return array
	 * @throws Failure Invalid, unavailable or too-small selection.
	 */
	public static function data( $selection ) {
		$ids = self::parse( $selection );
		if ( count( $ids ) < 2 ) {
			throw new Failure( array( 'comparison' => __( 'Choose two to four products or sizes from the catalog to compare.', 'parameter-commerce' ) ) );
		}
		$items = self::items( $ids );
		foreach ( $items as &$item ) {
			$item['specifications'] = Repository::public_specifications( $item['parent_id'], $item['variation_id'] );
			$item['options']        = self::options( $item['product'] );
			// Exact child measurements remain separate; never turn them into parent claims.
			if ( ! $item['variation_id'] && $item['product']->is_type( 'variable' ) ) {
				foreach ( array_slice( $item['product']->get_children(), 0, 100 ) as $child_id ) {
					if ( ! Public_Specifications::visible( $item['parent_id'], $child_id ) ) {
						continue;
					}
					$child_specs = Repository::public_specifications( $item['parent_id'], $child_id );
					foreach ( Wear_Profile::KEYS as $wear_key ) {
						$wear_metric = $child_specs['groups']['construction']['metrics'][ $wear_key ];
						if ( null !== $wear_metric['value'] && (int) $wear_metric['record']['wc_variation_id'] === (int) $child_id ) {
							$cell =& $item['specifications']['groups']['construction']['metrics'][ $wear_key ];
							if ( empty( $cell['variants'] ) && null !== $cell['value'] ) {
								$cell['variants'] = array( $cell );
							}
							$cell['variants'][] = $wear_metric;
							unset( $cell );
						}
					}

					foreach ( $child_specs['groups']['measurement']['metrics'] as $key => $metric ) {
						if ( null !== $metric['value'] && null === $item['specifications']['groups']['measurement']['metrics'][ $key ]['value'] && 0 !== strpos( $key, 'listing_' ) ) {
							$item['specifications']['groups']['measurement']['metrics'][ $key ]['variants'][] = $metric;
						}
					}
				}
			}
		}
		unset( $item );
		return array(
			'items'       => $items,
			'definitions' => Metrics::all(),
		);
	}

	/**
	 * Public native size/color choices, shared with the purchase options.
	 *
	 * @param \WC_Product $product Selected product or exact variation.
	 * @return array Display strings, without inventing shoe-size conversions.
	 */
	private static function options( $product ) {
		$options = array(
			'size'  => array(),
			'color' => array(),
		);
		$choices = $product->is_type( 'variable' ) ? array_slice( $product->get_children(), 0, 100 ) : array( $product->get_id() );
		foreach ( $choices as $id ) {
			$child = wc_get_product( $id );
			if ( ! $child || ( $child->is_type( 'variation' ) && ! Public_Specifications::visible( $child->get_parent_id(), $id ) ) ) {
				continue;
			}
			foreach ( array_keys( $options ) as $key ) {
				$value = $child->get_attribute( 'pa_' . $key );
				$value = $value ? $value : $child->get_attribute( $key );
				if ( '' !== $value ) {
					$options[ $key ][] = $value;
				}
			}
		}
		return array_map( static fn( $values ) => implode( ' / ', array_unique( $values ) ), $options );
	}

	/** Render the ordinary WordPress comparison page. @return string */
	public static function render() {
		ob_start();
		echo '<section class="pc-comparison"><h2>' . esc_html__( 'Compare socks', 'parameter-commerce' ) . '</h2>';
		self::toolbar();
		if ( Plugin::has_woocommerce() ) {
			echo '<p><a href="' . esc_url( self::carry( Catalog::url() ) ) . '">' . esc_html__( 'Choose products from the catalog', 'parameter-commerce' ) . '</a></p>';
		}
		try {
			$ids = self::current();
			if ( count( $ids ) < 2 ) {
				echo '<p role="status">' . esc_html__( 'Choose two to four products or sizes from the catalog to compare.', 'parameter-commerce' ) . '</p>';
			} else {
				$data = self::data( implode( ',', $ids ) );
				require dirname( __DIR__ ) . '/templates/comparison.php';
			}
		} catch ( Failure $error ) {
			echo '<p role="alert">' . esc_html( $error->getMessage() ) . '</p>';
		}
		echo '</section>';
		return ob_get_clean();
	}
}
