/**
 * Diagnostic test v4 - Testing Choices.js interaction
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
  await page.waitForTimeout(2000);
}

test('Test Choices.js Interaction', async ({ page }) => {
  test.setTimeout(60000);
  
  await login(page);
  await safeGoto(page, `${BASE_URL}/admin/order-requests/create`);
  
  console.log(`URL: ${page.url()}`);
  
  // Wait for Alpine to init
  await page.waitForTimeout(2000);
  
  // Check if Choices.js is initialized
  const choicesInfo = await page.evaluate(() => {
    const choicesEl = document.querySelector('.choices');
    const choicesInput = document.querySelector('.choices__input');
    const choicesInner = document.querySelector('.choices__inner');
    const tsDropdown = document.querySelector('.choices__list--dropdown');
    
    return {
      hasChoices: !!choicesEl,
      hasInput: !!choicesInput,
      hasInner: !!choicesInner,
      hasDropdown: !!tsDropdown,
      choicesElInfo: choicesEl ? {
        classes: choicesEl.className,
        children: Array.from(choicesEl.children).map(c => ({
          tag: c.tagName,
          classes: c.className
        }))
      } : null,
      inputInfo: choicesInput ? {
        type: choicesInput.type,
        classes: choicesInput.className,
        placeholder: choicesInput.placeholder,
        visible: window.getComputedStyle(choicesInput).display !== 'none'
      } : null
    };
  });
  
  console.log('Choices.js info:', JSON.stringify(choicesInfo, null, 2));
  
  // Strategy: Click inside choices__inner to open dropdown, then type in choices__input
  console.log('\n=== Testing Choices.js click + search ===');
  
  const choicesInner = page.locator('.choices__inner').first();
  const choicesCount = await page.locator('.choices').count();
  console.log(`choices.js elements: ${choicesCount}`);
  
  if (await choicesInner.isVisible({ timeout: 2000 }).catch(() => false)) {
    console.log('Found .choices__inner, clicking...');
    await choicesInner.click();
    await page.waitForTimeout(500);
    
    // Now look for the search input
    const searchInput = page.locator('.choices__input.choices__input--cloned, input.choices__input');
    const searchCount = await searchInput.count();
    console.log(`Search inputs found: ${searchCount}`);
    
    if (searchCount > 0 && await searchInput.first().isVisible({ timeout: 1000 }).catch(() => false)) {
      await searchInput.first().fill('Cabang');
      await page.waitForTimeout(1000);
      
      // Check dropdown
      const dropdownItems = await page.evaluate(() => {
        const items = document.querySelectorAll('.choices__item--selectable:not(.choices__item--disabled)');
        return Array.from(items).map(i => i.textContent?.trim()).slice(0, 5);
      });
      console.log('Dropdown items:', dropdownItems);
      
      // Take screenshot
      await page.screenshot({ path: 'test-results/diagnose-v4-dropdown.png', fullPage: false });
      
      // Click first item
      const firstItem = page.locator('.choices__item--selectable:not(.choices__item--disabled)').first();
      if (await firstItem.isVisible({ timeout: 1000 }).catch(() => false)) {
        await firstItem.click();
        console.log('Clicked first item!');
        
        const selValue = await page.locator('#data\\.cabang_id').inputValue();
        console.log(`Selected value: ${selValue}`);
      }
    } else {
      console.log('No search input found after clicking');
      await page.screenshot({ path: 'test-results/diagnose-v4-no-input.png', fullPage: false });
      
      // Check what's visible
      const visibleAfterClick = await page.evaluate(() => {
        const allInputs = Array.from(document.querySelectorAll('input, textarea'));
        return allInputs
          .filter(i => window.getComputedStyle(i).display !== 'none')
          .map(i => ({
            id: i.id,
            class: (i.className || '').substring(0, 100),
            type: i.type,
            placeholder: i.placeholder
          }));
      });
      console.log('Visible inputs after click:', JSON.stringify(visibleAfterClick.slice(0, 10), null, 2));
    }
  } else {
    console.log('choices__inner NOT found! Alpine may not have initialized yet.');
    console.log('Trying to click the <select> directly...');
    
    const selectEl = page.locator('#data\\.cabang_id');
    await selectEl.click();
    await page.waitForTimeout(500);
    
    await page.screenshot({ path: 'test-results/diagnose-v4-click-select.png', fullPage: false });
    
    const afterClickInputs = await page.evaluate(() => {
      const allInputs = Array.from(document.querySelectorAll('input'));
      return allInputs
        .filter(i => window.getComputedStyle(i).display !== 'none')
        .map(i => ({
          id: i.id,
          class: (i.className || '').substring(0, 100),
          type: i.type,
          placeholder: i.placeholder,
          ariaHaspopup: i.getAttribute('aria-haspopup'),
          ariaExpanded: i.getAttribute('aria-expanded')
        }));
    });
    console.log('Visible inputs after direct click:', JSON.stringify(afterClickInputs.slice(0, 10), null, 2));
  }
  
  expect(true).toBe(true);
});
