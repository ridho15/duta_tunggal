import { chromium } from '@playwright/test';
import path from 'path';

const artifactDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/454ff04d-d356-4453-9db9-86c8a61104d5';
const baseUrl = 'http://localhost:8009';

async function verifyQoAndSoWidened() {
  console.log('Launching browser to capture widened Quotation and Sales Order layouts...');

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,1100'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });

  const page = await context.newPage();

  // Helper for SearchableSelect
  async function selectSearchableOption(labelContains, optionIndex = 0) {
    await page.evaluate((labelTxt) => {
      const labels = Array.from(document.querySelectorAll('label'));
      const targetLabel = labels.find((l) => l.innerText.toLowerCase().includes(labelTxt.toLowerCase()));
      if (targetLabel && targetLabel.parentElement) {
        const trigger = targetLabel.parentElement.querySelector('.cursor-pointer');
        if (trigger) trigger.click();
      }
    }, labelContains);
    await new Promise(r => setTimeout(r, 600));

    await page.evaluate((idx) => {
      const options = Array.from(document.querySelectorAll('.max-h-60 .cursor-pointer'));
      if (options.length > idx) {
        options[idx].click();
      } else if (options.length > 0) {
        options[0].click();
      }
    }, optionIndex);
    await new Promise(r => setTimeout(r, 600));
  }

  // 1. Verify Quotation Create Page
  console.log('Navigating to Quotation Create Page...');
  await page.goto(`${baseUrl}/dev-autologin-qo`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#quotation-next-app', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
  await new Promise(r => setTimeout(r, 800));

  console.log('Filling Quotation form...');
  await selectSearchableOption('Customer', 0);
  await selectSearchableOption('Cabang', 0);
  await selectSearchableOption('Produk', 0);

  // Set Qty and Diskon
  await page.evaluate(() => {
    const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
    if (qtyInput) {
      qtyInput.value = '5';
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await new Promise(r => setTimeout(r, 800));

  const qoScreenshotPath = path.join(artifactDir, 'uat_qo_widened_layout.png');
  await page.screenshot({ path: qoScreenshotPath, fullPage: true });
  console.log('Quotation Widened Screenshot saved to:', qoScreenshotPath);

  // 2. Verify Sales Order Create Page
  console.log('Navigating to Sales Order Create Page...');
  await page.goto(`${baseUrl}/dev-autologin-so`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#sale-order-next-app', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
  await new Promise(r => setTimeout(r, 800));

  console.log('Filling Sales Order form...');
  await selectSearchableOption('Customer', 0);
  await selectSearchableOption('Cabang', 0);
  await selectSearchableOption('Produk', 0);

  // Set Qty
  await page.evaluate(() => {
    const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
    if (qtyInput) {
      qtyInput.value = '8';
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await new Promise(r => setTimeout(r, 800));

  const soScreenshotPath = path.join(artifactDir, 'uat_so_widened_layout.png');
  await page.screenshot({ path: soScreenshotPath, fullPage: true });
  console.log('Sales Order Widened Screenshot saved to:', soScreenshotPath);

  await browser.close();
}

verifyQoAndSoWidened().catch(err => {
  console.error('Error verifying widened layouts:', err);
  process.exit(1);
});
