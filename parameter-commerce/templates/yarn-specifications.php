<?php
/**
 * Reviewed yarn descriptions, separate from the measurement claim ledger.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="pc-product-facts pc-yarn-specifications">
	<?php foreach ( $records as $area => $row ) : ?>
		<h3><?php echo esc_html( self::areas()[ $area ] ); ?></h3>
		<p><?php echo esc_html( $row['applies_to'] ); ?></p>
		<figure class="wp-block-table"><table>
			<thead><tr><th scope="col"><?php esc_html_e( 'Specification', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'parameter-commerce' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( self::values( $row ) as $label => $value ) : ?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table></figure>
		<details class="pc-product-evidence"><summary><?php esc_html_e( 'Sources & testing details', 'parameter-commerce' ); ?></summary>
			<p><?php echo esc_html( self::sources()[ $row['source_type'] ] ); ?></p>
			<?php foreach ( array( 'source', 'date', 'method', 'sample', 'batch' ) as $key ) : ?>
				<?php if ( '' !== $row[ $key ] ) : ?>
					<p><strong><?php echo esc_html( self::fields()[ $key ] ); ?>:</strong> <?php echo esc_html( $row[ $key ] ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>
			<p><?php esc_html_e( 'Reviewed for display. These details apply to the stated area and sample scope; review is not independent certification.', 'parameter-commerce' ); ?></p>
		</details>
	<?php endforeach; ?>
</section>
