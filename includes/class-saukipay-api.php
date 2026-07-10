<?php
/**
 * Sauki Pay API client.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API wrapper around Sauki Pay endpoints.
 */
class SaukiPay_API {
	/**
	 * Settings service.
	 *
	 * @var SaukiPay_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SaukiPay_Settings $settings Settings service.
	 */
	public function __construct( SaukiPay_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize payment.
	 *
	 * @param array $payload Initialization payload.
	 * @return array|WP_Error
	 */
	public function initialize_payment( array $payload ) {
		return $this->request( 'POST', '/transaction/init', $payload );
	}

	/**
	 * Extract checkout URL from an initialize response.
	 *
	 * @param array $response Initialize response.
	 * @return string
	 */
	public function get_checkout_url( array $response ) {
		$candidates = array(
			isset( $response['data']['checkout'] ) ? $response['data']['checkout'] : '',
			isset( $response['data']['checkout_url'] ) ? $response['data']['checkout_url'] : '',
			isset( $response['data']['checkoutUrl'] ) ? $response['data']['checkoutUrl'] : '',
			isset( $response['data']['payment_url'] ) ? $response['data']['payment_url'] : '',
			isset( $response['data']['paymentUrl'] ) ? $response['data']['paymentUrl'] : '',
			isset( $response['checkout'] ) ? $response['checkout'] : '',
			isset( $response['checkout_url'] ) ? $response['checkout_url'] : '',
			isset( $response['checkoutUrl'] ) ? $response['checkoutUrl'] : '',
			isset( $response['payment_url'] ) ? $response['payment_url'] : '',
			isset( $response['paymentUrl'] ) ? $response['paymentUrl'] : '',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return esc_url_raw( $candidate );
			}
		}

		return '';
	}

	/**
	 * Extract access code from an initialize response.
	 *
	 * @param array $response Initialize response.
	 * @return string
	 */
	public function get_access_code( array $response ) {
		$candidates = array(
			isset( $response['data']['accessCode'] ) ? $response['data']['accessCode'] : '',
			isset( $response['data']['access_code'] ) ? $response['data']['access_code'] : '',
			isset( $response['accessCode'] ) ? $response['accessCode'] : '',
			isset( $response['access_code'] ) ? $response['access_code'] : '',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return sanitize_text_field( $candidate );
			}
		}

		return '';
	}

	/**
	 * Log a safe copy of an API response for troubleshooting.
	 *
	 * @param string $message Log message.
	 * @param mixed  $context Context to log.
	 * @return void
	 */
	public function log_debug( $message, $context = null ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( null !== $context ) {
			$message .= ' ' . wp_json_encode( $this->redact_sensitive_data( $context ) );
		}

		error_log( '[Sauki Pay] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Verify payment by reference.
	 *
	 * @param string $reference Payment reference.
	 * @return array|WP_Error
	 */
	public function verify_payment( $reference ) {
		$reference = rawurlencode( sanitize_text_field( $reference ) );
		return $this->request( 'GET', '/transaction/verify/' . $reference );
	}

	/**
	 * Make API request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path API path.
	 * @param array|null $payload Optional payload.
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $payload = null ) {
		$secret_key = $this->settings->secret_key();

		if ( '' === $secret_key ) {
			return new WP_Error( 'saukipay_missing_secret_key', __( 'Sauki Pay secret key is not configured.', 'saukipay' ) );
		}

		$args = array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		$url = $this->settings->api_base_url() . $path;

		if ( 'POST' === strtoupper( $method ) ) {
			$args['body'] = wp_json_encode( $payload );
			$response     = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( $body, true );

		if ( null === $json && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'saukipay_invalid_response', __( 'Sauki Pay returned an invalid response.', 'saukipay' ), array( 'status' => $code, 'body' => $body ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'saukipay_http_error', __( 'Sauki Pay request failed.', 'saukipay' ), array( 'status' => $code, 'body' => $json ) );
		}

		return $json;
	}

	/**
	 * Redact sensitive values before logging.
	 *
	 * @param mixed $value Value to redact.
	 * @return mixed
	 */
	private function redact_sensitive_data( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$redacted = array();

		foreach ( $value as $key => $item ) {
			$normalized_key = strtolower( (string) $key );

			if ( false !== strpos( $normalized_key, 'secret' ) || false !== strpos( $normalized_key, 'authorization' ) || false !== strpos( $normalized_key, 'token' ) ) {
				$redacted[ $key ] = '[redacted]';
				continue;
			}

			$redacted[ $key ] = is_array( $item ) ? $this->redact_sensitive_data( $item ) : $item;
		}

		return $redacted;
	}
}
