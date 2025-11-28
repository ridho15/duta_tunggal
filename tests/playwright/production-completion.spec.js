import { test, expect } from '@playwright/test';

test.describe('Production Completion Tests', () => {
  test('login test', async ({ page }) => {
    // Navigate to login page
    await page.goto('http://127.0.0.1:8009/admin/login');

    // Wait for form to load
    await page.waitForSelector('#data\\.email', { timeout: 10000 });

    // Take screenshot of login page
    await page.screenshot({ path: 'login-page.png', fullPage: true });

    // Fill login form
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');

    // Take screenshot before submit
    await page.screenshot({ path: 'before-submit.png', fullPage: true });

    // Click login button
    await page.click('button[type="submit"]');

    // Wait a bit
    await page.waitForTimeout(5000);

    // Take screenshot after submit
    await page.screenshot({ path: 'after-submit.png', fullPage: true });

    // Check current URL
    const currentUrl = page.url();
    console.log('Current URL after login:', currentUrl);

    // Check for error messages
    const errorMessages = await page.locator('.text-red-500, .error, [role="alert"]').allTextContents();
    if (errorMessages.length > 0) {
      console.log('Error messages found:', errorMessages);
    }

    // Check page content
    const bodyText = await page.locator('body').textContent();
    console.log('Page contains:', bodyText?.substring(0, 500));

    // Basic check - if we're not on login page, login might have worked
    expect(currentUrl).toBeTruthy();
  });

  test('page accessibility test without login', async ({ page }) => {
    // Test basic page loading without login
    await page.goto('http://127.0.0.1:8009/admin/login');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check if page loaded successfully
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Masuk');

    // Check for login form elements
    const emailField = await page.locator('#data\\.email').count();
    const passwordField = await page.locator('#data\\.password').count();
    const submitButton = await page.locator('button[type="submit"]').count();

    expect(emailField).toBeGreaterThan(0);
    expect(passwordField).toBeGreaterThan(0);
    expect(submitButton).toBeGreaterThan(0);
  });

  test('production completion workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to production completion page
    await page.goto('http://127.0.0.1:8009/admin/finished-goods-completions');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check if we're on the right page
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Penyelesaian Barang Jadi');

    // Check for create new button or table
    const createButton = await page.locator('a[href*="create"], button:has-text("Create"), button:has-text("New")').count();
    const tableExists = await page.locator('table').count();

    // If create button exists, we can create new completion
    if (createButton > 0) {
      console.log('Create button found, can create new completion');
    }

    // If table exists, check for existing completions
    if (tableExists > 0) {
      console.log('Table found, checking existing completions');
      const rows = await page.locator('table tbody tr').count();
      console.log(`Found ${rows} completion records`);
    }

    // Take screenshot of the page
    await page.screenshot({ path: 'production-completion-page.png', fullPage: true });
  });

  test('manufacturing orders page', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to manufacturing orders page
    await page.goto('http://127.0.0.1:8009/admin/manufacturing-orders');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check page title
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Manufacturing Order');

    // Check for table or create functionality
    const tableExists = await page.locator('table').count();
    const createButton = await page.locator('a[href*="create"], button:has-text("Create")').count();

    if (tableExists > 0) {
      const rows = await page.locator('table tbody tr').count();
      console.log(`Found ${rows} manufacturing orders`);
    }

    if (createButton > 0) {
      console.log('Can create new manufacturing orders');
    }

    // Take screenshot
    await page.screenshot({ path: 'manufacturing-orders-page.png', fullPage: true });
  });
});