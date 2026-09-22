import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

test.describe('Login Test - ralamzah@gmail.com', () => {
  test('should login successfully with ralamzah@gmail.com', async ({ page }) => {
    // Navigate to login page
    await page.goto('/admin/login');

    // Check page title
    const title = await page.title();
    console.log('Page title:', title);

    // Verify we're on login page
    await expect(page).toHaveTitle(/Masuk|Login|Duta Tunggal ERP/);

    // Fill login form
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');

    // Click login button
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();

    // Wait for redirect from login page
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });

    // Verify we're logged in
    const currentPath = new URL(page.url()).pathname;
    console.log('Current path after login:', currentPath);

    // Check that we're not on login page anymore
    expect(currentPath).not.toContain('/login');

    // Verify user info is displayed (check for username or name)
    const bodyText = await page.textContent('body');
    console.log('Page contains user info:', bodyText.includes('Ridho') || bodyText.includes('ridho'));

    // Take screenshot for verification
    await page.screenshot({ path: path.join(__dirname, 'screenshots', 'login-success.png'), fullPage: true });

    console.log('Login test PASSED - Successfully logged in as ralamzah@gmail.com');
  });

  test('should display user dashboard after login', async ({ page }) => {
    // Login first
    await page.goto('/admin/login');
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });

    // Check dashboard elements
    const currentPath = new URL(page.url()).pathname;
    console.log('Dashboard path:', currentPath);

    // Verify sidebar navigation is present
    const sidebar = page.locator('aside, nav, [class*="sidebar"]').first();
    await expect(sidebar).toBeVisible();

    // Check for main content area
    const mainContent = page.locator('main, [role="main"], .fi-content').first();
    await expect(mainContent).toBeVisible();

    console.log('Dashboard elements verified');
  });
});
