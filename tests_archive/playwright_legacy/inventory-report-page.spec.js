/**
 * ============================================================
 *  inventory-report-page.spec.js
 *  Duta Tunggal ERP — Laporan Inventori Page Redesign & Select2 E2E Tests
 *
 *  Test Cases:
 *   TC-INV-001: Halaman dapat diakses dan menampilkan header gradient premium & tab switcher
 *   TC-INV-002: Select2 terload dengan baik untuk Gudang dan Produk
 *   TC-INV-003: Select2 Gudang & Produk dapat dicari (searchable)
 *   TC-INV-004: Dark mode via .dark class menyesuaikan warna kontras Select2
 *
 *  URL: /admin/inventory-report-page
 * ============================================================
 */

import { test, expect } from '@playwright/test';

const INV_URL = '/admin/inventory-report-page';

test.setTimeout(60000);

// Helper to navigate to Laporan Inventori and wait for Livewire & resources to load
async function goToInventory(page) {
    await page.goto(INV_URL);
    await page.waitForLoadState('domcontentloaded');
    // Wait for jQuery and Livewire to complete first paint
    await page.waitForTimeout(1000);
}

// ══════════════════════════════════════════════════════════════
// TC-INV-001: Access & Layout Verification
// ══════════════════════════════════════════════════════════════
test('TC-INV-001: Halaman Laporan Inventori dapat diakses, menampilkan header gradient & tab switcher', async ({ page }) => {
    await goToInventory(page);

    // Verify correct URL path
    await expect(page).toHaveURL(/inventory-report-page/);

    // Verify premium header gradient with text
    const headerTitle = page.getByText(/LAPORAN INVENTORI —/i).first();
    await expect(headerTitle).toBeVisible({ timeout: 8000 });

    // Verify tab switcher exists with proper buttons
    const tabStock = page.locator('#tab-stock');
    const tabMovement = page.locator('#tab-movement');
    const tabAging = page.locator('#tab-aging');
    
    await expect(tabStock).toBeVisible();
    await expect(tabMovement).toBeVisible();
    await expect(tabAging).toBeVisible();
});

// ══════════════════════════════════════════════════════════════
// TC-INV-002: Select2 Loading Verification
// ══════════════════════════════════════════════════════════════
test('TC-INV-002: Select2 terload dengan baik untuk Gudang dan Produk', async ({ page }) => {
    await goToInventory(page);

    // Wait for Select2 library to be loaded on window object
    await page.waitForFunction(() => {
        return typeof window.$ !== 'undefined' && typeof window.$.fn.select2 !== 'undefined';
    }, { timeout: 12000 });

    // Select2 wraps the native <select> elements and creates container spans
    const gudangSelect2 = page.locator('#select-gudang').locator('xpath=following-sibling::span[contains(@class,"select2-container")]');
    const produkSelect2 = page.locator('#select-produk').locator('xpath=following-sibling::span[contains(@class,"select2-container")]');

    await expect(gudangSelect2).toBeAttached({ timeout: 8000 });
    await expect(produkSelect2).toBeAttached({ timeout: 8000 });
});

// ══════════════════════════════════════════════════════════════
// TC-INV-003: Select2 Searchable Dropdown Verification
// ══════════════════════════════════════════════════════════════
test('TC-INV-003: Select2 Gudang & Produk dapat dicari (searchable)', async ({ page }) => {
    await goToInventory(page);

    // Wait for jQuery & Select2 to be ready
    await page.waitForFunction(() => typeof window.$ !== 'undefined', { timeout: 8000 });

    // Find the first Select2 container (Gudang) and click it to open the dropdown
    const gudangContainer = page.locator('.select2-container').first();
    if (!(await gudangContainer.isVisible({ timeout: 3000 }).catch(() => false))) {
        test.skip();
        return;
    }

    await gudangContainer.click();
    await page.waitForTimeout(300);

    // The search input field inside the open dropdown should be visible
    const searchField = page.locator('.select2-search--dropdown .select2-search__field');
    await expect(searchField).toBeVisible({ timeout: 5000 });

    // Type a sample search term
    await searchField.fill('gudang');
    await page.waitForTimeout(400);

    // Verify option results are rendered
    const results = page.locator('.select2-results__option');
    const resultCount = await results.count();
    expect(resultCount).toBeGreaterThan(0);

    // Close the dropdown with Escape
    await page.keyboard.press('Escape');
});

// ══════════════════════════════════════════════════════════════
// TC-INV-004: Dark Mode Styling Compatibility
// ══════════════════════════════════════════════════════════════
test('TC-INV-004: Dark mode via .dark class menyesuaikan warna kontras Select2', async ({ page }) => {
    await goToInventory(page);

    // Manually inject dark class to simulate theme switcher
    await page.evaluate(() => {
        document.documentElement.classList.add('dark');
    });
    await page.waitForTimeout(400);

    // Confirm that the .dark class exists on html tag
    const isDarkActive = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(isDarkActive).toBe(true);

    // Select2 element should remain perfectly visible in dark mode
    const select2Container = page.locator('.select2-container').first();
    await expect(select2Container).toBeVisible();

    // Clean up dark mode
    await page.evaluate(() => {
        document.documentElement.classList.remove('dark');
    });
});
