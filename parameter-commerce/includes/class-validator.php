<?php
/**
 * Typed input and publication rules. Missing values remain null.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Structured errors are escaped by the admin controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates unslashed input before persistence. */
final class Validator {
	/**
	 * Validate and sanitize a claim, retaining incomplete drafts.
	 *
	 * @param array $input Unslashed fields.
	 * @return array Normalized database fields.
	 * @throws Failure When a supplied value is malformed.
	 */
	public static function claim( array $input ) {
		$errors = array();
		$data   = array();
		foreach ( array( 'wc_product_id', 'wc_variation_id', 'sample_count' ) as $field ) {
			$value = self::text( $input, $field, 20, $errors );
			if ( '' !== $value && ( ! ctype_digit( $value ) || (float) $value > 2147483647 ) ) {
				$errors[ $field ] = __( 'Use a nonnegative whole number within the supported ID/count range.', 'parameter-commerce' );
			}
			$data[ $field ] = '' === $value ? null : (int) $value;
		}
		$data['wc_variation_id'] = $data['wc_variation_id'] ?? 0;
		foreach ( array(
			'metric_key'     => 64,
			'unit'           => 64,
			'source_type'    => 32,
			'size_scope'     => 191,
			'method_code'    => 191,
			'method_version' => 64,
			'sample_batch'   => 191,
			'measured_at'    => 10,
			'note'           => 8000,
		) as $field => $limit ) {
			$data[ $field ] = self::text( $input, $field, $limit, $errors );
		}
		$definition = Metrics::defaults()[ $data['metric_key'] ] ?? null;
		if ( ! $definition ) {
			$errors['metric_key'] = __( 'Choose a registered sock metric.', 'parameter-commerce' );
		}
		if ( ! in_array( $data['source_type'], array( 'supplier_declared', 'platform_measured', 'third_party_lab', 'supplier_report' ), true ) ) {
			$errors['source_type'] = __( 'Choose a valid evidence source tier.', 'parameter-commerce' );
		}
		$data['value_num']  = null;
		$data['value_enum'] = null;
		$value              = self::text( $input, 'value', 64, $errors );
		if ( $definition ) {
			if ( null !== $definition['unit'] && $definition['unit'] !== $data['unit'] ) {
				$errors['unit'] = __( 'Use the metric dictionary unit exactly; values are never silently converted.', 'parameter-commerce' );
			}
			if ( isset( $definition['allowed_units'] ) && ! in_array( $data['unit'], $definition['allowed_units'], true ) && ( '' !== $value || '' !== $data['unit'] ) ) {
				$errors['unit'] = __( 'Use a documented yarn count system: Ne, Nm, tex, dtex or denier. Do not assume the meaning of S.', 'parameter-commerce' );
			}
			if ( '' !== $value ) {
				if ( 'enum' === $definition['type'] ) {
					if ( ! in_array( $value, $definition['choices'], true ) ) {
						$errors['value'] = __( 'Choose one of the metric dictionary values.', 'parameter-commerce' );
					} else {
						$data['value_enum'] = $value;
					}
				} elseif ( ! preg_match( '/^-?\d{1,14}(?:\.\d{1,6})?$/D', $value ) || ( 'integer' === $definition['type'] && floor( (float) $value ) !== (float) $value ) ) {
					$errors['value'] = __( 'Use a plain number with at most 14 integer and 6 decimal digits; integer metrics require whole numbers.', 'parameter-commerce' );
				} elseif ( ( null !== $definition['min'] && (float) $value < $definition['min'] ) || ( null !== $definition['max'] && (float) $value > $definition['max'] ) ) {
					$errors['value'] = __( 'The value is outside this metric’s allowed range.', 'parameter-commerce' );
				} else {
					$data['value_num'] = $value;
				}
			}
		}
		$date = $data['measured_at'];
		if ( '' !== $date ) {
			$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date > gmdate( 'Y-m-d' ) ) {
				$errors['measured_at'] = __( 'Enter a valid, nonfuture source or measurement date (YYYY-MM-DD).', 'parameter-commerce' );
			}
		} else {
			$data['measured_at'] = null;
		}
		$conditions = self::text( $input, 'conditions_json', 8000, $errors, false );
		if ( '' === $conditions ) {
			$conditions = '{}';
		}
		$decoded = json_decode( $conditions );
		if ( ! is_object( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			$errors['conditions_json'] = __( 'Test conditions must be a JSON object, such as {"conditioning":"documented procedure"}.', 'parameter-commerce' );
		}
		$data['conditions_json'] = is_object( $decoded ) ? wp_json_encode( $decoded ) : '{}';
		if ( ! Plugin::has_woocommerce() ) {
			$errors['wc_product_id'] = __( 'WooCommerce must be active to edit claims.', 'parameter-commerce' );
		} else {
			$product = wc_get_product( $data['wc_product_id'] ?? 0 );
			if ( ! $product || $product->is_type( 'variation' ) || 'trash' === $product->get_status() ) {
				$errors['wc_product_id'] = __( 'Choose an existing WooCommerce parent or simple product.', 'parameter-commerce' );
			}
			if ( $data['wc_variation_id'] ) {
				$variation = wc_get_product( $data['wc_variation_id'] );
				if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $data['wc_product_id'] || 'trash' === $variation->get_status() ) {
					$errors['wc_variation_id'] = __( 'The variation must belong to the selected product.', 'parameter-commerce' );
				}
			}
		}
		if ( $errors ) {
			throw new Failure( $errors );
		}
		return $data;
	}

	/**
	 * Validate evidence. Private records never use ordinary public Media URLs.
	 *
	 * @param array $input Evidence fields.
	 * @return array
	 * @throws Failure When metadata or a URL/attachment is invalid.
	 */
	public static function evidence( array $input ) {
		$errors = array();
		$data   = array();
		foreach ( array(
			'public_title'  => 191,
			'source_org'    => 191,
			'note'          => 8000,
			'source_url'    => 2048,
			'public_url'    => 2048,
			'visibility'    => 16,
			'attachment_id' => 20,
		) as $key => $length ) {
			$data[ $key ] = self::text( $input, $key, $length, $errors );
		}
		if ( ! in_array( $data['visibility'], array( 'private', 'public' ), true ) ) {
			$errors['evidence.visibility'] = __( 'Choose public or private evidence visibility.', 'parameter-commerce' );
		}
		foreach ( array( 'source_url', 'public_url' ) as $field ) {
			$url = $data[ $field ];
			if ( '' !== $url && ( ! filter_var( $url, FILTER_VALIDATE_URL ) || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || wp_parse_url( $url, PHP_URL_USER ) || wp_parse_url( $url, PHP_URL_PASS ) ) ) {
				$errors[ 'evidence.' . $field ] = __( 'Use an HTTPS URL without embedded credentials.', 'parameter-commerce' );
			}
			$data[ $field ] = esc_url_raw( $url, array( 'https' ) );
		}
		if ( '' !== $data['attachment_id'] && ( ! ctype_digit( $data['attachment_id'] ) || (float) $data['attachment_id'] > 2147483647 ) ) {
			$errors['evidence.attachment_id'] = __( 'Use a valid Media Library attachment ID.', 'parameter-commerce' );
		}
		$data['attachment_id'] = empty( $data['attachment_id'] ) ? null : (int) $data['attachment_id'];
		if ( 'private' === $data['visibility'] && ( $data['attachment_id'] || $data['public_url'] ) ) {
			$errors['evidence.visibility'] = __( 'Private evidence cannot expose a public URL or Media Library attachment. Use an access-controlled source URL; ordinary WordPress uploads are public.', 'parameter-commerce' );
		}
		if ( $data['attachment_id'] ) {
			$attachment = get_post( $data['attachment_id'] );
			if ( ! $attachment || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status || ! current_user_can( 'edit_post', $attachment->ID ) || ! in_array( get_post_mime_type( $attachment ), array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				$errors['evidence.attachment_id'] = __( 'Choose an accessible PDF, JPEG, PNG, or WebP attachment intended for public access.', 'parameter-commerce' );
			}
		}
		if ( $errors ) {
			throw new Failure( $errors );
		}
		return $data;
	}

	/**
	 * Check mandatory publication provenance. Also used when marking reviewed.
	 *
	 * @param array $claim Normalized claim.
	 * @param array $evidence Evidence record.
	 * @return void
	 * @throws Failure When required provenance is missing.
	 */
	public static function publishable( array $claim, array $evidence ) {
		$errors     = array();
		$definition = Metrics::defaults()[ $claim['metric_key'] ];
		if ( null === $claim['value_num'] && null === $claim['value_enum'] ) {
			$errors['value'] = __( 'A missing value cannot be published. Keep it as a draft / Not tested.', 'parameter-commerce' );
		}
		if ( 'enum' !== $definition['type'] && '' === $claim['unit'] ) {
			$errors['unit'] = __( 'An explicit measurement unit is required.', 'parameter-commerce' );
		}
		if ( ! $claim['measured_at'] ) {
			$errors['measured_at'] = __( 'A source or measurement date is required.', 'parameter-commerce' );
		}
		if ( empty( $evidence['source_url'] ) && empty( $evidence['public_url'] ) && empty( $evidence['attachment_id'] ) && ! ( 'private' === ( $evidence['visibility'] ?? '' ) && preg_match( '/Stored source SHA-256: [a-f0-9]{64}(?:[.; ]|$)/D', ( $evidence['note'] ?? '' ) ) ) ) {
			$errors['evidence.source_url'] = __( 'Attach evidence or provide a documented source URL.', 'parameter-commerce' );
		}
		foreach ( array( 'public_title', 'source_org' ) as $field ) {
			if ( empty( $evidence[ $field ] ) ) {
				$errors[ 'evidence.' . $field ] = __( 'An evidence title and source organization are required.', 'parameter-commerce' );
			}
		}
		if ( 'supplier_report' === $claim['source_type'] ) {
			if ( ! in_array( $definition['category'], array( 'composition', 'material' ), true ) || ! $claim['size_scope'] || ! $claim['method_code'] || ! $claim['method_version'] || ! $claim['note'] ) {
				$errors['report'] = __( 'Supplier-provided reports require composition or material values, tested area, methods and explicit sample limitations.', 'parameter-commerce' );
			}
		}
		if ( ! empty( $definition['material_scope'] ) ) {
			$conditions = json_decode( $claim['conditions_json'], true );
			if ( ! $claim['size_scope'] || ! in_array( $conditions['area'] ?? '', array( 'whole', 'body', 'sole', 'heel_toe', 'cuff' ), true ) || ! is_string( $conditions['material_component'] ?? null ) || '' === trim( $conditions['material_component'] ) ) {
				$errors['size_scope'] = __( 'Material records require applicable sizes/colors, an area (whole, body, sole, heel_toe or cuff), and the documented material_component in conditions.', 'parameter-commerce' );
			}
			if ( 'yarn_count' === $claim['metric_key'] && 'single_strand' !== ( $conditions['count_basis'] ?? '' ) ) {
				$errors['conditions_json'] = __( 'Yarn count requires count_basis: single_strand. Confirm the numbering system and strand basis; do not infer them from an ambiguous count.', 'parameter-commerce' );
			}
		}
		$tested = 'performance' === $definition['category'] || in_array( $claim['source_type'], array( 'platform_measured', 'third_party_lab' ), true );
		if ( $tested ) {
			foreach ( array( 'method_code', 'method_version', 'sample_batch' ) as $field ) {
				if ( '' === $claim[ $field ] ) {
					$errors[ $field ] = __( 'Tested claims require a method, version, and sample batch.', 'parameter-commerce' );
				}
			}
			if ( empty( $claim['sample_count'] ) ) {
				$errors['sample_count'] = __( 'Enter the actual positive sample count; no count is assumed.', 'parameter-commerce' );
			}
			if ( empty( (array) json_decode( $claim['conditions_json'] ) ) ) {
				$errors['conditions_json'] = __( 'Document the actual test conditions, including the procedure and endpoint where applicable.', 'parameter-commerce' );
			}
		}
		if ( 'measurement' === $definition['category'] && '' === $claim['size_scope'] ) {
			$errors['size_scope'] = __( 'Dimensions and weight require an explicit specimen size or size scope.', 'parameter-commerce' );
		}
		if ( $errors ) {
			throw new Failure( $errors );
		}
	}

	/**
	 * Validate total composition without silently filling missing fractions.
	 *
	 * @param array $values Present composition values keyed by metric.
	 * @param float $tolerance Explicitly configured percentage-point tolerance.
	 * @return void
	 * @throws Failure When a supplied total is invalid.
	 */
	public static function composition( array $values, $tolerance ) {
		$merino = $values['merino_wool_pct'] ?? null;
		unset( $values['merino_wool_pct'] );
		if ( null !== $merino && isset( $values['wool_pct'] ) && $merino > $values['wool_pct'] + $tolerance + 0.000001 ) {
			throw new Failure( array( 'value' => __( 'Merino wool is a subset of total wool and cannot exceed it.', 'parameter-commerce' ) ) );
		}
		// Merino is not a seventh independent fiber. If total wool is unknown,
		// it is only a lower bound, never a fabricated total-wool measurement.
		$sum = array_sum( $values ) + ( ! isset( $values['wool_pct'] ) && null !== $merino ? $merino : 0 );
		if ( $sum > 100 + $tolerance + 0.000001 || ( 6 === count( $values ) && abs( $sum - 100 ) > $tolerance + 0.000001 ) ) {
			throw new Failure( array( 'value' => __( 'Composition exceeds 100% or a complete six-fiber mix is outside the configured tolerance. Unknown fractions are not normalized.', 'parameter-commerce' ) ) );
		}
	}

	/**
	 * Read a bounded scalar. All callers supply already-unslashed data.
	 *
	 * @param array  $input Input fields.
	 * @param string $key Field key.
	 * @param int    $limit Character limit.
	 * @param array  $errors Collected errors.
	 * @param bool   $sanitize Whether to strip markup (false for JSON).
	 * @return string
	 */
	private static function text( array $input, $key, $limit, array &$errors, $sanitize = true ) {
		$value = $input[ $key ] ?? '';
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			$errors[ $key ] = __( 'Expected a scalar value.', 'parameter-commerce' );
			return '';
		}
		$value = trim( (string) $value );
		if ( mb_strlen( $value ) > $limit ) {
			$errors[ $key ] = __( 'This field is too long.', 'parameter-commerce' );
		}
		return $sanitize ? sanitize_textarea_field( $value ) : $value;
	}
}
