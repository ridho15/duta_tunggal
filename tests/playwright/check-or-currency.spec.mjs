import { test, expect } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8009';

async function selectFirstNonIdrCurrencyFromWrapper(page, wrapper) {
  const choicesInner = wrapper.locator('.choices__inner').first();
  await choicesInner.waitFor({ state: 'visible', timeout: 10_000 });
  await choicesInner.click();
  await page.waitForTimeout(250);

  const options = page.locator('.choices__list--dropdown:visible .choices__item--choice:not(.choices__placeholder):not(.is-disabled)');
  const optionCount = await options.count();

  let targetText = null;
  for (let i = 0; i < optionCount; i += 1) {
    const text = ((await options.nth(i).textContent()) || '').trim();
    const isIdr = /\bIDR\b|Rupiah|\(\s*Rp\s*\)/i.test(text);
    if (!isIdr && text.includes('(') && text.includes(')')) {
      targetText = text;
      await options.nth(i).click();
      break;
    }
  }

  if (!targetText) {
    throw new Error('Tidak menemukan opsi mata uang non-IDR untuk pengujian.');
  }

  const match = targetText.match(/\(([^)]+)\)/);
  const symbol = (match?.[1] || '').trim();

  if (!symbol || /^Rp$/i.test(symbol)) {
    throw new Error(`Simbol non-IDR tidak valid dari opsi: "${targetText}"`);
  }

  return { optionText: targetText, symbol };
}

test('Order Request create page shows non-IDR symbol when non-IDR currency is selected', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests/create`, { waitUntil: 'networkidle' });
  await page.waitForLoadState('networkidle');

  // Select header currency (non-IDR)
  const headerCurrencyWrapper = page
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("Mata Uang")') })
    .first();

  const { symbol } = await selectFirstNonIdrCurrencyFromWrapper(page, headerCurrencyWrapper);

  await page.waitForTimeout(600);

  // Add repeater item so the field is visible for currency selection
  const addBtn = page.getByRole('button').filter({ hasText: /tambah item|add item/i }).first();
  const genericAddBtn = page.locator('button[wire\\:click*="addItem"], button[x-on\\:click*="add"]').first();
  let addBtnClicked = false;

  if (await addBtn.isVisible().catch(() => false)) {
    await addBtn.click();
    addBtnClicked = true;
  } else if (await genericAddBtn.isVisible().catch(() => false)) {
    await genericAddBtn.click();
    addBtnClicked = true;
  } else {
    const repeaterAdd = page.locator('.fi-fo-repeater').getByRole('button').last();
    if (await repeaterAdd.isVisible().catch(() => false)) {
      await repeaterAdd.click();
      addBtnClicked = true;
    }
  }

  if (!addBtnClicked) {
    throw new Error('Tidak dapat menemukan atau klik tombol "Tambah Item".');
  }

  await page.waitForTimeout(900);

  // Select item currency (non-IDR)
  const itemCurrencyWrapper = page
    .locator('.fi-fo-repeater-item')
    .first()
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("Mata Uang Item")') })
    .first();

  await selectFirstNonIdrCurrencyFromWrapper(page, itemCurrencyWrapper);

  await page.waitForTimeout(900);

  // Verify non-IDR symbol appears in Harga Override field label/prefix
  const hargaOverrideWrapper = page
    .locator('.fi-fo-repeater-item')
    .first()
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("Harga Override")') })
    .first();

  await expect(hargaOverrideWrapper).toContainText(symbol);
  await expect(hargaOverrideWrapper).not.toContainText(/\bRp\b/);

  await page.screenshot({ path: 'playwright-report/or-non-idr-symbol-create.png', fullPage: true });
});
