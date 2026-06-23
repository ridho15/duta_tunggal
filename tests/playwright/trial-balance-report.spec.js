/**
 * ============================================================
 *  trial-balance-report.spec.js
 *  Duta Tunggal ERP — Trial Balance Report E2E Tests
 *
 *  Test Cases:
 *   TC-TB-UI-001  Page loads with correct title
 *   TC-TB-UI-002  Filter form is present (start date, end date)
 *   TC-TB-UI-003  Report does NOT render before clicking preview
 *   TC-TB-UI-004  Clicking 'Tampilkan Laporan' renders the report table
 *   TC-TB-UI-005  Header shows "PT. DUTA TUNGGAL" and "TRIAL BALANCE REPORT"
 *   TC-TB-UI-006  Period line contains the date range
 *   TC-TB-UI-007  Table has expected column headers
 *   TC-TB-UI-008  Grand total row is visible
 *   TC-TB-UI-009  Reset button hides the report
 *   TC-TB-UI-010  Print button is visible when report is shown
 *
 *  URL: /admin/trial-balance
 *  Auth: saved storage state from playwright/.auth/user.json
 * ============================================================
 */

import { test, expect } from '@playwright/test';

const TB_URL = '/admin/trial-balance';

// ──────────────────────────────────────────────────────────────
// Helper: navigate, fill dates, click 'Tampilkan Laporan' and return the popup
// ──────────────────────────────────────────────────────────────
async function openTrialBalance(page, startDate = '2025-01-01', endDate = '2025-12-31') {
    await page.goto(TB_URL);
    await page.waitForLoadState('networkidle');

    // Fill start date — input[type=date] requires YYYY-MM-DD
    const startInput = page.locator('input[id*="start_date"]').first()
        .or(page.locator('[id*="start_date"] input').first())
        .or(page.locator('input[wire\\:model*="start_date"]').first());

    if (await startInput.isVisible({ timeout: 5000 }).catch(() => false)) {
        await startInput.fill(startDate);
        await startInput.press('Tab');
        await page.waitForTimeout(200);
    }

    // Fill end date
    const endInput = page.locator('input[id*="end_date"]').first()
        .or(page.locator('[id*="end_date"] input').first())
        .or(page.locator('input[wire\\:model*="end_date"]').first());

    if (await endInput.isVisible({ timeout: 5000 }).catch(() => false)) {
        await endInput.fill(endDate);
        await endInput.press('Tab');
        await page.waitForTimeout(200);
    }

    // Click preview button and wait for the preview popup
    const previewBtn = page.getByRole('button', { name: /tampilkan laporan/i }).first()
        .or(page.getByRole('button', { name: /preview/i }).first());

    await expect(previewBtn).toBeVisible({ timeout: 10_000 });
    const popupPromise = page.waitForEvent('popup');
    await previewBtn.click();

    const popup = await popupPromise;
    await popup.waitForURL(/\/reports\/trial-balance\/preview(?:\?|$)/, { timeout: 15_000 });
    await popup.waitForLoadState('networkidle');
    await popup.bringToFront().catch(() => {});
    await popup.waitForTimeout(1_000);

    return popup;
}

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-001  Page loads
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-001 trial balance page loads without errors', async ({ page }) => {
    const res = await page.goto(TB_URL);
    await page.waitForLoadState('networkidle');

    // HTTP status must be 200
    expect(res.status()).toBe(200);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-002  Filter form present
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-002 filter form has start date and end date fields', async ({ page }) => {
    await page.goto(TB_URL);
    await page.waitForLoadState('networkidle');

    // At least one date-type input should be visible
    const dateInputs = page.locator('input[type="text"][id*="date"], input[type="date"][id*="date"]');
    const count = await dateInputs.count();
    expect(count).toBeGreaterThanOrEqual(2);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-003  Report hidden before preview click
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-003 report table is not visible before preview click', async ({ page }) => {
    await page.goto(TB_URL);
    await page.waitForLoadState('networkidle');

    const reportWrapper = page.locator('#trial-balance-report');
    await expect(reportWrapper).not.toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-004  Report renders after preview click
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-004 report table renders after clicking Tampilkan Laporan', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const reportWrapper = reportPage.locator('#trial-balance-report');
    await expect(reportWrapper).toBeVisible({ timeout: 15_000 });

    // Data table must be present
    const table = reportPage.locator('#tb-data-table');
    await expect(table).toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-005  Report header text
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-005 report header shows PT. DUTA TUNGGAL and TRIAL BALANCE REPORT', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const report = reportPage.locator('#trial-balance-report');
    const text   = await report.textContent();

    expect(text).toMatch(/PT\.\s*DUTA TUNGGAL/i);
    expect(text).toMatch(/TRIAL BALANCE REPORT/i);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-006  Period line in header
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-006 period line shows date range in header', async ({ page }) => {
    const reportPage = await openTrialBalance(page, '2025-01-01', '2025-12-31');

    const report  = reportPage.locator('#trial-balance-report');
    const text    = await report.textContent();

    // Should contain year 2025 in the period description
    expect(text).toMatch(/2025/);
    expect(text).toMatch(/January|Januari/i);
    expect(text).toMatch(/December|Desember/i);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-007  Table column headers
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-007 table has expected column headers', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const headerRow = reportPage.locator('#tb-data-table thead tr');
    const headerText = await headerRow.textContent();

    expect(headerText).toMatch(/Account\s*No/i);
    expect(headerText).toMatch(/Account\s*Name/i);
    expect(headerText).toMatch(/Normal\s*Balance/i);
    expect(headerText).toMatch(/Account\s*Type/i);
    expect(headerText).toMatch(/Beginning\s*Balance/i);
    expect(headerText).toMatch(/Debit/i);
    expect(headerText).toMatch(/Credit/i);
    expect(headerText).toMatch(/Ending\s*Balance/i);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-008  Grand total row
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-008 grand total row is visible at bottom of table', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const totalRow = reportPage.locator('#tb-grand-total-row');
    await expect(totalRow).toBeVisible({ timeout: 10_000 });

    const totalText = await totalRow.textContent();
    expect(totalText).toMatch(/TOTAL/i);
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-009  Close button is visible in standalone preview
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-009 close button is visible in standalone preview', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const report = reportPage.locator('#trial-balance-report');
    await expect(report).toBeVisible({ timeout: 10_000 });

    const closeBtn = reportPage.getByRole('button', { name: /tutup/i }).first();
    await expect(closeBtn).toBeVisible({ timeout: 5_000 });
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-010  Print button visible
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-010 print button appears after report is generated', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    const printBtn = reportPage.getByRole('button', { name: /cetak/i }).first();
    await expect(printBtn).toBeVisible({ timeout: 10_000 });
});

// ══════════════════════════════════════════════════════════════
// TC-TB-UI-011  Preview opens standalone route
// ══════════════════════════════════════════════════════════════
test('TC-TB-UI-011 preview opens the standalone trial balance route', async ({ page }) => {
    const reportPage = await openTrialBalance(page);

    expect(reportPage.url()).toContain('/reports/trial-balance/preview');
    await expect(reportPage.locator('.fi-sidebar')).toHaveCount(0);
    await expect(reportPage.locator('body')).toContainText(/Trial Balance/i);
});
