<?php
/**
 * GiveWP payment gateway integration.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Sauki Pay as a GiveWP gateway.
 */
class SaukiPay_Give_Gateway {
	const GATEWAY_ID = 'saukipay';

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
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'give_get_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'givewp_register_payment_gateway', array( $this, 'register_modern_gateway' ) );
		add_action( 'give_gateway_' . self::GATEWAY_ID, array( $this, 'process_payment' ) );
	}

	/**
	 * Add Sauki Pay to GiveWP gateways.
	 *
	 * @param array $gateways Registered gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			$gateways = array();
		}

		$gateways[ self::GATEWAY_ID ] = array(
			'admin_label'    => __( 'Sauki Pay', 'saukipay' ),
			'checkout_label' => __( 'Sauki Pay', 'saukipay' ),
		);

		return $gateways;
	}

	/**
	 * Register Sauki Pay with GiveWP's modern gateway registry.
	 *
	 * @param object $registrar GiveWP payment gateway registrar.
	 * @return void
	 */
	public function register_modern_gateway( $registrar ) {
		if ( ! class_exists( '\Give\Framework\PaymentGateways\PaymentGateway' ) ) {
			return;
		}

		if ( ! is_object( $registrar ) || ! method_exists( $registrar, 'registerGateway' ) ) {
			return;
		}

		require_once SAUKIPAY_PATH . 'includes/class-saukipay-give-modern-gateway.php';

		if ( method_exists( $registrar, 'hasPaymentGateway' ) && $registrar->hasPaymentGateway( self::GATEWAY_ID ) ) {
			return;
		}

		try {
			$registrar->registerGateway( \SaukiPay\GiveWP\Modern_Gateway::class );
		} catch ( \Exception $exception ) {
			$this->api->log_debug( 'Unable to register modern GiveWP gateway.', array( 'message' => $exception->getMessage() ) );
		} catch ( \Throwable $throwable ) {
			$this->api->log_debug( 'Unable to register modern GiveWP gateway.', array( 'message' => $throwable->getMessage() ) );
		}
	}

	/**
	 * Process GiveWP donation payment.
	 *
	 * @param array $purchase_data GiveWP purchase data.
	 * @return void
	 */
	public function process_payment( $purchase_data ) {
		if ( ! $this->settings->is_enabled() || '' === $this->settings->secret_key() ) {
			$this->give_error( __( 'Sauki Pay is not configured. Please contact the site administrator.', 'saukipay' ) );
		}

		$payment_id = $this->insert_payment( $purchase_data );

		if ( ! $payment_id ) {
			$this->give_error( __( 'Unable to create GiveWP donation payment.', 'saukipay' ) );
		}

		$reference = $this->create_reference( $payment_id );
		$payload   = $this->build_payload( $purchase_data, $payment_id, $reference );

		$this->update_payment_meta( $payment_id, '_saukipay_reference', $reference );
		$this->add_payment_note( $payment_id, sprintf( 'Sauki Pay initialization started. Reference: %s', $reference ) );

		$response = $this->api->initialize_payment( $payload );

		if ( is_wp_error( $response ) ) {
			$this->add_payment_note( $payment_id, 'Sauki Pay initialization failed: ' . $response->get_error_message() );
			$this->update_payment_status( $payment_id, 'failed' );
			$this->give_error( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ) );
		}

		$checkout_url = $this->api->get_checkout_url( $response );

		if ( '' === $checkout_url ) {
			$this->api->log_debug( 'GiveWP payment initialization response missing checkout URL.', array( 'payment_id' => $payment_id, 'response' => $response ) );
			$this->add_payment_note( $payment_id, 'Sauki Pay initialization failed: checkout URL missing in API response.' );
			$this->update_payment_status( $payment_id, 'failed' );
			$this->give_error( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ) );
		}

		$this->update_payment_meta( $payment_id, '_saukipay_access_code', $this->api->get_access_code( $response ) );
		$this->update_payment_meta( $payment_id, '_saukipay_checkout_url', esc_url_raw( $checkout_url ) );
		$this->add_payment_note( $payment_id, sprintf( 'Sauki Pay initialized successfully. Reference: %s', $reference ) );

		wp_redirect( esc_url_raw( $checkout_url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Insert GiveWP payment record.
	 *
	 * @param array $purchase_data GiveWP purchase data.
	 * @return int
	 */
	private function insert_payment( array $purchase_data ) {
		if ( ! function_exists( 'give_insert_payment' ) ) {
			return 0;
		}

		$post_data = isset( $purchase_data['post_data'] ) && is_array( $purchase_data['post_data'] ) ? $purchase_data['post_data'] : array();
		$user_info = isset( $purchase_data['user_info'] ) && is_array( $purchase_data['user_info'] ) ? $purchase_data['user_info'] : array();

		$payment_data = array(
			'price'           => isset( $purchase_data['price'] ) ? $purchase_data['price'] : 0,
			'give_form_title' => isset( $post_data['give-form-title'] ) ? $post_data['give-form-title'] : '',
			'give_form_id'    => isset( $post_data['give-form-id'] ) ? absint( $post_data['give-form-id'] ) : 0,
			'give_price_id'   => isset( $post_data['give-price-id'] ) ? sanitize_text_field( wp_unslash( $post_data['give-price-id'] ) ) : '',
			'date'            => isset( $purchase_data['date'] ) ? $purchase_data['date'] : current_time( 'mysql' ),
			'user_email'      => isset( $purchase_data['user_email'] ) ? sanitize_email( $purchase_data['user_email'] ) : '',
			'purchase_key'    => isset( $purchase_data['purchase_key'] ) ? sanitize_text_field( $purchase_data['purchase_key'] ) : '',
			'currency'        => $this->get_currency(),
			'user_info'       => $user_info,
			'status'          => 'pending',
			'gateway'         => self::GATEWAY_ID,
		);

		return absint( give_insert_payment( $payment_data ) );
	}

	/**
	 * Build Sauki Pay initialization payload.
	 *
	 * @param array  $purchase_data GiveWP purchase data.
	 * @param int    $payment_id GiveWP payment ID.
	 * @param string $reference Sauki Pay reference.
	 * @return array
	 */
	private function build_payload( array $purchase_data, $payment_id, $reference ) {
		$user_info = isset( $purchase_data['user_info'] ) && is_array( $purchase_data['user_info'] ) ? $purchase_data['user_info'] : array();
		$post_data = isset( $purchase_data['post_data'] ) && is_array( $purchase_data['post_data'] ) ? $purchase_data['post_data'] : array();
		$first     = isset( $user_info['first_name'] ) ? sanitize_text_field( $user_info['first_name'] ) : '';
		$last      = isset( $user_info['last_name'] ) ? sanitize_text_field( $user_info['last_name'] ) : '';
		$name      = trim( $first . ' ' . $last );
		$email     = isset( $purchase_data['user_email'] ) ? sanitize_email( $purchase_data['user_email'] ) : '';
		$phone     = $this->extract_phone_number( $purchase_data );

		if ( '' === $name && isset( $user_info['name'] ) ) {
			$name = sanitize_text_field( $user_info['name'] );
		}

		if ( '' === $name ) {
			$name = $email;
		}

		return array(
			'reference'    => $reference,
			'amount'       => isset( $purchase_data['price'] ) ? (float) $purchase_data['price'] : 0,
			'currency'     => $this->get_currency(),
			'callback_url' => SaukiPay_Settings::callback_url(),
			'customer'     => array(
				'payerName'   => $name,
				'email'       => $email,
				'phoneNumber' => $phone,
			),
			'metadata'     => array(
				'source'        => 'givewp',
				'payment_id'    => absint( $payment_id ),
				'form_id'       => isset( $post_data['give-form-id'] ) ? absint( $post_data['give-form-id'] ) : 0,
				'purchase_key'  => isset( $purchase_data['purchase_key'] ) ? sanitize_text_field( $purchase_data['purchase_key'] ) : '',
				'site_url'      => home_url( '/' ),
			),
		);
	}

	/**
	 * Extract donor phone number when present.
	 *
	 * @param array $purchase_data GiveWP purchase data.
	 * @return string
	 */
	private function extract_phone_number( array $purchase_data ) {
		$post_data = isset( $purchase_data['post_data'] ) && is_array( $purchase_data['post_data'] ) ? $purchase_data['post_data'] : array();
		$user_info = isset( $purchase_data['user_info'] ) && is_array( $purchase_data['user_info'] ) ? $purchase_data['user_info'] : array();

		$candidates = array(
			isset( $post_data['phone'] ) ? $post_data['phone'] : '',
			isset( $post_data['give_phone'] ) ? $post_data['give_phone'] : '',
			isset( $post_data['give-phone'] ) ? $post_data['give-phone'] : '',
			isset( $user_info['phone'] ) ? $user_info['phone'] : '',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return sanitize_text_field( wp_unslash( $candidate ) );
			}
		}

		return '';
	}

	/**
	 * Current GiveWP currency.
	 *
	 * @return string
	 */
	private function get_currency() {
		if ( function_exists( 'give_get_currency' ) ) {
			return sanitize_text_field( give_get_currency() );
		}

		return 'NGN';
	}

	/**
	 * Create unique reference.
	 *
	 * @param int $payment_id Payment ID.
	 * @return string
	 */
	private function create_reference( $payment_id ) {
		return 'GIVE-' . absint( $payment_id ) . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
	}

	/**
	 * Update GiveWP payment meta.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	private function update_payment_meta( $payment_id, $key, $value ) {
		if ( function_exists( 'give_update_payment_meta' ) ) {
			give_update_payment_meta( $payment_id, $key, $value );
			return;
		}

		update_post_meta( $payment_id, $key, $value );
	}

	/**
	 * Update GiveWP payment status.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $status Status.
	 * @return void
	 */
	private function update_payment_status( $payment_id, $status ) {
		if ( function_exists( 'give_update_payment_status' ) ) {
			give_update_payment_status( $payment_id, $status );
		}
	}

	/**
	 * Add GiveWP payment note.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $note Note.
	 * @return void
	 */
	private function add_payment_note( $payment_id, $note ) {
		if ( function_exists( 'give_insert_payment_note' ) ) {
			give_insert_payment_note( $payment_id, sanitize_text_field( $note ) );
		}
	}

	/**
	 * Add GiveWP error and send donor back to checkout.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function give_error( $message ) {
		if ( function_exists( 'give_set_error' ) ) {
			give_set_error( 'saukipay_error', $message );
		}

		if ( function_exists( 'give_send_back_to_checkout' ) ) {
			give_send_back_to_checkout( '?payment-mode=' . self::GATEWAY_ID );
		}

		wp_die( esc_html( $message ), esc_html__( 'Sauki Pay payment error', 'saukipay' ), array( 'response' => 500 ) );
	}
}
