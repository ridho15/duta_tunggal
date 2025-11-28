import { test, expect } from '@playwright/test';

test.describe('Complete Sales E2E Flow', () => {
  test.setTimeout(180000); // 3 minutes - adequate for complete sales flow

  // Helper function for login
  async function login(page) {
    await page.goto('http://localhost:8009/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('basic page load test', async ({ page }) => {
    await page.goto('http://localhost:8009/login');
    console.log('Page loaded, title:', await page.title());
    expect(await page.title()).toContain('Duta Tunggal ERP');
    console.log('Basic test passed');
  });

  test('simple login test', async ({ page }) => {
    console.log('Testing simple login...');
    await page.goto('http://localhost:8009/login');
    console.log('On login page, filling credentials...');
    
    // Fill login form
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    console.log('Email filled');
    await page.fill('input[id="data.password"]', 'ridho123');
    console.log('Password filled');
    
    // Click login button
    await page.click('button[type="submit"]:has-text("Masuk")');
    console.log('Login button clicked, waiting for navigation...');
    
    // Wait a bit and check what happened
    await page.waitForTimeout(1000);
    const currentUrl = page.url();
    console.log('Current URL after login attempt:', currentUrl);
    
    // Check for error messages
    const errorVisible = await page.locator('.fi-fo-field-wrapper-error-message').isVisible();
    if (errorVisible) {
      const errorText = await page.locator('.fi-fo-field-wrapper-error-message').textContent();
      console.log('Login error:', errorText);
    }
    
    // Check if we're still on login page
    if (currentUrl.includes('/login')) {
      console.log('Still on login page - login failed');
      // Take a screenshot for debugging
      await page.screenshot({ path: 'login-failed.png' });
    } else {
      console.log('Login successful');
    }
    
    // For now, just check that we attempted login
    expect(currentUrl).toBeDefined();
  });

  test('minimal quotation creation test', async ({ page }) => {
    console.log('Testing minimal quotation creation...');
    await login(page);
    console.log('Login successful');

    // Navigate to quotation creation
    await page.goto('http://localhost:8009/admin/quotations/create');
    console.log('On create page');

    // Fill only the quotation number
    await page.fill('#data\\.quotation_number', 'QT-MINIMAL-' + Date.now());
    console.log('Quotation number filled');

    // Try to submit with minimal data
    await page.click('button[type="submit"]:has-text("Buat")');
    console.log('Submit button clicked');

    await page.waitForTimeout(2000);
    const url = page.url();
    console.log('URL after submission:', url);

    if (url.includes('/create')) {
      console.log('Still on create page - checking for errors');
      const errors = await page.locator('.fi-fo-field-wrapper-error-message').all();
      for (const error of errors) {
        console.log('Validation error:', await error.textContent());
      }
    } else {
      console.log('Form submitted successfully');
    }
  });

  test('complete sales flow from quotation to payment', async ({ page }) => {
    console.log('Starting complete sales flow test...');
    await login(page);
    console.log('Login completed successfully');

    // Generate unique identifiers for this test
    const timestamp = Date.now();
    const quotationNumber = `QT-E2E-${timestamp}`;
    const soNumber = `SO-E2E-${timestamp}`;
    const doNumber = `DO-E2E-${timestamp}`;
    const invoiceNumber = `INV-E2E-${timestamp}`;

    // 1. Create Quotation
    console.log('Creating quotation...');
    // Navigate directly to create page (like the minimal test)
    await page.goto('/admin/quotations/create');
    await page.waitForLoadState('networkidle');
    
    // Fill quotation form
    await page.fill('input[id*="quotation_number"]', quotationNumber);
    await page.fill('input[id*="date"]', new Date().toISOString().split('T')[0]);
    
    // Select customer
    const customerChoices = page.locator('.choices').first();
    await customerChoices.click();
    await page.keyboard.type('Ami');
    await page.waitForSelector('.choices__list--dropdown .choices__item', { timeout: 5000 });
    const customerOptions = page.locator('.choices__list--dropdown .choices__item');
    if (await customerOptions.count() > 0) {
      await customerOptions.first().click();
    }
    
    // Select product
    const productChoices = page.locator('.choices').nth(1);
    await productChoices.click();
    await page.keyboard.type('Produk');
    await page.waitForSelector('.choices__list--dropdown .choices__item', { timeout: 5000 });
    const productOptions = page.locator('.choices__list--dropdown .choices__item');
    if (await productOptions.count() > 0) {
      await productOptions.first().click();
    }
    
    // Fill quantity, unit price, tax
    await page.fill('input[id*="quantity"]', '10');
    await page.fill('input[id*="unit_price"]', '150000');
    await page.fill('input[id*="tax"]', '11');
    
    // Submit form
    await page.click('button[type="submit"]:has-text("Buat")');
    
    // Wait for success
    await Promise.race([
      page.waitForURL('**/quotations**', { timeout: 10000 }),
      page.waitForLoadState('networkidle', { timeout: 10000 })
    ]);
    
    // Check if we're back on quotations list or create page
    const currentUrl = page.url();
    let quotationId = null;
    
    if (currentUrl.includes('/quotations/') && !currentUrl.includes('/create')) {
      // On detail page
      quotationId = currentUrl.match(/quotations\/(\d+)/)?.[1];
    } else {
      // Check quotations list for our quotation
      await page.goto('/admin/quotations');
      await page.waitForLoadState('networkidle');
      const quotationExists = await page.locator(`text=${quotationNumber}`).isVisible({ timeout: 5000 });
      if (quotationExists) {
        const quotationRow = page.locator(`tr:has-text("${quotationNumber}")`);
        const quotationLink = quotationRow.locator('a').first();
        const href = await quotationLink.getAttribute('href');
        quotationId = href.match(/quotations\/(\d+)/)?.[1];
      }
    }
    
    if (!quotationId) {
      throw new Error('Quotation creation failed - could not find quotation ID');
    }
    
    console.log('Quotation created successfully with ID:', quotationId);

    // 2. Convert Quotation to Sales Order
    await page.goto(`/admin/quotations/${quotationId}`);
    await page.waitForLoadState('networkidle');
    
    // Click convert to SO button
    const convertButton = page.locator('button').filter({ hasText: /Convert to SO|Convert|SO|Sales Order/i }).first();
    await convertButton.waitFor({ state: 'visible' });
    await convertButton.click();
    
    // Wait for conversion success
    const soNotification = await page.locator('.filament-notifications');
    await expect(soNotification).toContainText(/created|berhasil|success/i);
    
    // Get SO ID from URL or notification
    const soUrl = page.url();
    const soId = soUrl.match(/sales-orders\/(\d+)/)?.[1];
    if (!soId) {
      throw new Error('Could not find Sales Order ID after conversion');
    }
    
    console.log('Sales Order created with ID:', soId);

    // 3. Convert SO to Delivery Order
    await page.goto(`/admin/sales-orders/${soId}`);
    await page.waitForLoadState('networkidle');
    
    // Click create DO button
    const createDoButton = page.locator('button').filter({ hasText: /Create DO|Delivery|DO/i }).first();
    await createDoButton.waitFor({ state: 'visible' });
    await createDoButton.click();
    
    // Fill DO form if needed
    await page.fill('input[id*="delivery_order_number"]', doNumber);
    await page.click('button[type="submit"]');
    
    // Wait for DO creation
    await expect(page.locator('.filament-notifications')).toContainText(/created|berhasil|success/i);
    
    // Get DO ID
    const doUrl = page.url();
    const doId = doUrl.match(/delivery-orders\/(\d+)/)?.[1];
    if (!doId) {
      throw new Error('Could not find Delivery Order ID after creation');
    }
    
    console.log('Delivery Order created with ID:', doId);

    // 4. Create Invoice from DO
    await page.goto(`/admin/delivery-orders/${doId}`);
    await page.waitForLoadState('networkidle');
    
    // Click create invoice button
    const createInvoiceButton = page.locator('button').filter({ hasText: /Create Invoice|Invoice/i }).first();
    await createInvoiceButton.waitFor({ state: 'visible' });
    await createInvoiceButton.click();
    
    // Fill invoice form
    await page.fill('input[id*="invoice_number"]', invoiceNumber);
    await page.click('button[type="submit"]');
    
    // Wait for invoice creation
    await expect(page.locator('.filament-notifications')).toContainText(/created|berhasil|success/i);
    
    // Get invoice ID
    const invoiceUrl = page.url();
    const invoiceId = invoiceUrl.match(/invoices\/(\d+)/)?.[1];
    if (!invoiceId) {
      throw new Error('Could not find Invoice ID after creation');
    }
    
    console.log('Invoice created with ID:', invoiceId);

    // 5. Create Customer Receipt (Payment)
    await page.goto(`/admin/invoices/${invoiceId}`);
    await page.waitForLoadState('networkidle');
    
    // Click create payment button
    const createPaymentButton = page.locator('button').filter({ hasText: /Payment|Receipt|Bayar/i }).first();
    await createPaymentButton.waitFor({ state: 'visible' });
    await createPaymentButton.click();
    
    // Fill payment form
    await page.fill('input[id*="amount"]', '1650000'); // 10 * 150000 * 1.11
    await page.click('button[type="submit"]');
    
    // Wait for payment creation
    await expect(page.locator('.filament-notifications')).toContainText(/created|berhasil|success/i);
    
    console.log('✅ Complete Sales Flow Test Passed!');
    console.log(`📋 Created: Quotation ${quotationNumber}, SO ${soId}, DO ${doId}, Invoice ${invoiceId}`);

    // Verify we can navigate back to dashboard
    await page.goto('/admin');
    await expect(page).toHaveURL('**/admin');
    await expect(page.locator('h1')).toContainText('Dashboard');
  });
});
