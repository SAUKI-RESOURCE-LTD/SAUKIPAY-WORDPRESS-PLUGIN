# Sauki Pay WordPress Plugin v1.1.8

This release improves the standalone Sauki Pay payment form return flow.

## Download

Download the plugin ZIP:

https://github.com/SAUKI-RESOURCE-LTD/SAUKIPAY-WORDPRESS-PLUGIN/releases/download/v1.1.8/saukipay-wordpress-plugin-v1.1.8.zip

## What's New

- Added configurable success page for standalone payment form payments.
- Added configurable failure page for standalone payment form payments.
- Standalone payment form callbacks now fall back to the original form page when no dedicated result page is selected.
- Renamed the displayed callback URL to “Plugin callback URL” because it is shared by shortcode and GiveWP flows.

## Important Notes

You do not need to change the Sauki Pay dashboard callback URL or webhook URL unless your WordPress domain changes.

The callback URL and webhook URL shown in Sauki Pay settings should remain the URLs configured in your Sauki Pay dashboard. The new success/failure pages are WordPress landing pages used after the plugin has verified the payment.

## Supported Payment Options

- WooCommerce checkout gateway.
- GiveWP donation gateway.
- Standalone Sauki Pay payment form using `[saukipay_payment_form]`.

## Installation

1. Download `saukipay-wordpress-plugin-v1.1.8.zip`.
2. Log in to WordPress admin.
3. Go to Plugins > Add New > Upload Plugin.
4. Upload the ZIP file.
5. Activate Sauki Pay.
6. Open Sauki Pay settings and confirm your public and secret keys are saved.
7. Optional: choose success and failure pages for standalone payment forms.

## Documentation

User guide:

https://sauki-resource-ltd.github.io/SAUKIPAY-WORDPRESS-PLUGIN/
