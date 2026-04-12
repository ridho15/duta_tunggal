# Legacy Delivery History Migration Plan

## Goal

Move legacy delivery order and surat jalan history from `inventory` and `inventory_cab` into the current ERP in a way that preserves all source data and does not delete rows from any database.

## Constraint

The current migration matrix treats legacy delivery letters and their detail rows as archive-only records. Because of that, the safest implementation is to archive the data into the ERP's legacy transaction archive layer rather than re-posting or deleting source records.

## Scope

This plan covers:

- `delivery_letters`
- `delivery_letters_detail`

It intentionally does not delete or truncate rows in:

- `inventory`
- `inventory_cab`
- the current ERP database

## Implementation Approach

1. Read legacy delivery rows from `inventory` and `inventory_cab`.
2. Normalize them through the existing legacy archive import pipeline.
3. Upsert the records into `legacy_transaction_archives` using source name, table name, and legacy ID as the stable key.
4. Run dry-run validation first, then execute only after row counts and mappings look correct.

## Command

Use the dedicated command:

- `legacy:import-delivery-history`

Recommended validation flow:

1. Dry-run with `--limit=25`.
2. Review the summary output.
3. Run with `--execute` only after the dry-run matches expectations.

## Safety Rules

- Do not delete from source databases.
- Do not truncate source tables.
- Do not use destructive cleanup against the ERP tables.
- Prefer idempotent upserts so the command can be re-run safely.

## Follow-Up

If the archive data later needs to be surfaced in active delivery screens, add a separate rehydration step instead of changing this archive import path.