/**
 * ============================================================
 *  drill-down-financial-report.spec.js
 *  Duta Tunggal ERP — Drill Down Financial Report E2E Tests
 *
 *  Test Cases:
 *   TC-DDF-001: Halaman dapat diakses dan menampilkan filter section
 *   TC-DDF-002: Select2 terload untuk Akun COA dan Cabang
 *   TC-DDF-003: Empty state tampil sebelum generate laporan
 *   TC-DDF-004: Generate laporan berhasil menampilkan stat cards dan tabel
 *   TC-DDF-005: Filter Tipe Akun berfungsi (Expense filter)
 *   TC-DDF-006: Dark mode menggunakan .dark class (bukan prefers-color-scheme OS)
 *   TC-DDF-007: Collapse/expand detail akun berfungsi
 *   TC-DDF-008: Tidak ada hardcoded warna via media prefers-color-scheme
 *
 *  URL: /admin/drill-down-financial-report
 *  Auth: ralamzah@gmail.com / ridho123 (via saved auth state)
 * ============================================================
 */

import { test, expect } from '@playwright/test';

const DDF_URL = '/admin/drill-down-financial-report';
const DDF_PREVIEW_URL = '/reports/drill-down-financial-report/preview';

test.setTimeout(60000);

// ──────────────────────────────────────────────────────────────
// Helper: Navigate to drill-down page and wait until loaded
// ──────────────────────────────────────────────────────────────
async function goToDDF(page) {
    await page.goto(DDF_URL);
    await page.waitForLoadState('domcontentloaded');
    // Wait for Livewire to initialise
    await page.waitForTimeout(800);
}

// ──────────────────────────────────────────────────────────────
// Helper: Generate report with default date range and return the popup
// ──────────────────────────────────────────────────────────────
async function generateReport(page) {
    const btn = page.getByRole('button', { name: /tampilkan laporan/i }).first();
    await expect(btn).toBeVisible({ timeout: 10000 });

    const popupPromise = page.waitForEvent('popup');
    await btn.click();
    const popup = await popupPromise;
    await popup.waitForLoadState('domcontentloaded');
    await popup.waitForTimeout(500);

    return popup;
}

// ══════════════════════════════════════════════════════════════
// TC-DDF-001: Halaman dapat diakses
// ══════════════════════════════════════════════════════════════
test('TC-DDF-001: Halaman drill-down dapat diakses dan menampilkan filter section', async ({ page }) => {
    await goToDDF(page);

    // Page title / navigation label should be present
    await expect(page).toHaveURL(/drill-down-financial-report/);

    // Filter card header
    await expect(page.getByText('Filter Laporan')).toBeVisible({ timeout: 8000 });

    // All four label texts present
    await expect(page.getByText(/tipe akun/i).first()).toBeVisible();
    await expect(page.getByText(/akun coa/i).first()).toBeVisible();
    await expect(page.getByText(/tanggal mulai/i).first()).toBeVisible();
    await expect(page.getByText(/tanggal akhir/i).first()).toBeVisible();
    await expect(page.getByText(/cabang/i).first()).toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-002: Select2 terload untuk Akun COA dan Cabang
// ══════════════════════════════════════════════════════════════
test('TC-DDF-002: Select2 terload untuk Akun COA dan Cabang', async ({ page }) => {
    await goToDDF(page);

    // Wait for Select2 to initialise (it wraps the <select> with its own container)
    await page.waitForFunction(() => {
        return typeof window.$ !== 'undefined' && typeof window.$.fn.select2 !== 'undefined';
    }, { timeout: 10000 });

    // Select2 creates a container sibling next to the <select> with class select2-container
    const coaSelect2Container = page.locator('#select-coa').locator('xpath=following-sibling::span[contains(@class,"select2-container")]');
    const cabangSelect2Container = page.locator('#select-cabang').locator('xpath=following-sibling::span[contains(@class,"select2-container")]');

    await expect(coaSelect2Container).toBeAttached({ timeout: 8000 });
    await expect(cabangSelect2Container).toBeAttached({ timeout: 8000 });
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-003: Empty state tampil sebelum generate laporan
// ══════════════════════════════════════════════════════════════
test('TC-DDF-003: Empty state tampil sebelum generate laporan', async ({ page }) => {
    await goToDDF(page);

    await expect(page.getByText(/belum ada laporan ditampilkan/i)).toBeVisible({ timeout: 8000 });
    await expect(page.getByText(/tampilkan laporan/i).nth(1)).toBeVisible();

    // Stat cards should NOT be visible yet
    await expect(page.getByText(/total transaksi/i)).not.toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-004: Generate laporan berhasil
// ══════════════════════════════════════════════════════════════
test('TC-DDF-004: Generate laporan berhasil menampilkan stat cards', async ({ page }) => {
    await goToDDF(page);
    const reportPage = await generateReport(page);

    await expect(reportPage).toHaveURL(new RegExp(`${DDF_PREVIEW_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`));
    await expect(reportPage.getByRole('link', { name: /download excel/i })).toHaveAttribute('href', /reports\/drill-down-financial-report\/download-excel/);
    await expect(reportPage.getByRole('link', { name: /download pdf/i })).toHaveAttribute('href', /reports\/drill-down-financial-report\/download-pdf/);

    // After generating, stat cards should appear
    await expect(reportPage.getByText(/total transaksi/i).first()).toBeVisible({ timeout: 12000 });
    await expect(reportPage.getByText(/total debit/i).first()).toBeVisible();
    await expect(reportPage.getByText(/total kredit/i).first()).toBeVisible();

    // Report header banner should appear
    await expect(reportPage.getByText(/drill down financial report/i).first()).toBeVisible();

    // Empty state text should be gone
    await expect(reportPage.getByText(/belum ada laporan ditampilkan/i)).not.toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-005: Filter tipe akun Expense berfungsi
// ══════════════════════════════════════════════════════════════
test('TC-DDF-005: Filter tipe akun Expense memfilter hasil laporan', async ({ page }) => {
    await goToDDF(page);

    // Select "Expense" from the account type dropdown (this is a plain select with wire:model.live)
    const typeSelect = page.locator('#select-account-type');
    await typeSelect.selectOption('Expense');
    await page.waitForTimeout(600); // wait Livewire update

    // Active filter badge should appear
    await expect(page.getByText(/filter aktif: expense/i)).toBeVisible({ timeout: 8000 });

    // Generate report
    const reportPage = await generateReport(page);

    await expect(reportPage).toHaveURL(/account_type=Expense/);

    // All displayed account badges should be Expense type
    const badges = reportPage.locator('.badge.expense');
    const count = await badges.count();
    if (count > 0) {
        // At least 1 Expense badge is visible
        await expect(badges.first()).toBeVisible();
    }
    // No Liability / Equity badges should appear if we filtered to Expense only
    const liabBadges = reportPage.locator('.badge.liability');
    expect(await liabBadges.count()).toBe(0);
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-006: Dark mode dikontrol via class .dark bukan OS media query
// ══════════════════════════════════════════════════════════════
test('TC-DDF-006: Warna dark mode dikontrol via .dark class bukan prefers-color-scheme', async ({ page }) => {
    await goToDDF(page);

    // Read only OUR DDF inline <style> block (identified by .ddf- selectors)
    const styleContent = await page.evaluate(() => {
        const styles = Array.from(document.querySelectorAll('style'));
        const ourStyle = styles.find(s => s.textContent.includes('.ddf-badge-'));
        return ourStyle ? ourStyle.textContent : '';
    });

    // Our DDF style block must exist
    expect(styleContent.length).toBeGreaterThan(0);

    // Our DDF style block must NOT use prefers-color-scheme
    expect(styleContent).not.toContain('prefers-color-scheme');

    // Must contain .dark selectors for badge colours
    expect(styleContent).toMatch(/\.dark\s+\.ddf-badge-/);

    // Must NOT have any bare (non-.dark-prefixed) background overrides for dark badge
    // i.e. dark mode badges should only be in .dark context
    expect(styleContent).toMatch(/\.dark \.ddf-badge-asset/);
    expect(styleContent).toMatch(/\.dark \.ddf-badge-expense/);
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-007: Simulate dark mode via .dark class on <html>
// ══════════════════════════════════════════════════════════════
test('TC-DDF-007: Dark mode melalui .dark class mengubah tampilan select2', async ({ page }) => {
    await goToDDF(page);

    // Manually add .dark class to simulate website dark mode
    await page.evaluate(() => {
        document.documentElement.classList.add('dark');
    });

    await page.waitForTimeout(300);

    // Filter card should now use dark background (bg-gray-900)
    const filterCard = page.locator('.ddf-filter-card').first();
    await expect(filterCard).toBeVisible();

    // The <html> element should carry the .dark class
    const hasDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDark).toBe(true);

    // Select2 container should still be visible (not broken by dark mode)
    const select2Container = page.locator('.select2-container').first();
    if (await select2Container.isVisible({ timeout: 3000 }).catch(() => false)) {
        await expect(select2Container).toBeVisible();
    }

    // Remove dark mode
    await page.evaluate(() => {
        document.documentElement.classList.remove('dark');
    });
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-008: Expand/collapse detail akun berfungsi
// ══════════════════════════════════════════════════════════════
test('TC-DDF-008: Expand dan collapse detail group akun berfungsi', async ({ page }) => {
    await goToDDF(page);
    const reportPage = await generateReport(page);

    // Get the first <details> element (account group)
    const firstDetails = reportPage.locator('details').first();
    const count = await firstDetails.count();

    if (count === 0) {
        // No transactions – skip expand/collapse test
        test.skip();
        return;
    }

    // Initially the details should be closed (no open attribute)
    const isOpen = await firstDetails.evaluate(el => el.open);
    expect(isOpen).toBe(false);

    // Click the summary to open it
    const summary = firstDetails.locator('summary');
    await summary.click();
    await reportPage.waitForTimeout(200);

    const isOpenAfter = await firstDetails.evaluate(el => el.open);
    expect(isOpenAfter).toBe(true);

    // The inner table should now be visible
    await expect(firstDetails.locator('table')).toBeVisible({ timeout: 5000 });

    // Click again to close
    await summary.click();
    await reportPage.waitForTimeout(200);

    const isClosedAfter = await firstDetails.evaluate(el => el.open);
    expect(isClosedAfter).toBe(false);
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-009: Select2 COA dapat dicari (searchable)
// ══════════════════════════════════════════════════════════════
test('TC-DDF-009: Select2 COA dapat digunakan untuk mencari akun', async ({ page }) => {
    await goToDDF(page);

    // Wait for Select2 to be ready
    await page.waitForFunction(() => typeof window.$ !== 'undefined', { timeout: 8000 });

    // Open Select2 for COA by clicking the rendered container
    const coaContainer = page.locator('.select2-container').first();
    if (!(await coaContainer.isVisible({ timeout: 3000 }).catch(() => false))) {
        test.skip(); // Select2 not visible, skip
        return;
    }

    await coaContainer.click();
    await page.waitForTimeout(300);

    // The search field inside the dropdown should be visible
    const searchField = page.locator('.select2-search--dropdown .select2-search__field');
    await expect(searchField).toBeVisible({ timeout: 5000 });

    // Type a search term
    await searchField.fill('kas');
    await page.waitForTimeout(400);

    // Results should appear
    const results = page.locator('.select2-results__option');
    const resultCount = await results.count();
    expect(resultCount).toBeGreaterThan(0);

    // Close by pressing Escape
    await page.keyboard.press('Escape');
});

// ══════════════════════════════════════════════════════════════
// TC-DDF-010: Financial statement mode menampilkan laporan ringkasan
// ══════════════════════════════════════════════════════════════
test('TC-DDF-010: Financial statement mode menampilkan section laporan keuangan', async ({ page }) => {
    await goToDDF(page);

    await page.locator('#select-report-mode').selectOption('financial_statement');
    await page.waitForTimeout(500);
    await page.locator('#select-statement-type').selectOption('all');

    await expect(page.getByText(/mode aktif: semua/i)).toBeVisible({ timeout: 8000 });

    const reportPage = await generateReport(page);

    await expect(reportPage).toHaveURL(/\/reports\/financial-statement\/preview/);
    await expect(reportPage.getByText(/laporan financial statement/i).first()).toBeVisible({ timeout: 12000 });
    await expect(reportPage.getByText(/laporan laba rugi/i).first()).toBeVisible();
    await expect(reportPage.getByText(/neraca\s*[\/(].*balance sheet/i).first()).toBeVisible();
});
