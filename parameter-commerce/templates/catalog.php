<?php
/**
 * Evidence-aware catalog. Every value is escaped at output.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
$staff_preview     = 'yes' === get_option( 'woocommerce_coming_soon', 'no' ) && current_user_can( 'manage_woocommerce' );
$f                 = $result['filters'];
$active_filters    = count(
	array_filter(
		array_diff_key(
			$f,
			array(
				'page' => 1,
				'sort' => 1,
			)
		),
		static fn( $value ) => '' !== $value
	)
);
$comparison_return = get_permalink();
foreach ( $f as $filter_key => $filter_value ) {
	if ( '' !== $filter_value ) {
		$comparison_return = add_query_arg( 'pc_' . $filter_key, $filter_value, $comparison_return );
	}
}
?>
<div class="pc-catalog">
<div class="pc-catalog-heading">
<h1><?php esc_html_e( 'Shop', 'parameter-commerce' ); ?></h1>
<?php if ( $staff_preview ) : ?>
<details class="pc-catalog-preview">
<summary><?php esc_html_e( 'Staff preview — sales closed', 'parameter-commerce' ); ?></summary>
<p role="status"><?php esc_html_e( 'Staff catalog preview. Coming-soon mode is still enabled; public sales remain closed.', 'parameter-commerce' ); ?></p>
</details>
<?php endif; ?>
</div>
<?php
try {
	if ( Comparison::current() ) {
		Comparison::toolbar( $comparison_return );
	}
} catch ( Failure $error ) {
	// Keep the existing error and reset controls for malformed selections.
	Comparison::toolbar( $comparison_return );
}
?>
<div class="pc-catalog-layout">
<details class="pc-catalog-filter-panel">
<summary><?php esc_html_e( 'Filters', 'parameter-commerce' ); ?>
<?php
if ( $active_filters ) :
	?>
	<span>(<?php echo esc_html( $active_filters ); ?>)</span><?php endif; ?></summary>
<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="pc-catalog-filters">
<?php
Comparison::hidden_selection();
$query = array();
wp_parse_str( (string) wp_parse_url( get_permalink(), PHP_URL_QUERY ), $query );
foreach ( $query as $key => $value ) {
	if ( is_scalar( $value ) ) {
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
}
?>
<label><?php esc_html_e( 'Category', 'parameter-commerce' ); ?><select name="pc_category"><option value=""><?php esc_html_e( 'All categories', 'parameter-commerce' ); ?></option>
<?php
foreach ( get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
) as $category_term ) :
	?>
<option value="<?php echo esc_attr( $category_term->slug ); ?>" <?php selected( $f['category'], $category_term->slug ); ?>><?php echo esc_html( $category_term->name ); ?></option>
<?php endforeach; ?></select></label>
<label><?php esc_html_e( 'Intended use', 'parameter-commerce' ); ?><select name="pc_use"><option value=""><?php esc_html_e( 'All uses', 'parameter-commerce' ); ?></option>
<?php
foreach ( array(
	'everyday'     => __( 'Everyday', 'parameter-commerce' ),
	'running'      => __( 'Running', 'parameter-commerce' ),
	'hiking'       => __( 'Hiking', 'parameter-commerce' ),
	'cold-weather' => __( 'Cold weather', 'parameter-commerce' ),
) as $key => $label ) :
	?>
<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['use'], $key ); ?>><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?></select></label>
<label><?php esc_html_e( 'Size', 'parameter-commerce' ); ?><input name="pc_size" maxlength="100" value="<?php echo esc_attr( $f['size'] ); ?>" placeholder="S, M, L…"></label>
<?php
foreach ( array(
	'min_price'  => __( 'Minimum price', 'parameter-commerce' ),
	'max_price'  => __( 'Maximum price', 'parameter-commerce' ),
	'cotton_min' => __( 'Documented cotton, at least (%)', 'parameter-commerce' ),
	'weight_max' => __( 'Pair weight, at most (g)', 'parameter-commerce' ),
) as $key => $label ) :
	?>
<label><?php echo esc_html( $label ); ?><input type="number" min="0" step="any" name="pc_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $f[ $key ] ); ?>"
	<?php
	if ( 'cotton_min' === $key ) {
		echo 'max="100"'; }
	?>
></label>
<?php endforeach; ?>
<label><?php esc_html_e( 'Weight source', 'parameter-commerce' ); ?><select name="pc_weight_basis">
<?php
foreach ( array(
	''         => __( 'Recorded pair weight', 'parameter-commerce' ),
	'catalog'  => __( 'Catalog pair weight', 'parameter-commerce' ),
	'measured' => __( 'Measured pair weight only', 'parameter-commerce' ),
) as $key => $label ) :
	?>
<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['weight_basis'], $key ); ?>><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?></select></label>
<label><?php esc_html_e( 'Sort', 'parameter-commerce' ); ?><select name="pc_sort">
<?php
foreach ( array(
	''           => __( 'Editorial order', 'parameter-commerce' ),
	'price_asc'  => __( 'Price: low to high', 'parameter-commerce' ),
	'price_desc' => __( 'Price: high to low', 'parameter-commerce' ),
) as $key => $label ) :
	?>
<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['sort'], $key ); ?>><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?></select></label>
<button type="submit"><?php esc_html_e( 'Apply filters', 'parameter-commerce' ); ?></button><a href="<?php echo esc_url( Comparison::carry( get_permalink() ) ); ?>"><?php esc_html_e( 'Clear all filters', 'parameter-commerce' ); ?></a>
</form>
</details>
<div class="pc-catalog-results">
<div class="pc-catalog-results-heading">
<p role="status"><?php /* translators: %d: result count. */ echo esc_html( sprintf( __( '%d matching catalog entries', 'parameter-commerce' ), $result['total'] ) ); ?></p>
<details class="pc-catalog-help">
<summary><?php esc_html_e( 'About filters and prices', 'parameter-commerce' ); ?></summary>
<div>
<p><?php esc_html_e( 'Browse socks by intended use and documented specifications.', 'parameter-commerce' ); ?></p>
<p class="pc-filter-explanation"><?php esc_html_e( 'Measurement filters show only products with matching published data. For products with multiple options, all selected filters must match the same option. Visit each product page for material details, measurement methods, and supporting evidence.', 'parameter-commerce' ); ?></p>
<p><?php esc_html_e( 'Prices vary by product option. Price sorting uses the lowest displayed price unless filters select a specific option.', 'parameter-commerce' ); ?></p>
</div>
</details>
</div>
<?php
if ( ! $result['items'] ) :
	?>
	<p><?php esc_html_e( 'No published products match these filters. Draft demonstration products are not shown.', 'parameter-commerce' ); ?></p><?php endif; ?>
<div class="pc-catalog-grid">
<?php
foreach ( $result['items'] as $item ) :
	$p   = $item['product'];
	$url = Comparison::carry( $p->get_permalink() );
	if ( $item['variation_id'] ) {
		$url = add_query_arg( 'pc_variation', $item['variation_id'], $url ); }
	?>
<article class="pc-catalog-card"><a href="<?php echo esc_url( $url ); ?>"><?php echo wp_kses_post( $p->get_image( 'woocommerce_thumbnail' ) ); ?><h2><?php echo esc_html( $p->get_name() ); ?></h2></a>
<p><?php echo esc_html( $p->get_sku() ); ?></p><p><?php echo wp_kses_post( $p->get_price_html() ); ?></p>
	<?php
	if ( $item['variation_id'] ) :
		?>
		<p><?php echo esc_html( wc_get_formatted_variation( $p, true, true, true ) ); ?></p><?php endif; ?>
<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View specifications and options', 'parameter-commerce' ); ?></a>
	<?php
	if ( ! $staff_preview ) {
		Comparison::action( $p->get_id(), $comparison_return );
	}
	?>
</article>
<?php endforeach; ?></div>
<nav aria-label="<?php esc_attr_e( 'Catalog pages', 'parameter-commerce' ); ?>" class="pc-catalog-pages">
<?php
$links = array();
foreach ( $f as $key => $value ) {
	if ( 'page' !== $key && '' !== $value ) {
		$links[ 'pc_' . $key ] = $value; }
}
foreach ( array(
	$f['page'] - 1 => __( 'Previous page', 'parameter-commerce' ),
	$f['page'] + 1 => __( 'Next page', 'parameter-commerce' ),
) as $page_number => $label ) {
	if ( $page_number > 0 && $page_number <= $result['pages'] ) {
		echo '<a href="' . esc_url( Comparison::carry( add_query_arg( array_merge( $links, array( 'pc_page' => $page_number ) ), get_permalink() ) ) ) . '">' . esc_html( $label ) . '</a>'; }
}
?>
</nav></div></div></div>
