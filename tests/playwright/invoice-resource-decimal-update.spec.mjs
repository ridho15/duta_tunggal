import { test, expect } from '@playwright/test';

test.use({ storageState: 'playwright/.auth/user.json' });

test('invoice edit page keeps decimal subtotal after save and reload', async ({ page }) => {
  await page.goto('/admin/invoices', { waitUntil: 'networkidle' });

  const search = page.locator('input[type="search"]').first();
  if (await search.isVisible().catch(() => false)) {
    await search.fill('INV-2024-001');
    await page.waitForLoadState('networkidle');
  }

  const row = page.locator('table tbody tr').filter({ hasText: 'INV-2024-001' }).first();
  await expect(row).toBeVisible();

  const invoiceHref = await row.getByRole('link', { name: 'INV-2024-001' }).getAttribute('href');
  await page.goto(new URL(`${invoiceHref}/edit`, page.url()).toString(), { waitUntil: 'networkidle' });

  const subtotalInput = page.locator('input#data\\.subtotal');
  await expect(subtotalInput).toBeVisible();

  await subtotalInput.fill('2469');

  const saveButton = page.getByRole('button', { name: /simpan|save/i }).first();
  await saveButton.click();
  await page.waitForLoadState('networkidle');

  await page.reload({ waitUntil: 'networkidle' });
  await expect(page.locator('input#data\\.subtotal')).toHaveValue(/2\.469|2469/);
});