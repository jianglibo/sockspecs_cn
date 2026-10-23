<?php
/**
 * Public, exact-scope specification table. All output is escaped.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php Comparison::action( $scope_product->get_id() ); ?>
<p class="pc-scope-label"><strong><?php echo esc_html( $variation_id ? Public_Specifications::scope_label( $scope_product ) : __( 'Product-level records', 'parameter-commerce' ) ); ?></strong></p>
<?php if ( ! $variation_id && $product->is_type( 'variable' ) ) : ?>
<p class="pc-scope-notice"><?php esc_html_e( 'These records describe product-level samples, not every size. Choose a size or SKU for its own measurements. Sample measurements do not transfer to other sizes. Only explicit model-wide declarations apply across variants.', 'parameter-commerce' ); ?></p>
<?php endif; ?>
<p><?php esc_html_e( 'Only staff-approved, published records appear here. Approval is not independent certification. Each source is identified; missing results are not a zero or a failing grade.', 'parameter-commerce' ); ?></p>
<?php foreach ( $specifications['groups'] as $group_key => $group ) : ?>
<div class="pc-table-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( $group['label'] ); ?>">
<table class="pc-spec-table">
	<caption><?php echo esc_html( $group['label'] ); ?></caption>
	<thead><tr><th scope="col"><?php esc_html_e( 'Parameter', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Value / unit', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Applies to', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Source & evidence', 'parameter-commerce' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $group['metrics'] as $key => $metric ) : ?>
		<tr data-metric="<?php echo esc_attr( $key ); ?>" data-availability="<?php echo esc_attr( $metric['availability'] ); ?>">
			<th scope="row"><?php echo esc_html( $metric['label'] ); ?>
			<?php
			if ( ! empty( $metric['help'] ) ) :
				?>
				<p class="pc-metric-help"><?php echo esc_html( $metric['help'] ); ?></p><?php endif; ?></th>
			<td class="pc-metric-value"><?php echo esc_html( Public_Specifications::value( $metric ) ); ?>
			<?php
			if ( null !== $metric['value'] && $metric['unit'] ) :
				?>
				<span class="pc-unit"><?php echo esc_html( Domestic_Catalog::unit( $metric ) ); ?></span><?php endif; ?></td>
			<td><?php echo esc_html( $metric['record']['size_scope'] ?? '' ); ?></td>
			<td><p><?php echo esc_html( $metric['source_label'] ?? '—' ); ?></p>
			<?php $record = $metric['record']; ?>
			<?php if ( $record ) : ?>
				<?php require __DIR__ . '/evidence-details.php'; ?>
				<?php
			else :
				?>
				—<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endforeach; ?>
<p class="pc-method-note"><?php esc_html_e( 'Results apply to the documented samples and conditions. Different test methods are not interchangeable, and a single measurement does not establish overall quality or predict product lifetime.', 'parameter-commerce' ); ?></p>
