const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
    viewport: { width: 1440, height: 900 }
  });
  const page = await context.newPage();
  
  // Login first
  await page.goto('http://localhost:8009/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[type="email"]', 'ralamzah@gmail.com');
  await page.fill('input[type="password"]', 'ridho123');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  console.log('Logged in, URL:', page.url());

  await page.goto('http://localhost:8009/admin/reports/balance-sheets');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: '/tmp/bs-before.png', fullPage: false });
  console.log('Before screenshot saved');
  
  const btns = await page.getByRole('button', { name: /tampilkan laporan/i }).all();
  if (btns.length > 0 && await btns[0].isVisible()) {
    await btns[0].click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    console.log('Report generated');
  }
  await page.screenshot({ path: '/tmp/bs-after.png', fullPage: true });
  console.log('After screenshot saved');
  
  const codeStyle = await page.evaluate(() => {
    const el = document.querySelector('.fr-row-code');
    if (!el) return {error: 'not found'};
    const s = window.getComputedStyle(el);
    return { color: s.color, bg: s.backgroundColor, fontSize: s.fontSize };
  });
  console.log('fr-row-code:', JSON.stringify(codeStyle));
  
  const rowNameStyle = await page.evaluate(() => {
    const el = document.querySelector('.fr-row-name');
    if (!el) return {error: 'not found'};
    const s = window.getComputedStyle(el);
    return { color: s.color, bg: s.backgroundColor, fontSize: s.fontSize };
  });
  console.log('fr-row-name:', JSON.stringify(rowNameStyle));

  const rowStyle = await page.evaluate(() => {
    const el = document.querySelector('.fr-row');
    if (!el) return {error: 'not found'};
    const s = window.getComputedStyle(el);
    return { color: s.color, bg: s.backgroundColor, fontSize: s.fontSize, padding: s.padding };
  });
  console.log('fr-row:', JSON.stringify(rowStyle));

  await browser.close();
})();
