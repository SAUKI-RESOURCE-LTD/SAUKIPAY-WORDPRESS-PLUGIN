=== Sauki Pay ===
Contributors: saukipay
Tags: payments, woocommerce, gateway, shortcode
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments with Sauki Pay through WooCommerce and a standalone shortcode form.

== Description ==

Sauki Pay adds two payment features to WordPress:

* A WooCommerce payment gateway called Sauki Pay.
* A standalone payment form shortcode: [saukipay_payment_form].

The plugin integrates with the Sauki Pay API at https://www.server.saukipay.net/api/v1 by default.

== Installation ==

1. Upload the `saukipay` folder to `/wp-content/plugins/`.
2. Activate "Sauki Pay" from the WordPress Plugins screen.
3. Go to Sauki Pay in the WordPress admin menu.
4. Enter your test or live public and secret keys.
5. Configure your callback and webhook URLs in the Sauki Pay dashboard.
6. If using WooCommerce, enable the Sauki Pay gateway in WooCommerce payment settings.

== Shortcode ==

Open Sauki Pay > Payment Form in WordPress admin to configure a payment form and copy the generated shortcode.

You can also use the shortcode manually:

`[saukipay_payment_form]`

Supported attributes:

* `amount` - Optional amount.
* `currency` - Currency code, defaults to NGN.
* `title` - Form title.
* `button_text` - Submit button text.
* `fixed_amount` - Use `yes` to prevent amount editing.

Example:

`[saukipay_payment_form amount="5000" currency="NGN" title="Pay Registration Fee" fixed_amount="yes"]`

== Webhook ==

Set the webhook URL shown on the Sauki Pay settings page. Successful webhook handling returns exactly:

`ok`

== External services ==

This plugin connects to the Sauki Pay payment service to initialize and verify payments.

When a customer pays with Sauki Pay, the plugin sends payment details to the Sauki Pay API, including the payment reference, amount, currency, callback URL, customer name, customer email address, customer phone number, order metadata, and site URL.

The default Sauki Pay API endpoint is:

`https://www.server.saukipay.net/api/v1`

This service is required for the payment features to work. Merchants must have a Sauki Pay account and configure their Sauki Pay public and secret keys in the plugin settings.

Sauki Pay website:

`https://saukipay.net`

== Privacy ==

This plugin does not collect payment data for advertising or tracking. Customer payment details are sent to Sauki Pay only for payment processing, payment verification, and webhook confirmation.

== Assets and licensing ==

The plugin code is licensed under GPLv2 or later. Included Sauki Pay brand assets are distributed with this plugin under GPLv2 or later, subject to Sauki Pay trademark rights.

== Changelog ==

= 1.0.0 =
Initial release.
