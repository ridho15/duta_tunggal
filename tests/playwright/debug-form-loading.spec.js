import { test, expect } from '@playwright/test';

test.describe('Debug Form Loading', () => {
  test('check quotation form loading and console errors', async ({ page }) => {
    // Listen for console errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    // Listen for page errors
    page.on('pageerror', error => {
      errors.push(`Page error: ${error.message}`);
    });

    // Login first
    await page.goto('http://localhost:8009/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    console.log('=== CONSOLE ERRORS ===');
    if (errors.length > 0) {
      errors.forEach(error => console.log('ERROR:', error));
    } else {
      console.log('No console errors found');
    }

    // Navigate to create quotation page
    await page.goto('http://localhost:8009/admin/quotations/create');
    await page.waitForLoadState('networkidle');

    // Wait for form to load
    await page.waitForTimeout(3000);

    console.log('=== FORM ELEMENTS ===');
    const customerSelect = page.locator('#data\\.customer_id');
    const isVisible = await customerSelect.isVisible();
    console.log('Customer select visible:', isVisible);

    if (!isVisible) {
      const exists = await customerSelect.count() > 0;
      console.log('Customer select exists:', exists);
      
      if (exists) {
        const attributes = await customerSelect.evaluate(el => {
          const attrs = {};
          for (let attr of el.attributes) {
            attrs[attr.name] = attr.value;
          }
          return attrs;
        });
        console.log('Customer select attributes:', attributes);
        
        // Check parent elements
        const parentHtml = await customerSelect.evaluate(el => el.parentElement?.outerHTML);
        console.log('Parent HTML:', parentHtml?.substring(0, 500) + '...');
      }
    }

    // Look for Choices.js elements
    console.log('=== CHOICES.JS ELEMENTS ===');
    const choicesElements = await page.locator('.choices').all();
    console.log(`Found ${choicesElements.length} choices elements`);
    
    for (let i = 0; i < choicesElements.length; i++) {
      const choicesEl = choicesElements[i];
      const html = await choicesEl.evaluate(el => el.outerHTML);
      console.log(`Choices ${i}:`, html.substring(0, 200) + '...');
    }

    // Look for any select elements
    console.log('=== ALL SELECT ELEMENTS ===');
    const allSelects = await page.locator('select').all();
    for (let i = 0; i < allSelects.length; i++) {
      const select = allSelects[i];
      const id = await select.getAttribute('id') || 'no-id';
      const classes = await select.getAttribute('class') || 'no-class';
      const dataChoice = await select.getAttribute('data-choice') || 'no-data-choice';
      console.log(`Select ${i}: id=${id}, class=${classes}, data-choice=${dataChoice}`);
    }

    // Check network requests for any failed AJAX calls
    console.log('=== NETWORK REQUESTS ===');
    const failedRequests = [];
    page.on('response', response => {
      if (!response.ok() && response.url().includes('/admin/')) {
        failedRequests.push(`${response.status()} ${response.url()}`);
      }
    });

    // Wait a bit more to catch any delayed requests
    await page.waitForTimeout(2000);

    if (failedRequests.length > 0) {
      console.log('Failed requests:');
      failedRequests.forEach(req => console.log('  ', req));
    } else {
      console.log('No failed admin requests');
    }

    // Simulate adding an item and check the form
    console.log('=== SIMULATING ADD ITEM ===');
    try {
      await page.click('button:has-text("Tambahkan ke quotation item")');
      await page.waitForTimeout(2000);
      
      console.log('After adding item - checking inputs:');
      const allInputsAfter = await page.locator('input, select, textarea').all();
      for (let i = 0; i < allInputsAfter.length; i++) {
        const input = allInputsAfter[i];
        const tagName = await input.evaluate(el => el.tagName.toLowerCase());
        const name = await input.getAttribute('name') || 'N/A';
        const id = await input.getAttribute('id') || 'N/A';
        if (name.includes('quantity') || name.includes('unit_price') || name.includes('items')) {
          console.log(`Item input ${i}: ${tagName} - name: ${name}, id: ${id}`);
        }
      }
    } catch (e) {
      console.log('Error simulating add item:', e.message);
    }

    // Take screenshot
    await page.screenshot({ path: 'quotation-form-debug.png', fullPage: true });

    // Force failure to see the debug output
    expect(errors.length).toBe(0);
  });
});