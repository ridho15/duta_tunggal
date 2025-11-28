import { test, expect } from '@playwright/test';

test.describe('Complete Procurement E2E Flow', () => {
  test.setTimeout(120000); // 2 minutes timeout

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('complete procurement to accounting flow', async ({ page }) => {
    await login(page);

    // 1. Create Purchase Order
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click create purchase order button
    await page.click('a[href*="purchase-orders/create"]');
    await page.waitForLoadState('networkidle');

    // Fill PO form
    const poNumber = `PO-E2E-${Date.now()}`;
    await page.fill('#data\\.po_number', poNumber);
    await page.waitForTimeout(2000); // Wait for form to stabilize

    // Select supplier using Choices.js interface
    const supplierContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Supplier' }).locator('.choices');
    await supplierContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Select currency using Choices.js interface
    const currencyContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Mata Uang' }).locator('.choices');
    await currencyContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Add PO item
    await page.click('button[data-action="add-item"]');
    await page.waitForTimeout(1000);

    // Select product using Choices.js
    const productContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Product' }).locator('.choices').first();
    await productContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    await page.fill('input[name="items[0][quantity]"]', '100');
    await page.fill('input[name="items[0][unit_price]"]', '50000');

    // Save PO
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*purchase-orders.*/);
    await expect(page.locator('.filament-notifications')).toContainText('Purchase Order created');

    // Get PO ID from URL
    const poUrl = page.url();
    const poId = poUrl.match(/purchase-orders\/(\d+)/)?.[1];

    // 2. Create Purchase Receipt from PO
    await page.goto('/admin/purchase-receipts/create');
    await page.waitForLoadState('networkidle');

    // Select the PO we just created
    const poSelectContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Purchase Order' }).locator('.choices');
    await poSelectContainer.click();
    await page.waitForTimeout(500);
    // Find and click the PO we created
    await page.locator('.choices__list--dropdown .choices__item').filter({ hasText: poNumber }).click();

    // Wait for receipt items to load
    await page.waitForTimeout(2000);

    // Set receipt quantities (full receipt)
    await page.fill('input[name="items[0][received_quantity]"]', '100');

    // Save receipt
    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Purchase receipt created');

    // Get receipt ID from URL
    const receiptUrl = page.url();
    const receiptId = receiptUrl.match(/purchase-receipts\/(\d+)/)?.[1];

    // 3. Send receipt items to QC (manual "Kirim QC" action)
    // This creates temporary procurement journal entries
    await page.goto(`/admin/purchase-receipts/${receiptId}`);
    await page.waitForLoadState('networkidle');

    // Find and click the "Kirim QC" button for the receipt item
    // This button sets is_sent=1 and creates journal entries
    const kirimQcButton = page.locator('button').filter({ hasText: 'Kirim QC' }).first();
    await kirimQcButton.click();

    // Confirm the action (if there's a confirmation dialog)
    await page.waitForTimeout(1000);
    const confirmButton = page.locator('button').filter({ hasText: 'Ya' }).or(page.locator('button').filter({ hasText: 'Confirm' }));
    if (await confirmButton.isVisible()) {
      await confirmButton.click();
    }

    await expect(page.locator('.filament-notifications')).toContainText('Item sent to quality control');

    // 4. Complete Quality Control
    // Navigate to QC list and find the QC for our receipt
    await page.goto('/admin/quality-controls');
    await page.waitForLoadState('networkidle');

    // Click on the first QC (should be the one we just created)
    await page.waitForSelector('table tbody tr');
    await page.click('table tbody tr:first-child a[href*="quality-controls"]');

    // Complete the QC with full approval
    await page.click('button[data-action="complete-qc"]');
    await page.waitForTimeout(1000);

    // Fill completion form
    await page.fill('input[name="passed_quantity"]', '100');
    await page.fill('input[name="rejected_quantity"]', '0');

    // Submit completion
    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Quality control completed');

    // 5. Create Purchase Invoice
    await page.goto('/admin/purchase-invoices/create');
    await page.waitForLoadState('networkidle');

    // Select the receipt
    const receiptSelectContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Purchase Receipt' }).locator('.choices');
    await receiptSelectContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Fill invoice details
    await page.fill('input[name="invoice_number"]', `INV-E2E-${Date.now()}`);
    await page.fill('input[name="ppn_percentage"]', '11');

    // Save invoice
    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Purchase invoice created');

    // Get invoice ID from URL
    const invoiceUrl = page.url();
    const invoiceId = invoiceUrl.match(/purchase-invoices\/(\d+)/)?.[1];

    // 6. Create Vendor Payment
    await page.goto('/admin/vendor-payments/create');
    await page.waitForLoadState('networkidle');

    // Select supplier
    const paymentSupplierContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Supplier' }).locator('.choices');
    await paymentSupplierContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Select cash/bank account
    const cashBankContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Cash/Bank' }).locator('.choices');
    await cashBankContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Fill payment details
    await page.fill('input[name="reference_number"]', `PAY-E2E-${Date.now()}`);
    await page.fill('input[name="amount"]', '5000000'); // Approximate amount

    // Select invoice to pay
    await page.check(`input[name="invoices[${invoiceId}][selected]"]`);
    await page.fill(`input[name="invoices[${invoiceId}][amount]"]`, '5000000');

    // Save payment
    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Vendor payment created');

    // 7. Verify the complete flow by checking records exist
    // Check PO exists and is approved
    await page.goto('/admin/purchase-orders');
    await expect(page.locator('table')).toContainText(poNumber);

    // Check QC exists and is completed
    await page.goto('/admin/quality-controls');
    await expect(page.locator('table')).toContainText('completed');

    // Check Receipt exists and is posted
    await page.goto('/admin/purchase-receipts');
    await expect(page.locator('table')).toContainText('posted');

    // Check Invoice exists
    await page.goto('/admin/purchase-invoices');
    await expect(page.locator('table')).toContainText('INV-E2E-');

    // Check Payment exists
    await page.goto('/admin/vendor-payments');
    await expect(page.locator('table')).toContainText('PAY-E2E-');
  });

  test('procurement flow with partial receipts', async ({ page }) => {
    // Login
    await page.goto('http://localhost:8009/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForTimeout(2000); // Wait for dashboard to fully load

    // Create PO with larger quantity
    await page.goto('http://localhost:8009/admin/purchase-orders/create');
    await page.fill('#data\\.po_number', `PO-PARTIAL-${Date.now()}`);
    await page.waitForTimeout(2000);

    // Select supplier using visible Choices.js interface
    const supplierContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Supplier' }).locator('.choices');
    await supplierContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Select currency using visible Choices.js interface
    const currencyContainer = page.locator('[data-field-wrapper]').filter({ hasText: 'Mata Uang' }).locator('.choices');
    await currencyContainer.click();
    await page.waitForTimeout(500);
    await page.locator('.choices__list--dropdown .choices__item').first().click();

    // Add item with 200 quantity
    await page.click('button[data-action="add-item"]');
    await page.selectOption('select[name="items[0][product_id]"]', { index: 1 });
    await page.fill('input[name="items[0][quantity]"]', '200');
    await page.fill('input[name="items[0][unit_price]"]', '50000');

    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Purchase Order created');

    // First QC - partial receipt
    await page.goto('http://localhost:8009/admin/quality-controls/create');
    const poUrl = page.url();
    const poId = poUrl.match(/purchase-orders\/(\d+)/)?.[1] || '1';
    await page.selectOption('select[name="purchase_order_id"]', poId);

    // Set partial quantities
    await page.fill('input[name="items[0][inspected_quantity]"]', '100');
    await page.fill('input[name="items[0][passed_quantity]"]', '95');
    await page.fill('input[name="items[0][rejected_quantity]"]', '5');

    await page.click('button[type="submit"]');
    await expect(page.locator('.filament-notifications')).toContainText('Quality control completed');

    // Post first receipt
    await page.goto('http://localhost:8009/admin/purchase-receipts');
    await page.waitForSelector('table tbody tr');
    await page.click('table tbody tr:first-child a[href*="purchase-receipts"]');
    await page.click('button[data-action="post-receipt"]');

    // Second QC - remaining quantity
    await page.goto('http://localhost:8009/admin/quality-controls/create');
    await page.selectOption('select[name="purchase_order_id"]', poId);

    await page.fill('input[name="items[0][inspected_quantity]"]', '100');
    await page.fill('input[name="items[0][passed_quantity]"]', '90');
    await page.fill('input[name="items[0][rejected_quantity]"]', '10');

    await page.click('button[type="submit"]');

    // Post second receipt
    await page.goto('http://localhost:8009/admin/purchase-receipts');
    await page.waitForSelector('table tbody tr');
    const rows = await page.locator('table tbody tr').count();
    await page.click(`table tbody tr:nth-child(${rows}) a[href*="purchase-receipts"]`); // Click last receipt
    await page.click('button[data-action="post-receipt"]');

    // Verify PO still shows approved status (not completed due to rejections)
    await page.goto('http://localhost:8009/admin/purchase-orders');
    await expect(page.locator('table')).toContainText('approved');
  });

  test('procurement flow error handling', async ({ page }) => {
    // Login
    await page.goto('http://localhost:8009/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForTimeout(2000); // Wait for dashboard to fully load

    // Try to create PO without required fields
    await page.goto('http://localhost:8009/admin/purchase-orders/create');

    // Try to save without filling required fields
    await page.click('button[type="submit"]');

    // Should show validation errors
    await expect(page.locator('.filament-forms-field-wrapper-error-message')).toBeVisible();

    // Try to create QC without PO
    await page.goto('http://localhost:8009/admin/quality-controls/create');
    await page.click('button[type="submit"]');

    // Should show validation error
    await expect(page.locator('.filament-forms-field-wrapper-error-message')).toBeVisible();
  });
});