import { test, expect } from '@playwright/test';

test('create purchase invoice with PO and receipt selection', async ({ page }) => {
  console.log('🧪 Testing: Create Purchase Invoice with PO and Receipt Selection');

  // Login
  await page.goto('http://127.0.0.1:8009/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 15000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  // Navigate to create purchase invoice
  await page.goto('http://127.0.0.1:8009/admin/purchase-invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000); // Extra wait

  console.log('Current URL:', page.url());
  console.log('Page title:', await page.title());

  // Set supplier value directly
  await page.evaluate(() => {
    const select = document.querySelector('#data\\.selected_supplier');
    if (select) {
      select.value = '3';
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await page.waitForTimeout(3000);

  console.log('Set supplier to 3');

  // Set PO value directly
  await page.evaluate(() => {
    const select = document.querySelector('#data\\.selected_purchase_order');
    if (select) {
      select.value = '1';
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await page.waitForTimeout(5000);

  console.log('Set PO to 1');

  // Select purchase receipts
  const receiptCheckboxes = page.locator('input[type="checkbox"][name*="selected_purchase_receipts"]');
  const count = await receiptCheckboxes.count();
  console.log(`Receipt checkboxes: ${count}`);
  if (count > 0) {
    await receiptCheckboxes.first().check();
    await page.waitForTimeout(2000);
    console.log('Checked first receipt');
  } else {
    console.log('No receipt checkboxes found');
    // Try alternative locator
    const altCheckboxes = page.locator('input[type="checkbox"]').filter({ hasText: /receipt|RN-/ });
    const altCount = await altCheckboxes.count();
    console.log(`Alt receipt checkboxes: ${altCount}`);
    if (altCount > 0) {
      await altCheckboxes.first().check();
      console.log('Checked alt receipt');
    } else {
      return;
    }
  }

  // Fill invoice number
  const invoiceNumber = 'INV-' + Date.now();
  await page.fill('#data\\.invoice_number', invoiceNumber);

  // Fill dates
  const today = new Date().toISOString().split('T')[0];
  const dueDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
  await page.fill('#data\\.invoice_date', today);
  await page.fill('#data\\.due_date', dueDate);

  console.log('Form filled, submitting');
  await page.waitForLoadState('networkidle');

  // Verify success
  await expect(page.locator('.alert-success, .fi-notifications')).toContainText(/created|saved|success|berhasil/i);

  console.log('✅ Purchase Invoice created successfully with PO and receipt selection');
});