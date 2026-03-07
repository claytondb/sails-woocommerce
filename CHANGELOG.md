# Changelog

All notable changes to Sails Tax for WooCommerce.

## [0.8.0] - 2026-03-07

### Added
- **CSV Export** - Export tax data from the reports dashboard:
  - Export dropdown menu with two options
  - **Summary Report**: Totals, state breakdown, confidence analysis
  - **Detailed Orders**: All orders with full tax data (14 columns)
  - Respects current date range filter
  - Excel-compatible UTF-8 encoding (BOM)
  - Security: nonce verification and capability checks

- **Tax by State Breakdown** - New section in reports dashboard:
  - Shows orders and tax collected per state
  - Average tax rate per state
  - Top 10 displayed, full list in CSV export
  - Full HPOS support with legacy fallback

### Improved
- Reports dashboard layout reorganized for better information hierarchy
- Date filter and export controls grouped in toolbar

## [0.7.0] - 2026-03-07

### Added
- **Refund Tracking** - Full refund integration for accurate tax reporting:
  - Automatic tax refund calculation when refunds are processed
  - Tracks refund tax using original order's tax rate
  - Proportional fallback when rate not available
  - Order notes showing estimated tax refund amount
  - New order metadata: `_sails_tax_refunded`, `_sails_refund_tax`, `_sails_refund_rate`

### Improved
- **Tax Reports Dashboard** - Enhanced with refund data:
  - New "Gross Tax Collected" and "Tax Refunded" cards
  - New "Net Tax" card showing actual tax liability
  - Recent Refunds widget showing orders with tax adjustments
  - Color-coded cards (red for refunds, green for net)
- Handles refund deletion and recalculates totals correctly
- Full HPOS support for refund queries

## [0.6.0] - 2026-03-06

### Added
- **Product Category Exemptions** - Mark entire product categories as tax-exempt:
  - New admin page: WooCommerce → Product Exemptions
  - Preset exemption types: Groceries, Clothing, Medicine, Digital Products, Custom
  - State-specific exemptions (e.g., groceries only exempt in certain states)
  - Multi-select category picker with Select2
  - Multi-select state picker with all 50 US states + DC
  - Mixed cart support (partial exemptions calculated correctly)
  - Full cart exemption when all products are exempt (skips API call)
  - Order notes showing exempt amount for partial exemptions
  - Order metadata for audit trail

### Improved
- Checkout calculates tax only on taxable portion of cart
- Common exemption states pre-filled when selecting preset exemption types
- Clean admin UI with rule-based configuration

## [0.5.0] - 2026-03-06

### Added
- **Tax Exemption Support** - Mark customers as tax exempt with full certificate management:
  - Enable/disable exemptions from settings
  - User profile fields for exemption status, certificate number, reason, and expiry
  - State-specific exemptions (exempt in certain states only)
  - Exemption reasons: Resale/Wholesale, Government, Non-Profit, Tribal, Agriculture, Manufacturing, Diplomatic, Other
  - Certificate expiry date tracking
  - "Tax Exempt" column in WP Users list
  - Exemption info displayed on order admin page
  - Order notes and metadata for exempt orders
  - Automatic API skip for exempt customers (saves API calls)

### Improved
- Checkout now checks exemption status before calling Sails API
- Orders store exemption details for audit trail

## [0.4.0] - 2026-03-06

### Added
- **Tax Reports Page** - New admin dashboard showing:
  - Total tax collected in date range
  - Order count with tax calculations
  - Average tax rate across orders
  - Exact ZIP match percentage
  - Confidence level breakdown with totals
  - Recent orders with Sails tax data
  - Date range filtering
- **TODO.md** - Task list for tracking planned features

### Improved
- Full HPOS (High-Performance Order Storage) support in reports
- Legacy postmeta fallback for older WooCommerce versions

## [0.3.1] - 2026-03-06

### Added
- **Full Internationalization (i18n)** - All user-facing strings now use WordPress translation functions
- **Languages Directory** - Ready for translation files (.po/.mo)
- **Text Domain Loading** - Proper textdomain initialization on plugin load

### Improved
- WordPress Plugin Guidelines compliance for WordPress.org submission

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
