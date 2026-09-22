import { test, expect } from '@playwright/test';

async function selectViaJs(page, selector) {
  await page.evaluate((sel) => {
    const element = document.querySelector(sel);
    if (element && element.options && element.options.length > 1) {
      element.selectedIndex = 1;
      element.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, selector);
  await page.waitForTimeout(700);
}

async function selectRepeaterOptionByText(page, fieldLabel, exactText) {
  const wrapper = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator(`label:has-text("${fieldLabel}")`) }).first();
  await wrapper.waitFor({ state: 'visible', timeout: 10000 });
  await wrapper.locator('.choices__inner').click();
  await page.waitForTimeout(350);

  const visibleChoices = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)');
  const match = visibleChoices.filter({ hasText: exactText }).first();
  if (await match.count()) {
    await match.click();
  } else {
    await visibleChoices.first().click();
  }
  await page.waitForTimeout(700);
}

async function selectItemCurrency(page, exactText) {
  const wrapper = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Mata Uang Item")') }).first();
  await wrapper.waitFor({ state: 'visible', timeout: 10000 });
  await wrapper.locator('.choices__inner').click();
  await page.waitForTimeout(350);

  const visibleChoices = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)');
  const match = visibleChoices.filter({ hasText: exactText }).first();
  await match.waitFor({ state: 'visible', timeout: 8000 });
  await match.click();
  await page.waitForTimeout(1000);
}

test('order request current screenshot condition: product autum 1 currency toggle', async ({ page }) => {
  const logs = [];
  page.on('console', msg => {
    if (msg.type() === 'error' || msg.type() === 'warning') logs.push(`[${msg.type()}] ${msg.text()}`);
  });
  page.on('pageerror', err => logs.push(`[pageerror] ${err.message}`));

  await page.goto('/admin/order-requests/create');
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto('/admin/order-requests/create');
  }

  await page.waitForLoadState('networkidle');

  await selectViaJs(page, 'select[id*="cabang"], select[id*="cabang_id"]');
  await selectViaJs(page, 'select[id*="warehouse"], select[id*="gudang"], select[id*="gudang_id"]');

  const addBtn = page.getByRole('button').filter({ hasText: /tambah item|add item/i }).first();
  if (await addBtn.isVisible()) {
    await addBtn.click();
  } else {
    await page.locator('.fi-fo-repeater').getByRole('button').last().click();
  }
  await page.waitForTimeout(800);

  await selectRepeaterOptionByText(page, 'Product', '(SKU-001) Produk autem 1');

  const supplierWrapper = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Supplier")') }).first();
  if (await supplierWrapper.isVisible().catch(() => false)) {
    await supplierWrapper.locator('.choices__inner').click();
    await page.waitForTimeout(350);
    const supplierChoices = supplierWrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)');
    const supplierMatch = supplierChoices.filter({ hasText: 'PJ Pangestu' }).first();
    if (await supplierMatch.count()) {
      await supplierMatch.click();
      await page.waitForTimeout(800);
    }
  }

  const originalAsli = page.locator('input[id*="original_price"]').first();
  const originalOverride = page.locator('input[id*="unit_price"]').first();
  const itemCurrency = page.locator('.fi-fo-repeater-item').first()
    .locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Mata Uang Item")') }).first();

  await expect(originalOverride).toBeVisible();
  const beforeCurrency = await itemCurrency.locator('.choices__inner').textContent().catch(() => '');
  const beforeAsli = await originalAsli.inputValue().catch(() => '');
  const beforeOverride = await originalOverride.inputValue().catch(() => '');
  console.log('[before] currency=', beforeCurrency?.trim(), 'asli=', beforeAsli, 'override=', beforeOverride);

  await selectItemCurrency(page, 'US Dollar ($)');

  const usdAsli = await originalAsli.inputValue().catch(() => '');
  const usdOverride = await originalOverride.inputValue().catch(() => '');
  console.log('[usd] asli=', usdAsli, 'override=', usdOverride);

  await selectItemCurrency(page, 'Indonesian Rupiah (Rp)');

  const idrAsli = await originalAsli.inputValue().catch(() => '');
  const idrOverride = await originalOverride.inputValue().catch(() => '');
  console.log('[idr] asli=', idrAsli, 'override=', idrOverride);

  expect(idrAsli || idrOverride).toBeTruthy();

  if (logs.length) {
    console.warn(logs.join('\n'));
  }
});