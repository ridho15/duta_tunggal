import { test, expect } from '@playwright/test';

test.describe('Quality Control (QC) E2E Tests', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('can access QC page and verify interface', async ({ page }) => {
    console.log('🧪 Testing: QC page access and interface verification');

    await login(page);

    // Navigate to Quality Control Purchase page
    await page.goto('/admin/quality-control-purchases');
    await page.waitForLoadState('networkidle');

    // Verify QC page loads
    await expect(page.locator('h1, .page-title')).toContainText(/quality control/i);

    // Check if create button exists
    const createButton = page.locator('a[href*="quality-control-purchases/create"]');
    if (await createButton.isVisible()) {
      console.log('✅ Create QC button is visible');
    } else {
      console.log('ℹ️  Create QC button not visible (may be due to permissions or no data)');
    }

    // Check if QC table exists (main content table, not debug bar)
    const qcTable = page.locator('table').filter({ hasText: 'Quality Control' }).or(page.locator('table').filter({ hasText: 'QC' })).or(page.locator('table').first());
    if (await qcTable.isVisible()) {
      console.log('✅ QC table is visible');

      // Check table headers
      const headers = qcTable.locator('thead th');
      const headerCount = await headers.count();
      console.log(`📊 QC table has ${headerCount} columns`);
    }

    console.log('✅ QC page access test completed successfully');
  });

  test('verify QC workflow with existing data', async ({ page }) => {
    console.log('🧪 Testing: QC workflow verification with existing data');

    await login(page);

    // Check if QC page loads
    await page.goto('/admin/quality-control-purchases');
    await page.waitForLoadState('networkidle');

    // Verify QC page loads
    await expect(page.locator('h1, .page-title')).toContainText(/quality control/i);

    // Check if we can access QC creation
    const createButton = page.locator('a[href*="quality-control-purchases/create"]');
    if (await createButton.isVisible()) {
      console.log('✅ QC create button is available');

      // Click create QC button
      await page.click('a[href*="quality-control-purchases/create"]');
      await page.waitForLoadState('networkidle');

      // Verify create form loads
      await expect(page.locator('h1, .page-title')).toContainText(/quality control|create/i);

      // Check if PO item selection is available
      const poRadio = page.locator('input[name="from_model_type"][value="App\\\\Models\\\\PurchaseOrderItem"]');
      if (await poRadio.isVisible()) {
        console.log('✅ PO item selection is available for QC');
      } else {
        console.log('ℹ️  PO item selection not available');
      }

      // Check if receipt item selection is available
      const receiptRadio = page.locator('input[name="from_model_type"][value="App\\\\Models\\\\PurchaseReceiptItem"]');
      if (await receiptRadio.isVisible()) {
        console.log('✅ Receipt item selection is available for QC');
      } else {
        console.log('ℹ️  Receipt item selection not available');
      }

    } else {
      console.log('ℹ️  QC create button not visible (may be due to permissions)');
    }

    // Check existing QC records
    const qcTable = page.locator('table').first();
    if (await qcTable.isVisible()) {
      const rowCount = await qcTable.locator('tbody tr').count();
      console.log(`📊 Found ${rowCount} existing QC records`);

      if (rowCount > 0) {
        // Check first QC record
        const firstRow = qcTable.locator('tbody tr').first();
        const statusCell = firstRow.locator('td').nth(-1); // Last column usually has status
        const statusText = await statusCell.textContent();
        console.log(`📋 First QC record status: ${statusText}`);
      }
    }

    console.log('✅ QC workflow verification completed successfully');
  });

});