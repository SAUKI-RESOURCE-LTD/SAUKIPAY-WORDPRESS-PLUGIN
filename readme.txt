=== Sauki Pay ===
Contributors: saukipay
Tags: payments, woocommerce, gateway, shortcode
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments with Sauki Pay through WooCommerce, GiveWP, and a standalone shortcode form.

== Description ==

Sauki Pay adds three payment features to WordPress:

* A WooCommerce payment gateway called Sauki Pay.
* A GiveWP donation payment gateway called Sauki Pay.
* A standalone payment form shortcode: [saukipay_payment_form].

The plugin integrates with the Sauki Pay API at https://www.server.saukipay.net/api/v1 by default.

== Installation ==

1. Upload the `saukipay` folder to `/wp-content/plugins/`.
2. Activate "Sauki Pay" from the WordPress Plugins screen.
3. Go to Sauki Pay in the WordPress admin menu.
4. Enter your test or live public and secret keys.
5. Configure your callback and webhook URLs in the Sauki Pay dashboard.
6. If using WooCommerce, enable the Sauki Pay gateway in WooCommerce payment settings.
7. If using GiveWP, enable the Sauki Pay gateway in GiveWP gateway settings.

== Shortcode ==

Open Sauki Pay > Payment Form in WordPress admin to configure a payment form and copy the generated shortcode.

You can also use the shortcode manually:

`[saukipay_payment_form]`

Supported attributes:

* `amount` - Optional amount.
* `currency` - Currency code, defaults to NGN.
* `title` - Form title.
* `description` - Optional text shown below the form title.
* `button_text` - Submit button text.
* `footer_text` - Optional note shown below the payment button.
* `fixed_amount` - Use `yes` to prevent amount editing.
* `preset_amounts` - Comma-separated quick-select amounts.
* `allow_custom_amount` - Use `yes` to let customers type a custom amount.
* `width` - Form width. Supported values: compact, wide, full.

Example:

`[saukipay_payment_form amount="5000" currency="NGN" title="Pay Registration Fee" fixed_amount="yes"]`

Preset amount example:

`[saukipay_payment_form currency="NGN" title="Donate" preset_amounts="1000,10000,50000" allow_custom_amount="yes"]`

== Webhook ==

Set the webhook URL shown on the Sauki Pay settings page. Successful webhook handling returns exactly:

`ok`

== GiveWP ==

If GiveWP is installed and active, Sauki Pay is registered as a GiveWP payment gateway.

To use it:

1. Go to GiveWP payment gateway settings.
2. Enable Sauki Pay.
3. Configure your Sauki Pay keys in the Sauki Pay plugin settings.
4. Add the Sauki Pay webhook URL to your Sauki Pay dashboard.

Sauki Pay redirects donors to checkout, verifies payment after callback, and updates the GiveWP donation status.

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

= 1.2.0 =
Add a Sauki Pay Transactions admin page with summary cards, filters, pagination, and transaction verification actions.

= 1.1.8 =
Add configurable success and failure landing pages for standalone Sauki Pay payment forms.

= 1.1.7 =
Fix GiveWP callback completion for modern GiveWP donations so successful Sauki Pay payments update donations from pending to completed and redirect donors to the GiveWP result page.

= 1.1.6 =
Add Sauki Pay branding to the GiveWP modern donation payment option.

= 1.1.5 =
Add configurable preset donation amounts and optional custom amount input to the standalone payment form.

= 1.1.4 =
Add GiveWP modern form frontend gateway registration so Sauki Pay is available on donation payment screens.

= 1.1.3 =
Add GiveWP 4 modern gateway registration so Sauki Pay appears in newer GiveWP payment gateway settings.

= 1.1.2 =
Remove a GiveWP enabled-gateway compatibility hook that could recurse while GiveWP builds its gateway list.

= 1.1.1 =
Improve GiveWP gateway registration so Sauki Pay appears reliably in GiveWP payment gateway settings.

= 1.1.0 =
Add GiveWP donation gateway support with Sauki Pay checkout, callback verification, and webhook updates.

= 1.0.2 =
Improve the payment form builder with currency dropdown, description/footer controls, width options, and a wider default form layout.

= 1.0.1 =
Fix shortcode payment initialization for nested Sauki Pay API responses and improve visible error handling.

= 1.0.0 =
Initial release.
