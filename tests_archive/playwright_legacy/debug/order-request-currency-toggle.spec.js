import { test, expect } from '@playwright/test';

// Quick debug E2E: select product, toggle currency to index 1 then back, compare unit_price

async function selectChoicesOption(page, labelText) {
  const wrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator(`label:has-text("${labelText}")`) }).first();
  await wrapper.waitFor({ state: 'visible', timeout: 10000 });
  const choicesInner = wrapper.locator('.choices__inner');
  await choicesInner.click();
  await page.waitForTimeout(300);
  const option = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').first();
  await option.click();
  await page.waitForTimeout(400);
}

async function selectProductInRepeater(page) {
  const productWrapper = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Product")') }).first();
  await productWrapper.waitFor({ state: 'visible', timeout: 10000 });
  const choicesInner = productWrapper.locator('.choices__inner');
  await choicesInner.click();
  await page.waitForTimeout(300);
  const option = productWrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').first();
  await option.click();
  await page.waitForTimeout(400);
}

test('order-request: currency toggle preserves unit_price', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => consoleErrors.push(err.message));

  await page.goto('/admin/order-requests/create');
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto('/admin/order-requests/create');
  }
  await page.waitForLoadState('networkidle');

  // Prefer native select if available (more reliable than Choices.js wrapper)
  // Set Cabang via JS to avoid ambiguous Choices.js wrappers
  await page.evaluate(() => {
    const sel = document.querySelector('select[id*="cabang"], select[id*="cabang_id"]');
    if (sel && sel.options && sel.options.length > 1) {
      sel.selectedIndex = 1;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await page.waitForTimeout(600);

  // Set Warehouse via JS as well
  await page.evaluate(() => {
    const sel = document.querySelector('select[id*="warehouse"], select[id*="gudang"], select[id*="gudang_id"]');
    if (sel && sel.options && sel.options.length > 1) {
      sel.selectedIndex = 1;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  // add item
  const addBtn = page.getByRole('button').filter({ hasText: /tambah item|add item/i }).first();
  if (await addBtn.isVisible()) await addBtn.click();
  await page.waitForTimeout(800);

  await selectProductInRepeater(page);
  await page.waitForTimeout(1500);

  const unitPriceInput = page.locator('input[id*="orderRequestItem"][id*="unit_price"]').first();
  await unitPriceInput.waitFor({ state: 'visible', timeout: 10000 });
  const initial = await unitPriceInput.inputValue();
  console.log('[initial unit_price] ', initial);

  // find currency select and toggle to index 1 (USD) then back to index 0
  const currencySelect = page.locator('select[id*="currency"], select[id*="mata_uang"]').first();
  if (await currencySelect.isVisible().catch(() => false)) {
    const optionCount = await currencySelect.locator('option').count();
    if (optionCount > 1) {
      // choose second option
      await currencySelect.selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(800);

      // choose first option (back)
      await currencySelect.selectOption({ index: 0 });
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(800);
    }
  }

  const after = await unitPriceInput.inputValue();
  console.log('[after toggle unit_price] ', after);

  // parse numbers
  const parse = v => Number((v || '').replace(/\./g, '').replace(/[^0-9]/g, ''));
  const nInitial = parse(initial);
  const nAfter = parse(after);

  console.log(`[parsed] initial=${nInitial} after=${nAfter}`);

  // Log any console errors
  const criticalErrors = consoleErrors.filter(e => !e.includes('favicon') && !e.includes('robots.txt') && !e.includes('404'));
  if (criticalErrors.length) console.warn('console errors:', criticalErrors);

  expect(nAfter).toBe(nInitial);
});
