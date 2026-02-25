/**
 * Diagnostic test v9 - Check why Choices.js doesn't initialize
 */
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8009';

test('v9 - Check Choices.js init', async ({ page }) => {
  test.setTimeout(120000);

  // Capture console errors
  const consoleErrors = [];
  const consoleMessages = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    } else if (msg.type() === 'log') {
      consoleMessages.push(msg.text().substring(0, 200));
    }
  });
  
  // Capture failed requests
  const failedRequests = [];
  page.on('requestfailed', req => {
    failedRequests.push({ url: req.url().substring(0, 150), failure: req.failure()?.errorText });
  });
  
  // Go to login
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');
  
  // Login
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/admin**', { timeout: 20000 });
  await page.waitForLoadState('networkidle');
  console.log(`After login URL: ${page.url()}`);
  
  // Navigate directly (may need second attempt)
  try {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'domcontentloaded' });
  } catch (e) {
    if (!e.message.includes('ERR_ABORTED') && !e.message.includes('ERR_FAILED')) throw e;
  }
  
  // Check if we're on the right page
  const currentUrl = page.url();
  if (!currentUrl.includes('/order-requests/create')) {
    console.log(`Not on create page (${currentUrl}), trying again...`);
    try {
      await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'networkidle' });
    } catch (e) {
      if (!e.message.includes('ERR_ABORTED') && !e.message.includes('ERR_FAILED')) throw e;
    }
  }
  
  await page.waitForLoadState('networkidle');
  console.log(`Final URL: ${page.url()}`);
  
  // Wait 5 seconds and check progressively  
  for (let i = 1; i <= 10; i++) {
    await page.waitForTimeout(1000);
    const state = await page.evaluate(() => ({
      choicesInnerCount: document.querySelectorAll('.choices__inner').length,
      choicesCount: document.querySelectorAll('.choices').length,
      xDataSelectCount: document.querySelectorAll('[x-data*="selectFormComponent"]').length,
      selectFormComponentRegistered: typeof window.selectFormComponent !== 'undefined',
    }));
    console.log(`t+${i}s: choices=${state.choicesCount}, inner=${state.choicesInnerCount}, xdata=${state.xDataSelectCount}, fn=${state.selectFormComponentRegistered}`);
    if (state.choicesCount > 0) {
      console.log('Choices.js initialized!');
      break;
    }
  }
  
  // Check console errors
  console.log('\nConsole errors:', JSON.stringify(consoleErrors.slice(0, 5), null, 2));
  console.log('Failed requests:', JSON.stringify(failedRequests.slice(0, 5), null, 2));
  
  // Check network requests for select.js
  const selectJsStatus = await page.evaluate(() => {
    const selectJsUrl = '/js/filament/forms/components/select.js';
    return fetch(selectJsUrl).then(r => r.status);
  }).catch(e => `Error: ${e.message}`);
  console.log('select.js fetch status:', selectJsStatus);
  
  // Check if Alpine is initialized and has the selectFormComponent data
  const alpineInfo = await page.evaluate(() => {
    return {
      hasAlpine: !!window.Alpine,
      alpineVersion: window.Alpine?.version,
      hasAsyncAlpine: !!window.AsyncAlpine,
      asyncAlpineData: window.AsyncAlpine?._data ? Object.keys(window.AsyncAlpine._data) : null
    };
  });
  console.log('Alpine info:', JSON.stringify(alpineInfo, null, 2));
  
  await page.screenshot({ path: 'test-results/diagnose-v9.png', fullPage: false });
  expect(page.url()).toContain('/order-requests/create');
});
