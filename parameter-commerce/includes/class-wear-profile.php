<?php
/**
 * Optional wearing guidance from the shared published specification ledger.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No guessed product values, duplicate metadata or external dependencies. */
final class Wear_Profile {
	const KEYS = array( 'thickness_grade', 'cushion_location', 'footwear_fit' );

	/**
	 * Prevent duplicate classic/block summaries.
	 *
	 * @var array
	 */
	private static $rendered = array();

	/** Register native product and loop placements.
	 */
	public static function register_hooks() {
		add_action( 'woocommerce_single_product_summary', array( self::class, 'panel' ), 24 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( self::class, 'card' ), 12 );
		add_filter( 'render_block_woocommerce/product-summary', array( self::class, 'block' ), 10, 3 );
		add_filter( 'render_block_core/post-excerpt', array( self::class, 'block' ), 10, 3 );
		add_filter( 'woocommerce_blocks_hook_compatibility_additional_data', array( self::class, 'block_hooks' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param array  $hooks Existing hooks.
	 * @param string $template Template.
	 * @return array
	 */
	public static function block_hooks( $hooks, $template ) {
		if ( 'SingleProductTemplateCompatibility' === $template ) {
			$hooks[] = array(
				'hook'     => 'woocommerce_single_product_summary',
				'function' => array( self::class, 'panel' ),
				'priority' => 24,
			);
		}
		return $hooks;
	}

	/** Tiny stylesheet for server-rendered summaries.
	 */
	public static function assets() {
		if ( Plugin::has_woocommerce() ) {
			wp_enqueue_style( 'pc-wear-profile', plugins_url( 'assets/wear-profile.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '1.0.0' );
		}
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @return array Shared, translated enum values across every consumer.
	 */
	public static function labels() {
		return array(
			'thin'                => __( 'Thin', 'parameter-commerce' ),
			'moderate'            => __( 'Medium thickness', 'parameter-commerce' ),
			'thick'               => __( 'Thick', 'parameter-commerce' ),
			'no-terry'            => __( 'No terry', 'parameter-commerce' ),
			'partial-terry'       => __( 'Partial terry', 'parameter-commerce' ),
			'terry-sole'          => __( 'Terry sole', 'parameter-commerce' ),
			'full-terry'          => __( 'Full terry', 'parameter-commerce' ),
			'terry-cushioning'    => __( 'Terry — coverage unspecified', 'parameter-commerce' ),
			'close-fitting-shoes' => __( 'Suitable for close-fitting shoes', 'parameter-commerce' ),
			'regular-fit-shoes'   => __( 'Suitable for regular-fit shoes', 'parameter-commerce' ),
			'roomier-shoes'       => __( 'Best with roomier shoes', 'parameter-commerce' ),
		);
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param array $filters Validated input.
	 * @return bool
	 */
	public static function active_filters( array $filters ) {
		foreach ( self::KEYS as $key ) {
			if ( '' !== ( $filters[ $key ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param array $filters Input.
	 * @throws Failure Invalid or disabled choice.
	 */
	public static function validate_filters( array $filters ) {
		$definitions = Metrics::all();
		foreach ( self::KEYS as $key ) {
			$value = $filters[ $key ] ?? '';
			if ( '' !== $value && ( ! $definitions[ $key ]['filterable'] || ! in_array( $value, $definitions[ $key ]['choices'], true ) ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Structured error is escaped by the catalog controller.
				throw new Failure( array( $key => __( 'Invalid catalog filter.', 'parameter-commerce' ) ) );
			}
		}
	}

	/**
	 * Read the same exact-scope projection as the product and comparison tables.
	 *
	 * @param int $parent_id Parent ID.
	 * @param int $variation Child ID.
	 * @return array
	 */
	public static function metrics( $parent_id, $variation = 0 ) {
		if ( ! Public_Specifications::catalog_visible( $parent_id, $variation ) ) {
			return array();
		}
		try {
			$specs = Repository::public_specifications( $parent_id, $variation );
		} catch ( Failure $error ) {
			return array();
		}
		return array_filter(
			array_intersect_key( $specs['groups']['construction']['metrics'], array_flip( self::KEYS ) ),
			static fn( $metric ) => null !== $metric['value'] && ! empty( $metric['record'] )
		);
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param int   $parent_id Parent.
	 * @param int   $variation Child.
	 * @param array $filters Input.
	 * @return bool
	 */
	public static function matches( $parent_id, $variation, array $filters ) {
		if ( ! self::active_filters( $filters ) ) {
			return true;
		}
		$metrics = self::metrics( $parent_id, $variation );
		foreach ( self::KEYS as $key ) {
			if ( '' !== ( $filters[ $key ] ?? '' ) && ( $metrics[ $key ]['value'] ?? null ) !== $filters[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param array $filters Current selections.
	 * @param array $keys Optional subset of wearing controls.
	 */
	public static function filter_controls( array $filters, array $keys = self::KEYS ) {
		$definitions = Metrics::all();
		$labels      = self::labels();
		foreach ( array_intersect( self::KEYS, $keys ) as $key ) {
			if ( ! $definitions[ $key ]['filterable'] ) {
				continue;
			}
			echo '<label>' . esc_html( $definitions[ $key ]['label'] ) . '<select name="pc_' . esc_attr( $key ) . '"><option value="">' . esc_html__( 'Any', 'parameter-commerce' ) . '</option>';
			foreach ( $definitions[ $key ]['choices'] as $value ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters[ $key ] ?? '', $value, false ) . '>' . esc_html( $labels[ $value ] ) . '</option>';
			}
			echo '</select></label>';
		}
	}

	/**
	 * Product summary lists scoped values; cards show only the thickness label.
	 * Sample records retain their scope, and child records never become parent facts.
	 *
	 * @param \WC_Product $product Product.
	 * @param bool        $compact Card.
	 * @return string
	 */
	public static function render( $product, $compact = false ) {
		if ( ! $product ) {
			return '';
		}
		$variation = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$parent_id = $variation ? $product->get_parent_id() : $product->get_id();
		$metrics   = self::metrics( $parent_id, $variation );
		$rows      = '';
		$shown     = array();
		foreach ( self::KEYS as $key ) {
			if ( ! isset( $metrics[ $key ] ) || ( $compact && 'thickness_grade' !== $key ) ) {
				continue;
			}
			$shown[ $key ] = true;
			$metric        = $metrics[ $key ];
			$scope         = $metric['record']['size_scope'] ?? '';
			$rows         .= '<div data-wear-metric="' . esc_attr( $key ) . '"><dt>' . esc_html( $metric['label'] ) . '</dt><dd><strong>' . esc_html( Public_Specifications::value( $metric ) ) . '</strong>';
			if ( $scope ) {
				$rows .= '<small>' . esc_html( $scope ) . '</small>';
			}
			if ( ! $compact ) {
				$rows .= self::public_note( $metric );
			}
			$rows .= '</dd></div>';
		}
		// All child-specific guidance is visible without JavaScript or stale selection state.
		if ( ! $compact && ! $variation && $product->is_type( 'variable' ) ) {
			foreach ( self::scopes( $parent_id ) as $child_id ) {
				if ( ! $child_id ) {
					continue;
				}
				foreach ( self::metrics( $parent_id, $child_id ) as $key => $metric ) {
					if ( (int) $metric['record']['wc_variation_id'] !== (int) $child_id ) {
						continue;
					}
					$shown[ $key ] = true;
					$child         = wc_get_product( $child_id );
					$rows         .= '<div data-wear-metric="' . esc_attr( $key ) . '"><dt>' . esc_html( $metric['label'] ) . '</dt><dd><strong>' . esc_html( Public_Specifications::value( $metric ) ) . '</strong><small>' . esc_html( Public_Specifications::scope_label( $child ) . ' · ' . $metric['record']['size_scope'] ) . '</small>' . self::public_note( $metric ) . '</dd></div>';
				}
			}
		}
		if ( $compact && ! $variation && $product->is_type( 'variable' ) ) {
			$values = array();
			foreach ( self::scopes( $parent_id ) as $child_id ) {
				$child_metric = self::metrics( $parent_id, $child_id )['thickness_grade'] ?? null;
				if ( $child_metric && (int) $child_metric['record']['wc_variation_id'] === $child_id ) {
					$values[] = Public_Specifications::value( $child_metric );
				}
			}
			if ( $values ) {
				$rows .= '<div><dt>' . esc_html__( 'Thickness by option', 'parameter-commerce' ) . '</dt><dd>' . esc_html( implode( ' / ', array_unique( $values ) ) ) . '<small>' . esc_html__( 'Recorded options only; see product details.', 'parameter-commerce' ) . '</small></dd></div>';
			}
		}
		if ( '' === $rows ) {
			return '';
		}
		$html = '<section class="pc-wear-profile' . ( $compact ? ' pc-wear-card' : '' ) . '" aria-label="' . esc_attr__( 'Thickness & shoe pairing', 'parameter-commerce' ) . '"><dl>' . $rows . '</dl>';
		if ( ! $compact && isset( $shown['thickness_grade'] ) ) {
			$html .= '<p>' . esc_html__( 'Thickness is a relative assessment, not a quality rating.', 'parameter-commerce' ) . '</p>';
		}
		if ( ! $compact && isset( $shown['footwear_fit'] ) ) {
			$html .= '<p>' . esc_html__( 'Shoe pairing is guidance; comfort also depends on the room inside your shoes.', 'parameter-commerce' ) . '</p>';
		}
		return $html . '</section>';
	}

	/**
	 * Render only the published public note from the shared projection.
	 *
	 * @param array $metric Public metric, never a staff evidence record.
	 * @return string
	 */
	private static function public_note( array $metric ) {
		$note = trim( $metric['record']['note'] ?? '' );
		return '' === $note ? '' : '<small class="pc-wear-note">' . nl2br( esc_html( $note ) ) . '</small>';
	}

	/**
	 * Find only child scopes with wearing guidance, avoiding all measurement scopes.
	 *
	 * @param int $product_id Parent ID.
	 * @return array
	 */
	private static function scopes( $product_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded indexed candidate lookup; public projection rechecks every record.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT wc_variation_id FROM %i WHERE wc_product_id=%d AND wc_variation_id>0 AND metric_key IN (%s,%s,%s) AND status='published' AND published_slot IS NOT NULL LIMIT 100", $wpdb->prefix . 'pc_claims', $product_id, 'thickness_grade', 'cushion_location', 'footwear_fit' ) ) );
	}

	/** Classic summary.
	 */
	public static function panel() {
		global $product;
		if ( $product && ! isset( self::$rendered[ $product->get_id() ] ) ) {
			self::$rendered[ $product->get_id() ] = true;
			echo wp_kses_post( self::render( $product ) );
		}
	}

	/** Classic loop card.
	 */
	public static function card() {
		global $product;
		echo wp_kses_post( self::render( $product, true ) );
	}

	/**
	 * Shared wearing guidance.
	 *
	 * @param string    $html Markup.
	 * @param array     $parsed Block.
	 * @param \WP_Block $block Context.
	 * @return string
	 */
	public static function block( $html, $parsed, $block ) {
		if ( ! Plugin::has_woocommerce() || ! is_product() ) {
			return $html;
		}
		$id = (int) ( $block->context['postId'] ?? 0 );
		if ( get_queried_object_id() !== $id || isset( self::$rendered[ $id ] ) || isset( $block->context['queryId'] ) ) {
			return $html;
		}
		self::$rendered[ $id ] = true;
		return $html . self::render( wc_get_product( $id ) );
	}
}
