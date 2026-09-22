import { test, expect } from '@playwright/test';

const BASE = process.env.BASE_URL ?? 'http://localhost:8009';

test('Order Request item shows currency symbol on view page', async ({ page }) => {
  // Login via dusk helper route (development/test route)
  await page.goto(`${BASE}/_dusk/login/1`, { waitUntil: 'networkidle' });

  // Open the order request view page
  await page.goto(`${BASE}/admin/order-requests/20`, { waitUntil: 'networkidle' });

  // Wait for Filament form or repeater to render
  await page.waitForSelector('form, .fi-fo-repeater-item, [data-testid="filament-content"]', { timeout: 10000 });

  const html = await page.content();
  const hasRp = html.includes('Rp') || html.includes('Rupiah') || html.includes('IDR');

  // Save a screenshot for manual inspection
  await page.screenshot({ path: 'playwright-report/or-view-order-20.png', fullPage: true });

  expect(hasRp).toBe(true);
});
