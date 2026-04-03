import { test, expect } from '@playwright/test';

test.describe('Report Navigation Grouping', () => {
  test('finance and operational report hubs are visible in the sidebar', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('link', { name: /laporan keuangan/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /laporan operasional/i }).first()).toBeVisible({ timeout: 10000 });
  });

  test('legacy duplicate sidebar labels are no longer shown as top-level items', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('link', { name: /^trial balance$/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /^aging report$/i })).toHaveCount(0);
  });

  test('detailed finance report links are hidden from the sidebar', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');

    await expect(sidebar.getByRole('link', { name: /neraca saldo/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /buku besar/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /balance sheet/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /arus kas/i })).toHaveCount(0);
  });

  test('finance report hub page renders quick links to grouped reports', async ({ page }) => {
    await page.goto('/admin/finance-reports');
    await page.waitForLoadState('networkidle');

    const hub = page.locator('#finance-report-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /neraca saldo/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /buku besar/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /laba rugi/i }).first()).toBeVisible();
  });
});