<?php
/**
 * Callback and webhook handlers.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Sauki Pay redirects and webhooks.
 */
class SaukiPay_Webhook {
	/**
	 * Settings service.
	 *
	 * @var SaukiPay_Settings
	 */
	private $settings;

	/**
	 * API client.
	 *
	 * @var SaukiPay_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param SaukiPay_Settings $settings Settings service.
	 * @param SaukiPay_API      $api API client.
	 */
	public function __construct( SaukiPay_Settings $settings, SaukiPay_API $api ) {
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_listener' ) );
		add_action( 'woocommerce_api_saukipay_callback', array( $this, 'handle_callback' ) );
	}

	/**
	 * Register public query var.
	 *
	 * @return void
	 */
	public function add_query_var() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'saukipay-listener';
		return $vars;
	}

	/**
	 * Handle query listener.
	 *
	 * @return void
	 */
	public function maybe_handle_listener() {
		$listener = get_query_var( 'saukipay-listener' );

		if ( empty( $listener ) && isset( $_GET['saukipay-listener'] ) ) {
			$listener = sanitize_key( wp_unslash( $_GET['saukipay-listener'] ) );
		}

		if ( 'callback' === $listener ) {
			$this->handle_callback();
		}

		if ( 'webhook' === $listener ) {
			$this->handle_webhook();
		}
	}

	/**
	 * Handle checkout redirect callback.
	 *
	 * @return void
	 */
	public function handle_callback() {
		$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';

		if ( '' === $reference ) {
			$this->redirect_form_result( 'failed', __( 'Missing Sauki Pay payment reference.', 'saukipay' ) );
		}

		$verification = $this->api->verify_payment( $reference );

		if ( is_wp_error( $verification ) ) {
			$this->add_order_note_by_reference( $reference, 'Sauki Pay callback verification failed: ' . $verification->get_error_message() );
			$this->redirect_form_result( 'failed', __( 'Payment verification failed. Please contact support.', 'saukipay' ) );
		}

		$data   = isset( $verification['data'] ) && is_array( $verification['data'] ) ? $verification['data'] : array();
		$status = isset( $data['status'] ) ? strtolower( sanitize_text_field( $data['status'] ) ) : '';

		if ( $this->update_woocommerce_order( $reference, $data, 'callback' ) ) {
			return;
		}

		if ( $this->update_give_payment( $reference, $data, 'callback' ) ) {
			return;
		}

		$this->update_form_transaction( $reference, $data, 'success' === $status ? 'success' : 'failed' );
		$this->redirect_form_result(
			'success' === $status ? 'success' : 'failed',
			'success' === $status ? __( 'Payment successful. Thank you.', 'saukipay' ) : __( 'Payment was not successful.', 'saukipay' )
		);
	}

	/**
	 * Handle webhook.
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$raw_body = file_get_contents( 'php://input' );
		$headers  = $this->get_request_headers();

		if ( ! $this->is_valid_webhook( $raw_body, $headers ) ) {
			status_header( 401 );
			echo 'invalid';
			exit;
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) || empty( $payload['data']['reference'] ) ) {
			status_header( 400 );
			echo 'invalid';
			exit;
		}

		$data      = is_array( $payload['data'] ) ? $payload['data'] : array();
		$reference = sanitize_text_field( $data['reference'] );

		$this->update_woocommerce_order( $reference, $data, 'webhook' );
		$this->update_give_payment( $reference, $data, 'webhook' );
		$this->update_form_transaction( $reference, $data, 'success' === strtolower( (string) ( $data['status'] ?? '' ) ) ? 'success' : 'failed' );

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'ok';
		exit;
	}

	/**
	 * Validate webhook headers.
	 *
	 * @param string $raw_body Raw request body.
	 * @param array  $headers Request headers.
	 * @return bool
	 */
	private function is_valid_webhook( $raw_body, array $headers ) {
		$public_key = $this->settings->public_key();
		$api_key    = isset( $headers['apikey'] ) ? sanitize_text_field( $headers['apikey'] ) : '';
		$signature  = isset( $headers['x-saukipay-signature'] ) ? sanitize_text_field( $headers['x-saukipay-signature'] ) : '';

		if ( '' === $public_key ) {
			return false;
		}

		$api_key_valid = hash_equals( $public_key, $api_key );
		$expected      = hash_hmac( 'sha512', $raw_body, $public_key );
		$signature_ok  = '' !== $signature && hash_equals( $expected, $signature );

		return $api_key_valid && $signature_ok;
	}

	/**
	 * Normalize request headers.
	 *
	 * @return array
	 */
	private function get_request_headers() {
		$headers = array();

		foreach ( $_SERVER as $key => $value ) {
			if ( 0 === strpos( $key, 'HTTP_' ) ) {
				$name             = strtolower( str_replace( '_', '-', substr( $key, 5 ) ) );
				$headers[ $name ] = is_string( $value ) ? $value : '';
			}
		}

		return $headers;
	}

	/**
	 * Update WooCommerce order from reference.
	 *
	 * @param string $reference Payment reference.
	 * @param array  $data Verified or webhook data.
	 * @param string $source Update source.
	 * @return bool
	 */
	public function update_woocommerce_order( $reference, array $data, $source ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_saukipay_reference',
				'meta_value' => $reference,
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) ) {
			return false;
		}

		$order  = $orders[0];
		$status = isset( $data['status'] ) ? strtolower( sanitize_text_field( $data['status'] ) ) : '';

		if ( 'success' === $status ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $reference );
			}
			$order->add_order_note( sprintf( 'Sauki Pay %s verification successful. Reference: %s', sanitize_text_field( $source ), $reference ) );
		} else {
			$order->update_status( 'failed', sprintf( 'Sauki Pay %s reported failed payment. Reference: %s', sanitize_text_field( $source ), $reference ) );
		}

		$order->update_meta_data( '_saukipay_last_status', $status );
		$order->update_meta_data( '_saukipay_payment_channel', isset( $data['paymentChannel'] ) ? sanitize_text_field( $data['paymentChannel'] ) : '' );
		$order->save();

		if ( 'callback' === $source ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		return true;
	}

	/**
	 * Update GiveWP payment from reference.
	 *
	 * @param string $reference Payment reference.
	 * @param array  $data Verified or webhook data.
	 * @param string $source Update source.
	 * @return bool
	 */
	public function update_give_payment( $reference, array $data, $source ) {
		if ( ! post_type_exists( 'give_payment' ) ) {
			return false;
		}

		$payments = get_posts(
			array(
				'post_type'      => 'give_payment',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_saukipay_reference',
				'meta_value'     => $reference,
			)
		);

		if ( empty( $payments ) ) {
			return false;
		}

		$payment_id = absint( $payments[0] );
		$status     = isset( $data['status'] ) ? strtolower( sanitize_text_field( $data['status'] ) ) : '';

		if ( 'success' === $status ) {
			$this->update_give_payment_status( $payment_id, 'publish' );
			$this->add_give_payment_note( $payment_id, sprintf( 'Sauki Pay %s verification successful. Reference: %s', sanitize_text_field( $source ), $reference ) );
		} else {
			$this->update_give_payment_status( $payment_id, 'failed' );
			$this->add_give_payment_note( $payment_id, sprintf( 'Sauki Pay %s reported failed payment. Reference: %s', sanitize_text_field( $source ), $reference ) );
		}

		$this->update_give_payment_meta( $payment_id, '_saukipay_last_status', $status );
		$this->update_give_payment_meta( $payment_id, '_saukipay_payment_channel', isset( $data['paymentChannel'] ) ? sanitize_text_field( $data['paymentChannel'] ) : '' );

		if ( 'callback' === $source ) {
			wp_safe_redirect( $this->give_redirect_url( 'success' === $status ) );
			exit;
		}

		return true;
	}

	/**
	 * Update GiveWP payment status.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $status Payment status.
	 * @return void
	 */
	private function update_give_payment_status( $payment_id, $status ) {
		if ( function_exists( 'give_update_payment_status' ) ) {
			give_update_payment_status( $payment_id, $status );
			return;
		}

		wp_update_post(
			array(
				'ID'          => absint( $payment_id ),
				'post_status' => sanitize_key( $status ),
			)
		);
	}

	/**
	 * Update GiveWP payment meta.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	private function update_give_payment_meta( $payment_id, $key, $value ) {
		if ( function_exists( 'give_update_payment_meta' ) ) {
			give_update_payment_meta( $payment_id, $key, $value );
			return;
		}

		update_post_meta( $payment_id, $key, $value );
	}

	/**
	 * Add GiveWP payment note.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $note Payment note.
	 * @return void
	 */
	private function add_give_payment_note( $payment_id, $note ) {
		if ( function_exists( 'give_insert_payment_note' ) ) {
			give_insert_payment_note( $payment_id, sanitize_text_field( $note ) );
		}
	}

	/**
	 * Get GiveWP callback redirect URL.
	 *
	 * @param bool $success Whether payment succeeded.
	 * @return string
	 */
	private function give_redirect_url( $success ) {
		if ( $success && function_exists( 'give_get_success_page_uri' ) ) {
			return give_get_success_page_uri();
		}

		if ( ! $success && function_exists( 'give_get_failed_transaction_uri' ) ) {
			return give_get_failed_transaction_uri();
		}

		return home_url( '/' );
	}

	/**
	 * Add order note by reference.
	 *
	 * @param string $reference Payment reference.
	 * @param string $note Order note.
	 * @return void
	 */
	private function add_order_note_by_reference( $reference, $note ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_saukipay_reference',
				'meta_value' => $reference,
				'return'     => 'objects',
			)
		);

		if ( ! empty( $orders ) ) {
			$orders[0]->add_order_note( sanitize_text_field( $note ) );
		}
	}

	/**
	 * Update shortcode transaction option.
	 *
	 * @param string $reference Payment reference.
	 * @param array  $data Payment data.
	 * @param string $status Status.
	 * @return void
	 */
	private function update_form_transaction( $reference, array $data, $status ) {
		$transaction = get_option( 'saukipay_form_txn_' . sanitize_key( $reference ), array() );

		if ( is_array( $transaction ) ) {
			$transaction['status']       = sanitize_key( $status );
			$transaction['verified_at']  = time();
			$transaction['payment_data'] = $data;
			update_option( 'saukipay_form_txn_' . sanitize_key( $reference ), $transaction, false );
		}
	}

	/**
	 * Redirect to a frontend result page.
	 *
	 * @param string $status Result status.
	 * @param string $message Result message.
	 * @return void
	 */
	private function redirect_form_result( $status, $message ) {
		$url = add_query_arg(
			array(
				'saukipay_result'  => sanitize_key( $status ),
				'saukipay_message' => rawurlencode( $message ),
			),
			home_url( '/' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
