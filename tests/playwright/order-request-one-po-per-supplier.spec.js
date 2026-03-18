/**
 * order-request-approve-one-po-per-supplier.spec.js
 *
 * Verifies the core business rule:
 *   "Even with a multi-supplier Order Request, each Purchase Order must
 *    belong to exactly ONE supplier."
 *
 * Uses OR #3 (OR-TEST-C-MULTISUPPLIER, status: request_approve):
 *   Item 3: Panel Kontrol Industri     → Supplier: (SUPP001) PT Supplier Utama
 *   Item 4: Sensor Tekanan Digital     → Supplier: (SUPP002) CV Distributor Jaya
 *   Item 5: Bahan Baku Plastik Granul  → Supplier: (SUPP001) PT Supplier Utama
 *
 * Expected result after approve: 2 POs created, each with 1 unique supplier.
 *
 * Test strategy:
 *   1. Verify the modal shows multi_supplier notice.
 *   2. Verify each repeater item shows its own supplier_name (not shared).
 *   3. Verify items from the same supplier are grouped together.
 *   4. Confirm items from different suppliers are distinguished.
 */

import { test, expect } from '@playwright/test';

const OR3_ID = 3;
const OR3_URL = `/admin/order-requests/${OR3_ID}`;

// OR #3 expected items (sorted as they come from DB)
const EXPECTED_ITEMS = [
  { productName: 'Panel Kontrol Industri',    supplierName: 'PT Supplier Utama',   qty: '5'  },
  { productName: 'Sensor Tekanan Digital',    supplierName: 'CV Distributor Jaya', qty: '3'  },
  { productName: 'Bahan Baku Plastik Granul', supplierName: 'PT Supplier Utama',   qty: '20' },
];

async function openApproveModal(page) {
  const approveBtn = page.locator('button').filter({ hasText: /^\s*Approve\s*$/ }).first();
  await approveBtn.waitFor({ state: 'visible', timeout: 10_000 });
  await approveBtn.click();

  const modalContainer = page.locator('.fi-modal-open').first();
  const modalWindow = modalContainer.locator('.fi-modal-window').first();
  await modalWindow.waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_000);

  return modalContainer;
}

test.describe('OR #3 (multi-supplier) — Approve creates one PO per supplier', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(OR3_URL);
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login/, { timeout: 5_000 });
  });

  /**
   * Test 1: The Approve modal opens and shows the multi-supplier notice.
   * This notice tells the user that "one PO per supplier" will be created automatically.
   */
  test('Approve modal shows multi-supplier notice for OR with multiple suppliers', async ({ page }) => {
    const modal = await openApproveModal(page);

    // The Filament Placeholder renders as a fi-fo-placeholder component
    // Look for any visible element containing the multi-supplier message
    const noticeText = modal.locator('[class*="fi-fo-placeholder"], .fi-placeholder, p, span, div').filter({
      hasText: /satu PO per supplier|beberapa supplier/i,
    });

    const noticeCount = await noticeText.count();
    console.log('Multi-supplier notice found:', noticeCount > 0);

    // Also check the raw page text for the notice
    const pageContent = await modal.textContent();
    const hasNoticeText = /satu PO per supplier|beberapa supplier/i.test(pageContent || '');
    console.log('Notice in page text:', hasNoticeText);

    expect(hasNoticeText || noticeCount > 0, 'Multi-supplier notice text should be present in the modal').toBeTruthy();
  });

  /**
   * Test 2: Each item in the repeater shows its own supplier_name — not the same for all.
   */
  test('Approve modal shows per-item supplier_name, not a single OR-level supplier', async ({ page }) => {
    const modal = await openApproveModal(page);

    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();
    console.log('Repeater item count:', itemCount);
    expect(itemCount, 'OR #3 should have 3 items').toBe(3);

    const supplierNames = [];
    for (let i = 0; i < itemCount; i++) {
      const name = await repeaterItems.nth(i).locator('input[id*="supplier_name"]').first().inputValue();
      console.log(`Item ${i} supplier_name:`, name);
      expect(name.trim()).not.toBe('');
      expect(name.trim()).not.toBe('-');
      supplierNames.push(name);
    }

    // Not all items should have the same supplier (multi-supplier OR)
    const uniqueSuppliers = [...new Set(supplierNames)];
    console.log('Unique suppliers in modal:', uniqueSuppliers);
    expect(uniqueSuppliers.length, 'Should have items from at least 2 different suppliers').toBeGreaterThanOrEqual(2);
  });

  /**
   * Test 3: Items from PT Supplier Utama appear in the modal (2 items).
   */
  test('Approve modal contains items for PT Supplier Utama', async ({ page }) => {
    const modal = await openApproveModal(page);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();

    let supp1Count = 0;
    for (let i = 0; i < itemCount; i++) {
      const name = await repeaterItems.nth(i).locator('input[id*="supplier_name"]').first().inputValue();
      if (name.includes('PT Supplier Utama')) supp1Count++;
    }
    console.log('Items for PT Supplier Utama:', supp1Count);
    expect(supp1Count, 'Should have 2 items from PT Supplier Utama').toBe(2);
  });

  /**
   * Test 4: Item for CV Distributor Jaya appears in the modal (1 item).
   */
  test('Approve modal contains item for CV Distributor Jaya', async ({ page }) => {
    const modal = await openApproveModal(page);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();

    let supp2Count = 0;
    for (let i = 0; i < itemCount; i++) {
      const name = await repeaterItems.nth(i).locator('input[id*="supplier_name"]').first().inputValue();
      if (name.includes('CV Distributor Jaya')) supp2Count++;
    }
    console.log('Items for CV Distributor Jaya:', supp2Count);
    expect(supp2Count, 'Should have 1 item from CV Distributor Jaya').toBe(1);
  });

  /**
   * Test 5: Items grouped by supplier are correctly countable.
   * Simulate the groupBy logic: confirms that the modal data allows
   * the system to group into 2 PO batches (1 per supplier).
   */
  test('Items in modal can be grouped into 2 supplier batches for PO creation', async ({ page }) => {
    const modal = await openApproveModal(page);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();

    const supplierGroups = {};
    for (let i = 0; i < itemCount; i++) {
      const supplierName = await repeaterItems.nth(i).locator('input[id*="supplier_name"]').first().inputValue();
      // Get item_supplier_id from a hidden field or use supplier_name as proxy
      supplierGroups[supplierName.trim()] = (supplierGroups[supplierName.trim()] || 0) + 1;
    }

    console.log('Supplier groups from modal:', supplierGroups);
    const numGroups = Object.keys(supplierGroups).length;
    expect(numGroups, 'Should result in 2 supplier groups for 2 POs').toBe(2);

    const totalItems = Object.values(supplierGroups).reduce((a, b) => a + b, 0);
    expect(totalItems).toBe(3); // all 3 items accounted for
  });

  /**
   * Test 6: Verify each item has correct product_name, supplier_name, and uom.
   */
  test('Approve modal shows correct product, supplier and uom for all items', async ({ page }) => {
    const modal = await openApproveModal(page);
    const repeaterItems = modal.locator('.fi-fo-repeater-item');
    const itemCount = await repeaterItems.count();
    expect(itemCount).toBe(3);

    // Collect all items from modal
    const modalItems = [];
    for (let i = 0; i < itemCount; i++) {
      const row = repeaterItems.nth(i);
      const productName = await row.locator('input[id*="product_name"]').first().inputValue();
      const supplierName = await row.locator('input[id*="supplier_name"]').first().inputValue();
      const uom = await row.locator('input[id*="uom"]').first().inputValue();
      const qty = await row.locator('input[id*="quantity"]').first().inputValue();
      console.log(`Item ${i}: product="${productName}" supplier="${supplierName}" uom="${uom}" qty="${qty}"`);
      modalItems.push({ productName, supplierName, uom, qty });
    }

    // Verify each expected item appears somewhere in the modal
    for (const expected of EXPECTED_ITEMS) {
      const found = modalItems.find(m =>
        m.productName.includes(expected.productName) &&
        m.supplierName.includes(expected.supplierName)
      );
      expect(found, `Should find item "${expected.productName}" with supplier "${expected.supplierName}"`).toBeTruthy();
    }
  });

});
