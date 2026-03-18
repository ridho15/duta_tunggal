/**
 * order-request-approve-multi-supplier.spec.js
 *
 * Verifies that when the Approve modal is opened for a request_approve OR,
 * each item in the repeater shows:
 *   - product_name  (read-only)
 *   - supplier_name (read-only, from item's own supplier)
 *   - uom / satuan  (read-only, from product uom)
 *   - quantity, unit_price, original_price, etc.
 *
 * Uses OR #3 (OR-TEST-C-MULTISUPPLIER), which contains items from 2 suppliers.
 */

import { test, expect } from '@playwright/test';

const OR_ID = 3;

// Expected values per item (indices 0..2)
const ITEMS = [
  {
    productName:  'Panel Kontrol Industri',
    supplierName: 'PT Supplier Utama',
    uom:          'pcs',
    qty:          '5',
  },
  {
    productName:  'Sensor Tekanan Digital',
    supplierName: 'CV Distributor Jaya',
    uom:          'pcs',
    qty:          '3',
  },
  {
    productName:  'Bahan Baku Plastik Granul',
    supplierName: 'PT Supplier Utama',
    uom:          'pcs',
    qty:          '20',
  },
];

/**
 * Open the Approve action modal for the given table row.
 * Filament teleports dropdown panels to <body>.
 */
async function openApproveModal(page, targetRow) {
  // Click the page-header Approve button (visible on ViewRecord page)
  // OR click the row 3-dot trigger if on list page
  const approveBtn = page.locator('button').filter({ hasText: /^\s*Approve\s*$/ }).first();
  const btnCount = await approveBtn.count();

  if (btnCount > 0) {
    await approveBtn.waitFor({ state: 'visible', timeout: 10_000 });
    await approveBtn.click();
  } else {
    if (!targetRow) {
      throw new Error('Approve button not visible on OR view page.');
    }

    // Try via row dropdown trigger
    const dropdownTrigger = targetRow.locator('.fi-dropdown-trigger').first();
    await dropdownTrigger.waitFor({ state: 'visible', timeout: 10_000 });
    await dropdownTrigger.click();
    await page.waitForTimeout(600);

    const approveItem = page.locator('.fi-dropdown-list-item:visible').filter({
      has: page.locator('.fi-dropdown-list-item-label', { hasText: /^\s*Approve\s*$/ }),
    }).first();
    await approveItem.waitFor({ state: 'visible', timeout: 10_000 });
    await approveItem.click();
  }

  const modalContainer = page.locator('.fi-modal-open').first();
  const modalWindow = modalContainer.locator('.fi-modal-window').first();
  await modalWindow.waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_200);

  return modalContainer;
}

test.describe('Order Request #5 Approve Modal — multi-supplier & satuan per item', () => {

  test.beforeEach(async ({ page }) => {
    // Navigate directly to deterministic OR view page
    await page.goto(`/admin/order-requests/${OR_ID}`);
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login/, { timeout: 5_000 });
  });

  test('Approve modal shows supplier_name and uom per item', async ({ page }) => {
    const jsErrors = [];
    page.on('pageerror', err => jsErrors.push(err.message));

    const modal = await openApproveModal(page, null);

    // ── Locate all repeater items ──────────────────────────────────────────
    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();
    console.log('Repeater item count:', itemCount);
    expect(itemCount, 'Should have 3 repeater items for OR #3').toBe(3);

    for (let i = 0; i < ITEMS.length; i++) {
      const expected = ITEMS[i];
      const row = repeaterItems.nth(i);

      // product_name
      const productVal = await row.locator('input[id*="product_name"]').first().inputValue();
      console.log(`Item ${i} product_name:`, productVal);
      expect(productVal, `Item ${i}: product_name should contain "${expected.productName}"`).toContain(expected.productName);

      // supplier_name
      const supplierVal = await row.locator('input[id*="supplier_name"]').first().inputValue();
      console.log(`Item ${i} supplier_name:`, supplierVal);
      expect(supplierVal, `Item ${i}: supplier_name should contain "${expected.supplierName}"`).toContain(expected.supplierName);

      // uom / satuan
      const uomVal = await row.locator('input[id*="uom"]').first().inputValue();
      console.log(`Item ${i} uom:`, uomVal);
      expect(uomVal, `Item ${i}: uom should be "${expected.uom}"`).toBe(expected.uom);

      // quantity
      const qtyVal = await row.locator('input[id*="quantity"]').first().inputValue();
      console.log(`Item ${i} quantity:`, qtyVal);
      expect(qtyVal, `Item ${i}: quantity should be "${expected.qty}"`).toBe(expected.qty);
    }

    // Item 0 and item 1 should have different suppliers
    const supplier0 = await repeaterItems.nth(0).locator('input[id*="supplier_name"]').first().inputValue();
    const supplier1 = await repeaterItems.nth(1).locator('input[id*="supplier_name"]').first().inputValue();
    expect(supplier0, 'Items should have different suppliers').not.toBe(supplier1);
    console.log('Confirmed: item 0 supplier:', supplier0, '≠ item 1 supplier:', supplier1);

    // No blocking JS errors
    const blockingErrors = jsErrors.filter(e =>
      !e.includes('favicon') && !e.includes('chunk') && !e.includes('Loading chunk')
    );
    if (blockingErrors.length) console.warn('JS errors:', blockingErrors);
    expect(blockingErrors).toHaveLength(0);
  });

  test('supplier_name fields are not empty in approve modal', async ({ page }) => {
    const modal = await openApproveModal(page, null);

    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();
    expect(itemCount).toBeGreaterThan(0);

    for (let i = 0; i < itemCount; i++) {
      const supplierVal = await repeaterItems.nth(i).locator('input[id*="supplier_name"]').first().inputValue();
      console.log(`Item ${i} supplier_name (non-empty check):`, supplierVal);
      expect(supplierVal.trim(), `Item ${i}: supplier_name should not be empty`).not.toBe('');
      expect(supplierVal.trim(), `Item ${i}: supplier_name should not be '-'`).not.toBe('-');
    }
  });

  test('uom fields are not empty in approve modal', async ({ page }) => {
    const modal = await openApproveModal(page, null);

    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();
    expect(itemCount).toBeGreaterThan(0);

    for (let i = 0; i < itemCount; i++) {
      const uomVal = await repeaterItems.nth(i).locator('input[id*="uom"]').first().inputValue();
      console.log(`Item ${i} uom (non-empty check):`, uomVal);
      expect(uomVal.trim(), `Item ${i}: uom should not be empty`).not.toBe('');
      expect(uomVal.trim(), `Item ${i}: uom should not be '-'`).not.toBe('-');
    }
  });

  test('Approve modal shows correct supplier for item 0 (PT Supplier Utama)', async ({ page }) => {
    const modal = await openApproveModal(page, null);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');

    const supplierVal = await repeaterItems.nth(0).locator('input[id*="supplier_name"]').first().inputValue();
    console.log('Item 0 supplier:', supplierVal);
    expect(supplierVal).toContain('PT Supplier Utama');
  });

  test('Approve modal shows correct supplier for item 1 (CV Distributor Jaya)', async ({ page }) => {
    const modal = await openApproveModal(page, null);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');

    const supplierVal = await repeaterItems.nth(1).locator('input[id*="supplier_name"]').first().inputValue();
    console.log('Item 1 supplier:', supplierVal);
    expect(supplierVal).toContain('CV Distributor Jaya');
  });

});
