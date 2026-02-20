# Sails Tax for WooCommerce

WordPress/WooCommerce plugin for sales tax calculation powered by Sails.tax API.

## Features

- **Checkout Tax Calculation** - Real-time tax estimates at checkout
- **Confidence Tracking** - Records calculation confidence levels
- **WooCommerce Integration** - Seamless integration with WC tax system
- **Admin Settings** - Configure API credentials in WordPress admin

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
  class-sails-tax-settings.php   # Admin settings
  class-sails-tax-api.php        # Sails.tax API client
  class-sails-tax-checkout.php   # Checkout integration
```

## Version

Current: 0.2.1

## Related

- [Sails.tax](https://sails.tax/) - Sales tax calculation service
- `dc-salestaxjar` - Full-featured tax compliance app (Next.js)
