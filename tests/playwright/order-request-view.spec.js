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
  const kodeCabangCount = await detailSection.locator('text=Kode Cabang').count();
  expect(kodeCabangCount).toBeGreaterThan(1);

  // Lock requirement: first detail item Supplier and Cabang Item are rendered as "(KODE) Nama"
  const firstItem = detailSection.locator('.fi-in-repeatable-item').first();

  const supplierValue = (
    await firstItem
      .locator('.fi-in-entry-wrp')
      .filter({ has: firstItem.getByText('Supplier', { exact: true }) })
      .locator('dd .text-sm.leading-6')
      .first()
      .innerText()
  ).trim();
  expect(supplierValue).toMatch(/^\([^)]+\)\s+.+$/);

  const cabangItemValue = (
    await firstItem
      .locator('.fi-in-entry-wrp')
      .filter({ has: firstItem.getByText('Cabang Item', { exact: true }) })
      .locator('dd .text-sm.leading-6')
      .first()
      .innerText()
  ).trim();
  expect(cabangItemValue).toMatch(/^\([^)]+\)\s+.+$/);

  // As final assurance, ensure at least one product name is visible on the page
  const productRow = page.locator('text=Produk').first();
  await expect(productRow).toBeVisible();
});