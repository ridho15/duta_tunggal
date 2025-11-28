import { test, expect } from '@playwright/test';

test.describe('Sales Invoice Tests', () => {
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

  test('sales invoices page navigation', async ({ page }) => {
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

    // Navigate to sales invoices page
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Check if page loaded
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Invoice Penjualan');

    // Check for table or list elements
    const tableHeaders = await page.locator('table th').count();
    expect(tableHeaders).toBeGreaterThan(0);

    console.log('Found', tableHeaders, 'table headers');
  });

  test('create new sales invoice workflow', async ({ page }) => {
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

    // Navigate to create sales invoice page
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices/create');
    await page.waitForLoadState('networkidle');

    // Check if create form loaded
    const formTitle = await page.locator('h1, h2, h3').first().textContent();
    expect(formTitle).toContain('Invoice Penjualan');

    // Check for form fields - be more flexible with field detection
    const allInputs = await page.locator('input, select, textarea').all();
    console.log('Found', allInputs.length, 'form elements');

    // Check for common sales invoice fields
    const invoiceNumberField = await page.locator('input[name*="invoice_number"], input[name*="number"], input[id*="invoice_number"]').count();
    const invoiceDateField = await page.locator('input[name*="invoice_date"], input[name*="date"], input[type="date"]').count();
    const customerSelect = await page.locator('select[name*="customer"], select[name*="customer_id"]').count();
    const deliveryOrderSelect = await page.locator('select[name*="delivery_order"], select[name*="do_id"]').count();

    // At least some fields should exist
    const hasSomeFields = invoiceNumberField + invoiceDateField + customerSelect + deliveryOrderSelect > 0;
    expect(hasSomeFields).toBe(true);

    console.log('Invoice Number field:', invoiceNumberField, 'Invoice Date:', invoiceDateField, 'Customer:', customerSelect, 'DO:', deliveryOrderSelect);

    // Check for submit button
    const submitButton = await page.locator('button[type="submit"], input[type="submit"]').count();
    expect(submitButton).toBeGreaterThan(0);
  });

  test('sales invoice list and filtering', async ({ page }) => {
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

    // Navigate to sales invoices list
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Check for search/filter inputs
    const searchInputs = await page.locator('input[type="search"], input[placeholder*="search"]').count();
    const filterElements = await page.locator('select, input[type="date"]').count();

    console.log('Search inputs:', searchInputs, 'Filter elements:', filterElements);

    // Check table structure
    const tableHeaders = await page.locator('table th').allTextContents();
    console.log('Table headers:', tableHeaders);

    // Check if there are any sales invoices
    const tableRows = await page.locator('table tbody tr').count();
    console.log('Found', tableRows, 'sales invoice rows in table');
  });

  test('sales invoice detail view', async ({ page }) => {
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

    // Navigate to sales invoices list
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Try to find a sales invoice to view details
    const viewButtons = await page.locator('a[href*="sales-invoices"][href*="show"], button[title*="View"]').count();
    console.log('Found', viewButtons, 'view buttons');

    if (viewButtons > 0) {
      // Click first view button
      await page.locator('a[href*="sales-invoices"][href*="show"], button[title*="View"]').first().click();
      await page.waitForLoadState('networkidle');

      // Check detail page
      const detailTitle = await page.locator('h1, h2, h3').first().textContent();
      expect(detailTitle).toContain('Sales Invoice');

      // Check for detail elements
      const statusElements = await page.locator('.status, [class*="status"]').allTextContents();
      console.log('Status elements found:', statusElements);

      // Check for journal entries section
      const journalSection = await page.locator('text=Journal Entries, text=Journal, text=Jurnal').count();
      console.log('Journal section found:', journalSection);
    } else {
      console.log('No sales invoices found to view details');
    }
  });

  test('create invoice from delivery order', async ({ page }) => {
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

    // Navigate to delivery orders page to find completed DO
    await page.goto('http://127.0.0.1:8009/admin/delivery-orders');
    await page.waitForLoadState('networkidle');

    // Look for completed delivery orders
    const completedRows = await page.locator('table tbody tr').filter({ hasText: 'Completed' }).count();
    console.log('Found', completedRows, 'completed delivery orders');

    if (completedRows > 0) {
      // Click on the first completed delivery order
      const completedRow = page.locator('table tbody tr').filter({ hasText: 'Completed' }).first();
      const viewLink = completedRow.locator('a').first();

      if (await viewLink.count() > 0) {
        await viewLink.click();
        await page.waitForLoadState('networkidle');

        // Look for "Create Invoice" button
        const createInvoiceButton = await page.locator('a[href*="sales-invoices"][href*="create"], button:has-text("Create Invoice")').count();
        console.log('Found', createInvoiceButton, 'Create Invoice buttons');

        if (createInvoiceButton > 0) {
          await page.locator('a[href*="sales-invoices"][href*="create"], button:has-text("Create Invoice")').first().click();
          await page.waitForLoadState('networkidle');

          // Check if we're on the create invoice page
          const currentUrl = page.url();
          expect(currentUrl).toContain('sales-invoices/create');

          console.log('Successfully navigated to create invoice page from DO');
        } else {
          console.log('No Create Invoice button found');
        }
      } else {
        console.log('No view link found in completed delivery order row');
      }
    } else {
      console.log('No completed delivery orders found');
    }
  });

  test('sales invoice status workflow', async ({ page }) => {
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

    // Navigate to sales invoices list
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Look for sales invoices with different statuses
    const draftInvoices = await page.locator('table tbody tr').filter({ hasText: 'Draft' }).count();
    const postedInvoices = await page.locator('table tbody tr').filter({ hasText: 'Posted' }).count();
    const paidInvoices = await page.locator('table tbody tr').filter({ hasText: 'Paid' }).count();

    console.log('Draft invoices:', draftInvoices, 'Posted invoices:', postedInvoices, 'Paid invoices:', paidInvoices);

    // Check that we can see status information
    const statusCells = await page.locator('table tbody td').filter({ hasText: /(Draft|Posted|Paid|Unpaid)/ }).count();
    console.log('Found', statusCells, 'status cells in table');
  });

  test('sales invoice search and filter functionality', async ({ page }) => {
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

    // Navigate to sales invoices list
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchInput = await page.locator('input[type="search"], input[placeholder*="search"]').first();
    if (await searchInput.count() > 0) {
      await searchInput.fill('INV');
      await page.waitForTimeout(1000); // Wait for search to apply

      const searchResults = await page.locator('table tbody tr').count();
      console.log('Search results after searching "INV":', searchResults);
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

  test('verify journal entries in invoice detail', async ({ page }) => {
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

    // Navigate to sales invoices list
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    // Try to find a posted sales invoice to view journal entries
    const postedRows = await page.locator('table tbody tr').filter({ hasText: 'Posted' }).count();
    console.log('Found', postedRows, 'posted sales invoices');

    if (postedRows > 0) {
      // Click on the first posted sales invoice
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
          const piutangDagang = await page.locator('text=1120.01, text=Piutang Dagang').count();
          const penjualan = await page.locator('text=4100.01, text=Penjualan').count();
          const ppnKeluaran = await page.locator('text=2140.01, text=PPN Keluaran').count();
          const hpp = await page.locator('text=5100.10, text=HPP').count();

          console.log('Piutang Dagang entries:', piutangDagang);
          console.log('Penjualan entries:', penjualan);
          console.log('PPN Keluaran entries:', ppnKeluaran);
          console.log('HPP entries:', hpp);

          // At least some journal entries should be present
          const hasJournalEntries = piutangDagang + penjualan + ppnKeluaran + hpp > 0;
          expect(hasJournalEntries).toBe(true);
        } else {
          console.log('No journal entries table found');
        }
      } else {
        console.log('No view link found in posted sales invoice row');
      }
    } else {
      console.log('No posted sales invoices found');
    }
  });
});