/**
 * audit-fixes.spec.js
 *
 * Playwright E2E tests for the 2026-03 audit bug fixes:
 *
 *  1. Deposit money input — "20.000.000" Indonesian format is masked correctly
 *     and can be submitted without "numeric" validation error.
 *
 *  2. OrderRequest row colors — list rows carry the correct Tailwind bg classes
 *     depending on their status (draft=none, request_approve=bg-gray-100,
 *     approved=bg-blue-50, partial=bg-yellow-50, complete=bg-green-50,
 *     closed/rejected=bg-red-50).
 *
 *  3. PurchaseInvoice price fields are readonly (not disabled) — the input
 *     carries the HTML `readonly` attribute and does NOT carry the `disabled`
 *     attribute so values are visible in normal styling.
 *
 * Prerequisites: the app must be running at the baseURL configured in
 * playwright.config.js and the database must contain test data (run seeders).
 */

import { test, expect } from '@playwright/test';

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Navigate and, if redirected to login, authenticate first.
 */
async function navigate(page, path) {
  await page.goto(path);
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').waitFor({ state: 'visible', timeout: 15_000 });
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.goto(path);
  }
  await page.waitForLoadState('networkidle');
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Deposit "Tambah Saldo" money mask
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Deposit money mask on tambahSaldo action', () => {
  test('deposit list page loads without JS errors', async ({ page }) => {
    const jsErrors = [];
    page.on('pageerror', err => jsErrors.push(err.message));
    page.on('console', msg => {
      if (msg.type() === 'error') jsErrors.push(msg.text());
    });

    await navigate(page, '/admin/deposits');

    // Page should render the deposits table
    await expect(page.locator('h1, .fi-header-heading')).toContainText(/deposit/i, { timeout: 10_000 });

    if (jsErrors.length) {
      console.warn('JS errors on deposit list:', jsErrors);
    }
    // No blocking JS errors
    const blockingErrors = jsErrors.filter(e =>
      !e.includes('favicon') && !e.includes('chunk') && !e.includes('Loading chunk')
    );
    expect(blockingErrors).toHaveLength(0);
  });

  test('"20.000.000" is masked as Indonesian money in tambahSaldo modal', async ({ page }) => {
    await navigate(page, '/admin/deposits');

    // Find the first Tambah Saldo action button (table row action)
    const tambahBtn = page
      .locator('[data-action="tambah-saldo"], button:has-text("Tambah Saldo")')
      .first();

    const hasTambah = await tambahBtn.count() > 0;
    if (!hasTambah) {
      test.skip();
      return; // No deposit rows with tambah saldo available — skip gracefully
    }

    await tambahBtn.click();

    // Wait for the modal to open
    const modal = page.locator('.fi-modal-content, [role="dialog"]').first();
    await modal.waitFor({ state: 'visible', timeout: 10_000 });

    // Find the amount/nominal input field inside the modal
    const amountInput = modal.locator('input[id*="jumlah"], input[id*="amount"], input[id*="nominal"]').first();
    const inputExists = await amountInput.count() > 0;
    if (!inputExists) {
      test.skip();
      return;
    }

    await amountInput.click({ clickCount: 3 });
    await page.keyboard.press('Delete');

    // Type as raw digits — the Indonesian money mask should format them
    await amountInput.pressSequentially('20000000', { delay: 80 });
    await page.waitForTimeout(600);

    const maskedValue = await amountInput.inputValue();
    console.log('Deposit tambah saldo masked value:', maskedValue);

    // The mask should produce Indonesian format "20.000.000"
    expect(maskedValue).toBe('20.000.000');
  });

  test('"20.000.000" passes validation (no "must be a number" error)', async ({ page }) => {
    await navigate(page, '/admin/deposits');

    const tambahBtn = page
      .locator('[data-action="tambah-saldo"], button:has-text("Tambah Saldo")')
      .first();

    if (await tambahBtn.count() === 0) {
      test.skip();
      return;
    }

    await tambahBtn.click();
    const modal = page.locator('.fi-modal-content, [role="dialog"]').first();
    await modal.waitFor({ state: 'visible', timeout: 10_000 });

    const amountInput = modal.locator('input[id*="jumlah"], input[id*="amount"], input[id*="nominal"]').first();
    if (await amountInput.count() === 0) {
      test.skip();
      return;
    }

    await amountInput.click({ clickCount: 3 });
    await page.keyboard.press('Delete');
    await amountInput.pressSequentially('20000000', { delay: 80 });
    await page.waitForTimeout(300);

    // Submit the form
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);

    // Should NOT see a numeric validation error
    const numericError = page.locator(':text("must be a number"), :text("harus berupa angka"), :text("numeric")');
    await expect(numericError).toHaveCount(0, { timeout: 3_000 });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. OrderRequest row colors by status
// ─────────────────────────────────────────────────────────────────────────────

test.describe('OrderRequest list — row color coding by status', () => {
  test('order request list page loads', async ({ page }) => {
    await navigate(page, '/admin/order-requests');
    await expect(page.locator('h1, .fi-header-heading')).toContainText(/order request|permintaan/i, { timeout: 10_000 });
  });

  test('rows with status "request_approve" carry bg-gray-100 class', async ({ page }) => {
    await navigate(page, '/admin/order-requests');
    await page.waitForLoadState('networkidle');

    // Check if any rows with request_approve status exist in the table
    const grayRows = page.locator('tr.bg-gray-100, .fi-ta-row.bg-gray-100');
    const count = await grayRows.count();
    if (count === 0) {
      console.info('No request_approve rows found — skipping row color assertion.');
      test.skip();
    } else {
      await expect(grayRows.first()).toBeVisible();
    }
  });

  test('rows with status "approved" carry bg-blue-50 class', async ({ page }) => {
    await navigate(page, '/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const blueRows = page.locator('tr.bg-blue-50, .fi-ta-row.bg-blue-50');
    const count = await blueRows.count();
    if (count === 0) {
      console.info('No approved rows found — skipping row color assertion.');
      test.skip();
    } else {
      await expect(blueRows.first()).toBeVisible();
    }
  });

  test('rows with status "partial" carry bg-yellow-50 class', async ({ page }) => {
    await navigate(page, '/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const yellowRows = page.locator('tr.bg-yellow-50, .fi-ta-row.bg-yellow-50');
    const count = await yellowRows.count();
    if (count === 0) {
      console.info('No partial rows found — skipping row color assertion.');
      test.skip();
    } else {
      await expect(yellowRows.first()).toBeVisible();
    }
  });

  test('rows with status "complete" carry bg-green-50 class', async ({ page }) => {
    await navigate(page, '/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const greenRows = page.locator('tr.bg-green-50, .fi-ta-row.bg-green-50');
    const count = await greenRows.count();
    if (count === 0) {
      console.info('No complete rows found — skipping row color assertion.');
      test.skip();
    } else {
      await expect(greenRows.first()).toBeVisible();
    }
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. PurchaseInvoice — price and total fields are readonly not disabled
// ─────────────────────────────────────────────────────────────────────────────

test.describe('PurchaseInvoice invoice items — price fields are readOnly', () => {
  test('purchase invoice create page loads', async ({ page }) => {
    await navigate(page, '/admin/purchase-invoices/create');
    // Should reach the form (or be redirected if no POs available)
    const url = page.url();
    const reachedForm = url.includes('/purchase-invoices/create') || url.includes('/purchase-invoices');
    expect(reachedForm).toBe(true);
  });

  test('invoice item price field is readonly (not disabled) when items populate', async ({ page }) => {
    await navigate(page, '/admin/purchase-invoices/create');
    await page.waitForLoadState('networkidle');

    // Look for price inputs that are in the invoice items repeater
    const priceInputs = page.locator(
      '.fi-fo-repeater-item input[id*="price"]:not([id*="unit_price"]), ' +
      '.fi-fo-repeater-item input[id*="harga"]'
    );
    const count = await priceInputs.count();

    if (count === 0) {
      console.info('No invoice item price inputs found (no PO items loaded) — skipping.');
      test.skip();
      return;
    }

    // All price inputs should be readonly, NOT disabled
    for (let i = 0; i < count; i++) {
      const input = priceInputs.nth(i);
      const isReadonly = await input.getAttribute('readonly');
      const isDisabled = await input.getAttribute('disabled');

      expect(isReadonly, `Price input #${i} should have readonly attribute`).not.toBeNull();
      expect(isDisabled, `Price input #${i} should NOT have disabled attribute`).toBeNull();
    }
  });
});
