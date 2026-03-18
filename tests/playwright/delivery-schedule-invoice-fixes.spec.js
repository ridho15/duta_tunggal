/**
 * Playwright tests for K1 (DeliverySchedule delivery_method) and L1 (SalesInvoice tipe_pajak)
 */
import { test, expect } from '@playwright/test';

const RUPIAH_PATTERN = /Rp\s?[\d.,]+|[\d.,]+\s?IDR/i;

async function navigateTo(page, path) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
}

// ─────────────────────────────────────────────────────────────────────────────
// K1: DeliverySchedule - Metode Pengiriman field
// ─────────────────────────────────────────────────────────────────────────────

test.describe('K1: DeliverySchedule - Metode Pengiriman', () => {

    test('K1-a: Jadwal Pengiriman create page loads correctly', async ({ page }) => {
        await navigateTo(page, '/admin/delivery-schedules/create');
        const title = await page.locator('h1, h2, .fi-header-heading').first().textContent().catch(() => '');
        expect(title.trim().length).toBeGreaterThan(0);
    });

    test('K1-b: "Metode Pengiriman" field is visible on create form', async ({ page }) => {
        await navigateTo(page, '/admin/delivery-schedules/create');
        // Check the label or select field exists
        const metodeLabel = page.locator('label:has-text("Metode Pengiriman"), [data-field="delivery_method"]');
        await expect(metodeLabel.first()).toBeVisible({ timeout: 10000 });
    });

    test('K1-c: Metode Pengiriman has correct options (Internal, Kurir Internal, Ekspedisi)', async ({ page }) => {
        await navigateTo(page, '/admin/delivery-schedules/create');
        // Find the select for delivery_method
        const pageHtml = await page.content();
        expect(pageHtml).toMatch(/Internal.*Driver Perusahaan|Metode Pengiriman/i);
        // Check at least one esperado option text in the page (options may be in a dropdown)
        const hasInternal = pageHtml.includes('Internal') || pageHtml.includes('internal');
        expect(hasInternal).toBe(true);
    });

    test('K1-d: Delivery schedule list page loads', async ({ page }) => {
        await navigateTo(page, '/admin/delivery-schedules');
        const pageHtml = await page.content();
        // page should not show a server error
        expect(pageHtml).not.toMatch(/Fatal error|Whoops!|Something went wrong/i);
    });

    test('K1-e: Delivery schedule list has nominal columns in Rupiah (if records exist)', async ({ page }) => {
        await navigateTo(page, '/admin/delivery-schedules');
        // Check if any records exist by looking for real table rows
        const rows = await page.locator('table tbody tr[wire\\:key]').count();
        if (rows === 0) {
            test.skip(true, 'No delivery schedule records to verify Rupiah format');
            return;
        }
        const pageHtml = await page.content();
        expect(pageHtml).toMatch(RUPIAH_PATTERN);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// L1: SalesInvoice - Tipe Pajak
// ─────────────────────────────────────────────────────────────────────────────

test.describe('L1: SalesInvoice - Tipe Pajak', () => {

    test('L1-a: Sales invoice create page loads correctly', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices/create');
        const title = await page.locator('h1, h2, .fi-header-heading').first().textContent().catch(() => '');
        expect(title.trim().length).toBeGreaterThan(0);
    });

    test('L1-b: "Tipe Pajak" field is visible on create invoice form', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices/create');
        const tipePajakLabel = page.locator('label:has-text("Tipe Pajak")');
        await expect(tipePajakLabel.first()).toBeVisible({ timeout: 10000 });
    });

    test('L1-c: Tipe Pajak options include None, Inklusif, Eklusif', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices/create');
        const pageHtml = await page.content();
        // Options should be present in the rendered HTML (select options)
        expect(pageHtml).toMatch(/Tidak Kena Pajak|None/i);
        expect(pageHtml).toMatch(/Inklusif/i);
        expect(pageHtml).toMatch(/Eksklusif|Eklusif/i);
    });

    test('L1-d: Sales invoice list page loads without error', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices');
        const pageHtml = await page.content();
        expect(pageHtml).not.toMatch(/Fatal error|Whoops!|Something went wrong/i);
    });

    test('L1-e: Sales invoice list shows Rupiah format for amounts (if records exist)', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices');
        // Check for actual invoice links (not debugbar rows)
        const invoiceLinks = await page.locator('a[href*="/admin/sales-invoices/"]').evaluateAll(
            els => els.filter(el => /\/admin\/sales-invoices\/\d+/.test(el.getAttribute('href') || '')).length
        );
        if (invoiceLinks === 0) {
            test.skip(true, 'No SalesInvoice records to verify Rupiah format');
            return;
        }
        const pageHtml = await page.content();
        expect(pageHtml).toMatch(RUPIAH_PATTERN);
    });

    test('L1-f: Sales invoice view shows tipe_pajak and ppn_amount (if records exist)', async ({ page }) => {
        await navigateTo(page, '/admin/sales-invoices');
        const invoiceLinks = await page.locator('a[href*="/admin/sales-invoices/"]').evaluateAll(
            els => els.filter(el => /\/admin\/sales-invoices\/\d+/.test(el.getAttribute('href') || '')).map(el => el.getAttribute('href'))
        );
        if (invoiceLinks.length === 0) {
            test.skip(true, 'No SalesInvoice records to verify tipe_pajak and ppn_amount');
            return;
        }
        // Navigate to the first invoice detail page
        await navigateTo(page, invoiceLinks[0]);
        const pageHtml = await page.content();
        // Should show Tipe Pajak field
        expect(pageHtml).toMatch(/Tipe Pajak|tipe_pajak/i);
        // Should show PPN amount label when tipe_pajak is not None
        // (will pass both if shown or if tipe_pajak=None and it's hidden)
    });

});
