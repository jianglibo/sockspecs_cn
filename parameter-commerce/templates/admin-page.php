<?php
/**
 * Staff-only editor. All dynamic HTML is escaped at output.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Parameter Specs', 'parameter-commerce' ); ?></h1>
	<p><?php esc_html_e( 'Record evidence, review provenance, and intentionally publish claims. Missing values remain Not tested. Prices and stock stay in WooCommerce.', 'parameter-commerce' ); ?></p>
	<?php if ( $errors ) : ?>
		<div class="notice notice-error" role="alert"><p><strong><?php esc_html_e( 'Nothing was saved. Correct these fields or reload after a conflict:', 'parameter-commerce' ); ?></strong></p><ul>
		<?php foreach ( $errors as $field => $message ) : ?>
			<li><strong><?php echo esc_html( $field ); ?>:</strong> <?php echo esc_html( $message ); ?></li>
		<?php endforeach; ?>
		</ul></div>
	<?php endif; ?>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pc-specs' ) ); ?>"><?php esc_html_e( 'New claim / reload list', 'parameter-commerce' ); ?></a></p>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="pc-specs">
		<?php Product_Picker::field( 'product_id', $product_filter, __( 'Filter by product', 'parameter-commerce' ) ); ?>
		<?php submit_button( __( 'Filter', 'parameter-commerce' ), 'secondary', '', false ); ?>
	</form>
	<table class="widefat striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'Claims, newest first; 20 per page', 'parameter-commerce' ); ?></caption>
		<thead><tr>
		<?php foreach ( array( __( 'Claim', 'parameter-commerce' ), __( 'Product / variation', 'parameter-commerce' ), __( 'Metric', 'parameter-commerce' ), __( 'Value', 'parameter-commerce' ), __( 'Status / revision', 'parameter-commerce' ) ) as $heading ) : ?>
			<th scope="col"><?php echo esc_html( $heading ); ?></th>
		<?php endforeach; ?>
		</tr></thead><tbody>
		<?php foreach ( array_slice( $rows, 0, 20 ) as $row ) : ?>
			<tr><td><a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'     => 'pc-specs',
						'claim_id' => $row['id'],
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php echo esc_html( $row['id'] ); ?></a></td>
			<td><?php echo esc_html( $row['wc_product_id'] . ' / ' . $row['wc_variation_id'] ); ?></td>
			<td><?php echo esc_html( $definitions[ $row['metric_key'] ]['label'] ?? $row['metric_key'] ); ?></td>
			<td><?php echo esc_html( $row['value_num'] ?? $row['value_enum'] ?? __( 'Not tested', 'parameter-commerce' ) ); ?> <?php echo esc_html( $row['unit'] ); ?></td>
			<td><?php echo esc_html( Admin::status_label( $row['status'] ) . ' / ' . $row['revision'] ); ?></td></tr>
		<?php endforeach; ?>
		<?php
		if ( ! $rows ) :
			?>
			<tr><td colspan="5"><?php esc_html_e( 'No claims yet.', 'parameter-commerce' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
	<p>
	<?php if ( $page_number > 1 ) : ?>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page'       => 'pc-specs',
					'product_id' => $product_filter,
					'paged'      => $page_number - 1,
				),
				admin_url( 'admin.php' )
			)
		);
		?>
		"><?php esc_html_e( 'Previous page', 'parameter-commerce' ); ?></a>
	<?php endif; ?>
	<?php if ( count( $rows ) > 20 ) : ?>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page'       => 'pc-specs',
					'product_id' => $product_filter,
					'paged'      => $page_number + 1,
				),
				admin_url( 'admin.php' )
			)
		);
		?>
		"><?php esc_html_e( 'Next page', 'parameter-commerce' ); ?></a>
	<?php endif; ?>
	</p>
	<h2><?php echo esc_html( $id ? __( 'Saved claim', 'parameter-commerce' ) . ' #' . $id : __( 'New draft', 'parameter-commerce' ) ); ?></h2>
	<?php if ( ! $claim || 'draft' === $claim['status'] || $submitted ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="pc_claim"><input type="hidden" name="pc_operation" value="save">
		<input type="hidden" name="claim_id" value="<?php echo esc_attr( $id ); ?>">
		<input type="hidden" name="revision" value="<?php echo esc_attr( is_scalar( $form['revision'] ) ? $form['revision'] : '' ); ?>">
		<?php wp_nonce_field( 'pc_claim', 'pc_nonce' ); ?>
		<?php Product_Picker::field( 'wc_product_id', $claim['wc_product_id'] ?? $form['wc_product_id'], __( 'Product', 'parameter-commerce' ), 'product', (bool) $claim ); ?>
		<?php Product_Picker::field( 'wc_variation_id', $claim['wc_variation_id'] ?? $form['wc_variation_id'], __( 'Variation', 'parameter-commerce' ), 'variation', (bool) $claim ); ?>
		<p><?php esc_html_e( 'Use a variation for size-specific measurements. Product-scope measurements must not imply they apply to every size. Product, variation, and metric are fixed once a draft is saved.', 'parameter-commerce' ); ?></p>
		<p><?php esc_html_e( 'For fibres and yarns, identify the material component and sock area as well as applicable sizes/colors. In conditions, use area: whole, body, sole, heel_toe or cuff; material_component: the documented fibre or blend. Yarn count also needs count_basis: single_strand and its exact unit (Ne, Nm, tex, dtex or denier). Do not infer Merino, long-staple cotton or micron values. One record per metric and product/variation is supported; do not merge different yarns or areas into an average.', 'parameter-commerce' ); ?></p>
		<?php Admin::field( $form, 'size_scope', __( 'Specimen size / scope', 'parameter-commerce' ) ); ?>
		<p><label for="pc-metric"><?php esc_html_e( 'Metric', 'parameter-commerce' ); ?></label><br><select id="pc-metric" name="metric_key">
		<?php foreach ( $definitions as $key => $definition ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( is_scalar( $form['metric_key'] ) ? $form['metric_key'] : '', $key ); ?>><?php echo esc_html( $definition['label'] . ' (' . $key . ')' ); ?></option>
		<?php endforeach; ?>
		</select></p>
		<?php Admin::field( $form, 'value', __( 'Value (blank means Not tested)', 'parameter-commerce' ) ); ?>
		<?php Admin::field( $form, 'unit', __( 'Unit (use the dictionary below)', 'parameter-commerce' ) ); ?>
		<p><label for="pc-source"><?php esc_html_e( 'Evidence source tier', 'parameter-commerce' ); ?></label><br><select id="pc-source" name="source_type">
		<?php
		foreach ( array(
			'supplier_declared' => __( 'Manufacturer-declared', 'parameter-commerce' ),
			'platform_measured' => __( 'Measured by us', 'parameter-commerce' ),
			'third_party_lab'   => __( 'Third-party laboratory', 'parameter-commerce' ),
			'supplier_report'   => __( 'Supplier-provided laboratory report', 'parameter-commerce' ),
		) as $key => $label ) :
			?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( is_scalar( $form['source_type'] ) ? $form['source_type'] : '', $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
		</select></p>
		<?php
		Admin::field( $form, 'method_code', __( 'Documented method code', 'parameter-commerce' ) );
		Admin::field( $form, 'method_version', __( 'Method version', 'parameter-commerce' ) );
		Admin::field( $form, 'conditions_json', __( 'Test conditions (JSON object)', 'parameter-commerce' ), 'textarea' );
		Admin::field( $form, 'sample_count', __( 'Actual sample count', 'parameter-commerce' ), 'number' );
		Admin::field( $form, 'sample_batch', __( 'Sample batch', 'parameter-commerce' ) );
		Admin::field( $form, 'measured_at', __( 'Source / measurement date', 'parameter-commerce' ), 'date' );
		Admin::field( $form, 'note', __( 'Public limitations and notes', 'parameter-commerce' ), 'textarea' );
		$evidence_form = isset( $form['evidence'] ) && is_array( $form['evidence'] ) ? $form['evidence'] : array();
		?>
		<fieldset><legend><h3><?php esc_html_e( 'Evidence snapshot', 'parameter-commerce' ); ?></h3></legend>
		<p><?php esc_html_e( 'Each save creates a new evidence snapshot. Private source URLs and internal notes remain staff-only. Private files must be hosted behind access controls; ordinary WordPress media is public.', 'parameter-commerce' ); ?></p>
		<?php
		Admin::field( $evidence_form, 'public_title', __( 'Public evidence title', 'parameter-commerce' ), 'text', 'evidence' );
		Admin::field( $evidence_form, 'source_org', __( 'Source organization (staff-only)', 'parameter-commerce' ), 'text', 'evidence' );
		Admin::field( $evidence_form, 'source_url', __( 'Source URL (staff-only HTTPS)', 'parameter-commerce' ), 'url', 'evidence' );
		Admin::field( $evidence_form, 'public_url', __( 'Public excerpt URL (explicit HTTPS disclosure)', 'parameter-commerce' ), 'url', 'evidence' );
		Admin::field( $evidence_form, 'attachment_id', __( 'Public Media Library attachment ID (PDF or image)', 'parameter-commerce' ), 'number', 'evidence' );
		Admin::field( $evidence_form, 'note', __( 'Internal evidence notes (staff-only)', 'parameter-commerce' ), 'textarea', 'evidence' );
		?>
		<p><label for="pc-evidence-visibility"><?php esc_html_e( 'Evidence visibility', 'parameter-commerce' ); ?></label><select id="pc-evidence-visibility" name="evidence[visibility]">
			<option value="private" <?php selected( is_scalar( $evidence_form['visibility'] ?? '' ) ? ( $evidence_form['visibility'] ?? 'private' ) : '', 'private' ); ?>><?php esc_html_e( 'Private', 'parameter-commerce' ); ?></option>
			<option value="public" <?php selected( is_scalar( $evidence_form['visibility'] ?? '' ) ? ( $evidence_form['visibility'] ?? '' ) : '', 'public' ); ?>><?php esc_html_e( 'Public excerpt / attachment', 'parameter-commerce' ); ?></option>
		</select></p>
		</fieldset>
		<?php submit_button( __( 'Save draft', 'parameter-commerce' ) ); ?>
	</form>
	<?php endif; ?>
	<?php if ( $claim ) : ?>
		<h3><?php esc_html_e( 'Saved preview (staff-only; no storefront panel yet)', 'parameter-commerce' ); ?></h3>
		<p><?php esc_html_e( 'This preview shows the saved revision, not unsaved form input. Draft and reviewed claims are not public. Review all provenance before changing status.', 'parameter-commerce' ); ?></p>
		<?php
		$preview_labels = array(
			'metric_key'      => __( 'Metric', 'parameter-commerce' ),
			'value'           => __( 'Value', 'parameter-commerce' ),
			'unit'            => __( 'Unit', 'parameter-commerce' ),
			'status'          => __( 'Status', 'parameter-commerce' ),
			'source_type'     => __( 'Evidence source tier', 'parameter-commerce' ),
			'wc_product_id'   => __( 'WooCommerce product ID', 'parameter-commerce' ),
			'wc_variation_id' => __( 'Variation ID (0 for product scope)', 'parameter-commerce' ),
			'size_scope'      => __( 'Specimen size / scope', 'parameter-commerce' ),
			'method_code'     => __( 'Documented method code', 'parameter-commerce' ),
			'method_version'  => __( 'Method version', 'parameter-commerce' ),
			'conditions_json' => __( 'Test conditions (JSON object)', 'parameter-commerce' ),
			'sample_count'    => __( 'Actual sample count', 'parameter-commerce' ),
			'sample_batch'    => __( 'Sample batch', 'parameter-commerce' ),
			'measured_at'     => __( 'Source / measurement date', 'parameter-commerce' ),
			'note'            => __( 'Public limitations and notes', 'parameter-commerce' ),
			'evidence_title'  => __( 'Public evidence title', 'parameter-commerce' ),
			'evidence_url'    => __( 'Public excerpt URL (explicit HTTPS disclosure)', 'parameter-commerce' ),
			'attachment_url'  => __( 'Public attachment URL', 'parameter-commerce' ),
		);
		$source_labels  = array(
			'supplier_declared' => __( 'Manufacturer-declared', 'parameter-commerce' ),
			'platform_measured' => __( 'Measured by us', 'parameter-commerce' ),
			'third_party_lab'   => __( 'Third-party laboratory', 'parameter-commerce' ),
			'supplier_report'   => __( 'Supplier-provided laboratory report', 'parameter-commerce' ),
		);
		?>
		<table class="widefat striped"><tbody>
		<?php foreach ( $preview as $key => $value ) : ?>
			<?php
			if ( 'status' === $key ) {
				$value = Admin::status_label( $value );
			} elseif ( 'source_type' === $key ) {
				$value = $source_labels[ $value ];
			} elseif ( 'metric_key' === $key ) {
				$value = $definitions[ $value ]['label'];
			}
			?>
			<tr><th scope="row"><?php echo esc_html( $preview_labels[ $key ] ); ?></th><td><?php echo esc_html( null === $value || '' === $value ? __( 'Not tested / not supplied', 'parameter-commerce' ) : $value ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
		$pc_targets = array(
			'draft'     => array( 'reviewed' ),
			'reviewed'  => array( 'draft', 'published' ),
			'published' => array( 'retracted' ),
			'retracted' => array(),
		);
		foreach ( $pc_targets[ $claim['status'] ] as $target ) :
			if ( in_array( $target, array( 'published', 'retracted' ), true ) && ! current_user_can( 'publish_pc_claims' ) ) {
				continue;
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pc_claim', 'pc_nonce' ); ?>
				<input type="hidden" name="action" value="pc_claim"><input type="hidden" name="pc_operation" value="transition">
				<input type="hidden" name="claim_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( $claim['revision'] ); ?>">
				<input type="hidden" name="target" value="<?php echo esc_attr( $target ); ?>">
				<p><label><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'I reviewed the saved claim and confirm this status change.', 'parameter-commerce' ); ?></label></p>
				<?php submit_button( Admin::transition_label( $target ), 'secondary', '', false ); ?>
			</form>
		<?php endforeach; ?>
		<?php if ( in_array( $claim['status'], array( 'published', 'retracted' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pc_claim', 'pc_nonce' ); ?>
				<input type="hidden" name="action" value="pc_claim"><input type="hidden" name="pc_operation" value="fork">
				<input type="hidden" name="claim_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( $claim['revision'] ); ?>">
				<?php submit_button( __( 'Create a new draft revision', 'parameter-commerce' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Audit history (staff-only)', 'parameter-commerce' ); ?></h3>
		<?php foreach ( array_slice( $history, 0, 20 ) as $event ) : ?>
			<details><summary><?php echo esc_html( $event['happened_at'] . ' UTC — ' . $event['action'] . ' — user #' . $event['actor_user_id'] ); ?></summary>
				<p><?php esc_html_e( 'Before', 'parameter-commerce' ); ?></p><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html( wp_json_encode( json_decode( $event['before_json'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				<p><?php esc_html_e( 'After', 'parameter-commerce' ); ?></p><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html( wp_json_encode( json_decode( $event['after_json'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			</details>
		<?php endforeach; ?>
		<p>
		<?php
		if ( $audit_page > 1 ) :
			?>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'       => 'pc-specs',
						'claim_id'   => $id,
						'audit_page' => $audit_page - 1,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Newer audit entries', 'parameter-commerce' ); ?></a><?php endif; ?>
		<?php
		if ( count( $history ) > 20 ) :
			?>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'       => 'pc-specs',
						'claim_id'   => $id,
						'audit_page' => $audit_page + 1,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Older audit entries', 'parameter-commerce' ); ?></a><?php endif; ?>
		</p>
	<?php endif; ?>
	<h2><?php esc_html_e( 'Metric dictionary', 'parameter-commerce' ); ?></h2>
	<p><?php esc_html_e( 'Units, types, and validation rules are versioned in code. Administrators may change labels and filterability. Composition tolerance defaults to zero percentage points; an operator may configure the documented pc_composition_tolerance filter. Partial mixes stay incomplete.', 'parameter-commerce' ); ?></p>
	<table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Metric', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Type / unit / enum values', 'parameter-commerce' ); ?></th><th scope="col"><?php esc_html_e( 'Configuration', 'parameter-commerce' ); ?></th></tr></thead><tbody>
	<?php foreach ( $definitions as $key => $definition ) : ?>
		<tr><td><?php echo esc_html( $definition['label'] . ' (' . $key . ')' ); ?>
		<?php
		if ( ! empty( $definition['help'] ) ) :
			?>
			<p><?php echo esc_html( $definition['help'] ); ?></p><?php endif; ?></td><td><?php echo esc_html( $definition['type'] . ' / ' . ( isset( $definition['allowed_units'] ) ? implode( ', ', $definition['allowed_units'] ) : ( $definition['unit'] ?? __( 'Explicit test unit required', 'parameter-commerce' ) ) ) . ' / ' . implode( ', ', $definition['choices'] ) ); ?></td><td>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<?php
			$metric_row = $definition;
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pc_claim', 'pc_nonce' ); ?>
				<input type="hidden" name="action" value="pc_claim"><input type="hidden" name="pc_operation" value="metric"><input type="hidden" name="metric_key" value="<?php echo esc_attr( $key ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( $metric_row['revision'] ); ?>">
				<label><?php esc_html_e( 'Label override (blank uses translation)', 'parameter-commerce' ); ?><input type="text" name="label_override" value="<?php echo esc_attr( $metric_row['label_override'] ); ?>"></label>
				<label><input type="checkbox" name="is_filterable" value="1" <?php checked( $definition['filterable'] ); ?>><?php esc_html_e( 'Filterable', 'parameter-commerce' ); ?></label>
				<?php submit_button( __( 'Save metric configuration', 'parameter-commerce' ), 'secondary', '', false ); ?>
			</form>
			<?php
		else :
			?>
			<?php esc_html_e( 'Administrator access required.', 'parameter-commerce' ); ?><?php endif; ?>
		</td></tr>
	<?php endforeach; ?>
	</tbody></table>
</div>
