import { test, expect } from '@playwright/test';

test.describe('Invoice Form Diagnostic', () => {
  test('inspect invoice create form', async ({ page }) => {
    // Login first
    await page.goto('/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    // Navigate to create invoice page
    await page.goto('/admin/invoices/create');
    await page.waitForLoadState('networkidle');

    // Wait for form to load
    await page.waitForTimeout(3000);

    console.log('=== PAGE TITLE ===');
    console.log('Page title:', await page.title());

    console.log('=== SELECT ELEMENTS ===');
    const selectElements = await page.locator('select').all();
    for (let i = 0; i < selectElements.length; i++) {
      const select = selectElements[i];
      const name = await select.getAttribute('name') || 'N/A';
      const id = await select.getAttribute('id') || 'N/A';
      console.log(`${i}: select - name: ${name}, id: ${id}`);
    }

    console.log('=== LABELS ===');
    const labels = await page.locator('label').all();
    for (let i = 0; i < labels.length; i++) {
      const label = labels[i];
      const text = await label.textContent();
      const forAttr = await label.getAttribute('for') || 'N/A';
      console.log(`${i}: "${text?.trim()}" - for: ${forAttr}`);
    }

    // Take screenshot for visual inspection
    await page.screenshot({ path: 'invoice-form-inspection.png', fullPage: true });

    // Keep the page open for manual inspection
    await page.waitForTimeout(10000);
  });
});