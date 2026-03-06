# Sails Tax for WooCommerce - Task List

Last updated: 2026-03-06 (4:00 AM)

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

### 2. Product Category Exemptions
**Priority:** MEDIUM

- [ ] Per-category tax settings
- [ ] Non-taxable product types (groceries, clothing in some states)
- [ ] Mixed cart handling

### 3. Refund Integration
**Priority:** MEDIUM

- [ ] Track refunded orders
- [ ] Adjust tax reports for refunds
- [ ] Sync refund data with Sails API (if supported)

---

## 🎯 Future Enhancements

### Advanced Reporting
- [ ] CSV export of tax data
- [ ] Date range filtering
- [ ] Tax by state breakdown
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

- **Version:** 0.3.1
- **WordPress.org:** Pending review
- **i18n:** Ready for translations
