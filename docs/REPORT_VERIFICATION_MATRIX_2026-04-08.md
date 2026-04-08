# Report Verification Matrix 2026-04-08

Scope: preview and print routes audited against automated feature coverage and active-database verification as of 8 April 2026.

## Verified Live And Covered

| Route | Automated coverage | Live verification status | Notes |
| --- | --- | --- | --- |
| `reports.trial-balance.preview` | Yes | Verified row-by-row | Service rows matched direct journal aggregation with `mismatch_count = 0`. |
| `reports.stock-report.preview` | Yes | Verified row-by-row | Report rows matched direct stock snapshot plus movement grouping with `mismatch_count = 0`. |
| `reports.ageing-report.preview` | Yes | Verified row-by-row | Active dataset had zero outstanding AR/AP rows and service matched direct computation exactly. |
| `reports.hpp.preview` | Yes | Verified live | Dedicated route test added on 2026-04-08. Live HPP figures matched direct DB reconstruction including raw-material fallback logic. |
| `reports.cogm.preview` | Yes | Verified via shared HPP logic | Uses `HppReportService`; live HPP verification covers the shared core calculation. |
| `reports.alk-grafik.preview` | Yes | Verified live aggregate | Summary and ratio values matched active financial data; `null` ratios are data-driven when denominators are zero. |
| `reports.balance-sheet.preview` | Yes | Verified live aggregate | Assets, liabilities, equity, and balance difference matched active database totals. |
| `reports.profit-and-loss.preview` | Yes | Verified live aggregate | Revenue, expense, and net income matched direct journal sums. |
| `reports.financial-statement.preview` | Yes | Verified live composite | Route test passed; live scalar summaries for BS, PL, and COGM matched the active database and the shared service payload. |
| `reports.cash-flow.preview` | Yes | Verified live | April 2026 direct-method sections, opening balance, net change, and closing balance matched direct reconstruction. Snapshot produced `net_change = 50,412,870`. |
| `reports.drill-down-financial-report.preview` | Yes | Verified live | Grouped rows, total debit, total credit, and entry count matched direct journal grouping. |
| `reports.journal-consolidation.preview` | Yes | Verified live | Branch groups, COA summary, totals, and balance matched direct journal grouping. |
| `reports.profit-loss-multi-division.preview` | Yes | Verified live | Division count `20`; revenue, COGS, gross profit, opex, operating profit, other income/expense, and net profit all matched direct reconstruction. |
| `reports.inventory-report` | Yes | Verified live composite | Stock, movement, and aging payloads matched direct active-database reconstruction after aging days were normalized to day granularity. |
| `inventory-card.print` | Yes | Verified live | April 2026 preview rows, totals, labels, and period matched direct stock-movement aggregation. |

## Covered But Still Worth Deeper Live Audit

| Route | Automated coverage | Current verification status | Notes |
| --- | --- | --- | --- |

## Current Audit Evidence

- Trial Balance live verification: service rows `16`, direct rows `16`, mismatch `0`.
- Stock Report live verification: service rows `4`, direct rows `4`, mismatch `0`.
- Ageing live verification: AR rows `0`, AP rows `0`, mismatch `0`.
- HPP live verification: all major fields matched direct DB reconstruction after applying the same stock fallback rule as the service.
- Financial Statement live verification: route test passed, and scalar BS, PL, and COGM summaries matched the active database and shared service payload.
- Journal Consolidation live verification: count, debit, credit, difference, grouped branches, and COA summary all matched.
- Drill Down Financial Report live verification: count, totals, and grouped balances all matched.
- Cash Flow live verification for April 2026: reconstructed direct-method sections matched the service exactly; active source snapshot included one customer receipt and four vendor-payment journals.
- Profit-loss multi-division live verification: all 20 divisions matched, including net profit and other income/expense totals.
- Inventory report live verification: stock, movement, and aging hashes matched direct active-database reconstruction after aligning aging days to day granularity.
- Inventory-card live verification for April 2026: preview rows, totals, labels, and period matched direct stock movement aggregation exactly.

## Supporting Utility

- Reproducible snapshot script: `php scripts/report_verification_snapshot.php`