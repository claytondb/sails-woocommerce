# Changelog

All notable changes to Sails Tax for WooCommerce.

## [0.3.0] - 2026-02-20

### Added
- **Order Admin Meta Box** - View tax calculation details on each order
- **Debug Logging** - Optional integration with WooCommerce logs for troubleshooting
- **Cache Clear Button** - Manual cache purge from settings page
- **User-Agent Header** - Better API tracking and debugging

### Improved
- Error handling and fallback behavior
- API response timing logged when debug enabled

## [0.2.1] - 2026-02-14

### Added
- **Test Connection Button** - Verify API key works from settings page

## [0.2.0] - 2026-02-12

### Added
- **Rate Caching** - 5-minute cache to reduce API calls and speed up checkout
- **Order Metadata** - Store tax confidence, rate, and amount on orders
- **Order Notes** - Admin-visible notes for non-exact calculations
- **Block Checkout Support** - Full compatibility with WooCommerce Blocks

### Fixed
- Fall back to billing address when shipping not available (fixes Block Checkout)

## [0.1.0] - 2026-02-06

### Added
- Initial release
- Basic checkout tax calculation via Sails.tax API
- Settings page for API credentials
- Customer-facing estimate disclaimer (optional)
- WooCommerce 7.0+ and WordPress 6.0+ support

---

**WordPress.org:** https://wordpress.org/plugins/sails-tax-woocommerce/ (pending review)  
**Documentation:** https://sails.tax/docs/woocommerce
