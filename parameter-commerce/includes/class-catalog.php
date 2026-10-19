<?php
/**
 * Server-rendered catalog using native products and exact-scope evidence.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Failures are escaped by the calling controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Small bounded catalog; no copy of WooCommerce commerce data. */
final class Catalog {
	/** Register shortcode and documented WooCommerce query extension. @return void */
	public static function register_hooks() {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( self::class, 'query_args' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}
	/** Register public rendering. @return void */
	public static function register() {
		add_shortcode( 'pc_catalog', array( self::class, 'render' ) );
	}
	/** Catalog location with a native-shop fallback. @return string */
	public static function url() {
		$page = get_page_by_path( 'sockspecs-catalog' );
		return $page && 'publish' === $page->post_status ? get_permalink( $page ) : wc_get_page_permalink( 'shop' );
	}
	/** Load only where the catalog shortcode is used. @return void */
	public static function assets() {
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'pc_catalog' ) ) {
			wp_enqueue_style( 'pc-catalog', plugins_url( 'assets/catalog.css', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.3' );
			wp_enqueue_script( 'pc-catalog', plugins_url( 'assets/catalog.js', dirname( __DIR__ ) . '/parameter-commerce.php' ), array(), '0.5.3', true );
		}
	}
	/**
	 * Scope the compact page layout to the catalog, without editing the theme.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		$post = get_post();
		if ( is_singular( 'page' ) && $post && has_shortcode( $post->post_content, 'pc_catalog' ) ) {
			$classes[] = 'pc-catalog-page';
		}
		return $classes;
	}
	/**
	 * Keep catalog query adaptation inside WooCommerce's documented data-store hook.
	 *
	 * @param array $args WordPress query.
	 * @param array $vars WooCommerce query.
	 * @return array
	 */
	public static function query_args( $args, $vars ) {
		if ( ! empty( $vars['pc_catalog'] ) ) {
			$args['has_password']           = false;
			$args['update_post_meta_cache'] = true;
			$args['update_post_term_cache'] = true;
		}
		if ( ! empty( $vars['pc_parent_ids'] ) ) {
			$args['post_parent__in'] = array_map( 'absint', $vars['pc_parent_ids'] );
		}
		if ( ! empty( $vars['pc_supplier_id'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_pc_supplier_id',
				'value' => absint( $vars['pc_supplier_id'] ),
			);
		}
		return $args;
	}

	/**
	 * Validate a small allowlist; unknown query keys are never query fragments.
	 *
	 * @param array $input Unslashed request input.
	 * @return array
	 * @throws Failure On invalid ranges or choices.
	 */
	public static function filters( array $input ) {
		$result = array();
		foreach ( array( 'category', 'use', 'size', 'min_price', 'max_price', 'cotton_min', 'weight_max', 'weight_basis', 'sort', 'page' ) as $key ) {
			$value = $input[ 'pc_' . $key ] ?? '';
			if ( ! is_scalar( $value ) || strlen( (string) $value ) > 100 ) {
				throw new Failure( array( 'filter' => __( 'Invalid catalog filter.', 'parameter-commerce' ) ) );
			}
			$result[ $key ] = sanitize_text_field( (string) $value );
		}
		foreach ( array( 'min_price', 'max_price', 'cotton_min', 'weight_max' ) as $key ) {
			if ( '' !== $result[ $key ] && ( ! preg_match( '/^\d{1,10}(?:\.\d{1,6})?$/D', $result[ $key ] ) || ( 'cotton_min' === $key && (float) $result[ $key ] > 100 ) ) ) {
				throw new Failure( array( $key => __( 'Enter a nonnegative number; cotton percentage cannot exceed 100.', 'parameter-commerce' ) ) );
			}
		}
		if ( '' !== $result['min_price'] && '' !== $result['max_price'] && (float) $result['min_price'] > (float) $result['max_price'] ) {
			throw new Failure( array( 'price' => __( 'Minimum price cannot exceed maximum price.', 'parameter-commerce' ) ) );
		}
		if ( ! in_array( $result['use'], array( '', 'everyday', 'running', 'hiking', 'cold-weather' ), true ) || ! in_array( $result['sort'], array( '', 'price_asc', 'price_desc' ), true ) || ( '' !== $result['page'] && ( ! ctype_digit( $result['page'] ) || (int) $result['page'] < 1 || (float) $result['page'] > 10000 ) ) ) {
			throw new Failure( array( 'filter' => __( 'Invalid catalog filter.', 'parameter-commerce' ) ) );
		}
		if ( ! in_array( $result['weight_basis'], array( '', 'catalog', 'measured' ), true ) ) {
			throw new Failure( array( 'weight_basis' => __( 'Invalid weight basis.', 'parameter-commerce' ) ) );
		}
		$result['page'] = $result['page'] ? (int) $result['page'] : 1;
		return $result;
	}

	/**
	 * Fetch indexed eligible matches for active metric filters in one query.
	 *
	 * @param array $filters Validated filters.
	 * @param array $ids Candidate parents.
	 * @return array Exact scope => metric keys.
	 * @throws Failure On storage errors.
	 */
	private static function evidence_matches( array $filters, array $ids ) {
		$clauses     = array();
		$definitions = Metrics::all();
		global $wpdb;
		foreach ( array(
			'cotton_min' => 'cotton_pct',
			'weight_max' => 'pair_weight_g',
		) as $field => $metric ) {
			if ( '' === $filters[ $field ] ) {
				continue;
			}
			if ( ! $definitions[ $metric ]['filterable'] ) {
				throw new Failure( array( 'filter' => __( 'This measurement filter is currently disabled.', 'parameter-commerce' ) ) );
			}
			if ( 'cotton_pct' === $metric ) {
				$clauses[] = $wpdb->prepare( '(c.metric_key=%s AND c.unit=%s AND c.value_num >= %f)', $metric, '%', $filters[ $field ] );
			} elseif ( 'catalog' === ( $filters['weight_basis'] ?? '' ) ) {
				$clauses[] = $wpdb->prepare( '(c.metric_key IN (%s,%s) AND c.unit=%s AND c.value_num <= %f)', 'pair_weight_g', 'catalog_pair_weight_g', 'g', $filters[ $field ] );
			} else {
				$clauses[] = $wpdb->prepare( '(c.metric_key=%s AND c.unit=%s AND c.value_num <= %f)', $metric, 'g', $filters[ $field ] );
			}
		}
		if ( ! $clauses || ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$where        = implode( ' OR ', $clauses );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Plugin-owned indexed data only; fragments and IDs are prepared above/below.
		$sql = $wpdb->prepare( "SELECT c.wc_product_id,c.wc_variation_id,c.metric_key,c.published_slot,c.source_type,c.conditions_json FROM %i c INNER JOIN %i e ON e.id=c.evidence_id WHERE c.wc_product_id IN ($placeholders) AND c.status='published' AND c.verified_by>0 AND c.published_at IS NOT NULL AND c.value_num IS NOT NULL AND c.source_type IN ('supplier_declared','platform_measured','third_party_lab','supplier_report') AND ($where)", array_merge( array( $wpdb->prefix . 'pc_claims', $wpdb->prefix . 'pc_evidence' ), $ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		if ( $wpdb->last_error ) {
			throw new Failure( array( 'catalog' => __( 'Catalog evidence is temporarily unavailable.', 'parameter-commerce' ) ), 503 );
		}
		$matches = array();
		foreach ( $rows as $row ) {
			$scope = $row['wc_product_id'] . ':' . $row['wc_variation_id'];
			if ( $row['published_slot'] === $scope . ':' . $row['metric_key'] ) {
				if ( 'measured' === ( $filters['weight_basis'] ?? '' ) && 'pair_weight_g' === $row['metric_key'] && ! in_array( $row['source_type'], array( 'platform_measured', 'third_party_lab' ), true ) ) {
					continue; }
				if ( 'catalog' === ( $filters['weight_basis'] ?? '' ) && 'pair_weight_g' === $row['metric_key'] && 'supplier_catalog' !== ( json_decode( $row['conditions_json'], true )['basis'] ?? '' ) ) {
					continue;
				}
				$matches[ $scope ][ 'catalog_pair_weight_g' === $row['metric_key'] ? 'pair_weight_g' : $row['metric_key'] ] = true;
				if ( Repository::model_wide( $row ) ) {
					$matches[ $row['wc_product_id'] . ':model' ][ $row['metric_key'] ] = true;
				}
			}
		}
		return $matches;
	}

	/**
	 * Match a native Size attribute; ambiguous multi-size simple products do not qualify.
	 *
	 * @param \WC_Product $product Exact purchase scope.
	 * @param \WC_Product $scope_parent Parent.
	 * @param string      $size Requested size.
	 * @return bool
	 */
	private static function size_matches( $product, $scope_parent, $size ) {
		if ( '' === $size ) {
			return true;
		}
		$wanted = sanitize_title( $size );
		foreach ( array( 'pa_size', 'size' ) as $key ) {
			if ( $product->is_type( 'variation' ) ) {
				$attrs = $product->get_attributes();
				if ( ! array_key_exists( $key, $attrs ) ) {
					continue;
				}
				if ( '' !== $attrs[ $key ] ) {
					return sanitize_title( $attrs[ $key ] ) === $wanted;
				}
			}
			$attribute = $scope_parent->get_attributes()[ $key ] ?? null;
			if ( ! $attribute ) {
				continue;
			}
			$options = $attribute->is_taxonomy() ? wp_get_object_terms( $scope_parent->get_id(), $key, array( 'fields' => 'slugs' ) ) : $attribute->get_options();
			if ( is_wp_error( $options ) || ( ! $product->is_type( 'variation' ) && 1 !== count( $options ) ) ) {
				return false;
			}
			return in_array( $wanted, array_map( 'sanitize_title', $options ), true );
		}
		return false;
	}

	/**
	 * Exact-SKU matching, bounded bulk loads, then stable pagination/sorting.
	 *
	 * @param array $input Raw filter fields.
	 * @return array
	 * @throws Failure If unavailable or catalog exceeds the documented M3 bounds.
	 */
	public static function search( array $input ) {
		if ( ! Plugin::has_woocommerce() || Schema::VERSION !== (int) get_option( 'pc_schema_version' ) ) {
			throw new Failure( array( 'catalog' => __( 'The catalog is temporarily unavailable.', 'parameter-commerce' ) ), 503 );
		}
		$start = microtime( true );
		global $wpdb;
		$queries = $wpdb->num_queries;
		$f       = self::filters( $input );
		if ( 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'items'   => array(),
				'total'   => 0,
				'pages'   => 0,
				'filters' => $f,
			);
		}
		$args = array(
			'pc_catalog' => true,
			'type'       => array( 'simple', 'variable' ),
			'status'     => 'publish',
			'visibility' => 'catalog',
			'limit'      => 1001,
			'orderby'    => 'menu_order title ID',
			'order'      => 'ASC',
		);
		if ( $f['category'] ) {
			$args['category'] = array( sanitize_title( $f['category'] ) );
		}
		if ( $f['use'] ) {
			$args['tag'] = array( $f['use'] );
		}
		$parents = wc_get_products( $args );
		if ( count( $parents ) > 1000 ) {
			throw new Failure( array( 'catalog' => __( 'Please narrow your category or intended use to browse this catalog.', 'parameter-commerce' ) ) );
		}
		$parents      = array_filter( $parents, static fn( $p ) => Public_Specifications::catalog_visible( $p->get_id() ) && in_array( $p->get_catalog_visibility(), array( 'visible', 'catalog' ), true ) && ! get_post_meta( $p->get_id(), '_pc_demo_catalog', true ) );
		$parent_map   = array();
		$variable_ids = array();
		foreach ( $parents as $parent ) {
			$parent_map[ $parent->get_id() ] = $parent;
			if ( $parent->is_type( 'variable' ) ) {
				$variable_ids[] = $parent->get_id();
			}
		}
		$scoped = '' !== $f['size'] || '' !== $f['min_price'] || '' !== $f['max_price'] || '' !== $f['cotton_min'] || '' !== $f['weight_max'];
		$scopes = $parents;
		if ( $scoped && $variable_ids ) {
			$children = wc_get_products(
				array(
					'pc_catalog'    => true,
					'pc_parent_ids' => $variable_ids,
					'type'          => 'variation',
					'status'        => 'publish',
					'limit'         => 5001,
					'orderby'       => 'ID',
					'order'         => 'ASC',
				)
			);
			if ( count( $children ) > 5000 ) {
				throw new Failure( array( 'catalog' => __( 'Please narrow your category or intended use to browse this catalog.', 'parameter-commerce' ) ) );
			}
			$scopes = array_merge( array_values( array_filter( $parents, static fn( $p ) => ! $p->is_type( 'variable' ) ) ), $children );
		}
		$matches = self::evidence_matches( $f, array_keys( $parent_map ) );
		$items   = array();
		if ( '' !== $f['cotton_min'] && '' === $f['weight_max'] && '' === $f['size'] && '' === $f['min_price'] && '' === $f['max_price'] ) {
			foreach ( $parents as $parent ) {
				if ( $parent->is_type( 'variable' ) && ! empty( $matches[ $parent->get_id() . ':0' ]['cotton_pct'] ) && empty( $matches[ $parent->get_id() . ':model' ]['cotton_pct'] ) ) {
					$scopes[] = $parent;
				}
			}
		}
		foreach ( $scopes as $product ) {
			$variation = $product->is_type( 'variation' ) ? $product->get_id() : 0;
			$parent    = $variation ? ( $parent_map[ $product->get_parent_id() ] ?? null ) : $product;
			if ( ! $parent || ! Public_Specifications::catalog_visible( $parent->get_id(), $variation ) || ! self::size_matches( $product, $parent, $f['size'] ) ) {
				continue;
			}
			$scope = $parent->get_id() . ':' . $variation;
			if ( ( '' !== $f['cotton_min'] && empty( $matches[ $scope ]['cotton_pct'] ) && empty( $matches[ $parent->get_id() . ':model' ]['cotton_pct'] ) ) || ( '' !== $f['weight_max'] && empty( $matches[ $scope ]['pair_weight_g'] ) ) ) {
				continue;
			}
			$price = '' === $product->get_price() ? null : (float) wc_get_price_to_display( $product );
			if ( Pack_Builder::enabled( $parent->get_id() ) ) {
				try {
					$pack_prices = Pack_Pricing::prices( $parent );
					if ( $pack_prices ) {
						$price = (float) wc_get_price_to_display( $parent, array( 'price' => min( $pack_prices ) ) );
					}
				} catch ( \RuntimeException $e ) {
					$price = null;
				}
			}
			if ( ( '' !== $f['min_price'] && ( null === $price || $price < (float) $f['min_price'] ) ) || ( '' !== $f['max_price'] && ( null === $price || $price > (float) $f['max_price'] ) ) ) {
				continue;
			}
			$items[] = array(
				'product'      => $product,
				'parent'       => $parent,
				'price'        => $price,
				'variation_id' => $variation,
			);
		}
		if ( $f['sort'] ) {
			usort(
				$items,
				static function ( $a, $b ) use ( $f ) {
					if ( null === $a['price'] || null === $b['price'] ) {
						return ( null === $a['price'] ) <=> ( null === $b['price'] );
					}
					$comparison = 'price_desc' === $f['sort'] ? $b['price'] <=> $a['price'] : $a['price'] <=> $b['price'];
					return $comparison ? $comparison : $a['product']->get_id() <=> $b['product']->get_id();
				}
			);
		}
		if ( ! $f['sort'] ) {
			usort(
				$items,
				static function ( $a, $b ) {
					$menu = $a['parent']->get_menu_order() <=> $b['parent']->get_menu_order();
					if ( $menu ) {
						return $menu; }
					$name = strcasecmp( $a['parent']->get_name(), $b['parent']->get_name() );
					return $name ? $name : $a['product']->get_id() <=> $b['product']->get_id();
				}
			);
		}
		$total   = count( $items );
		$profile = array(
			'parents'      => count( $parents ),
			'scopes'       => count( $scopes ),
			'queries'      => $wpdb->num_queries - $queries,
			'milliseconds' => round( ( microtime( true ) - $start ) * 1000, 2 ),
		);
		do_action( 'pc_catalog_profile', $profile );
		return array(
			'items'   => array_slice( $items, ( $f['page'] - 1 ) * 12, 12 ),
			'total'   => $total,
			'pages'   => (int) ceil( $total / 12 ),
			'filters' => $f,
			'profile' => $profile,
		);
	}

	/** Public server-rendered page; all request values are validated and escaped. @return string */
	public static function render() {
		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filters validated by search().
			$result = self::search( wp_unslash( $_GET ) );
			ob_start();
			require dirname( __DIR__ ) . '/templates/catalog.php';
			return ob_get_clean();
		} catch ( Failure $error ) {
			return '<p role="alert">' . esc_html( $error->getMessage() ) . '</p>';
		}
	}
}
