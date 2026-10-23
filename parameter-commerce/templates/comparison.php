<?php
/**
 * Semantic comparison tables: values are never converted, scored or ranked.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items = $data['items'];
?>
<p><?php esc_html_e( 'Compare materials, construction, sizes and measurements. Missing information is marked as not provided; expand a value to see its scope and evidence.', 'parameter-commerce' ); ?></p>
<p><?php esc_html_e( 'Scroll each table horizontally to see all selections. Keyboard users can focus the table region and use the arrow keys.', 'parameter-commerce' ); ?></p>
<div class="pc-compare-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Products, prices and availability', 'parameter-commerce' ); ?>">
<table class="pc-compare-table">
<caption><?php esc_html_e( 'Products, prices and availability', 'parameter-commerce' ); ?></caption>
<thead><tr><th scope="col"><?php esc_html_e( 'Product', 'parameter-commerce' ); ?></th>
<?php foreach ( $items as $item ) : ?>
<th scope="col"><a href="<?php echo esc_url( Comparison::carry( $item['variation_id'] ? add_query_arg( 'pc_variation', $item['variation_id'], $item['product']->get_permalink() ) : $item['product']->get_permalink() ) ); ?>"><?php echo esc_html( Public_Specifications::scope_label( $item['product'] ) ); ?></a></th>
<?php endforeach; ?></tr></thead><tbody>
<?php
foreach ( array(
	'size'  => __( 'Available sizes', 'parameter-commerce' ),
	'color' => __( 'Available colors', 'parameter-commerce' ),
) as $option => $label ) :
	?>
<tr><th scope="row"><?php echo esc_html( $label ); ?></th>
	<?php foreach ( $items as $item ) : ?>
<td><?php echo esc_html( $item['options'][ $option ] ? $item['options'][ $option ] : __( 'Not provided', 'parameter-commerce' ) ); ?></td>
	<?php endforeach; ?>
</tr>
<?php endforeach; ?>
<tr><th scope="row"><?php esc_html_e( 'Current price', 'parameter-commerce' ); ?></th>
<?php
foreach ( $items as $item ) :
	?>
	<td><?php echo $item['product']->get_price_html() ? wp_kses_post( $item['product']->get_price_html() ) : esc_html__( 'Price not available', 'parameter-commerce' ); ?></td><?php endforeach; ?></tr>
<tr><th scope="row"><?php esc_html_e( 'Availability', 'parameter-commerce' ); ?></th>
<?php
foreach ( $items as $item ) :
	?>
	<td><?php echo esc_html( Domestic_Catalog::preview( $item['product'] ) ? __( 'Price and stock pending confirmation.', 'parameter-commerce' ) : ( $item['product']->is_type( 'variable' ) ? __( 'Choose a size on the product page to check availability.', 'parameter-commerce' ) : ( $item['product']->get_availability()['availability'] ? $item['product']->get_availability()['availability'] : __( 'In stock', 'parameter-commerce' ) ) ) ); ?></td><?php endforeach; ?></tr>
</tbody></table></div>
<p><?php esc_html_e( 'Prices are per pack. Select a pack size and product options on the product page to see the total.', 'parameter-commerce' ); ?></p>
<?php
foreach ( $items[0]['specifications']['groups'] as $group_key => $group ) :
	$group['metrics'] = array_filter(
		$group['metrics'],
		static function ( $metric, $key ) use ( $items, $group_key ) {
			foreach ( $items as $item ) {
				if ( null !== $item['specifications']['groups'][ $group_key ]['metrics'][ $key ]['value'] || ! empty( $item['specifications']['groups'][ $group_key ]['metrics'][ $key ]['variants'] ) ) {
					return true; }
			}
			return false;
		},
		ARRAY_FILTER_USE_BOTH
	);
	if ( ! $group['metrics'] ) {
		continue; }
	?>
<div class="pc-compare-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( $group['label'] ); ?>">
<table class="pc-compare-table"><caption><?php echo esc_html( $group['label'] ); ?></caption>
<thead><tr><th scope="col"><?php esc_html_e( 'Parameter', 'parameter-commerce' ); ?></th>
	<?php
	foreach ( $items as $item ) :
		?>
		<th scope="col"><?php echo esc_html( Public_Specifications::scope_label( $item['product'] ) ); ?></th><?php endforeach; ?></tr></thead>
<tbody>
	<?php
	foreach ( $group['metrics'] as $key => $metric ) :
		$cells = array();
		foreach ( $items as $item ) {
			$cells[] = $item['specifications']['groups'][ $group_key ]['metrics'][ $key ] + array( 'scope_type' => $item['specifications']['scope_type'] );
		}
		?>
<tr data-metric="<?php echo esc_attr( $key ); ?>"><th scope="row"><?php echo esc_html( $metric['label'] ); ?>
		<?php
		if ( ! empty( $metric['help'] ) ) :
			?>
	<p class="pc-metric-help"><?php echo esc_html( $metric['help'] ); ?></p><?php endif; ?><p class="pc-comparability"><?php echo esc_html( array_filter( array_column( $cells, 'variants' ) ) ? __( 'Measurements are listed for their recorded sizes/colors, not every variant.', 'parameter-commerce' ) : Comparison::assess( $cells, $data['definitions'][ $key ] ) ); ?></p></th>
		<?php foreach ( $cells as $cell ) : ?>
<td data-availability="<?php echo esc_attr( ! empty( $cell['variants'] ) ? 'available' : $cell['availability'] ); ?>">
			<?php
			$entries = ! empty( $cell['variants'] ) ? $cell['variants'] : ( $cell['record'] ? array( $cell ) : array() );
			$values  = array();
			foreach ( $entries as $entry ) {
				$values[] = Public_Specifications::value( $entry ) . ( $entry['unit'] ? ' ' . Domestic_Catalog::unit( $entry ) : '' );
			}
			?>
<p class="pc-compare-value"><?php echo esc_html( $values ? implode( ' / ', array_unique( $values ) ) : __( 'Not provided', 'parameter-commerce' ) ); ?></p>
			<?php if ( $entries ) : ?>
<details><summary><?php esc_html_e( 'Sizes, source and evidence', 'parameter-commerce' ); ?></summary>
				<?php foreach ( $entries as $entry ) : ?>
<p><strong><?php echo esc_html( Public_Specifications::value( $entry ) . ' ' . Domestic_Catalog::unit( $entry ) ); ?></strong> — <?php echo esc_html( $entry['record']['size_scope'] ); ?></p>
<p class="pc-compare-source"><?php echo esc_html( $entry['source_label'] ); ?></p>
					<?php
					$record = $entry['record'];
					require __DIR__ . '/evidence-details.php';
					?>
				<?php endforeach; ?>
</details>
			<?php endif; ?>
</td>
<?php endforeach; ?></tr>
	<?php endforeach; ?>
</tbody></table></div>
<?php endforeach; ?>
<p><?php esc_html_e( 'Results apply to the documented samples and conditions. Different test methods are not interchangeable, and a single measurement does not establish overall quality or predict product lifetime.', 'parameter-commerce' ); ?></p>
