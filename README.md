# Sails Tax for WooCommerce

WordPress/WooCommerce plugin for sales tax calculation powered by Sails.tax API.

## Features

- **Checkout Tax Calculation** - Real-time tax estimates at checkout
- **Confidence Tracking** - Records calculation confidence levels
- **WooCommerce Integration** - Seamless integration with WC tax system
- **Admin Settings** - Configure API credentials in WordPress admin
- **Order Admin Display** - View tax details on order pages (v0.3.0)
- **Debug Logging** - Optional WooCommerce log integration for troubleshooting
- **Rate Caching** - 5-minute cache with manual clear option
- **Block Checkout Support** - Full compatibility with WooCommerce Blocks

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
  class-sails-tax-settings.php     # Admin settings + cache management
  class-sails-tax-api.php          # Sails.tax API client with logging
  class-sails-tax-checkout.php     # Checkout integration
  class-sails-tax-order-display.php # Order admin meta box
```

## Version

Current: 0.3.1

## Changelog

### 0.3.0 (2026-02-20)
- Added: Order admin meta box showing tax calculation details
- Added: Debug logging option (integrates with WooCommerce logs)
- Added: Cache management with manual clear button
- Added: User-Agent header for better API tracking
- Improved: API response timing logged when debug enabled

### 0.2.1
- Added: Test Connection button in settings

### 0.2.0
- Added: 5-minute rate caching
- Added: Order metadata storage
- Added: Block Checkout support

## Related

- [Sails.tax](https://sails.tax/) - Sales tax calculation service
- `dc-salestaxjar` - Full-featured tax compliance app (Next.js)
