/**
 * Diagnostic test to understand Filament Select DOM structure
 */
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8009';

test('Diagnose Filament Select', async ({ page }) => {
  // Login
  await page.goto(`${BASE_URL}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/admin**', { timeout: 20000 });
  await page.waitForLoadState('networkidle');

  await page.goto(`${BASE_URL}/admin/order-requests/create`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  // Get the select element info
  const info = await page.evaluate(() => {
    const sel = document.querySelector('#data\\.cabang_id');
    if (!sel) return { error: 'NOT FOUND' };
    
    const computed = window.getComputedStyle(sel);
    const parent = sel.parentElement;
    const grandParent = parent?.parentElement;
    
    // Get options
    const options = Array.from(sel.options).map(o => ({ value: o.value, text: o.text }));
    
    // Get all siblings in the wrapper
    const siblings = parent ? Array.from(parent.children).map(c => ({
      tag: c.tagName,
      id: c.id,
      classes: c.className.substring(0, 100),
      role: c.getAttribute('role'),
      type: c.getAttribute('type'),
      visible: window.getComputedStyle(c).display !== 'none',
      text: c.textContent?.substring(0, 50)
    })) : [];
    
    return {
      select: {
        display: computed.display,
        visibility: computed.visibility,
        width: computed.width,
        height: computed.height,
        role: sel.getAttribute('role'),
        xref: sel.getAttribute('x-ref'),
        classes: sel.className,
        optionCount: options.length,
        options: options.slice(0, 5)
      },
      parentTag: parent?.tagName,
      parentClasses: parent?.className?.substring(0, 200),
      grandParentTag: grandParent?.tagName,
      grandParentChildren: grandParent ? Array.from(grandParent.children).map(c => ({
        tag: c.tagName,
        id: c.id,
        classes: c.className.substring(0, 100),
        role: c.getAttribute('role'),
        type: c.getAttribute('type'),
        visible: window.getComputedStyle(c).display !== 'none'
      })) : []
    };
  });
  
  console.log('=== CABANG SELECT INFO ===');
  console.log(JSON.stringify(info, null, 2));
  
  // Try selectOption directly
  console.log('\n=== Trying selectOption ===');
  try {
    await page.locator('#data\\.cabang_id').selectOption({ index: 1 });
    console.log('selectOption by index: SUCCESS');
    const val = await page.locator('#data\\.cabang_id').inputValue();
    console.log(`Selected value: ${val}`);
  } catch (e) {
    console.log(`selectOption failed: ${e.message}`);
  }
  
  // Try selectOption by label
  console.log('\n=== Trying selectOption by label "Cabang 1" ===');
  try {
    await page.locator('#data\\.cabang_id').selectOption({ label: 'Cabang 1' });
    console.log('selectOption by label: SUCCESS');
  } catch (e) {
    console.log(`selectOption by label failed: ${e.message}`);
  }
  
  await page.screenshot({ path: 'test-results/diagnose-select.png', fullPage: false });
  expect(true).toBe(true);
});
