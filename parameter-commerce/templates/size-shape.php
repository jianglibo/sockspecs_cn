<?php
/**
 * Schematic catalog dimensions. Variables come from Size_Shape::render().
 *
 * @package ParameterCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="pc-size-shape" aria-labelledby="<?php echo esc_attr( $id ); ?>-heading">
	<div class="pc-size-shape__heading">
		<h2 id="<?php echo esc_attr( $id ); ?>-heading"><?php esc_html_e( 'Size & Shape', 'parameter-commerce' ); ?></h2>
		<p><span><?php echo esc_html( $sizing['label'] ); ?></span> <strong><?php echo esc_html( $sizing['value'] ); ?></strong></p>
	</div>
	<div class="pc-size-shape__body">
		<svg class="pc-size-shape__diagram" viewBox="0 0 340 300" role="img" aria-labelledby="<?php echo esc_attr( $id ); ?>-title <?php echo esc_attr( $id ); ?>-desc" focusable="false">
			<title id="<?php echo esc_attr( $id ); ?>-title"><?php esc_html_e( 'Sock shape and dimensions', 'parameter-commerce' ); ?></title>
			<desc id="<?php echo esc_attr( $id ); ?>-desc"><?php esc_html_e( 'Schematic side view. A indicates the sock leg; B indicates the foot. Values are listed beside the illustration.', 'parameter-commerce' ); ?></desc>
			<path class="pc-size-shape__sock" d="M110 <?php echo esc_attr( $top ); ?> H176 L170 181 Q169 201 190 209 L280 219 Q304 222 304 238 Q304 253 281 253 H136 Q104 253 104 223 Z"/>
			<path class="pc-size-shape__detail" d="M110 <?php echo esc_attr( $top + 18 ); ?> H175 M111 209 Q145 207 148 252 M275 219 Q260 235 269 253"/>
			<path class="pc-size-shape__rib" d="M121 <?php echo esc_attr( $top + 4 ); ?> v10 M133 <?php echo esc_attr( $top + 4 ); ?> v10 M145 <?php echo esc_attr( $top + 4 ); ?> v10 M157 <?php echo esc_attr( $top + 4 ); ?> v10 M169 <?php echo esc_attr( $top + 4 ); ?> v10"/>
			<path class="pc-size-shape__guide" d="M98 <?php echo esc_attr( $top ); ?> H62 M98 205 H62 M104 262 V284 M304 262 V284"/>
			<path class="pc-size-shape__arrow" d="M72 <?php echo esc_attr( $top + 3 ); ?> V202 M67 <?php echo esc_attr( $top + 9 ); ?> L72 <?php echo esc_attr( $top + 3 ); ?> L77 <?php echo esc_attr( $top + 9 ); ?> M67 196 L72 202 L77 196 M107 275 H301 M113 270 L107 275 L113 280 M295 270 L301 275 L295 280"/>
			<circle class="pc-size-shape__badge" cx="39" cy="<?php echo esc_attr( ( $top + 205 ) / 2 ); ?>" r="15"/>
			<text class="pc-size-shape__letter" x="39" y="<?php echo esc_attr( ( $top + 205 ) / 2 + 5 ); ?>" text-anchor="middle">A</text>
			<circle class="pc-size-shape__badge" cx="204" cy="275" r="15"/>
			<text class="pc-size-shape__letter" x="204" y="280" text-anchor="middle">B</text>
		</svg>
		<?php if ( ! empty( $dimensions['sizes'] ) ) : ?>
		<table class="pc-size-shape__sizes">
			<thead><tr><th><?php esc_html_e( 'Size / options', 'parameter-commerce' ); ?></th><th>A<br><?php esc_html_e( 'Leg length', 'parameter-commerce' ); ?></th><th>B<br><?php esc_html_e( 'Foot length', 'parameter-commerce' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $dimensions['sizes'] as $row ) : ?>
				<tr><th scope="row"><?php echo esc_html( $row['size'] ); ?></th><td><?php echo esc_html( wc_format_localized_decimal( $row['leg_cm'] ) ); ?> cm</td><td><?php echo esc_html( wc_format_localized_decimal( $row['foot_cm'] ) ); ?> cm</td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<dl class="pc-size-shape__dimensions">
			<div><dt><span aria-hidden="true">A</span> <?php esc_html_e( 'Leg length', 'parameter-commerce' ); ?></dt><dd><?php echo esc_html( wc_format_localized_decimal( $dimensions['leg_cm'] ) ); ?> <small><?php esc_html_e( 'cm', 'parameter-commerce' ); ?></small></dd></div>
			<div><dt><span aria-hidden="true">B</span> <?php esc_html_e( 'Foot length', 'parameter-commerce' ); ?></dt><dd><?php echo esc_html( wc_format_localized_decimal( $dimensions['foot_cm'] ) ); ?> <small><?php esc_html_e( 'cm', 'parameter-commerce' ); ?></small></dd></div>
		</dl>
		<?php endif; ?>
	</div>
	<p class="pc-size-shape__caption"><?php esc_html_e( 'Model dimensions · Schematic illustration', 'parameter-commerce' ); ?></p>
	<details class="pc-size-shape__source"><summary><?php esc_html_e( 'About these dimensions', 'parameter-commerce' ); ?></summary><p><?php echo esc_html( $dimensions['source'] ); ?>. <?php esc_html_e( 'These dimensions describe the sock’s shape. Use the shoe-size option for fit. The illustration is not a measurement method or a size-specific test record.', 'parameter-commerce' ); ?></p></details>
</section>
