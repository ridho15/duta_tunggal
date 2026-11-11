import { test, expect } from '@playwright/test';

test.describe('ERP Smoke Test', () => {
  test('Verify dashboard is accessible when authenticated', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dashboard');
    await expect(page).toHaveTitle(/.*Duta Tunggal ERP|Dashboard.*/i);
    await expect(page.locator('body')).toBeVisible();
  });
});

