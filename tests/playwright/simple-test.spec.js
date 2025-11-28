import { test, expect } from '@playwright/test';

test.describe('Simple Test', () => {
  test('can access login page', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Check if we're on the login page
    const title = await page.title();
    console.log('Page title:', title);

    // Take screenshot
    await page.screenshot({ path: 'login-page.png' });

    expect(page.url()).toContain('login');
  });
});