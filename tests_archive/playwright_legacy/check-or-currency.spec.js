const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost:8009';

test('Order Request item shows currency symbol on view page', async ({ page }) => {
  await page.goto(`${BASE}/_dusk/login/1`, { waitUntil: 'networkidle' });
  await page.goto(`${BASE}/admin/order-requests/20`, { waitUntil: 'networkidle' });
  await page.waitForSelector('form, .fi-fo-repeater-item, [data-testid="filament-content"]', { timeout: 10000 });
  const html = await page.content();
  const hasRp = html.includes('Rp') || html.includes('Rupiah') || html.includes('IDR');
  await page.screenshot({ path: 'playwright-report/or-view-order-20.png', fullPage: true });
  expect(hasRp).toBe(true);
});
