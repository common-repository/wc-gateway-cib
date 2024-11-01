=== Payment Gateway via CIB for WooCommerce ===
Contributors: szathmari
Tags: woocommerce, e-commerce, cib, gateway, payment
Requires at least: 4.0
Tested up to: 6.5
WC requires at least: 3.4
WC tested up to: 8.7
Requires PHP: 7.4
PHP tested up to: 8.3
Stable tag: 1.2

License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

With this plugin customers of CIB can accept instant payments through their online stores using the WooCommerce plugin.

== Description ==

Take payments in your WooCommerce store using the CIB Gateway

= Features =

- Adds a payment option to the WooCommerce checkout page
- Test mode
- Logging

== Installation ==

Once you have installed WooCommerce on your Wordpress setup, follow these simple steps:

1. Upload the plugin files to the `/wp-content/plugins/wc-gateway-cib` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Upload yor DES key (eg.: AAA0001.des) to the `/wp-content/plugins/wc-gateway-cib` folder, insert the merchant ID in the Checkout settings for the CIB payment plugin and activate it.

== Screenshots ==

1. The settings panel for the CIB gateway: WooCommerce Settings > Checkout page
2. Checkout screen: CIB as a payment method
3. Payment screen

== Changelog ==

= 1.0 =
* Initial release

= 1.1 =
* Refund transaction (Pro version only)

= 1.2 =
* PHP 8.3 support
* HPOS compatible
* new assets