=== Sails Tax for WooCommerce ===
Contributors: sails
Tags: sales tax, woocommerce, tax, compliance
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sales tax estimation and tracking powered by Sails.tax.

== Description ==

Sails Tax for WooCommerce calculates estimated sales tax at checkout using the Sails API.

Features:
- Checkout sales tax estimation (always returns an estimate)
- Merchant-visible confidence notes (exact ZIP vs nearby ZIP vs state-only)
- Optional customer-visible estimate note (off by default)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce → Settings → Sails Tax.
4. Paste your Sails API key.

== Frequently Asked Questions ==

= Why might the tax be an estimate? =
If Sails cannot determine an exact ZIP-level rate, it may use a nearby ZIP rate or a state-only rate as an estimate.

= Can customers see the estimate note? =
Yes — there is a setting to show a short note at checkout. It is off by default.
