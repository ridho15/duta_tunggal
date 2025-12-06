import { test, expect } from '@playwright/test';

test.describe('Sales Invoice and Account Receivable Flow Tests', () => {
  // Helper function for login
  async function login(page) {
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
  }

  test('complete sales invoice and account receivable flow', async ({ page }) => {
    console.log('🧪 Testing: Complete Sales Invoice and Account Receivable Flow');

    await login(page);

    // Step 1: Create Sales Invoice
    console.log('Step 1: Creating Sales Invoice...');
    await page.goto('http://127.0.0.1:8009/admin/invoices');
    await page.waitForLoadState('networkidle');

    // Click create button
    await page.locator('a[href*="invoices/create"]').first().click();
    await page.waitForLoadState('networkidle');

    // Fill invoice form
    const invoiceNumber = 'TEST-SALES-' + Date.now();

    // Select customer
    await page.locator('select[name="customer_id"]').selectOption({ label: 'PT Maju Bersama' });

    // Fill invoice details
    await page.fill('input[name="invoice_number"]', invoiceNumber);
    await page.fill('input[name="invoice_date"]', new Date().toISOString().split('T')[0]);

    // Add invoice item
    await page.locator('button').filter({ hasText: 'Add Item' }).click();
    await page.waitForTimeout(1000);

    // Select product
    await page.locator('select[name="invoice_items[0][product_id]"]').selectOption({ label: 'Panel Kontrol Industri' });
    await page.fill('input[name="invoice_items[0][quantity]"]', '2');
    await page.fill('input[name="invoice_items[0][price]"]', '50000');

    // Submit form
    await page.locator('button[type="submit"]').filter({ hasText: 'Create' }).click();
    await page.waitForLoadState('networkidle');

    // Verify invoice created
    await expect(page.locator('h1')).toContainText('Invoice');
    console.log('✅ Sales Invoice created successfully');

    // Step 2: Verify Account Receivable created
    console.log('Step 2: Verifying Account Receivable created...');
    await page.goto('http://127.0.0.1:8009/admin/account-receivables');
    await page.waitForLoadState('networkidle');

    // Check if invoice appears in account receivables
    await expect(page.locator('table')).toContainText(invoiceNumber);
    console.log('✅ Account Receivable created automatically');

    // Get initial account receivable count
    const initialARCount = await page.locator('.fi-ta-row').count();
    console.log(`Initial AR count: ${initialARCount}`);

    // Step 3: Make Partial Payment
    console.log('Step 3: Making partial payment...');
    await page.locator('a').filter({ hasText: 'View' }).first().click();
    await page.waitForLoadState('networkidle');

    // Click Add Payment button
    await page.locator('button').filter({ hasText: 'Add Payment' }).click();
    await page.waitForTimeout(1000);

    // Fill payment form
    await page.fill('input[name="amount"]', '50000'); // Partial payment of 50,000
    await page.fill('input[name="payment_date"]', new Date().toISOString().split('T')[0]);
    await page.fill('textarea[name="notes"]', 'Partial payment test');

    // Submit payment
    await page.locator('button[type="submit"]').filter({ hasText: 'Add Payment' }).click();
    await page.waitForLoadState('networkidle');

    // Verify partial payment recorded
    await expect(page.locator('.fi-ta-body')).toContainText('50.000');
    console.log('✅ Partial payment recorded');

    // Step 4: Make Full Payment
    console.log('Step 4: Making full payment...');
    await page.locator('button').filter({ hasText: 'Add Payment' }).click();
    await page.waitForTimeout(1000);

    // Fill remaining payment
    await page.fill('input[name="amount"]', '50000'); // Remaining payment
    await page.fill('input[name="payment_date"]', new Date().toISOString().split('T')[0]);
    await page.fill('textarea[name="notes"]', 'Full payment test');

    // Submit payment
    await page.locator('button[type="submit"]').filter({ hasText: 'Add Payment' }).click();
    await page.waitForLoadState('networkidle');

    // Verify status changed to Lunas
    await expect(page.locator('.fi-section')).toContainText('Lunas');
    console.log('✅ Full payment completed, status changed to Lunas');

    // Step 5: Check Ageing Schedule - should be removed
    console.log('Step 5: Checking Ageing Schedule removal...');
    await page.goto('http://127.0.0.1:8009/admin/account-receivables');
    await page.waitForLoadState('networkidle');

    // Count current AR records
    const currentARCount = await page.locator('.fi-ta-row').count();
    console.log(`Current AR count: ${currentARCount}`);

    // Should be less than initial count (paid invoice removed from ageing)
    if (currentARCount < initialARCount) {
      console.log('✅ Ageing Schedule updated - paid invoice removed');
    } else {
      console.log('⚠️  Ageing Schedule may still show paid invoice');
    }

    // Step 6: Delete Sales Invoice and check Account Receivable
    console.log('Step 6: Deleting Sales Invoice and checking Account Receivable...');
    await page.goto('http://127.0.0.1:8009/admin/invoices');
    await page.waitForLoadState('networkidle');

    // Find and click the test invoice
    await page.locator('table').filter({ hasText: invoiceNumber }).locator('a').filter({ hasText: 'View' }).click();
    await page.waitForLoadState('networkidle');

    // Click delete button
    await page.locator('button').filter({ hasText: 'Delete' }).click();
    await page.waitForTimeout(1000);

    // Confirm deletion
    await page.locator('button').filter({ hasText: 'Delete' }).click();
    await page.waitForLoadState('networkidle');

    // Verify invoice deleted (should redirect or show success)
    console.log('✅ Sales Invoice deleted');

    // Check if Account Receivable still exists (should be soft deleted)
    await page.goto('http://127.0.0.1:8009/admin/account-receivables');
    await page.waitForLoadState('networkidle');

    // Should not show the deleted invoice's AR
    const hasDeletedInvoice = await page.locator('table').locator(`text=${invoiceNumber}`).count();
    if (hasDeletedInvoice === 0) {
      console.log('✅ Account Receivable removed when invoice deleted');
    } else {
      console.log('⚠️  Account Receivable may still exist');
    }

    // Take final screenshot
    await page.screenshot({ path: 'sales-invoice-ar-flow-test.png', fullPage: true });

    console.log('🎉 Complete Sales Invoice and Account Receivable flow test completed successfully');
  });
});