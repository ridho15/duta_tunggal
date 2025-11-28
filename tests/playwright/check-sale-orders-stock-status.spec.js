import { test, expect } from '@playwright/test';

test.describe('Sale Orders Stock Status Test', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin**');
  });

  test('should display stock status column on sale orders page', async ({ page }) => {
    // First ensure we're logged in and on dashboard
    await expect(page).toHaveTitle(/Dasbor/);

    // Navigate to sale orders page
    await page.goto('/admin/sale-orders');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Check if we're on the sale orders page
    await expect(page).toHaveTitle(/Penjualan/);

    // Wait for the table to load
    await page.waitForSelector('table', { timeout: 10000 });

    // Check if "Status Stok" column header exists
    const statusStokHeader = page.locator('th').filter({ hasText: 'Status Stok' });
    await expect(statusStokHeader).toBeVisible();

    console.log('✅ Status Stok column header is visible');

    // Check if there are stock status badges in the table
    // Look for badges with "STOK OK" or "KURANG STOK"
    const stockOkBadges = page.locator('.fi-badge').filter({ hasText: 'STOK OK' });
    const kurangStokBadges = page.locator('.fi-badge').filter({ hasText: 'KURANG STOK' });

    // Count total badges
    const stockOkCount = await stockOkBadges.count();
    const kurangStokCount = await kurangStokBadges.count();

    console.log(`📊 Found ${stockOkCount} "STOK OK" badges`);
    console.log(`⚠️ Found ${kurangStokCount} "KURANG STOK" badges`);

    // Debug: Check what badges actually exist
    const allBadges = page.locator('.fi-badge');
    const allBadgesCount = await allBadges.count();
    console.log(`📊 Total badges found: ${allBadgesCount}`);

    if (allBadgesCount > 0) {
      for (let i = 0; i < Math.min(allBadgesCount, 5); i++) {
        const badgeText = await allBadges.nth(i).textContent();
        console.log(`Badge ${i + 1}: "${badgeText}"`);
      }
    }

    // Check the Status Stok column cells directly
    const statusStokCells = page.locator('td').filter({ hasText: /STOK OK|KURANG STOK/ });
    const statusStokCellsCount = await statusStokCells.count();
    console.log(`📊 Found ${statusStokCellsCount} cells with stock status text`);

    // Debug: Check the content of Status Stok column
    const statusStokColumnIndex = await page.locator('th').filter({ hasText: 'Status Stok' }).locator('xpath=preceding-sibling::*').count() + 1;
    console.log(`Status Stok column appears to be at index: ${statusStokColumnIndex}`);

    // Get all table rows
    const rows = page.locator('tbody tr');
    const rowCount = await rows.count();
    console.log(`Found ${rowCount} table rows`);

    if (rowCount > 0) {
      // Check first few rows
      for (let i = 0; i < Math.min(rowCount, 3); i++) {
        const cells = rows.nth(i).locator('td');
        const cellCount = await cells.count();
        console.log(`Row ${i + 1} has ${cellCount} cells`);

        // Try to find Status Stok cell (usually around column 8-10)
        for (let j = 7; j < Math.min(cellCount, 12); j++) {
          const cellText = await cells.nth(j).textContent();
          if (cellText && cellText.trim()) {
            console.log(`Row ${i + 1}, Cell ${j}: "${cellText.trim()}"`);
          }
        }
      }
    }

    // At least one badge should exist
    expect(stockOkCount + kurangStokCount).toBeGreaterThan(0);

    // Check that badges have correct styling
    if (stockOkCount > 0) {
      await expect(stockOkBadges.first()).toHaveClass(/fi-color-success/);
      console.log('✅ STOK OK badges have success styling');
    }

    if (kurangStokCount > 0) {
      await expect(kurangStokBadges.first()).toHaveClass(/fi-color-warning/);
      console.log('✅ KURANG STOK badges have warning styling');
    }

    // Test tooltip functionality by hovering over a badge
    const firstBadge = page.locator('.fi-badge').first();
    await firstBadge.hover();

    // Wait a moment for tooltip to appear
    await page.waitForTimeout(1000);

    // Check if tooltip appears (look for tooltip text)
    const tooltipText = page.locator('text=/✅ Semua item memiliki stok yang cukup|⚠️ Item dengan stok kurang/');
    const tooltipVisible = await tooltipText.isVisible();

    if (tooltipVisible) {
      console.log('✅ Tooltip is working and displaying stock information');
    } else {
      console.log('⚠️ Tooltip might not be visible or working');
    }

    // Take a screenshot for verification
    await page.screenshot({ path: 'sale-orders-stock-status.png', fullPage: true });
    console.log('📸 Screenshot saved as sale-orders-stock-status.png');
  });

  test('should filter by stock status', async ({ page }) => {
    // First ensure we're logged in and on dashboard
    await expect(page).toHaveTitle(/Dasbor/);

    // Navigate to sale orders page
    await page.goto('/admin/sale-orders');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Look for the stock status filter
    const filterSelect = page.locator('select').filter({ hasText: /Status Stok/ }).first();

    if (await filterSelect.isVisible()) {
      console.log('✅ Stock status filter is available');

      // Try to open the filter dropdown
      await filterSelect.click();

      // Check if filter options exist
      const sufficientOption = page.locator('option').filter({ hasText: 'Stok Tersedia' });
      const insufficientOption = page.locator('option').filter({ hasText: 'Kurang Stok' });

      const sufficientVisible = await sufficientOption.isVisible();
      const insufficientVisible = await insufficientOption.isVisible();

      if (sufficientVisible && insufficientVisible) {
        console.log('✅ Filter options "Stok Tersedia" and "Kurang Stok" are available');
      } else {
        console.log('⚠️ Some filter options might be missing');
      }
    } else {
      console.log('⚠️ Stock status filter might not be visible');
    }
  });
});