# 📋 Currency Verification Execution Checklist

**Prepared:** 13 May 2026  
**Total Tasks:** 38+ test cases across 7 phases  
**Estimated Duration:** 4 weeks  

---

## PHASE 1: Form Input & Conversion Testing (Week 1)

### OrderRequest Multi-Currency Handling
- [ ] **1.1.1** IDR Entry → Stored as 1000000.00, display "Rp 1.000.000,00"
- [ ] **1.1.2** USD Entry → Stored as 1000.00, display "$ 1.000,00" (NOT converted)
- [ ] **1.1.3** Currency Switch → Prefix changes, amount stays same
- [ ] **1.1.4** Price Change Recalc → Subtotal updates in transaction currency
- [ ] **1.1.5** Tax on Foreign Currency → Tax stored in USD (not IDR)

**File:** `tests/Feature/CurrencyAmountInputValidationTest.php`  
**Run Command:** `php artisan test tests/Feature/CurrencyAmountInputValidationTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### SaleOrder Livewire Reactivity
- [ ] **1.2.1** Add Item (IDR) → Stored correctly
- [ ] **1.2.2** Switch to USD → Prefix $, value NOT converted (1.000.000 ≠ 62.5)
- [ ] **1.2.3** Item Total After Switch → Shows "$ 2.000.000" (not "$ 62,50")
- [ ] **1.2.4** Reload & Verify → USD persists, amount unchanged

**File:** `tests/Feature/SaleOrderCurrencyLifecycleTest.php`  
**Run Command:** `php artisan test tests/Feature/SaleOrderCurrencyLifecycleTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### PurchaseOrder Mixed Currency
- [ ] **1.3.1** Item in USD → Stored as 1000.00 USD (not IDR)
- [ ] **1.3.2** Item in EUR → 500.00 EUR separate from USD
- [ ] **1.3.3** Display Both → "$ 1.000,00" + "€ 500,00"
- [ ] **1.3.4** Mixed Total → Error/sum separately (not merged)

**File:** `tests/Feature/PurchaseOrderMixedCurrencyTest.php`  
**Run Command:** `php artisan test tests/Feature/PurchaseOrderMixedCurrencyTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

**Phase 1 Summary:**
- [ ] All 3 test files created
- [ ] All 13 assertions passing
- [ ] No corruption detected
- **Estimated Time:** 2-3 days

---

## PHASE 2: Persistence & Computed Fields (Week 1-2)

### Data Integrity After Save
- [ ] **2.1.1** Decimal Precision → "1.234,56" stored as 1234.56 (DECIMAL:2)
- [ ] **2.1.2** Large Numbers → "999.999.999,99" no overflow
- [ ] **2.1.3** Zero & Negatives → Validation correct
- [ ] **2.1.4** Currency Mismatch → Resolved or error

**File:** `tests/Feature/CurrencyAmountPersistenceTest.php`  
**Run Command:** `php artisan test tests/Feature/CurrencyAmountPersistenceTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### Calculated Fields (Subtotal, Tax, Total)
- [ ] **2.2.1** Subtotal Formula → unit_price × qty (in USD, not converted)
- [ ] **2.2.2** Tax Calculation → 10% of 1.000.000 IDR = 100.000 IDR
- [ ] **2.2.3** Final Total → Subtotal + Tax (same currency)
- [ ] **2.2.4** Discount Applied → On transaction currency, not rate-adjusted

**File:** `tests/Feature/OrderRequestComputedFieldsTest.php`  
**Run Command:** `php artisan test tests/Feature/OrderRequestComputedFieldsTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### Reload Consistency
- [ ] **4.2.1** Form Reload → Values identical (byte-for-byte)
- [ ] **4.2.2** Infolist Reload → Amounts match form
- [ ] **4.2.3** Cross-Resource → Linked amounts in sync
- [ ] **4.2.4** Audit Trail → Old/new amounts logged

**File:** `tests/Feature/CurrencyConsistencyReloadTest.php`  
**Run Command:** `php artisan test tests/Feature/CurrencyConsistencyReloadTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### SO→PO Conversion
- [ ] **5.2.1** Create PO from SO → Currency inherited or forced to IDR?
- [ ] **5.2.2** Item Amount Transferred → 1.000 USD → 1.000 USD or 16M IDR?
- [ ] **5.2.3** PO Save & Display → Prefix + amount consistent
- [ ] **5.2.4** Service Layer Rule Clear → Document behavior

**File:** `tests/Feature/SaleOrderToPurchaseOrderConversionTest.php`  
**Run Command:** `php artisan test tests/Feature/SaleOrderToPurchaseOrderConversionTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

**Phase 2 Summary:**
- [ ] All 4 test files created
- [ ] All 16 assertions passing
- [ ] Persistence verified
- **Estimated Time:** 3-4 days

---

## PHASE 3: Display & Formatting (Week 2-3)

### Form Fields Display
- [ ] **3.1.1** Unit Price Prefix → Shows "$", amount "1.000,00"
- [ ] **3.1.2** Subtotal Recalc Display → Updates to "$ 5.000,00" (qty change)
- [ ] **3.1.3** Tax Field Display → Shows "Rp 100.000,00"
- [ ] **3.1.4** Readonly Fields → Display only, no edit

**File:** `tests/Playwright/sale-order-currency-display.spec.mjs`  
**Run Command:** `npx playwright test tests/playwright/sale-order-currency-display.spec.mjs`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

### Infolist Display
- [ ] **3.2.1** Single Currency → All items "$ X,XX"
- [ ] **3.2.2** Item Tax Display → "$ 100,00"
- [ ] **3.2.3** Subtotal Match → Infolist = DB storage
- [ ] **3.2.4** Mixed Currency → Correct symbols per item

**File:** `tests/Playwright/order-request-currency-infolist.spec.mjs`  
**Run Command:** `npx playwright test tests/playwright/order-request-currency-infolist.spec.mjs`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

**Phase 3 Summary:**
- [ ] 2 Playwright specs created & passing
- [ ] All 8 display assertions verified
- [ ] UI rendering correct
- **Estimated Time:** 2-3 days

---

## PHASE 4: Edge Cases (Week 3)

- [ ] **4.1.1** Zero Amount → "$ 0,00" (no error)
- [ ] **4.1.2** Very Small Amount → "0,01" preserved, not rounded
- [ ] **4.1.3** Very Large Amount → "9.999.999,99" (no overflow)
- [ ] **4.1.4** Null Currency → Inherit or error?
- [ ] **4.1.5** Invalid Currency ID → Fallback or error?

**File:** `tests/Feature/CurrencyEdgeCasesTest.php`  
**Run Command:** `php artisan test tests/Feature/CurrencyEdgeCasesTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

**Phase 4 Summary:**
- [ ] Edge case test created
- [ ] 5 edge scenarios verified
- **Estimated Time:** 1-2 days

---

## PHASE 5: Integration Workflows (Week 4)

### Complete OrderRequest Workflow
**Scenario:**
1. Create with USD, unit_price=1000, qty=5
2. Compute: subtotal=5000 USD, tax=500 USD, total=5500 USD
3. Save
4. Reload
5. Switch to EUR (no re-enter)
6. Change qty to 3
7. Save
8. View infolist

**Checkpoints:**
- [ ] Step 1-2: Amounts computed correctly in USD
- [ ] Step 3-4: All values persist
- [ ] Step 5-6: Prefix changes to €, amounts stay USD (1000, 5000)
- [ ] Step 7: qty recalcs to 3, subtotal=3000 USD
- [ ] Step 8: Infolist shows "€ 1.000,00" (original unit_price)

**File:** `tests/Feature/OrderRequestEndToEndWorkflowTest.php`  
**Status:** [ ] Not Started [ ] In Progress [ ] Complete ✓

---

**Phase 5 Summary:**
- [ ] End-to-end test created & passing
- [ ] Full workflow verified
- **Estimated Time:** 1-2 days

---

## 🎯 FINAL VALIDATION

### All Tests Running
```bash
php artisan test tests/Feature/Currency*.php
npx playwright test tests/playwright/currency-*.spec.mjs
```

- [ ] All 38+ test cases passing
- [ ] No failures or warnings
- [ ] Code coverage acceptable

### Manual Verification (Browser)
- [ ] Create OrderRequest with IDR
  - [ ] Add item: "1.000.000"
  - [ ] Verify DB: 1000000.00
  - [ ] Verify display: "Rp 1.000.000,00"
  
- [ ] Switch currency to USD
  - [ ] Verify prefix changes to "$"
  - [ ] Verify amount stays "1.000.000" (NOT "62,50")
  - [ ] Verify subtotal: "$ 2.000.000" (qty=2, no conversion)
  
- [ ] Save & reload
  - [ ] Verify all values identical
  - [ ] Verify currency_id=2 persists

### Code Review
- [ ] CurrencyConversionResolver usage correct
- [ ] MoneyHelper parsing/formatting working
- [ ] Form handlers (afterStateUpdated) not converting
- [ ] Infolist display logic correct

---

## 📊 Success Metrics

| Metric | Target | Status |
|--------|--------|--------|
| Test Pass Rate | 100% | [ ] |
| Code Coverage | >80% | [ ] |
| No Data Corruption | 0 failures | [ ] |
| Display Accuracy | All prefixes correct | [ ] |
| Reload Persistence | Byte-for-byte match | [ ] |
| Edge Cases Handled | 5/5 | [ ] |
| Integration Flows | All working | [ ] |

---

## ⚠️ Known Issues / Questions to Resolve

| Issue | Resolution | Status |
|-------|-----------|--------|
| SO→PO currency inheritance | Define rule: inherit USD or force IDR? | [ ] |
| Mixed PO total calculation | Sum per-currency or error? | [ ] |
| Null currency_id fallback | Inherit parent or validation error? | [ ] |
| Negative amount validation | Block or allow for adjustments? | [ ] |

---

## 📅 Timeline

| Week | Phase | Target | Status |
|------|-------|--------|--------|
| **Week 1** | 1-2 (Input, Persistence) | 7 test files, 29 assertions | [ ] |
| **Week 2** | 2-3 (Display) | 2 Playwright specs | [ ] |
| **Week 3** | 4-5 (Edge cases, Integration) | 2 test files | [ ] |
| **Week 4** | Final validation & review | All green, manual QA | [ ] |

---

## ✅ Sign-Off

**Plan Reviewed By:** _______________  
**Date:** _______________  
**Ready to Execute:** [ ] YES [ ] NO (Concerns: _______________)

---

## 📎 Attachments

1. **CURRENCY_VERIFICATION_PLAN.md** — Full detailed plan
2. **Phase Diagrams:**
   - Currency Amount Lifecycle Verification Plan
   - Currency Amount Data Flow & Verification Points
   - Currency Verification: Risk Matrix & Testing Priority

---

**Document Status:** ✅ READY FOR EXECUTION

Print this checklist or save as PDF for tracking progress throughout the 4-week verification cycle.
