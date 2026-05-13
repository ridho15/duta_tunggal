import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'fs';

test.use({ storageState: 'playwright/.auth/user.json' });

// Ensure test data present (setup script creates OR #20 and related fixtures)
test.beforeAll(() => {
  execSync('php scripts/setup_procurement_test_data.php', { stdio: 'inherit' });
});

test('Order Request view shows header and item cabang/warehouse codes', async ({ page }) => {
  await page.goto('/admin/order-requests/20');
  await page.waitForLoadState('networkidle');

  // Dump page HTML to assist debugging if labels aren't present
  const html = await page.content();
  fs.writeFileSync('tmp/or-20.html', html);

  await expect(page).not.toHaveURL(/login/);

  // Check required header labels
  const headerSection = page.locator('#informasi-order-request');
  const detailSection = page.locator('#detail-item-order-request');
  const cabangLabel = headerSection.getByText('Kode Cabang', { exact: true });
  const gudangLabel = headerSection.getByText('Kode Gudang', { exact: true });

  await expect(cabangLabel).toBeVisible();
  await expect(gudangLabel).toBeVisible();

  // Detail item should also expose branch code information
  await expect(page.getByText('Detail Item Order Request', { exact: true })).toBeVisible();
  const cabangItemLabelCount = await detailSection.getByText('Cabang Item', { exact: true }).count();
  expect(cabangItemLabelCount).toBeGreaterThan(0);

  // Lock requirement: Supplier and Cabang Item values are rendered as "(KODE) Nama"
  const items = detailSection.locator('.fi-in-repeatable-item');
  const itemCount = await items.count();
  expect(itemCount).toBeGreaterThan(0);

  for (let i = 0; i < itemCount; i += 1) {
    const item = items.nth(i);
    const itemText = await item.innerText();
    expect(itemText).toMatch(/Supplier\s*\n\([^)]+\)\s+.+/);
    expect(itemText).toMatch(/Cabang Item\s*\n\([^)]+\)\s+.+/);
  }

  // As final assurance, ensure at least one product name is visible on the page
  const productRow = page.locator('text=Produk').first();
  await expect(productRow).toBeVisible();
});