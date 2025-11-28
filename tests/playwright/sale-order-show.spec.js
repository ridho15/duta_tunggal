import { test, expect } from '@playwright/test';
import fs from 'fs';

test.describe('Sale Order Show Page Test', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin**');
  });

  test('login and navigate to dashboard works', async ({ page }) => {
    // Should be on dashboard after login
    await expect(page).toHaveTitle(/Dasbor/);
    console.log('✅ Login successful, on dashboard');
  });

  test('should display total amount and unit prices correctly', async ({ page }) => {
    // First ensure we're logged in and on dashboard
    await expect(page).toHaveTitle(/Dasbor/);

    // Navigate to the sale order show page
    await page.goto('/admin/sale-orders/4');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Check if we're on the correct page (look for SO number)
    await expect(page.locator('body')).toContainText('RN-20251123-0004');

    // Check total amount is displayed
    const totalAmountLocator = page.locator('text=/Total Amount|Total Amount/i').first();
    await expect(totalAmountLocator).toBeVisible();

    // Check if total amount contains the expected value (29.137.500)
    await expect(page.locator('body')).toContainText('29.137.500');

    // Check unit prices are displayed in the items table
    // Look for unit price values
    await expect(page.locator('body')).toContainText('12.500.000'); // First item unit price
    await expect(page.locator('body')).toContainText('2.500.000');  // Second item unit price

    // Check subtotals are displayed
    await expect(page.locator('body')).toContainText('26.362.500'); // First item subtotal
    await expect(page.locator('body')).toContainText('2.775.000');  // Second item subtotal

    // Check product names are displayed
    await expect(page.locator('body')).toContainText('Panel Kontrol Industri');
    await expect(page.locator('body')).toContainText('Sensor Tekanan Digital');

    // Check quantities are displayed
    await expect(page.locator('body')).toContainText('2'); // First item quantity
    await expect(page.locator('body')).toContainText('1'); // Second item quantity

    console.log('✅ All sale order data verified successfully');
  });

  test('capture page for debugging (screenshot + html + console)', async ({ page }) => {
    // Ensure logged in and on dashboard
    await expect(page).toHaveTitle(/Dasbor/);

    // Attach console & network listeners early
    const logs = [];
    page.on('console', msg => logs.push(`${msg.type()}: ${msg.text()}`));
    const requests = [];
    page.on('request', r => requests.push(`REQUEST: ${r.method()} ${r.url()}`));
    page.on('response', r => requests.push(`RESPONSE: ${r.status()} ${r.url()}`));

    // Navigate to the sale order show page
    await page.goto('/admin/sale-orders/4');
    // Wait longer for Livewire to render
    await page.waitForTimeout(2000);

    // Debug: log current URL and title
    console.log('Current URL:', page.url());
    console.log('Page title:', await page.title());

    // Try multiple possible selectors to detect loaded items
    const selectors = [
      'text=Panel Kontrol Industri',
      'text=FG-SEED-001',
      'text=Sale order item',
      'text=SO Number',
      'text=Total Amount',
    ];

    let found = false;
    for (const sel of selectors) {
      try {
        await page.waitForSelector(sel, { timeout: 5000 });
        console.log('Found selector:', sel);
        found = true;
        break;
      } catch (e) {
        console.log('Not found selector:', sel);
      }
    }

    // Save screenshot and html regardless
    await page.screenshot({ path: 'test-results/sale-order-4-screenshot.png', fullPage: true });
    const html = await page.content();
    fs.mkdirSync('test-results', { recursive: true });
    fs.writeFileSync('test-results/sale-order-4-page.html', html);
    fs.writeFileSync('test-results/sale-order-4-console.log', logs.join('\n'));
    fs.writeFileSync('test-results/sale-order-4-network.log', requests.join('\n'));

    console.log('✅ Captured screenshot and page HTML to test-results/');
  });

  test('should display sale order header information', async ({ page }) => {
    // First ensure we're logged in and on dashboard
    await expect(page).toHaveTitle(/Dasbor/);

    await page.goto('/admin/sale-orders/4');
    await page.waitForLoadState('networkidle');

    // Check customer name
    await expect(page.locator('body')).toContainText('PT Maju Bersama');

    // Check status
    await expect(page.locator('body')).toContainText('draft');

    // Check order date (should contain today's date)
    await expect(page.locator('body')).toContainText('2025-11-23');

    console.log('✅ Sale order header information verified');
  });
});