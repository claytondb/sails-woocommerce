# Sails Tax for WooCommerce - Task List

Last updated: 2026-03-07 (1:00 AM)

## ✅ Completed

- [x] Basic checkout tax calculation
- [x] Settings page with API key configuration  
- [x] Customer disclaimer option
- [x] Rate caching (5 min TTL)
- [x] Order metadata storage
- [x] Block Checkout support
- [x] Order admin meta box
- [x] Debug logging
- [x] Test connection button
- [x] Cache clear button
- [x] Full i18n support
- [x] **Tax Reports page** (2026-03-06)

---

## 🔥 Priority Features

### 1. Tax Exemption Support ✅ DONE (2026-03-06)
**Priority:** HIGH

- [x] Tax-exempt customer flag
- [x] Exemption certificate storage (number, reason, expiry, states)
- [x] Skip API call for exempt customers
- [x] Admin UI for managing exemptions (user profile fields)
- [x] User list column showing exempt status
- [x] Order-level exemption tracking

### 2. Product Category Exemptions ✅ DONE (2026-03-06)
**Priority:** MEDIUM

- [x] Per-category tax settings
- [x] Non-taxable product types (groceries, clothing, medicine, digital)
- [x] State-specific exemptions (only exempt in certain states)
- [x] Mixed cart handling (partial exemptions)
- [x] Admin UI with Select2 multi-select for categories and states
- [x] Order meta storage and notes for exempt products

### 3. Refund Integration ✅ DONE (2026-03-07)
**Priority:** MEDIUM

- [x] Track refunded orders
- [x] Adjust tax reports for refunds
- [x] Calculate refund tax using original order's tax rate
- [x] Handle refund deletion (recalculate totals)
- [x] Order notes for refund tax amounts
- [x] Reports show gross/refunded/net tax
- [x] Recent Refunds widget in reports

---

## 🎯 Future Enhancements

### Advanced Reporting ✅ MOSTLY DONE (2026-03-07)
- [x] CSV export of tax data (Summary + Detailed Orders)
- [x] Date range filtering (already had this)
- [x] Tax by state breakdown
- [ ] Monthly summary emails

### Multi-Nexus Support
- [ ] Multiple business locations
- [ ] Origin-based vs destination-based switching
- [ ] Nexus tracking dashboard

### Performance
- [ ] Batch API calls for cart with multiple destinations
- [ ] Background cache warming
- [ ] Rate limit handling with exponential backoff

### Compliance
- [ ] Automated tax filing integration
- [ ] Audit trail exports
- [ ] Marketplace facilitator support

---

## 🐛 Known Issues

- None currently tracked

---

## 📊 Stats

- **Version:** 0.8.0
- **WordPress.org:** Pending review
- **i18n:** Ready for translations
