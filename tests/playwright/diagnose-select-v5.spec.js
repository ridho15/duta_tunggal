/**
 * Diagnostic test v5 - Wait for Choices.js to initialize
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
  // Wait for Choices.js to initialize (it loads from network)
  try {
    await page.waitForSelector('.choices', { timeout: 10000 });
  } catch (e) {
    console.log('No .choices element found, waiting for networkidle...');
    await page.waitForLoadState('networkidle');
  }
  await page.waitForTimeout(500);
}

/**
 * Fill a Filament-Choices.js searchable select
 * @param {Page} page - Playwright page
 * @param {string} selectId - The ID of the underlying <select> (e.g. 'data.cabang_id')
 * @param {string} searchText - Text to search for
 */
async function fillChoicesSelect(page, selectId, searchText) {
  // The Choices.js wrapper is the next sibling element group from the <select>
  // Find the .choices__inner element adjacent to our select
  
  const selectEl = page.locator(`#${selectId.replace(/\./g, '\\.')}`);
  
  // Method: Find the .choices container that wraps our select
  // Choices.js wraps the select and creates .choices > .choices__inner > input.choices__input
  const choicesContainer = page.locator('[x-data*="selectFormComponent"]')
    .filter({ has: selectEl });
  
  if (!(await choicesContainer.count())) {
    console.log(`⚠️ No choices container found for ${selectId}`);
    return false;
  }
  
  // Click the choices__inner to open dropdown
  const inner = choicesContainer.locator('.choices__inner');
  if (await inner.isVisible({ timeout: 3000 }).catch(() => false)) {
    await inner.click();
    await page.waitForTimeout(300);
    
    // Fill the search input
    const searchInput = choicesContainer.locator('input.choices__input');
    if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
      await searchInput.fill(searchText);
      await page.waitForTimeout(1000);
      
      const option = choicesContainer.locator('.choices__item--selectable:not(.choices__item--disabled)').first();
      if (await option.isVisible({ timeout: 3000 }).catch(() => false)) {
        await option.click();
        return true;
      }
    }
    
    // Fallback: type and look globally for choices items
    const globalSearch = page.locator('input.choices__input').first();
    if (await globalSearch.isVisible({ timeout: 1000 }).catch(() => false)) {
      await globalSearch.fill(searchText);
      await page.waitForTimeout(1000);
      const option = page.locator('.choices__item--selectable:not(.choices__item--disabled)').first();
      if (await option.isVisible({ timeout: 3000 }).catch(() => false)) {
        await option.click();
        return true;
      }
    }
  }
  
  console.log(`⚠️ Could not interact with choices for ${selectId}`);
  return false;
}

test('Test Choices.js with Wait', async ({ page }) => {
  test.setTimeout(60000);
  
  await login(page);
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  
  console.log(`URL: ${page.url()}`);
  
  // Check Choices.js initialization
  const choicesInfo = await page.evaluate(() => {
    return {
      choicesCount: document.querySelectorAll('.choices').length,
      choicesInnerCount: document.querySelectorAll('.choices__inner').length,
      choicesInputCount: document.querySelectorAll('input.choices__input').length,
      xDataSelects: document.querySelectorAll('[x-data*="selectFormComponent"]').length
    };
  });
  console.log('Choices init state:', JSON.stringify(choicesInfo, null, 2));
  
  if (choicesInfo.choicesCount === 0) {
    console.log('Choices not ready, waiting 5 more seconds...');
    await page.waitForTimeout(5000);
    
    const choicesInfo2 = await page.evaluate(() => ({
      choicesCount: document.querySelectorAll('.choices').length,
      choicesInnerCount: document.querySelectorAll('.choices__inner').length,
    }));
    console.log('After 5s:', JSON.stringify(choicesInfo2, null, 2));
  }
  
  // List all choices containers
  const allChoicesInfo = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('[x-data*="selectFormComponent"]')).map(el => {
      const select = el.querySelector('select');
      const inner = el.querySelector('.choices__inner');
      return {
        selectId: select?.id,
        hasChoicesInner: !!inner,
        innerVisible: inner ? window.getComputedStyle(inner).display !== 'none' : false
      };
    });
  });
  console.log('All choices selects:', JSON.stringify(allChoicesInfo, null, 2));
  
  // Try to fill the Cabang select
  console.log('\n=== Filling Cabang select ===');
  const result = await fillChoicesSelect(page, 'data.cabang_id', 'Cabang');
  console.log(`fillChoicesSelect result: ${result}`);
  
  await page.screenshot({ path: 'test-results/diagnose-v5-after-fill.png', fullPage: false });
  
  const cabangValue = await page.locator('#data\\.cabang_id').inputValue().catch(() => '');
  console.log(`Cabang value: ${cabangValue}`);
  
  expect(true).toBe(true);
});
