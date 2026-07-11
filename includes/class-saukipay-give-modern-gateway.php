<?php
/**
 * GiveWP modern payment gateway adapter.
 *
 * @package SaukiPay
 */

namespace SaukiPay\GiveWP;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Sauki Pay with GiveWP's modern gateway registry.
 */
class Modern_Gateway extends PaymentGateway {
	/**
	 * Gateway ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'saukipay';
	}

	/**
	 * Gateway ID.
	 *
	 * @return string
	 */
	public function getId(): string {
		return self::id();
	}

	/**
	 * Gateway name in admin.
	 *
	 * @return string
	 */
	public function getName(): string {
		return __( 'Sauki Pay', 'saukipay' );
	}

	/**
	 * Donor-facing payment method label.
	 *
	 * @return string
	 */
	public function getPaymentMethodLabel(): string {
		return __( 'Sauki Pay', 'saukipay' );
	}

	/**
	 * GiveWP calls this for modern forms. Sauki Pay does not need extra fields.
	 *
	 * @param int $formId Donation form ID.
	 * @return void
	 */
	public function enqueueScript( int $formId ) {
		wp_enqueue_style(
			'saukipay-form',
			SAUKIPAY_URL . 'assets/css/saukipay-form.css',
			array(),
			SAUKIPAY_VERSION
		);

		wp_enqueue_script(
			'saukipay-givewp-gateway',
			SAUKIPAY_URL . 'assets/js/saukipay-givewp-gateway.js',
			array( 'wp-element' ),
			SAUKIPAY_VERSION,
			true
		);

		wp_localize_script(
			'saukipay-givewp-gateway',
			'saukiPayGiveWP',
			array(
				'iconUrl' => SAUKIPAY_URL . 'assets/images/saukipay-icon.png',
				'logoUrl' => SAUKIPAY_URL . 'assets/images/saukipay-logo.png',
				'secureText' => __( 'Secure checkout', 'saukipay' ),
				'brandText' => __( 'Sauki Pay', 'saukipay' ),
			)
		);
	}

	/**
	 * Gateway form settings.
	 *
	 * @param int $formId Donation form ID.
	 * @return array
	 */
	public function formSettings( int $formId ): array {
		return array(
			'message' => __( 'Donors are redirected to Sauki Pay secure checkout to complete payment.', 'saukipay' ),
		);
	}

	/**
	 * Create a GiveWP payment and redirect the donor to Sauki Pay checkout.
	 *
	 * @param Donation $donation GiveWP donation.
	 * @param mixed    $gatewayData Gateway data.
	 * @return RedirectOffsite
	 * @throws PaymentGatewayException When payment cannot be initialized.
	 */
	public function createPayment( Donation $donation, $gatewayData ) {
		$plugin   = \SaukiPay_Plugin::instance();
		$settings = $plugin->settings;
		$api      = $plugin->api;

		if ( ! $settings->is_enabled() || '' === $settings->secret_key() ) {
			throw new PaymentGatewayException( __( 'Sauki Pay is not configured. Please contact the site administrator.', 'saukipay' ) );
		}

		$reference = $this->create_reference( $donation->id );
		$payload   = $this->build_payload( $donation, $reference );

		$this->update_payment_meta( $donation->id, '_saukipay_reference', $reference );
		$this->add_payment_note( $donation->id, sprintf( 'Sauki Pay initialization started. Reference: %s', $reference ) );

		$response = $api->initialize_payment( $payload );

		if ( is_wp_error( $response ) ) {
			$this->add_payment_note( $donation->id, 'Sauki Pay initialization failed: ' . $response->get_error_message() );
			throw new PaymentGatewayException( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ) );
		}

		$checkout_url = $api->get_checkout_url( $response );

		if ( '' === $checkout_url ) {
			$api->log_debug( 'GiveWP modern payment initialization response missing checkout URL.', array( 'donation_id' => $donation->id, 'response' => $response ) );
			$this->add_payment_note( $donation->id, 'Sauki Pay initialization failed: checkout URL missing in API response.' );
			throw new PaymentGatewayException( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ) );
		}

		$this->update_payment_meta( $donation->id, '_saukipay_access_code', $api->get_access_code( $response ) );
		$this->update_payment_meta( $donation->id, '_saukipay_checkout_url', esc_url_raw( $checkout_url ) );
		$this->add_payment_note( $donation->id, sprintf( 'Sauki Pay initialized successfully. Reference: %s', $reference ) );

		return new RedirectOffsite( esc_url_raw( $checkout_url ) );
	}

	/**
	 * Build Sauki Pay API payload.
	 *
	 * @param Donation $donation GiveWP donation.
	 * @param string   $reference Sauki Pay reference.
	 * @return array
	 */
	private function build_payload( Donation $donation, $reference ) {
		$name  = trim( $donation->firstName . ' ' . $donation->lastName );
		$email = sanitize_email( $donation->email );

		if ( '' === $name ) {
			$name = $email;
		}

		return array(
			'reference'    => $reference,
			'amount'       => $this->donation_amount( $donation ),
			'currency'     => $this->donation_currency( $donation ),
			'callback_url' => \SaukiPay_Settings::callback_url(),
			'customer'     => array(
				'payerName'   => sanitize_text_field( $name ),
				'email'       => $email,
				'phoneNumber' => sanitize_text_field( $donation->phone ),
			),
			'metadata'     => array(
				'source'       => 'givewp',
				'donation_id'  => absint( $donation->id ),
				'payment_id'   => absint( $donation->id ),
				'form_id'      => absint( $donation->formId ),
				'purchase_key' => sanitize_text_field( $donation->purchaseKey ),
				'site_url'     => home_url( '/' ),
			),
		);
	}

	/**
	 * Donation amount as a decimal value.
	 *
	 * @param Donation $donation GiveWP donation.
	 * @return float
	 */
	private function donation_amount( Donation $donation ) {
		if ( is_object( $donation->amount ) && method_exists( $donation->amount, 'formatToDecimal' ) ) {
			return (float) $donation->amount->formatToDecimal();
		}

		return 0;
	}

	/**
	 * Donation currency.
	 *
	 * @param Donation $donation GiveWP donation.
	 * @return string
	 */
	private function donation_currency( Donation $donation ) {
		if ( is_object( $donation->amount ) && method_exists( $donation->amount, 'toArray' ) ) {
			$amount = $donation->amount->toArray();

			if ( isset( $amount['currency'] ) && '' !== $amount['currency'] ) {
				return sanitize_text_field( $amount['currency'] );
			}
		}

		if ( function_exists( 'give_get_currency' ) ) {
			return sanitize_text_field( give_get_currency() );
		}

		return 'NGN';
	}

	/**
	 * Create unique reference.
	 *
	 * @param int $donation_id Donation ID.
	 * @return string
	 */
	private function create_reference( $donation_id ) {
		return 'GIVE-' . absint( $donation_id ) . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
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
	 * Add GiveWP payment note.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $note Note.
	 * @return void
	 */
	private function add_payment_note( $payment_id, $note ) {
		if ( class_exists( '\Give\Donations\Models\DonationNote' ) ) {
			try {
				DonationNote::create(
					array(
						'donationId' => absint( $payment_id ),
						'content'    => sanitize_text_field( $note ),
					)
				);
			} catch ( \Exception $exception ) {
				return;
			} catch ( \Throwable $throwable ) {
				return;
			}
			return;
		}

		if ( function_exists( 'give_insert_payment_note' ) ) {
			give_insert_payment_note( $payment_id, sanitize_text_field( $note ) );
		}
	}
}
