<?php
/**
 * Plugin Name: Sauki Pay
 * Plugin URI: https://saukipay.net
 * Description: Accept payments with Sauki Pay through WooCommerce and a standalone shortcode payment form.
 * Version: 1.0.2
 * Author: Sauki Pay
 * Author URI: https://saukipay.net
 * Text Domain: saukipay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAUKIPAY_VERSION', '1.0.2' );
define( 'SAUKIPAY_FILE', __FILE__ );
define( 'SAUKIPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAUKIPAY_URL', plugin_dir_url( __FILE__ ) );

require_once SAUKIPAY_PATH . 'includes/class-saukipay-plugin.php';

/**
 * Boot Sauki Pay after plugins are loaded so WooCommerce availability is known.
 */
function saukipay_bootstrap() {
	SaukiPay_Plugin::instance();
}
add_action( 'plugins_loaded', 'saukipay_bootstrap' );

register_activation_hook( __FILE__, array( 'SaukiPay_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SaukiPay_Plugin', 'deactivate' ) );
