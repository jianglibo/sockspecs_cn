<?php
/**
 * X70 photo-informed profile. Catalog height must not position an unverified arrow.
 *
 * @package ParameterCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="pc-size-shape" data-profile="low-cut" aria-labelledby="<?php echo esc_attr( $id ); ?>-heading">
	<div class="pc-size-shape__heading">
		<h2 id="<?php echo esc_attr( $id ); ?>-heading"><?php esc_html_e( 'Size & Shape', 'parameter-commerce' ); ?></h2>
		<p><span><?php echo esc_html( $sizing['label'] ); ?></span> <strong><?php echo esc_html( $sizing['value'] ); ?></strong></p>
	</div>
	<div class="pc-size-shape__body">
		<svg class="pc-size-shape__diagram" viewBox="25 100 310 175" role="img" aria-labelledby="<?php echo esc_attr( $id ); ?>-title <?php echo esc_attr( $id ); ?>-desc" focusable="false">
			<title id="<?php echo esc_attr( $id ); ?>-title"><?php esc_html_e( 'Low-cut sock with a raised heel tab', 'parameter-commerce' ); ?></title>
			<desc id="<?php echo esc_attr( $id ); ?>-desc"><?php esc_html_e( 'Illustrative side profile based on the product photo. The low opening and heel tab are shown without measurement arrows; the outline is not to scale.', 'parameter-commerce' ); ?></desc>
			<path class="pc-size-shape__sock" d="M62 177 Q64 157 80 146 L96 130 Q105 134 110 149 L123 174 Q145 186 166 166 Q193 187 220 196 L282 211 Q309 217 309 233 Q308 249 282 251 H91 Q57 250 54 225 Q51 202 62 177 Z"/>
			<path class="pc-size-shape__detail" d="M77 158 L87 151 Q101 177 120 187 Q143 197 169 177 M58 209 Q99 206 108 251 M274 209 Q259 230 268 251"/>
		</svg>
		<dl class="pc-size-shape__dimensions">
			<div><dt><?php esc_html_e( 'Profile', 'parameter-commerce' ); ?></dt><dd><?php esc_html_e( 'Low-cut', 'parameter-commerce' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Catalog foot length', 'parameter-commerce' ); ?></dt><dd><?php echo esc_html( wc_format_localized_decimal( $dimensions['foot_cm'] ) ); ?> <small><?php esc_html_e( 'cm', 'parameter-commerce' ); ?></small></dd></div>
		</dl>
	</div>
	<p class="pc-size-shape__caption"><?php esc_html_e( 'Illustrative profile · Not to scale', 'parameter-commerce' ); ?></p>
	<details class="pc-size-shape__source">
		<summary><?php esc_html_e( 'About these dimensions', 'parameter-commerce' ); ?></summary>
		<p><?php echo esc_html( $dimensions['source'] ); ?>.</p>
		<p>
		<?php
		/* translators: %s: catalog height in centimeters. Measurement endpoints are not confirmed. */
		echo esc_html( sprintf( __( 'The catalog lists a leg length of %s cm. Its measurement points are awaiting sample confirmation, so this value is not drawn as a cuff height.', 'parameter-commerce' ), wc_format_localized_decimal( $dimensions['leg_cm'] ) ) );
		?>
		</p>
		<p><?php esc_html_e( 'Use the shoe-size option for fit. The illustration shows the low-cut shape, not a measurement method or a size-specific test record.', 'parameter-commerce' ); ?></p>
	</details>
</section>
