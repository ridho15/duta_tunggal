# Sales Delivery And Customer Receipt Audit And Implementation Report

Date: 2026-03-31

## Scope

Task set covered:

1. Product master default `Pembelian Belum Tertagih` uses COA `2100.100`, and inventory default uses `1140.01`.
2. Sales Order warehouse allocation should follow warehouses with available stock.
3. Warehouse Confirmation must not auto approve or auto confirm.
4. Surat Jalan should redirect back to index after create.
5. `Mark as Send` is no longer used on Surat Jalan.
6. Delivery scheduling should no longer depend on Surat Jalan filters; Surat Jalan is now only a DO document record.
7. Delivery Order completion and delivery execution should follow Delivery Schedule.
8. Customer Receipt should not require payment mode for partial payments, should keep rupiah formatting aligned with Order Request and Sales Order patterns, and should generate journal entries automatically.

## Audit Summary

### 1. Product Default COA

Previous behavior:

- Product inventory default still pointed to `1140.10`.
- Unbilled purchase default was already aligned to `2100.100` from the prior procurement pass.

Resolution:

- Product inventory default now uses `1140.01`.
- Product unbilled purchase default remains `2100.100`.

Files:

- [app/Filament/Resources/ProductResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/ProductResource.php)
- [tests/Feature/ProductAccountUiTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/ProductAccountUiTest.php)

### 2. Sales Order Warehouse Allocation

Previous behavior:

- Sales Order item allocation required manual warehouse input even when stock availability clearly indicated the best source warehouses.
- This made the user flow slower and increased the chance of selecting empty warehouses.

Resolution:

- Added automatic warehouse allocation suggestion logic based on available stock per warehouse.
- Suggestions are applied when product and quantity are chosen and no manual allocation has been entered yet.
- Allocation prioritizes warehouses with the highest available stock.

Files:

- [app/Filament/Resources/SaleOrderResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SaleOrderResource.php)

### 3. Warehouse Confirmation Manual Processing

Previous behavior:

- Sale Order approval still auto-created Warehouse Confirmation records with `confirmed` status when stock was sufficient.
- Legacy logic still attempted to auto-create Delivery Orders from confirmed SO-linked Warehouse Confirmations.

Resolution:

- Sale Order approval no longer auto-creates or auto-confirms Warehouse Confirmation as a completed warehouse decision.
- When the legacy helper is used, the created Warehouse Confirmation now starts in `request` status and requires manual processing.
- Remaining auto-create Delivery Order logic from SO-linked Warehouse Confirmation was removed.

Files:

- [app/Observers/SaleOrderObserver.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Observers/SaleOrderObserver.php)
- [app/Models/WarehouseConfirmation.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Models/WarehouseConfirmation.php)
- [tests/Feature/SaleOrderMultiWarehouseTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/SaleOrderMultiWarehouseTest.php)

### 4. Surat Jalan Simplification

Previous behavior:

- Surat Jalan still exposed `Mark as Sent` behavior.
- Create page did not redirect back to the index page after saving.

Resolution:

- Removed the Surat Jalan `Mark as Sent` action.
- Surat Jalan create page now redirects directly back to the Surat Jalan index.
- Surat Jalan remains available only as a DO document record.

Files:

- [app/Filament/Resources/SuratJalanResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SuratJalanResource.php)
- [app/Filament/Resources/SuratJalanResource/Pages/CreateSuratJalan.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SuratJalanResource/Pages/CreateSuratJalan.php)
- [tests/Feature/G05SuratJalanMarkAsSentTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/G05SuratJalanMarkAsSentTest.php)
- [tests/playwright/sales-do-sj-fixes.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/sales-do-sj-fixes.spec.js)

### 5. Delivery Order And Delivery Schedule Flow

Previous behavior:

- Delivery Order approval still depended on the presence of Surat Jalan.
- Delivery Schedule still selected and grouped shipment execution through Surat Jalan pivots.
- Delivery completion from schedule was derived indirectly through Surat Jalan.

Resolution:

- Delivery Order approval no longer requires Surat Jalan.
- Delivery Schedule now links directly to Delivery Orders using a new pivot table.
- Delivery Schedule list, form, view, export, and work-order output now use Delivery Orders directly.
- When Delivery Schedule status becomes `delivered`, related Delivery Orders are completed directly from the schedule relation.

Files:

- [app/Services/DeliveryOrderService.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderService.php)
- [app/Models/DeliveryOrder.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Models/DeliveryOrder.php)
- [app/Models/DeliverySchedule.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Models/DeliverySchedule.php)
- [app/Services/DeliveryScheduleService.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryScheduleService.php)
- [app/Filament/Resources/DeliveryScheduleResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/DeliveryScheduleResource.php)
- [app/Exports/DeliveryScheduleRecapExport.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Exports/DeliveryScheduleRecapExport.php)
- [app/Filament/Resources/DeliveryOrderResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/DeliveryOrderResource.php)
- [app/Filament/Resources/DeliveryOrderResource/Pages/ViewDeliveryOrder.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/DeliveryOrderResource/Pages/ViewDeliveryOrder.php)
- [database/migrations/2026_03_31_101500_create_delivery_schedule_delivery_orders_table.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/database/migrations/2026_03_31_101500_create_delivery_schedule_delivery_orders_table.php)
- [tests/Feature/DeliveryOrderFeatureTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryOrderFeatureTest.php)
- [tests/Feature/DeliveryScheduleTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryScheduleTest.php)
- [tests/playwright/sj-schedule-invoice-followups.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/sj-schedule-invoice-followups.spec.js)

### 6. Customer Receipt Flexibility And Auto Journal

Audit result:

- Customer Receipt journal posting was already automatic through observer + ledger posting service.
- The main issues were rigid partial/full payment assumptions and unnecessary manual journal actions.

Resolution:

- Payment method remains available in the Customer Receipt form, table filter, and detail view.
- The rigid partial/full payment choice is not required; payment allocation remains flexible through invoice selection and per-invoice receipt amounts.
- COA is always required for Customer Receipt entry.
- Manual journal generation action was removed from the view page because journal creation is automatic.
- Existing rupiah formatting improvements on total payment remain in place.

Files:

- [app/Filament/Resources/CustomerReceiptResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/CustomerReceiptResource.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/ViewCustomerReceipt.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/CustomerReceiptResource/Pages/ViewCustomerReceipt.php)
- [tests/Feature/CustomerReceiptResourceTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptResourceTest.php)
- [tests/Feature/CustomerReceiptJournalIntegrationTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptJournalIntegrationTest.php)
- [tests/playwright/customer-receipt-fixes.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/customer-receipt-fixes.spec.js)

## Test Coverage Executed

### Targeted Feature Tests

Passed:

- [tests/Feature/ProductAccountUiTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/ProductAccountUiTest.php)
- [tests/Feature/SaleOrderMultiWarehouseTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/SaleOrderMultiWarehouseTest.php)
- [tests/Feature/DeliveryOrderFeatureTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryOrderFeatureTest.php)
- [tests/Feature/G05SuratJalanMarkAsSentTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/G05SuratJalanMarkAsSentTest.php)
- [tests/Feature/DeliveryScheduleTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryScheduleTest.php)
- [tests/Feature/CustomerReceiptResourceTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptResourceTest.php)

Result:

- 17 passed, 0 failed

### Additional Flow And Journal Checks

Passed:

- [tests/Feature/G03G08WCApproveTriggersDOUpdateTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/G03G08WCApproveTriggersDOUpdateTest.php)
- [tests/Feature/CustomerReceiptJournalIntegrationTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptJournalIntegrationTest.php)

Result:

- 7 passed, 0 failed

### Broader Regression After Customer Receipt Adjustment

Passed:

- [tests/Feature/CustomerReceiptResourceTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptResourceTest.php)
- [tests/Feature/CustomerReceiptJournalIntegrationTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/CustomerReceiptJournalIntegrationTest.php)
- [tests/Feature/DeliveryOrderFeatureTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryOrderFeatureTest.php)
- [tests/Feature/DeliveryScheduleTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/DeliveryScheduleTest.php)
- [tests/Feature/SaleOrderMultiWarehouseTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/SaleOrderMultiWarehouseTest.php)
- [tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php)
- [tests/Feature/G03G08WCApproveTriggersDOUpdateTest.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/Feature/G03G08WCApproveTriggersDOUpdateTest.php)

Result:

- 22 passed, 0 failed

### Playwright UI Verification

Passed:

- [tests/playwright/sales-do-sj-fixes.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/sales-do-sj-fixes.spec.js)
- [tests/playwright/sj-schedule-invoice-followups.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/sj-schedule-invoice-followups.spec.js)
- [tests/playwright/customer-receipt-fixes.spec.js](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/tests/playwright/customer-receipt-fixes.spec.js)

Result:

- 23 passed, 0 failed
- Run with `--workers=1` for stable fixture execution.

## Residual Risks

1. Some broader legacy delivery tests may still narrate old Surat Jalan approval assumptions outside the audited subset.
2. The Customer Receipt persistence model still stores `payment_method` for backward compatibility while flexible allocation is handled separately from the old partial/full framing.
3. The new Delivery Schedule to Delivery Order pivot requires migration execution before the updated schedule UI can be used in non-test environments.

## Implementation Status

Status: Completed

All requested items were audited.
The relevant workflow changes were implemented.
Targeted PHPUnit/Pest and Playwright regressions passed.
