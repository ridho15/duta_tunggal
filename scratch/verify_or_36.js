import { chromium } from '@playwright/test';
import path from 'path';

const artifactDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/454ff04d-d356-4453-9db9-86c8a61104d5';
const baseUrl = 'http://localhost:8009';

async function verifyOrderRequest36() {
  console.log('Launching browser to verify Order Request #36 edit page...');

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,1100'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });

  const page = await context.newPage();

  // Login
  await page.goto(`${baseUrl}/dev-autologin`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await new Promise(r => setTimeout(r, 1000));

  // Navigate to Edit page 36
  await page.goto(`${baseUrl}/admin/order-requests/36/edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#order-request-next-app', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
  await new Promise(r => setTimeout(r, 1000));

  console.log('Edit page for Order Request #36 loaded successfully!');

  const screenshotPath = path.join(artifactDir, 'uat_verified_or_36_edit.png');
  await page.screenshot({ path: screenshotPath, fullPage: true });
  console.log('Screenshot saved to:', screenshotPath);

  await browser.close();
}

verifyOrderRequest36().catch(err => {
  console.error('Error verifying OR 36:', err);
  process.exit(1);
});
