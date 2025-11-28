import { test, expect } from '@playwright/test';

test.describe('Quotation Form Diagnostic', () => {
  test('inspect quotation create form', async ({ page }) => {
    // Login first
    await page.goto('/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    // Navigate to create quotation page
    await page.goto('/admin/quotations/create');
    await page.waitForLoadState('networkidle');

    // Wait for form to load
    await page.waitForTimeout(3000);

    console.log('=== PAGE TITLE ===');
    console.log('Page title:', await page.title());

    console.log('=== ALL FORM INPUTS ===');
    const allInputs = await page.locator('input, select, textarea').all();
    for (let i = 0; i < allInputs.length; i++) {
      const input = allInputs[i];
      const tagName = await input.evaluate(el => el.tagName.toLowerCase());
      const type = await input.getAttribute('type') || 'N/A';
      const name = await input.getAttribute('name') || 'N/A';
      const id = await input.getAttribute('id') || 'N/A';
      const placeholder = await input.getAttribute('placeholder') || 'N/A';
      console.log(`${i}: ${tagName} - type: ${type}, name: ${name}, id: ${id}, placeholder: ${placeholder}`);
    }

    console.log('=== FIELD WRAPPER ANALYSIS ===');
    const fieldWrappers = await page.locator('[data-field-wrapper]').all();
    for (let i = 0; i < fieldWrappers.length; i++) {
      const wrapper = fieldWrappers[i];
      const text = await wrapper.textContent();
      const classes = await wrapper.getAttribute('class') || '';
      console.log(`${i}: "${text?.trim()}" - classes: ${classes}`);
    }

    console.log('=== CHOICES.JS ELEMENTS ===');
    const choicesElements = await page.locator('.choices').all();
    console.log(`Found ${choicesElements.length} choices elements`);

    console.log('=== SELECT ELEMENTS ===');
    const selectElements = await page.locator('select').all();
    for (let i = 0; i < selectElements.length; i++) {
      const select = selectElements[i];
      const name = await select.getAttribute('name') || 'N/A';
      const id = await select.getAttribute('id') || 'N/A';
      console.log(`${i}: select - name: ${name}, id: ${id}`);
    }

    console.log('=== LABELS ===');
    const labels = await page.locator('label').all();
    for (let i = 0; i < labels.length; i++) {
      const label = labels[i];
      const text = await label.textContent();
      const forAttr = await label.getAttribute('for') || 'N/A';
      console.log(`${i}: "${text?.trim()}" - for: ${forAttr}`);
    }

    // Take screenshot for visual inspection
    await page.screenshot({ path: 'quotation-form-inspection.png', fullPage: true });

    // Keep the page open for manual inspection
    await page.waitForTimeout(10000);
  });
});