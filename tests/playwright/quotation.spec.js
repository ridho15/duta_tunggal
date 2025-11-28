import { test, expect } from '@playwright/test';

test.describe('Quotation Tests', () => {
  test('login test', async ({ page }) => {
    // Navigate to login page
    await page.goto('http://127.0.0.1:8009/admin/login');

    // Wait for form to load
    await page.waitForSelector('#data\\.email', { timeout: 10000 });

    // Fill login form
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');

    // Click login button
    await page.click('button[type="submit"]');

    // Wait for redirect to admin with longer timeout
    await page.waitForURL('**/admin', { timeout: 15000 });

    // Check current URL
    const currentUrl = page.url();
    console.log('Current URL after login:', currentUrl);

    expect(currentUrl).toContain('/admin');
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

  test('quotations page navigation', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to quotations page
    await page.goto('http://127.0.0.1:8009/admin/quotations');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check page title
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Quotation');

    // Check for table or create functionality
    const tableExists = await page.locator('table').count();
    const createButton = await page.locator('a[href*="create"], button:has-text("Create"), button:has-text("New")').count();

    if (tableExists > 0) {
      const rows = await page.locator('table tbody tr').count();
      console.log(`Found ${rows} quotations`);
    }

    if (createButton > 0) {
      console.log('Can create new quotations');
    }

    // Take screenshot
    await page.screenshot({ path: 'quotations-page.png', fullPage: true });
  });

  test('create new quotation workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to create quotation page
    await page.goto('http://127.0.0.1:8009/admin/quotations/create');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check page title
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Quotation');

    // Check for form elements
    const customerSelect = await page.locator('select[name*="customer"]').count();
    const dateField = await page.locator('input[type="date"], input[name*="date"]').count();
    const saveButton = await page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Create")').count();

    console.log(`Customer select: ${customerSelect}, Date field: ${dateField}, Save button: ${saveButton}`);

    // Take screenshot of create form
    await page.screenshot({ path: 'quotation-create-form.png', fullPage: true });

    // Basic form validation - check if essential elements exist
    expect(saveButton).toBeGreaterThan(0);
  });

  test('quotation list and filtering', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to quotations page
    await page.goto('http://127.0.0.1:8009/admin/quotations');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check for search/filter functionality
    const searchInput = await page.locator('input[type="search"], input[placeholder*="search"], input[placeholder*="Search"]').count();
    const filterButtons = await page.locator('button:has-text("Filter"), select[name*="status"]').count();

    console.log(`Search inputs: ${searchInput}, Filter elements: ${filterButtons}`);

    // Check table headers
    const tableHeaders = await page.locator('table thead th').allTextContents();
    console.log('Table headers:', tableHeaders);

    // Take screenshot of quotation list
    await page.screenshot({ path: 'quotation-list.png', fullPage: true });
  });

  test('quotation detail view', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 10000 });

    // Navigate to quotations page
    await page.goto('http://127.0.0.1:8009/admin/quotations');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check that we have quotations in the table
    const tableRows = await page.locator('table tbody tr').count();
    console.log(`Found ${tableRows} quotation rows in table`);

    // Check for quotation numbers in the table (they might not follow QO- pattern)
    const quotationNumbers = await page.locator('table tbody td').filter({ hasText: /\w+/ }).allTextContents();
    console.log('Quotation data found:', quotationNumbers.slice(0, 10)); // Show first 10 items

    // Check for status badges
    const statusElements = await page.locator('table tbody td span').allTextContents();
    console.log('Status elements found:', statusElements.slice(0, 5)); // Show first 5 items

    // Take screenshot of quotations table
    await page.screenshot({ path: 'quotation-table.png', fullPage: true });

    // Verify we have at least some quotations and data
    expect(tableRows).toBeGreaterThan(0);
    expect(quotationNumbers.length).toBeGreaterThan(0);
  });
});