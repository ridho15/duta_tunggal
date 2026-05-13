# Price-Related TextInput Fields Audit

## Summary
This audit identifies all price-related TextInput fields across Filament Resources and their formatting patterns.

---

## Resources by File

### 1. **OrderRequestResource.php**
#### OrderRequestItem Repeater Fields

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 683 | `original_price` | 0 | ✓ Yes (resolveCurrencySymbol) | `number_format(..., 0, ',', '.')` | Read-only, currency prefix dynamic |
| 702 | `unit_price` | 2 | ✓ Yes (resolveCurrencySymbol) | `number_format(..., 2, ',', '.')` | reactive, live(onBlur: true) |
| 823 | `total` | 0 | ✓ Yes (resolveCurrencySymbol) | `number_format(..., 0, ',', '.')` | Read-only (qty × price) |
| 841 | `tax_nominal` | 0 | ✓ Yes (resolveCurrencySymbol) | Computed via TaxService | Read-only, calculated field |
| 871 | `subtotal` | 0 | ✓ Yes (resolveCurrencySymbol) | `->indonesianMoney()` | Read-only, disabled |

#### OrderRequestApprovalItem Fields (View Page)
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 1709 | `original_price` | 0 | ✓ Yes | `number_format(..., 0, ',', '.')` | formatStateUsing + afterStateHydrated |
| 1722 | `unit_price` | 2 | ✓ Yes | `number_format(..., 2, ',', '.')` | formatStateUsing + afterStateHydrated |
| 1772 | `tax_nominal` | 0 | ✓ Yes | `number_format(..., 0, ',', '.')` | Calculated via TaxService |
| 1776 | `total_cost` | 0 | ✓ Yes | `number_format(..., 0, ',', '.')` | Calculated |
| 1786 | `subtotal` | 0 | ✓ Yes | `number_format(..., 0, ',', '.')` | Calculated via TaxService |

**Currency-aware method**: `resolveCurrencySymbol()` resolves symbol based on currency_id from item or parent ($get('../../currency_id'))

---

### 2. **PurchaseOrderResource.php**
#### PurchaseOrderItem Repeater Fields

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 876 | `unit_price` | - | ✓ Yes | `->indonesianMoney()` | reactive, live(onBlur: true) |
| 898 | `total` | 0 | ✓ Yes (Currency::find) | `->indonesianMoney()` | Read-only, prefix 'Rp' |
| 962 | `subtotal` | - | ✓ Yes | `->indonesianMoney()` | Read-only, disabled, calculated |

#### PurchaseOrderBiaya (Costs) Fields
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 1218 | `total` | - | - | Manual parsing in repeater | Biaya item total |
| 1347 | `nominal` | - | - | exchangeRate field | Currency exchange rate |

#### Header Fields
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 1406 | `total_amount` | - | - | Manual calculation | Grand total of all items |

**Notes**: Uses `HelperController::parseIndonesianMoney()` and `self::formatMoneyState()` for conversions.

---

### 3. **ProductResource.php**
#### Main Form Fields

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 182 | `cost_price` | - | ✗ No | `->indonesianMoney()` | Required, default 0 |
| 191 | `sell_price` | - | ✗ No | `->indonesianMoney()` | Required, default 0 |

#### Branch Price Repeater Fields
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 635 | `cost_price_min` | - | ✗ No | - | Range validation |
| 638 | `cost_price_max` | - | ✗ No | - | Range validation |
| 740 | `cost_price` | - | ✗ No | `->indonesianMoney()` | Branch-specific cost |
| 749 | `sell_price` | - | ✗ No | `->indonesianMoney()` | Branch-specific sell price |
| 1065 | `cost_price` (array) | - | ✗ No | `->indonesianMoney()` | Dynamic field: "products.{$index}.cost_price" |
| 1070 | `sell_price` (array) | - | ✗ No | `->indonesianMoney()` | Dynamic field: "products.{$index}.sell_price" |

**Currency-aware**: NO - all ProductResource prices use fixed IDR (Rp) without currency selection.

---

### 4. **SaleOrderResource.php**
#### Header Field

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 573 | `total_amount` | - | ✗ No | `->indonesianMoney()` | Disabled, reactive |

#### SaleOrderItem Repeater Fields
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 795 | `unit_price` | - | ✗ No | `->indonesianMoney()` | reactive, live(onBlur: true) |
| 818 | `total` | 0 | ✗ No | prefix 'Rp' | Read-only, afterStateHydrated |
| 914 | `tax_nominal` | 0 | ✗ No | TaxService computed | Read-only |
| 932 | `subtotal` | - | ✗ No | `->indonesianMoney()` | Read-only, disabled |

**SaleOrderItemRelationManager** (Line 102, 146)
- `unit_price`: `->indonesianMoney()`
- `subtotal`: `->indonesianMoney()`

---

### 5. **DepositResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 125 | `amount` | - | ✗ No | `->indonesianMoney()` | Required, reactive, live(onBlur) |
| 480 | `amount_from` | - | ✗ No | - | Range field |
| 483 | `amount_to` | - | ✗ No | - | Range field |
| 539 | `amount` (section) | - | ✗ No | `->indonesianMoney()` | Repeater item |
| 592 | `amount` (subsection) | - | ✗ No | `->indonesianMoney()` | Nested repeater |

---

### 6. **CashBankTransferResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 68 | `amount` | - | ✗ No | `->indonesianMoney()` | minValue(0.01), required |
| 69 | `other_costs` | - | ✗ No | `->indonesianMoney()` | Biaya admin bank |

---

### 7. **MaterialIssueResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 301 | `cost_per_unit` | - | ✗ No | `->indonesianMoney()` | reactive, required |
| 318 | `total_cost` | - | ✗ No | `->indonesianMoney()` | Disabled, calculated (qty × cost) |

#### MaterialIssueItemRelationManager
| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 40 | `cost_per_unit` | - | ✗ No | `->indonesianMoney()` | required |

---

### 8. **StockOpnameResource.php (RelationManager)**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 98 | `unit_cost` | - | ✗ No | `->indonesianMoney()` | reactive, live() |
| 115 | `average_cost` | 0 | ✗ No | `number_format(..., 0, ',', '.')` | Read-only, formatStateUsing |
| 131 | `total_value` | 0 | ✗ No | `number_format(..., 0, ',', '.')` | Read-only, formatStateUsing |

**Pattern**: Disabled fields use prefix 'Rp' with `number_format(..., 0, ',', '.')` format.

---

### 9. **AssetResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 203 | `purchase_cost` | 2 | ✗ No | `number_format(..., 2, '.', '')` | Auto-calculated from PO |

**Note**: Uses `number_format($purchaseCost, 2, '.', '')` - formatted with 2 decimals and period separator (for input parsing).

---

### 10. **PurchaseInvoiceResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 513 | `price` | - | ✗ No | `->indonesianMoney()` | required, readOnly |
| 522 | `total` | - | ✗ No | `->indonesianMoney()` | required, readOnly |
| 580 | `total` | - | ✗ No | `->indonesianMoney()` | readOnly |
| 621 | `amount` | - | ✗ No | `->indonesianMoney()` | Repeater item |
| 701 | `ppn_amount` | - | ✗ No | `->indonesianMoney()` | readOnly |
| 711 | `total` | - | ✗ No | `->indonesianMoney()` | readOnly |

---

### 11. **SalesInvoiceResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 569 | `unit_price` | - | ✗ No | `->indonesianMoney()` | required |
| 590 | `total_price` | - | ✗ No | `->indonesianMoney()` | required |
| 662 | `amount` | - | ✗ No | `->indonesianMoney()` | Repeater |
| 758 | `total` | - | ✗ No | `->indonesianMoney()` | readOnly |
| 819 | `price` | - | ✗ No | `->indonesianMoney()` | readOnly |
| 828 | `total` | - | ✗ No | `->indonesianMoney()` | readOnly |

---

### 12. **ProductResource/RelationManagers/SuppliersRelationManager.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 41 | `supplier_price` | - | ✗ No | `->indonesianMoney()` | minValue(0), default 0 |
| 98 | `supplier_price` | - | ✗ No | `->indonesianMoney()` | Edit action, required |
| 115 | `supplier_price` | - | ✗ No | `->indonesianMoney()` | AttachAction form, required |

**Display format** (TextColumn, line ~88): `number_format(..., 0, ',', '.')`

---

### 13. **StockAdjustmentResource.php**

| Line | Field Name | Decimals | Currency-Aware | Pattern | Notes |
|------|-----------|----------|---|---------|-------|
| 217 | `unit_cost` | - | ✗ No | `->indonesianMoney()` | Repeater item |

---

### 14. **Other Resources** (Quick List)

| Resource | Line | Field | Pattern | Notes |
|----------|------|-------|---------|-------|
| CustomerReceiptResource | 420 | `total_payment` | `->indonesianMoney()` | readOnly |
| AccountReceivableResource | 97 | `total` | `->indonesianMoney()` | Repeater |
| AccountPayableResource | 90 | `total` | `->indonesianMoney()` | Repeater |
| VoucherRequestResource | 78 | `amount` | `->indonesianMoney()` | required |
| PaymentRequestResource | 96 | `total_amount` | `->indonesianMoney()` | readOnly |
| CashBankTransactionResource | 98 | `amount` | `->indonesianMoney()` | required |
| VendorPaymentResource | 395-640 | Multiple amounts | `->indonesianMoney()` | readOnly fields |
| InvoiceResource | 230, 262, 293, 317, 322 | `subtotal`, `amount`, `total`, `price`, `total` | `->indonesianMoney()` | Mixed readOnly |
| BillOfMaterialResource | 293 | `unit_price` | `->indonesianMoney()` | Repeater |
| QualityControlPurchaseResource | 379 | `total_inspected` | - | Numeric field |
| DepositAdjustmentResource | 101-202 | `amount`, `used_amount`, `remaining_amount` | `->indonesianMoney()` | Multiple items |

---

## Decimal Place Patterns

### **0 Decimals (Integer Format)**
Used for: `total`, `tax_nominal`, `subtotal` (calculated fields), `original_price` (master), amounts

**Pattern**:
```php
->formatStateUsing(fn($state) => number_format(..., 0, ',', '.'))
->afterStateHydrated(fn($component, $record) => $component->state(number_format(..., 0, ',', '.')))
```

### **2 Decimals**
Used for: `unit_price` (user input), `purchase_cost` (precise input)

**Pattern**:
```php
->formatStateUsing(fn($state) => number_format(..., 2, ',', '.'))
->afterStateHydrated(fn($component, $record) => $component->state(number_format(..., 2, ',', '.')))
```

### **Using ->indonesianMoney() Macro**
No explicit decimals specified - framework-handled formatting.

Used in: ProductResource, DepositResource, most recent resources.

---

## Currency-Aware Patterns

### **Method 1: Dynamic Currency Symbol via resolveCurrencySymbol()**
**Files**: OrderRequestResource

```php
->prefix(fn(Get $get) => self::resolveCurrencySymbol(
    is_numeric($get('currency_id'))
        ? (int) $get('currency_id')
        : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
))
```

### **Method 2: Currency::find()**
**Files**: PurchaseOrderResource, SaleOrderResource

```php
->prefix(function ($get) {
    $currency = Currency::find($get('currency_id'));
    return $currency ? $currency->symbol : null;
})
```

### **Method 3: Static Rupiah (Rp)**
**Files**: ProductResource, DepositResource, MaterialIssueResource, most others

```php
->prefix('Rp')
// OR no prefix at all (framework default)
```

---

## Key Findings & Inconsistencies

### ✅ Consistent Patterns
1. **Indonesian formatting**: All use `,` (comma) as thousands separator and `.` (period) as decimal separator
2. **Field readonly states**: Calculated fields consistently use `readOnly()` + `dehydrated(false)`
3. **Reactive updates**: Price-dependent fields use `reactive()` + `live(onBlur: true)` for performance

### ⚠️ Inconsistencies Found
1. **Decimal places vary**:
   - OrderRequest uses 0 for totals, 2 for unit_price
   - Most others don't specify (defer to ->indonesianMoney())
   - AssetResource uses 2 decimals with period separator

2. **Currency handling**:
   - OrderRequest: Dynamic via resolveCurrencySymbol()
   - PurchaseOrder: Dynamic via Currency::find()
   - Product, Sales, Deposit: Static 'Rp'
   - Inconsistent across similar resources

3. **Money parsing**:
   - OrderRequest: \App\Helpers\MoneyHelper::parse()
   - PurchaseOrder: HelperController::parseIndonesianMoney()
   - Others: Implicit via ->indonesianMoney()

4. **Hydration patterns**:
   - OrderRequest: Manual formatStateUsing + afterStateHydrated (verbose)
   - Most others: Rely on ->indonesianMoney() (concise)

---

## Recommendations for Standardization

1. **Adopt consistent Money formatting macro** across all resources
2. **Document decimal place rules**:
   - Unit prices: 2 decimals
   - Totals/amounts: 0 decimals (whole numbers)
3. **Standardize currency-aware formatting** using one method (preferably resolveCurrencySymbol pattern)
4. **Use MoneyHelper::parse() consistently** throughout for parsing user input
5. **Create a PriceInput component** encapsulating all these patterns

