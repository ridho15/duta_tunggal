# Finance Seeder Balance Audit

This note records the finance seeder audit that was used to diagnose and fix the balance sheet imbalance caused by seeded demo data.

## What was audited

- `database/seeders/FinanceSeeder.php`
- `database/seeders/Finance/FinanceSalesSeeder.php`
- `database/seeders/Finance/FinancePurchaseSeeder.php`
- `database/seeders/Finance/FinanceCashBankTransactionSeeder.php`
- `database/seeders/Finance/FinanceHppSeeder.php`
- `database/seeders/Finance/FinanceFixedAssetSeeder.php`
- `database/seeders/Finance/FinanceMiscSeeder.php`
- `database/seeders/Finance/FinanceBankReconciliationSeeder.php`
- `database/seeders/AutoBalanceSeeder.php`

## Why the fix was needed

- The finance seeder chain depended on several COA codes that were missing from the finance chart of accounts.
- Some seed paths still assumed `cabang_id` columns existed on product tables even though the schema had changed.
- The original auto-balance step used a manual calculation, but the real balance sheet service still reported a non-zero difference.

## What changed

- Added the missing finance COAs needed by invoice, HPP, and posting flows.
- Guarded schema-dependent product/category seeding so it works when `cabang_id` is absent.
- Added a final reconciliation pass in `AutoBalanceSeeder` against `BalanceSheetService`.
- Added a regression test to verify that the seeded balance sheet is balanced.

## Audit result

- The finance seeder now completes without schema errors.
- Journal-producing seeders in the finance chain are balanced after the final reconciliation pass.
- `FinanceSeederBalanceAuditTest` passes and confirms `is_balanced = true`.

## Verification

- `php artisan test tests/Feature/FinanceSeederBalanceAuditTest.php`
