import { test, expect } from '@playwright/test';

test.describe('Purchase Order Diagnostic', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('diagnose purchase order form', async ({ page }) => {
    await login(page);

    // Navigate to purchase orders page
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click create purchase order button
    await page.click('a[href*="purchase-orders/create"]');
    await page.waitForLoadState('networkidle');

    // Take screenshot for debugging
    await page.screenshot({ path: 'purchase-order-form.png', fullPage: true });

    // Log all input fields
    const inputs = await page.locator('input').all();
    console.log('Input fields found:');
    for (const input of inputs) {
      const id = await input.getAttribute('id');
      const name = await input.getAttribute('name');
      const type = await input.getAttribute('type');
      console.log(`Input: id=${id}, name=${name}, type=${type}`);
    }

    // Log all select fields
    const selects = await page.locator('select').all();
    console.log('Select fields found:');
    for (const select of selects) {
      const id = await select.getAttribute('id');
      const name = await select.getAttribute('name');
      console.log(`Select: id=${id}, name=${name}`);
    }

    // Log all buttons
    const buttons = await page.locator('button').all();
    console.log('Button elements found:');
    for (const button of buttons) {
      const text = await button.textContent();
      const type = await button.getAttribute('type');
      console.log(`Button: text=${text?.trim()}, type=${type}`);
    }

    // Just pass the test - this is for diagnostics
    expect(true).toBe(true);
  });

});