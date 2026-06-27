<?php
/**
 * WooCommerce payment gateway.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sauki Pay WooCommerce gateway.
 */
class SaukiPay_WooCommerce_Gateway extends WC_Payment_Gateway {
	/**
	 * Global settings.
	 *
	 * @var SaukiPay_Settings
	 */
	private $saukipay_settings;

	/**
	 * API client.
	 *
	 * @var SaukiPay_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'saukipay';
		$this->method_title       = __( 'Sauki Pay', 'saukipay' );
		$this->method_description = __( 'Accept card, bank, and transfer payments through Sauki Pay.', 'saukipay' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->saukipay_settings = new SaukiPay_Settings();
		$this->api               = new SaukiPay_API( $this->saukipay_settings );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Sauki Pay', 'saukipay' ) );
		$this->description = $this->get_option( 'description', __( 'Pay securely with Sauki Pay.', 'saukipay' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Gateway-specific WooCommerce settings.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'saukipay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sauki Pay for WooCommerce', 'saukipay' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => __( 'Title', 'saukipay' ),
				'type'        => 'text',
				'description' => __( 'Payment method title customers see during checkout.', 'saukipay' ),
				'default'     => __( 'Sauki Pay', 'saukipay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'saukipay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description customers see during checkout.', 'saukipay' ),
				'default'     => __( 'Pay securely with Sauki Pay.', 'saukipay' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Whether gateway is available at checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled || ! $this->saukipay_settings->is_enabled() || '' === $this->saukipay_settings->secret_key() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Process WooCommerce payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Unable to find order for Sauki Pay payment.', 'saukipay' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$reference = $this->create_reference( $order );
		$payload   = array(
			'reference'    => $reference,
			'amount'       => (float) $order->get_total(),
			'currency'     => $order->get_currency(),
			'callback_url' => SaukiPay_Settings::woocommerce_callback_url(),
			'customer'     => array(
				'payerName'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'       => $order->get_billing_email(),
				'phoneNumber' => $order->get_billing_phone(),
			),
			'metadata'     => array(
				'source'   => 'woocommerce',
				'order_id' => $order->get_id(),
				'site_url' => home_url( '/' ),
			),
		);

		$order->update_meta_data( '_saukipay_reference', $reference );
		$order->save();
		$order->add_order_note( sprintf( 'Sauki Pay initialization started. Reference: %s', $reference ) );

		$response = $this->api->initialize_payment( $payload );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note( 'Sauki Pay initialization failed: ' . $response->get_error_message() );
			wc_add_notice( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( empty( $response['data']['checkout'] ) ) {
			$order->add_order_note( 'Sauki Pay initialization failed: checkout URL missing in API response.' );
			wc_add_notice( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_saukipay_access_code', isset( $response['data']['accessCode'] ) ? sanitize_text_field( $response['data']['accessCode'] ) : '' );
		$order->update_meta_data( '_saukipay_checkout_url', esc_url_raw( $response['data']['checkout'] ) );
		$order->save();
		$order->add_order_note( sprintf( 'Sauki Pay initialized successfully. Reference: %s', $reference ) );

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $response['data']['checkout'] ),
		);
	}

	/**
	 * Create a unique reference from order ID.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function create_reference( WC_Order $order ) {
		return 'WC-' . $order->get_id() . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
	}
}
