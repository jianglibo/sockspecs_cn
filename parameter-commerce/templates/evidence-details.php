<?php
/**
 * Shared escaped evidence detail, using only the safe public projection.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<details><summary><?php esc_html_e( 'View evidence details', 'parameter-commerce' ); ?></summary><dl>
				<?php
				foreach ( array(
					__( 'Method', 'parameter-commerce' )   => trim( $record['method_code'] . ' ' . $record['method_version'] ),
					__( 'Status', 'parameter-commerce' )   => __( 'Published; staff-approved', 'parameter-commerce' ),
					__( 'Source / measurement date', 'parameter-commerce' ) => $record['measured_at'],
					__( 'Specimen size / scope', 'parameter-commerce' ) => $record['size_scope'],
					__( 'Actual sample count', 'parameter-commerce' ) => $record['sample_count'] ?? '',
					__( 'Sample batch', 'parameter-commerce' ) => $record['sample_batch'],
					__( 'Evidence', 'parameter-commerce' ) => $record['evidence_title'],
				) as $label => $value ) :
					if ( '' === (string) $value ) {
						continue; }
					?>
					<dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd>
				<?php endforeach; ?>
				</dl>
				<?php if ( '{}' !== $record['conditions_json'] ) : ?>
					<dl><?php foreach ( (array) json_decode( $record['conditions_json'], true ) as $key => $value ) : ?>
<dt><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?></dt><dd><?php echo esc_html( is_scalar( $value ) ? str_replace( '_', ' ', (string) $value ) : wp_json_encode( $value ) ); ?></dd>
<?php endforeach; ?></dl>
				<?php endif; ?>
				<?php
				if ( $record['note'] ) :
					?>
					<p><?php echo esc_html( $record['note'] ); ?></p><?php endif; ?>
				<?php
				if ( $record['evidence_url'] ) :
					?>
					<p><a href="<?php echo esc_url( $record['evidence_url'], array( 'https' ) ); ?>" rel="external noreferrer"><?php esc_html_e( 'Public evidence excerpt', 'parameter-commerce' ); ?></a></p><?php endif; ?>
				<?php
				if ( $record['attachment_url'] ) :
					?>
					<p><a href="<?php echo esc_url( $record['attachment_url'] ); ?>"><?php esc_html_e( 'Public evidence attachment', 'parameter-commerce' ); ?></a></p><?php endif; ?>
				<?php
				if ( ! $record['evidence_url'] && ! $record['attachment_url'] ) :
					?>
					<p><?php esc_html_e( 'Source document is not public.', 'parameter-commerce' ); ?></p><?php endif; ?>
				</details>
