=== Sails Tax for WooCommerce ===
Contributors: sailstax
Tags: sales tax, woocommerce, tax, tax calculation, ecommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic sales tax calculation for WooCommerce stores. Real-time rates powered by Sails.

== Description ==

Sails Tax for WooCommerce automatically calculates sales tax at checkout using real-time data from the [Sails](https://sails.tax) API.

**Perfect for small online sellers** who want accurate tax calculations without the complexity of enterprise tax software.

= Features =

* **Real-time tax calculation** at checkout based on customer address
* **ZIP-level accuracy** with fallback to state rates when needed
* **Confidence indicators** so you know how precise each calculation is
* **Tax reports dashboard** with CSV export to track total tax collected, refunds, net liability, and trends
* **Monthly summary emails** - automated tax reports delivered to your inbox on the 1st of each month
* **Tax exemptions** for wholesale, government, non-profit, and other exempt customers
* **Product category exemptions** for groceries, clothing, medicine, and other non-taxable products
* **Caching** to minimize API calls and keep your checkout fast
* **Debug logging** for easy troubleshooting
* **Order meta box** showing tax details on each order
* **Cache management** from the admin settings
* **HPOS compatible** - works with High-Performance Order Storage

= How It Works =

1. Customer enters their shipping address at checkout
2. Sails calculates the exact tax rate for that location
3. Tax is applied to the order automatically
4. You see confidence details in the order admin

= Requirements =

* WooCommerce 7.0 or higher
* A free Sails account ([sign up here](https://sails.tax/signup))
* Your Sails API key

= Free Plan Included =

The free Sails plan includes 100 tax calculations per month. Perfect for small stores just getting started.

== Installation ==

1. Upload the `sails-tax-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to WooCommerce > Settings > Sails Tax
4. Enter your Sails API key
5. Configure your store address and preferences
6. Save changes

That's it! Tax will now be calculated automatically at checkout.

== Frequently Asked Questions ==

= Do I need a Sails account? =

Yes. You'll need a free Sails account to get your API key. Sign up at [sails.tax/signup](https://sails.tax/signup).

= Is it free? =

The plugin is free. The Sails free plan includes 100 tax calculations per month, which is enough for most small stores.

= Why does it say "estimate" sometimes? =

If Sails cannot determine an exact rate for a specific ZIP code, it uses a nearby ZIP or state-level rate as a safe estimate. The confidence level is shown in your order details.

= Does it work with WooCommerce Subscriptions? =

Yes. Tax is calculated for each renewal based on the customer's current address.

= How do I set up tax exemptions? =

1. Enable "Tax Exemptions" in WooCommerce > Sails Tax settings
2. Go to Users > edit any customer's profile
3. Scroll to "Tax Exemption (Sails Tax)" and check "Tax Exempt"
4. Enter their certificate number, reason, and optional expiry date
5. Choose which states the exemption applies to (or select "All States")

The customer will automatically get $0 tax at checkout.

= What happens if the API is down? =

The plugin can fall back to WooCommerce's built-in tax tables if configured. We recommend keeping basic rates set up as a safety net.

= Which payment gateways does it support? =

All of them. Tax is calculated during checkout before payment processing, so it works with Stripe, PayPal, Square, and any WooCommerce-compatible gateway.

== Screenshots ==

1. Settings page where you enter your API key and configure options
2. Tax calculation shown at checkout
3. Order details showing tax confidence and breakdown

== Changelog ==

= 1.0.0 =
* 🎉 **First stable release!** Production-ready for WooCommerce stores
* Complete tax calculation with ZIP-level accuracy
* Full tax exemption system (customers + product categories)
* Advanced reporting dashboard with CSV export
* Monthly summary emails
* Refund tracking and net tax calculations
* HPOS compatible with all WooCommerce versions

= 0.9.0 =
* NEW: Monthly Summary Emails - automated tax reports delivered to your inbox
* Beautiful HTML email template showing gross tax, refunds, and net liability
* Top states breakdown with order counts and tax amounts
* Calculation accuracy visualization (confidence levels)
* Configurable email recipients (comma-separated list)
* "Send Test Email" button to preview reports
* Scheduled for the 1st of each month at 8am

= 0.8.0 =
* NEW: CSV Export - export tax data from the reports dashboard
* Summary Report: totals, state breakdown, confidence analysis
* Detailed Orders: all orders with full tax data (14 columns)
* NEW: Tax by State breakdown in reports dashboard
* Respects current date range filter
* Excel-compatible UTF-8 encoding
* Security: nonce verification and capability checks

= 0.7.0 =
* NEW: Refund Tracking - automatic tax refund calculations when processing refunds
* Tax Reports Dashboard now shows Gross Tax, Tax Refunded, and Net Tax
* Recent Refunds widget shows orders with tax adjustments
* Order notes for refund tax amounts
* Handles refund deletion and recalculates totals correctly
* Full HPOS support for refund queries

= 0.6.0 =
* NEW: Product Category Exemptions - mark entire categories as tax-exempt
* Preset exemption types: Groceries, Clothing, Medicine, Digital Products
* State-specific exemptions (e.g., groceries only exempt in certain states)
* Mixed cart support - correctly calculates partial exemptions
* Multi-select category and state pickers with Select2
* Order notes and metadata for exempt products
* New admin page: WooCommerce > Product Exemptions

= 0.5.0 =
* NEW: Tax Exemption Support - mark customers as tax exempt with full certificate management
* Exemption reasons: Resale/Wholesale, Government, Non-Profit, Tribal, Agriculture, Manufacturing, Diplomatic
* State-specific exemptions (exempt in certain states only)
* Certificate expiry date tracking
* User profile fields for managing exemptions
* "Tax Exempt" column in WordPress Users list
* Exempt customers skip API calls automatically
* Exemption details shown on order admin page

= 0.4.0 =
* Added Tax Reports dashboard (WooCommerce > Tax Reports)
* View total tax collected with date range filtering
* Confidence level breakdown with order counts and totals
* Recent orders with Sails tax data at a glance
* Full HPOS (High-Performance Order Storage) support

= 0.3.1 =
* Added full internationalization (i18n) support for translations
* Created languages directory for translation files
* Improved WordPress coding standards compliance

= 0.3.0 =
* Added order admin meta box showing tax details
* Added debug logging option
* Added cache clear button in settings
* Added User-Agent header for API tracking
* Improved error handling

= 0.2.0 =
* Added address caching to reduce API calls
* Added confidence level display
* Improved checkout integration

= 0.1.0 =
* Initial release
* Basic checkout tax calculation
* Settings page for API key

== Upgrade Notice ==

= 1.0.0 =
🎉 FIRST STABLE RELEASE! Sails Tax for WooCommerce is now production-ready. Complete tax calculation, exemptions, reports, and monthly email summaries. Recommended for all stores.

= 0.9.0 =
NEW: Monthly Summary Emails! Receive automated tax reports on the 1st of each month. Beautiful HTML emails show your gross tax, refunds, net liability, top states, and calculation accuracy. Configure recipients in settings.

= 0.8.0 =
NEW: CSV Export! Export your tax data as Summary Report (totals + state breakdown) or Detailed Orders (all order data). Plus new Tax by State breakdown in the dashboard.

= 0.7.0 =
NEW: Refund Tracking! Tax reports now show Gross Tax, Tax Refunded, and Net Tax. Automatic tax refund calculations when processing WooCommerce refunds.

= 0.6.0 =
NEW: Product Category Exemptions! Mark groceries, clothing, medicine, and other categories as tax-exempt. Supports state-specific rules (e.g., groceries exempt in some states only).

= 0.5.0 =
NEW: Tax Exemption Support! Mark wholesale, government, and non-profit customers as tax exempt. Manage certificates with expiry dates and state-specific rules.

= 0.4.0 =
New Tax Reports dashboard to track your tax collections and confidence levels. View totals, breakdowns, and recent orders. HPOS compatible.

= 0.3.0 =
New features: order meta box, debug logging, and cache management. Recommended update for all users.
