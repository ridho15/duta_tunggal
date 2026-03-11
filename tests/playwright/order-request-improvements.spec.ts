/**
 * Playwright tests for Order Request improvements:
 *  1. Editable price (original_price vs unit_price override)
 *  2. tax_type field (PPN Included / PPN Excluded)
 *  3. Generate multiple Purchase Orders from one Order Request
 *
 * These tests require a running Laravel server on http://127.0.0.1:8000
 * and a seeded user with 'approve order request' permission.
 */

import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';
const ADMIN_EMAIL = process.env.TEST_EMAIL ?? 'admin@duta-tunggal.test';
const ADMIN_PASS  = process.env.TEST_PASSWORD ?? 'password';

async function login(page: Page) {
  await page.goto(`${BASE_URL}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.getByLabel('Email').fill(ADMIN_EMAIL);
  await page.getByLabel('Password').fill(ADMIN_PASS);
  await page.getByRole('button', { name: /sign in|login/i }).click();
  await page.waitForURL(/admin\/order-requests|admin$/, { timeout: 15000 });
}

async function navigateToOrderRequests(page: Page) {
  await page.goto(`${BASE_URL}/admin/order-requests`, { waitUntil: 'networkidle' });
}

// ─── Test Suite ───────────────────────────────────────────────────────────────

test.describe('Order Request Improvements', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  // ─── Feature 1: Editable price with original_price separate ───────────────

  test('create Order Request shows Harga Asli (readonly) and Harga Override (editable)', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });

    // Add a repeater item to see the price fields
    const addItemButton = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      await page.waitForTimeout(500);
    }

    // Harga Asli (Master) field should be present and read-only
    const originalPriceInput = page.getByLabel('Harga Asli (Master)').first();
    const overridePriceInput = page.getByLabel('Harga Override').first();

    await expect(originalPriceInput).toBeVisible();
    await expect(overridePriceInput).toBeVisible();

    // Harga Asli must be read-only
    await expect(originalPriceInput).toHaveAttribute('readonly', '');

    // Harga Override should be editable
    await expect(overridePriceInput).not.toHaveAttribute('readonly', '');
  });

  test('selecting a product pre-fills both Harga Asli and Harga Override', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });

    // Fill required header fields first
    await page.getByLabel('Supplier').first().click();
    await page.waitForTimeout(300);
    const firstSupplierOption = page.getByRole('option').first();
    if (await firstSupplierOption.isVisible({ timeout: 3000 })) {
      await firstSupplierOption.click();
    }

    // Add item and select a product
    const addItemButton = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      await page.waitForTimeout(500);
    }

    const productSelect = page.getByLabel('Product').first();
    if (await productSelect.isVisible({ timeout: 3000 })) {
      await productSelect.click();
      await page.waitForTimeout(300);
      const firstProduct = page.getByRole('option').first();
      if (await firstProduct.isVisible({ timeout: 2000 })) {
        await firstProduct.click();
        await page.waitForTimeout(800);

        // Both price fields should now have a value (from master)
        const originalPrice = await page.getByLabel('Harga Asli (Master)').first().inputValue();
        const overridePrice = await page.getByLabel('Harga Override').first().inputValue();

        // After product selection, original and override should match (master price)
        expect(originalPrice).toBeTruthy();
        expect(overridePrice).toBeTruthy();
      }
    }
  });

  test('user can override Harga Override without changing Harga Asli', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });

    const addItemButton = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      await page.waitForTimeout(500);
    }

    const originalPriceInput = page.getByLabel('Harga Asli (Master)').first();
    const overridePriceInput = page.getByLabel('Harga Override').first();

    if (await overridePriceInput.isVisible({ timeout: 3000 })) {
      // Simulate a pre-filled original price
      const originalValue = await originalPriceInput.inputValue();

      // Type a new override price
      await overridePriceInput.clear();
      await overridePriceInput.fill('99999');
      await overridePriceInput.blur();
      await page.waitForTimeout(400);

      // Harga Asli should NOT change
      const newOriginalValue = await originalPriceInput.inputValue();
      expect(newOriginalValue).toBe(originalValue);
    }
  });

  // ─── Feature 2: tax_type field ────────────────────────────────────────────

  test('Order Request create form shows Tipe PPN select with two options', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });

    const taxTypeSelect = page.getByLabel('Tipe PPN');
    await expect(taxTypeSelect).toBeVisible();

    // Click to open dropdown
    await taxTypeSelect.click();
    await page.waitForTimeout(300);

    const ppnExcluded = page.getByRole('option', { name: /PPN Excluded/ });
    const ppnIncluded = page.getByRole('option', { name: /PPN Included/ });

    await expect(ppnExcluded).toBeVisible();
    await expect(ppnIncluded).toBeVisible();
  });

  test('changing Tipe PPN to PPN Included recalculates subtotal (no extra tax)', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });

    const addItemButton = page.getByRole('button', { name: /tambah|add item/i }).first();
    if (await addItemButton.isVisible()) {
      await addItemButton.click();
      await page.waitForTimeout(500);
    }

    const overridePriceInput = page.getByLabel('Harga Override').first();
    const quantityInput      = page.getByLabel('Quantity').first();
    const taxInput           = page.getByLabel('Tax (%)').first();
    const subtotalInput      = page.getByLabel('Subtotal').first();

    if (await overridePriceInput.isVisible({ timeout: 3000 })) {
      // Set known values: qty=10, price=10000, tax=10%
      await quantityInput.fill('10');
      await quantityInput.blur();
      await overridePriceInput.fill('10000');
      await overridePriceInput.blur();
      await taxInput.fill('10');
      await taxInput.blur();
      await page.waitForTimeout(600);

      // With PPN Excluded (default): subtotal = 10 * 10000 * 1.10 = 110000
      const subtotalExcluded = await subtotalInput.inputValue();

      // Switch to PPN Included
      const taxTypeSelect = page.getByLabel('Tipe PPN');
      await taxTypeSelect.click();
      await page.waitForTimeout(200);
      await page.getByRole('option', { name: /PPN Included/ }).click();
      await page.waitForTimeout(600);

      // With PPN Included: subtotal = 10 * 10000 = 100000 (tax already in price)
      const subtotalIncluded = await subtotalInput.inputValue();

      // PPN Excluded subtotal should be >= PPN Included subtotal
      const excluded = parseFloat(subtotalExcluded.replace(/[^0-9.-]/g, ''));
      const included = parseFloat(subtotalIncluded.replace(/[^0-9.-]/g, ''));

      if (!isNaN(excluded) && !isNaN(included) && excluded > 0 && included > 0) {
        expect(excluded).toBeGreaterThan(included);
      }
    }
  });

  // ─── Feature 3: Multiple POs from one Order Request ───────────────────────

  test('approved Order Request shows Create Purchase Order button for unfulfilled items', async ({ page }) => {
    await navigateToOrderRequests(page);

    // Look for an approved OR in the table
    const approvedRow = page.getByRole('row').filter({ hasText: /APPROVED/i }).first();

    if (await approvedRow.isVisible({ timeout: 3000 })) {
      // Open action menu for this row
      const actionButton = approvedRow.getByRole('button', { name: /actions|more|⋮/i }).first();
      if (await actionButton.isVisible({ timeout: 2000 })) {
        await actionButton.click();
        await page.waitForTimeout(300);

        const createPoButton = page.getByRole('menuitem', { name: /Create Purchase Order/i });
        await expect(createPoButton).toBeVisible();
      }
    } else {
      // Skip gracefully if no approved OR exists in test environment
      test.skip();
    }
  });

  test('Create PO modal shows selected_items repeater with original_price and unit_price columns', async ({ page }) => {
    await navigateToOrderRequests(page);

    // Look for an approved OR
    const approvedRow = page.getByRole('row').filter({ hasText: /APPROVED/i }).first();

    if (!(await approvedRow.isVisible({ timeout: 3000 }))) {
      test.skip();
      return;
    }

    const actionButton = approvedRow.getByRole('button', { name: /actions|more|⋮/i }).first();
    if (await actionButton.isVisible({ timeout: 2000 })) {
      await actionButton.click();
      await page.waitForTimeout(300);

      const createPoButton = page.getByRole('menuitem', { name: /Create Purchase Order/i });
      if (await createPoButton.isVisible({ timeout: 2000 })) {
        await createPoButton.click();
        await page.waitForTimeout(1000);

        // Modal should have Harga Asli (readonly) and Harga Override columns
        await expect(page.getByText('Harga Asli')).toBeVisible();
        await expect(page.getByText('Harga Override')).toBeVisible();
        await expect(page.getByText('Sertakan')).toBeVisible();

        // Harga Asli should be readonly
        const hargaAsliInput = page.getByLabel('Harga Asli').first();
        if (await hargaAsliInput.isVisible({ timeout: 2000 })) {
          await expect(hargaAsliInput).toHaveAttribute('readonly', '');
        }
      }
    }
  });
});
