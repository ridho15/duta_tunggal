/**
 * Diagnostic test v8 - Fix navigation + test Choices.js
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
  // Wait until we're actually on the target URL
  const urlPath = new URL(url).pathname;
  try {
    await page.waitForURL(`**${urlPath}`, { timeout: 10000 });
  } catch (e) {
    console.log(`URL not matching ${urlPath}, current URL: ${page.url()}`);
    // Try again
    try {
      await page.goto(url, { waitUntil: 'networkidle' });
    } catch (e2) {
      if (!e2.message.includes('ERR_ABORTED') && !e2.message.includes('ERR_FAILED')) throw e2;
    }
    await page.waitForTimeout(2000);
  }
  // Final wait for Choices.js
  await page.waitForSelector('.choices__inner', { timeout: 15000 }).catch(() => null);
  await page.waitForTimeout(500);
}

test('v8 - Fix navigation + test Choices.js', async ({ page }) => {
  test.setTimeout(60000);
  
  await login(page);
  console.log(`After login URL: ${page.url()}`);
  
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  console.log(`After goto URL: ${page.url()}`);
  
  const state = await page.evaluate(() => ({
    url: window.location.href,
    choicesInnerCount: document.querySelectorAll('.choices__inner').length,
    xDataSelectCount: document.querySelectorAll('[x-data*="selectFormComponent"]').length,
    heading: document.querySelector('h1')?.textContent?.trim()
  }));
  console.log('Page state:', JSON.stringify(state, null, 2));
  
  expect(state.url).toContain('/admin/order-requests/create');
  expect(state.choicesInnerCount).toBeGreaterThan(0);
  
  // Now try to fill the Cabang select
  console.log('\n=== Finding all .choices__inner ===');
  const allInners = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('[x-data*="selectFormComponent"]')).map(el => {
      const select = el.querySelector('select');
      const inner = el.querySelector('.choices__inner');
      const label = el.closest('.fi-fo-field-wrp')?.querySelector('label, .fi-fo-field-wrp-label')?.textContent?.trim();
      return {
        selectId: select?.id,
        hasInner: !!inner,
        innerHTML: inner?.innerHTML?.substring(0, 200),
        label
      };
    });
  });
  console.log('All choices selects:', JSON.stringify(allInners, null, 2));
  
  // Click the Cabang choices__inner
  const cabangWrapper = page.locator('[x-data*="selectFormComponent"]').filter({
    has: page.locator('#data\\.cabang_id')
  });
  
  const choicesInner = cabangWrapper.locator('.choices__inner').first();
  console.log('\n=== Clicking Cabang choices__inner ===');
  await choicesInner.click();
  await page.waitForTimeout(500);
  
  const searchInput = cabangWrapper.locator('input.choices__input').first();
  const isVisible = await searchInput.isVisible({ timeout: 2000 }).catch(() => false);
  console.log(`Search input visible: ${isVisible}`);
  
  if (isVisible) {
    await searchInput.fill('Cabang');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'test-results/diagnose-v8-after-search.png', fullPage: false });
    
    const options = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('.choices__item--selectable:not(.choices__item--disabled)'))
        .map(el => el.textContent?.trim())
        .slice(0, 5);
    });
    console.log('Options:', options);
    
    if (options.length > 0) {
      await page.locator('.choices__item--selectable:not(.choices__item--disabled)').first().click();
      const val = await page.locator('#data\\.cabang_id').inputValue();
      console.log(`Selected value: ${val}`);
    }
  }
  
  await page.screenshot({ path: 'test-results/diagnose-v8-final.png', fullPage: false });
});
