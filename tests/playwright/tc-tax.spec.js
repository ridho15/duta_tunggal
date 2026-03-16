/**
 * ============================================================
 *  tc-tax.spec.js
 *  Duta Tunggal ERP — Tax (PPN) Calculation E2E Tests
 *
 *  Test Cases:
 *   TC-TAX-003: PPN rate 0% menghasilkan PPN = 0
 *   TC-TAX-004: Nilai desimal — rounding ke 0 desimal (rupiah)
 *   TC-TAX-005: Large amounts — tidak ada floating point error > Rp 1 miliar
 *   TC-TAX-006: PPN Excluded dengan diskon — DPP = (price * qty) - diskon
 *
 *  Tested via: Quotation Create Form
 *  URL: /admin/quotations/create
 *  Auth: ralamzah@gmail.com / ridho123 (via saved auth state)
 * ============================================================
 */

import { test, expect } from '@playwright/test';

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────

/**
 * Parse Indonesian-formatted number string to numeric value.
 * "1.120.000.000" → 1120000000
 */
function parseIndonesian(str) {
  return parseFloat((str || '').replace(/\./g, '').replace(',', '.')) || 0;
}

/**
 * Format number to Indonesian thousands separator.
 * 1120000000 → "1.120.000.000"
 */
function formatIndonesian(num) {
  return Math.round(num).toLocaleString('id-ID');
}

/**
 * Fill a currency input field (handles indonesian money formatting).
 * Clears the field first, then types the raw digits.
 */
async function fillCurrencyInput(page, locator, rawValue) {
  await locator.click({ clickCount: 3 });
  await locator.press('Control+a');
  await locator.press('Delete');
  await locator.pressSequentially(String(rawValue), { delay: 50 });
  // Blur to trigger afterStateUpdated
  await locator.press('Tab');
}

/**
 * Fill a plain numeric input and trigger Livewire reactive update.
 */
async function fillNumericInput(page, locator, value) {
  await locator.click({ clickCount: 3 });
  await locator.fill(String(value));
  await locator.press('Tab');
}

/**
 * Select an option from a Filament Select dropdown.
 */
async function selectOption(page, fieldId, optionText) {
  // Click the select to open it
  const selectContainer = page.locator(`[wire\\:key*="${fieldId}"]`).first()
    .or(page.locator(`[id*="${fieldId}"]`).locator('..').first());
  
  // Try clicking the visible select button
  const selectInput = page.locator(`[id*="tax_type"]`).first();
  if (await selectInput.isVisible()) {
    await selectInput.selectOption({ label: optionText });
    await page.waitForTimeout(300);
    // Trigger tab to fire afterStateUpdated
    await selectInput.press('Tab');
  }
}

/**
 * Setup: navigate to quotation create and ensure the first repeater item is ready.
 */
async function goToQuotationCreate(page) {
  await page.goto('/admin/quotations/create');
  await page.waitForLoadState('networkidle');

  // Click "Add item" button if repeater starts empty
  const addBtn = page.getByRole('button', { name: /tambah|add item/i }).first();
  if (await addBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await addBtn.click();
    await page.waitForTimeout(600);
  }
}

/**
 * Fill the quotation item fields and return the total_price input.
 *
 * Filament repeater IDs pattern: data.quotationItem.{uuid}.fieldName
 * e.g. data.quotationItem.f08507b0-xxxx.unit_price
 *
 * ORDERING matters: set tax_type BEFORE setting tax rate so that when
 * the tax rate's afterStateUpdated fires it already has the correct type.
 *
 * @param {object} opts  { price, qty, discount, taxRate, taxType }
 *   taxType: 'None'|'Exclusive'|'Inclusive'
 */
async function fillQuotationItem(page, { price, qty = 1, discount = 0, taxRate = 0, taxType = 'Exclusive' }) {
  // Input selectors — Filament repeater uses UUID-based IDs (data.quotationItem.{uuid}.field)
  const unitPriceInput  = page.locator('input[id*="unit_price"]').first();
  const quantityInput   = page.locator('input[id*="quantity"]').first();
  const discountInput   = page.locator('input[id*="discount"]').first();
  // Tax rate input: id ends with ".tax" (NOT ".tax_type")
  const taxRateInput    = page.locator('input[id$=".tax"]').first();
  // Tax type: native <select> element
  const taxTypeSelect   = page.locator('select[id*="tax_type"]').first();
  const totalPriceInput = page.locator('input[id*="total_price"]').first();

  // Step 1: Fill unit price (currency formatted) and wait for Livewire
  await fillCurrencyInput(page, unitPriceInput, price);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(300);

  // Step 2: Fill quantity and wait for Livewire
  await fillNumericInput(page, quantityInput, qty);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(300);

  // Step 3: Fill discount and wait for Livewire
  await fillNumericInput(page, discountInput, discount);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(300);

  // Step 4: Select tax type FIRST (before filling tax rate) so that when
  // the tax rate's afterStateUpdated fires, tax_type is already committed.
  if (await taxTypeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
    await taxTypeSelect.selectOption(taxType);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
  }

  // Step 5: Fill tax rate AFTER tax type is committed — ensures
  // afterStateUpdated(tax) runs with the correct tax_type in Livewire state
  await fillNumericInput(page, taxRateInput, taxRate);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);

  return totalPriceInput;
}

// ──────────────────────────────────────────────────────────────
// TC-TAX-003: PPN rate 0% menghasilkan PPN = 0
// ──────────────────────────────────────────────────────────────
test('TC-TAX-003: PPN rate 0% — total equals base price (PPN = 0)', async ({ page }) => {
  await goToQuotationCreate(page);

  const price = 100000;
  const totalPriceInput = await fillQuotationItem(page, {
    price, qty: 1, discount: 0, taxRate: 0, taxType: 'Exclusive',
  });

  const totalValue = await totalPriceInput.inputValue();
  const numericTotal = parseIndonesian(totalValue);

  console.log(`TC-TAX-003: price=${price}, total_price="${totalValue}" (numeric: ${numericTotal})`);

  // With 0% tax, total must equal base price
  expect(numericTotal).toBe(price);
});

// ──────────────────────────────────────────────────────────────
// TC-TAX-004: Nilai desimal — rounding ke 0 desimal (rupiah)
// ──────────────────────────────────────────────────────────────
test('TC-TAX-004: PPN rounding — no decimal places in total', async ({ page }) => {
  await goToQuotationCreate(page);

  // 100001 * 12% = 12000.12 → rounded to 12000
  // Total = 100001 + 12000 = 112001 (exact integer, no decimal)
  const price = 100001;
  const totalPriceInput = await fillQuotationItem(page, {
    price, qty: 1, discount: 0, taxRate: 12, taxType: 'Exclusive',
  });

  const totalValue = await totalPriceInput.inputValue();
  const numericTotal = parseIndonesian(totalValue);
  const expectedTotal = 112001; // 100001 + round(100001*0.12)=12000 = 112001

  console.log(`TC-TAX-004: price=${price}, tax=12%, total_price="${totalValue}" (numeric: ${numericTotal}), expected: ${expectedTotal}`);

  // No floating point decimal in the stored value — must be integer
  expect(Number.isInteger(numericTotal)).toBe(true);
  expect(numericTotal).toBe(expectedTotal);
});

// ──────────────────────────────────────────────────────────────
// TC-TAX-005: Large amounts — tidak ada floating point error > Rp 1 miliar
// ──────────────────────────────────────────────────────────────
test('TC-TAX-005: Large amount > Rp 1 Miliar — correct PPN without floating point error', async ({ page }) => {
  await goToQuotationCreate(page);

  // 1,000,000,000 * 12% = 120,000,000 (exact, no floating point error)
  // Total = 1,120,000,000
  const price = 1000000000;
  const totalPriceInput = await fillQuotationItem(page, {
    price, qty: 1, discount: 0, taxRate: 12, taxType: 'Exclusive',
  });

  const totalValue = await totalPriceInput.inputValue();
  const numericTotal = parseIndonesian(totalValue);
  const expectedTotal = 1120000000;

  console.log(`TC-TAX-005: price=${price}, tax=12%, total_price="${totalValue}" (numeric: ${numericTotal}), expected: ${expectedTotal}`);

  // Must match exactly — no floating point drift
  expect(numericTotal).toBe(expectedTotal);
  expect(Number.isInteger(numericTotal)).toBe(true);
});

// ──────────────────────────────────────────────────────────────
// TC-TAX-006: PPN Excluded dengan diskon — DPP = (price * qty) - diskon
// ──────────────────────────────────────────────────────────────
test('TC-TAX-006: PPN Eksklusif with discount — DPP = base minus discount', async ({ page }) => {
  await goToQuotationCreate(page);

  // price=1,000,000, qty=1, discount=10%, tax=12%, type=Exclusive
  // base = 1,000,000 * 1 = 1,000,000
  // discountAmount = 1,000,000 * 10% = 100,000
  // afterDiscount (DPP) = 900,000
  // PPN = 900,000 * 12% = 108,000
  // Total = 900,000 + 108,000 = 1,008,000
  const price = 1000000;
  const totalPriceInput = await fillQuotationItem(page, {
    price, qty: 1, discount: 10, taxRate: 12, taxType: 'Exclusive',
  });

  const totalValue = await totalPriceInput.inputValue();
  const numericTotal = parseIndonesian(totalValue);
  const expectedTotal = 1008000;

  console.log(`TC-TAX-006: price=${price}, discount=10%, tax=12%, total_price="${totalValue}" (numeric: ${numericTotal}), expected: ${expectedTotal}`);

  // Total must reflect DPP-based calculation (discount applied BEFORE tax)
  expect(numericTotal).toBe(expectedTotal);
});
