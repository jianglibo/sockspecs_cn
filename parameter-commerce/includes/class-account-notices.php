<?php
/**
 * Helpful inbox guidance on native account confirmation screens.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep account creation, delivery and authentication owned by WordPress/WooCommerce. */
final class Account_Notices {
	/** Register narrowly scoped message filters. @return void */
	public static function register_hooks() {
		add_filter( 'gettext_woocommerce', array( self::class, 'confirmation' ), 10, 2 );
		add_filter( 'gettext_default', array( self::class, 'confirmation' ), 10, 2 );
		add_filter( 'woocommerce_lost_password_confirmation_message', array( self::class, 'reminder' ) );
	}

	/**
	 * Extend only native successful registration/reset confirmation messages.
	 *
	 * @param string $translation Existing translated message, including native links.
	 * @param string $text Original source string.
	 * @return string
	 */
	public static function confirmation( $translation, $text ) {
		$messages = array(
			'Your account was created successfully and a password has been sent to your email address.',
			'Your account was created successfully. Your login details have been sent to your email address.',
			'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.',
			'Registration complete. Please check your email, then visit the <a href="%s">login page</a>.',
		);
		return in_array( $text, $messages, true ) ? self::reminder( $translation ) : $translation;
	}

	/**
	 * Append guidance without replacing the original confirmation or wait time.
	 *
	 * @param string $message Existing confirmation message.
	 * @return string
	 */
	public static function reminder( $message ) {
		return $message . ' ' . esc_html__( 'If the email is not in your inbox, please check your spam or junk folder.', 'parameter-commerce' );
	}
}
