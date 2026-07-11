<?php
/**
 * Main plugin loader.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SAUKIPAY_PATH . 'includes/class-saukipay-settings.php';
require_once SAUKIPAY_PATH . 'includes/class-saukipay-api.php';
require_once SAUKIPAY_PATH . 'includes/class-saukipay-webhook.php';
require_once SAUKIPAY_PATH . 'includes/class-saukipay-payment-form.php';
require_once SAUKIPAY_PATH . 'includes/class-saukipay-give-gateway.php';

/**
 * Coordinates plugin services.
 */
final class SaukiPay_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var SaukiPay_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var SaukiPay_Settings
	 */
	public $settings;

	/**
	 * API client.
	 *
	 * @var SaukiPay_API
	 */
	public $api;

	/**
	 * Webhook and callback service.
	 *
	 * @var SaukiPay_Webhook
	 */
	public $webhook;

	/**
	 * Payment form service.
	 *
	 * @var SaukiPay_Payment_Form
	 */
	public $payment_form;

	/**
	 * Get singleton instance.
	 *
	 * @return SaukiPay_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings     = new SaukiPay_Settings();
		$this->api          = new SaukiPay_API( $this->settings );
		$this->webhook      = new SaukiPay_Webhook( $this->settings, $this->api );
		$this->payment_form = new SaukiPay_Payment_Form( $this->settings, $this->api );
		$give_gateway       = new SaukiPay_Give_Gateway( $this->settings, $this->api );

		$this->settings->init();
		$this->webhook->init();
		$this->payment_form->init();
		$give_gateway->init();

		add_filter( 'plugin_action_links_' . plugin_basename( SAUKIPAY_FILE ), array( $this, 'plugin_action_links' ) );

		if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Payment_Gateway' ) ) {
			require_once SAUKIPAY_PATH . 'includes/class-saukipay-woocommerce-gateway.php';
			add_filter( 'woocommerce_payment_gateways', array( $this, 'register_woocommerce_gateway' ) );
		}

	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( SaukiPay_Settings::OPTION_NAME ) ) {
			add_option( SaukiPay_Settings::OPTION_NAME, SaukiPay_Settings::defaults() );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Add settings shortcut on plugins screen.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=saukipay-settings' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'saukipay' )
			)
		);

		return $links;
	}

	/**
	 * Register WooCommerce gateway.
	 *
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_woocommerce_gateway( $gateways ) {
		$gateways[] = 'SaukiPay_WooCommerce_Gateway';
		return $gateways;
	}
}
