# Sauki Pay WordPress Plugin v1.2.1

This release fixes callback redirects after Sauki Pay checkout.

## Download

Download the plugin ZIP:

https://github.com/SAUKI-RESOURCE-LTD/SAUKIPAY-WORDPRESS-PLUGIN/releases/download/v1.2.1/saukipay-wordpress-plugin-v1.2.1.zip

## What's Fixed

- Fixed callbacks where Sauki Pay appends `?status=...` to a callback URL that already contains query parameters.
- Fixed standalone payment form callbacks that could land on the site archive page instead of the configured result page.
- Added configured success/failure redirect page fallback for GiveWP payment results.
- Updated Sauki Pay settings labels so the redirect pages are clearly shown as shared success/failure pages.

## Supported Payment Options

- WooCommerce checkout gateway.
- GiveWP donation gateway.
- Standalone Sauki Pay payment form using `[saukipay_payment_form]`.

## Installation

1. Download `saukipay-wordpress-plugin-v1.2.1.zip`.
2. Log in to WordPress admin.
3. Go to Plugins > Add New > Upload Plugin.
4. Upload the ZIP file.
5. Activate or replace Sauki Pay.
6. Open Sauki Pay settings and confirm your success/failure redirect pages are selected.

## Documentation

User guide:

https://sauki-resource-ltd.github.io/SAUKIPAY-WORDPRESS-PLUGIN/
