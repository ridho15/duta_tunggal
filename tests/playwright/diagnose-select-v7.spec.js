/**
 * Diagnostic test v7 - Properly wait for Choices.js init
 */
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8009';

async function login(page) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');
  if (!page.url().includes('/login')) return;
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/admin**', { timeout: 20000 });
  await page.waitForLoadState('networkidle');
}

async function safeGoto(page, url) {
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
  } catch (e) {
    if (!e.message.includes('ERR_ABORTED') && !e.message.includes('ERR_FAILED')) throw e;
  }
  await page.waitForLoadState('networkidle');
  // Wait for Choices.js to initialize (it loads asynchronously via async-alpine)
  await page.waitForSelector('.choices__inner', { timeout: 15000 }).catch(() => {
    console.log('Choices.js did not init with .choices__inner');
  });
  await page.waitForTimeout(500);
}

/**
 * Fill a Filament searchable select using Choices.js interaction
 * @param {Page} page
 * @param {string} labelText - The label text (e.g. 'Cabang', 'Supplier')
 * @param {string} searchText - Text to search for
 */
async function fillSelect(page, labelText, searchText) {
  // Find the field wrapper for this label
  const fieldWrapper = page.locator('.fi-fo-field-wrp').filter({
    has: page.locator('label, .fi-fo-field-wrp-label').filter({ hasText: new RegExp(`^${labelText}`, 'i') })
  }).first();
  
  if (await fieldWrapper.count() === 0) {
    console.log(`⚠️ Field wrapper not found for: ${labelText}`);
    // Fallback: search more broadly
    const broadWrapper = page.locator('.fi-fo-field-wrp').filter({
      hasText: new RegExp(labelText, 'i')
    }).first();
    if (await broadWrapper.count() === 0) {
      console.log(`⚠️ No wrapper found at all for: ${labelText}`);
      return false;
    }
    return fillChoicesInWrapper(page, broadWrapper, searchText);
  }
  
  return fillChoicesInWrapper(page, fieldWrapper, searchText);
}

async function fillChoicesInWrapper(page, wrapper, searchText) {
  // Find the .choices__inner in this wrapper (Choices.js trigger)
  const inner = wrapper.locator('.choices__inner').first();
  
  if (await inner.isVisible({ timeout: 3000 }).catch(() => false)) {
    await inner.click();
    await page.waitForTimeout(400);
    
    // After clicking, a search input appears inside choices__inner
    const searchInput = wrapper.locator('input.choices__input').first();
    if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
      await searchInput.fill(searchText);
      await page.waitForTimeout(1000); // Wait for search results (may be async)
      
      // Click first result
      const firstOption = page.locator('.choices__item--selectable:not(.choices__item--disabled)').first();
      if (await firstOption.isVisible({ timeout: 3000 }).catch(() => false)) {
        const optionText = await firstOption.textContent();
        console.log(`  → Selecting option: ${optionText?.trim()}`);
        await firstOption.click();
        await page.waitForTimeout(300);
        return true;
      } else {
        console.log(`  ⚠️ No options found for search: ${searchText}`);
        // Take screenshot for debugging
        await page.screenshot({ path: `test-results/debug-no-options-${Date.now()}.png`, fullPage: false });
      }
    } else {
      console.log(`  ⚠️ No search input found after clicking choices__inner`);
    }
  } else {
    console.log(`  ⚠️ choices__inner not visible in wrapper`);
  }
  
  return false;
}

test('Full Select Interaction v7', async ({ page }) => {
  test.setTimeout(90000);
  
  await login(page);
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  
  console.log(`URL: ${page.url()}`);
  
  // Check state
  const state = await page.evaluate(() => ({
    choicesInnerCount: document.querySelectorAll('.choices__inner').length,
    xDataSelectCount: document.querySelectorAll('[x-data*="selectFormComponent"]').length,
  }));
  console.log('Initial state:', JSON.stringify(state, null, 2));
  
  // Try filling Cabang
  console.log('\n=== Filling Cabang ===');
  const cabangResult = await fillSelect(page, 'Cabang', 'Cabang');
  console.log(`Cabang result: ${cabangResult}`);
  await page.screenshot({ path: 'test-results/diagnose-v7-cabang.png', fullPage: false });
  
  const cabangVal = await page.locator('#data\\.cabang_id').inputValue().catch(() => '');
  console.log(`Cabang value: ${cabangVal}`);
  
  if (cabangResult) {
    // Wait for warehouse to become reactive
    await page.waitForTimeout(1000);
    
    // Try filling Supplier
    console.log('\n=== Filling Supplier ===');
    const supplierResult = await fillSelect(page, 'Supplier', 'Personal');
    console.log(`Supplier result: ${supplierResult}`);
    await page.screenshot({ path: 'test-results/diagnose-v7-supplier.png', fullPage: false });
    
    const supplierVal = await page.locator('#data\\.supplier_id').inputValue().catch(() => '');
    console.log(`Supplier value: ${supplierVal}`);
  }
  
  expect(true).toBe(true);
});
