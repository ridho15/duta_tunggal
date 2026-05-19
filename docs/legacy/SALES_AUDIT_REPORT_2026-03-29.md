# Sales Flow Audit Report

**Date:** 2026-03-29  
**Scope:** Quotation -> Sale Order -> Delivery / Warehouse Confirmation -> Sales Invoice -> Customer Receipt -> Lunas

## Executive Summary

The sales flow is implemented end-to-end and the main business path is present in the codebase. Quotation approval, SO creation from quotation, stock reservation, invoice generation, and customer receipt posting all exist and are wired into Filament resources, services, observers, and accounting journals.

The audit also found a few weak points that deserve follow-up:

- The payment flow still mixes legacy single-invoice behavior with the newer multi-invoice JSON allocation model.
- Some AR updates happen both in page handlers and in observers, which makes the payment path harder to reason about and easier to break.
- The SO -> delivery -> completion flow is spread across several layers, and one focused test in the current run failed around the final completion path.
- The receipt test suite exposed duplicate AR creation errors in scenarios that manually recreate AR rows, which shows the path is sensitive to idempotency assumptions.

Overall verdict: **feature complete, but not yet fully hardended**.

## Verified Flow Map

### 1. Quotation

Verified in:

- [app/Models/Quotation.php](../app/Models/Quotation.php)
- [app/Services/QuotationService.php](../app/Services/QuotationService.php)
- [app/Filament/Resources/QuotationResource.php](../app/Filament/Resources/QuotationResource.php)
- [app/Filament/Resources/QuotationResource/Pages/ViewQuotation.php](../app/Filament/Resources/QuotationResource/Pages/ViewQuotation.php)

What works:

- quotation numbering is generated in service code
- approval / rejection timestamps are stored
- quotation items are copied into SO creation UI
- branch inheritance is enforced when SO is created from quotation

### 2. Sale Order

Verified in:

- [app/Models/SaleOrder.php](../app/Models/SaleOrder.php)
- [app/Services/SalesOrderService.php](../app/Services/SalesOrderService.php)
- [app/Filament/Resources/SaleOrderResource.php](../app/Filament/Resources/SaleOrderResource.php)
- [app/Filament/Resources/SaleOrderResource/Pages/CreateSaleOrder.php](../app/Filament/Resources/SaleOrderResource/Pages/CreateSaleOrder.php)

What works:

- SO can be created directly or from quotation
- credit validation runs before create / approve
- stock confirmation uses database locking and reservations
- SO has explicit status gates before completion
- SO numbering is generated in service code

### 3. Delivery / Warehouse Confirmation

Verified in:

- [app/Services/SalesOrderService.php](../app/Services/SalesOrderService.php)
- [app/Models/SaleOrder.php](../app/Models/SaleOrder.php)
- [app/Filament/Resources/SaleOrderResource.php](../app/Filament/Resources/SaleOrderResource.php)

What works:

- warehouse confirmation is polymorphic
- multiple warehouse confirmations are supported
- delivery order completion is used as a gate for SO completion in direct shipment flow

### 4. Invoice

Verified in:

- [app/Models/Invoice.php](../app/Models/Invoice.php)
- [app/Services/InvoiceService.php](../app/Services/InvoiceService.php)
- [app/Observers/InvoiceObserver.php](../app/Observers/InvoiceObserver.php)
- [app/Filament/Resources/InvoiceResource.php](../app/Filament/Resources/InvoiceResource.php)

What works:

- invoice number generation is race-condition aware
- sales invoice creation creates AR automatically
- journal posting is triggered from observer logic
- invoice total / tax / DPP support the sales path

### 5. Customer Receipt / Payment

Verified in:

- [app/Models/CustomerReceipt.php](../app/Models/CustomerReceipt.php)
- [app/Observers/CustomerReceiptObserver.php](../app/Observers/CustomerReceiptObserver.php)
- [app/Filament/Resources/CustomerReceiptResource.php](../app/Filament/Resources/CustomerReceiptResource.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php)

What works:

- multi-invoice payment allocation is supported
- receipt items are created from JSON allocation data
- AR is reduced when payment is posted
- invoice status is updated to partially_paid / paid
- accounting journals are generated for receipt posting

## Findings

### High Severity

#### 1. Payment flow has two sources of truth

Evidence:

- [app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php)
- [app/Observers/CustomerReceiptObserver.php](../app/Observers/CustomerReceiptObserver.php)
- [app/Models/CustomerReceipt.php](../app/Models/CustomerReceipt.php)

Why it matters:

- `selected_invoices`, `invoice_receipts`, `invoice_id`, and `customerReceiptItem` all represent the same business intent in different shapes.
- The create page updates AR directly, then marks the receipt so the observer does not double count.
- The edit page rebuilds receipt items again after save.

Risk:

- future changes can easily create double posting, stale AR balances, or status drift between receipt header and receipt items.

#### 2. Sales completion path is split across too many layers

Evidence:

- [app/Filament/Resources/SaleOrderResource.php](../app/Filament/Resources/SaleOrderResource.php)
- [app/Services/SalesOrderService.php](../app/Services/SalesOrderService.php)
- [tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php](../tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php)

Why it matters:

- status visibility is enforced in the resource
- stock reservation is enforced in the service
- completion and delivery confirmation are partly driven by observers and related records

Risk:

- small changes in one layer can break the final transition from approved/confirmed to completed.

#### 3. AR creation is observer-driven and not explicitly idempotent

Evidence:

- [app/Observers/InvoiceObserver.php](../app/Observers/InvoiceObserver.php)
- [tests/Feature/CustomerReceiptFeatureTest.php](../tests/Feature/CustomerReceiptFeatureTest.php)

Why it matters:

- invoice creation automatically creates AR
- receipt tests that manually recreate AR hit unique constraint errors

Risk:

- any import, fixture, or retry path that repeats invoice-side setup can fail hard unless the AR creation contract is made explicit.

### Medium Severity

#### 4. Multi-invoice receipt logic is powerful but still brittle

Evidence:

- [app/Filament/Resources/CustomerReceiptResource.php](../app/Filament/Resources/CustomerReceiptResource.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/CreateCustomerReceipt.php)
- [app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php](../app/Filament/Resources/CustomerReceiptResource/Pages/EditCustomerReceipt.php)

Why it matters:

- there is a JSON-driven UI layer and a hidden-field compatibility layer at the same time
- some allocations are auto-corrected in page code instead of being rejected early

Risk:

- partial edits can silently normalize bad data instead of forcing the user to fix it.

#### 5. Test coverage shows a real gap at the edge of the flow

Evidence from the targeted run:

- 30 tests passed
- 4 tests failed
- failing tests:
  - [tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php](../tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php)
  - [tests/Feature/CustomerReceiptFeatureTest.php](../tests/Feature/CustomerReceiptFeatureTest.php)

Observed failures:

- final delivery completion assertion mismatch in the SO -> DO flow
- single-invoice payment allocation assertion mismatch
- duplicate AR unique-constraint failures in multi-invoice receipt scenarios

## Positive Controls

The following parts of the flow are in good shape:

- quotation approval / rejection is explicit and permissioned
- SO can inherit data from quotation
- invoice number generation is guarded against collisions
- sales invoice posting creates revenue and AR journal entries
- customer receipt posting updates AR and invoice status
- branch scope propagation exists in the sales models and invoice observer

## Audit Conclusion

The sales pipeline is structurally complete and already usable. The main remaining work is not feature discovery; it is hardening the transition points and reducing duplicated business logic.

If this were going into production as a controlled finance flow, I would treat the payment path and the final delivery/completion path as the highest risk areas.
