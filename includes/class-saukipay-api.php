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
}
