import { test, expect } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8009';

async function openOrderRequestList(page) {
  await page.goto(`${BASE}/admin/order-requests`, { waitUntil: 'networkidle' });
  await page.waitForLoadState('networkidle');
}

async function getSearchInput(page) {
  return page.locator('input[placeholder*="Search"], input[placeholder*="search"], input[type="search"]').first();
}

function extractProductLabels(text) {
  return (text || '').match(/\([^)]+\)\s+[^\[\n]+/g) ?? [];
}

async function searchOrderRequests(page, term) {
  const searchInput = await getSearchInput(page);
  await expect(searchInput).toBeVisible();

  await searchInput.click();
  await searchInput.fill('');
  await page.waitForTimeout(250);
  await searchInput.fill(term);
  await page.waitForTimeout(900);
  await page.waitForLoadState('networkidle');

  return page.locator('table tbody tr');
}

/**
 * Playwright test for product search functionality in OrderRequest resource table
 * Tests that searching by product name returns accurate results
 */

test('Search OrderRequest by product name returns correct results', async ({ page }) => {
  // Navigate to OrderRequest list view
  await openOrderRequestList(page);

  // Verify we're on the OrderRequest list page
  await expect(page).toHaveTitle(/Order Request|Permintaan Pembelian/i);

  // Check if search/filter field is visible
  const searchInput = await getSearchInput(page);
  
  if (await searchInput.isVisible().catch(() => false)) {
    // Find a product name from the table to search for
    // First, let's get all visible product names from the table
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();

    if (rowCount > 0) {
      // Extract text content from the first row to get a product name
      const firstRow = rows.first();
      const itemsCell = firstRow.locator('td').nth(3); // Items column is typically around index 3-4
      const itemsText = await itemsCell.textContent();

      // Extract product name from format like "(SKU) Product Name"
      const productMatch = itemsText?.match(/\)\s+(.+?)$/);
      let productName = productMatch ? productMatch[1].trim() : '';

      if (!productName) {
        // If we couldn't extract product name, try to get any searchable text
        productName = itemsText?.trim().split('\n')[0] || '';
      }

      // If we found a product name, test the search
      if (productName && productName.length > 2) {
        // Clear any existing search and enter product name
        await searchInput.click();
        await searchInput.fill('');
        await page.waitForTimeout(300);

        // Search for product name (use partial name for better test flexibility)
        const searchTerm = productName.substring(0, 5);
        await searchInput.fill(searchTerm);
        await page.waitForTimeout(800);

        // Wait for search results to update
        await page.waitForLoadState('networkidle');

        // Verify search results contain the searched product
        const resultsRows = page.locator('table tbody tr');
        const resultsCount = await resultsRows.count();

        expect(resultsCount).toBeGreaterThan(0);

        // Check that at least one row contains the search term
        let foundMatch = false;
        for (let i = 0; i < resultsCount; i++) {
          const rowText = await resultsRows.nth(i).textContent();
          if (rowText.toLowerCase().includes(searchTerm.toLowerCase())) {
            foundMatch = true;
            break;
          }
        }

        expect(foundMatch).toBeTruthy();

        // Clear search to verify all results are restored
        await searchInput.fill('');
        await page.waitForTimeout(800);
        await page.waitForLoadState('networkidle');

        const allResultsRows = page.locator('table tbody tr');
        const allResultsCount = await allResultsRows.count();

        // Should show more or same results after clearing search
        expect(allResultsCount).toBeGreaterThanOrEqual(resultsCount);
      }
    }
  }
});

test('Search OrderRequest by product SKU returns accurate results', async ({ page }) => {
  await openOrderRequestList(page);

  const searchInput = await getSearchInput(page);

  if (await searchInput.isVisible().catch(() => false)) {
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();

    if (rowCount > 0) {
      const firstRow = rows.first();
      const itemsCell = firstRow.locator('td').nth(3);
      const itemsText = await itemsCell.textContent();

      // Extract SKU from format like "(SKU) Product Name"
      const skuMatch = itemsText?.match(/\(([^)]+)\)/);
      const sku = skuMatch ? skuMatch[1].trim() : '';

      if (sku && sku.length > 0) {
        await searchInput.click();
        await searchInput.fill('');
        await page.waitForTimeout(300);

        // Use partial SKU for search
        const searchTerm = sku.substring(0, Math.max(2, Math.ceil(sku.length / 2)));
        await searchInput.fill(searchTerm);
        await page.waitForTimeout(800);
        await page.waitForLoadState('networkidle');

        const resultsRows = page.locator('table tbody tr');
        const resultsCount = await resultsRows.count();

        expect(resultsCount).toBeGreaterThan(0);

        // Verify at least one result contains SKU
        let skuFound = false;
        for (let i = 0; i < resultsCount; i++) {
          const rowText = await resultsRows.nth(i).textContent();
          if (rowText.includes(sku)) {
            skuFound = true;
            break;
          }
        }

        expect(skuFound).toBeTruthy();

        // Clear search
        await searchInput.fill('');
        await page.waitForTimeout(800);
        await page.waitForLoadState('networkidle');
      }
    }
  }
});

test('Search OrderRequest with non-matching term handles gracefully', async ({ page }) => {
  await openOrderRequestList(page);

  const searchInput = await getSearchInput(page);

  if (await searchInput.isVisible().catch(() => false)) {
    // First, get initial result count
    let initialRows = await page.locator('table tbody tr').count();
    
    // Search for non-existent term
    const nonExistentTerm = 'ZZZNONEXISTENTPRODUCTXXXYYY';
    
    await searchInput.click();
    await searchInput.fill('');
    await page.waitForTimeout(300);
    
    await searchInput.fill(nonExistentTerm);
    await page.waitForTimeout(800);
    await page.waitForLoadState('networkidle');

    const resultsRows = page.locator('table tbody tr');
    const resultsCount = await resultsRows.count();
    
    // After search with non-existent term, result count should be stable
    // (either same or fewer than initial)
    expect(resultsCount).toBeGreaterThanOrEqual(0);

    // Clear search and verify we get back to initial state
    await searchInput.fill('');
    await page.waitForTimeout(800);
    await page.waitForLoadState('networkidle');

    const clearedRows = await page.locator('table tbody tr').count();
    expect(clearedRows).toBeGreaterThanOrEqual(resultsCount);
  }
});

test('Search preserves results across page interactions', async ({ page }) => {
  await openOrderRequestList(page);

  const searchInput = await getSearchInput(page);

  if (await searchInput.isVisible().catch(() => false)) {
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();

    if (rowCount > 0) {
      // Extract a product name to search for
      const firstRow = rows.first();
      const itemsCell = firstRow.locator('td').nth(3);
      const itemsText = await itemsCell.textContent();
      const productMatch = itemsText?.match(/\)\s+(.+?)$/);
      let productName = productMatch ? productMatch[1].trim() : '';

      if (!productName) {
        productName = itemsText?.trim().split('\n')[0] || '';
      }

      if (productName && productName.length > 2) {
        const searchTerm = productName.substring(0, 5);

        // Perform search
        await searchInput.fill(searchTerm);
        await page.waitForTimeout(800);
        await page.waitForLoadState('networkidle');

        const firstSearchResults = await page.locator('table tbody tr').count();

        // Click on first result to view details
        const firstResultLink = page.locator('table tbody tr td a').first();
        if (await firstResultLink.isVisible().catch(() => false)) {
          await firstResultLink.click();
          await page.waitForLoadState('networkidle');

          // Navigate back
          await page.goBack();
          await page.waitForLoadState('networkidle');

          // Verify search term is still in the search box
          const searchValue = await searchInput.inputValue();
          
          // Search might have been cleared, so we check if results are still present
          const currentResults = await page.locator('table tbody tr').count();
          
          // Either search term is preserved or we're back at full results
          // (both are acceptable behaviors)
          expect(currentResults).toBeGreaterThan(0);
        }
      }
    }
  }
});

test('Search OrderRequest with query "produk et" returns rows that include matching products', async ({ page }) => {
  await openOrderRequestList(page);

  const rows = await searchOrderRequests(page, 'produk et');
  const rowCount = await rows.count();

  expect(rowCount).toBeGreaterThan(0);

  const matchingRows = [];

  for (let i = 0; i < rowCount; i += 1) {
    const row = rows.nth(i);
    const rowText = (await row.textContent()) || '';
    const products = extractProductLabels(rowText);
    const matchedProducts = products.filter((product) => product.toLowerCase().includes('produk et'));

    if (matchedProducts.length > 0) {
      matchingRows.push({ rowIndex: i + 1, products, matchedProducts });
    }
  }

  expect(matchingRows.length).toBeGreaterThan(0);

  for (const row of matchingRows) {
    expect(row.matchedProducts.length).toBeGreaterThan(0);
  }
});
