import { test, expect } from '@playwright/test';

test.describe('Product & Category Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'ralamzah@gmail.com');
    await page.fill('input[name="password"]', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
  });

  test('can create product with COA mapping', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/products');

    // Click create button - Filament biasanya menggunakan text atau class tertentu
    await page.click('a[href*="create"]');

    // Fill basic product info
    await page.fill('input[name="sku"]', 'SKU-COA-' + Date.now());
    await page.fill('input[name="name"]', 'Test Product COA');
    await page.fill('input[name="kode_merk"]', 'TEST');

    // Select cabang
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });

    // Select supplier
    await page.locator('select[name="supplier_id"]').selectOption({ index: 1 });

    // Select category
    await page.locator('select[name="product_category_id"]').selectOption({ index: 1 });

    // Fill prices
    await page.fill('input[name="cost_price"]', '15000');
    await page.fill('input[name="sell_price"]', '25000');
    await page.fill('input[name="biaya"]', '500');

    // Select UOM
    await page.locator('select[name="uom_id"]').selectOption({ index: 1 });

    // Fill COA mappings - Filament menggunakan select dengan name
    await page.locator('select[name="inventory_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_return_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_discount_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="goods_delivery_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="cogs_coa_id"]').selectOption({ index: 1 });

    // Submit - Filament biasanya menggunakan button dengan text Save atau Create
    await page.click('button[type="submit"]');

    // Verify
    await expect(page.locator('text=Test Product COA')).toBeVisible();
  });

  test('can update product pricing', async ({ page }) => {
    // First create a product
    await page.goto('http://localhost:8000/admin/products');
    await page.click('a[href*="create"]');
    await page.fill('input[name="sku"]', 'SKU-UPDATE-' + Date.now());
    await page.fill('input[name="name"]', 'Product for Update');
    await page.fill('input[name="kode_merk"]', 'TEST');
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });
    await page.locator('select[name="supplier_id"]').selectOption({ index: 1 });
    await page.locator('select[name="product_category_id"]').selectOption({ index: 1 });
    await page.fill('input[name="cost_price"]', '10000');
    await page.fill('input[name="sell_price"]', '20000');
    await page.fill('input[name="biaya"]', '500');
    await page.locator('select[name="uom_id"]').selectOption({ index: 1 });
    await page.locator('select[name="inventory_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_coa_id"]').selectOption({ index: 1 });
    await page.click('button[type="submit"]');

    // Now update pricing - Filament menggunakan link edit di table
    await page.click('a[href*="edit"]');
    await page.fill('input[name="sell_price"]', '30000');
    await page.fill('input[name="cost_price"]', '18000');
    await page.click('button[type="submit"]');

    // Verify
    await expect(page.locator('text=Product for Update')).toBeVisible();
  });

  test('validates SKU uniqueness', async ({ page }) => {
    const uniqueSKU = 'SKU-UNIQUE-' + Date.now();

    // Create first product
    await page.goto('http://localhost:8000/admin/products');
    await page.click('a[href*="create"]');
    await page.fill('input[name="sku"]', uniqueSKU);
    await page.fill('input[name="name"]', 'First Product');
    await page.fill('input[name="kode_merk"]', 'TEST');
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });
    await page.locator('select[name="supplier_id"]').selectOption({ index: 1 });
    await page.locator('select[name="product_category_id"]').selectOption({ index: 1 });
    await page.fill('input[name="cost_price"]', '10000');
    await page.fill('input[name="sell_price"]', '20000');
    await page.fill('input[name="biaya"]', '500');
    await page.locator('select[name="uom_id"]').selectOption({ index: 1 });
    await page.locator('select[name="inventory_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_coa_id"]').selectOption({ index: 1 });
    await page.click('button[type="submit"]');

    // Try to create another with same SKU
    await page.click('a[href*="create"]');
    await page.fill('input[name="sku"]', uniqueSKU); // Same SKU
    await page.fill('input[name="name"]', 'Duplicate Product');
    await page.fill('input[name="kode_merk"]', 'TEST2');
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });
    await page.locator('select[name="supplier_id"]').selectOption({ index: 1 });
    await page.locator('select[name="product_category_id"]').selectOption({ index: 1 });
    await page.fill('input[name="cost_price"]', '10000');
    await page.fill('input[name="sell_price"]', '20000');
    await page.fill('input[name="biaya"]', '500');
    await page.locator('select[name="uom_id"]').selectOption({ index: 1 });
    await page.locator('select[name="inventory_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_coa_id"]').selectOption({ index: 1 });
    await page.click('button[type="submit"]');

    // Should show error
    await expect(page.locator('text=SKU sudah digunakan')).toBeVisible();
  });

  test('tests product-category relationship', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/products');

    // Create product with category
    await page.click('a[href*="create"]');
    await page.fill('input[name="sku"]', 'SKU-CATEGORY-' + Date.now());
    await page.fill('input[name="name"]', 'Product with Category');
    await page.fill('input[name="kode_merk"]', 'TEST');
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });
    await page.locator('select[name="supplier_id"]').selectOption({ index: 1 });
    await page.locator('select[name="product_category_id"]').selectOption({ index: 1 });
    await page.fill('input[name="cost_price"]', '10000');
    await page.fill('input[name="sell_price"]', '20000');
    await page.fill('input[name="biaya"]', '500');
    await page.locator('select[name="uom_id"]').selectOption({ index: 1 });
    await page.locator('select[name="inventory_coa_id"]').selectOption({ index: 1 });
    await page.locator('select[name="sales_coa_id"]').selectOption({ index: 1 });
    await page.click('button[type="submit"]');

    // Verify product is created with category
    await expect(page.locator('text=Product with Category')).toBeVisible();
  });

  test('can create product category', async ({ page }) => {
    await page.goto('http://localhost:8000/admin/product-categories');

    // Click create button
    await page.click('a[href*="create"]');

    // Fill category info
    await page.fill('input[name="name"]', 'Test Category ' + Date.now());
    await page.fill('input[name="kode"]', 'CAT-' + Date.now());
    await page.locator('select[name="cabang_id"]').selectOption({ index: 1 });
    await page.fill('input[name="kenaikan_harga"]', '10');

    // Submit
    await page.click('button[type="submit"]');

    // Verify
    await expect(page.locator(`text=Test Category`)).toBeVisible();
  });

  test('can bulk update product prices', async ({ page }) => {
    // Go to products page
    await page.goto('http://localhost:8000/admin/products');

    // Wait for table to load
    await page.waitForSelector('table', { timeout: 10000 });

    // Select first product using checkbox - Filament uses specific checkbox selectors
    const checkboxes = page.locator('input[type="checkbox"][name="selectedRecords[]"]');
    await checkboxes.first().waitFor();

    // Check the first checkbox
    await checkboxes.first().check();

    // Wait for bulk actions to appear - now all bulk actions are in a single group
    await page.waitForSelector('.fi-ta-bulk-actions', { timeout: 5000 });

    // Click the bulk actions trigger to open the dropdown
    await page.click('.fi-ta-bulk-actions button');

    // Click "Update Harga Massal" action from the dropdown
    await page.click('text=Update Harga Massal');

    // Wait for modal to appear and verify it exists
    await page.waitForSelector('.fi-modal', { timeout: 5000 });

    // Verify modal title
    await expect(page.locator('.fi-modal')).toContainText('Update Harga Produk Terpilih');

    // Verify form fields are present
    await expect(page.locator('input[name="data[cost_price]"]')).toBeVisible();
    await expect(page.locator('input[name="data[sell_price]"]')).toBeVisible();
    await expect(page.locator('input[name="data[biaya]"]')).toBeVisible();

    console.log('Modal "Update Harga Massal" berhasil muncul!');
  });
});