import { test, expect } from '@playwright/test';

test.describe('Create Quotation via Filament UI', () => {
  test('login, create quotation and verify totals', async ({ page }) => {
    // Use the localhost host to match asset origin and avoid CORS issues
    await page.goto('http://localhost:8009/admin/login');

    // Login
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]');
    // Wait for dashboard to be visible to ensure login completed
    await page.waitForSelector('text=Dasbor', { timeout: 10000 });

    // Navigate to quotation create page
    await page.goto('http://localhost:8009/admin/quotations/create');
    await page.waitForLoadState('networkidle');

    // Fill Quotation Number with a unique value
    const qNumber = `QO-E2E-${Date.now()}`;
    await page.fill('input[id="data.quotation_number"]', qNumber);

    // Wait for the customer select to be present, then set it programmatically
    await page.waitForSelector('select', { state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
      const sel = Array.from(document.querySelectorAll('select')).find(s => s.id && s.id.includes('customer_id'));
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Set dates (use today's date in yyyy-mm-dd format)
    const today = new Date().toISOString().slice(0, 10);
    await page.fill('input[id="data.date"]', today);

    // The repeater has at least 1 item by default. Fill the first item fields.
    // Product: type product SKU (FG-SEED-001) then press Enter to select
    // Set product select (the repeater's select uses an id that contains 'product_id')
    await page.evaluate(() => {
      const sel = Array.from(document.querySelectorAll('select')).find(s => s.id && s.id.includes('product_id'));
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Unit Price (formatted as Indonesian money)
    await page.getByLabel('Unit Price').first().fill('12.500.000');

    // Quantity
    await page.getByLabel('Quantity').first().fill('2');

    // Discount (percent)
    await page.getByLabel('Discount').first().fill('5');

    // Tax (percent)
    await page.getByLabel('Tax').first().fill('11');

    // Wait a short moment for Livewire to compute totals
    await page.waitForTimeout(800);

    // Submit form using the visible submit button labeled 'Buat'
    await page.locator('button[type="submit"]:has-text("Buat")').first().click();

    // Wait for navigation or for the quotation number to appear on the page
    await page.waitForLoadState('networkidle');

    // Assert the page contains our generated quotation number
    await expect(page.locator('body')).toContainText(qNumber);

    // Assert computed total amount is present (26.362.500)
    await expect(page.locator('body')).toContainText('26.362.500');

    // Optionally capture a screenshot for verification
    await page.screenshot({ path: 'test-results/create-quotation-e2e.png', fullPage: true });
  });
});
