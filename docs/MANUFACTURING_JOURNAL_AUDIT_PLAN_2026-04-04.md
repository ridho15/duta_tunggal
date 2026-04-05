# Manufacturing Journal Audit Plan - 2026-04-04

## Scope

- Material Issue journal (`manufacturing_issue`)
- Production in progress / WIP journal (`manufacturing_wip`)
- QC completion / finished goods journal (`manufacturing_completion`)
- Impact to balance sheet preview totals

## Verified Current Behavior

1. Material Issue completion posts debit to `1400.04` and credit to raw material inventory COA.
2. Production creation posts WIP journal to move cost into WIP inventory.
3. QC completion posts finished goods journal and keeps debit-credit balanced.
4. Financial preview tests for balance sheet still pass after manufacturing journal sync fix.

## Audit Finding

There was a timing gap in the manufacturing flow:

- If `Production` already existed and a related `MaterialIssue` was completed afterwards, the WIP journal was not refreshed automatically.
- That could leave material cost stranded in `1400.04` while QC completion credited only WIP inventory.
- Resulting risk: understated finished goods valuation and temporary production balance not clearing correctly on the balance sheet.

## Implemented Fix

- `MaterialIssueObserver` now refreshes related production WIP journals when a material issue or material return reaches `completed`, as long as the related QC is not already completed.
- Added regression coverage for:
  - late material issue refreshing an existing WIP journal
  - QC completion using refreshed WIP value after late material issue completion

## Validation Executed

- Pest: `tests/Feature/ManufacturingJournalLateIssueTest.php`
- Playwright: `tests/playwright/manufacturing-flow-e2e.spec.js`
- Financial preview tests covering balance sheet/report totals

## Recommended Ongoing Controls

1. Keep the late-material-issue regression test mandatory in CI for manufacturing/accounting changes.
2. Add a reporting query or health check for manufacturing references where total debit != total credit.
3. Add a monitoring query for MO references where `1400.04` retains balance after QC completion.
4. Before closing monthly books, reconcile:
   - net `1400.04`
   - net `1-201`
   - finished goods completion entries
   - stock movement `manufacture_in` totals

## Suggested Next Audit Extension

- Add a browser or service-level regression for the exact sequence:
  `MO start -> Production created -> Material Issue approved/completed -> Production finished -> QC completed -> Balance sheet preview`
  and assert the final open balance of `1400.04` is zero for the completed MO.