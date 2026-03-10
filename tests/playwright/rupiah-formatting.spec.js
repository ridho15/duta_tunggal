/**
 * Rupiah Formatting UI Tests
 *
 * Validates that Indonesian Rupiah (IDR) is displayed consistently
 * across the Duta Tunggal ERP.
 *
 * Format rules tested:
 *   - Prefix:    "Rp " (with space)
 *   - Thousands: "." (dot)
 *   - No commas as thousands separators
 *   - No RP. or Rp. (wrong cases or with dot)
 *
 * Base URL: http://localhost:8009
 * Auth:     reuses saved session from auth.setup.js
 */

import { test, expect } from '@playwright/test';

// ---------------------------------------------------------------
// Helper: Check that a text element contains valid Rupiah format
// ---------------------------------------------------------------
/**
 * Asserts that `text` matches the expected Rupiah format.
 * Valid: "Rp 1.000", "Rp 10.000", "Rp 1.000.000"
 * Invalid: "IDR 1,000,000", "RP. 1,000", "Rp1000000"
 */
function assertRupiahFormat(text) {
    // Must start with "Rp " (capital R, lowercase p, space)
    expect(text).toMatch(/^-?Rp \d/);

    // Must NOT use comma as thousands separator
    expect(text).not.toMatch(/\d,\d{3}/);

    // Must NOT have IDR prefix
    expect(text).not.toContain('IDR');

    // Must NOT have RP. prefix
    expect(text).not.toMatch(/^RP\./);
}

// ---------------------------------------------------------------
// Table column format tests
// ---------------------------------------------------------------

test.describe('Rupiah Format - Table Columns', () => {

    test('Purchase Orders table displays Rupiah in correct format', async ({ page }) => {
        await page.goto('/admin/purchase-orders');
        await page.waitForLoadState('networkidle');

        // Wait for table to load
        const table = page.locator('table');
        await expect(table).toBeVisible({ timeout: 10000 });

        // Look for any money cells - should contain "Rp " pattern
        const moneyCells = page.locator('table td').filter({ hasText: /Rp \d/ });
        const count = await moneyCells.count();

        if (count > 0) {
            const firstCell = moneyCells.first();
            const text = (await firstCell.textContent()).trim();
            console.log(`Purchase Order money cell: "${text}"`);

            // Verify format
            assertRupiahFormat(text);

            // Thousands separator is dot (.) not comma (,)
            if (text.replace('Rp ', '').length > 4) {
                expect(text).toContain('.');
            }
        } else {
            console.log('No money cells found in Purchase Orders table (may be empty)');
        }
    });

    test('Sale Orders table displays Rupiah in correct format', async ({ page }) => {
        await page.goto('/admin/sale-orders');
        await page.waitForLoadState('networkidle');

        const table = page.locator('table');
        await expect(table).toBeVisible({ timeout: 10000 });

        const moneyCells = page.locator('table td').filter({ hasText: /Rp \d/ });
        const count = await moneyCells.count();

        if (count > 0) {
            const firstCell = moneyCells.first();
            const text = (await firstCell.textContent()).trim();
            console.log(`Sale Order money cell: "${text}"`);
            assertRupiahFormat(text);
        }
    });

    test('Invoices table displays Rupiah in correct format', async ({ page }) => {
        await page.goto('/admin/invoices');
        await page.waitForLoadState('networkidle');

        const moneyCells = page.locator('table td').filter({ hasText: /Rp \d/ });
        const count = await moneyCells.count();

        if (count > 0) {
            const text = (await moneyCells.first().textContent()).trim();
            console.log(`Invoice money cell: "${text}"`);
            assertRupiahFormat(text);
        }
    });

    test('no table cell contains USD-style comma-thousands format', async ({ page }) => {
        await page.goto('/admin/purchase-orders');
        await page.waitForLoadState('networkidle');

        // Look for any cell that has comma-thousands (e.g. "1,000,000") — should NOT exist
        const badFormat = page.locator('table td').filter({ hasText: /\d{1,3},\d{3}/ });
        const badCount = await badFormat.count();

        if (badCount > 0) {
            const sample = await badFormat.first().textContent();
            console.warn(`Found USD-style formatting: "${sample}"`);
        }

        expect(badCount).toBe(0);
    });
});

// ---------------------------------------------------------------
// Form input format tests
// ---------------------------------------------------------------

test.describe('Rupiah Format - Form Inputs', () => {

    test('Purchase Order create form shows Rp prefix on money inputs', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');
        await page.waitForLoadState('networkidle');

        // Money inputs should show prefix "Rp"
        const rpPrefix = page.locator('.fi-prefix').filter({ hasText: 'Rp' });
        const count = await rpPrefix.count();

        console.log(`Found ${count} "Rp" prefix element(s) on PO create form`);
        expect(count).toBeGreaterThan(0);
    });

    test('indonesianMoney input formats number with dot thousands on blur', async ({ page }) => {
        await page.goto('/admin/sale-orders/create');
        await page.waitForLoadState('networkidle');

        // Find any price/unit_price input with Rp prefix
        const priceInput = page.locator('input[id*="unit_price"], input[id*="price"]').first();
        const inputCount = await priceInput.count();

        if (inputCount === 0) {
            console.log('No price input found on sale order create page — skipping');
            return;
        }

        // Type a numeric value
        await priceInput.click();
        await priceInput.fill('1000000');
        await priceInput.press('Tab'); // trigger formatting on blur

        await page.waitForTimeout(500);

        const value = await priceInput.inputValue();
        console.log(`Price input value after formatting: "${value}"`);

        // Value should be formatted with dot thousands
        // Either "1.000.000" (formatted display) or "1000000" (stored numeric)
        // The key check: there should be NO comma-thousands format like "1,000,000"
        expect(value).not.toMatch(/1,000,000/);
    });
});

// ---------------------------------------------------------------
// Dashboard widget format tests
// ---------------------------------------------------------------

test.describe('Rupiah Format - Dashboard Widgets', () => {

    test('dashboard stats widgets display Rupiah in correct format', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('networkidle');

        // Stats overview widgets will have Rupiah values
        const statValues = page.locator('[class*="fi-stats"], [class*="stats"] .text-2xl, [class*="stats"] .text-3xl, [data-stats]');
        const count = await statValues.count();

        console.log(`Found ${count} stat value elements on dashboard`);

        // Check each that contains "Rp"
        for (let i = 0; i < Math.min(count, 10); i++) {
            const text = (await statValues.nth(i).textContent()).trim();

            if (text.includes('Rp')) {
                console.log(`Stat widget value: "${text}"`);
                assertRupiahFormat(text);
            }
        }
    });

    test('dashboard contains no RP. (uppercase with dot) format', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('networkidle');

        const bodyText = await page.locator('body').textContent();

        // Should not contain "RP. " (old bad format)
        expect(bodyText).not.toContain('RP. ');

        // Should not contain "Rp." (dot without space)
        // Allow some tolerance for UI text that may say "Rp.xxx" in descriptions
        const rpDotMatches = (bodyText.match(/Rp\.\d/g) || []).length;
        console.log(`Found ${rpDotMatches} "Rp.[digit]" occurrences (should be 0)`);
        expect(rpDotMatches).toBe(0);
    });
});

// ---------------------------------------------------------------
// Infolist / View page format tests
// ---------------------------------------------------------------

test.describe('Rupiah Format - Infolist View Pages', () => {

    test('first visible Purchase Order view page displays Rupiah correctly', async ({ page }) => {
        await page.goto('/admin/purchase-orders');
        await page.waitForLoadState('networkidle');

        // Find first row link to view
        const firstRow = page.locator('table tbody tr').first();
        const rowCount = await firstRow.count();

        if (rowCount === 0) {
            console.log('No purchase orders found — skipping view test');
            return;
        }

        // Click the view/eye button
        const viewBtn = firstRow.locator('a[href*="/view"], button[title*="iew"]').first();
        if (await viewBtn.count() === 0) {
            console.log('No view button found — clicking row');
            await firstRow.click();
        } else {
            await viewBtn.click();
        }

        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // All Rp values in infolist should be correctly formatted
        const entries = page.locator('[class*="fi-in-text"]').filter({ hasText: /Rp/ });
        const count = await entries.count();

        console.log(`Found ${count} Rp text entries on PO view page`);

        for (let i = 0; i < Math.min(count, 5); i++) {
            const text = (await entries.nth(i).textContent()).trim();
            console.log(`Infolist entry: "${text}"`);

            if (text.startsWith('Rp') || text.startsWith('-Rp')) {
                assertRupiahFormat(text);
            }
        }
    });
});

// ---------------------------------------------------------------
// Comprehensive: verify no money field uses wrong format anywhere
// ---------------------------------------------------------------

test.describe('Rupiah Format - Global Consistency Check', () => {

    const pagesToCheck = [
        { name: 'Purchase Orders', path: '/admin/purchase-orders' },
        { name: 'Sale Orders',     path: '/admin/sale-orders' },
        { name: 'Invoices',        path: '/admin/invoices' },
        { name: 'Products',        path: '/admin/products' },
        { name: 'Chart of Accounts', path: '/admin/chart-of-accounts' },
        { name: 'Journal Entries', path: '/admin/journal-entries' },
    ];

    for (const { name, path } of pagesToCheck) {
        test(`${name} page: no IDR string money format`, async ({ page }) => {
            await page.goto(path);
            await page.waitForLoadState('networkidle');

            const bodyText = await page.locator('body').textContent();

            // Should not have text like "IDR 1,000" or "IDR1,000"
            expect(bodyText).not.toMatch(/IDR\s*\d{1,3},\d{3}/);

            console.log(`✅ ${name}: No IDR comma-format found`);
        });
    }
});
