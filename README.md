# Sails Tax for WooCommerce

🎉 **v1.0.0 Released!** — WordPress/WooCommerce plugin for sales tax calculation powered by Sails.tax API.

## Features

### Core Tax Calculation
- **Real-time Checkout Tax** - ZIP-level accuracy with state fallback
- **Confidence Tracking** - Know how precise each calculation is
- **Rate Caching** - 5-minute cache minimizes API calls

### Tax Exemptions
- **Customer Exemptions** - Wholesale, government, non-profit with certificate management
- **Product Exemptions** - Groceries, clothing, medicine, digital products
- **State-specific Rules** - Exemptions that apply only in certain states

### Reports & Analytics
- **Tax Reports Dashboard** - Total tax, refunds, net liability, state breakdown
- **CSV Export** - Summary reports and detailed order data
- **Monthly Emails** - Automated tax summaries on the 1st of each month
- **Refund Tracking** - Accurate net tax calculations

### Developer Friendly
- **HPOS Compatible** - Works with High-Performance Order Storage
- **Block Checkout Support** - Full WooCommerce Blocks compatibility
- **Debug Logging** - Optional WooCommerce log integration
- **i18n Ready** - Full internationalization support

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Sails.tax API account

## Installation

1. Upload `sails-tax-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Go to WooCommerce > Settings > Sails Tax
4. Enter your Sails.tax API credentials

## Plugin Structure

```
sails-tax-woocommerce.php    # Main plugin file
includes/
  class-sails-tax-settings.php         # Admin settings + cache management
  class-sails-tax-api.php              # Sails.tax API client with logging
  class-sails-tax-checkout.php         # Checkout integration
  class-sails-tax-order-display.php    # Order admin meta box
  class-sails-tax-reports.php          # Tax reports dashboard
  class-sails-tax-exemptions.php       # Customer tax exemptions
  class-sails-tax-product-exemptions.php # Product category exemptions
  class-sails-tax-refunds.php          # Refund tracking
  class-sails-tax-email-reports.php    # Monthly summary emails
```

## Version

Current: **1.0.0** 🎉

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full history.

### 1.0.0 (2026-03-08)
- 🎉 First stable release! Production-ready for WooCommerce stores
- Complete tax calculation with ZIP-level accuracy
- Full tax exemption system (customers + product categories)
- Advanced reporting dashboard with CSV export
- Monthly summary emails
- Refund tracking and net tax calculations

## Related

- [Sails.tax](https://sails.tax/) - Sales tax calculation service
- `dc-salestaxjar` - Full-featured tax compliance app (Next.js)
