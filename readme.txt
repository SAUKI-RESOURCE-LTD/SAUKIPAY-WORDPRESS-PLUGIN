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

== Changelog ==

= 1.0.0 =
Initial release.
