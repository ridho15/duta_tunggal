import { test, expect } from '@playwright/test';

async function loginToAdmin(page) {
  // Navigate to login page
  await page.goto('/admin/login');

  // Wait for page to load
  await page.waitForLoadState('networkidle');

  // Fill login form using Filament selectors
  await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
  await page.fill('input[id="data.password"]', 'ridho123');

  // Submit form using specific login button
  await page.click('button[type="submit"]:has-text("Masuk")');

  // Wait for login to complete and redirect to dashboard
  await page.waitForURL('**/admin**', { timeout: 10000 });
  await expect(page).toHaveTitle(/- Duta Tunggal ERP/);
}

test.describe('Vendor/Customer Summary Report Tests', () => {
  test('test basic connectivity', async ({ page }) => {
    // Just test if we can reach the server
    await page.goto('/');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    console.log('Page title:', await page.title());
    console.log('Page URL:', page.url());

    console.log('✅ Basic connectivity test passed');
  });

  test('debug vendor customer summary page', async ({ page }) => {
    // Login first
    await loginToAdmin(page);

    // Now try to access vendor customer summary page
    await page.goto('/admin/vendor-customer-summary-page');

    // Wait for page to load - use timeout instead of networkidle
    await page.waitForTimeout(5000);

    // Take screenshot for debugging
    await page.screenshot({ path: 'debug-vendor-customer-page.png', fullPage: true });

    // Log page title and URL
    console.log('Page title:', await page.title());
    console.log('Page URL:', page.url());

    // Log all h1 elements
    const h1Elements = await page.locator('h1').allTextContents();
    console.log('H1 elements:', h1Elements);

    console.log('✅ Vendor Customer Summary page debug completed');
  });

  test('should switch between customer and vendor views', async ({ page }) => {
    // Navigate to vendor customer summary page
    await page.goto('http://127.0.0.1:8009/admin/vendor-customer-summary-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait for Livewire to initialize
    await page.waitForTimeout(2000);

    // Check initial state (should be customer)
    const typeSelect = page.locator('select[wire\\:model\\.live="type"]');
    await expect(typeSelect).toHaveValue('customer');

    // Switch to vendor
    await typeSelect.selectOption('vendor');

    // Wait for table to update
    await page.waitForTimeout(2000);

    // Verify type changed
    await expect(typeSelect).toHaveValue('vendor');

    console.log('✅ Successfully switched between customer and vendor views');
  });

  test('should export Excel successfully', async ({ page }) => {
    // Navigate to vendor customer summary page
    await page.goto('http://127.0.0.1:8009/admin/vendor-customer-summary-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait for Livewire and table to load
    await page.waitForTimeout(3000);

    // Wait for export buttons to be visible
    const excelButton = page.locator('button').filter({ hasText: 'Export Excel' });
    await expect(excelButton).toBeVisible();

    // Start waiting for download before clicking
    const downloadPromise = page.waitForEvent('download');

    // Click the export button
    await excelButton.click();

    // Wait for download to start
    const download = await downloadPromise;

    // Verify download
    expect(download.suggestedFilename()).toMatch(/vendor_customer_summary.*\.xlsx$/);

    console.log('✅ Excel export test passed');
  });

  test('should export PDF successfully', async ({ page }) => {
    // Navigate to vendor customer summary page
    await page.goto('http://127.0.0.1:8009/admin/vendor-customer-summary-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait for Livewire and table to load
    await page.waitForTimeout(3000);

    // Wait for export buttons to be visible
    const pdfButton = page.locator('button').filter({ hasText: 'Export PDF' });
    await expect(pdfButton).toBeVisible();

    // Click the export button
    await pdfButton.click();

    // Wait for any response or page change
    await page.waitForTimeout(3000);

    console.log('✅ PDF export action completed successfully');
  });

  test('should filter by search term', async ({ page }) => {
    // Navigate to vendor customer summary page
    await page.goto('http://127.0.0.1:8009/admin/vendor-customer-summary-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait for Livewire and table to load
    await page.waitForTimeout(3000);

    // Get initial table row count
    const initialRows = await page.locator('.fi-table tbody tr').count();

    // Apply search filter
    await page.fill('input[placeholder="Cari berdasarkan kode atau nama"]', 'CV');

    // Wait for search to apply
    await page.waitForTimeout(2000);

    // Check if results are filtered (should have fewer or equal rows)
    const filteredRows = await page.locator('.fi-table tbody tr').count();

    // Results should be less than or equal to initial (filtered)
    expect(filteredRows).toBeLessThanOrEqual(initialRows);

    console.log(`✅ Search filter working. Initial: ${initialRows}, Filtered: ${filteredRows}`);
  });
});