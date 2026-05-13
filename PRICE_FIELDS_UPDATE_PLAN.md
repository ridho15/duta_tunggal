# 📋 PLAN: Standardisasi Price Fields di Seluruh Aplikasi

**Status**: AUDIT COMPLETE ✅  
**Created**: 13 Mei 2026  
**Priority**: HIGH - Data consistency & User experience  
**Estimated Timeline**: 3-5 phases (2-3 minggu)

---

## 🎯 Tujuan Utama

1. **Konsistensi Desimal**: Semua price fields harus menggunakan desimal yang konsisten
   - **Unit Price**: 2 desimal (untuk akurasi, terutama USD)
   - **Totals/Amounts**: 0 desimal (untuk IDR/keseluruhan)
   
2. **Awareness Mata Uang**: Sistem harus menyadari jenis currency dan format sesuai
   - IDR: 0 desimal, prefix 'Rp'
   - USD: 2 desimal, prefix '$'
   
3. **Eliminasi Kebingungan**: Tidak ada mismatch antara displayed value vs stored value (seperti SKU-040: 29.80 vs 30)

4. **Code Maintainability**: Kurangi duplicate code dengan standardisasi pattern

---

## 📊 AUDIT HASIL

### Status Sekarang

| Resource | Multi-Currency | Decimal Pattern | Currency-Aware | Status |
|----------|---|---|---|---|
| **OrderRequest** | ✓ Yes | Inconsistent (0 & 2) | ✓ Yes (resolveCurrencySymbol) | 🟡 PARTIAL FIX |
| **PurchaseOrder** | ✓ Yes | Not specified | ✓ Yes (Currency::find) | 🟠 NEEDS AUDIT |
| **SaleOrder** | ✗ No (IDR only) | Not specified | ✗ Static 'Rp' | 🟠 NEEDS AUDIT |
| **Product** | ✗ No (IDR only) | Not specified | ✗ Static 'Rp' | ✓ OK |
| **Deposit** | ✗ No (IDR only) | Not specified | ✗ Static 'Rp' | ✓ OK |
| **MaterialIssue** | ✗ No (IDR only) | Not specified | ✗ Static 'Rp' | ✓ OK |
| **StockOpname** | ✗ No (IDR only) | 0 decimals | ✗ Static 'Rp' | ✓ OK |
| **Other 6+ Resources** | Mixed | Inconsistent | Mixed | 🟠 NEEDS REVIEW |

### Temuan Utama

**✅ Sudah Benar**:
- OrderRequestResource (line 702, 1327, 1722) - Sudah difix: 2 desimal untuk unit_price
- Semua IDR-only resources: Konsisten menggunakan 0 desimal
- Framework standard: ->indonesianMoney() macro konsisten

**⚠️ Perlu Perbaikan**:
1. **OrderRequestResource** (lines 683, 823, 841, 871):
   - `original_price`: Seharusnya 2 desimal (editable master price)
   - `total`, `tax_nominal`, `subtotal`: OK dgn 0 desimal (calculated/readonly)

2. **PurchaseOrderResource** (lines 876, 898, 962+):
   - Semua unit_price fields: Belum jelas decimal policy
   - Total fields: Belum konsisten dengan OrderRequest

3. **SaleOrderResource** (lines 795, 818, 914, 932):
   - Belum ada explicit decimal formatting
   - Perlu audit formatStateUsing patterns

4. **Calculated Fields** (tax_nominal, subtotal, totals):
   - Beberapa menggunakan 0, beberapa implicit
   - Perlu standarisasi: 0 desimal HARUS untuk totals

5. **Inconsistent Parsers**:
   - MoneyHelper::parse() vs HelperController::parseIndonesianMoney()
   - Perlu standardisasi ke satu method

---

## 🔄 PHASE-BY-PHASE UPDATE PLAN

### **PHASE 1: OrderRequestResource - COMPLETE ✅**
**Status**: DONE (13 Mei 2026)

**Selesai**:
- ✅ Line 702: unit_price - 2 desimal (formatStateUsing + afterStateHydrated)
- ✅ Line 1722: unit_price - 2 desimal (mask + formatStateUsing + dehydrateStateUsing)
- ✅ Test: tests/playwright/order-request-edit-sku-040-price.spec.mjs - PASSING
- ✅ PHP Lint: No errors

**Masih Perlu** (OrderRequest):
- Line 683: original_price - Ubah dari 0 → 2 desimal (editable field)
- Verifikasi: Lines 823, 841, 871 (total, tax_nominal, subtotal) tetap 0 desimal ✓

---

### **PHASE 2: OrderRequestResource - Remaining Fields**
**Scope**: Fix original_price decimal places + verify calculated fields  
**Effort**: 30 menit  
**Risk**: LOW (read-only fields mostly)

**Changes Required**:

```
File: app/Filament/Resources/OrderRequestResource.php

Line 683-700: original_price field (CREATE form)
  CHANGE: number_format(..., 0, ',', '.') → number_format(..., 2, ',', '.')
  Both formatStateUsing AND afterStateHydrated
  Reason: original_price adalah editable master price, harus support 2 desimal

Line 823-837: total field (CREATE form)
  VERIFY: Tetap 0 desimal ✓ (calculated: qty × price, roundable)
  
Line 841-869: tax_nominal field (CREATE form)
  VERIFY: Tetap 0 desimal ✓ (computed via TaxService)
  
Line 871-905: subtotal field (CREATE form)
  VERIFY: Tetap 0 desimal ✓ (->indonesianMoney() macro handles this)
  
Line 1709: original_price (CREATE_APPROVAL form) 
  CHANGE: number_format(..., 0, ',', '.') → number_format(..., 2, ',', '.')
  
Line 1767: tax_nominal (CREATE_APPROVAL form)
  VERIFY: Tetap 0 desimal ✓
```

**Verification**:
- Create test: SKU-040 dengan original_price USD (harus show 2 desimal)
- Run existing test: Pastikan SKU-040 masih passing
- PHP Lint: No errors

---

### **PHASE 3: PurchaseOrderResource - Audit & Standardize**
**Scope**: Audit PurchaseOrder pricing, standardize dengan OrderRequest  
**Effort**: 1-2 jam  
**Risk**: MEDIUM (multi-currency support)

**Files Involved**:
- `app/Filament/Resources/PurchaseOrderResource.php`
- `app/Filament/Resources/PurchaseOrderResource/RelationManagers/*.php`

**Items to Standardize**:
1. Line 876: `unit_price` → Ensure 2 decimals explicit
2. Line 898: `total` → Ensure 0 decimals explicit
3. Line 962: `subtotal` → Ensure 0 decimals explicit
4. All calculated fields: tax_nominal, total_cost, etc. → 0 decimals

**Checklist**:
- [ ] Review PurchaseOrderItem formatStateUsing patterns
- [ ] Check currency-aware implementation (Currency::find() vs resolveCurrencySymbol)
- [ ] Verify dehydrateStateUsing consistency
- [ ] Add explicit decimals where ->indonesianMoney() is used
- [ ] Run PurchaseOrder tests
- [ ] PHP Lint check

---

### **PHASE 4: SaleOrderResource - Audit & Standardize**
**Scope**: Audit SaleOrder pricing, ensure decimal consistency  
**Effort**: 1-1.5 jam  
**Risk**: LOW (IDR only, simpler)

**Files Involved**:
- `app/Filament/Resources/SaleOrderResource.php`
- `app/Filament/Resources/SaleOrderResource/RelationManagers/ItemsRelationManager.php`

**Items to Check**:
1. Line 795: `unit_price` → Should be explicit 2 decimals (not rely on ->indonesianMoney())
2. Line 818: `total` → Verify 0 decimals
3. Line 914: `tax_nominal` → Verify 0 decimals
4. Line 932: `subtotal` → Verify 0 decimals
5. RelationManager fields → Same decimal rules

**Action**:
- Add explicit formatStateUsing/dehydrateStateUsing for critical fields
- Keep ->indonesianMoney() for non-critical calculated fields
- Verify afterStateUpdated calculations use correct decimals

---

### **PHASE 5: Other Resources - Documentation & Spot Checks**
**Scope**: Review MaterialIssue, StockOpname, Deposit, other resources  
**Effort**: 30 menit - 1 jam  
**Risk**: LOW (mostly IDR-only, already consistent)

**Resources to Check**:
- MaterialIssueResource (cost_per_unit, total_cost)
- StockOpnameResource (unit_cost, average_cost, total_value)
- DepositResource (amount fields)
- Other supporting resources

**Rationale**: These are mostly single-currency (IDR) with consistent 0 decimal pattern. Spot check untuk ensure no edge cases.

**Checklist**:
- [ ] Verify all use 0 decimals consistently
- [ ] Check for any USD/multi-currency usage (unexpected)
- [ ] No manual number_format conflicts

---

## 🛠️ STANDARDIZATION RULES (Updated)

### **Rule 1: Decimal Places**
```
✓ User-Input Price Fields (unit_price, cost_price, sell_price, original_price):
  - Always 2 decimals
  - Pattern: number_format(..., 2, ',', '.')
  
✓ Calculated/Read-Only Fields (total, subtotal, tax_nominal, amount):
  - Always 0 decimals  
  - Pattern: number_format(..., 0, ',', '.')
  
✓ Percentage Fields (tax, discount):
  - 0 decimals with % suffix
  - Pattern: ->numeric()->suffix('%')
```

### **Rule 2: Currency Handling**
```
✓ Multi-Currency Resources (OrderRequest, PurchaseOrder):
  - Use resolveCurrencySymbol() or Currency::find()
  - Dynamic prefix based on currency_id
  - Handle NULL currency gracefully
  
✓ Single-Currency Resources (Product, SaleOrder, Deposit):
  - Static prefix 'Rp'
  - No dynamic currency resolution needed
```

### **Rule 3: Formatting Pattern** (Standard)
```php
// For user-input price fields:
TextInput::make('unit_price')
    ->label('Harga Satuan')
    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(...))
    ->dehydrateStateUsing(fn($state) => MoneyHelper::parse($state ?? 0))
    ->formatStateUsing(fn($state) => 
        $state !== null && $state !== '' 
            ? number_format(MoneyHelper::parse($state), 2, ',', '.')
            : ''
    )
    ->reactive()
    ->live(onBlur: true)
    ->afterStateUpdated(function ($state, callable $set, callable $get) {
        // Recalculate dependent fields with correct decimals
    })
    
// For calculated/read-only fields:
TextInput::make('total')
    ->label('Total')
    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(...))
    ->readOnly()
    ->dehydrated(false)
    ->formatStateUsing(fn($state) =>
        number_format(MoneyHelper::parse($state), 0, ',', '.')
    )
```

### **Rule 4: afterStateUpdated Calculations**
```php
// When setting calculated fields, use correct decimals:
$set('subtotal', number_format($calculated_value, 0, ',', '.'));  // 0 decimals
$set('total', number_format($calculated_value, 0, ',', '.'));    // 0 decimals
$set('unit_price', number_format($calculated_value, 2, ',', '.')); // 2 decimals
```

---

## 📋 TESTING STRATEGY

### **Unit Tests**
- [ ] Test MoneyHelper::parse() with decimals
- [ ] Test number_format behavior with 0 vs 2 decimals
- [ ] Test formatStateUsing/dehydrateStateUsing round-trip

### **Playwright Tests** (Browser Tests)
```
✓ test-sku-040-order-request-price.spec.mjs - PASSING
  (Validates SKU-040 shows 29,80 in form, stores 29.80 in DB)

NEW:
- test-original-price-decimal-display.spec.mjs
  (Validate original_price shows 2 decimals)
  
- test-purchase-order-decimal-consistency.spec.mjs
  (Validate PO item prices show/store correctly)
  
- test-calculated-field-decimals.spec.mjs
  (Validate totals/tax always 0 decimals)
```

### **Manual Testing Checklist**
- [ ] Create OrderRequest dengan USD currency → verify 2 decimals
- [ ] Edit OrderRequest price → verify decimal preserved
- [ ] Create PurchaseOrder → verify decimal consistency
- [ ] Create SaleOrder → verify no decimal loss
- [ ] Test rounding: 29.80 should NOT become 30

---

## ⚠️ POTENTIAL ISSUES & MITIGATIONS

### Issue 1: Backward Compatibility
**Risk**: Changing decimals might affect existing data displays
**Mitigation**: 
- All changes are display/formatting only, DB unchanged
- MoneyHelper::parse() already handles both formats
- Test with existing data (SKU-040 already tests this)

### Issue 2: Calculated Field Precision
**Risk**: 0-decimal totals might lose precision in calculations
**Mitigation**:
- Keep calculations using floats internally
- Only round-format when displaying
- Use number_format(..., 2) for internal math, then number_format(..., 0) for display

### Issue 3: Multi-Currency Edge Cases
**Risk**: Some fields might not know currency_id at render time
**Mitigation**:
- Use $get('currency_id') with fallback to parent $get('../../currency_id')
- Handle NULL gracefully: don't crash, just omit prefix
- Test edge cases (no currency selected, parent currency missing, etc.)

### Issue 4: Indonesia Money Macro
**Risk**: ->indonesianMoney() hardcodes 0 decimals + 'Rp' prefix
**Mitigation**:
- Don't use ->indonesianMoney() for multi-currency fields
- OK to use for single-currency IDR fields
- For critical fields, prefer explicit number_format(..., 2 or 0, ',', '.')

---

## 📈 ROLLOUT STRATEGY

### **Phase 1** (DONE) 
- OrderRequestResource unit_price: 2 decimals ✅
- Playwright test updated & passing ✅

### **Phase 2** (NEXT - Week 1)
- OrderRequestResource original_price: 2 decimals
- Verify calculated fields: 0 decimals
- Add tests

### **Phase 3** (Week 1-2)
- PurchaseOrderResource standardization
- Run full PO test suite
- Update documentation

### **Phase 4** (Week 2)
- SaleOrderResource standardization
- Spot-check other resources

### **Phase 5** (Week 2-3)
- Final validation
- Performance testing
- Deploy to production

---

## 📝 DOCUMENTATION UPDATES NEEDED

1. **Code Comments**: Add comment in OrderRequestResource explaining decimal rules
2. **Developer Guide**: Document formatStateUsing/dehydrateStateUsing patterns
3. **Bug Tracker**: Document this standardization as completed
4. **Migration Notes**: If needed for future developers

---

## ✅ SUCCESS CRITERIA

- [ ] All user-input price fields: 2 decimals
- [ ] All calculated fields: 0 decimals
- [ ] No data loss or rounding errors
- [ ] All Playwright tests passing
- [ ] PHP syntax: No errors
- [ ] Documentation updated
- [ ] No performance regression

---

## 🔗 RELATED FILES

| File | Status | Notes |
|------|--------|-------|
| `app/Filament/Resources/OrderRequestResource.php` | 🟢 PARTIAL | Phase 1 done, Phase 2 pending |
| `app/Filament/Resources/PurchaseOrderResource.php` | 🟠 NEEDS AUDIT | Phase 3 |
| `app/Filament/Resources/SaleOrderResource.php` | 🟠 NEEDS AUDIT | Phase 4 |
| `tests/playwright/order-request-edit-sku-040-price.spec.mjs` | 🟢 OK | Updated & passing |
| `PRICE_TEXTINPUT_AUDIT.md` | 🟢 REFERENCE | Full audit details |

---

## 💡 RECOMMENDATIONS

1. **Start dengan Phase 2** (OrderRequest original_price) - 30 menit, low risk
2. **Then Phase 3** (PurchaseOrder) - Most important after OrderRequest
3. **Then Phase 4** (SaleOrder) - Should be quick since IDR-only
4. **Phase 5** dapat dilakukan bersamaan dengan QA testing

---

**Prepared by**: Code Audit Agent  
**Date**: 13 Mei 2026  
**Next Review**: After Phase 2 completion
