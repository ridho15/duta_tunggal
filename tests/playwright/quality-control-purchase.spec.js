import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

async function navigate(page, path) {
  await page.goto(path);
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').waitFor({ state: 'visible', timeout: 15_000 });
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.goto(path);
  }
  await page.waitForLoadState('networkidle');
}

async function selectFirstChoicesOption(page, labelText, searchTerm = '') {
  const label = page.locator(`label:has-text("${labelText}")`).first();
  const wrapper = page.locator('.fi-fo-field-wrp').filter({ has: label }).first();

  let combobox = null;
  if (await wrapper.count()) {
    await expect(wrapper).toBeVisible();
    combobox = wrapper.getByRole('combobox').first();
  } else {
    combobox = page.getByRole('combobox').first();
  }

  await combobox.click();

  if (searchTerm) {
    const searchInput = page.locator('.choices__input--cloned:visible').first();
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill(searchTerm);
      await page.waitForTimeout(600);
    }
  }

  const matchingItem = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: searchTerm || labelText })
    .first();

  const globalMatchingItem = page
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled):visible')
    .filter({ hasText: searchTerm || labelText })
    .first();

  const fallbackItem = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').first();
  const globalFallbackItem = page.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled):visible').first();

  let targetItem = fallbackItem;
  let targetType = 'fallbackItem';

  if (await matchingItem.count()) {
    targetItem = matchingItem;
    targetType = 'matchingItem';
  } else if (await globalMatchingItem.count()) {
    targetItem = globalMatchingItem;
    targetType = 'globalMatchingItem';
  } else if (await globalFallbackItem.count()) {
    targetItem = globalFallbackItem;
    targetType = 'globalFallbackItem';
  }

  await expect(targetItem).toBeVisible({ timeout: 10000 });
  const targetText = await targetItem.textContent();
  console.log(`Dropdown target chosen: [${targetType}] "${targetText?.trim()}"`);

  // Set up Livewire response waiter BEFORE clicking to avoid race condition
  const livewireResponsePromise = page.waitForResponse(
    (resp) => resp.url().includes('/livewire') && resp.request().method() === 'POST',
    { timeout: 15_000 }
  ).catch(err => {
    console.log('Livewire POST request timed out or did not fire');
    return null;
  });

  await targetItem.click({ force: true });

  // Wait for Livewire afterStateUpdated + DOM re-render to complete
  await livewireResponsePromise;
  await page.waitForTimeout(1000);
}

test.describe('Quality Control Purchase E2E Tests', () => {
  test.beforeAll(() => {
    execSync('php scripts/setup_qc_purchase_playwright_data.php', { stdio: 'inherit' });
  });

  test('verify Cabang, Inspected By, and Gudang filtering constraints', async ({ page }) => {
    // 1. Navigate to QC Purchase Create form
    await navigate(page, '/admin/quality-control-purchases/create');

    // Print form markup for debugging
    console.log('--- Navigated to Quality Control Purchases Create Page ---');

    // 2. Verify "Cabang" select field exists and is disabled
    const cabangWrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Cabang")') }).first();
    await expect(cabangWrapper).toBeVisible();
    const cabangSelect = cabangWrapper.locator('select');
    await expect(cabangSelect).toBeDisabled();

    // 3. Verify "Inspected By" select field exists, is enabled (since logged-in user is Owner), and defaults to Ridho Al Amzah (ID: 1)
    const inspectedWrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Inspected By")') }).first();
    await expect(inspectedWrapper).toBeVisible();
    const inspectedSelect = inspectedWrapper.locator('select');
    await expect(inspectedSelect).toBeEnabled();
    await expect(inspectedSelect).toHaveValue('1'); // ID of Ridho Al Amzah is 1

    // 4. Select the Purchase Order Item and wait for reactive update
    await selectFirstChoicesOption(page, 'Purchase Order Item', 'PO-E2E-QC-TEST');

    // 5. Verify "Cabang" automatically reactive-fills with "Cabang E2E Playwright" and remains disabled
    await expect(cabangSelect).toHaveValue(/\d+/);
    const selectedCabangText = await cabangSelect.locator('option:checked').first().textContent();
    console.log('Reactive selected Cabang name:', selectedCabangText?.trim());
    expect(selectedCabangText?.trim()).toContain('Cabang E2E Playwright');
    await expect(cabangSelect).toBeDisabled();

    // 6. Click on Gudang and verify warehouse filtering constraints
    const gudangWrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Gudang")') }).first();
    await expect(gudangWrapper).toBeVisible();
    
    // Trigger Gudang dropdown to see options
    const gudangChoicesInner = gudangWrapper.locator('.choices__inner');
    await gudangChoicesInner.click();
    await page.waitForTimeout(500);

    // Let's inspect the dropdown items
    const visibleChoices = page.locator('.choices__list--dropdown:visible .choices__item--choice');
    const texts = await visibleChoices.allTextContents();
    console.log('Available Gudang options in dropdown:', texts.map(t => t.trim()));

    // Verify it contains "Gudang E2E QC" (C-E2E)
    const hasGudangQc = texts.some(t => t.includes('Gudang E2E QC'));
    expect(hasGudangQc).toBe(true);

    // Verify it does NOT contain "Gudang E2E Lainnya"
    const hasGudangLainnya = texts.some(t => t.includes('Gudang E2E Lainnya'));
    expect(hasGudangLainnya).toBe(false);

    // Select the "Gudang E2E QC" option
    const gudangOption = page.locator('.choices__list--dropdown:visible .choices__item--choice').filter({ hasText: 'Gudang E2E QC' }).first();
    await gudangOption.click();
    await page.waitForTimeout(500);

    // 7. Verify quantities are auto-filled
    const quantityReceivedInput = page.getByLabel('Quantity Received').first();
    await expect(quantityReceivedInput).toHaveValue('10');

    const passedQuantityInput = page.getByLabel('Passed Quantity').first();
    await expect(passedQuantityInput).toHaveValue('10');

    // 8. Submit the form to create the QC record
    const submitButton = page.locator('button[type="submit"]:visible, button:has-text("Buat"):visible, button:has-text("Create"):visible').first();
    await expect(submitButton).toBeVisible();
    
    // Wait for network idle and submit
    await page.waitForLoadState('networkidle');
    await submitButton.click();

    // 9. Wait for navigation/redirection or success notification
    await page.waitForTimeout(3000);
    console.log('Final page URL after submission:', page.url());
    expect(page.url()).not.toContain('/create');
  });
});
