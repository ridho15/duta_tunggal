import { test, expect } from '@playwright/test';

test.describe('Basic Application Test', () => {
  test('login page loads correctly', async ({ page }) => {
    // Navigate to login page
    await page.goto('/admin/login');

    // Check if login form exists using Filament selectors
    await expect(page.locator('input[id="data.email"]')).toBeVisible();
    await expect(page.locator('input[id="data.password"]')).toBeVisible();
    // Use more specific selector for login button (exclude search button)
    await expect(page.locator('button[type="submit"]:has-text("Masuk")')).toBeVisible();
  });

  test('can login with provided credentials', async ({ page }) => {
    // Navigate to login page
    await page.goto('/admin/login');

    // Fill login form using Filament selectors
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');

    // Submit form using specific login button
    await page.click('button[type="submit"]:has-text("Masuk")');

    // Should redirect to dashboard or admin area
    await page.waitForURL('**/admin**');
    // Check for dashboard title (Indonesian: "Dasbor")
    await expect(page).toHaveTitle(/Dasbor/);
  });
});