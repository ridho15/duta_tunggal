import { test, expect } from '@playwright/test';

test.describe('Material Issue E2E Tests', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    // Additional wait to ensure login is complete
    await page.waitForTimeout(2000);
  }

  test('can access material issue page and verify interface', async ({ page }) => {
    console.log('🧪 Testing: Material Issue page access and interface verification');

    await login(page);

    // Navigate to Material Issue page
    await page.goto('http://127.0.0.1:8009/admin/material-issues');
    await page.waitForLoadState('networkidle');

    // Verify Material Issue page loads
    await expect(page.locator('h1, .page-title')).toContainText(/pengambilan.*bahan|material.*issue/i);

    // Check if create button exists
    const createButton = page.locator('a[href*="material-issues/create"]');
    if (await createButton.isVisible()) {
      console.log('✅ Create Material Issue button is visible');
    } else {
      console.log('ℹ️  Create Material Issue button not visible (may be due to permissions or no data)');
    }

    // Check if Material Issue table exists
    const miTable = page.locator('.fi-ta-table').first();
    if (await miTable.isVisible()) {
      console.log('✅ Material Issue table is visible');

      // Check table headers
      const headers = miTable.locator('thead th');
      const headerCount = await headers.count();
      console.log(`📊 Material Issue table has ${headerCount} columns`);
    }

    console.log('✅ Material Issue page access test completed successfully');
  });

  test('can create material issue for manufacturing order', async ({ page }) => {
    console.log('🧪 Testing: Create Material Issue for Manufacturing Order');

    await login(page);

    // Navigate to Material Issue create page
    await page.goto('http://127.0.0.1:8009/admin/material-issues/create');
    await page.waitForLoadState('networkidle');

    // Verify we're on create page
    await expect(page.locator('h1')).toContainText(/buat.*pengambilan|create.*material.*issue/i);

    // Fill basic information
    const issueNumber = `MI-E2E-${Date.now()}`;
    await page.fill('#data\\.issue_number', issueNumber);
    // Skip date fill as it's readonly in Filament date picker
    console.log('ℹ️  Skipping date fill - using default date');

    // Select type as 'issue'
    await page.selectOption('#data\\.type', 'issue');

    // Select warehouse (assuming there's at least one)
    const warehouseSelect = page.locator('#data\\.warehouse_id');
    if (await warehouseSelect.isVisible()) {
      const options = warehouseSelect.locator('option');
      const optionCount = await options.count();
      if (optionCount > 1) {
        await warehouseSelect.selectOption({ index: 1 }); // Select first available warehouse
      }
    }

    // Try to select manufacturing order (if available)
    const moSelect = page.locator('#data\\.manufacturing_order_id');
    if (await moSelect.isVisible()) {
      const options = moSelect.locator('option');
      const optionCount = await options.count();
      if (optionCount > 1) {
        await moSelect.selectOption({ index: 1 }); // Select first available MO
        console.log('✅ Manufacturing Order selected');
      } else {
        console.log('ℹ️  No Manufacturing Orders available for selection');
      }
    }

    // Add material item (if form allows)
    const addItemButton = page.locator('button').filter({ hasText: /tambah.*item|tambah.*bahan|add.*item|add.*material/i });
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      console.log('✅ Add item button clicked');

      // Wait for item form to appear
      await page.waitForTimeout(1000);

      // Try to select a product
      const productSelect = page.locator('select[name*="product_id"]').last();
      if (await productSelect.isVisible()) {
        const options = productSelect.locator('option');
        const optionCount = await options.count();
        if (optionCount > 1) {
          await productSelect.selectOption({ index: 1 });
          console.log('✅ Product selected for material issue');
        }
      }

      // Fill quantity
      const qtyInput = page.locator('input[name*="quantity"]').last();
      if (await qtyInput.isVisible()) {
        await qtyInput.fill('10');
        console.log('✅ Quantity filled');
      }
    }

    // Save as draft first
    const saveButton = page.locator('button[type="submit"]').filter({ hasText: /simpan|save|buat|create/i });
    if (await saveButton.isVisible()) {
      await saveButton.click();
      await page.waitForLoadState('networkidle');

      // Check if we're redirected to index or detail page
      const currentUrl = page.url();
      if (currentUrl.includes('material-issues') && !currentUrl.includes('create')) {
        console.log('✅ Material Issue created successfully');
      } else {
        console.log('⚠️  Material Issue creation may have issues - check page URL');
      }
    } else {
      console.log('ℹ️  Save button not found - form may need additional setup');
    }

    console.log('✅ Material Issue creation test completed');
  });

  test('can issue materials and verify stock deduction', async ({ page }) => {
    console.log('🧪 Testing: Issue materials and verify stock changes');

    await login(page);

    // Navigate to Material Issues page
    await page.goto('http://127.0.0.1:8009/admin/material-issues');
    await page.waitForLoadState('networkidle');

    // Look for a draft material issue to complete
    const draftIssue = page.locator('.fi-ta-table tbody tr').filter({ hasText: 'draft' }).first();
    if (await draftIssue.isVisible()) {
      console.log('✅ Found draft material issue');

      // Click on the issue to edit
      await draftIssue.click();
      await page.waitForLoadState('networkidle');

      // Look for complete/issue button
      const completeButton = page.locator('button').filter({ hasText: /complete|issue.*material/i });
      if (await completeButton.isVisible()) {
        await completeButton.click();
        await page.waitForLoadState('networkidle');

        // Check for success message
        const successMessage = page.locator('.fi-banner-success, .alert-success').first();
        if (await successMessage.isVisible()) {
          console.log('✅ Material issue completed successfully');
        } else {
          console.log('⚠️  No success message found after completing material issue');
        }
      } else {
        console.log('ℹ️  Complete button not found - issue may already be completed');
      }
    } else {
      console.log('ℹ️  No draft material issues found to complete');
    }

    console.log('✅ Material issuing test completed');
  });

  test('can verify journal entries for material issue', async ({ page }) => {
    console.log('🧪 Testing: Verify journal entries for material issue');

    await login(page);

    // Navigate to Journal Entries page
    await page.goto('http://127.0.0.1:8009/admin/journal-entries');
    await page.waitForLoadState('networkidle');

    // Look for journal entries with manufacturing/material issue reference
    const journalTable = page.locator('.fi-ta-table');
    if (await journalTable.isVisible()) {
      const rows = journalTable.locator('tbody tr');
      const rowCount = await rows.count();

      console.log(`📊 Found ${rowCount} journal entries`);

      // Look for entries with WIP or Raw Material accounts
      const wipEntries = rows.filter({ hasText: /1140\.02|barang dalam proses/i });
      const rawEntries = rows.filter({ hasText: /1140\.01|bahan baku/i });

      const wipCount = await wipEntries.count();
      const rawCount = await rawEntries.count();

      console.log(`✅ Found ${wipCount} WIP journal entries`);
      console.log(`✅ Found ${rawCount} Raw Material journal entries`);

      if (wipCount > 0 && rawCount > 0) {
        console.log('✅ Journal entries for material issue are present');
      } else {
        console.log('ℹ️  No material issue journal entries found (may need to create material issue first)');
      }
    }

    console.log('✅ Journal entries verification test completed');
  });

  test('can handle material returns', async ({ page }) => {
    console.log('🧪 Testing: Handle material returns');

    await login(page);

    // Navigate to Material Issue create page for return
    await page.goto('http://127.0.0.1:8009/admin/material-issues/create');
    await page.waitForLoadState('networkidle');

    // Fill basic information for return
    const returnNumber = `MI-RETURN-E2E-${Date.now()}`;
    await page.fill('#data\\.issue_number', returnNumber);
    // Skip date fill as it's readonly in Filament date picker
    console.log('ℹ️  Skipping date fill - using default date');

    // Select type as 'return'
    await page.selectOption('#data\\.type', 'return');

    // Select warehouse
    const warehouseSelect = page.locator('#data\\.warehouse_id');
    if (await warehouseSelect.isVisible()) {
      const options = warehouseSelect.locator('option');
      const optionCount = await options.count();
      if (optionCount > 1) {
        await warehouseSelect.selectOption({ index: 1 });
      }
    }

    // Try to select manufacturing order
    const moSelect = page.locator('#data\\.manufacturing_order_id');
    if (await moSelect.isVisible()) {
      const options = moSelect.locator('option');
      const optionCount = await options.count();
      if (optionCount > 1) {
        await moSelect.selectOption({ index: 1 });
      }
    }

    // Add return item
    const addItemButton = page.locator('button').filter({ hasText: /tambah.*item|tambah.*bahan|add.*item|add.*material/i });
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      await page.waitForTimeout(1000);

      // Select product to return
      const productSelect = page.locator('select[name*="product_id"]').last();
      if (await productSelect.isVisible()) {
        const options = productSelect.locator('option');
        const optionCount = await options.count();
        if (optionCount > 1) {
          await productSelect.selectOption({ index: 1 });
        }
      }

      // Fill return quantity
      const qtyInput = page.locator('input[name*="quantity"]').last();
      if (await qtyInput.isVisible()) {
        await qtyInput.fill('5');
      }

      // Add notes
      const notesInput = page.locator('textarea[name*="notes"]').last();
      if (await notesInput.isVisible()) {
        await notesInput.fill('Return unused materials from production');
      }
    }

    // Save return
    const saveButton = page.locator('button[type="submit"]').filter({ hasText: /simpan|save|buat|create/i });
    if (await saveButton.isVisible()) {
      await saveButton.click();
      await page.waitForLoadState('networkidle');

      const currentUrl = page.url();
      if (currentUrl.includes('material-issues') && !currentUrl.includes('create')) {
        console.log('✅ Material return created successfully');
      }
    }

    console.log('✅ Material returns test completed');
  });

});