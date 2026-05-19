# 📌 Currency Verification Plan - Executive Summary

**Version:** 1.0  
**Date:** 13 May 2026  
**Prepared For:** Development Team  
**Status:** ✅ Ready for Approval & Execution

---

## What Is This Plan?

A comprehensive 4-week verification and testing strategy to ensure **currency amounts (money values) stay accurate and consistent** throughout the entire lifecycle:

1. **Input** — User enters amount in form (e.g., "1.000.000" USD)
2. **Conversion** — User changes currency (prefix changes, but amount should NOT be auto-converted)
3. **Storage** — Amount saved to database
4. **Display** — Amount shown in form/infolist with correct prefix
5. **Reload** — Values remain identical across page reloads

---

## The Problem We're Solving

**Original Issue:** When user changed currency in Sale Order from IDR to USD, the "Total (Harga × Qty)" field did NOT recalculate or update. Only the prefix (Rp → $) changed, but the visible amount stayed frozen.

**Fix Applied:** Currency change now triggers recalculation in Sale Order (see [app/Filament/Resources/SaleOrderResource.php#580](app/Filament/Resources/SaleOrderResource.php#580))

**But New Question:** Are there other cases where amounts get corrupted, converted unexpectedly, or lost? We need to verify the ENTIRE system.

---

## Quick Facts

| Metric | Value |
|--------|-------|
| **Test Phases** | 7 phases |
| **Test Cases** | 38+ cases |
| **Test Files to Create** | 12 files |
| **Total Assertions** | 140+ |
| **Estimated Duration** | 4 weeks |
| **Risk Level** | HIGH (money data) |
| **Priority** | CRITICAL |

---

## What Gets Tested

### ✅ Included in Scope

- ✔️ Form input (OrderRequest, SaleOrder, PurchaseOrder, Quotation)
- ✔️ Multi-currency handling (USD, EUR, IDR, JPY, etc.)
- ✔️ Currency switching (mid-form, no re-entry needed)
- ✔️ Calculated fields (subtotal = unit_price × qty, tax calculations)
- ✔️ Database storage (DECIMAL:2 precision, no rounding errors)
- ✔️ Display formatting (prefix + number_format)
- ✔️ Page reload (values persist identically)
- ✔️ Cross-resource flows (SaleOrder → PurchaseOrder conversion)
- ✔️ Edge cases (zero, null, very large amounts)
- ✔️ Mixed-currency PurchaseOrder (USD + EUR items)

### ❌ Out of Scope (For Now)

- Multi-currency invoice consolidation (complex accounting rules)
- Real-time exchange rate updates
- Historical rate tracking
- Currency conversion for reports (unless explicitly called)

---

## Key Principles

### Principle 1: No Unexpected Conversions
When user changes currency in form:
- Prefix **SHOULD** change (IDR → $ USD)
- Amount **SHOULD NOT** change (1.000.000 stays 1.000.000, not converted to 62.5)

### Principle 2: Storage = Input
What's entered in the form is stored in DB:
- "1.000.000" USD → stored as 1000000.00 USD (not converted to IDR)
- Currency is stored separately (currency_id column)

### Principle 3: Computed Fields Stay in Transaction Currency
Calculations happen in the original currency:
- Subtotal = unit_price × qty (in USD, not IDR)
- Tax = subtotal × tax_rate (in USD, not IDR)
- Total = subtotal + tax + discount (all in USD)

### Principle 4: Display Shows Currency Context
Amounts always shown with correct symbol:
- "$ 1.000,00" (USD)
- "Rp 1.000.000,00" (IDR)
- "€ 500,00" (EUR)

---

## Test Pyramid

```
                          ▲
                         /|\
                        / | \
                       /  |  \    Phase 5-6: Integration (2 scenarios)
                      /   |   \   Risk: MEDIUM
                     /    |    \  Tests: 6 cases
                    /_____|_____\
                   /      |      \
                  /       |       \  Phase 3-4: Display & Edge (6 test files)
                 /        |        \ Risk: MEDIUM
                /         |         \ Tests: 13 cases
               /_________|_________\
              /          |          \
             /           |           \  Phase 1-2: Input & Persistence (7 test files)
            /            |            \ Risk: HIGH
           /             |             \ Tests: 19 cases
          /______________|______________\
```

---

## Test Breakdown by Risk

### 🔴 HIGH RISK (Week 1 - Do First)

These are the most likely to have bugs and most critical to verify:

| Test Case | Why Critical | Estimated Time |
|-----------|-------------|-----------------|
| SO currency switch amount NOT converted | Original bug fix verification | 1 day |
| PO mixed-currency (USD + EUR) items | Complex per-item handling | 1 day |
| OrderRequest computed fields (subtotal, tax) | Calculation correctness | 1 day |
| Form reload persistence | Data integrity | 1 day |
| SO→PO conversion rule | Service layer logic | 1 day |

**Week 1 Total: 5 days, 19 test cases**

---

### 🟡 MEDIUM RISK (Week 2-3)

Display and edge cases (important but lower severity):

| Test Case | Why Important | Estimated Time |
|-----------|--------------|-----------------|
| Display prefix correctness | UI/UX accuracy | 1 day |
| Infolist formatting | View page correctness | 1 day |
| Null/zero amounts | Graceful handling | 1 day |
| Mid-flow changes | Workflow robustness | 1 day |

**Week 2-3 Total: 4 days, 13 test cases**

---

### 🟢 LOW RISK (Week 3-4)

Edge cases unlikely to occur in normal usage:

| Test Case | Why Still Test | Estimated Time |
|-----------|----------------|-----------------|
| Very large numbers (no overflow) | Data safety | 0.5 days |
| Negative amounts (validation) | Business rules | 0.5 days |
| Invalid currency ID (fallback) | Error handling | 0.5 days |

**Week 3-4 Total: 1.5 days, 8 test cases**

---

## Execution Plan

### Week 1: Foundation (HIGH RISK)

**Monday-Friday:** Create & run HIGH risk test files

```bash
# Create test files
touch tests/Feature/CurrencyAmountInputValidationTest.php
touch tests/Feature/SaleOrderCurrencyLifecycleTest.php
touch tests/Feature/PurchaseOrderMixedCurrencyTest.php
touch tests/Feature/CurrencyAmountPersistenceTest.php
touch tests/Feature/OrderRequestComputedFieldsTest.php
touch tests/Feature/CurrencyConsistencyReloadTest.php
touch tests/Feature/SaleOrderToPurchaseOrderConversionTest.php

# Run all tests
php artisan test tests/Feature/Currency*.php
```

**Expected Outcome:** 
- [ ] 7 test files created
- [ ] 100% pass rate
- [ ] No data corruption detected

---

### Week 2: Display & Persistence (MEDIUM RISK)

**Monday-Wednesday:** Create display tests

```bash
# Playwright specs
npx playwright test tests/playwright/sale-order-currency-display.spec.mjs
npx playwright test tests/playwright/order-request-currency-infolist.spec.mjs
```

**Thursday-Friday:** Edge cases

```bash
php artisan test tests/Feature/CurrencyEdgeCasesTest.php
```

**Expected Outcome:**
- [ ] 2 Playwright specs passing
- [ ] 1 edge case test file passing
- [ ] All display formats correct

---

### Week 3: Integration (MEDIUM RISK)

**Monday-Wednesday:** End-to-end workflow

```bash
php artisan test tests/Feature/OrderRequestEndToEndWorkflowTest.php
```

**Thursday-Friday:** Manual browser verification

- [ ] Create order (IDR)
- [ ] Switch currency (USD)
- [ ] Change qty
- [ ] Verify amounts
- [ ] Reload page

**Expected Outcome:**
- [ ] Integration test passing
- [ ] Manual verification successful
- [ ] No data loss on reload

---

### Week 4: Final Review (ALL TESTS)

```bash
# Complete test run
php artisan test tests/Feature/Currency*.php
npx playwright test tests/playwright/currency-*.spec.mjs

# Check coverage
php artisan test --coverage
```

**Final Checklist:**
- [ ] All 38+ tests passing
- [ ] Zero failures
- [ ] Code coverage >80%
- [ ] No regressions in other tests
- [ ] Documentation updated

---

## Key Files to Review/Create

### Existing Files (Reference)

| File | Purpose | Link |
|------|---------|------|
| CurrencyConversionResolver | Core conversion logic | [app/Support/CurrencyConversionResolver.php](app/Support/CurrencyConversionResolver.php) |
| MoneyHelper | Money formatting/parsing | [app/Helpers/MoneyHelper.php](app/Helpers/MoneyHelper.php) |
| SaleOrderResource | SO currency handling | [app/Filament/Resources/SaleOrderResource.php#580](app/Filament/Resources/SaleOrderResource.php#580) |
| OrderRequestResource | OR item pricing | [app/Filament/Resources/OrderRequestResource.php#346](app/Filament/Resources/OrderRequestResource.php#346) |
| PurchaseOrderResource | PO per-item currency | [app/Filament/Resources/PurchaseOrderResource.php#906](app/Filament/Resources/PurchaseOrderResource.php#906) |

### New Files to Create (12 files)

1. ✅ `tests/Feature/CurrencyAmountInputValidationTest.php`
2. ✅ `tests/Feature/SaleOrderCurrencyLifecycleTest.php`
3. ✅ `tests/Feature/PurchaseOrderMixedCurrencyTest.php`
4. ✅ `tests/Feature/CurrencyAmountPersistenceTest.php`
5. ✅ `tests/Feature/OrderRequestComputedFieldsTest.php`
6. ✅ `tests/Feature/CurrencyConsistencyReloadTest.php`
7. ✅ `tests/Feature/SaleOrderToPurchaseOrderConversionTest.php`
8. ✅ `tests/Feature/CurrencyEdgeCasesTest.php`
9. ✅ `tests/Feature/OrderRequestEndToEndWorkflowTest.php`
10. ✅ `tests/Playwright/sale-order-currency-display.spec.mjs`
11. ✅ `tests/Playwright/order-request-currency-infolist.spec.mjs`
12. ✅ `tests/Playwright/order-request-multi-currency.spec.mjs` (optional)

---

## Expected Test Results

### If All Tests Pass ✅

```
Tests: 38+ cases across 12 files
Assertions: 140+
Status: ALL GREEN ✅

Findings:
✓ No data corruption
✓ Amounts consistent input→storage→display
✓ Currency prefixes correct
✓ Reload safe
✓ Edge cases handled gracefully
✓ Integration flows working

Recommendation: SHIP IT! 🚀
```

### If Tests Fail ❌

```
Failed Tests: X

Action Items:
1. Review failed test details
2. Identify root cause (code or test logic?)
3. Fix code or clarify test expectations
4. Re-run failed test
5. Verify no new failures
6. Repeat until all pass
```

---

## Success Criteria (Definition of Done)

✅ **MUST HAVE (Non-negotiable)**
- All 38+ test cases passing (100% pass rate)
- No data corruption or loss
- Amounts stored exactly as entered (no unexpected conversions)
- Prefix display correct per currency_id
- Reload persistence verified

✅ **SHOULD HAVE (Strong preference)**
- Code coverage >80%
- All edge cases handled gracefully
- Integration workflows tested
- Manual browser verification completed
- Documentation updated

✅ **NICE TO HAVE (Optional)**
- Performance benchmarks
- Historical audit trail tests
- Multi-currency report tests

---

## Dependencies & Blockers

| Item | Status | Impact |
|------|--------|--------|
| CurrencyConversionResolver deployed | ✅ Done | Core logic ready |
| SaleOrderResource fix deployed | ✅ Done | Original bug fixed |
| Test database setup | ✅ Available | Ready to use |
| Playwright environment | ✅ Ready | Browser tests can run |
| Team bandwidth | ⏳ Pending | Needed: 4 weeks |

**No blockers detected.** Ready to proceed.

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Tests find bugs in production code | MEDIUM | HIGH | Fix immediately, no ship |
| Tests take longer than expected | LOW | MEDIUM | Extend timeline if needed |
| Clarifications needed on currency rules | MEDIUM | MEDIUM | Document assumptions now |
| Env setup issues | LOW | LOW | Have backup plan |
| Scope creep | MEDIUM | HIGH | Stick to plan, defer extras |

**Overall Risk:** MANAGEABLE with clear plan and focus

---

## Questions to Answer Before Starting

1. **When currency changes in form:**
   - Should amounts auto-convert? (Current: NO)
   - Or stay same with new prefix? (Current: YES)
   - ➡️ **Action:** Confirm with PM

2. **SO→PO conversion:**
   - Should PO inherit USD from SO?
   - Or force to IDR for internal accounting?
   - ➡️ **Action:** Check SalesOrderService logic

3. **Mixed-currency PO:**
   - How to calculate total (USD + EUR)?
   - Per-currency sum or error?
   - ➡️ **Action:** Define business rule

4. **Null currency_id:**
   - Fallback to parent currency?
   - Or validation error?
   - ➡️ **Action:** Clarify model behavior

---

## Next Steps

### Immediate (This Week)

1. **Review & Approve Plan** 
   - [ ] Share with team
   - [ ] Address questions/concerns
   - [ ] Get sign-off

2. **Clarify Assumptions**
   - [ ] Currency switch behavior
   - [ ] SO→PO conversion rule
   - [ ] Null currency handling

3. **Setup Test Environment**
   - [ ] Test database ready
   - [ ] Playwright configured
   - [ ] Test fixtures prepared

### First Week

1. **Create Test Files** (refer to CURRENCY_VERIFICATION_PLAN.md for templates)
2. **Run Initial Tests** (expect some failures)
3. **Fix Code** (based on test results)
4. **Document Findings**

---

## Related Documents

📄 **Full Details:** [CURRENCY_VERIFICATION_PLAN.md](CURRENCY_VERIFICATION_PLAN.md)  
📋 **Checklist:** [CURRENCY_VERIFICATION_CHECKLIST.md](CURRENCY_VERIFICATION_CHECKLIST.md)  
📊 **Diagrams:**
- Currency Amount Lifecycle Verification Plan (visual)
- Currency Amount Data Flow & Verification Points (sequence)
- Currency Verification: Risk Matrix & Testing Priority (risk map)

---

## Sign-Off

| Role | Name | Date | Status |
|------|------|------|--------|
| **QA Lead** | _________________ | _________ | [ ] Approved |
| **Dev Lead** | _________________ | _________ | [ ] Approved |
| **PM** | _________________ | _________ | [ ] Approved |

---

## Contact & Questions

**Plan Author:** GitHub Copilot Agent  
**Date Created:** 13 May 2026  
**Version:** 1.0  

**Questions/Feedback:** Please refer to specific sections above or open discussion in team channel.

---

**STATUS:** ✅ **READY FOR EXECUTION**

Print or bookmark these 3 files:
1. CURRENCY_VERIFICATION_PLAN.md (detailed)
2. CURRENCY_VERIFICATION_CHECKLIST.md (tracking)
3. This file (executive summary)

Start with Week 1 test creation when ready.
