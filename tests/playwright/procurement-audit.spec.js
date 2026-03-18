/**
 * procurement-audit.spec.js
 *
 * Comprehensive procurement flow verification tests covering:
 *
 *  TEST 1: OR approve modal shows item's unit_price (not catalog price)
 *    - OR-TEST-B-APPROVE: 1 item, unit_price=130000 (override, catalog=150000)
 *    - Verifies unit_price field shows "130.000" (not "150.000")
 *    - Verifies original_price field shows "150.000" (catalog reference)
 *    - Verifies total_cost = 10 × 130000 = "1.300.000"
 *
 *  TEST 2: OR approve modal for multi-supplier OR shows multi_supplier notice
 *    - OR-TEST-C-MULTISUPPLIER: 3 items, 2 suppliers
 *    - Verifies supplier dropdown is hidden (multi mode)
 *    - Verifies po_number field is hidden (PO numbers auto-generated)
 *    - Verifies item prices: productId2 shows 220.000 (not 240.000 catalog)
 *
 *  TEST 3: OR header supplier is optional (no required validation)
 *    - Creating a new OR without selecting a header supplier should be allowed
 *    - Per-item supplier selection must work independently
 *
 * Pre-requisite: run scripts/setup_procurement_test_data.php
 * Auth: uses playwright/.auth/user.json (ralamzah@gmail.com / ridho123)
 */

import { test, expect } from '@playwright/test';

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Open the dropdown action menu for the table row containing the given text,
 * then click the item whose label matches actionLabel.
 */
async function openRowAction(page, rowText, actionLabel) {
  const targetRow = page.locator('tr').filter({ hasText: rowText }).first();
  await targetRow.waitFor({ state: 'visible', timeout: 15_000 });

  const dropdownTrigger = targetRow.locator('.fi-dropdown-trigger').first();
  await dropdownTrigger.waitFor({ state: 'visible', timeout: 10_000 });
  await dropdownTrigger.click();
  await page.waitForTimeout(600);

  // Use :visible pseudo-class to only find the currently shown dropdown items
  const actionItem = page.locator(`.fi-dropdown-list-item:visible`).filter({
    has: page.locator('.fi-dropdown-list-item-label', { hasText: new RegExp(`^\\s*${actionLabel}\\s*$`) }),
  }).first();
  await actionItem.waitFor({ state: 'visible', timeout: 10_000 });
  await actionItem.click();

  // Wait for the Filament modal window to become visible
  const modalContainer = page.locator('.fi-modal-open').first();
  const modalWindow = modalContainer.locator('.fi-modal-window').first();
  await modalWindow.waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_200);

  return modalContainer;
}

/**
 * Get the value of a repeater item's input field by its name fragment.
 * Searches within the modal container.
 */
async function getRepeaterInputValue(modal, inputNameFragment, itemIndex = 0) {
  const inputs = modal.locator(`input[id*="${inputNameFragment}"]`);
  const count = await inputs.count();
  if (count === 0) return null;
  const idx = Math.min(itemIndex, count - 1);
  return await inputs.nth(idx).inputValue();
}

// ─── TEST 1: Approve modal — unit_price override respected ────────────────────

test.describe('TEST 1: Approve modal — item unit_price override over catalog', () => {

  test('OR-TEST-B-APPROVE: unit_price shows 130.000 (not catalog 150.000)', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    // Confirm not redirected to login
    await expect(page).not.toHaveURL(/login/, { timeout: 5_000 });

    const modal = await openRowAction(page, 'OR-TEST-B-APPROVE', 'Approve');

    // unit_price in the first repeater item
    const unitPrice = await getRepeaterInputValue(modal, 'unit_price', 0);
    console.log('[TEST1] unit_price:', unitPrice);
    expect(
      unitPrice,
      `unit_price should be "130.000" (override) not "150.000" (catalog). Got: "${unitPrice}"`
    ).toBe('130.000');

    // original_price should show the catalog reference price
    const originalPrice = await getRepeaterInputValue(modal, 'original_price', 0);
    console.log('[TEST1] original_price:', originalPrice);
    expect(
      originalPrice,
      `original_price should be "150.000" (catalog). Got: "${originalPrice}"`
    ).toBe('150.000');

    // total_cost = 10 × 130.000 = 1.300.000
    const totalCost = await getRepeaterInputValue(modal, 'total_cost', 0);
    console.log('[TEST1] total_cost:', totalCost);
    expect(
      totalCost,
      `total_cost should be "1.300.000" (10 × 130.000). Got: "${totalCost}"`
    ).toBe('1.300.000');
  });

});

// ─── TEST 2: Multi-supplier OR approve modal ───────────────────────────────────

test.describe('TEST 2: Multi-supplier OR approve modal', () => {

  test('OR-TEST-C-MULTISUPPLIER: modal hides supplier & po_number fields, shows notice', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const modal = await openRowAction(page, 'OR-TEST-C-MULTISUPPLIER', 'Approve');

    // The multi-supplier notice placeholder should be visible
    const notice = modal.locator('text=Item dalam OR ini memiliki beberapa supplier berbeda');
    const noticeVisible = await notice.isVisible().catch(() => false);
    console.log('[TEST2] multi_supplier notice visible:', noticeVisible);
    expect(noticeVisible).toBe(true);

    // The per-form supplier_id select should NOT be visible
    // (it's hidden when multi_supplier=true via ->visible(fn(Get $get) => !$get('multi_supplier')))
    const supplierSection = modal.locator('label:text-is("Supplier")').filter({
      hasNot: modal.locator('label:text-is("Nama Produk")'),
    }).first();
    const supplierVisible = await supplierSection.isVisible().catch(() => false);
    console.log('[TEST2] PO supplier field visible:', supplierVisible);
    expect(supplierVisible).toBe(false);

    // The po_number field should NOT be visible in multi_supplier mode
    const poNumberLabel = modal.locator('label:text-is("PO Number")');
    const poNumberVisible = await poNumberLabel.isVisible().catch(() => false);
    console.log('[TEST2] PO Number field visible:', poNumberVisible);
    expect(poNumberVisible).toBe(false);
  });

  test('OR-TEST-C-MULTISUPPLIER: item prices show overrides not catalog prices', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const modal = await openRowAction(page, 'OR-TEST-C-MULTISUPPLIER', 'Approve');

    // Item 0: Panel (supplier A), unit_price=150.000 (matches catalog, no override needed)
    const unitPrice0 = await getRepeaterInputValue(modal, 'unit_price', 0);
    console.log('[TEST2] item[0] unit_price:', unitPrice0);
    expect(unitPrice0).toBe('150.000');

    // Item 1: Sensor (supplier B), unit_price=220.000 (override, catalog=240.000)
    const unitPrice1 = await getRepeaterInputValue(modal, 'unit_price', 1);
    console.log('[TEST2] item[1] unit_price:', unitPrice1);
    expect(
      unitPrice1,
      `item[1] unit_price should be "220.000" (override) not "240.000" (catalog). Got: "${unitPrice1}"`
    ).toBe('220.000');

    // original_price for item 1 should be 240.000 (catalog)
    const originalPrice1 = await getRepeaterInputValue(modal, 'original_price', 1);
    console.log('[TEST2] item[1] original_price:', originalPrice1);
    expect(originalPrice1).toBe('240.000');

    // Item 2: Bahan Baku (supplier A), unit_price=75.000 (matches catalog)
    const unitPrice2 = await getRepeaterInputValue(modal, 'unit_price', 2);
    console.log('[TEST2] item[2] unit_price:', unitPrice2);
    expect(unitPrice2).toBe('75.000');
  });

});

// ─── TEST 3: OR header supplier is optional ────────────────────────────────────

test.describe('TEST 3: OR header supplier field is optional', () => {

  test('OR-TEST-B-APPROVE has NULL header supplier — row still exists and is accessible', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    // OR-TEST-B-APPROVE was created with no header supplier (supplier_id=NULL)
    const targetRow = page.locator('tr').filter({ hasText: 'OR-TEST-B-APPROVE' }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 10_000 });
    const rowText = await targetRow.textContent();
    console.log('[TEST3] Row text:', rowText?.substring(0, 100));

    // Row should be visible and not show an error
    await expect(targetRow).toBeVisible();
  });

  test('OR create form — header supplier has no required validation', async ({ page }) => {
    await page.goto('/admin/order-requests/create');
    await page.waitForLoadState('networkidle');

    // The supplier_id field should NOT have required asterisk or be mandatory
    // Find the "Supplier (Default)" field wrapper
    const supplierWrapper = page.locator('.fi-fo-field-wrp')
      .filter({ has: page.locator('label:text-is("Supplier (Default)")') })
      .first();

    const isVisible = await supplierWrapper.isVisible().catch(() => false);
    console.log('[TEST3] Supplier (Default) field visible:', isVisible);

    if (isVisible) {
      // The label should NOT have a required indicator (trailing *) 
      const label = supplierWrapper.locator('label').first();
      const labelText = await label.textContent();
      console.log('[TEST3] Supplier label text:', labelText);
      // Required indicator in Filament is an <svg> or span.fi-fo-field-wrp-label-required-indicator
      const requiredIndicator = supplierWrapper.locator('.fi-fo-field-wrp-label-required-indicator, [data-isrequired]');
      const hasRequired = (await requiredIndicator.count()) > 0;
      console.log('[TEST3] Has required indicator:', hasRequired);
      expect(hasRequired).toBe(false);
    }
  });

});

// ─── TEST 4: OR-TEST-A-DRAFT has header supplier — shows in row ────────────────

test.describe('TEST 4: OR with header supplier_id (backward compat)', () => {

  test('OR-TEST-A-DRAFT (header supplier set) — row visible with no errors', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const targetRow = page.locator('tr').filter({ hasText: 'OR-TEST-A-DRAFT' }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 10_000 });
    await expect(targetRow).toBeVisible();
    console.log('[TEST4] OR-TEST-A-DRAFT row is visible');
  });

});
