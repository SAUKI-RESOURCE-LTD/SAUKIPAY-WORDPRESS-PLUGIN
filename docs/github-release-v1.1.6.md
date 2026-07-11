# Sauki Pay WordPress Plugin v1.1.6

This release expands the Sauki Pay WordPress plugin beyond WooCommerce and the standalone shortcode form with full GiveWP donation support, modern form branding, and an improved donation amount selector.

## Download

Download the plugin ZIP:

https://github.com/SAUKI-RESOURCE-LTD/SAUKIPAY-WORDPRESS-PLUGIN/releases/download/v1.1.6/saukipay-wordpress-plugin.zip

## What's New

- Added GiveWP donation gateway support.
- Added GiveWP 4 modern gateway registration.
- Added Sauki Pay branding to the GiveWP donation payment option.
- Added standalone payment form preset amount buttons.
- Added optional custom amount entry for standalone payment forms.
- Improved shortcode form builder settings in WordPress admin.
- Improved shortcode API response handling for nested Sauki Pay initialization responses.
- Improved visible error handling for failed shortcode payment initialization.
- Updated the user guide with sanitized setup and checkout screenshots.

## Supported Payment Options

- WooCommerce checkout gateway.
- GiveWP donation gateway.
- Standalone Sauki Pay payment form using `[saukipay_payment_form]`.

## Installation

1. Download `saukipay-wordpress-plugin.zip`.
2. Log in to WordPress admin.
3. Go to Plugins > Add New > Upload Plugin.
4. Upload the ZIP file.
5. Activate Sauki Pay.
6. Open Sauki Pay settings and add your public and secret keys.

## Documentation

User guide:

https://sauki-resource-ltd.github.io/SAUKIPAY-WORDPRESS-PLUGIN/

## Notes

Use test mode while setting up. Switch to live mode only when your live Sauki Pay keys and webhook URL are configured.
