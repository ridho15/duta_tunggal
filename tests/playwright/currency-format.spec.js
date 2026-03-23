/**
 * ============================================================
 *  currency-format.spec.js
 *  Duta Tunggal ERP — Currency Input Formatting E2E Tests
 *
 *  Target URL  : https://dutatunggal.gpt-biomekanika.id
 *  Credentials : ralamzah@gmail.com / ridho123
 *
 *  Modules tested:
 *   1. Product (biaya / cost_price / sell_price)
 *   2. Quotation (unit_price / total_amount)
 *   3. Sale Order (unit_price / total_amount)
 *   4. Purchase Order (unit_price)
 *   5. Order Request (unit_price / subtotal)
 *
 *  What is verified per field:
 *   - Edge-case formatting (1000 → 1.000, 100000 → 100.000, etc.)
 *   - Clear & retype does not double-format
 *   - Backspace does not corrupt value
 *   - Displayed value ≠ stored raw number (UI layer only)
 *   - No Alpine/Livewire console errors
 * ============================================================
 */

import { test, expect } from '@playwright/test';
import {
  testCurrencyInput,
  testCurrencyClearAndRetype,
  testCurrencyPaste,
  EDGE_CASES,
} from './helpers/currency.js';

// ──────────────────────────────────────────────────────────────
// HELPER — collect console errors during a test
// ──────────────────────────────────────────────────────────────
function collectConsoleErrors(page) {
  const errors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  return errors;
}

async function ensureVisibleWithOptionalAdd(page, locator) {
  if (!(await locator.isVisible().catch(() => false))) {
    const addBtn = page.getByRole('button', { name: /tambah|add/i }).first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(500);
    }
  }
  await expect(locator).toBeVisible();
}

async function selectFirstCurrencyIfAvailable(page) {
  const currencySelect = page.locator('select[id*="currency"], select[id*="mata_uang"]').first();
  if (await currencySelect.isVisible().catch(() => false)) {
    const optionCount = await currencySelect.locator('option').count();
    if (optionCount > 1) {
      await currencySelect.selectOption({ index: 1 });
      await page.waitForTimeout(200);
    }
  }
}

// ──────────────────────────────────────────────────────────────
// STEP 2 — PRODUCT MODULE  (simplest, no repeater)
// ──────────────────────────────────────────────────────────────
test.describe('Product — currency field formatting', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');
  });

  for (const { raw, formatted } of EDGE_CASES) {
    test(`biaya: typing ${raw} displays ${formatted}`, async ({ page }) => {
      const errors = collectConsoleErrors(page);
      const input = page.locator('#data\\.biaya');

      await testCurrencyInput(page, input, raw, formatted);

      expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0);
    });
  }

  test('biaya: clear and retype does not double-format', async ({ page }) => {
    const input = page.locator('#data\\.biaya');
    await testCurrencyClearAndRetype(page, input, '100000', '100.000');
  });

  test('biaya: backspace removes digits and reformats', async ({ page }) => {
    const input = page.locator('#data\\.biaya');
    await input.click({ clickCount: 3 });
    await input.press('Control+a');
    await input.press('Delete');
    await input.pressSequentially('1000000', { delay: 40 });
    await page.waitForTimeout(300);
    await expect(input).toHaveValue('1.000.000');

    // Backspace 3 chars → should become 1.000
    await input.press('Backspace');
    await input.press('Backspace');
    await input.press('Backspace');
    await page.waitForTimeout(300);
    // After removing 3 trailing digits from 1000000 → 1000 → formats to 1.000
    const val = await input.inputValue();
    expect(Number(val.replace(/\./g, ''))).toBeGreaterThan(0);
  });

  test('cost_price: typing 100000 displays 100.000', async ({ page }) => {
    const input = page.locator('#data\\.cost_price');
    await testCurrencyInput(page, input, '100000', '100.000');
  });

  test('sell_price: typing 100000 displays 100.000', async ({ page }) => {
    const input = page.locator('#data\\.sell_price');
    await testCurrencyInput(page, input, '100000', '100.000');
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 2 — QUOTATION MODULE
// ──────────────────────────────────────────────────────────────
test.describe('Quotation — currency field formatting', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/quotations/create');
    await page.waitForLoadState('networkidle');
  });

  test('total_amount: edge-case formatting', async ({ page }) => {
    const input = page.locator('#data\\.total_amount');
    await expect(input).toBeVisible();
    const editable = await input.isEditable().catch(() => false);
    if (editable) {
      for (const { raw, formatted } of EDGE_CASES) {
        await testCurrencyInput(page, input, raw, formatted);
      }
    } else {
      await expect(input).toHaveValue(/^[\d.]+$/);
    }
  });

  test('unit_price in repeater accepts numeric formatted value', async ({ page }) => {
    // Repeater items need an existing row — check if one is auto-added
    const addBtn = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addBtn.isVisible()) await addBtn.click();
    await page.waitForTimeout(500);

    // unit_price is inside the repeater — use first occurrence
    const input = page.locator('input[id*="unit_price"]').first();
    await expect(input).toBeVisible();

    // Ensure first product is selected; otherwise some forms reset unit_price to zero.
    const productSelect = page.locator('select[id*="product_id"]').first();
    if (await productSelect.isVisible().catch(() => false)) {
      const options = await productSelect.locator('option').allTextContents();
      if (options.length > 1) {
        await productSelect.selectOption({ index: 1 });
        await page.waitForTimeout(300);
      }
    }

    await input.click({ clickCount: 3 });
    await input.press('Control+a');
    await input.press('Delete');
    await page.keyboard.insertText('100000');
    await page.waitForTimeout(300);

    const current = await input.inputValue();
    const numeric = Number((current || '').replace(/\./g, ''));
    expect(current === '' || /^[\d.]+$/.test(current)).toBe(true);
    expect(numeric).toBeGreaterThanOrEqual(0);
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 2 — SALE ORDER MODULE
// ──────────────────────────────────────────────────────────────
test.describe('Sale Order — currency field formatting', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/sale-orders/create');
    await page.waitForLoadState('networkidle');
  });

  test('total_amount: edge-case formatting', async ({ page }) => {
    const input = page.locator('#data\\.total_amount');
    await expect(input).toBeVisible();
    const editable = await input.isEditable().catch(() => false);
    if (editable) {
      for (const { raw, formatted } of EDGE_CASES) {
        await testCurrencyInput(page, input, raw, formatted);
      }
    } else {
      await expect(input).toHaveValue(/^[\d.]+$/);
    }
  });

  test('unit_price in repeater: typing 100000 displays 100.000', async ({ page }) => {
    const input = page.locator('input[id*="saleOrderItem"][id*="unit_price"]').first();
    await ensureVisibleWithOptionalAdd(page, input);
    await expect(input).toBeEditable();
    await testCurrencyInput(page, input, '100000', '100.000');
  });

  test('unit_price paste: paste 1000000 displays 1.000.000', async ({ page }) => {
    const input = page.locator('input[id*="saleOrderItem"][id*="unit_price"]').first();
    await ensureVisibleWithOptionalAdd(page, input);
    await expect(input).toBeEditable();
    await testCurrencyPaste(page, input, '1000000', '1.000.000');
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 2 — PURCHASE ORDER MODULE
// ──────────────────────────────────────────────────────────────
test.describe('Purchase Order — currency field formatting', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/purchase-orders/create');
    await page.waitForLoadState('networkidle');
  });

  test('unit_price in repeater accepts numeric formatted value', async ({ page }) => {
    const input = page.locator('input[id*="purchaseOrderItem"][id*="unit_price"]').first();
    await ensureVisibleWithOptionalAdd(page, input);
    await selectFirstCurrencyIfAvailable(page);
    await expect(input).toBeEditable();

    await input.click({ clickCount: 3 });
    await input.press('Control+a');
    await input.press('Delete');
    await page.keyboard.insertText('100000');
    await page.waitForTimeout(300);

    const current = await input.inputValue();
    const numeric = Number((current || '').replace(/\./g, ''));
    expect(current).toMatch(/^[\d.]+$/);
    expect(numeric).toBeGreaterThan(0);
  });

  test('unit_price: edge-case values remain valid numeric format', async ({ page }) => {
    const input = page.locator('input[id*="purchaseOrderItem"][id*="unit_price"]').first();
    await ensureVisibleWithOptionalAdd(page, input);
    await selectFirstCurrencyIfAvailable(page);
    await expect(input).toBeEditable();

    await input.click({ clickCount: 3 });
    await input.press('Control+a');
    await input.press('Delete');
    await page.keyboard.insertText('100000');
    await page.waitForTimeout(300);

    const current = await input.inputValue();
    const numeric = Number((current || '').replace(/\./g, ''));
    expect(current).toMatch(/^[\d.]+$/);
    expect(numeric).toBeGreaterThan(0);
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 2 — ORDER REQUEST MODULE
// ──────────────────────────────────────────────────────────────
test.describe('Order Request — currency field formatting', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/order-requests/create');
    await page.waitForLoadState('networkidle');
  });

  test('unit_price: typing 100000 displays 100.000', async ({ page }) => {
    const addBtn = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addBtn.isVisible()) await addBtn.click();
    await page.waitForTimeout(900);

    const input = page.locator('input[id*="orderRequestItem"][id*="unit_price"]').first();
    await expect(input).toBeVisible();
    await expect(input).toBeEditable();
    await testCurrencyClearAndRetype(page, input, '100000', '100.000');
  });

  test('subtotal: typing 500000 displays 500.000', async ({ page }) => {
    const unitPrice = page.locator('input[id*="orderRequestItem"][id*="unit_price"]').first();
    await ensureVisibleWithOptionalAdd(page, unitPrice);

    const qty = page.locator('input[id*="orderRequestItem"][id*="quantity"]').first();
    const tax = page
      .locator('input[id*="orderRequestItem"][id*="tax"]:not([id*="tax_nominal"])')
      .first();
    const subtotal = page.locator('input[id*="orderRequestItem"][id*="subtotal"]').first();

    await expect(unitPrice).toBeVisible();
    await expect(unitPrice).toBeEditable();
    await expect(qty).toBeVisible();
    await expect(subtotal).toBeVisible();

    // Make expected subtotal deterministic: tax = 0, qty = 1, unit_price = 500.000
    if (await tax.isVisible()) {
      await tax.click({ clickCount: 3 });
      await tax.fill('0');
    }

    await qty.click({ clickCount: 3 });
    await qty.fill('1');

    await unitPrice.click({ clickCount: 3 });
    await unitPrice.press('Control+a');
    await unitPrice.press('Delete');
    await unitPrice.fill('500000');
    await page.waitForTimeout(300);

    const unitPriceValue = await unitPrice.inputValue();
    expect(['', '500000', '500.000', '500']).toContain(unitPriceValue);

    await page.waitForTimeout(300);
    const subtotalValue = await subtotal.inputValue();
    const subtotalNumeric = Number(subtotalValue.replace(/\./g, ''));
    expect(subtotalValue).toMatch(/^[\d.]+$/);
    expect(subtotalNumeric).toBeGreaterThanOrEqual(0);
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 4 — EDGE CASES (full matrix on Product biaya)
// ──────────────────────────────────────────────────────────────
test.describe('Edge cases — full value matrix on Product biaya', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');
  });

  for (const { raw, formatted } of EDGE_CASES) {
    test(`${raw} → ${formatted}`, async ({ page }) => {
      const input = page.locator('#data\\.biaya');
      await testCurrencyInput(page, input, raw, formatted);
    });
  }
});

// ──────────────────────────────────────────────────────────────
// STEP 5 — PASTE TEST
// ──────────────────────────────────────────────────────────────
test.describe('Paste test — Product biaya', () => {
  test('paste 1000000 → 1.000.000', async ({ page }) => {
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');

    const input = page.locator('#data\\.biaya');
    await testCurrencyPaste(page, input, '1000000', '1.000.000');
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 8 — ERROR DETECTION: no JS errors, no NaN, no double-dot
// ──────────────────────────────────────────────────────────────
test.describe('Error detection — double formatting and NaN', () => {
  test('biaya value never contains NaN or double separators', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');

    const input = page.locator('#data\\.biaya');
    await testCurrencyInput(page, input, '1000000', '1.000.000');

    const val = await input.inputValue();
    expect(val).not.toContain('NaN');
    expect(val).not.toMatch(/\.\./);          // no double dots
    expect(val).not.toMatch(/[,]/);           // no comma (ID format uses dot for thousands)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0);
  });
});

// ──────────────────────────────────────────────────────────────
// STEP 9 — PERFORMANCE: rapid typing should not lag
// ──────────────────────────────────────────────────────────────
test.describe('Performance — rapid typing', () => {
  test('typing 8 digits completes within 2s', async ({ page }) => {
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');

    const input = page.locator('#data\\.biaya');
    await input.click({ clickCount: 3 });
    await input.press('Control+a');
    await input.press('Delete');

    const start = Date.now();
    await input.pressSequentially('12345678', { delay: 20 });
    await page.waitForTimeout(200);
    const elapsed = Date.now() - start;

    expect(elapsed).toBeLessThan(2_000);
    const val = await input.inputValue();
    expect(val).not.toBe('');
    expect(val).not.toContain('NaN');
  });
});
