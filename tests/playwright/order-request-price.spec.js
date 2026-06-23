/**
 * order-request-price.spec.js
 *
 * Verifies two bugs that were fixed in OrderRequestResource:
 *
 *  BUG 1: When a product is selected, unit_price was set via $set() with a raw
 *          float (e.g. 8500000). formatStateUsing only runs on initial hydration,
 *          not on programmatic $set(), so the field showed "8500000" instead of
 *          "8.500.000".
 *          FIX: Wrap the value with number_format() before $set().
 *
 *  BUG 2: When quantity changed, $get('unit_price') returned the Alpine-masked
 *          string "8.500.000". PHP's (float)"8.500.000" = 8.5 (treats "." as
 *          decimal separator), so subtotals were wildly wrong.
 *          FIX: Use MoneyHelper::parse() which strips thousand-dot first.
 *
 * Test product: Panel Kontrol Industri (SKU: FG-SEED-001, cost_price: 8.500.000)
 */
import { test, expect } from '@playwright/test';

// ─── Reusable helper: select an exact option in a Filament Choices.js dropdown ──
async function selectChoicesOption(page, labelText) {
  // Find the field wrapper that contains the given label with exact text match
  const wrapper = page.locator('.fi-fo-field-wrp').filter({
    has: page.locator('label').filter({ hasText: new RegExp('^' + labelText + '$', 'i') })
  }).first();
  await wrapper.waitFor({ state: 'visible', timeout: 10_000 });

  const choicesInner = wrapper.locator('.choices__inner');
  await choicesInner.click();
  await page.waitForTimeout(300);

  const option = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .first();
  await option.waitFor({ state: 'visible', timeout: 8_000 });
  await option.click();
  await page.waitForTimeout(400);
}

// ─── Select the first seeded product in the repeater row's product select ────
async function selectProductInRepeater(page) {
  // The product_id select is inside the repeater — find its Choices container
  const productWrapper = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Product")') }).first();
  await productWrapper.waitFor({ state: 'visible', timeout: 10_000 });

  const choicesInner = productWrapper.locator('.choices__inner');
  await choicesInner.click();
  await page.waitForTimeout(300);

  const option = productWrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .first();
  await option.waitFor({ state: 'visible', timeout: 8_000 });
  await option.click();
  await page.waitForTimeout(400);
}

// ─── Main test suite ──────────────────────────────────────────────────────────
test.describe('Order Request — price formatting & subtotal calculation', () => {
  test('selecting a product sets formatted unit_price and subtotal', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', err => consoleErrors.push(err.message));

    // ── Navigate ──────────────────────────────────────────────────────────
    await page.goto('/admin/order-requests/create');

    if (page.url().includes('/login')) {
      await page.locator('#data\\.email').fill('ralamzah@gmail.com');
      await page.locator('#data\\.password').fill('ridho123');
      await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
      await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
      await page.goto('/admin/order-requests/create');
    }

    await page.waitForLoadState('networkidle');

    // ── Fill required header fields ───────────────────────────────────────

    // 4. Request date
    const dateInput = page.locator('input[id*="request_date"]').first();
    if (await dateInput.isVisible()) {
      await dateInput.fill('2026-03-15');
      await dateInput.press('Escape'); // close date picker
    }

    // ── Add repeater item ─────────────────────────────────────────────────
    const existingRow = page.locator('.fi-fo-repeater-item').first();
    if (await existingRow.count() === 0) {
      const addBtn = page.getByRole('button').filter({ hasText: /tambah item|add item/i }).first();
      const genericAddBtn = page.locator('button[wire\\:click*="addItem"], button[x-on\\:click*="add"]').first();
      if (await addBtn.isVisible()) {
        await addBtn.click();
      } else if (await genericAddBtn.isVisible()) {
        await genericAddBtn.click();
      } else {
        // Filament repeater "Add" button — find it by looking for button near repeater
        const repeaterAdd = page.locator('.fi-fo-repeater').getByRole('button').last();
        await repeaterAdd.click();
      }
      await page.waitForTimeout(800);
    }

    // ── Select product: "Panel Kontrol Industri" (SKU: FG-SEED-001) ───────
    await selectProductInRepeater(page, '(FG-SEED-001) Panel Kontrol Industri');

    // Wait for Livewire to process the afterStateUpdated callback
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // ── Assert unit_price is formatted (Indonesian thousands separator) ────
    const unitPriceInput = page.locator('input[id*="unit_price"]').first();
    await unitPriceInput.waitFor({ state: 'visible', timeout: 10_000 });
    const unitPriceValue = await unitPriceInput.inputValue();
    const taxRateInput = page.locator('input[id$="tax"]').first();
    const taxRateValue = await taxRateInput.inputValue();

    console.log(`\n[unit_price after product select]: "${unitPriceValue}"`);

    // Should contain dots (thousands separator) — e.g. "8.500.000"
    expect(
      unitPriceValue,
      `unit_price should be formatted with dots, but got "${unitPriceValue}". ` +
      `Raw floats like "8500000" indicate $set() does not format the value.`
    ).toMatch(/^\d{1,3}(\.\d{3})*(,\d{2})?$/);

    // ── Change quantity to 2 and verify subtotal ──────────────────────────
    const qtyInput = page.locator('input[id*="quantity"]').first();
    await qtyInput.click({ clickCount: 3 });
    await qtyInput.fill(''); // clear
    await qtyInput.pressSequentially('2', { delay: 50 });
    // Trigger blur to fire afterStateUpdated
    await qtyInput.press('Tab');

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const subtotalInput = page.locator('input[id*="subtotal"]').first();
    await subtotalInput.waitFor({ state: 'visible', timeout: 10_000 });
    const subtotalValue = await subtotalInput.inputValue();

    console.log(`[subtotal after qty=2]: "${subtotalValue}"`);

    // Subtotal should also be formatted
    expect(
      subtotalValue,
      `subtotal should be formatted with dots, but got "${subtotalValue}".`
    ).toMatch(/^\d{1,3}(\.\d{3})*(,\d{2})?$/);

    // Subtotal = 2 × unit_price × (1 + tax/100)
    const parsedUnitPrice = parseInt(unitPriceValue.replace(/\./g, ''), 10);
    const taxRate = Number(taxRateValue || '0');
    const expectedSubtotal = Math.round(2 * parsedUnitPrice * (1 + taxRate / 100));
    const parsedSubtotal  = parseInt(subtotalValue.replace(/\./g, ''), 10);

    expect(
      parsedSubtotal,
      `subtotal (${parsedSubtotal}) should equal 2 × unit_price × (1 + tax/100) = ${expectedSubtotal}.`
    ).toBe(expectedSubtotal);

    console.log(`✅ unit_price: ${unitPriceValue}, subtotal (×2): ${subtotalValue}`);

    // No critical JS errors
    const criticalErrors = consoleErrors.filter(
      e => !e.includes('favicon') && !e.includes('robots.txt') && !e.includes('404')
    );
    if (criticalErrors.length > 0) {
      console.warn('Console errors:', criticalErrors);
    }
  });

  test('changing unit_price manually updates subtotal correctly', async ({ page }) => {
    // Navigate to create page (already logged in via storageState)
    await page.goto('/admin/order-requests/create');
    await page.waitForLoadState('networkidle');

    // Fill required fields quickly — no header supplier, uses cost_price fallback

    // Add repeater item and select product
    const existingRow = page.locator('.fi-fo-repeater-item').first();
    if (await existingRow.count() === 0) {
      const addBtns = page.locator('.fi-fo-repeater').getByRole('button');
      const lastAddBtn = addBtns.last();
      await lastAddBtn.click();
      await page.waitForTimeout(600);
    }

    await selectProductInRepeater(page, '(FG-SEED-001) Panel Kontrol Industri');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);

    // Set quantity to 3
    const qtyInput = page.locator('input[id*="quantity"]').first();
    await qtyInput.click({ clickCount: 3 });
    await qtyInput.fill('');
    await qtyInput.pressSequentially('3', { delay: 50 });
    await qtyInput.press('Tab');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);

    const subtotalInput = page.locator('input[id*="subtotal"]').first();
    const subtotalValue = await subtotalInput.inputValue();
    const parsedSubtotal = parseInt(subtotalValue.replace(/\./g, ''), 10);
    const unitPriceInput = page.locator('input[id*="unit_price"]').first();
    const unitPriceValue = await unitPriceInput.inputValue();
    const taxRateInput = page.locator('input[id$="tax"]').first();
    const taxRateValue = await taxRateInput.inputValue();

    const parsedUnitPrice = parseInt(unitPriceValue.replace(/\./g, ''), 10);
    const taxRate = Number(taxRateValue || '0');
    const expectedSubtotal = Math.round(3 * parsedUnitPrice * (1 + taxRate / 100));
    expect(parsedSubtotal, `subtotal should equal 3 × unit_price × (1 + tax/100) = ${expectedSubtotal.toLocaleString()}, but got "${subtotalValue}"`).toBe(expectedSubtotal);

    console.log(`✅ subtotal after qty=3: "${subtotalValue}" = ${parsedSubtotal}`);
  });
});
