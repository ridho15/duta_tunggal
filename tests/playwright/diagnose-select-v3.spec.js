/**
 * Diagnostic test v3 - Testing TomSelect/Alpine interaction
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
  await page.waitForTimeout(1500);
}

test('Diagnose TomSelect Interaction', async ({ page }) => {
  test.setTimeout(60000);
  
  await login(page);
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  
  console.log(`URL: ${page.url()}`);
  
  // Method 1: Click the visible select directly (this should open TomSelect dropdown)
  console.log('\n=== Method 1: Click select directly ===');
  
  const selectEl = page.locator('#data\\.cabang_id');
  await selectEl.click();
  await page.waitForTimeout(1000);
  
  // After clicking, what new elements appeared?
  const afterClickInfo = await page.evaluate(() => {
    // Look for TomSelect container
    const tsWrappers = document.querySelectorAll('.ts-wrapper, .ts-dropdown, [data-ts]');
    const tsInputs = document.querySelectorAll('.ts-input, .ts-control input');
    const newInputs = document.querySelectorAll('input[aria-haspopup], input[aria-expanded]');
    const allInputs = Array.from(document.querySelectorAll('input:not([type=hidden])'))
      .filter(i => {
        const c = window.getComputedStyle(i);
        return c.display !== 'none';
      })
      .map(i => ({
        id: i.id,
        classes: i.className?.substring(0, 100),
        placeholder: i.placeholder,
        type: i.type,
        role: i.getAttribute('role'),
        ariaExpanded: i.getAttribute('aria-expanded')
      }));
      
    return {
      tsWrapperCount: tsWrappers.length,
      tsInputCount: tsInputs.length,
      allVisibleInputs: allInputs.slice(0, 10),
      // Check for any new overlays
      overlays: Array.from(document.querySelectorAll('[class*="dropdown"], [class*="overlay"], [class*="listbox"], [class*="popup"]'))
        .filter(el => window.getComputedStyle(el).display !== 'none')
        .map(el => ({
          tag: el.tagName,
          classes: el.className?.substring(0, 100),
          visible: true
        }))
        .slice(0, 5)
    };
  });
  
  console.log('After click info:', JSON.stringify(afterClickInfo, null, 2));
  
  // Take screenshot after click
  await page.screenshot({ path: 'test-results/diagnose-v3-after-click.png', fullPage: false });
  
  // Method 2: Try Alpine dispatch to set value directly
  console.log('\n=== Method 2: Alpine dispatch ===');
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  await page.waitForTimeout(1500);
  
  // Try dispatching an Alpine custom event to set the value
  const dispatchResult = await page.evaluate(() => {
    const sel = document.querySelector('#data\\.cabang_id');
    if (!sel) return { error: 'select not found' };
    
    // Try to get Alpine component
    const container = sel.closest('[x-data]');
    if (!container) return { error: 'no x-data container' };
    
    const alpine = window.Alpine;
    if (!alpine) return { error: 'no Alpine' };
    
    const data = alpine.$data(container);
    return {
      hasAlpine: true,
      dataKeys: Object.keys(data || {}),
      state: JSON.stringify(data?.state)?.substring(0, 200)
    };
  });
  console.log('Alpine data:', JSON.stringify(dispatchResult, null, 2));
  
  // Method 3: Try keyboard interaction after focus
  console.log('\n=== Method 3: Click + type ===');
  await selectEl.click();
  await page.waitForTimeout(500);
  await page.keyboard.type('Cabang');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/diagnose-v3-typed.png', fullPage: false });
  
  // Any options/listbox visible?
  const listboxHTML = await page.evaluate(() => {
    const lb = document.querySelector('[role="listbox"], [role="option"], .ts-dropdown-content, .choices__list--dropdown');
    return lb?.outerHTML?.substring(0, 1000) || 'NO LISTBOX';
  });
  console.log('\n=== Listbox after typing ===');
  console.log(listboxHTML);
  
  expect(true).toBe(true);
});
