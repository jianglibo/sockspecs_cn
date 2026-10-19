<?php
/**
 * Safe validation and conflict errors.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries field errors without database details. */
final class Failure extends \RuntimeException {
	/**
	 * Field-specific errors.
	 *
	 * @var array
	 */
	public $fields;
	/**
	 * HTTP response status.
	 *
	 * @var int
	 */
	public $status;

	/**
	 * Construct a safe error.
	 *
	 * @param array $fields Field messages.
	 * @param int   $status HTTP status.
	 */
	public function __construct( array $fields, $status = 422 ) {
		$this->fields = $fields;
		$this->status = $status;
		parent::__construct( implode( ' ', $fields ) );
	}
}
