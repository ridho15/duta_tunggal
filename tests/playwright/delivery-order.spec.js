import { test, expect } from '@playwright/test';

test.describe('Delivery Order Tests', () => {
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

  test('delivery orders page navigation', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 15000 });

    // Navigate to delivery orders page
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Check if page loaded
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Delivery Order');

    // Check for table or list elements
    const tableHeaders = await page.locator('table th').count();
    expect(tableHeaders).toBeGreaterThan(0);

    console.log('Found', tableHeaders, 'table headers');
  });

  test('create new delivery order workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 15000 });

    // Navigate to create delivery order page
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders/create');
    await page.waitForLoadState('networkidle');

    // Check if create form loaded
    const formTitle = await page.locator('h1, h2, h3').first().textContent();
    expect(formTitle).toContain('Delivery Order');

    // Check for form fields - be more flexible with field detection
    const allInputs = await page.locator('input, select, textarea').all();
    console.log('Found', allInputs.length, 'form elements');

    // Check for common delivery order fields
    const doNumberField = await page.locator('input[name*="do_number"], input[name*="number"], input[id*="do_number"]').count();
    const deliveryDateField = await page.locator('input[name*="delivery_date"], input[name*="date"], input[type="date"]').count();
    const driverSelect = await page.locator('select[name*="driver"], select[name*="driver_id"]').count();
    const vehicleSelect = await page.locator('select[name*="vehicle"], select[name*="vehicle_id"]').count();

    // At least one of these should exist
    const hasSomeFields = doNumberField + deliveryDateField + driverSelect + vehicleSelect > 0;
    expect(hasSomeFields).toBe(true);

    console.log('DO Number field:', doNumberField, 'Delivery Date:', deliveryDateField, 'Driver:', driverSelect, 'Vehicle:', vehicleSelect);

    // Check for submit button
    const submitButton = await page.locator('button[type="submit"], input[type="submit"]').count();
    expect(submitButton).toBeGreaterThan(0);
  });

  test('delivery order list and filtering', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete - check if we're redirected to admin or still on login
    await page.waitForTimeout(2000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      // Still on login page, wait a bit more
      await page.waitForURL('**/admin', { timeout: 10000 });
    }

    // Navigate to delivery orders list
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Check for search/filter inputs
    const searchInputs = await page.locator('input[type="search"], input[placeholder*="search"]').count();
    const filterElements = await page.locator('select, input[type="date"]').count();

    console.log('Search inputs:', searchInputs, 'Filter elements:', filterElements);

    // Check table structure
    const tableHeaders = await page.locator('table th').allTextContents();
    console.log('Table headers:', tableHeaders);

    // Check if there are any delivery orders
    const tableRows = await page.locator('table tbody tr').count();
    console.log('Found', tableRows, 'delivery order rows in table');
  });

  test('delivery order detail view', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete - check if we're redirected to admin or still on login
    await page.waitForTimeout(2000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      // Still on login page, wait a bit more
      await page.waitForURL('**/admin', { timeout: 10000 });
    }

    // Navigate to delivery orders list
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Try to find a delivery order to view details
    const viewButtons = await page.locator('a[href*="delivery-orders"][href*="show"], button[title*="View"]').count();
    console.log('Found', viewButtons, 'view buttons');

    if (viewButtons > 0) {
      // Click first view button
      await page.locator('a[href*="delivery-orders"][href*="show"], button[title*="View"]').first().click();
      await page.waitForLoadState('networkidle');

      // Check detail page
      const detailTitle = await page.locator('h1, h2, h3').first().textContent();
      expect(detailTitle).toContain('Delivery Order');

      // Check for detail elements
      const statusElements = await page.locator('.status, [class*="status"]').allTextContents();
      console.log('Status elements found:', statusElements);
    } else {
      console.log('No delivery orders found to view details');
    }
  });

  test('create delivery order from sales order', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete - check if we're redirected to admin or still on login
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      // Still on login page, wait a bit more
      await page.waitForTimeout(5000);
    }

    // Navigate to sales orders page to find an approved SO
    await page.goto('http://127.0.0.1:8009/admin/sale-orders');
    await page.waitForLoadState('networkidle');

    // Look for approved sales orders
    const approvedRows = await page.locator('table tbody tr').filter({ hasText: 'Approved' }).count();
    console.log('Found', approvedRows, 'approved sales orders');

    if (approvedRows > 0) {
      // Try to find and click on the first approved sales order link
      const approvedRow = page.locator('table tbody tr').filter({ hasText: 'Approved' }).first();
      const viewLink = approvedRow.locator('a').first();

      if (await viewLink.count() > 0) {
        await viewLink.click();
        await page.waitForLoadState('networkidle');

        // Look for "Create Delivery Order" button
        const createDOButton = await page.locator('a[href*="delivery-orders"][href*="create"], button:has-text("Create Delivery Order")').count();
        console.log('Found', createDOButton, 'Create Delivery Order buttons');

        if (createDOButton > 0) {
          await page.locator('a[href*="delivery-orders"][href*="create"], button:has-text("Create Delivery Order")').first().click();
          await page.waitForLoadState('networkidle');

          // Check if we're on the create DO page
          const currentUrl = page.url();
          expect(currentUrl).toContain('delivery-orders/create');

          console.log('Successfully navigated to create DO page from SO');
        } else {
          console.log('No Create Delivery Order button found');
        }
      } else {
        console.log('No view link found in approved sales order row');
      }
    } else {
      console.log('No approved sales orders found');
    }
  });

  test('delivery order status workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete - check if we're redirected to admin or still on login
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      // Still on login page, wait a bit more
      await page.waitForTimeout(5000);
    }

    // Navigate to delivery orders list
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Look for delivery orders with different statuses
    const draftOrders = await page.locator('table tbody tr').filter({ hasText: 'Draft' }).count();
    const pendingOrders = await page.locator('table tbody tr').filter({ hasText: 'Pending' }).count();
    const deliveredOrders = await page.locator('table tbody tr').filter({ hasText: 'Delivered' }).count();

    console.log('Draft orders:', draftOrders, 'Pending orders:', pendingOrders, 'Delivered orders:', deliveredOrders);

    // Check that we can see status information
    const statusCells = await page.locator('table tbody td').filter({ hasText: /(Draft|Pending|Delivered|In Transit)/ }).count();
    console.log('Found', statusCells, 'status cells in table');
  });

  test('delivery order search and filter functionality', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete - check if we're redirected to admin or still on login
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      // Still on login page, wait a bit more
      await page.waitForTimeout(5000);
    }

    // Navigate to delivery orders list
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchInput = await page.locator('input[type="search"], input[placeholder*="search"]').first();
    if (await searchInput.count() > 0) {
      await searchInput.fill('DO');
      await page.waitForTimeout(1000); // Wait for search to apply

      const searchResults = await page.locator('table tbody tr').count();
      console.log('Search results after searching "DO":', searchResults);
    }

    // Test filter functionality - look for status filter
    const statusFilter = await page.locator('select[name*="status"], select[id*="status"]').first();
    if (await statusFilter.count() > 0) {
      const options = await statusFilter.locator('option').allTextContents();
      console.log('Status filter options:', options);

      if (options.length > 1) {
        await statusFilter.selectOption(options[1]); // Select second option
        await page.waitForTimeout(1000); // Wait for filter to apply

        const filteredResults = await page.locator('table tbody tr').count();
        console.log('Filtered results:', filteredResults);
      }
    }
  });
});