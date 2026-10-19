<?php
/**
 * Signed YunExpress OpenAPI transport. No automatic retries of writes.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Server-only transport; callers never receive credentials or raw HTTP logs. */
final class YunExpress_Client {
	/** Environment.
	 *
	 * @var string
	 */
	private $mode;
	/** Credentials.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Construct from server configuration, never request parameters.
	 *
	 * @param string $mode sandbox or production.
	 * @throws \RuntimeException On missing configuration.
	 */
	public function __construct( $mode ) {
		if ( ! in_array( $mode, array( 'sandbox', 'production' ), true ) ) {
			throw new \RuntimeException( esc_html__( 'Choose a shipping environment first.', 'parameter-commerce' ) );
		}
		if ( 'production' === $mode && ( ! defined( 'PC_YUNEXPRESS_LIVE_ENABLED' ) || true !== PC_YUNEXPRESS_LIVE_ENABLED ) ) {
			throw new \RuntimeException( esc_html__( 'Live shipping has not been enabled in the server configuration.', 'parameter-commerce' ) );
		}
		$this->mode        = $mode;
		$prefix            = 'sandbox' === $mode ? 'YUNEXPRESS_SANDBOX_' : 'YUNEXPRESS_';
		$this->credentials = array(
			'appId'     => self::secret( $prefix . 'APP_ID' ),
			'appSecret' => self::secret( $prefix . 'SECRET' ),
			'sourceKey' => self::secret( 'YUNEXPRESS_SOURCE_KEY' ),
		);
		if ( in_array( '', $this->credentials, true ) ) {
			throw new \RuntimeException( esc_html__( 'YunExpress credentials are missing from the server configuration.', 'parameter-commerce' ) );
		}
	}

	/** Account identity binds recovery to the original environment and application.
	 *
	 * @return string
	 */
	public function identity() {
		return hash( 'sha256', $this->mode . $this->credentials['appId'] . $this->credentials['sourceKey'] );
	}

	/**
	 * Read only an explicitly named runtime constant/environment variable.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function secret( $name ) {
		return trim( (string) ( defined( $name ) ? constant( $name ) : getenv( $name ) ) );
	}

	/**
	 * Match the documented canonical request; body bytes equal the sent bytes.
	 *
	 * @param string $secret Signing secret.
	 * @param string $date Millisecond timestamp.
	 * @param string $method HTTP method.
	 * @param string $path Path without query string.
	 * @param string $body Exact JSON or empty string.
	 * @return string
	 */
	public static function signature( $secret, $date, $method, $path, $body = '' ) {
		$content = ( '' !== $body ? 'body=' . $body . '&' : '' ) . 'date=' . $date . '&method=' . $method . '&uri=' . $path;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required OpenAPI signature encoding.
		return base64_encode( hash_hmac( 'sha256', $content, $secret, true ) );
	}

	/**
	 * HTTP transport. Disable redirects so credentials cannot cross hosts.
	 *
	 * @param string $path Relative API path.
	 * @param string $method Method.
	 * @param string $body Exact body bytes.
	 * @param array  $headers Additional headers.
	 * @return array|\WP_Error
	 */
	private function send( $path, $method, $body, $headers = array() ) {
		$base     = 'sandbox' === $this->mode ? 'https://openapi-sbx.yunexpress.cn' : 'https://openapi.yunexpress.cn';
		$response = wp_remote_request(
			$base . $path,
			array(
				'method'              => $method,
				'timeout'             => 25,
				'redirection'         => 0,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'headers'             => array_merge(
					array(
						'Content-Type'    => 'application/json;charset=utf-8',
						'Accept-Language' => 'en-US',
					),
					$headers
				),
				'body'                => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'yun_transport', __( 'YunExpress did not return a response. Reconcile the shipment before trying again.', 'parameter-commerce' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'yun_response', __( 'YunExpress returned an unexpected response. Reconcile the shipment before trying again.', 'parameter-commerce' ), array( 'http_status' => $status ) );
		}
		return $data;
	}

	/**
	 * Cache a short-lived bearer credential privately; refresh before expiry.
	 *
	 * @return string|\WP_Error
	 */
	private function token() {
		$key   = 'pc_yun_token_' . hash_hmac( 'sha256', $this->mode . wp_json_encode( $this->credentials ), wp_salt( 'auth' ) );
		$token = get_transient( $key );
		if ( is_string( $token ) && '' !== $token ) {
			return $token;
		}
		return Shipping::locked(
			$key,
			function () use ( $key ) {
				$token = get_transient( $key );
				if ( is_string( $token ) && '' !== $token ) {
					return $token;
				}
				$data = $this->send( '/openapi/oauth2/token', 'POST', wp_json_encode( array_merge( array( 'grantType' => 'client_credentials' ), $this->credentials ) ) );
				if ( is_wp_error( $data ) || empty( $data['accessToken'] ) || ! is_string( $data['accessToken'] ) ) {
					return new \WP_Error( 'yun_auth', __( 'YunExpress authentication failed. Check the environment and server credentials.', 'parameter-commerce' ) );
				}
				$ttl = min( 7200, max( 0, (int) ( $data['expiresIn'] ?? 0 ) - 120 ) );
				if ( $ttl > 0 ) {
					set_transient( $key, $data['accessToken'], $ttl );
				}
				return $data['accessToken'];
			}
		);
	}

	/**
	 * Allowlisted requests. The caller handles write ambiguity explicitly.
	 *
	 * @param string $operation Operation name.
	 * @param array  $payload JSON body or GET parameters.
	 * @return array|\WP_Error
	 */
	public function request( $operation, $payload = array() ) {
		$routes = array(
			'products' => array( 'GET', '/v1/basic-data/products/getlist' ),
			'create'   => array( 'POST', '/v1/order/package/create' ),
			'lookup'   => array( 'GET', '/v1/order/info/get' ),
			'label'    => array( 'GET', '/v1/order/label/get' ),
			'cancel'   => array( 'POST', '/v1/order/cancel' ),
		);
		if ( ! isset( $routes[ $operation ] ) ) {
			return new \WP_Error( 'yun_operation', __( 'Unsupported shipping operation.', 'parameter-commerce' ) );
		}
		$token = $this->token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		list( $method, $path ) = $routes[ $operation ];
		$body                  = 'POST' === $method ? wp_json_encode( $payload ) : '';
		$date                  = (string) (int) floor( microtime( true ) * 1000 );
		$headers               = array(
			'token' => $token,
			'date'  => $date,
			'sign'  => self::signature( $this->credentials['appSecret'], $date, $method, $path, $body ),
		);
		$url                   = 'GET' === $method ? add_query_arg( $payload, $path ) : $path;
		$data                  = $this->send( $url, $method, $body, $headers );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( true !== ( $data['success'] ?? false ) ) {
			$code    = sanitize_key( (string) ( $data['code'] ?? 'unknown' ) );
			$message = sanitize_text_field( (string) ( $data['msg'] ?? '' ) );
			// Provider messages are staff-only; remove credentials even if echoed back.
			$message = str_replace( array_merge( array_values( $this->credentials ), array( $token, $headers['sign'] ) ), '[redacted]', $message );
			return new \WP_Error( 'yun_api', sprintf( /* translators: 1: provider error code, 2: provider message. */ __( 'YunExpress %1$s: %2$s', 'parameter-commerce' ), $code, mb_substr( $message, 0, 500 ) ), array( 'provider_code' => $code ) );
		}
		return $data;
	}
}
