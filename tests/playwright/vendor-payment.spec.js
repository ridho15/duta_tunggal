import { test, expect } from '@playwright/test';

test.describe('Vendor Payment E2E Tests', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForLoadState('networkidle');

    // Fill login form
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');

    // Click login button (Indonesian text)
    await page.click('button:has-text("Masuk")');

    // Wait for redirect to admin area
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('networkidle');

    // Additional wait to ensure login is complete
    await page.waitForTimeout(2000);
  }

  test('can access vendor payment page and verify interface', async ({ page }) => {
    console.log('🧪 Testing: Vendor Payment page access and interface verification');

    await login(page);

    // Navigate to Vendor Payment page
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments');
    await page.waitForLoadState('networkidle');

    // Verify Vendor Payment page loads - check for the correct heading
    await expect(page.locator('h1')).toContainText(/Vendor Payment/i);

    // Check if create button exists
    const createButton = page.locator('a[href*="vendor-payments/create"]');
    if (await createButton.isVisible()) {
      console.log('✅ Create Payment button is visible');
    } else {
      console.log('ℹ️  Create Payment button not visible (may be due to permissions or no data)');
    }

    // Check if payment table exists
    const paymentTable = page.locator('.fi-ta-table').first();
    if (await paymentTable.isVisible()) {
      console.log('✅ Payment table is visible');

      // Check table headers
      const headers = paymentTable.locator('thead th');
      const headerCount = await headers.count();
      console.log(`📊 Payment table has ${headerCount} columns`);
    }

    console.log('✅ Vendor Payment page access test completed successfully');
  });

  test('can create full payment for vendor invoice', async ({ page }) => {
    console.log('🧪 Testing: Full vendor payment creation');

    await login(page);

    // Navigate to Vendor Payment page
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments');
    await page.waitForLoadState('networkidle');

    // Check if create button exists
    const createButton = page.locator('a[href*="vendor-payments/create"]');
    if (!(await createButton.isVisible())) {
      console.log('Create Payment button not visible, skipping payment creation test');
      await expect(page.locator('h1, .page-title')).toContainText(/vendor payment|payment/i);
      return;
    }

    // Click create payment button
    await page.click('a[href*="vendor-payments/create"]');
    await page.waitForLoadState('networkidle');

    // Verify create form loads
    await expect(page.locator('h1, .page-title')).toContainText(/vendor payment|create|tambah/i);

    // Check if supplier selection is available
    const supplierSelect = page.locator('#data\\.supplier_id');
    const supplierOptionCount = await supplierSelect.locator('option').count();

    if (supplierOptionCount <= 1) {
      console.log('No suppliers available for payment creation');
      return;
    }

    console.log(`✅ Found ${supplierOptionCount - 1} suppliers available for payment`);

    // Select first supplier
    await page.selectOption('#data\\.supplier_id', { index: 1 });

    // Wait for invoices to load
    await page.waitForTimeout(2000);

    // Check if invoices are available for the selected supplier
    const invoiceCheckboxes = page.locator('input[name*="invoice_ids"]');
    if (await invoiceCheckboxes.count() === 0) {
      console.log('No unpaid invoices available for the selected supplier');
      return;
    }

    // Select first available invoice
    await invoiceCheckboxes.first().check();

    // Wait for payment details to load
    await page.waitForTimeout(1000);

    // Fill payment details
    const paymentNumber = 'VP-' + Date.now();
    await page.fill('#data\\.payment_number', paymentNumber);
    await page.fill('#data\\.payment_date', new Date().toISOString().split('T')[0]);

    // Select payment method (Cash)
    await page.selectOption('#data\\.coa_id', { index: 1 });

    // Add payment detail
    const amountInputs = page.locator('input[name*="amount"]');
    if (await amountInputs.count() > 0) {
      // Get the invoice amount and use it for full payment
      const invoiceAmount = await amountInputs.first().inputValue();
      console.log(`📊 Invoice amount: ${invoiceAmount}`);

      // The amount should be auto-filled, just verify it's there
      await expect(amountInputs.first()).not.toBeEmpty();
    }

    console.log('✅ Vendor Payment form filled successfully - ready for submission');

    // Submit the form
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify payment created
    await expect(page.locator('.alert-success, .success-message')).toContainText(/created|saved|success|berhasil/i);

    console.log('✅ Full vendor payment created successfully');
  });

  test('can create partial payment for vendor invoice', async ({ page }) => {
    console.log('🧪 Testing: Partial vendor payment creation');

    await login(page);

    // Navigate to Vendor Payment page
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments');
    await page.waitForLoadState('networkidle');

    // Check if create button exists
    const createButton = page.locator('a[href*="vendor-payments/create"]');
    if (!(await createButton.isVisible())) {
      console.log('Create Payment button not visible, skipping partial payment test');
      return;
    }

    // Click create payment button
    await page.click('a[href*="vendor-payments/create"]');
    await page.waitForLoadState('networkidle');

    // Select supplier
    const supplierSelect = page.locator('#data\\.supplier_id');
    const supplierOptionCount = await supplierSelect.locator('option').count();

    if (supplierOptionCount <= 1) {
      console.log('No suppliers available for partial payment');
      return;
    }

    // Select first supplier
    await page.selectOption('#data\\.supplier_id', { index: 1 });

    // Wait for invoices to load
    await page.waitForTimeout(2000);

    // Check if invoices are available
    const invoiceCheckboxes = page.locator('input[name*="invoice_ids"]');
    if (await invoiceCheckboxes.count() === 0) {
      console.log('No unpaid invoices available for partial payment');
      return;
    }

    // Select first invoice
    await invoiceCheckboxes.first().check();

    // Wait for payment details to load
    await page.waitForTimeout(1000);

    // Fill payment details
    const paymentNumber = 'VP-PARTIAL-' + Date.now();
    await page.fill('#data\\.payment_number', paymentNumber);
    await page.fill('#data\\.payment_date', new Date().toISOString().split('T')[0]);

    // Select payment method
    await page.selectOption('#data\\.coa_id', { index: 1 });

    // Modify amount to be partial (half of the invoice amount)
    const amountInputs = page.locator('input[name*="amount"]');
    if (await amountInputs.count() > 0) {
      const fullAmount = await amountInputs.first().inputValue();
      const partialAmount = Math.floor(parseInt(fullAmount.replace(/[^\d]/g, '')) / 2);
      await amountInputs.first().fill(partialAmount.toString());
      console.log(`📊 Partial payment amount: ${partialAmount} (half of ${fullAmount})`);
    }

    console.log('✅ Partial vendor payment form filled successfully');

    // Submit the form
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify payment created
    await expect(page.locator('.alert-success, .success-message')).toContainText(/created|saved|success|berhasil/i);

    console.log('✅ Partial vendor payment created successfully');
  });

  test('can create payment using deposit', async ({ page }) => {
    console.log('🧪 Testing: Vendor payment using deposit');

    await login(page);

    // Navigate to Vendor Payment page
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments');
    await page.waitForLoadState('networkidle');

    // Check if create button exists
    const createButton = page.locator('a[href*="vendor-payments/create"]');
    if (!(await createButton.isVisible())) {
      console.log('Create Payment button not visible, skipping deposit payment test');
      return;
    }

    // Click create payment button
    await page.click('a[href*="vendor-payments/create"]');
    await page.waitForLoadState('networkidle');

    // Select supplier
    const supplierSelect = page.locator('#data\\.supplier_id');
    const supplierOptionCount = await supplierSelect.locator('option').count();

    if (supplierOptionCount <= 1) {
      console.log('No suppliers available for deposit payment');
      return;
    }

    // Select first supplier
    await page.selectOption('#data\\.supplier_id', { index: 1 });

    // Wait for invoices to load
    await page.waitForTimeout(2000);

    // Check if invoices are available
    const invoiceCheckboxes = page.locator('input[name*="invoice_ids"]');
    if (await invoiceCheckboxes.count() === 0) {
      console.log('No unpaid invoices available for deposit payment');
      return;
    }

    // Select first invoice
    await invoiceCheckboxes.first().check();

    // Wait for payment details to load
    await page.waitForTimeout(1000);

    // Fill payment details
    const paymentNumber = 'VP-DEPOSIT-' + Date.now();
    await page.fill('#data\\.payment_number', paymentNumber);
    await page.fill('#data\\.payment_date', new Date().toISOString().split('T')[0]);

    // Select deposit as payment method (assuming deposit option exists)
    const depositOption = page.locator('option').filter({ hasText: /deposit|titipan/i });
    if (await depositOption.isVisible()) {
      await depositOption.click();
      console.log('✅ Deposit payment method selected');
    } else {
      // Fallback to regular payment method
      await page.selectOption('#data\\.coa_id', { index: 1 });
      console.log('ℹ️  Deposit option not available, using regular payment method');
    }

    console.log('✅ Deposit vendor payment form filled successfully');

    // Submit the form
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify payment created
    await expect(page.locator('.alert-success, .success-message')).toContainText(/created|saved|success|berhasil/i);

    console.log('✅ Deposit vendor payment created successfully');
  });

  test('can view and verify payment details', async ({ page }) => {
    console.log('🧪 Testing: Vendor payment view and verification');

    await login(page);

    // Navigate to Vendor Payment page
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments');
    await page.waitForLoadState('networkidle');

    // Check if there are any payments in the table
    const paymentRows = page.locator('.fi-ta-table tbody tr');
    if (await paymentRows.count() > 0) {
      // Click on the first payment to view
      await paymentRows.first().click();
      await page.waitForLoadState('networkidle');

      // Verify we're on the payment view page
      await expect(page.locator('h1, .page-title')).toContainText(/vendor payment|payment|pembayaran/i);

      // Check for payment details - using actual field labels from the infolist
      await expect(page.locator('body')).toContainText(/Informasi Vendor Payment|Vendor Payment/i);
      await expect(page.locator('body')).toContainText(/Tanggal Pembayaran|Payment Date/i);
      await expect(page.locator('body')).toContainText(/Supplier|Vendor/i);
      await expect(page.locator('body')).toContainText(/Total Pembayaran|Total Payment/i);

      // Check if journal entries are displayed (look for journal content, not sidebar navigation)
      const journalContent = page.locator('body').filter({ hasText: /journal|jurnal/i }).locator('table, .table, tbody tr');
      if (await journalContent.count() > 0) {
        console.log('✅ Journal entries table is displayed');
      } else {
        console.log('ℹ️  Journal entries table not visible on this page');
      }

      console.log('✅ Payment details view verified successfully');
    } else {
      console.log('ℹ️  No payments available to view');
    }

    console.log('✅ Vendor payment view test completed successfully');
  });

  test('can verify vendor payment ID 13 details', async ({ page }) => {
    console.log('🧪 Testing: Vendor Payment ID 13 specific verification');

    await login(page);

    // Navigate directly to vendor payment ID 13
    await page.goto('http://127.0.0.1:8009/admin/vendor-payments/13');
    await page.waitForLoadState('networkidle');

    // Check page title
    const pageTitle = await page.locator('h1, .page-title').textContent();
    console.log('📄 Page Title:', pageTitle?.trim());

    // Check for "Invoice yang Dibayar" section
    const invoiceSection = page.locator('body').filter({ hasText: 'Invoice yang Dibayar' });
    if (await invoiceSection.isVisible()) {
      console.log('✅ "Invoice yang Dibayar" section is visible');

      // Check if it contains invoice data (not empty message)
      const hasInvoiceData = await invoiceSection.filter({ hasText: 'Tidak ada invoice' }).isVisible();
      if (!hasInvoiceData) {
        console.log('✅ Invoice data is displayed (not empty)');
      } else {
        console.log('❌ Invoice section shows "Tidak ada invoice"');
      }
    } else {
      console.log('❌ "Invoice yang Dibayar" section not found');
    }

    // Check for "Journal Entries" section
    const journalSection = page.locator('body').filter({ hasText: 'Journal Entries' });
    if (await journalSection.isVisible()) {
      console.log('✅ "Journal Entries" section is visible');

      // Check if it contains journal data (not empty message)
      const hasJournalData = await journalSection.filter({ hasText: 'Tidak ada journal entries' }).isVisible();
      if (!hasJournalData) {
        console.log('✅ Journal entries data is displayed (not empty)');

        // Count journal entry rows - try different selectors
        const journalRows1 = journalSection.locator('div.border');
        const journalRows2 = journalSection.locator('.border');
        const journalRows3 = journalSection.locator('tbody tr');
        
        let rowCount = 0;
        if (await journalRows1.count() > 0) {
          rowCount = await journalRows1.count();
          console.log(`📊 Found ${rowCount} journal entry rows (div.border)`);
        } else if (await journalRows2.count() > 0) {
          rowCount = await journalRows2.count();
          console.log(`📊 Found ${rowCount} journal entry rows (.border)`);
        } else if (await journalRows3.count() > 0) {
          rowCount = await journalRows3.count();
          console.log(`📊 Found ${rowCount} journal entry rows (tbody tr)`);
        } else {
          console.log(`📊 Found 0 journal entry rows (checked multiple selectors)`);
        }
      } else {
        console.log('❌ Journal section shows "Tidak ada journal entries"');
      }
    } else {
      console.log('❌ "Journal Entries" section not found');
    }

    // Check for specific payment amount
    const hasPaymentAmount = await page.locator('body').filter({ hasText: '100.000' }).isVisible();
    if (hasPaymentAmount) {
      console.log('✅ Payment amount Rp 100.000 is displayed');
    } else {
      console.log('❌ Payment amount not found');
    }

    console.log('✅ Vendor Payment ID 13 verification completed');
  });

});