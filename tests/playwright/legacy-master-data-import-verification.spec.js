import { test, expect } from '@playwright/test';

const ERR = /Fatal error|Whoops!|Something went wrong|SQLSTATE|QueryException/i;

test.use({ storageState: 'playwright/.auth/user.json' });

async function assertPageHealthy(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).not.toContainText(ERR);
}

test.describe.serial('Legacy master-data import UI verification', () => {
  test('master data hub and imported rows are visible in Filament', async ({ page }) => {
    await assertPageHealthy(page, '/admin/master-data-hub');
    await expect(page.locator('#master-data-hub')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.hubv2-hero-title')).toContainText('Pusat Data Master');
    await expect(page.getByRole('link', { name: /produk/i }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /kategori produk/i }).first()).toBeVisible();

    await assertPageHealthy(page, '/admin/customers?tableSearch=CAB--');
    await expect(page.locator('body')).toContainText('CAB--');
    await expect(page.locator('body')).toContainText('ARIO.A');

    await assertPageHealthy(page, '/admin/suppliers?tableSearch=CAB-A001');
    await expect(page.locator('body')).toContainText('CAB-A001');
    await expect(page.locator('body')).toContainText('Abdi Karya');

    await assertPageHealthy(page, '/admin/products?tableSearch=CAB-001001006071');
    await expect(page.locator('body')).toContainText('CAB-001001006071');
    await expect(page.locator('body')).toContainText('COPPER TUBE ASTM B819 TYPE L 1/4 X 0.71MM x 5.8M');

    await assertPageHealthy(page, '/admin/product-categories?tableSearch=ALFA');
    await expect(page.locator('body')).toContainText('ALFA');
    await expect(page.locator('body')).toContainText('Pipa Tembaga ALFA');

    await assertPageHealthy(page, '/admin/inventory-stocks?tableSearch=CAB-001001006071');
    await expect(page.locator('body')).toContainText('CAB-001001006071');
    await expect(page.locator('body')).toContainText('WH-LEG-CAB');
  });
});