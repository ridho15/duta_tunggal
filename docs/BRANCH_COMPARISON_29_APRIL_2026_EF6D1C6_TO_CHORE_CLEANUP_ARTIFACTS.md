# Branch Comparison: `29-april-2026` (`ef6d1c6`) vs `chore/cleanup-artifacts`

Date: 2026-05-15

## Purpose

This document compares the state of the repository at commit `ef6d1c6` on branch `29-april-2026` with the current `chore/cleanup-artifacts` branch.

The goal is to capture the meaningful changes that were added after the baseline commit, especially the currency-precision work, the UI resource cleanup, the new tests, and the documentation/archive reorganization.

## High-Level Summary

Compared with `ef6d1c6`, the current branch is substantially expanded and refined:

- Core currency handling is centralized in `app/Support/CurrencyConversionResolver.php`.
- Order Request, Sale Order, Invoice, and Purchase Order UI flows now use high-precision conversion paths where needed.
- New backend and Playwright coverage was added for currency precision and workflow consistency.
- Legacy reports and oversized documentation were archived to `docs/legacy/`.
- Several supporting models, observers, services, factories, seeders, and migrations were updated to keep currency and tax behavior consistent.

At the diff level, the branch movement is large: roughly 176 files changed, with about 26,120 insertions and 2,707 deletions.

## What Was Already Present at `ef6d1c6`

The baseline commit already contained the main ERP application structure and the existing Filament resources, services, models, migrations, and tests that the later work built on.

In practical terms, `ef6d1c6` was the starting point for the application feature set, while `chore/cleanup-artifacts` represents the branch after currency precision fixes, archive cleanup, and verification hardening.

## Major Additions Since `ef6d1c6`

### 1. Currency precision engine

The most important functional change is the new centralized currency conversion behavior in `app/Support/CurrencyConversionResolver.php`.

What changed:

- Conversions now use `bcmath` for intermediate math.
- UI-facing paths can request non-rounded intermediate values using a `$round = false` flag.
- The previous precision loss in IDR → USD → IDR roundtrips is reduced substantially.

Why it matters:

- It prevents visible drift when users switch currencies in live forms.
- It keeps storage behavior predictable by still allowing final rounding only at the save boundary.

### 2. Order Request currency flow

`app/Filament/Resources/OrderRequestResource.php` was updated so item-level currency switching uses the centralized resolver instead of inline math.

Notable effects:

- `original_price` and `unit_price` are recalculated with high-precision intermediate values.
- Item totals (`total`, `total_cost`, `subtotal`, `tax_nominal`) are recalculated after currency changes.
- Supplier label and product display paths also use the shared resolver.

### 3. Sale Order currency flow

`app/Filament/Resources/SaleOrderResource.php` now routes currency switching through the resolver.

Notable effects:

- UI currency changes no longer rely on ad hoc rounding math.
- The change keeps form-side values consistent while preserving the existing transaction model.

### 4. Invoice and Purchase Order UI cleanup

`app/Filament/Resources/InvoiceResource.php`, `app/Filament/Resources/PurchaseOrderResource.php`, and their related page classes were updated so UI-side totals and rate lookups use the shared resolver or a single rate source.

Notable effects:

- Direct `Currency.to_rupiah` usage in UI math was reduced.
- Total calculations now follow the same conversion path across the related screens.
- Purchase Order invoice-related totals and edit/view page calculations are aligned with the resolver-based approach.

### 5. Test coverage

The current branch adds or expands several test layers:

- `tests/Feature/CurrencyConversionPrecisionTest.php`
- `tests/Feature/OrderRequestCurrencyConversionTest.php`
- `tests/Feature/SaleOrderCurrencyLifecycleTest.php`
- `tests/Feature/InvoiceCurrencyAuditTest.php`
- `tests/Feature/PurchaseOrderMixedCurrencyTest.php`
- Playwright coverage for currency precision and form behavior

These tests verify:

- precision on conversion roundtrips,
- UI currency switching,
- mixed-currency purchase order behavior,
- invoice normalization and audit behavior,
- and end-to-end form stability with Playwright.

### 6. Documentation cleanup and archival

The branch also includes a large documentation reshuffle:

- audit and legacy reports were moved to `docs/legacy/`,
- a high-level archive summary was added,
- currency verification plans and summaries were added,
- the main improvement plan was condensed and supplemented with supporting notes.

This keeps `docs/` readable while preserving the full historical material in an archive location.

## What Was Removed Or Consolidated

Compared to `ef6d1c6`, the current branch removes or consolidates several forms of repetition:

- Inline conversion formulas were replaced by resolver calls in the UI paths that mattered most.
- Some ad hoc debug or temporary scripts were removed from the root.
- Legacy documentation was relocated out of the top-level `docs/` surface.

This makes the branch easier to reason about and reduces the risk of future drift between screens.

## Verification Status

The latest sweep after the updates passed:

- `CurrencyConversionPrecisionTest` — passed
- `OrderRequestCurrencyConversionTest` — passed
- `SaleOrderCurrencyLifecycleTest` — passed
- `InvoiceCurrencyAuditTest` — passed
- `PurchaseOrderMixedCurrencyTest` — passed
- `tests/playwright/order-request-currency-precision.spec.mjs` — passed

Current sweep total:

- 27 backend assertions passed
- 5 Playwright scenarios passed

## Practical Impact

The current branch is not just a cleanup branch. It is a cleanup branch plus a functional correctness pass for money handling.

In short:

- `ef6d1c6` = baseline application state
- `chore/cleanup-artifacts` = baseline + precision fixes + resource cleanup + expanded verification + archive organization

## Notes For Future Work

- If new resources introduce UI-side currency math, they should follow the resolver pattern used here.
- If you need a smaller executive summary, this file can be condensed into a one-page release note.
- If you want a more formal change log, this comparison can be split into “functional changes” and “documentation/archive changes.”
