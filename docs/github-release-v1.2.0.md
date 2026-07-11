# Sauki Pay WordPress Plugin v1.2.0

This release adds a merchant-facing Transactions dashboard inside WordPress admin.

## Download

Download the plugin ZIP:

https://github.com/SAUKI-RESOURCE-LTD/SAUKIPAY-WORDPRESS-PLUGIN/releases/download/v1.2.0/saukipay-wordpress-plugin-v1.2.0.zip

## What's New

- Added Sauki Pay > Transactions admin page.
- Added summary cards for total, successful, pending, failed, and successful amount.
- Added filters for status, environment, source, currency, date range, and search.
- Added paginated transaction table.
- Added transaction verify action.
- Added API client support for WordPress-specific backend routes:
  - `GET /api/v1/wp/transactions`
  - `GET /api/v1/wp/transactions/summary`

## Backend Requirement

The Transactions dashboard requires the backend WordPress transaction endpoints to be implemented before live data can appear.

## Supported Payment Options

- WooCommerce checkout gateway.
- GiveWP donation gateway.
- Standalone Sauki Pay payment form using `[saukipay_payment_form]`.

## Installation

1. Download `saukipay-wordpress-plugin-v1.2.0.zip`.
2. Log in to WordPress admin.
3. Go to Plugins > Add New > Upload Plugin.
4. Upload the ZIP file.
5. Activate Sauki Pay.
6. Open Sauki Pay settings and confirm your public and secret keys are saved.
7. Open Sauki Pay > Transactions to view processed invoices after the backend endpoints are live.

## Documentation

User guide:

https://sauki-resource-ltd.github.io/SAUKIPAY-WORDPRESS-PLUGIN/
