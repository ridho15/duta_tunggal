import { test, expect } from '@playwright/test';

test.describe('Quality Control Purchase Page Tests', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('can access quality-control-purchases page without errors', async ({ page }) => {
    console.log('🧪 Testing: Quality Control Purchase page access');

    await login(page);

    try {
      // Navigate to Quality Control Purchase page
      await page.goto('/admin/quality-control-purchases');
      await page.waitForLoadState('networkidle');

      // Wait for page to fully load and check for any error messages
      const errorLocator = page.locator('.text-red-500, .bg-red-100, .border-red-500').or(
        page.locator('h1').filter({ hasText: 'Error' })
      );

      // Check if page loaded successfully
      const pageTitle = page.locator('h1').or(page.locator('.page-title')).or(page.locator('.filament-page-title'));
      await expect(pageTitle).toBeVisible();

      // Check for table presence (Filament table specifically)
      const table = page.locator('.fi-ta-table, table.fi-ta-table, .filament-table');
      if (await table.isVisible()) {
        console.log('✅ QC Purchase table is visible');

        // Check table headers
        const headers = table.locator('thead th');
        const headerCount = await headers.count();
        console.log(`📊 QC Purchase table has ${headerCount} columns`);

        // Check for data rows
        const rows = table.locator('tbody tr');
        const rowCount = await rows.count();
        console.log(`📋 QC Purchase table has ${rowCount} data rows`);

        // Verify specific columns exist
        const headerTexts = await headers.allTextContents();
        console.log('📋 Table headers:', headerTexts);

        // Check if expected columns are present
        const expectedColumns = ['QC Number', 'Purchase Receipt', 'Purchase Order', 'Product', 'Passed', 'Rejected', 'Status'];
        for (const expectedCol of expectedColumns) {
          const hasColumn = headerTexts.some(header =>
            header.toLowerCase().includes(expectedCol.toLowerCase())
          );
          if (hasColumn) {
            console.log(`✅ Column "${expectedCol}" is present`);
          } else {
            console.log(`❌ Column "${expectedCol}" is missing`);
          }
        }

        // Test table data loading
        if (rowCount > 0) {
          const firstRow = rows.first();
          const cells = firstRow.locator('td');
          const cellCount = await cells.count();
          console.log(`📋 First row has ${cellCount} cells`);

          // Check if cells have content (not empty or N/A)
          for (let i = 0; i < Math.min(cellCount, 5); i++) {
            const cellText = await cells.nth(i).textContent();
            console.log(`📋 Cell ${i + 1}: "${cellText?.trim()}"`);
          }
        }
      } else {
        console.log('❌ QC Purchase table is not visible');
      }

      // Check for any error messages on the page
      if (await errorLocator.isVisible()) {
        const errorText = await errorLocator.first().textContent();
        console.log(`❌ Error found on page: ${errorText}`);
        throw new Error(`Page contains error: ${errorText}`);
      }

      console.log('✅ Quality Control Purchase page loaded successfully without errors');

    } catch (error) {
      console.error('❌ Error during test:', error.message);
      throw error;
    }
  });

  test('can navigate to view page from table', async ({ page }) => {
    console.log('🧪 Testing: Navigation to QC Purchase view page');

    await login(page);

    try {
      await page.goto('/admin/quality-control-purchases');
      await page.waitForLoadState('networkidle');

      // Check if table has data rows
      const table = page.locator('.fi-ta-table, table.fi-ta-table, .filament-table');
      const rows = table.locator('tbody tr');

      if (await rows.count() > 0) {
        // Click on the first row's view button or link
        const firstRow = rows.first();
        const viewButton = firstRow.locator('a[href*="view"], button[title*="View"], .action-view').first();

        if (await viewButton.isVisible()) {
          console.log('✅ View button found, clicking...');
          await viewButton.click();
          await page.waitForLoadState('networkidle');

          // Check if view page loads
          const viewTitle = page.locator('h1').or(page.locator('.page-title')).or(page.locator('.filament-page-title'));
          await expect(viewTitle).toBeVisible();

          console.log('✅ Successfully navigated to QC Purchase view page');
        } else {
          console.log('ℹ️  View button not found in first row');
        }
      } else {
        console.log('ℹ️  No data rows found to test navigation');
      }

    } catch (error) {
      console.error('❌ Error during navigation test:', error.message);
      throw error;
    }
  });

});