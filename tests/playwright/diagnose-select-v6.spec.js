/**
 * Diagnostic test v6 - Wait for Choices.js network request
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

test('Test x-load-src loading', async ({ page }) => {
  test.setTimeout(60000);

  // Track network requests  
  const jsRequests = [];
  page.on('request', req => {
    if (req.resourceType() === 'script' || req.url().includes('.js')) {
      jsRequests.push(req.url().substring(req.url().lastIndexOf('/') + 1).substring(0, 80));
    }
  });
  
  await login(page);
  
  // Monitor responses for select.js
  let selectJsLoaded = false;
  page.on('response', async (response) => {
    if (response.url().includes('select') && response.url().includes('.js')) {
      selectJsLoaded = true;
      console.log(`select.js loaded: ${response.url().substring(0, 100)}`);
    }
  });
  
  try {
    await page.goto(`${BASE_URL}/admin/order-requests/create`, { waitUntil: 'domcontentloaded' });
  } catch (e) {
    if (!e.message.includes('ERR_ABORTED')) throw e;
  }
  
  // Check x-load-src attribute
  const xloadSrc = await page.evaluate(() => {
    const selects = document.querySelectorAll('[x-data*="selectFormComponent"][x-load-src]');
    return Array.from(selects).map(el => el.getAttribute('x-load-src'));
  });
  console.log('x-load-src:', JSON.stringify(xloadSrc.slice(0, 3), null, 2));
  
  // Wait for selectFormComponent to be defined in window
  const waitResult = await page.waitForFunction(() => {
    return typeof window.selectFormComponent !== 'undefined' || 
           document.querySelector('.choices') !== null ||
           (window.Alpine && window.Alpine.store && Object.keys(window).some(k => k.includes('choices')));
  }, { timeout: 15000 }).catch(e => null);
  
  console.log(`selectFormComponent loaded: ${waitResult !== null}`);
  
  // Check what's available
  const windowInfo = await page.evaluate(() => {
    return {
      hasAlpine: !!window.Alpine,
      hasChoices: !!(window.Choices || window.choices),
      alpineVersions: window.Alpine?.version,
      choicesCount: document.querySelectorAll('.choices').length,
      selectFormComponentDefined: typeof window.selectFormComponent !== 'undefined'
    };
  });
  console.log('Window info:', JSON.stringify(windowInfo, null, 2));
  
  // Check the x-load attribute - does it have 'visible'?
  const xloadAttr = await page.evaluate(() => {
    const firstSelect = document.querySelector('[x-data*="selectFormComponent"]');
    return {
      xload: firstSelect?.getAttribute('x-load'),
      xloadSrc: firstSelect?.getAttribute('x-load-src')?.substring(0, 100)
    };
  });
  console.log('First select x-load attributes:', JSON.stringify(xloadAttr, null, 2));
  
  // Log network requests
  console.log('\nJS requests:', jsRequests.slice(0, 20));
  console.log('select.js loaded:', selectJsLoaded);
  
  await page.screenshot({ path: 'test-results/diagnose-v6.png', fullPage: false });
  
  expect(true).toBe(true);
});
