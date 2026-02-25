/**
 * Diagnostic test v2 - Understanding Filament Select interaction
 * Uses try/catch for ERR_ABORTED and tests selectOption
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
    if (!e.message.includes('ERR_ABORTED')) throw e;
    console.log(`Note: ERR_ABORTED on goto (normal for Livewire): ${url}`);
  }
  await page.waitForTimeout(1000);
}

test('Diagnose Filament Select v2', async ({ page }) => {
  test.setTimeout(120000);
  
  await login(page);
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  await page.waitForTimeout(2000);
  
  console.log(`Current URL: ${page.url()}`);
  
  // Get the select element info
  const info = await page.evaluate(() => {
    const sel = document.querySelector('#data\\.cabang_id');
    if (!sel) return { error: 'NOT FOUND' };
    
    const computed = window.getComputedStyle(sel);
    const parent = sel.parentElement;
    
    // Get options
    const options = Array.from(sel.options).map(o => ({ value: o.value, text: o.text }));
    
    // Get all children of grandparent (the Alpine x-data container)
    let container = sel;
    while (container && !container.hasAttribute('x-data')) {
      container = container.parentElement;
    }
    
    const containerInfo = container ? {
      tag: container.tagName,
      xdata: container.getAttribute('x-data')?.substring(0, 200),
      classes: container.className?.substring(0, 200),
      childrenCount: container.children.length,
      children: Array.from(container.children).map(c => ({
        tag: c.tagName,
        id: c.id,
        classes: c.className?.substring(0, 100),
        role: c.getAttribute('role'),
        type: c.getAttribute('type'),
        visible: window.getComputedStyle(c).display !== 'none',
        text: c.textContent?.trim()?.substring(0, 50)
      }))
    } : null;
    
    return {
      select: {
        display: computed.display,
        visibility: computed.visibility,
        opacity: computed.opacity,
        pointerEvents: computed.pointerEvents,
        width: computed.width,
        role: sel.getAttribute('role'),
        xref: sel.getAttribute('x-ref'),
        classes: sel.className,
        optionCount: options.length,
        options: options.slice(0, 5)
      },
      containerInfo
    };
  });
  
  console.log('=== CABANG SELECT INFO ===');
  console.log(JSON.stringify(info, null, 2));
  
  // Test 1: selectOption directly on the select
  console.log('\n=== Test 1: selectOption by index 1 ===');
  try {
    const sel = page.locator('#data\\.cabang_id');
    const cnt = await sel.count();
    console.log(`Select count: ${cnt}`);
    if (cnt > 0) {
      await sel.selectOption({ index: 1 });
      const val = await sel.inputValue();
      console.log(`selectOption SUCCESS, value: ${val}`);
    }
  } catch (e) {
    console.log(`selectOption FAILED: ${e.message}`);
  }
  
  // Test 2: Click on the visible part of the Filament select
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  await page.waitForTimeout(2000);
  
  console.log('\n=== Test 2: Find visible Filament select trigger ===');
  
  // Find the Alpine container for cabang
  const alpineContainerHTML = await page.evaluate(() => {
    const sel = document.querySelector('#data\\.cabang_id');
    if (!sel) return 'NOT FOUND';
    let container = sel;
    while (container && !container.hasAttribute('x-data')) {
      container = container.parentElement;
    }
    return container?.outerHTML?.substring(0, 2000) || 'NO CONTAINER';
  });
  console.log('\n=== Alpine Container HTML ===');
  console.log(alpineContainerHTML);
  
  await page.screenshot({ path: 'test-results/diagnose-v2.png', fullPage: false });
  
  expect(true).toBe(true);
});
