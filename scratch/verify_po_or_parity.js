import { chromium } from '@playwright/test';
import path from 'path';

const artifactDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/454ff04d-d356-4453-9db9-86c8a61104d5';
const baseUrl = 'http://localhost:8009';

async function verifyPoOrParity() {
  console.log('Launching browser with Chrome channel...');

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,1100'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });

  const page = await context.newPage();

  page.on('console', (msg) => console.log('BROWSER CONSOLE:', msg.type(), msg.text()));
  page.on('pageerror', (err) => console.log('BROWSER ERROR:', err.message));

  console.log('1. Navigating to Purchase Order Create page via dev-autologin-po...');
  await page.goto(`${baseUrl}/dev-autologin-po`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#purchase-order-next-app', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
  await new Promise((r) => setTimeout(r, 1000));

  console.log('2. Clicking Order Request (OR) radio button...');
  await page.evaluate(() => {
    const radios = Array.from(document.querySelectorAll('input[type="radio"]'));
    const orRadio = radios.find((r) => r.parentElement && r.parentElement.innerText.includes('Order Request'));
    if (orRadio) {
      orRadio.click();
    }
  });

  await new Promise((r) => setTimeout(r, 1000));

  console.log('3. Opening Order Request SearchableSelect dropdown...');
  await page.evaluate(() => {
    const dropdownTriggers = Array.from(document.querySelectorAll('.cursor-pointer'));
    const orTrigger = dropdownTriggers.find((t) => t.innerText.includes('Pilih Order Request'));
    if (orTrigger) {
      orTrigger.click();
    }
  });

  await new Promise((r) => setTimeout(r, 800));

  console.log('4. Selecting the first available Order Request...');
  await page.evaluate(() => {
    const options = Array.from(document.querySelectorAll('.max-h-60 .cursor-pointer'));
    if (options.length > 0) {
      console.log('Clicking OR Option:', options[0].innerText);
      options[0].click();
    }
  });

  await new Promise((r) => setTimeout(r, 3000));

  console.log('5. Capturing PO with OR Reference screenshot...');
  const outPath = path.join(artifactDir, 'uat_po_or_referenced.png');
  await page.screenshot({ path: outPath, fullPage: true });

  console.log(`Saved screenshot to: ${outPath}`);
  await browser.close();
}

verifyPoOrParity().catch((err) => {
  console.error('Error during verification:', err);
  process.exit(1);
});
