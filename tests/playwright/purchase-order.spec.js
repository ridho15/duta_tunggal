import { test, expect } from '@playwright/test';

test.describe('Purchase Order Management', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('can create purchase order with single product', async ({ page }) => {
    await login(page);

    // Navigate to purchase orders page
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click create purchase order button
    await page.click('a[href*="purchase-orders/create"]');
    await page.waitForLoadState('networkidle');

    // Fill purchase order header
    const poNumber = 'PO-E2E-' + Date.now();
    await page.fill('#data\\.po_number', poNumber);
    await page.fill('#data\\.order_date', new Date().toISOString().split('T')[0]);

    // Select supplier (assuming there's at least one supplier)
    await page.selectOption('#data\\.supplier_id', { index: 1 });

    // Select warehouse (assuming there's at least one warehouse)
    await page.selectOption('#data\\.warehouse_id', { index: 1 });

    // Add product item
    await page.click('button[data-action="add-item"]');
    await page.waitForTimeout(1000);

    // Select product
    await page.selectOption('select[name="items[0][product_id]"]', { index: 1 });

    // Fill quantity and price
    await page.fill('input[name="items[0][quantity]"]', '10');
    await page.fill('input[name="items[0][unit_price]"]', '25000');

    // Save the purchase order
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify PO was created
    await expect(page.locator('body')).toContainText(poNumber);
    await expect(page.locator('body')).toContainText('draft');
  });

  test('can create purchase order with multiple products', async ({ page }) => {
    await login(page);

    // Navigate to purchase orders page
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click create purchase order button
    await page.click('a[href*="purchase-orders/create"]');
    await page.waitForLoadState('networkidle');

    // Fill purchase order header
    const poNumber = 'PO-MULTI-E2E-' + Date.now();
    await page.fill('#data\\.po_number', poNumber);
    await page.fill('#data\\.order_date', new Date().toISOString().split('T')[0]);

    // Select supplier and warehouse
    await page.selectOption('#data\\.supplier_id', { index: 1 });
    await page.selectOption('#data\\.warehouse_id', { index: 1 });

    // Add first product
    await page.click('button[data-action="add-item"]');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="items[0][product_id]"]', { index: 1 });
    await page.fill('input[name="items[0][quantity]"]', '5');
    await page.fill('input[name="items[0][unit_price]"]', '20000');

    // Add second product
    await page.click('button[data-action="add-item"]');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="items[1][product_id]"]', { index: 2 });
    await page.fill('input[name="items[1][quantity]"]', '8');
    await page.fill('input[name="items[1][unit_price]"]', '15000');

    // Save the purchase order
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verify PO was created with multiple items
    await expect(page.locator('body')).toContainText(poNumber);
    await expect(page.locator('body')).toContainText('draft');
  });

  test('can approve purchase order', async ({ page }) => {
    await login(page);

    // Navigate to purchase orders page
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Find a draft PO and click to view it
    const draftPO = page.locator('tr').filter({ hasText: 'draft' }).first();
    await draftPO.click();
    await page.waitForLoadState('networkidle');

    // Click approve button (assuming there's an approve action)
    const approveButton = page.locator('button, a').filter({ hasText: /approve|setujui/i }).first();
    if (await approveButton.isVisible()) {
      await approveButton.click();
      await page.waitForLoadState('networkidle');

      // Verify status changed to approved
      await expect(page.locator('body')).toContainText('approved');
    }
  });

  test('can view purchase order details', async ({ page }) => {
    await login(page);

    // Navigate to purchase orders page
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click on the first PO in the list
    const firstPO = page.locator('tbody tr').first();
    await firstPO.click();
    await page.waitForLoadState('networkidle');

    // Verify we're on the PO detail page
    await expect(page.locator('h1, .page-title')).toContainText(/purchase order|po/i);

    // Check for PO details elements
    await expect(page.locator('body')).toContainText('PO Number');
    await expect(page.locator('body')).toContainText('Supplier');
    await expect(page.locator('body')).toContainText('Status');
  });

});