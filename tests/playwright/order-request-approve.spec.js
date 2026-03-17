/**
 * order-request-approve.spec.js
 *
 * Verifies that when the Approve action is triggered on Order Request #2
 * (status: request_approve, request_number: OR-20260317-2993), the modal
 * pre-fills:
 *
 *   1. "Original Price (Rp)" — should show the item's original_price (180.000)
 *   2. "Total (Harga × Qty)" — should show qty × unit_price (1.900.000)
 *
 * Root cause that was fixed:
 *   The fillForm() callback in the Approve action did NOT include
 *   `original_price` or `total_cost` in the returned item data, so the
 *   fields were always empty when the modal opened.
 *
 * Fix applied in OrderRequestResource.php:
 *   Added 'original_price' and 'total_cost' to the returned item array
 *   in the approve action's fillForm() callback.
 *
 * DOM note:
 *   Filament v3 renders all table row actions inside a dropdown (3-dot menu).
 *   The dropdown panel is teleported to <body> via Alpine x-float.teleport.
 *   To trigger the Approve action we must:
 *     1. Click the row's .fi-dropdown-trigger (the 3-dot icon button)
 *     2. Wait for the panel to appear
 *     3. Click the .fi-dropdown-list-item whose label is exactly "Approve"
 */

import { test, expect } from '@playwright/test';

const OR2_REQUEST_NUMBER = 'OR-20260317-2993';
// item data from DB: qty=10, original_price=180000, supplier_unit_price=180000
// total_cost = qty × supplier_unit_price = 10 × 180.000 = 1.800.000
const EXPECTED_ORIGINAL_PRICE = '180.000';
const EXPECTED_TOTAL_COST     = '1.800.000';
const EXPECTED_UNIT_PRICE     = '180.000';

/**
 * Open the Approve dropdown item for the given row and return the opened modal.
 *
 * Filament teleports the dropdown panel to <body>, so finding the "Approve"
 * item requires looking globally for the visible panel after clicking the
 * per-row 3-dot trigger.
 */
async function openApproveModal(page, targetRow) {
  // Click the 3-dot dropdown trigger for this row
  const dropdownTrigger = targetRow.locator('.fi-dropdown-trigger').first();
  await dropdownTrigger.waitFor({ state: 'visible', timeout: 10_000 });
  await dropdownTrigger.click();

  // Wait for dropdown panel to become visible (Alpine removes display:none)
  await page.waitForTimeout(500);

  // Find the "Approve" dropdown item.
  // Use .fi-dropdown-list-item-label with exact text to avoid matching "Request Approve".
  // The panel is teleported to body — search globally for the visible item.
  const approveItem = page.locator('.fi-dropdown-list-item').filter({
    has: page.locator('.fi-dropdown-list-item-label', { hasText: /^\s*Approve\s*$/ }),
  }).first();

  await approveItem.waitFor({ state: 'visible', timeout: 10_000 });
  await approveItem.click();

  // Wait for the Filament action modal to open.
  // NOTE: The outer [role="dialog"] element is never "visible" to Playwright
  // because visibility is controlled by the inner x-show="isShown" directive
  // on .fi-modal-window. We wait for .fi-modal-open .fi-modal-window instead.
  const modalContainer = page.locator('.fi-modal-open').first();
  const modalWindow = modalContainer.locator('.fi-modal-window').first();
  await modalWindow.waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForLoadState('networkidle');
  // Give Livewire / Alpine a moment to hydrate the repeater fields
  await page.waitForTimeout(1_000);

  return modalContainer;
}

test.describe('Order Request Approve Modal — field pre-fill', () => {

  test('Approve modal for OR #2 pre-fills original_price and total_cost', async ({ page }) => {
    const jsErrors = [];
    page.on('pageerror', err => jsErrors.push(err.message));

    // ── 1. Navigate to Order Request list ─────────────────────────────────
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    // Confirm we reached the list (not redirected back to login)
    await expect(page).not.toHaveURL(/login/, { timeout: 5_000 });

    // ── 2. Find the row for OR-20260317-2993 ──────────────────────────────
    const targetRow = page.locator('tr').filter({ hasText: OR2_REQUEST_NUMBER }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 15_000 });

    // ── 3. Open the Approve modal via the row dropdown ─────────────────────
    const modal = await openApproveModal(page, targetRow);

    // ── 4. Locate the first repeater item (inputs are inside .fi-modal-open) ─
    const repeaterItem = modal.locator('.fi-fo-repeater-item, [class*="repeater"]').first();
    const repeaterExists = await repeaterItem.count() > 0;
    // Fall back to searching all inputs within the open modal if no repeater wrapper
    const inputScope = repeaterExists ? repeaterItem : modal;

    // ── 5. Assert original_price is populated ─────────────────────────────
    const originalPriceInput = modal.locator('input[id*="original_price"]').first();
    await originalPriceInput.waitFor({ state: 'visible', timeout: 8_000 });
    const originalPriceValue = await originalPriceInput.inputValue();

    console.log('original_price value:', originalPriceValue);
    expect(
      originalPriceValue,
      `Expected original_price to be "${EXPECTED_ORIGINAL_PRICE}", got "${originalPriceValue}"`
    ).toBe(EXPECTED_ORIGINAL_PRICE);

    // ── 6. Assert total_cost (Total Harga × Qty) is populated ─────────────
    const totalCostInput = modal.locator('input[id*="total_cost"]').first();
    await totalCostInput.waitFor({ state: 'visible', timeout: 8_000 });
    const totalCostValue = await totalCostInput.inputValue();

    console.log('total_cost value:', totalCostValue);
    expect(
      totalCostValue,
      `Expected total_cost to be "${EXPECTED_TOTAL_COST}", got "${totalCostValue}"`
    ).toBe(EXPECTED_TOTAL_COST);

    // ── 7. No unexpected JS errors ────────────────────────────────────────
    const blockingErrors = jsErrors.filter(e =>
      !e.includes('favicon') &&
      !e.includes('chunk') &&
      !e.includes('Loading chunk')
    );
    if (blockingErrors.length) {
      console.warn('JS errors during test:', blockingErrors);
    }
    expect(blockingErrors).toHaveLength(0);
  });

  test('original_price and total_cost are not empty in approve modal', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const targetRow = page.locator('tr').filter({ hasText: OR2_REQUEST_NUMBER }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 15_000 });

    const modal = await openApproveModal(page, targetRow);

    // original_price must not be empty
    const origVal = await modal.locator('input[id*="original_price"]').first().inputValue();
    console.log('original_price (non-empty test):', origVal);
    expect(origVal.trim(), 'original_price should not be empty').not.toBe('');
    expect(origVal.trim(), 'original_price should not be 0').not.toBe('0');

    // total_cost must not be empty
    const totalVal = await modal.locator('input[id*="total_cost"]').first().inputValue();
    console.log('total_cost (non-empty test):', totalVal);
    expect(totalVal.trim(), 'total_cost should not be empty').not.toBe('');
    expect(totalVal.trim(), 'total_cost should not be 0').not.toBe('0');
  });

  test('product_name is visible and unit_price is pre-filled in approve modal', async ({ page }) => {
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    const targetRow = page.locator('tr').filter({ hasText: OR2_REQUEST_NUMBER }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 15_000 });

    const modal = await openApproveModal(page, targetRow);

    // product_name is a readonly text input — must contain item name
    const productNameVal = await modal.locator('input[id*="product_name"]').first().inputValue();
    console.log('product_name:', productNameVal);
    expect(productNameVal.trim()).toContain('Keyboard Mechanical');

    // unit_price must be pre-populated (supplier catalog price = 180.000)
    const unitPriceVal = await modal.locator('input[id*="unit_price"]').first().inputValue();
    console.log('unit_price:', unitPriceVal);
    expect(unitPriceVal.trim()).toBe(EXPECTED_UNIT_PRICE);
  });
});
