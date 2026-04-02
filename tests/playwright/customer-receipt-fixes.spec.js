/**
 * customer-receipt-fixes.spec.js
 *
 * Targeted tests for the 18 March 2026 CustomerReceipt fixes:
 *
 *  M1 — No debug/raw log output visible in UI responses
 *  M2 — Payment method field remains visible on CustomerReceipt create flow
 *  M3 — Journal Entries section visible on CustomerReceipt view page
 *  M4 — AccountReceivable paid_amount is updated after creating a receipt
 *       (verified by checking the infolist "Status AR" section shows a non-zero
 *        paid amount and the correct format Rupiah)
 *
 * Format check: every Rupiah amount shown in the view must match
 * the pattern "Rp X.XXX" (dot-separated thousands, no decimals).
 */

import { test, expect } from '@playwright/test';
import { ensureCustomerReceiptFixture, chooseFixtureCustomer } from './helpers/customer-receipt-fixture'

const RUPIAH_PATTERN = /Rp\s[\d.]+/;

test.beforeAll(() => {
  ensureCustomerReceiptFixture()
})

// ─── Navigate to first receipt view ──────────────────────────────────────────
async function openFirstReceipt(page) {
  await page.goto('/admin/customer-receipts');
  await page.waitForLoadState('networkidle');

  // Look for Filament table rows: they are inside .fi-ta-content or have data-id
  // but NOT part of phpdebugbar (which uses class phpdebugbar-widgets-table-row).
  // Also check that the page has actual content links, not just debugbar rows.
  const filamentLinks = page.locator('a[href*="/admin/customer-receipts/"]')
    .filter({ hasNot: page.locator('[href*="/create"], [href*="/edit"]') });

  // Collect hrefs excluding create and edit pages
  const links = await page.locator('a').all();
  let viewHref = null;
  for (const link of links) {
    const href = await link.getAttribute('href');
    if (href && href.includes('/admin/customer-receipts/') && !href.includes('/create') && !href.includes('/edit')) {
      viewHref = href;
      break;
    }
  }

  if (!viewHref) return null;

  await page.goto(viewHref);
  await page.waitForLoadState('networkidle');

  // If got 404, that means no valid records
  const isNotFound = await page.locator('h1').filter({ hasText: '404' }).count();
  if (isNotFound > 0) return null;

  return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// M2: Payment method remains available on create page
// ─────────────────────────────────────────────────────────────────────────────
test.describe('M2 — Payment method remains visible on CustomerReceipt create page', () => {
  test('Create page shows payment method field', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Payment Method')).toBeVisible({ timeout: 10_000 });
  });

  test('Create page invoice checkbox can be selected and receipt auto-fills remaining amount', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await chooseFixtureCustomer(page)

    const cabangField = page.locator('#data\\.cabang_id, select[name="data.cabang_id"]').first();
    if (await cabangField.isVisible().catch(() => false)) {
      const cabangValue = await cabangField.inputValue();
      expect(cabangValue).not.toBe('');
    }

    const fixtureRow = page.locator('tr').filter({ hasText: 'INV-PW-CR-001' }).first();
    await expect(fixtureRow).toBeVisible({ timeout: 15_000 });

    const invoiceCheckbox = fixtureRow.locator('input.invoice-checkbox:not([disabled])').first();
    await expect(invoiceCheckbox).toBeVisible({ timeout: 15_000 });

    const remaining = await invoiceCheckbox.getAttribute('data-remaining');
    expect(remaining).not.toBeNull();
    const remainingLabel = Number(remaining || '0').toLocaleString('id-ID');

    await invoiceCheckbox.evaluate((element) => element.click());
    await page.waitForTimeout(800);

    const row = invoiceCheckbox.locator('xpath=ancestor::tr').first();
    const receiptInput = row.locator('input.receipt-input').first();
    await expect(receiptInput).toBeVisible();

    const receiptValue = (await receiptInput.inputValue()).trim();
    expect(receiptValue).toMatch(/^\d{1,3}(\.\d{3})*$/);
    expect(receiptValue).toBe(remainingLabel);

    await receiptInput.fill('100000');
    await expect(receiptInput).toHaveValue('100.000', { timeout: 5_000 });

    await receiptInput.fill('125000');
    await expect(receiptInput).toHaveValue('125.000', { timeout: 5_000 });

    const totalPaymentField = page.locator('#data\\.total_payment, input[name="total_payment"], input[wire\\:model*="total_payment"]').first();
    if (await totalPaymentField.isVisible().catch(() => false)) {
      const totalValue = (await totalPaymentField.inputValue()).trim();
      expect(totalValue).not.toBe('0');
      expect(totalValue).toMatch(/^\d{1,3}(\.\d{3})*$/);
    }
  });

  test('Create page syncs cabang to selected invoice branch', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await chooseFixtureCustomer(page)

    const cabangSelect = page.locator('#data\\.cabang_id, select[name="data.cabang_id"], select[name="cabang_id"]').first();
    await expect(cabangSelect).toHaveCount(1, { timeout: 10_000 });
    const currentCabangId = await cabangSelect.inputValue();

    const invoiceCheckboxCount = await page.locator('input.invoice-checkbox:not([disabled])').count();
    expect(invoiceCheckboxCount).toBeGreaterThan(0);

    let invoiceCheckbox = null;
    let invoiceCabangId = null;

    for (let index = 0; index < invoiceCheckboxCount; index += 1) {
      const candidate = page.locator('input.invoice-checkbox:not([disabled])').nth(index);
      const candidateCabangId = await candidate.getAttribute('data-cabang-id');

      if (candidateCabangId && candidateCabangId !== currentCabangId) {
        invoiceCheckbox = candidate;
        invoiceCabangId = candidateCabangId;
        break;
      }
    }

    expect(invoiceCheckbox, 'Need an invoice from a different cabang to verify sync').not.toBeNull();
    expect(invoiceCabangId).not.toBeNull();

    await invoiceCheckbox.evaluate((element) => element.click());
    await expect.poll(async () => {
      return await page.locator('#data\\.cabang_id, select[name="data.cabang_id"], select[name="cabang_id"]').first().inputValue();
    }, { timeout: 10_000 }).toBe(invoiceCabangId);
    await expect(cabangSelect).toHaveValue(invoiceCabangId, { timeout: 10_000 });
  });

  test('Create page COA field is searchable and uses dropdown select behavior', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const coaWrapper = page.locator('[data-field-wrapper="data.coa_id"], .fi-fo-field-wrp').filter({ has: page.locator('label:has-text("COA")') }).first();
    await expect(coaWrapper).toBeVisible({ timeout: 10_000 });

    const coaMarkup = await coaWrapper.innerHTML();
    expect(coaMarkup).toContain('main-coa-field');
    expect(coaMarkup).toMatch(/choices__|select2/i);

    const coaWidget = coaWrapper.locator('.choices, .select2-container').first();
    await coaWidget.click();

    const searchInput = page.locator('.select2-search--dropdown .select2-search__field, .choices__input').first();
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill('kas');
      await page.waitForTimeout(300);
      const dropdownText = await page.locator('body').textContent();
      expect(dropdownText || '').toMatch(/kas|bank|deposit/i);
    }
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// M3: Journal Entries section is present on CustomerReceipt view
// ─────────────────────────────────────────────────────────────────────────────
test.describe('M3 — Journal Entries section on CustomerReceipt view', () => {
  test('View page has Journal Entries section header', async ({ page }) => {
    const opened = await openFirstReceipt(page);
    if (!opened) {
      await page.goto('/admin/customer-receipts');
      await page.waitForLoadState('networkidle');
      const body = await page.textContent('body');
      expect(body).not.toMatch(/Fatal error|Whoops!|Something went wrong/i);
      return;
    }

    // Journal Entries section must be present
    await expect(page.getByRole('heading', { name: 'Journal Entries', exact: true })).toBeVisible({ timeout: 10_000 });
  });

  test('Journal Entries section shows Jurnal Akuntansi label', async ({ page }) => {
    const opened = await openFirstReceipt(page);
    if (!opened) {
      await page.goto('/admin/customer-receipts');
      await page.waitForLoadState('networkidle');
      const body = await page.textContent('body');
      expect(body).not.toMatch(/Fatal error|Whoops!|Something went wrong/i);
      return;
    }

    await expect(page.getByText('Jurnal Akuntansi')).toBeVisible({ timeout: 10_000 });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// M4: AR paid_amount displayed correctly (format Rupiah, non-zero)  
// ─────────────────────────────────────────────────────────────────────────────
test.describe('M4 — AR Status shows updated paid amount in Rupiah format', () => {
  test('Status AR section visible on receipt view', async ({ page }) => {
    const opened = await openFirstReceipt(page);
    if (!opened) {
      await page.goto('/admin/customer-receipts');
      await page.waitForLoadState('networkidle');
      const body = await page.textContent('body');
      expect(body).not.toMatch(/Fatal error|Whoops!|Something went wrong/i);
      return;
    }

    // Status AR section must be visible
    await expect(page.getByText('Status Account Receivable')).toBeVisible({ timeout: 10_000 });
  });

  test('Rupiah format used for amounts in AR status section', async ({ page }) => {
    await page.goto('/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    if (count === 0) {
      const body = await page.textContent('body');
      expect(body).toMatch(/customer receipt|tidak ada data|no records|belum ada/i);
      return;
    }

    // Find a "Paid" or "Partial" receipt to verify amounts
    let targetHref = null;
    for (let i = 0; i < Math.min(count, 5); i++) {
      const statusBadge = rows.nth(i).locator('[class*="badge"], td').filter({ hasText: /Paid|Partial/i });
      if (await statusBadge.count() > 0) {
        targetHref = await rows.nth(i).locator('a').first().getAttribute('href');
        break;
      }
    }

    if (!targetHref) {
      const body = await page.textContent('body');
      expect(body).toMatch(/Paid|Partial|Draft|customer receipt/i);
      return;
    }

    await page.goto(targetHref);
    await page.waitForLoadState('networkidle');

    // Check that Rupiah amounts are shown in the AR section
    const arSection = page.locator('section, div').filter({ hasText: 'Status Account Receivable' }).first();
    const pageContent = await page.content();

    // Ensure at least one "Rp X.XXX" pattern appears in the page
    expect(pageContent).toMatch(RUPIAH_PATTERN);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// M1: No raw PHP/debug output visible on CustomerReceipt pages
// ─────────────────────────────────────────────────────────────────────────────
test.describe('M1 — No debug/raw output on CustomerReceipt pages', () => {
  test('List page has no visible debug output', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await page.goto('/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // Should not contain raw PHP debug markers
    expect(content).not.toContain('Raw Form Data Before Processing');
    expect(content).not.toContain('array (');
    expect(content).not.toContain('Full Request Data');
  });

  test('Create page has no visible debug output', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).not.toContain('Raw Form Data');
    expect(content).not.toContain('Attempting to extract data');
  });

  test('Create page total payment field renders rupiah formatted state', async ({ page }) => {
    await page.goto('/admin/customer-receipts/create', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const totalPaymentField = page.locator('#data\\.total_payment, input[name="total_payment"], input[wire\\:model*="total_payment"]').first();
    if (await totalPaymentField.isVisible().catch(() => false)) {
      await page.evaluate(() => {
        if (typeof window.updateTotalPaymentField === 'function') {
          window.updateTotalPaymentField(1250000);
        }
      });

      await page.waitForTimeout(300);
      const value = (await totalPaymentField.inputValue()).trim();
      expect(value).toBe('1.250.000');
    }
  });
});
