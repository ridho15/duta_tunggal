import { chromium } from '@playwright/test';
import path from 'path';

const artifactDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/454ff04d-d356-4453-9db9-86c8a61104d5';
const baseUrl = 'http://localhost:8009';

async function verifyPoWidened() {
  console.log('Launching browser to capture widened Purchase Order layout...');

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,1100'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });

  const page = await context.newPage();

  // 1. Login & Navigate to Create PO
  await page.goto(`${baseUrl}/dev-autologin-po`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#purchase-order-next-app', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
  await new Promise(r => setTimeout(r, 1000));

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

  // 2. Select Supplier in Header
  console.log('Selecting Supplier in header...');
  await selectSearchableOption('Supplier', 0);

  // 3. Select Cabang in Header
  console.log('Selecting Cabang in header...');
  await selectSearchableOption('Cabang', 0);

  // 4. Expand Item #1
  await page.evaluate(() => {
    const headerRow = document.querySelector('.bg-gray-50\\/70');
    if (headerRow) headerRow.click();
  });
  await new Promise(r => setTimeout(r, 600));

  // 5. Select Product for Item #1
  console.log('Selecting Product for Item #1...');
  await selectSearchableOption('Produk', 0);

  // 6. Set Price, Qty, and Diskon
  await page.evaluate(() => {
    const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
    if (qtyInput) {
      qtyInput.value = '4';
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  await new Promise(r => setTimeout(r, 1000));

  const screenshotPath = path.join(artifactDir, 'uat_po_widened_layout.png');
  await page.screenshot({ path: screenshotPath, fullPage: true });
  console.log('Widened PO Layout Screenshot saved to:', screenshotPath);

  await browser.close();
}

verifyPoWidened().catch(err => {
  console.error('Error verifying widened PO layout:', err);
  process.exit(1);
});
