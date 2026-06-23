import { test, expect } from '@playwright/test';

test.use({ storageState: 'playwright/.auth/user.json' });

async function openCogmPage(page) {
  await page.goto('/admin/cost-of-goods-manufacturing?preview=1&start_date=2026-04-01&end_date=2026-04-30', {
    waitUntil: 'domcontentloaded',
  });

  await expect(page.locator('body')).toBeVisible();
  await page.waitForTimeout(500);
}

async function assertSearchableSelect(page, labelText) {
  const fieldWrapper = page
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator(`label:has-text("${labelText}")`) })
    .first();

  await expect(fieldWrapper).toBeVisible({ timeout: 10_000 });

  const markup = await fieldWrapper.innerHTML();
  expect(markup).toMatch(/choices__|combobox/i);

  const trigger = fieldWrapper.locator('.choices__inner, [role="combobox"], .choices').first();
  await expect(trigger).toBeVisible({ timeout: 10_000 });
  await trigger.click({ force: true });
  await page.waitForTimeout(300);

  const dropdown = page.locator('.choices__list--dropdown:visible').first();
  await expect(dropdown).toBeVisible({ timeout: 5_000 });

  const searchInput = dropdown.locator('input').first();
  await expect(searchInput).toBeVisible({ timeout: 5_000 });
}

test('COGM filters render searchable cabang and produk selects', async ({ page }) => {
  await openCogmPage(page);

  await expect(page.getByText('Filter Laporan Harga Pokok Produksi')).toBeVisible();
  await assertSearchableSelect(page, 'Cabang');
  await assertSearchableSelect(page, 'Produk');
});