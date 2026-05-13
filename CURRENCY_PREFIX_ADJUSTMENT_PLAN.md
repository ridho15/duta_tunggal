# Currency Prefix Symbol Adjustment Plan
**Date**: May 13, 2026  
**Objective**: Ensure all resources with currency selection (Select mata uang) have dynamic currency symbol prefixes matching the selected currency

---

## Executive Summary

Audit revealed **5 resources with currency selection** and **price input fields**. Current status:
- ✅ **3 resources**: Already correctly implementing dynamic currency symbols
- ❌ **2 resources**: Need fixes for proper dynamic currency prefix handling  
- ⚠️ **1 resource**: Only supports IDR (no multi-currency support needed)

---

## Detailed Findings

### 1. OrderRequestResource ✅ CORRECT
**Status**: Already properly implemented  
**Location**: `app/Filament/Resources/OrderRequestResource.php`

**Currency Selection**: Line 347 + Line 529 (main form + repeater items)  
**Price Fields with Prefixes**:
- Line 685: `original_price` → Uses `resolveCurrencySymbol()` with currency_id lookup ✅
- Line 706: `unit_price` → Uses `resolveCurrencySymbol()` with currency_id lookup ✅
- Line 825: `total` → Uses `resolveCurrencySymbol()` ✅
- Line 843: `tax_nominal` → Uses `resolveCurrencySymbol()` ✅
- Line 874: `subtotal` → Uses `resolveCurrencySymbol()` ✅

**Pattern Used**:
```php
->prefix(fn(Get $get) => self::resolveCurrencySymbol(
    is_numeric($get('currency_id'))
        ? (int) $get('currency_id')
        : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
))
```

**Handling**: Includes fallback for repeater items (`../../currency_id`) to get parent currency context  
**Assessment**: ✅ **NO CHANGES NEEDED**

---

### 2. SaleOrderResource ❌ NEEDS FIXING
**Status**: Inconsistent - some fields hardcoded, some dynamic  
**Location**: `app/Filament/Resources/SaleOrderResource.php`

**Currency Selection**: Line 556 (main form)  
**Price Fields Analysis**:

| Line | Field | Current Prefix | Issue | Should Be |
|------|-------|---|---|---|
| 795 | `unit_price` | None (no prefix) | ❌ Missing dynamic prefix | Needs `->prefix()` with currency symbol |
| 818 | `total` | Hardcoded `'Rp'` | ❌ Ignores selected currency | Should use `resolveCurrencySymbol()` |
| 914 | `tax_nominal` | `resolveCurrencySymbol()` | ✅ Correct | Already uses dynamic symbol |

**Current Code (Line 818 - ISSUE)**:
```php
TextInput::make('total')
    ->label('Total (Harga × Qty)')
    ->prefix('Rp')  // ❌ HARDCODED - doesn't match selected currency
    ->readOnly()
    ->dehydrated(false)
    ->default(0)
    ->indonesianMoney()
```

**Current Code (Line 914 - CORRECT)**:
```php
TextInput::make('tax_nominal')
    ->label('Nominal Pajak')
    ->prefix(fn (callable $get) => static::resolveCurrencySymbol(
        is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()
    ))
    ->readOnly()
    ->dehydrated(false)
```

**Required Changes**:
1. **Line 795 (unit_price)**: Add dynamic currency prefix
   - Status: User input field for USD/EUR/IDR prices
   - Change: Add `->prefix()` callback with `resolveCurrencySymbol()`
   
2. **Line 818 (total)**: Replace hardcoded 'Rp' with dynamic symbol
   - Status: Calculated read-only field showing item total
   - Change: Replace `->prefix('Rp')` with dynamic symbol resolution
   - Note: Follow same pattern as tax_nominal at line 914

**Assessment**: ❌ **REQUIRES 2 FIXES**

---

### 3. PurchaseOrderResource ✅ CORRECT
**Status**: Already properly implemented  
**Location**: `app/Filament/Resources/PurchaseOrderResource.php`

**Currency Selection**: Line 809 + Line 1198 + Line 1335 (main items + biaya + currencies)  
**Price Fields with Prefixes**:
- Line 876: `unit_price` (item repeater) → Dynamic prefix ✅
- Line 904: `unit_price` (item repeater) → Uses `Currency::find()` ✅
- Line 914: `total` (item repeater) → Uses `Currency::find()` ✅
- Line 1235: `total` (biaya repeater) → Uses `Currency::find()` ✅
- Line 1366: `nominal` (currency repeater) → Uses `Currency::find()` ✅

**Pattern Used**:
```php
->prefix(function ($get) {
    $currency = Currency::find($get('currency_id'));
    if ($currency) {
        return $currency->symbol;
    }
    return null;
})
```

**Assessment**: ✅ **NO CHANGES NEEDED**

---

### 4. PurchaseReceiptResource ✅ CORRECT
**Status**: Already properly implemented  
**Location**: `app/Filament/Resources/PurchaseReceiptResource.php`

**Currency Selection**: Line 127 + Line 163 (main form + repeater)  
**Price Fields with Prefixes**:
- Line 198: `total` (biaya repeater) → Uses `Currency::find()` ✅

**Pattern Used**:
```php
->prefix(function ($get) {
    $currency = Currency::find($get('currency_id'));
    if ($currency) {
        return $currency->symbol;
    }
})
```

**Assessment**: ✅ **NO CHANGES NEEDED**

---

### 5. QuotationResource ⚠️ NO MULTI-CURRENCY
**Status**: No currency selection - IDR only  
**Location**: `app/Filament/Resources/QuotationResource.php`

**Currency Selection**: None found  
**Currency Handling**: Hardcoded to IDR (Rp)

**Price Fields Analysis**:
- Line 681: `tax_nominal` → Hardcoded `->prefix('Rp')` ✅ (appropriate since no currency select)
- No currency_id select in form

**Assessment**: ⚠️ **NO CHANGES NEEDED** (QuotationResource is IDR-only; if future multi-currency support added, will need update)

---

## Summary of Changes Required

### Phase 1: SaleOrderResource Fixes (Priority: HIGH)

**File**: `app/Filament/Resources/SaleOrderResource.php`

#### Fix 1: Add currency prefix to unit_price field
**Line**: ~795  
**Type**: Add missing prefix  
**Change**: Add dynamic currency symbol prefix to match selected currency

**Before**:
```php
TextInput::make('unit_price')
    ->label('Unit Price')
    ->indonesianMoney()
    ->validationMessages([...])
    ->reactive()
    ->afterStateUpdated(...)
```

**After**:
```php
TextInput::make('unit_price')
    ->label('Unit Price')
    ->prefix(fn (callable $get) => static::resolveCurrencySymbol(
        is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()
    ))
    ->indonesianMoney()
    ->validationMessages([...])
    ->reactive()
    ->afterStateUpdated(...)
```

---

#### Fix 2: Replace hardcoded 'Rp' with dynamic currency prefix in total field
**Line**: ~818  
**Type**: Replace hardcoded value with dynamic resolution  
**Change**: Use same pattern as tax_nominal field (line 916)

**Before**:
```php
TextInput::make('total')
    ->label('Total (Harga × Qty)')
    ->prefix('Rp')  // ❌ Hardcoded
    ->readOnly()
    ->dehydrated(false)
    ->default(0)
    ->indonesianMoney()
    ->afterStateHydrated(...)
```

**After**:
```php
TextInput::make('total')
    ->label('Total (Harga × Qty)')
    ->prefix(fn (callable $get) => static::resolveCurrencySymbol(
        is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()
    ))
    ->readOnly()
    ->dehydrated(false)
    ->default(0)
    ->indonesianMoney()
    ->afterStateHydrated(...)
```

---

## Implementation Verification

After fixes, verify:

1. **SaleOrderResource unit_price field**:
   - [ ] When currency_id = USD, prefix shows "$"
   - [ ] When currency_id = EUR, prefix shows "€"
   - [ ] When currency_id = IDR, prefix shows "Rp"
   - [ ] Defaults to IDR when no currency selected

2. **SaleOrderResource total field**:
   - [ ] When currency_id = USD, prefix shows "$"
   - [ ] When currency_id = EUR, prefix shows "€"
   - [ ] When currency_id = IDR, prefix shows "Rp"
   - [ ] Matches currency symbol in unit_price field

3. **Cross-resource consistency**:
   - [ ] OrderRequestResource maintains current correct behavior
   - [ ] PurchaseOrderResource maintains current correct behavior
   - [ ] PurchaseReceiptResource maintains current correct behavior

---

## Testing Strategy

### Unit Tests
- Test `resolveCurrencySymbol()` with various currency_ids
- Test fallback to default currency

### Integration Tests
- Create SaleOrder with USD currency → verify unit_price and total show "$"
- Create SaleOrder with EUR currency → verify unit_price and total show "€"
- Create SaleOrder with IDR currency → verify unit_price and total show "Rp"

### UI Tests (Playwright)
- Load SaleOrder form with each currency
- Verify prefix symbols update dynamically
- Test form submission with different currencies

---

## Risk Assessment

**Likelihood of Issues**: Low  
**Reason**: Pattern already proven working in PurchaseOrderResource, OrderRequestResource  
**Mitigation**: Follow existing patterns exactly

---

## Priority & Sequencing

**Phase 1** (This Request):  
- SaleOrderResource unit_price field (Add prefix)
- SaleOrderResource total field (Replace hardcoded prefix)

**Phase 2** (Optional Future):  
- If QuotationResource adds multi-currency support, apply same pattern
- Audit any custom resources in subdirectories

---

## Approval Checkpoint

This plan documents:
1. ✅ All 5 resources with currency selection identified
2. ✅ Current state of each resource documented
3. ✅ Issues clearly identified (SaleOrderResource only)
4. ✅ Specific code changes required
5. ✅ Testing strategy provided

**Ready for**: User review and approval before implementation

---

**Next Step**: Approve this plan, then proceed to implement Phase 1 fixes in SaleOrderResource
