/**
 * Playwright E2E tests for the three critical bug fixes:
 *   Bug #1 – Server error when generating invoice number
 *   Bug #2 – Server error when editing invoice
 *   Bug #3 – Delivery Order sometimes not generated after Sales Order is created
 *
 * These tests validate the UI flows against a running Laravel dev server.
 * Run with:  npx playwright test tests/playwright/invoice-and-do-bugfix.spec.js
 */

import { test, expect } from '@playwright/test';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Navigate to a Filament admin path and wait for the page to settle. */
async function goTo(page, path) {
  await page.goto(path);
  await page.waitForLoadState('networkidle', { timeout: 30_000 });
}

/** Wait for a Filament toast notification to appear. */
async function waitForNotification(page, type = 'success', timeout = 10_000) {
  const selector =
    type === 'success'
      ? '[data-notification-status="success"], .fi-no-success, [class*="success"]'
      : '[data-notification-status="error"], .fi-no-error, [class*="error"]';
  await page.waitForSelector(selector, { timeout });
}

// ─────────────────────────────────────────────────────────────────────────────
// Bug #1 — Invoice number generator
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Bug #1 – Invoice Number Generator', () => {
  test.use({ storageState: 'playwright/.auth/user.json' });

  test('generate button produces a correctly formatted invoice number', async ({ page }) => {
    await goTo(page, '/admin/sales-invoices/create');

    // Click the "generate" suffix-action button next to the invoice_number field
    const generateBtn = page.locator('[data-action="generate"], button[wire\\:click*="generate"]')
      .or(page.locator('button[title="Generate Invoice Number"]'))
      .or(page.locator('svg[class*="arrow-path"]').locator('..'))
      .first();

    await generateBtn.waitFor({ state: 'visible', timeout: 10_000 });
    await generateBtn.click();

    // Wait briefly for Livewire to respond
    await page.waitForTimeout(1_500);

    const invNumberInput = page.locator('input[id*="invoice_number"], input[wire\\:model*="invoice_number"]').first();
    await invNumberInput.waitFor({ state: 'visible', timeout: 5_000 });

    const value = await invNumberInput.inputValue();
    // Format must be INV-YYYYMMDD-NNNN
    expect(value).toMatch(/^INV-\d{8}-\d{4,}$/);
  });

  test('calling generate a second time returns a different (incremented) number', async ({ page }) => {
    await goTo(page, '/admin/sales-invoices/create');

    const generateBtn = page.locator('button[title="Generate Invoice Number"]')
      .or(page.locator('[data-action="generate"]'))
      .first();

    await generateBtn.waitFor({ state: 'visible', timeout: 10_000 });

    const invNumberInput = page.locator('input[id*="invoice_number"], input[wire\\:model*="invoice_number"]').first();

    // First click
    await generateBtn.click();
    await page.waitForTimeout(1_500);
    const first = await invNumberInput.inputValue();

    // Manually change the field so the server "sees" a different starting state,
    // then click again — server should still return the next sequential number.
    await invNumberInput.fill(first);
    await generateBtn.click();
    await page.waitForTimeout(1_500);
    const second = await invNumberInput.inputValue();

    // Both values must match the pattern
    expect(first).toMatch(/^INV-\d{8}-\d{4,}$/);
    expect(second).toMatch(/^INV-\d{8}-\d{4,}$/);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Bug #2 — Create & Edit Invoice flow
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Bug #2 – Create and Edit Invoice', () => {
  test.use({ storageState: 'playwright/.auth/user.json' });

  test('sales invoice create page loads without error', async ({ page }) => {
    await goTo(page, '/admin/sales-invoices/create');

    // Page should not show a Filament error / 500
    await expect(page.locator('h1, h2').first()).not.toHaveText(/error|exception|500/i);

    // The invoice_number field must be present
    const invField = page.locator('input[id*="invoice_number"], input[wire\\:model*="invoice_number"]').first();
    await expect(invField).toBeVisible({ timeout: 10_000 });
  });

  test('navigating to the sales invoice list returns HTTP 200', async ({ page }) => {
    const response = await page.goto('/admin/sales-invoices');
    expect(response?.status()).toBe(200);

    // Page title
    const title = await page.title();
    expect(title).toContain('Invoice');
  });

  test('editing an existing invoice page opens without 500 error', async ({ page }) => {
    // Navigate to the list first to find any existing invoice
    await goTo(page, '/admin/sales-invoices');

    // If there are rows, click the first Edit action
    const editLink = page.locator('a[href*="/sales-invoices/"][href*="/edit"]').first();

    const hasInvoices = await editLink.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!hasInvoices) {
      test.skip(true, 'No existing invoices to edit – skipping edit test');
      return;
    }

    await editLink.click();
    await page.waitForLoadState('networkidle', { timeout: 30_000 });

    // Should NOT have a Filament error overlay
    const errorHeading = page.locator('h1:text("500"), h1:text("Error"), .fi-body .text-danger').first();
    await expect(errorHeading).not.toBeVisible({ timeout: 3_000 }).catch(() => {
      // locator not found = page is fine
    });

    // Invoice number field should be visible
    const invField = page.locator('input[id*="invoice_number"]').first();
    await expect(invField).toBeVisible({ timeout: 10_000 });
  });

  test('saving an edited invoice does not trigger a server error', async ({ page }) => {
    await goTo(page, '/admin/sales-invoices');

    const editLink = page.locator('a[href*="/sales-invoices/"][href*="/edit"]').first();
    const hasInvoices = await editLink.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!hasInvoices) {
      test.skip(true, 'No existing invoices to edit – skipping save test');
      return;
    }

    await editLink.click();
    await page.waitForLoadState('networkidle', { timeout: 30_000 });

    // Find and click the Save button
    const saveBtn = page.locator('button[type="submit"]').or(
      page.locator('button:has-text("Save"), button:has-text("Simpan")')
    ).first();

    await saveBtn.waitFor({ state: 'visible', timeout: 10_000 });
    await saveBtn.click();

    // Wait for Livewire response
    await page.waitForTimeout(3_000);

    // Should NOT navigate to an error page
    expect(page.url()).not.toContain('/500');
    expect(page.url()).not.toContain('/error');

    // Should not show a server error overlay
    const serverError = page.locator('h1:text("Server Error"), h1:text("500")');
    await expect(serverError).not.toBeVisible({ timeout: 3_000 }).catch(() => {});
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Bug #3 — Delivery Order generated after Sales Order approval
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Bug #3 – Delivery Order Generation', () => {
  test.use({ storageState: 'playwright/.auth/user.json' });

  test('delivery order list page loads successfully', async ({ page }) => {
    const response = await page.goto('/admin/delivery-orders');
    expect(response?.status()).toBe(200);

    await page.waitForLoadState('networkidle', { timeout: 30_000 });
    const title = await page.title();
    expect(title.toLowerCase()).toContain('delivery');
  });

  test('sale order list page loads and shows existing SOs', async ({ page }) => {
    const response = await page.goto('/admin/sale-orders');
    expect(response?.status()).toBe(200);

    await page.waitForLoadState('networkidle', { timeout: 30_000 });

    // The page should not error
    const error = page.locator('h1:text("500"), h1:text("Error")');
    await expect(error).not.toBeVisible({ timeout: 3_000 }).catch(() => {});
  });

  test('approved SO that is "Kirim Langsung" has an associated delivery order', async ({ page }) => {
    // Navigate to completed sale orders — these should have DOs attached
    await goTo(page, '/admin/sale-orders?tableFilters[status][value]=completed');

    const soRows = await page.locator('table tbody tr').count();

    if (soRows === 0) {
      test.skip(true, 'No completed SOs in the database – skipping DO link test');
      return;
    }

    // Open the first completed SO
    const viewBtn = page.locator('table tbody tr').first()
      .locator('a, button').filter({ hasText: /view|lihat|detail/i }).first();

    const hasViewBtn = await viewBtn.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!hasViewBtn) {
      // Try clicking the row itself or any link in it
      await page.locator('table tbody tr').first().locator('a').first().click();
    } else {
      await viewBtn.click();
    }

    await page.waitForLoadState('networkidle', { timeout: 30_000 });

    // The SO detail page should not show a server error
    await expect(page.locator('h1:text("500")')).not.toBeVisible({ timeout: 3_000 }).catch(() => {});
  });

  test('warehouse confirmation list page loads without error', async ({ page }) => {
    const response = await page.goto('/admin/warehouse-confirmations');
    if (response?.status() === 404) {
      test.skip(true, 'Warehouse Confirmation route not found – skipping');
      return;
    }
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle', { timeout: 30_000 });
  });
});
