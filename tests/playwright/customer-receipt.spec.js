import { test, expect } from '@playwright/test';

test.describe('Customer Receipt Tests', () => {
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

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Check current URL
    const finalUrl = page.url();
    console.log('Current URL after login:', finalUrl);

    expect(finalUrl).toContain('/admin');
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

  test('customer receipts page navigation', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts page
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Check if page loaded
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Customer Receipt');

    // Check for table or list elements
    const tableHeaders = await page.locator('table th').count();
    expect(tableHeaders).toBeGreaterThan(0);

    console.log('Found', tableHeaders, 'table headers');
  });

  test('create new customer receipt workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to create customer receipt page
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts/create');
    await page.waitForLoadState('networkidle');

    // Check if create form loaded
    const formTitle = await page.locator('h1, h2, h3').first().textContent();
    expect(formTitle).toContain('Customer Receipt');

    // Check for form fields - be more flexible with field detection
    const allInputs = await page.locator('input, select, textarea').all();
    console.log('Found', allInputs.length, 'form elements');

    // Check for common customer receipt fields
    const receiptNumberField = await page.locator('input[name*="receipt_number"], input[name*="ntpn"], input[id*="receipt_number"]').count();
    const paymentDateField = await page.locator('input[name*="payment_date"], input[name*="date"], input[type="date"]').count();
    const customerSelect = await page.locator('select[name*="customer"], select[name*="customer_id"]').count();
    const paymentMethodSelect = await page.locator('select[name*="payment_method"], select[name*="method"]').count();

    // At least some fields should exist
    const hasSomeFields = receiptNumberField + paymentDateField + customerSelect + paymentMethodSelect > 0;
    expect(hasSomeFields).toBe(true);

    console.log('Receipt Number field:', receiptNumberField, 'Payment Date:', paymentDateField, 'Customer:', customerSelect, 'Payment Method:', paymentMethodSelect);

    // Check for submit button
    const submitButton = await page.locator('button[type="submit"], input[type="submit"]').count();
    expect(submitButton).toBeGreaterThan(0);
  });

  test('customer receipt list and filtering', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts list
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Check for search/filter inputs
    const searchInputs = await page.locator('input[type="search"], input[placeholder*="search"]').count();
    const filterElements = await page.locator('select, input[type="date"]').count();

    console.log('Search inputs:', searchInputs, 'Filter elements:', filterElements);

    // Check table structure
    const tableHeaders = await page.locator('table th').allTextContents();
    console.log('Table headers:', tableHeaders);

    // Check if there are any customer receipts
    const tableRows = await page.locator('table tbody tr').count();
    console.log('Found', tableRows, 'customer receipt rows in table');
  });

  test('customer receipt detail view', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts list
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Try to find a customer receipt to view details
    const viewButtons = await page.locator('a[href*="customer-receipts"][href*="show"], button[title*="View"]').count();
    console.log('Found', viewButtons, 'view buttons');

    if (viewButtons > 0) {
      // Click first view button
      await page.locator('a[href*="customer-receipts"][href*="show"], button[title*="View"]').first().click();
      await page.waitForLoadState('networkidle');

      // Check detail page
      const detailTitle = await page.locator('h1, h2, h3').first().textContent();
      expect(detailTitle).toContain('Customer Receipt');

      // Check for detail elements
      const statusElements = await page.locator('.status, [class*="status"]').allTextContents();
      console.log('Status elements found:', statusElements);

      // Check for payment allocation section
      const allocationSection = await page.locator('text=Payment Allocation, text=Allocation, text=Allocations').count();
      console.log('Allocation section found:', allocationSection);

      // Check for journal entries section
      const journalSection = await page.locator('text=Journal Entries, text=Journal, text=Jurnal').count();
      console.log('Journal section found:', journalSection);
    } else {
      console.log('No customer receipts found to view details');
    }
  });

  test('create receipt from customer invoice', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to sales invoices page to find unpaid invoices
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Look for unpaid invoices
    const unpaidRows = await page.locator('table tbody tr').filter({ hasText: 'Unpaid' }).count();
    console.log('Found', unpaidRows, 'unpaid invoices');

    if (unpaidRows > 0) {
      // Click on the first unpaid invoice
      const unpaidRow = page.locator('table tbody tr').filter({ hasText: 'Unpaid' }).first();
      const viewLink = unpaidRow.locator('a').first();

      if (await viewLink.count() > 0) {
        await viewLink.click();
        await page.waitForLoadState('networkidle');

        // Look for "Create Receipt" or "Receive Payment" button
        const createReceiptButton = await page.locator('a[href*="customer-receipts"][href*="create"], button:has-text("Create Receipt"), button:has-text("Receive Payment")').count();
        console.log('Found', createReceiptButton, 'Create Receipt buttons');

        if (createReceiptButton > 0) {
          await page.locator('a[href*="customer-receipts"][href*="create"], button:has-text("Create Receipt"), button:has-text("Receive Payment")').first().click();
          await page.waitForLoadState('networkidle');

          // Check if we're on the create receipt page
          const currentUrl = page.url();
          expect(currentUrl).toContain('customer-receipts/create');

          console.log('Successfully navigated to create receipt page from invoice');
        } else {
          console.log('No Create Receipt button found');
        }
      } else {
        console.log('No view link found in unpaid invoice row');
      }
    } else {
      console.log('No unpaid invoices found');
    }
  });

  test('customer receipt status workflow', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts list
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Look for customer receipts with different statuses
    const postedReceipts = await page.locator('table tbody tr').filter({ hasText: 'Posted' }).count();
    const draftReceipts = await page.locator('table tbody tr').filter({ hasText: 'Draft' }).count();
    const cancelledReceipts = await page.locator('table tbody tr').filter({ hasText: 'Cancelled' }).count();

    console.log('Posted receipts:', postedReceipts, 'Draft receipts:', draftReceipts, 'Cancelled receipts:', cancelledReceipts);

    // Check that we can see status information
    const statusCells = await page.locator('table tbody td').filter({ hasText: /(Posted|Draft|Cancelled|Processed)/ }).count();
    console.log('Found', statusCells, 'status cells in table');
  });

  test('customer receipt search and filter functionality', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts list
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchInput = await page.locator('input[type="search"], input[placeholder*="search"]').first();
    if (await searchInput.count() > 0) {
      await searchInput.fill('RCP');
      await page.waitForTimeout(1000); // Wait for search to apply

      const searchResults = await page.locator('table tbody tr').count();
      console.log('Search results after searching "RCP":', searchResults);
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

  test('verify journal entries in receipt detail', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    // Navigate to customer receipts list
    await page.goto('http://127.0.0.1:8009/admin/customer-receipts');
    await page.waitForLoadState('networkidle');

    // Try to find a posted customer receipt to view journal entries
    const postedRows = await page.locator('table tbody tr').filter({ hasText: 'Posted' }).count();
    console.log('Found', postedRows, 'posted customer receipts');

    if (postedRows > 0) {
      // Click on the first posted receipt
      const postedRow = page.locator('table tbody tr').filter({ hasText: 'Posted' }).first();
      const viewLink = postedRow.locator('a').first();

      if (await viewLink.count() > 0) {
        await viewLink.click();
        await page.waitForLoadState('networkidle');

        // Look for journal entries section
        const journalTable = await page.locator('table').filter({ hasText: 'Debit' }).or(page.locator('table').filter({ hasText: 'Credit' })).count();
        console.log('Journal entries table found:', journalTable);

        if (journalTable > 0) {
          // Check for expected accounts in journal entries
          const kasBank = await page.locator('text=1110.01, text=Kas/Bank').count();
          const piutangDagang = await page.locator('text=1120.01, text=Piutang Dagang').count();
          const hutangTitipan = await page.locator('text=2300.01, text=Hutang Titipan').count();

          console.log('Kas/Bank entries:', kasBank);
          console.log('Piutang Dagang entries:', piutangDagang);
          console.log('Hutang Titipan entries:', hutangTitipan);

          // At least some journal entries should be present
          const hasJournalEntries = kasBank + piutangDagang + hutangTitipan > 0;
          expect(hasJournalEntries).toBe(true);
        } else {
          console.log('No journal entries table found');
        }
      } else {
        console.log('No view link found in posted receipt row');
      }
    } else {
      console.log('No posted customer receipts found');
    }
  });
});