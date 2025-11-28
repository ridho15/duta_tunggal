import { test, expect } from '@playwright/test';
import fs from 'fs';

test.describe('Quotation stock info', () => {
  async function login(page) {
    await page.goto('http://localhost:8009/admin/login');
    await page.fill('input[id="data.email"]', 'ralamzah@gmail.com');
    await page.fill('input[id="data.password"]', 'ridho123');
    await page.click('button[type="submit"]:has-text("Masuk")');
    await page.waitForURL('**/admin/**', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
  }

  test('select product populates stock_info textarea', async ({ page }) => {
    // attach console and error listeners for diagnostics
    page.on('console', (msg) => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', (err) => console.log('PAGE ERROR:', err.message));

    await login(page);

    // Open create quotation page
    await page.goto('http://localhost:8009/admin/quotations/create');
    await page.waitForLoadState('networkidle');

    // Ensure there's at least one quotation item; add if necessary
    const productSelects = await page.locator('select[id*="product_id"]').count();
    if (productSelects === 0) {
      // Click the repeater add button (Indonesian label used elsewhere in tests)
      try {
        await page.click('button:has-text("Tambahkan ke quotation item")');
      } catch (e) {
        // fallback: click any button with 'Tambahkan' text
        await page.click('button:has-text("Tambahkan")');
      }
      await page.waitForTimeout(800);
    }

    // Select the first product using Choices.js dropdown associated with product select
    const productChoices = page.locator('.choices').filter({ has: page.locator('select[id*="product_id"]') }).first();
    await productChoices.waitFor({ state: 'visible', timeout: 10000 });
    await productChoices.click();
    await page.waitForTimeout(500);

    // Click first option
    const dropdown = productChoices.locator('.choices__list--dropdown');
    if (await dropdown.isVisible()) {
      const option = dropdown.locator('.choices__item').first();
      await option.click();
    } else {
      // fallback: press Enter to accept first option
      await page.keyboard.press('Enter');
    }

    // Try to trigger the underlying select change if needed (fallback)
    const select = page.locator('select[id*="product_id"]').first();
    try {
      const opt = await select.locator('option').nth(1).getAttribute('value');
      if (opt) {
        await select.selectOption(opt);
        // dispatch change event to ensure Livewire picks it up
        await select.evaluate((el) => {
          el.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
    } catch (e) {
      // ignore if select not found or no option
    }

    // Wait for the Stock per Warehouse field (input/textarea) to be present
    const stockTextarea = page.getByRole('textbox', { name: 'Stock per Warehouse' }).first();
    try {
      await stockTextarea.waitFor({ state: 'visible', timeout: 8000 });
    } catch (e) {
      console.log('stock_info field not found or not visible after waiting');
      throw e;
    }

    // Read the value and assert it's not empty; support inputValue() and textContent() fallback
    let val = '';
    try {
      val = await stockTextarea.inputValue();
    } catch (e) {
      try {
        val = await stockTextarea.textContent();
      } catch (e2) {
        val = '';
      }
    }
    // If still empty, wait a bit more for hydration
    if (!val) {
      await page.waitForTimeout(800);
      val = await stockTextarea.inputValue();
    }
    console.log('Stock info value:', val);
    if (!val) {
      // save diagnostics
      const html = await page.content();
      const screenshotPath = `test-results/stock-info-failure-${Date.now()}.png`;
      const htmlPath = `test-results/stock-info-failure-${Date.now()}.html`;
      try {
        await page.screenshot({ path: screenshotPath, fullPage: true });
        fs.writeFileSync(htmlPath, html);
        console.log('Saved diagnostics:', screenshotPath, htmlPath);
      } catch (e) {
        console.log('Failed to save diagnostics:', e.message);
      }
    }
    expect(val).toBeTruthy();
    expect(val.length).toBeGreaterThan(0);
  });
});
