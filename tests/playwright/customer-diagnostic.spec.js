import { test, expect } from '@playwright/test';

test.describe('Customer Supplier Diagnostic', () => {

  // Helper function for login
  async function login(page) {
    await page.goto('/admin/login');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  }

  test('diagnose customers page', async ({ page }) => {
    await login(page);

    // Navigate to customers page
    await page.goto('/admin/customers');
    await page.waitForLoadState('networkidle');

    // Take screenshot for debugging
    await page.screenshot({ path: 'customers-page.png', fullPage: true });

    // Log page URL and title
    console.log('Current URL:', page.url());
    console.log('Page title:', await page.title());

    // Look for Filament-specific create button selectors
    console.log('=== FILAMENT CREATE BUTTON CHECKS ===');

    // Check for Filament create action button
    const createButtonSelectors = [
      'a[href*="create"]',
      'button[data-action="create"]',
      '.fi-btn:has(.fi-btn-label)',
      '.fi-header-actions .fi-btn',
      '[wire\\:click*="create"]',
      'button.fi-btn',
      '.fi-page-actions .fi-btn',
      '.fi-header .fi-btn'
    ];

    for (const selector of createButtonSelectors) {
      try {
        const count = await page.locator(selector).count();
        console.log(`${selector}: ${count}`);
        if (count > 0) {
          const elements = await page.locator(selector).all();
          for (let i = 0; i < Math.min(elements.length, 3); i++) {
            const text = await elements[i].textContent();
            const className = await elements[i].getAttribute('class');
            const href = await elements[i].getAttribute('href');
            console.log(`  Element ${i}: text="${text}", class="${className}", href="${href}"`);
          }
        }
      } catch (e) {
        console.log(`${selector}: Error - ${e.message}`);
      }
    }

    // Check for any button containing "plus" icon or create-related text
    console.log('\n=== ICON AND TEXT BASED SEARCH ===');
    const iconSelectors = [
      'button:has(.heroicon-o-plus-circle)',
      'button:has(.heroicon-o-plus)',
      'button:has(svg)',
      'a:has(.heroicon-o-plus-circle)',
      'a:has(.heroicon-o-plus)'
    ];

    for (const selector of iconSelectors) {
      try {
        const count = await page.locator(selector).count();
        console.log(`${selector}: ${count}`);
        if (count > 0) {
          const elements = await page.locator(selector).all();
          for (let i = 0; i < Math.min(elements.length, 2); i++) {
            const text = await elements[i].textContent();
            const className = await elements[i].getAttribute('class');
            console.log(`  Element ${i}: text="${text}", class="${className}"`);
          }
        }
      } catch (e) {
        console.log(`${selector}: Error - ${e.message}`);
      }
    }

    // Check header area specifically
    console.log('\n=== HEADER AREA ANALYSIS ===');
    try {
      const header = await page.locator('.fi-header, .fi-page-header, header').first();
      if (await header.count() > 0) {
        const headerButtons = await header.locator('button, a').all();
        console.log(`Header buttons found: ${headerButtons.length}`);
        for (let i = 0; i < Math.min(headerButtons.length, 5); i++) {
          const text = await headerButtons[i].textContent();
          const tagName = await headerButtons[i].evaluate(el => el.tagName);
          const className = await headerButtons[i].getAttribute('class');
          console.log(`  Header ${tagName} ${i}: text="${text}", class="${className}"`);
        }
      } else {
        console.log('No header found with common selectors');
      }
    } catch (e) {
      console.log(`Header analysis error: ${e.message}`);
    }

    // Check for any element with "create" in attributes
    console.log('\n=== ELEMENTS WITH CREATE IN ATTRIBUTES ===');
    try {
      const allElements = await page.locator('*').all();
      let createElements = [];
      for (const element of allElements.slice(0, 1000)) { // Limit to first 1000 elements
        try {
          const attributes = await element.evaluate(el => {
            const attrs = {};
            for (let attr of el.attributes) {
              if (attr.name.includes('create') || attr.value.includes('create')) {
                attrs[attr.name] = attr.value;
              }
            }
            return attrs;
          });
          if (Object.keys(attributes).length > 0) {
            const tagName = await element.evaluate(el => el.tagName);
            const className = await element.getAttribute('class') || '';
            createElements.push({ tagName, className, attributes });
          }
        } catch (e) {
          // Skip elements that cause errors
        }
      }
      console.log(`Elements with create-related attributes: ${createElements.length}`);
      createElements.slice(0, 5).forEach((el, i) => {
        console.log(`  ${i}: ${el.tagName}.${el.className} - ${JSON.stringify(el.attributes)}`);
      });
    } catch (e) {
      console.log(`Create attributes search error: ${e.message}`);
    }

    // Just pass the test - this is for diagnostics
    expect(true).toBe(true);
  });

  test('diagnose suppliers page', async ({ page }) => {
    await login(page);

    // Navigate to suppliers page
    await page.goto('/admin/suppliers');
    await page.waitForLoadState('networkidle');

    console.log('Current URL:', page.url());
    console.log('Page title:', await page.title());

    // Check for create button
    const createSelectors = [
      'a[href*="suppliers/create"]',
      'a[href*="create"]',
      '.fi-btn:has(.fi-btn-label)',
      '.fi-header-actions .fi-btn',
      'button.fi-btn'
    ];

    for (const selector of createSelectors) {
      try {
        const count = await page.locator(selector).count();
        console.log(`${selector}: ${count}`);
        if (count > 0) {
          const elements = await page.locator(selector).all();
          for (let i = 0; i < Math.min(elements.length, 2); i++) {
            const text = await elements[i].textContent();
            const className = await elements[i].getAttribute('class');
            const href = await elements[i].getAttribute('href');
            console.log(`  Element ${i}: text="${text}", class="${className}", href="${href}"`);
          }
        }
      } catch (e) {
        console.log(`${selector}: Error - ${e.message}`);
      }
    }

    // Just pass the test - this is for diagnostics
    expect(true).toBe(true);
  });

  test('diagnose create customer form', async ({ page }) => {
    await login(page);

    // Navigate to customers page
    await page.goto('/admin/customers');
    await page.waitForLoadState('networkidle');

    // Click create customer button
    await page.click('a[href*="create"]');
    await page.waitForLoadState('networkidle');

    // Take screenshot of form
    await page.screenshot({ path: 'create-customer-form.png', fullPage: true });

    console.log('Current URL:', page.url());
    console.log('Page title:', await page.title());

    // Log all form inputs
    console.log('=== FORM INPUTS ===');
    const inputs = await page.locator('input').all();
    for (const input of inputs.slice(0, 20)) { // Limit to first 20
      const id = await input.getAttribute('id');
      const name = await input.getAttribute('name');
      const type = await input.getAttribute('type');
      const value = await input.getAttribute('value');
      if (id || name) {
        console.log(`Input: id="${id}", name="${name}", type="${type}", value="${value}"`);
      }
    }

    // Log all textareas
    console.log('\n=== TEXTAREAS ===');
    const textareas = await page.locator('textarea').all();
    for (const textarea of textareas) {
      const id = await textarea.getAttribute('id');
      const name = await textarea.getAttribute('name');
      if (id || name) {
        console.log(`Textarea: id="${id}", name="${name}"`);
      }
    }

    // Log all radio buttons and checkboxes
    console.log('\n=== RADIO BUTTONS ===');
    const radios = await page.locator('input[type="radio"]').all();
    for (const radio of radios) {
      const id = await radio.getAttribute('id');
      const name = await radio.getAttribute('name');
      const value = await radio.getAttribute('value');
      const checked = await radio.isChecked();
      console.log(`Radio: id="${id}", name="${name}", value="${value}", checked=${checked}`);
    }

    console.log('\n=== CHECKBOXES ===');
    const checkboxes = await page.locator('input[type="checkbox"]').all();
    for (const checkbox of checkboxes) {
      const id = await checkbox.getAttribute('id');
      const name = await checkbox.getAttribute('name');
      const value = await checkbox.getAttribute('value');
      const checked = await checkbox.isChecked();
      console.log(`Checkbox: id="${id}", name="${name}", value="${value}", checked=${checked}`);
    }

    // Log all submit buttons
    console.log('\n=== SUBMIT BUTTONS ===');
    const submitButtons = await page.locator('button[type="submit"], input[type="submit"]').all();
    for (const submitButton of submitButtons) {
      const text = await submitButton.textContent();
      const className = await submitButton.getAttribute('class');
      const visible = await submitButton.isVisible();
      console.log(`Submit button: text="${text?.trim()}", class="${className}", visible=${visible}`);
    }

    // Log all buttons with "save" or "submit" in text
    console.log('\n=== SAVE/SUBMIT BUTTONS ===');
    const allButtons = await page.locator('button').all();
    for (const button of allButtons) {
      const text = await button.textContent();
      const type = await button.getAttribute('type');
      const className = await button.getAttribute('class');
      if (text && (text.toLowerCase().includes('save') || text.toLowerCase().includes('submit') || text.toLowerCase().includes('simpan') || text.toLowerCase().includes('buat'))) {
        const visible = await button.isVisible();
        console.log(`Button: text="${text?.trim()}", type="${type}", class="${className}", visible=${visible}`);
      }
    }

    // Just pass the test - this is for diagnostics
    expect(true).toBe(true);
  });

  test('diagnose validation errors', async ({ page }) => {
    await login(page);

    // Navigate to customers page
    await page.goto('/admin/customers');
    await page.waitForLoadState('networkidle');

    // Click create customer button
    await page.click('a[href*="create"]');
    await page.waitForLoadState('networkidle');

    // Fill form with invalid email
    await page.fill('#data\\.code', 'TEST-VALIDATION');
    await page.fill('#data\\.name', 'Validation Test');
    await page.fill('#data\\.perusahaan', 'Validation Company');
    await page.fill('#data\\.nik_npwp', '1234567890123456');
    await page.fill('#data\\.address', 'Test Address');
    await page.fill('#data\\.telephone', '02112345678');
    await page.fill('#data\\.email', 'invalid-email'); // Invalid email

    // Try to submit
    await page.click('button:has-text("Buat")');
    await page.waitForLoadState('networkidle');

    console.log('Current URL after submit:', page.url());

    // Look for validation errors
    console.log('=== VALIDATION ERRORS ===');
    const errorSelectors = [
      '.fi-field-wrapper-error-message',
      '.text-red-600',
      '.text-danger',
      '[class*="error"]',
      '.invalid-feedback'
    ];

    for (const selector of errorSelectors) {
      try {
        const count = await page.locator(selector).count();
        console.log(`${selector}: ${count}`);
        if (count > 0) {
          const errors = await page.locator(selector).all();
          for (let i = 0; i < Math.min(errors.length, 3); i++) {
            const text = await errors[i].textContent();
            console.log(`  Error ${i}: "${text}"`);
          }
        }
      } catch (e) {
        console.log(`${selector}: Error - ${e.message}`);
      }
    }

    // Check if we're still on create page or redirected
    const currentUrl = page.url();
    if (currentUrl.includes('create')) {
      console.log('Still on create page - validation prevented submission');
    } else {
      console.log('Redirected - form submitted despite invalid data');
    }

    // Just pass the test - this is for diagnostics
    expect(true).toBe(true);
  });

});