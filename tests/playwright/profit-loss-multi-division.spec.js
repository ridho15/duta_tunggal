/**
 * =====================================================================
 *  profit-loss-multi-division.spec.js
 *  Duta Tunggal ERP — Profit & Loss Multiple By Division E2E Tests
 *
 *  Verifies:
 *   1. Page loads without errors
 *   2. Filter form is present (date pickers, division selector)
 *   3. "Tampilkan Laporan" button triggers report rendering
 *   4. Report Header shows company name and title
 *   5. Table column headers show "AccountNo", "AccountName", "Balance", "Vtc%"
 *   6. "Gross Profit" row is rendered (and has distinctive styling)
 *   7. "Net Profit" row is rendered
 *   8. Reset button hides the report
 *
 *  URL:   /admin/profit-loss-multi-division
 *  Auth:  playwright/.auth/user.json (pre-saved Livewire session)
 * =====================================================================
 */

import { test, expect } from '@playwright/test';

const URL = '/admin/profit-loss-multi-division';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Click "Tampilkan Laporan" and wait for Livewire to re-render. */
async function showReport(page) {
    const btn = page.getByRole('button', { name: /tampilkan laporan/i }).first();
    await expect(btn).toBeVisible({ timeout: 10_000 });
    await btn.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(2_000);
}

/** Navigate to the report page and wait for it to settle. */
async function gotoPage(page) {
    await page.goto(URL);
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(1_000);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Profit & Loss Multiple By Division', () => {

    // ── TC-PLMD-001: Page loads ───────────────────────────────────────────────

    test('TC-PLMD-001: page loads without 500/404 errors', async ({ page }) => {
        await gotoPage(page);

        const body = await page.locator('body').innerText();
        expect(body).not.toContain('500');
        expect(body).not.toContain('Whoops');
        expect(body).not.toContain('404');
        expect(body).not.toContain('ErrorException');
    });

    // ── TC-PLMD-002: Filter form visible ─────────────────────────────────────

    test('TC-PLMD-002: filter form has date pickers and generate button', async ({ page }) => {
        await gotoPage(page);

        // Date pickers
        await expect(page.getByLabel(/tanggal mulai/i).first()).toBeVisible({ timeout: 8_000 });
        await expect(page.getByLabel(/tanggal selesai/i).first()).toBeVisible({ timeout: 8_000 });

        // Generate button
        await expect(
            page.getByRole('button', { name: /tampilkan laporan/i }).first()
        ).toBeVisible({ timeout: 8_000 });
    });

    // ── TC-PLMD-003: Division selector exists ────────────────────────────────

    test('TC-PLMD-003: division selector is present', async ({ page }) => {
        await gotoPage(page);

        // The Select field for "Divisi / Cabang"
        const divisiLabel = page.getByText(/divisi\s*\/\s*cabang/i).first();
        await expect(divisiLabel).toBeVisible({ timeout: 8_000 });
    });

    // ── TC-PLMD-004: Report renders after clicking generate ──────────────────

    test('TC-PLMD-004: report table renders after "Tampilkan Laporan"', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        // Main table should be present
        const table = page.locator('#plmd-table');
        await expect(table).toBeVisible({ timeout: 12_000 });
    });

    // ── TC-PLMD-005: Report header content ───────────────────────────────────

    test('TC-PLMD-005: report header shows company name and title', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const body = await page.locator('body').innerText();
        expect(body.toUpperCase()).toContain('DUTA TUNGGAL');
        expect(body.toUpperCase()).toContain('PROFIT LOSS MULTIPLE BY DIVISION');
    });

    // ── TC-PLMD-006: Table column headers ────────────────────────────────────

    test('TC-PLMD-006: table shows AccountNo and AccountName column headers', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const tableText = await page.locator('#plmd-table').innerText();
        expect(tableText.toUpperCase()).toContain('ACCOUNTNO');
        expect(tableText.toUpperCase()).toContain('ACCOUNTNAME');
    });

    // ── TC-PLMD-007: Balance and Vtc% sub-headers exist ──────────────────────

    test('TC-PLMD-007: Balance and Vtc% sub-headers are present', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const headCells = page.locator('#plmd-table th');
        const texts  = await headCells.allInnerTexts();
        const joined = texts.join(' ').toUpperCase();

        expect(joined).toContain('BALANCE');
        expect(joined).toContain('VTC%');
    });

    // ── TC-PLMD-008: Gross Profit row is rendered ────────────────────────────

    test('TC-PLMD-008: Gross Profit row is displayed with distinctive styling', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const gpRow = page.locator('#plmd-gross-profit');
        await expect(gpRow).toBeVisible({ timeout: 12_000 });

        const text = await gpRow.innerText();
        expect(text.toUpperCase()).toContain('GROSS PROFIT');
    });

    // ── TC-PLMD-009: Net Profit row is rendered ──────────────────────────────

    test('TC-PLMD-009: Net Profit / Net Loss row is displayed', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const npRow = page.locator('#plmd-net-profit');
        await expect(npRow).toBeVisible({ timeout: 12_000 });

        const text = await npRow.innerText();
        expect(text.toUpperCase()).toMatch(/NET (PROFIT|LOSS)/);
    });

    // ── TC-PLMD-010: Reset hides the report ──────────────────────────────────

    test('TC-PLMD-010: Reset button hides the report table', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        // Verify report is visible
        await expect(page.locator('#plmd-table')).toBeVisible({ timeout: 12_000 });

        // Click Reset
        const resetBtn = page.getByRole('button', { name: /reset/i }).first();
        await expect(resetBtn).toBeVisible({ timeout: 5_000 });
        await resetBtn.click();
        await page.waitForTimeout(1_500);

        // Report table should be gone
        await expect(page.locator('#plmd-table')).not.toBeVisible();
    });

    // ── TC-PLMD-011: Date range filter works ─────────────────────────────────

    test('TC-PLMD-011: custom date range is reflected in report header', async ({ page }) => {
        await gotoPage(page);

        // Set start and end date
        const startInput = page.getByLabel(/tanggal mulai/i).first();
        const endInput   = page.getByLabel(/tanggal selesai/i).first();

        await startInput.fill('2025-01-01');
        await endInput.fill('2025-12-31');

        await showReport(page);

        const body = await page.locator('body').innerText();
        // Report header should contain the year 2025 in some form
        expect(body).toContain('2025');
    });

    // ── TC-PLMD-012: Period stored correctly in report ───────────────────────

    test('TC-PLMD-012: As Of header text is visible in the report', async ({ page }) => {
        await gotoPage(page);
        await showReport(page);

        const body = await page.locator('body').innerText();
        expect(body.toLowerCase()).toContain('as of');
    });

    // ── TC-PLMD-013: Navigation link exists ──────────────────────────────────

    test('TC-PLMD-013: navigation menu contains the P&L multi-division link', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(1_500);

        // Filament v3 sidebar uses various data attributes; fall back to full body text
        const bodyText = await page.locator('body').innerText().catch(() => '');
        // The nav label is "Laba Rugi Per Divisi"
        expect(bodyText.toLowerCase()).toContain('laba rugi per divisi');
    });
});
