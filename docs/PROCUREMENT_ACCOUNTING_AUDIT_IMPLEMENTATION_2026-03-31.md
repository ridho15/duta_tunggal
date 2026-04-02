# Procurement Accounting Audit And Implementation Report

Date: 2026-03-31

## Scope

Task set covered:

1. Purchase invoice can be created partially even when PO receipt is not yet complete, based on Purchase Receipt.
2. Purchase receipt other fees are moved above manual other fees on purchase invoice form.
3. Vendor payment invoice selection is tidied into a vertical layout.
4. Vendor payment invoice nominal and total payment calculation bug is fixed.
5. Account payable check/view nominal display is audited.
6. Product master default `Pembelian Belum Tertagih` account is changed to COA `2100.100`.

## Audit Summary

### 1. Purchase Invoice From Partial Receipt

Previous behavior:

- Purchase invoice receipt selector only accepted purchase receipts with status `completed`.
- Partial receipts existed in the domain model but were not selectable in the invoice form.

Root cause:

- Receipt query in purchase invoice form filtered only `completed` status.

Resolution:

- Receipt selector now accepts `partial` and `completed` purchase receipts.

Files:

- [app/Filament/Resources/PurchaseInvoiceResource.php](app/Filament/Resources/PurchaseInvoiceResource.php)

### 2. Purchase Receipt Fees Placement

Previous behavior:

- `Biaya Lain dari Purchase Receipt` was shown below manual `Biaya Lain - lain`.

Resolution:

- Receipt-origin fee repeater is now placed above manual other fees.
- Total recalculation remains consistent when either receipt fee rows or manual fee rows change.

Files:

- [app/Filament/Resources/PurchaseInvoiceResource.php](app/Filament/Resources/PurchaseInvoiceResource.php)

### 3. Vendor Payment Invoice Layout

Previous behavior:

- Invoice selection section rendered with a 2-column layout.
- Payment detail rows rendered horizontally in a 6-column repeater layout.

Resolution:

- Invoice selection list now uses a 1-column vertical layout.
- Payment detail repeater now stacks entries vertically for better readability.

Files:

- [app/Filament/Resources/VendorPaymentResource.php](app/Filament/Resources/VendorPaymentResource.php)

### 4. Vendor Payment Nominal Bug

Previous behavior:

- Remaining payable amount often fell back to `invoice.total` when `accountPayable` data was missing or stale.
- Because `Invoice::accountPayable()` uses `withDefault()`, the relation could look present even when no persisted AP existed.
- This could overstate payable amounts and reset remaining values back to full invoice nominal.

Resolution:

- Introduced centralized helper methods to calculate invoice remaining amounts and total selected payable amount.
- Added fallback calculation based on persisted `VendorPaymentDetail` totals when `AccountPayable` is absent.
- Treated `withDefault()` AP relations as missing unless they have a persisted key.
- Removed risky total-payment callback behavior that could rebuild payment details incorrectly.

Files:

- [app/Filament/Resources/VendorPaymentResource.php](app/Filament/Resources/VendorPaymentResource.php)
- [app/Filament/Resources/VendorPaymentResource/Pages/CreateVendorPayment.php](app/Filament/Resources/VendorPaymentResource/Pages/CreateVendorPayment.php)

### 5. Account Payable View Audit

Audit result:

- Account Payable form and view rely on the same persisted `total`, `paid`, and `remaining` fields.
- No separate or incorrect nominal source was found in the view page.
- No code change was required for this task item.

Files audited:

- [app/Filament/Resources/AccountPayableResource.php](app/Filament/Resources/AccountPayableResource.php)
- [app/Filament/Resources/AccountPayableResource/Pages/ViewAccountPayable.php](app/Filament/Resources/AccountPayableResource/Pages/ViewAccountPayable.php)

### 6. Product Master Default COA

Previous behavior:

- Default `unbilled_purchase_coa_id` in Product form still pointed to older code `2190.10`.

Resolution:

- Default now prefers COA `2100.100`.
- Test fixture setup was updated to create that COA explicitly when the test seeder does not provide it.

Files:

- [app/Filament/Resources/ProductResource.php](app/Filament/Resources/ProductResource.php)
- [tests/Feature/ProductAccountUiTest.php](tests/Feature/ProductAccountUiTest.php)

## Additional Finding From Wider Regression Suite

During broader procurement/accounting test execution, one extra accounting issue was found:

- Automatic invoice generation from purchase receipt could store invoice-wide `ppn_rate` even when accepted receipt items mixed taxable and non-taxable lines.
- Ledger posting then recalculated PPN from subtotal times rate, overstating `PPN Masukan`.

Resolution:

- Purchase receipt automatic invoice service now sets invoice-wide `ppn_rate` only when all accepted lines are taxable and share the same rate.

Files:

- [app/Services/PurchaseReceiptService.php](app/Services/PurchaseReceiptService.php)

## Test Coverage Executed

### Targeted Feature Tests

Passed:

- [tests/Feature/PurchaseInvoiceResourceTest.php](tests/Feature/PurchaseInvoiceResourceTest.php)
- [tests/Feature/VendorPaymentResourceTest.php](tests/Feature/VendorPaymentResourceTest.php)
- [tests/Feature/ProductAccountUiTest.php](tests/Feature/ProductAccountUiTest.php)

Result:

- 54 passed, 0 failed

### Wider Procurement And Accounting Regression Tests

Passed:

- [tests/Feature/CompleteProcurementAccountingFlowTest.php](tests/Feature/CompleteProcurementAccountingFlowTest.php)
- [tests/Feature/PaymentRequestVendorPaymentFlowTest.php](tests/Feature/PaymentRequestVendorPaymentFlowTest.php)
- [tests/Feature/PurchaseReceiptFlowTest.php](tests/Feature/PurchaseReceiptFlowTest.php)
- [tests/Feature/PurchaseReceiptInvoiceTaxTest.php](tests/Feature/PurchaseReceiptInvoiceTaxTest.php)
- [tests/Feature/AccountPayableFilamentFlowTest.php](tests/Feature/AccountPayableFilamentFlowTest.php)
- [tests/Feature/VendorPaymentFlowAdjustmentTest.php](tests/Feature/VendorPaymentFlowAdjustmentTest.php)

Result:

- 20 passed, 0 failed

### Playwright UI Verification

Passed:

- [tests/playwright/vendor-payment-c1-c2.spec.js](tests/playwright/vendor-payment-c1-c2.spec.js)
- [tests/playwright/vendor-payment-c3-c4.spec.js](tests/playwright/vendor-payment-c3-c4.spec.js)
- [tests/playwright/purchase-invoice-b2.spec.js](tests/playwright/purchase-invoice-b2.spec.js)

Final stable run:

- 10 passed, 0 failed
- Run with `--workers=1` for deterministic fixture handling.

## Residual Risks

1. Some legacy procurement/accounting tests and seeders still reference older COA codes such as `2100.10`, `2190.10`, or `2110` depending on context. The new product default is updated, but legacy mappings still exist elsewhere and should be normalized carefully.
2. Vendor payment UI fixture setup is sensitive to parallel Playwright execution. Serial execution remains the stable path for these specs.
3. Purchase invoice and vendor payment calculations now share clearer logic, but any future changes to AP synchronization should keep `VendorPaymentDetail` fallback behavior intact.

## Implementation Status

Status: Completed

All requested items were audited.
All required code changes were implemented where needed.
Targeted and wider regression tests passed.