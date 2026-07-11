# Sauki Pay WordPress Plugin v1.1.7

This release fixes GiveWP completion after a successful Sauki Pay checkout payment.

## Download

Download the plugin ZIP:

https://github.com/SAUKI-RESOURCE-LTD/SAUKIPAY-WORDPRESS-PLUGIN/releases/download/v1.1.7/saukipay-wordpress-plugin-v1.1.7.zip

## What's Fixed

- Fixed GiveWP callback handling for modern GiveWP donations.
- Successful Sauki Pay payments now update GiveWP donations from pending to completed.
- GiveWP donors are redirected to the configured GiveWP success page after callback verification.
- Webhook handling now also supports modern GiveWP donation records.

## Supported Payment Options

- WooCommerce checkout gateway.
- GiveWP donation gateway.
- Standalone Sauki Pay payment form using `[saukipay_payment_form]`.

## Installation

1. Download `saukipay-wordpress-plugin-v1.1.7.zip`.
2. Log in to WordPress admin.
3. Go to Plugins > Add New > Upload Plugin.
4. Upload the ZIP file.
5. Activate Sauki Pay.
6. Open Sauki Pay settings and confirm your public and secret keys are saved.

## Testing GiveWP

1. Enable Sauki Pay in GiveWP payment gateway settings.
2. Make a test donation with Sauki Pay.
3. Complete payment on Sauki Pay checkout.
4. Confirm the donor returns to the GiveWP success page.
5. Confirm the donation status changes from pending to completed.

## Documentation

User guide:

https://sauki-resource-ltd.github.io/SAUKIPAY-WORDPRESS-PLUGIN/
