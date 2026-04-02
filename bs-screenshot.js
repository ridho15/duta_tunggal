const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    storageState: 'playwright/.auth/user.json',
    viewport: { width: 1440, height: 900 }
  });
  const page = await context.newPage();
  
  await page.goto('http://localhost:8009/admin/reports/balance-sheets');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: '/tmp/bs-before.png', fullPage: false });
  
  const btns = await page.getByRole('button', { name: /tampilkan laporan/i }).all();
  if (btns.length > 0 && await btns[0].isVisible()) {
    await btns[0].click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
  }
  await page.screenshot({ path: '/tmp/bs-after.png', fullPage: true });
  
  const bodyHtml = await page.locator('.fr-body').innerHTML().catch(() => '(not found)');
  fs.writeFileSync('/tmp/bs-body.html', bodyHtml.substring(0, 5000));
  
  // Get computed styles of .fr-row-code
  const codeStyle = await page.evaluate(() => {
    const el = document.querySelector('.fr-row-code');
    if (!el) return null;
    const s = window.getComputedStyle(el);
    return { color: s.color, background: s.backgroundColor, fontSize: s.fontSize, fontWeight: s.fontWeight, padding: s.padding };
  });
  console.log('fr-row-code style:', JSON.stringify(codeStyle));
  
  const rowStyle = await page.evaluate(() => {
    const el = document.querySelector('.fr-row');
    if (!el) return null;
    const s = window.getComputedStyle(el);
    return { color: s.color, background: s.backgroundColor, fontSize: s.fontSize };
  });
  console.log('fr-row style:', JSON.stringify(rowStyle));

  await browser.close();
  console.log('Done');
})();
