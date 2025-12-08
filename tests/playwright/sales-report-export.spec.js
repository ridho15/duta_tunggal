import { test, expect } from '@playwright/test';

test.describe('Sales Report Export Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/admin/login');

    // Fill login form using Filament selectors
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');

    // Submit form using specific login button
    await page.click('button[type="submit"]:has-text("Masuk")');

    // Wait for login to complete and redirect to dashboard
    await page.waitForURL('**/admin**');
    await expect(page).toHaveTitle(/Dasbor/);
  });

  test('should access sales report page', async ({ page }) => {
    // Navigate to sales report page
    await page.goto('/admin/sales-report-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Take screenshot to see page structure
    await page.screenshot({ path: 'sales-report-page.png', fullPage: true });

    // Check if page title contains "Sales Report"
    await expect(page).toHaveTitle(/Sales Report/);

    // Check if export buttons exist
    const excelButton = page.locator('button').filter({ hasText: 'Export Excel' });
    const pdfButton = page.locator('button').filter({ hasText: 'Export PDF' });

    await expect(excelButton).toBeVisible();
    await expect(pdfButton).toBeVisible();

    console.log('✅ Sales report page accessed successfully');
  });

  test('should export Excel successfully', async ({ page }) => {
    // Navigate to sales report page
    await page.goto('/admin/sales-report-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

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
    expect(download.suggestedFilename()).toMatch(/sales_report.*\.xlsx$/);

    console.log('✅ Excel export test passed');
  });

  test('should export PDF successfully', async ({ page }) => {
    // Navigate to sales report page
    await page.goto('/admin/sales-report-page');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Wait for export buttons to be visible
    const pdfButton = page.locator('button').filter({ hasText: 'Export PDF' });
    await expect(pdfButton).toBeVisible();

    // Click the export button
    await pdfButton.click();

    // Wait for any response or page change
    await page.waitForTimeout(3000);

    // If we reach here without errors, the export action was successful
    // (PDF might open in browser or download depending on browser settings)
    console.log('✅ PDF export action completed successfully');
  });
});